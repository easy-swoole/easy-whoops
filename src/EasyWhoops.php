<?php

namespace EasySwoole\Whoops;

use EasySwoole\Core\Component\Di;
use EasySwoole\Core\Component\SysConst;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;
use EasySwoole\Core\Swoole\ServerManager;
use EasySwoole\Whoops\Instance\ExceptionHandle;
use EasySwoole\Whoops\Instance\Request as SingletonRequest;
use EasySwoole\Whoops\Instance\Response as SingletonResponse;

use Whoops\Exception\Inspector;
use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Util\SystemFacade;
use InvalidArgumentException;
use Whoops\Exception\ErrorException;
use Whoops\Handler\CallbackHandler;
use Whoops\Handler\HandlerInterface;
use Whoops\RunInterface;
use Whoops\Util\Misc;

/**
 * EasyWhoops registrar
 * Class EasyWhoops
 * @author  : evalor <master@evalor.cn>
 * @package EasySwoole\Whoops
 */
class EasyWhoops implements RunInterface
{
    private $isRegistered;
    private $allowQuit  = true;
    private $sendOutput = true;

    /**
     * @var integer|false
     */
    private $sendHttpCode = 500;

    /**
     * @var HandlerInterface[]
     */
    private $handlerStack = [];

    private $silencedPatterns = [];

    private $system;

    public function __construct(SystemFacade $system = null)
    {
        $this->system = $system ?: new SystemFacade;
    }

    /**
     * Singleton request and response
     * @param Request  $Request  the easySwoole Request Object
     * @param Response $Response the easySwoole Response Object
     * @author : evalor <master@evalor.cn>
     */
    static function instanceIO(Request $Request, Response $Response)
    {
        SingletonRequest::getInstance($Request);
        SingletonResponse::getInstance($Response);
    }

    /**
     * Pushes a handler to the end of the stack
     * @throws InvalidArgumentException  If argument is not callable or instance of HandlerInterface
     * @param  Callable|HandlerInterface $handler
     * @return EasyWhoops
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
     * Removes the last handler in the stack and returns it.
     * Returns null if there"s nothing else to pop.
     * @return null|HandlerInterface
     */
    public function popHandler()
    {
        return array_pop($this->handlerStack);
    }

    /**
     * Returns an array with all handlers, in the
     * order they were added to the stack.
     * @return array
     */
    public function getHandlers()
    {
        return $this->handlerStack;
    }

    /**
     * Clears all handlers in the handlerStack, including
     * the default PrettyPage handler.
     * @return EasyWhoops
     */
    public function clearHandlers()
    {
        $this->handlerStack = [];
        return $this;
    }

    /**
     * @param  \Throwable $exception
     * @return Inspector
     */
    private function getInspector($exception)
    {
        return new Inspector($exception);
    }

    /**
     * Registers this instance as an error handler.
     * @return EasyWhoops
     */
    public function register()
    {
        if (!$this->isRegistered) {
            class_exists("\\Whoops\\Exception\\ErrorException");
            class_exists("\\Whoops\\Exception\\FrameCollection");
            class_exists("\\Whoops\\Exception\\Frame");
            class_exists("\\Whoops\\Exception\\Inspector");

            $this->allowQuit = false;

            // Register the exception class in the container of the easyswoole
            $container       = Di::getInstance();
            $ExceptionHandle = new ExceptionHandle;
            $ExceptionHandle->setWhoops($this);

            $container->set(SysConst::ERROR_HANDLER, [$this, self::ERROR_HANDLER]);
            $container->set(SysConst::SHUTDOWN_FUNCTION, [$this, self::SHUTDOWN_HANDLER]);
            $container->set(SysConst::HTTP_EXCEPTION_HANDLER, $ExceptionHandle);

            $this->isRegistered = true;
        }

        return $this;
    }

