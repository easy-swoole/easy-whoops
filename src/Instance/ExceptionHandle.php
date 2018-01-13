<?php

namespace EasySwoole\Whoops\Instance;

use EasySwoole\Core\Http\AbstractInterface\ExceptionHandlerInterface;
use EasySwoole\Core\Http\Request;
use EasySwoole\Core\Http\Response;

/**
 * Class ExceptionHandle
 * @author  : evalor <master@evalor.cn>
 * @package EasySwoole\Whoops\Instance
 */
class ExceptionHandle implements ExceptionHandlerInterface
{

    public function handle(\Throwable $throwable, Request $request, Response $response)
    {
        var_dump('is ExceptionHandle');
    }
}