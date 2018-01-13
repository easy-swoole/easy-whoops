# easyWhoops

easySwoole 2.x 版本专用的 Whoops 适配插件

## 使用方法

在全局事件 **EasySwooleEvent** 中注册以下内容

```php
public function frameInitialize(): void
{
    date_default_timezone_set('Asia/Shanghai');
    
    // 注册 EasyWhoops
    $EasyWhoops = new EasyWhoops;
    $EasyWhoops->pushHandler(new PrettyPageHandler);
    $EasyWhoops->register();
}
```

```php
public function onRequest(Request $request, Response $response): void
{
    // 将 Request 和 Response 托管到单例
    EasyWhoops::instanceIO($request, $response);
}
```