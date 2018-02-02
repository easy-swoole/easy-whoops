<?php

namespace EasySwoole\Whoops\Util;

use EasySwoole\Core\Component\Di;
use EasySwoole\Core\Component\SysConst;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;
use EasySwoole\Whoops\Handle\TransferHandle;

/**
 * SystemFacade 重写
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

    /**
     * 注册 Error Handle
     * @param callable $handler
     * @param string   $types
     * @author : evalor <master@evalor.cn>
     * @return callable|null|void
     */
    function setErrorHandler(callable $handler, $types = 'use-php-defaults')
    {
        $this->container->set(SysConst::ERROR_HANDLER, $handler);
    }

    /**
     * 注册 Shutdown Function
     * @param callable $function
     * @author : evalor <master@evalor.cn>
     */
    function registerShutdownFunction(callable $function)
    {
        $this->container->set(SysConst::SHUTDOWN_FUNCTION, $function);
    }

    /**
     * 注册 Exception Handler
     * @param callable $handler
     * @author : evalor <master@evalor.cn>
     * @return callable|null|void
     */
    function setExceptionHandler(callable $handler)
    {
        $ExceptionHandler = new TransferHandle;
        $ExceptionHandler->setWhoopsHandle($handler);
        $this->container->set(SysConst::HTTP_EXCEPTION_HANDLER, $ExceptionHandler);
    }

    /**
     * 释放 Exception Handler
     * @author : evalor <master@evalor.cn>
     */
    function restoreExceptionHandler()
    {
        $this->container->set(SysConst::HTTP_EXCEPTION_HANDLER, null);
    }

    /**
     * 释放 Error Handle
     * @author : evalor <master@evalor.cn>
     */
    function restoreErrorHandler()
    {
        $this->container->set(SysConst::ERROR_HANDLER, null);
    }

    /**
     * 设置 Response 对象实例
     * @param Response $response
     * @author : evalor <master@evalor.cn>
     */
    function setResponseInstance(Response $response)
    {
        $this->responseInstance = $response;
    }

    /**
     * 设置 Request 对象实例
     * @param Request $request
     * @author : evalor <master@evalor.cn>
     */
    function setRequestInstance(Request $request)
    {
        $this->requestInstance = $request;
    }

    /**
     * 委托超全局变量到当前的进程
     * @author : evalor <master@evalor.cn>
     */
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

            $header = $this->requestInstance->getHeaderLine('x-requested-with');
            if ($header && strtolower($header) == 'xmlhttprequest') {
                $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
            }
        }
    }

    /**
     * 清理当前进程的超全局变量
     * @author : evalor <master@evalor.cn>
     */
    function cleanSuperGlobalVariables()
    {
        if (isset($_GET)) $_GET = array();
        if (isset($_POST)) $_POST = array();
        if (isset($_COOKIE)) $_COOKIE = array();
        if (isset($_FILES)) $_FILES = array();
        if (isset($_ENV)) $_ENV = array();
        if (isset($_REQUEST)) $_REQUEST = array();
        if (isset($_SERVER)) $_SERVER = array();
        if (isset($_SESSION)) $_SESSION = array();
        if (isset($GLOBALS)) $GLOBALS = array();
    }

    /**
     * 获取 Response 对象实例
     * @author : evalor <master@evalor.cn>
     * @return Response
     */
    function ResIns()
    {
        return $this->responseInstance;
    }

    /**
     * 获取 Request 对象实例
     * @author : evalor <master@evalor.cn>
     * @return Request
     */
    function ReqIns()
    {
        return $this->requestInstance;
    }
}