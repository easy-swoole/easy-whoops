<?php

namespace EasySwoole\Whoops\Util;

use EasySwoole\Http\Response;

/**
 * 适配不同框架的门面
 * Class SystemFacade
 * @package EasySwoole\Whoops\Util
 */
class SystemFacade extends \Whoops\Util\SystemFacade
{
    /**
     * 设置HTTP状态码
     * @param int $httpCode
     * @return bool|Response|int|null
     */
    public function setHttpResponseCode($httpCode)
    {
        $response = Misc::getEasySwooleResponse();
        if (($response instanceof Response) && Misc::canSendHeaders()) {
            return $response->withStatus($httpCode);
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
        $response = Misc::getEasySwooleResponse();
        if (($response instanceof Response) && Misc::canSendHeaders()) {
            return $this->setHttpHeader($name, $value);
        }
        return false;
    }

    /**
     * 设置HTTP响应体
     * @param string $bodyContent
     * @return bool
     */
    public function setHttpBody($bodyContent)
    {
        $response = Misc::getEasySwooleResponse();
        if (($response instanceof Response) && Misc::canSendHeaders()) {
            $body = $response->getBody();
            $body->truncate();
            $body->rewind();
            return $response->write($bodyContent);
        }
        return false;
    }

    /**
     * 立即终止脚本
     * // TODO 需要安全终止
     * @param int $exitStatus
     * @return bool|void
     */
    public function stopExecution($exitStatus)
    {
        return false;
    }
}