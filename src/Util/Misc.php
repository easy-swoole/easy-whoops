<?php

namespace EasySwoole\Whoops\Util;

use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Component\Context\Exception\ModifyError;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;

/**
 * 杂项工具
 * Class Misc
 * @package EasySwoole\Whoops\Util
 */
class Misc
{
    const CONTEXT_REQUEST = 'request';
    const CONTEXT_RESPONSE = 'response';

    /**
     * 储存请求和响应
     * @param Request $request
     * @param Response $response
     * @throws ModifyError
     */
    public static function hookOnRequest(Request $request, Response $response): void
    {
        $context = ContextManager::getInstance();
        $context->set(Misc::CONTEXT_REQUEST, $request);
        $context->set(Misc::CONTEXT_RESPONSE, $response);
    }

    /**
     * 获取本协程的请求对象
     * @return Request|null
     */
    public static function getEasySwooleRequest(): ?Request
    {
        return ContextManager::getInstance()->get(Misc::CONTEXT_REQUEST);
    }

    /**
     * 获取本协程的响应对象
     * @return Response|null
     */
    public static function getEasySwooleResponse(): ?Response
    {
        return ContextManager::getInstance()->get(Misc::CONTEXT_RESPONSE);
    }

    /**
     * 可否发送HTTP头部
     * 只要存在响应对象并且没有逻辑END
     * @return bool
     */
    public static function canSendResponse(): bool
    {
        $response = Misc::getEasySwooleResponse();
        if ($response instanceof Response) {
            return true;
        }
        return false;
    }

    /**
     * 是否Ajax请求
     * @return bool
     */
    public static function isAjaxRequest(): bool
    {
        $request = Misc::getEasySwooleRequest();
        if ($request instanceof Request) {
            $header = $request->getHeader('http_x_requested_with');
            return $header ? (strtolower($header[0]) == 'xmlhttprequest') : false;
        }
        return false;
    }

    /**
     * 当前是否在CLI
     * 由于Swoole始终在CLI运行 则需要判断当前是否注入了Request对象 无注入则无法输出到web
     * @return bool
     */
    public static function isCommandLine()
    {
        return !Misc::canSendResponse();
    }

    /**
     * 将错误异常代码转换为表示的常量
     * @param int $error_code
     * @return string
     */
    public static function translateErrorCode($error_code)
    {
        $constants = get_defined_constants(true);
        if (array_key_exists('Core', $constants)) {
            foreach ($constants['Core'] as $constant => $value) {
                if (substr($constant, 0, 2) == 'E_' && $value == $error_code) {
                    return $constant;
                }
            }
        }
        return "E_UNKNOWN";
    }

    /**
     * 确定错误级别是否致命（停止执行）
     * @param int $level
     * @return bool
     */
    public static function isLevelFatal($level)
    {
        $errors = E_ERROR;
        $errors |= E_PARSE;
        $errors |= E_CORE_ERROR;
        $errors |= E_CORE_WARNING;
        $errors |= E_COMPILE_ERROR;
        $errors |= E_COMPILE_WARNING;
        return ($level & $errors) > 0;
    }

}