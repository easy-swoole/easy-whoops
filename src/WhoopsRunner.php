<?php

namespace EasySwoole\Whoops;

use EasySwoole\Whoops\Util\Misc;
use EasySwoole\Whoops\Util\SystemFacade;

use Whoops\Exception\ErrorException;
use Whoops\Exception\Inspector;
use Whoops\Handler\CallbackHandler;
use Whoops\Handler\Handler;
use Whoops\Handler\HandlerInterface;
use Whoops\RunInterface;

use InvalidArgumentException;

/**
 * Class WhoopsRunner
 * @package EasySwoole\Whoops
 */
class WhoopsRunner implements RunInterface
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

    public function __construct(SystemFacade $system = null)
    {
        $this->system = $system ?: new SystemFacade;
    }

    /**
     * 向当前堆栈推入一个处理句柄
     * @param Callable|HandlerInterface $handler
     * @return $this|WhoopsRunner
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
     * @return $this|WhoopsRunner
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
     * @return WhoopsRunner
     */
    public function register(): WhoopsRunner
    {
        if (!$this->isRegistered) {

            // Workaround PHP bug 42098
            // https://bugs.php.net/bug.php?id=42098

            class_exists("\\Whoops\\Exception\\ErrorException");
            class_exists("\\Whoops\\Exception\\FrameCollection");
            class_exists("\\Whoops\\Exception\\Frame");
            class_exists("\\Whoops\\Exception\\Inspector");

            $this->system->setErrorHandler([$this, self::ERROR_HANDLER]);
            $this->system->setExceptionHandler([$this, self::EXCEPTION_HANDLER]);
            $this->system->registerShutdownFunction([$this, self::SHUTDOWN_HANDLER]);

            $this->isRegistered = true;
        }

        return $this;
    }

    /**
     * 注册后暂不支持撤销
     * @return bool|WhoopsRunner
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
     * @return $this|WhoopsRunner
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
     * @param \Throwable $exception
     * @return string|void
     */
    public function handleException($exception)
    {
        // 按注册的处理程序的相反顺序遍历它们，并传递异常
        $inspector = $this->getInspector($exception);

        // 捕获处理异常时产生的输出，我们可能希望直接将其发送到客户端，或者静默地返回它。
        $this->system->startOutputBuffering();

        // 以防没有处理程序
        $handlerResponse = null;
        $handlerContentType = null;

        foreach (array_reverse($this->handlerStack) as $handler) {

            $handler->setRun($this);
            $handler->setInspector($inspector);
            $handler->setException($exception);

            // 内置的处理句柄都不依赖于该方法设置的handle 但为了避免对第三方处理程序造成破坏所以保留该项
            $handlerResponse = $handler->handle($exception);

            // 收集contentType以便接下来发送
            $handlerContentType = method_exists($handler, 'contentType') ? $handler->contentType() : null;

            // 如果处理器已经把异常处理掉了并希望退出(Handler::QUIT) 或 不继续执行后续的处理器(Handler::LAST_HANDLER)
            if (in_array($handlerResponse, [Handler::LAST_HANDLER, Handler::QUIT])) {
                break;
            }

            // 拿到处理阶段写入缓冲区的所有输出文本
            $willQuit = $handlerResponse == Handler::QUIT && $this->allowQuit();
            $output = $this->system->cleanOutputBuffer();

            // 如果允许输出则输出到Response 否则跳过该步骤直接返回
            if ($this->writeToOutput()) {
                // @todo Might be able to clean this up a bit better
                if ($willQuit) {
                    // 在发送输出之前清除所有其他输出缓冲区
                    while ($this->system->getOutputBufferLevel() > 0) {
                        $this->system->endOutputBuffering();
                    }

                    // 如果允许则发送需要的响应头
                    if (Misc::canSendHeaders() && $handlerContentType) {
                        header("Content-Type: {$handlerContentType}");
                    }
                }

                $this->writeToOutputNow($output);
            }

            if ($willQuit) {
                // HHVM fix for https://github.com/facebook/hhvm/issues/4055
                $this->system->flushOutputBuffer();
                $this->system->stopExecution(1);
            }

            return $output;
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
     * 立即输出给客户端
     * @param $output
     * @return $this
     */
    private function writeToOutputNow($output)
    {
        if ($this->sendHttpCode() && \Whoops\Util\Misc::canSendHeaders()) {
            $this->system->setHttpResponseCode($this->sendHttpCode());
        }

        echo $output;
        return $this;
    }

}