    /**
     * UnRegisters all handlers registered by this Whoops\Run instance
     * @return EasyWhoops
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
     * @param  bool|int $exit
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
     * @param  array|string $patterns List or a single regex pattern to match
     * @param  int          $levels   Defaults to E_STRICT | E_DEPRECATED
     * @return EasyWhoops
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
     * Should Whoops send HTTP error code to the browser if possible?
     * Whoops will by default send HTTP code 500, but you may wish to
     * use 502, 503, or another 5xx family code.
     * @param bool|int $code
     * @return int|false
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
     * Should Whoops push output directly to the client?
     * If this is false, output will be returned by handleException
     * @param  bool|int $send
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
     * Handles an exception, ultimately generating a Whoops error
     * Discarded in this adapter
     * page.
     * @param  \Throwable $exception
     * @return string     Output generated by handlers
     * @throws \Exception
     */
    public function handleException($exception)
    {
        // Walk the registered handlers in the reverse order
        // they were registered, and pass off the exception
        $inspector = $this->getInspector($exception);

        // Capture output produced while handling the exception,
        // we might want to send it straight away to the client,
        // or return it silently.
        $this->system->startOutputBuffering();

        // Just in case there are no handlers:
        $handlerResponse    = null;
        $handlerContentType = null;

        foreach (array_reverse($this->handlerStack) as $handler) {
            /* @var HandlerInterface|PrettyPageHandler $handler */
            $handler->setRun($this);
            $handler->setInspector($inspector);
            $handler->setException($exception);

            if ($handler instanceof PrettyPageHandler) {
                $handler->handleUnconditionally(true);
                $handler->setPageTitle($inspector->getExceptionName() . ' - easySwoole 2.x');
                $Request = \EasySwoole\Whoops\Instance\Request::getInstance()->getRequest();
                $Server  = ServerManager::getInstance()->getServer();

                if ($Request instanceof Request) {
                    // Managed data to global variables
                    $_GET    = $Request->getQueryParams();
                    $_POST   = $Request->getParsedBody();
                    $_FILES  = $Request->getUploadedFiles();
                    $_COOKIE = $Request->getCookieParams();

                    $handler->addDataTable('Headers', array_map(function ($value) {
                        return str_replace("\n", '', implode(',', $value));
                    }, $Request->getHeaders()));

                    $handler->addDataTable('EasySwoole Constants', get_defined_constants(true)['user']);
                    $handler->addDataTable('Swoole Service', [
                        'main_host'   => $Server->host,
                        'main_port'   => $Server->port,
                        'master_pid'  => $Server->master_pid,
                        'manager_pid' => $Server->manager_pid,
                        'worker_id'   => $Server->worker_id,
                        'worker_pid'  => $Server->worker_pid,
                        'task_worker' => $Server->taskworker
                    ]);
                }
            }

            // The HandlerInterface does not require an Exception passed to handle()
            // and neither of our bundled handlers use it.
            // However, 3rd party handlers may have already relied on this parameter,
            // and removing it would be possibly breaking for users.
            $handlerResponse = $handler->handle($exception);

            // Collect the content type for possible sending in the headers.
            $handlerContentType = method_exists($handler, 'contentType') ? $handler->contentType() : null;

            if (in_array($handlerResponse, [Handler::LAST_HANDLER, Handler::QUIT])) {
                // The Handler has handled the exception in some way, and
                // wishes to quit execution (Handler::QUIT), or skip any
                // other handlers (Handler::LAST_HANDLER). If $this->allowQuit
                // is false, Handler::QUIT behaves like Handler::LAST_HANDLER
                break;
            }
        }

        $output = $this->system->cleanOutputBuffer();

        if ($this->writeToOutput()) {
            $this->writeToOutputNow($output, $handlerContentType);
        }

        return $output;
    }

    /**
     * Converts generic PHP errors to \ErrorException
     * instances, before passing them off to be handled.
     * This method MUST be compatible with set_error_handler.
     * @param int    $level
     * @param string $message
     * @param string $file
     * @param int    $line
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
                    // Ignore the error, abort handling
                    // See https://github.com/filp/whoops/issues/418
                    return true;
                }
            }

            // XXX we pass $level for the "code" param only for BC reasons.
            // see https://github.com/filp/whoops/issues/267
            $exception = new ErrorException($message, /*code*/
                $level, /*severity*/
                $level, $file, $line);
            if ($this->canThrowExceptions) {
                throw $exception;
            } else {
                $this->handleException($exception);
            }
            // Do not propagate errors which were already handled by Whoops.
            return true;
        }
        return false;
    }

    /**
     * Special case to deal with Fatal errors and the like.
     * @throws ErrorException
     */
    public function handleShutdown()
    {
        // If we reached this step, we are in shutdown handler.
        // An exception thrown in a shutdown handler will not be propagated
        // to the exception handler. Pass that information along.
        $this->canThrowExceptions = false;

        $error = $this->system->getLastError();
        if ($error && Misc::isLevelFatal($error['type'])) {
            // If there was a fatal error,
            // it was not handled in handleError yet.
            $this->handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * In certain scenarios, like in shutdown handler, we can not throw exceptions
     * @var bool
     */
    private $canThrowExceptions = true;

    /**
     * Echo something to the browser or console
     * @param  string $output
     * @param null    $handlerContentType
     * @return $this
     */
    private function writeToOutputNow($output, $handlerContentType = null)
    {
        if ($this->sendHttpCode() && \Whoops\Util\Misc::canSendHeaders()) {
            $this->system->setHttpResponseCode(
                $this->sendHttpCode()
            );
        }

        $Response = \EasySwoole\Whoops\Instance\Response::getInstance()->getResponse();

        if ($Response instanceof Response) {
            $Response->autoEnd(true);
            $Response->withHeader('Content-Type', $handlerContentType)->write($output);
        } else {
            // if null response then write to console
        }

        return $this;
    }
}