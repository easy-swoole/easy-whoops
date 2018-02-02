<?php

namespace EasySwoole\Whoops;

use EasySwoole\Core\Component\Logger;
use EasySwoole\Core\Http\Response;
use Whoops\Exception\ErrorException;
use Whoops\Exception\Inspector;
use Whoops\Handler\CallbackHandler;
use Whoops\Handler\Handler;
use Whoops\Handler\HandlerInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\RunInterface;
use Whoops\Util\Misc;
use EasySwoole\Whoops\Util\SystemFacade as easyFacade;

/**
 * EasyWhoops Run Handle
 * Class Handle
 * @author  : evalor <master@evalor.cn>
 * @package EasySwoole\Whoops
 */
class Runner implements RunInterface
{

    /**
     * 异常渲染 Handle 存放
     * @var HandlerInterface[] $handlerStack
     */
    private $handlerStack     = [];
    private $silencedPatterns = [];

    private $system;
    private $allowQuit          = true;
    private $sendOutput         = true;
    private $sendHttpCode       = 500;
    private $isRegistered       = false;
    private $canThrowExceptions = true;

    private $options = [
        'auto_conversion' => true,                    // 开启AJAX模式下自动转换为JSON输出
        'detailed'        => true,                    // 开启详细错误日志输出
        'information'     => '发生内部错误,请稍后再试'   // 不开启详细输出的情况下 输出的提示文本
    ];

    /**
     * Runner constructor.
     * @param array $options
     */
    function __construct(array $options = [])
    {
        $this->system = new easyFacade;

        if (!$options) {
            $this->options = array_merge($this->options, $options);
        }
    }

    /**
     * Pushes a handler to the end of the stack
     * @param Callable|\Whoops\Handler\HandlerInterface $handler
     * @return Runner
     * @author : evalor <master@evalor.cn>
     */
    public function pushHandler($handler)
    {
        if (is_callable($handler)) {
            $handler = new CallbackHandler($handler);
        }

        if (!$handler instanceof HandlerInterface) {
            throw new \InvalidArgumentException(
                "Argument to " . __METHOD__ . " must be a callable, or instance of " . "Whoops\\Handler\\HandlerInterface"
            );
        }

        $this->handlerStack[] = $handler;
        return $this;
    }

    /**
     * Removes the last handler in the stack and returns it.
     * @author : evalor <master@evalor.cn>
     * @return mixed|null|HandlerInterface
     */
    public function popHandler()
    {
        return array_pop($this->handlerStack);
    }

