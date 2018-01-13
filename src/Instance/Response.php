<?php

namespace EasySwoole\Whoops\Instance;

use EasySwoole\Core\Http\Response as easySwooleResponse;

class Response
{
    protected static $instance;
    protected        $Response;

    function __construct(easySwooleResponse $response)
    {
        $this->Response = $response;
    }

    static function getInstance(easySwooleResponse $response = null)
    {
        if ($response != null) {
            self::$instance = new static($response);
        }

        return self::$instance;
    }

    function getResponse()
    {
        return $this->Response;
    }
}