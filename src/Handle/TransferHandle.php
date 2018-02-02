<?php

namespace EasySwoole\Whoops\Handle;

use EasySwoole\Core\Http\AbstractInterface\ExceptionHandlerInterface;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;
use EasySwoole\Whoops\Runner;

/**
 * Transfer the Handle to EasySwoole
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
        $runClass = $this->whoops_handle[0];
        $function = $this->whoops_handle[1];

        // entrust the request and response to system instance
        $SystemInstance = $runClass->getSystems();
        $SystemInstance->cleanSuperGlobalVariables();
        $SystemInstance->setRequestInstance($request);
        $SystemInstance->setResponseInstance($response);
        $SystemInstance->entrustGlobalVariables();

        // run the whoops handle
        $runClass->$function($throwable);
        $SystemInstance->cleanSuperGlobalVariables();
    }

    /**
     * Set the Whoops Handle
     * @param callable $handle
     * @author : evalor <master@evalor.cn>
     */
    public function setWhoopsHandle(callable $handle)
    {
        $this->whoops_handle = $handle;
    }
}