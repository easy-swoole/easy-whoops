# easyWhoops

[![Latest Stable Version](https://poser.pugx.org/easyswoole/easy-whoops/v/stable)](https://packagist.org/packages/easyswoole/easy-whoops)
[![Total Downloads](https://poser.pugx.org/easyswoole/easy-whoops/downloads)](https://packagist.org/packages/easyswoole/easy-whoops)
[![Latest Unstable Version](https://poser.pugx.org/easyswoole/easy-whoops/v/unstable)](https://packagist.org/packages/easyswoole/easy-whoops)
[![License](https://poser.pugx.org/easyswoole/easy-whoops/license)](https://packagist.org/packages/easyswoole/easy-whoops)
[![Monthly Downloads](https://poser.pugx.org/easyswoole/easy-whoops/d/monthly)](https://packagist.org/packages/easyswoole/easy-whoops)

easySwoole 2.x 版本专用的 Whoops 适配插件

## 安装

```bash
composer require esayswoole/easy-whoops
```

## 预览

![Whoops!](http://i.imgur.com/0VQpe96.png)

## 使用

在全局事件 **EasySwooleEvent** 中注册以下内容

```php
use EasySwoole\Whoops\Runner;
use Whoops\Handler\PrettyPageHandler;

public function frameInitialize(): void
{
  // 可以进行更多设置，默认为以下设置
  $options = [
    'auto_conversion' => true,                    // 开启AJAX模式下自动转换为JSON输出
    'detailed'        => true,                    // 开启详细错误日志输出
    'information'     => '发生内部错误,请稍后再试'   // 不开启详细输出的情况下 输出的提示文本
  ];
  $whoops  = new Runner($options);
  // 注册异常事件处理
  $whoops->pushHandler(new PrettyPageHandler);
  $whoops->register();
}
```

默认情况下会自动判断请求头是否含有 `X-Requested-With: XMLHttpRequest` 如果含有并且开启了 `auto_conversion` 选项则自动转换成Json输出
