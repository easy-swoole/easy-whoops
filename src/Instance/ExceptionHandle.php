<?php

namespace EasySwoole\Whoops\Instance;

use EasySwoole\Core\Http\AbstractInterface\ExceptionHandlerInterface;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;
use EasySwoole\Whoops\EasyWhoops;
use Whoops\Exception\Inspector;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;

/**
 * Class ExceptionHandle
 * @author  : evalor <master@evalor.cn>
 * @package EasySwoole\Whoops\Instance
 */
class ExceptionHandle implements ExceptionHandlerInterface
{
    /* @var EasyWhoops $Whoops */
    protected $Whoops;

    public function handle(\Throwable $throwable, Request $request, Response $response)
    {
        // Call Whoops ExceptionHandle
        \EasySwoole\Whoops\Instance\Request::getInstance($request);
        \EasySwoole\Whoops\Instance\Response::getInstance($response);

        $this->Whoops->handleException($throwable);
    }

    public function setWhoops(EasyWhoops $whoops)
    {
        $this->Whoops = $whoops;
    }
}