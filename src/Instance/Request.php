<?php

namespace EasySwoole\Whoops\Instance;

use EasySwoole\Core\Http\Request as easySwooleRequest;

class Request
{
    protected static $instance;
    protected        $Request;

    function __construct(easySwooleRequest $request)
    {
        $this->Request = $request;
    }

    static function getInstance(easySwooleRequest $request = null)
    {
        if ($request != null) {
            self::$instance = new static($request);
        }

        return self::$instance;
    }

    function getRequest()
    {
        return $this->Request;
    }
}