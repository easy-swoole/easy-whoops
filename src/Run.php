<?php

namespace EasySwoole\Whoops;

use EasySwoole\Component\Context\Exception\ModifyError;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;

use EasySwoole\Utility\Random;
use EasySwoole\Whoops\Exception\ErrorException;
use EasySwoole\Whoops\Exception\Inspector;
use EasySwoole\Whoops\Handler\CallbackHandler;
use EasySwoole\Whoops\Handler\Handler;
use EasySwoole\Whoops\Handler\HandlerInterface;
use EasySwoole\Whoops\Util\Misc;
use EasySwoole\Whoops\Util\SeparateEngine;
use EasySwoole\Whoops\Util\SeparateRender;
use EasySwoole\Whoops\Util\SystemFacade;
use Exception;
use Throwable;
use InvalidArgumentException;

/**
 * Class WhoopsRunner
 * @package EasySwoole\Whoops
 */
class Run implements RunInterface
{
    private $isRegistered;
    private $allowQuit = false;
    private $sendOutput = true;
    private $canThrowExceptions = true;

    private $system;
    private $sendHttpCode = 500;
    private $silencedPatterns = [];

    /** @var HandlerInterface[] */
    private $handlerStack = [];

    /**
     * Run constructor.
     * @param SystemFacade|null $system
     * @throws Exception
     */
    public function __construct(SystemFacade $system = null)
    {
        // 当前是否在EASYSWOOLE环境 由于注册时服务尚未启动 直接die输出
        if (!defined("EASYSWOOLE_ROOT")) {
            die("\nEasyWhoops must run in EasySwoole framework! the EasyWhoops extension is in development phase, do not enable it in production environment!\n\n");
        }
        $this->system = $system ?: new SystemFacade;
    }

    /**
     * 向当前堆栈推入一个处理句柄
     * @param Callable|HandlerInterface $handler
     * @return $this|Run
     */
    public function pushHandler($handler)
    {
        if (is_callable($handler)) {
            $handler = new CallbackHandler($handler);
        }

        if (!$handler instanceof HandlerInterface) {
            throw new InvalidArgumentException(
                "Argument to " . __METHOD__ . " must be a callable, or instance of "
                . "Whoops\\Handler\\HandlerInterface"
            );
        }

        $this->handlerStack[] = $handler;
        return $this;
    }

    /**
     * 从当前堆栈取出一个处理句柄
     * @return mixed|HandlerInterface|null
     */
    public function popHandler()
    {
        return array_pop($this->handlerStack);
    }

    /**
     * 获取当前处理句柄堆栈
     * @return array|HandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlerStack;
    }

    /**
     * 清空处理堆栈
     * @return $this|Run
     */
    public function clearHandlers()
    {
        $this->handlerStack = [];
        return $this;
    }

    /**
     * 创建一个检查者
     * 检查者的作用是将异常格式化为handle可以快速访问的格式
     * @param $exception
     * @return Inspector
     */
    private function getInspector($exception)
    {
        return new Inspector($exception);
    }

    /**
     * 注册错误处理器
     * @return Run
     */
    public function register(): Run
    {
        if (!$this->isRegistered) {

            // Workaround PHP bug 42098
            // https://bugs.php.net/bug.php?id=42098

            class_exists("\\EasySwoole\\Whoops\\Exception\\ErrorException");
            class_exists("\\EasySwoole\\Whoops\\Exception\\FrameCollection");
            class_exists("\\EasySwoole\\Whoops\\Exception\\Frame");
            class_exists("\\EasySwoole\\Whoops\\Exception\\Inspector");

            $this->system->setErrorHandler([$this, self::ERROR_HANDLER]);
            $this->system->setExceptionHandler([$this, self::EXCEPTION_HANDLER]);
            $this->system->registerShutdownFunction([$this, self::SHUTDOWN_HANDLER]);

            $this->isRegistered = true;
        }

        return $this;
    }

    /**
     * 注册后暂不支持撤销
     * @return bool|Run
     */
    public function unregister()
    {
        return true;
    }

    /**
     * 无论何时都不允许处理器执行exit
     * @param null $exit
     * @return bool
     */
    public function allowQuit($exit = null)
    {
        $this->allowQuit = false;
        return false;
    }

    /**
     * 符合表达式的错误都将被忽略
     * @param array|string $patterns
     * @param int $levels
     * @return $this|Run
     */
    public function silenceErrorsInPaths($patterns, $levels = 10240)
    {
        $this->silencedPatterns = array_merge(
            $this->silencedPatterns,
            array_map(
                function ($pattern) use ($levels) {
                    return [
                        "pattern" => $pattern,
                        "levels" => $levels,
                    ];
                },
                (array)$patterns
            )
        );
        return $this;
    }

