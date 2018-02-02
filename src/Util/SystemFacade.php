<?php

namespace EasySwoole\Whoops\Util;

use EasySwoole\Core\Component\Di;
use EasySwoole\Core\Component\SysConst;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;
use EasySwoole\Whoops\Handle\TransferHandle;

/**
 * SystemFacade overwrite
 * Class SystemFacade
 * @author  : evalor <master@evalor.cn>
 * @package EasySwoole\Whoops\Util
 */
class SystemFacade extends \Whoops\Util\SystemFacade
{
    private $container;
    /** @var Request $requestInstance */
    private $requestInstance = null;
    /** @var Response $responseInstance */
    private $responseInstance = null;

    function __construct()
    {
        $this->container = Di::getInstance();
    }

    function setErrorHandler(callable $handler, $types = 'use-php-defaults')
    {
        $this->container->set(SysConst::ERROR_HANDLER, $handler);
    }

    function registerShutdownFunction(callable $function)
    {
        $this->container->set(SysConst::SHUTDOWN_FUNCTION, $function);
    }

    function setExceptionHandler(callable $handler)
    {
        $ExceptionHandler = new TransferHandle;
        $ExceptionHandler->setWhoopsHandle($handler);
        $this->container->set(SysConst::HTTP_EXCEPTION_HANDLER, $ExceptionHandler);
    }

    function restoreExceptionHandler()
    {
        $this->container->set(SysConst::HTTP_EXCEPTION_HANDLER, null);
    }

    function restoreErrorHandler()
    {
        $this->container->set(SysConst::ERROR_HANDLER, null);
    }

    function setResponseInstance(Response $response)
    {
        $this->responseInstance = $response;
    }

    function setRequestInstance(Request $request)
    {
        $this->requestInstance = $request;
    }

    function entrustGlobalVariables()
    {
        // if don't have request instance, do not anything
        if ($this->requestInstance) {
            $swooleRequest = $this->requestInstance->getSwooleRequest();
            $_GET          = isset($swooleRequest->get) ? $swooleRequest->get : [];
            $_POST         = isset($swooleRequest->post) ? $swooleRequest->post : [];
            $_FILES        = isset($swooleRequest->files) ? $swooleRequest->files : [];
            $_SERVER       = isset($swooleRequest->server) ? array_change_key_case($swooleRequest->server, CASE_UPPER) : [];
            $_REQUEST      = array(); // 暂不支持
            $_COOKIE       = array(); // 暂不支持
            $_ENV          = array(); // 暂不支持
            $_SESSION      = array(); // 暂不支持
            $GLOBALS       = array(); // 暂不支持
        }
    }

    function cleanSuperGlobalVariables()
    {
        unset($_GET, $_POST, $_COOKIE, $_FILES, $_ENV, $_REQUEST, $_SERVER, $_SESSION, $GLOBALS);
    }

    function ResIns()
    {
        return $this->responseInstance;
    }

    function ReqIns()
    {
        return $this->requestInstance;
    }
}