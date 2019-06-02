<?php

namespace EasySwoole\Whoops\Util;

use EasySwoole\Component\Di;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\SysConst;
use EasySwoole\Http\Response;

/**
 * 系统门面适配层
 * Class SystemFacade
 * @package EasySwoole\Whoops\Util
 */
class SystemFacade
{
    /**
     * 设置HTTP状态码
     * @param int $httpCode
     * @return bool|Response|int|null
     */
    public function setHttpResponseCode($httpCode)
    {
        if (Misc::canSendResponse()) {
            $response = Misc::getEasySwooleResponse();
            $response->withStatus($httpCode);
            return true;
        }
        return false;
    }

    /**
     * 设置HTTP响应头
     * @param $name
     * @param $value
     * @return bool
     */
    public function setHttpHeader($name, $value)
    {

        if (Misc::canSendResponse()) {
            $response = Misc::getEasySwooleResponse();
            $response->withHeader($name, $value);
            return true;
        }
        return false;
    }

    /**
     * 设置HTTP响应体
     * @param string $bodyContent
     * @return bool
     */
    public function sendHttpBody($bodyContent)
    {
        if (Misc::canSendResponse() && !empty($bodyContent)) {
            $response = Misc::getEasySwooleResponse();
            $body = $response->getBody();
            $body->truncate();
            $body->rewind();
            return $response->write($bodyContent);
        }
        return false;
    }

    /**
     * 设置错误处理
     * @param callable $handler
     * @param string $types
     * @return callable|mixed|null
     */
    public function setErrorHandler(callable $handler, $types = 'use-php-defaults')
    {
        // Workaround for PHP 5.5
        if ($types === 'use-php-defaults') {
            $types = E_ALL | E_STRICT;
        }
        // 不从DI注册 直接注册到PHP
        Di::getInstance()->set(SysConst::ERROR_HANDLER, $handler);
        return true;
    }

    /**
     * 设置异常处理
     * @param callable $handler
     * @return bool|callable|null
     */
    public function setExceptionHandler(callable $handler)
    {
        Di::getInstance()->set(SysConst::HTTP_EXCEPTION_HANDLER, $handler);
        return true;
    }

    /**
     * 设置退出前回调
     * @param callable $function
     * @return bool|void
     */
    public function registerShutdownFunction(callable $function)
    {
        Di::getInstance()->set(SysConst::SHUTDOWN_FUNCTION, $function);
        return true;
    }


    /**
     * 开启输出缓冲区
     * @return bool
     */
    public function startOutputBuffering()
    {
        return ob_start();
    }

    /**
     * 清空输出缓冲区并得到内容
     * @return string|false
     */
    public function cleanOutputBuffer()
    {
        return ob_get_clean();
    }

    /**
     * 获取当前的缓冲区级别
     * @return int
     */
    public function getOutputBufferLevel()
    {
        return ob_get_level();
    }

    /**
     * 关闭当前缓冲区并丢弃内容
     * @return bool
     */
    public function endOutputBuffering()
    {
        return ob_end_clean();
    }

    /**
     * 立即刷送当前的缓冲区
     * @return void
     */
    public function flushOutputBuffer()
    {
        flush();
    }

    /**
     * 获取最后一次的错误
     * @return array|null
     */
    public function getLastError()
    {
        return error_get_last();
    }

    /**
     * 获取错误报告等级
     * @return int
     */
    public function getErrorReportingLevel()
    {
        return error_reporting();
    }

    /**
     * 释放已经绑定的异常句柄
     * TODO 因为注册时已经劫持了框架的异常处理 不支持释放
     * @return void
     */
    public function restoreExceptionHandler()
    {
        return;
    }

    /**
     * 释放已经绑定的错误句柄
     * TODO 因为注册时已经劫持了框架的异常处理 不支持释放
     * @return void
     */
    public function restoreErrorHandler()
    {
        return;
    }

    /**
     * 立即终止脚本
     * TODO 需要安全地终止脚本 抛出一个异常并保证Whoops遇到该异常会直接忽略
     * @param int $exitStatus
     * @return bool|void
     */
    public function stopExecution($exitStatus)
    {
        return false;
    }

    /**
     * 获取当前的Server
     * @return \swoole_http_server|\swoole_server|\swoole_server_port|\swoole_websocket_server|null
     */
    public function swooleServer()
    {
        return ServerManager::getInstance()->getSwooleServer();
    }
}