    /**
     * 设置错误时的HTTP状态码
     * @param null $code
     * @return bool|false|int|null
     */
    public function sendHttpCode($code = null)
    {
        if (func_num_args() == 0) {
            return $this->sendHttpCode;
        }

        if (!$code) {
            return $this->sendHttpCode = false;
        }

        if ($code === true) {
            $code = 500;
        }

        if ($code < 400 || 600 <= $code) {
            throw new InvalidArgumentException(
                "Invalid status code '$code', must be 4xx or 5xx"
            );
        }

        return $this->sendHttpCode = $code;
    }

    /**
     * 是否允许输出错误
     * 如果不允许输出则会返回响应文本
     * @param null $send
     * @return bool
     */
    public function writeToOutput($send = null)
    {
        if (func_num_args() == 0) {
            return $this->sendOutput;
        }

        return $this->sendOutput = (bool)$send;
    }


    /**
     * 获取被忽略的路径
     * @return array
     */
    public function getSilenceErrorsInPaths()
    {
        return $this->silencedPatterns;
    }

    /**
     * 异常处理
     * PrettyPageHandler 已经实现了在内部使用协程安全的独立进程渲染
     * @param Throwable $exception
     * @return string|void
     * @throws Exception
     */
    public function handleException($exception)
    {
        // 按注册的处理程序的相反顺序遍历它们，并传递异常
        $inspector = $this->getInspector($exception);

        // 以防没有处理程序
        $handlerResponse = null;
        $handlerContentType = null;

        foreach (array_reverse($this->handlerStack) as $handler) {

            /** @var HandlerInterface $handler */
            $handler->setRun($this);
            $handler->setInspector($inspector);
            $handler->setException($exception);
            $handlerResponse = $handler->handle();

            // 获取响应内容并为输出到客户端做准备
            $output = $handler->getHandleContent();
            $handlerContentType = method_exists($handler, 'contentType') ? $handler->contentType() : null;
            $willQuit = $handlerResponse == Handler::QUIT && $this->allowQuit();
            $willSkip = $handlerResponse == Handler::LAST_HANDLER;

            // 如果当前允许输出到客户端 则进行内容输出
            if ($this->writeToOutput()) {
                if ($handlerContentType) $this->system->setHttpHeader('Content-Type', $handlerContentType);
                if ($this->sendHttpCode()) $this->system->setHttpResponseCode($this->sendHttpCode());
                $this->writeToOutputNow($output);  // 这里会智能选择控制台或Response进行输出
            }
        }
    }

    /**
     * 将一个Error转换为ErrorException并执行异常处理
     * @param int $level
     * @param string $message
     * @param null $file
     * @param null $line
     * @return bool
     * @throws ErrorException
     * @throws Exception
     */
    public function handleError($level, $message, $file = null, $line = null)
    {
        if ($level & $this->system->getErrorReportingLevel()) {
            foreach ($this->silencedPatterns as $entry) {
                $pathMatches = (bool)preg_match($entry["pattern"], $file);
                $levelMatches = $level & $entry["levels"];
                if ($pathMatches && $levelMatches) {
                    return true;
                }
            }

            $exception = new ErrorException($message, /*code*/ $level, /*severity*/ $level, $file, $line);
            if ($this->canThrowExceptions) {
                throw $exception;
            } else {
                $this->handleException($exception);
            }
            return true;
        }

        return false;
    }

    /**
     * 处理shutdown方法让他看起来"像"error被触发的样子
     * @throws ErrorException
     */
    public function handleShutdown()
    {
        $this->canThrowExceptions = false;

        $error = $this->system->getLastError();
        if ($error && Misc::isLevelFatal($error['type'])) {
            $this->handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * 进行一次输出
     * 会判断当前是否有Response 如果没有则意味着只能输出到控制台
     * @param $output
     * @return $this
     */
    private function writeToOutputNow($output)
    {
        // 如果当前还没有Response对象 则输出到控制台 否则输出到Response
        if (Misc::isCommandLine()) {
            echo $output;
        } else {
            $this->system->sendHttpBody($output);
        }
        return $this;
    }

    /**
     * 拦截onRequest使Whoops能拿到全局的请求和响应对象
     * @param Request $request
     * @param Response $response
     * @throws ModifyError
     */
    public static function attachRequest(Request $request, Response $response)
    {
        Misc::hookOnRequest($request, $response);
    }

    /**
     * 如果需要PuttyHandle则需要先行注册模板引擎
     * @param $server
     */
    public static function attachTemplateRender($server)
    {
        $separateRender = SeparateRender::getInstance();
        $separateRender->getConfig()->setTempDir(sys_get_temp_dir());
        $separateRender->getConfig()->setRender(new SeparateEngine);
        $separateRender->attachServer($server);
    }

}