    /**
     * Returns an array with all handlers
     * in the order they were added to the stack.
     * @author : evalor <master@evalor.cn>
     * @return array|HandlerInterface[]
     */
    public function getHandlers()
    {
        return $this->handlerStack;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Get the system instance
     * @author : evalor <master@evalor.cn>
     * @return easyFacade
     */
    public function getSystems()
    {
        return $this->system;
    }

    /**
     * Clears all handlers in the handlerStack, including the default PrettyPage handler.
     * @author : evalor <master@evalor.cn>
     * @return $this|\Whoops\Run
     */
    public function clearHandlers()
    {
        $this->handlerStack = [];
        return $this;
    }

    /**
     * Registers this instance as an error handler.
     * @author : evalor <master@evalor.cn>
     * @return Runner
     */
    public function register()
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
     * UnRegisters all handlers registered by this Whoops\Run instance
     * @author : evalor <master@evalor.cn>
     * @return $this|\Whoops\Run
     */
    public function unregister()
    {
        if ($this->isRegistered) {
            $this->system->restoreExceptionHandler();
            $this->system->restoreErrorHandler();
            $this->isRegistered = false;
        }
        return $this;
    }

    /**
     * Should Whoops allow Handlers to force the script to quit?
     * @param null $exit
     * @author : evalor <master@evalor.cn>
     * @return bool
     */
    public function allowQuit($exit = null)
    {
        if (func_num_args() == 0) {
            return $this->allowQuit;
        }
        return $this->allowQuit = (bool)$exit;
    }

    /**
     * Silence particular errors in particular files
     * @param array|string $patterns
     * @param int          $levels
     * @author : evalor <master@evalor.cn>
     * @return $this|\Whoops\Run
     */
    public function silenceErrorsInPaths($patterns, $levels = 10240)
    {
        $this->silencedPatterns = array_merge(
            $this->silencedPatterns,
            array_map(
                function ($pattern) use ($levels) {
                    return [
                        "pattern" => $pattern,
                        "levels"  => $levels,
                    ];
                },
                (array)$patterns
            )
        );
        return $this;
    }

    /**
     * Returns an array with silent errors in path configuration
     * @author : evalor <master@evalor.cn>
     * @return array
     */
    public function getSilenceErrorsInPaths()
    {
        return $this->silencedPatterns;
    }

    /**
     * Should Whoops send HTTP error code to the browser if possible?
     * Whoops will by default send HTTP code 500, but you may wish to
     * use 502, 503, or another 5xx family code.
     * @param null $code
     * @author : evalor <master@evalor.cn>
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
            throw new \InvalidArgumentException(
                "Invalid status code '$code', must be 4xx or 5xx"
            );
        }
        return $this->sendHttpCode = $code;
    }

    /**
     * Should Whoops push output directly to the client?
     * If this is false, output will be returned by handleException
     * @param null $send
     * @author : evalor <master@evalor.cn>
     * @return bool
     */
    public function writeToOutput($send = null)
    {
        if (func_num_args() == 0) {
            return $this->sendOutput;
        }
        return $this->sendOutput = (bool)$send;
    }

    // + ------------------------------------------
    // + Exception Handle
    // + ------------------------------------------

    /**
     * Handles an exception, ultimately generating a Whoops error page.
     * @param \Throwable $exception
     * @author : evalor <master@evalor.cn>
     */
    public function handleException($exception)
    {
        $inspector = $this->getInspector($exception);

        $handlerResponse    = null;
        $handlerContentType = null;
        $this->system->startOutputBuffering();

        // 如果当前没有Response 即不能输出到浏览器的情况 强制应用PlainTextHandler
        if (!$this->system->ResIns()) {
            $this->clearHandlers();
            $this->pushHandler(new PlainTextHandler);
        }

        //  AJAX自动返回Json
        if (Misc::isAjaxRequest() && $this->options['auto_conversion']) {
            $this->pushHandler(new JsonResponseHandler);
        }

        // 关闭详细错误输出
        if (!$this->options['detailed']) {
            $this->clearHandlers();
            $handlerContentType = 'text/html;charset=utf-8';
            echo $this->options['information'];
        }

        // 反向的遍历handles进行输出设置
        foreach (array_reverse($this->handlerStack) as $handler) {
            /* @var HandlerInterface|PrettyPageHandler $handler */
            if ($handler instanceof PrettyPageHandler) {
                $handler->handleUnconditionally(true);
            }

            $handler->setRun($this);
            $handler->setInspector($inspector);
            $handler->setException($exception);
            $handlerResponse    = $handler->handle($exception);
            $handlerContentType = method_exists($handler, 'contentType') ? $handler->contentType() : null;

            if (in_array($handlerResponse, [Handler::LAST_HANDLER, Handler::QUIT])) {
                break;
            }
        }

        $output = $this->system->cleanOutputBuffer();
        $this->writeToOutputNow($output, $handlerContentType);
    }

    /**
     * Converts generic PHP errors to \ErrorException instances, before passing them off to be handled.
     * This method MUST be compatible with set_error_handler.
     * @param int    $level
     * @param string $message
     * @param null   $file
     * @param null   $line
     * @author : evalor <master@evalor.cn>
     * @return bool
     * @throws ErrorException
     */
    public function handleError($level, $message, $file = null, $line = null)
    {
        if ($level & $this->system->getErrorReportingLevel()) {
            foreach ($this->silencedPatterns as $entry) {
                $pathMatches  = (bool)preg_match($entry["pattern"], $file);
                $levelMatches = $level & $entry["levels"];
                if ($pathMatches && $levelMatches) {
                    return true;
                }
            }
            $exception = new ErrorException($message, $level, $level, $file, $line);
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
     * Special case to deal with Fatal errors and the like.
     * @author : evalor <master@evalor.cn>
     * @throws ErrorException
     */
    public function handleShutdown()
    {
        $this->canThrowExceptions = false;

        $error = $this->system->getLastError();
        if ($error && Misc::isLevelFatal($error['type'])) {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * Get the inspector with exception.
     * @param $exception
     * @author : evalor <master@evalor.cn>
     * @return Inspector
     */
    private function getInspector($exception)
    {
        return new Inspector($exception);
    }

    /**
     * Auto write Output to Browser or Console
     * @param $output
     * @param $handlerContentType
     * @author : evalor <master@evalor.cn>
     */
    private function writeToOutputNow($output, $handlerContentType)
    {
        /** @var Response $Response */
        $Response = $this->system->ResIns();
        // If can do browser output
        if ($Response instanceof Response) {
            if ($this->sendHttpCode()) {
                $Response->withStatus($this->sendHttpCode());
            }
            $Response->withHeader('Content-Type', $handlerContentType);
            $Response->write($output);
            $Response->response();
            $Response->end(true);
        } else {
            Logger::getInstance()->console($output);
        }
    }
}
