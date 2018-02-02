<?php

namespace EasySwoole\Whoops\Handle;

use EasySwoole\Core\Http\AbstractInterface\ExceptionHandlerInterface;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;
use EasySwoole\Whoops\Runner;

/**
 * 转换 Whoops Handle 为 easySwoole Handle
 * Class TransferHandle
 * @author  : evalor <master@evalor.cn>
 * @package EasySwoole\Whoops\Handle
 */
class TransferHandle implements ExceptionHandlerInterface
{
    private $whoops_handle;

    public function handle(\Throwable $throwable, Request $request, Response $response)
    {
        /** @var Runner $runClass */
        $runClass       = $this->whoops_handle[0];
        $function       = $this->whoops_handle[1];
        $SystemInstance = $runClass->getSystems();

        // 托管 Request 和 Response 对象
        $SystemInstance->setRequestInstance($request);
        $SystemInstance->setResponseInstance($response);
        $SystemInstance->cleanSuperGlobalVariables();
        $SystemInstance->entrustGlobalVariables();

        // 执行Handle的渲染操作
        $runClass->$function($throwable);

        // 由于 Swoole 不会自动释放，执行完毕之后进行全局变量释放
        $SystemInstance->cleanSuperGlobalVariables();
    }

    /**
     * 设置 Whoops Handle
     * @param callable $handle
     * @author : evalor <master@evalor.cn>
     */
    public function setWhoopsHandle(callable $handle)
    {
        $this->whoops_handle = $handle;
    }
}