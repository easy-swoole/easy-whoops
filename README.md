# EasyWhoops

[![Latest Stable Version](https://poser.pugx.org/easyswoole/easy-whoops/v/stable)](https://packagist.org/packages/easyswoole/easy-whoops)
[![Total Downloads](https://poser.pugx.org/easyswoole/easy-whoops/downloads)](https://packagist.org/packages/easyswoole/easy-whoops)
[![Latest Unstable Version](https://poser.pugx.org/easyswoole/easy-whoops/v/unstable)](https://packagist.org/packages/easyswoole/easy-whoops)
[![License](https://poser.pugx.org/easyswoole/easy-whoops/license)](https://packagist.org/packages/easyswoole/easy-whoops)
[![Monthly Downloads](https://poser.pugx.org/easyswoole/easy-whoops/d/monthly)](https://packagist.org/packages/easyswoole/easy-whoops)

支持协程的错误美化组件，采用独立进程渲染来规避协程安全问题，让你的应用拥有一个友好的错误提示页面！

## 效果预览

![Whoops!](https://i.imgur.com/RKOCgXP.png)

## 安装类库

```bash
composer require easyswoole/easy-whoops=3.x
```

## 接管异常

在全局事件 **EasySwooleEvent** 中注册以下内容

```php
<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace EasySwoole\EasySwoole;

use EasySwoole\Component\Context\Exception\ModifyError;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Whoops\Handler\CallbackHandler;
use EasySwoole\Whoops\Handler\PrettyPageHandler;
use EasySwoole\Whoops\Run;
use \Exception;

class EasySwooleEvent implements Event
{
    private $whoopsInstance;

    /**
     * 框架初始化
     * @throws Exception
     */
    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');
        $whoops = new Run();
        $whoops->pushHandler(new PrettyPageHandler);  // 输出一个漂亮的页面
        $whoops->pushHandler(new CallbackHandler(function ($exception, $inspector, $run, $handle) {
            // 可以推进多个Handle 支持回调做更多后续处理
        }));
        $whoops->register();
    }

    /**
     * 主服务启动前
     * @param EventRegister $register
     */
    public static function mainServerCreate(EventRegister $register)
    {
        Run::attachTemplateRender(ServerManager::getInstance()->getSwooleServer());
    }

    /**
     * 收到请求时
     * @param Request $request
     * @param Response $response
     * @return bool
     * @throws ModifyError
     */
    public static function onRequest(Request $request, Response $response): bool
    {
        Run::attachRequest($request, $response);
        return true;
    }

    /**
     * 请求结束时
     * @param Request $request
     * @param Response $response
     */
    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }
}
```
