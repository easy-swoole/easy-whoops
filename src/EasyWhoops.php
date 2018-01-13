<?php

namespace EasySwoole\Whoops;

use EasySwoole\Core\Component\Di;
use EasySwoole\Core\Component\SysConst;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;

use EasySwoole\Whoops\Instance\ExceptionHandle;
use EasySwoole\Whoops\Instance\Request as SingletonRequest;
use EasySwoole\Whoops\Instance\Response as SingletonResponse;

/**
 * EasyWhoops registrar
 * Class EasyWhoops
 * @author  : evalor <master@evalor.cn>
 * @package EasySwoole\Whoops
 */
class EasyWhoops
{
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

    function register()
    {
        $this->registerToContainer(SysConst::ERROR_HANDLER, [$this, 'appError']);
        $this->registerToContainer(SysConst::SHUTDOWN_FUNCTION, [$this, 'appShutdown']);
        $this->registerToContainer(SysConst::HTTP_EXCEPTION_HANDLER, ExceptionHandle::class);
    }

    function appShutdown()
    {
        var_dump('is appShutdown');
    }

    function appError()
    {
        var_dump('is appError');
    }

    private function registerToContainer($name, $callable)
    {
        Di::getInstance()->set($name, $callable);
    }
}