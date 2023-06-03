# ThinkPlugsWorker for ThinkAdmin

[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-plugs-worker/v/stable)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![Total Downloads](https://poser.pugx.org/zoujingli/think-plugs-worker/downloads)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![Monthly Downloads](https://poser.pugx.org/zoujingli/think-plugs-worker/d/monthly)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![Daily Downloads](https://poser.pugx.org/zoujingli/think-plugs-worker/d/daily)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![PHP Version](https://doc.thinkadmin.top/static/icon/php-7.1.svg)](https://thinkadmin.top)
[![License](https://poser.pugx.org/zoujingli/think-plugs-worker/license)](https://gitee.com/zoujingli/think-plugs-worker/blob/master/license)

基于 **Workerman** 的 **HttpServer** 插件 ，基于内存方案快速运行 Web 及其他通信协议服务！

代码主仓库放在 **Gitee**，**Github** 仅为镜像仓库用于发布 **Composer** 包。

### 安装插件

```shell
### 安装前建议尝试更新所有组件
composer update --optimize-autoloader

### 注意，插件仅支持在 ThinkAdmin v6.1 中使用
composer require zoujingli/think-plugs-worker --optimize-autoloader
```

### 卸载插件

```shell
composer remove zoujingli/think-plugs-worker
```

### 配置参数

配置文件 `config/worker.php`

```php
return [
    // 服务监听地址
    'host'     => '127.0.0.1',
    // 服务监听端口
    'port'     => 2346,
    // 套接字上下文选项
    'context'  => [],
    // 高级自定义服务类
    'classes'  => '',
    // 消息请求回调处理
    'callable' => null,
    // 服务进程参数配置
    'worker'   => [
        // 进程名称
        "name"  => 'ThinkAdmin',
        // 进程数量
        'count' => 4,
    ],
    // 监控文件变更重载
    'files'    => [
        // 监控检测间隔（单位秒，零不监控）
        'time' => 3,
        // 文件监控目录（默认监控 app 目录）
        'path' => [],
    ],
    // 监控内存超限重载
    'memory'   => [
        // 监控检测间隔（单位秒，零不监控）
        'time'  => 60,
        // 限制内存大小（可选单位有 G M K ）
        'limit' => '1G'
    ],
    // 自定义服务配置（可选）
    'customs'  => [
        // 自定义 text 服务
        'text'      => [
            // 进程类型(Workerman|Gateway|Register|Business)
            'type'    => 'Workerman',
            // 监听地址(<协议>://<地址>:<端口>)
            'listen'  => 'text://0.0.0.0:8685',
            // 高级自定义服务类
            'classes' => '',
            // 套接字上下文选项
            'context' => [],
            // 服务进程参数配置
            'worker'  => [
                //'name' => 'TextTest',
                // onWorkerStart => [class,method]
                // onWorkerReload => [class,method]
                // onConnect => [class,method]
                // onBufferFull => [class,method]
                // onBufferDrain => [class,method]
                // onError => [class,method]
                // 设置连接的 onMessage 回调
                'onMessage' => function ($connection, $data) {
                    $connection->send("hello world");
                }
            ]
        ],
        // 自定义 websocket 服务
        'websocket' => [
            // 进程类型(Workerman|Gateway|Register|Business)
            'type'    => 'Workerman',
            // 监听地址(<协议>://<地址>:<端口>)
            'listen'  => 'websocket://0.0.0.0:8686',
            // 高级自定义服务类
            'classes' => '',
            // 套接字上下文选项
            'context' => [],
            // 服务进程参数配置
            'worker'  => [
                //'name' => 'TextTest',
                // onWorkerStart => [class,method]
                // onWorkerReload => [class,method]
                // onConnect => [class,method]
                // onBufferFull => [class,method]
                // onBufferDrain => [class,method]
                // onError => [class,method]
                // 设置连接的 onMessage 回调
                'onMessage' => function ($connection, $data) {
                    $connection->send("hello world");
                }
            ]
        ],
        // 自定义 Gateway 服务
        'gateway'   => [
            // 进程类型(Workerman|Gateway|Register|Business)
            'type'    => 'Gateway',
            // 监听地址(<协议>://<地址>:<端口>)
            'listen'  => 'websocket://127.0.0.1:8689',
            // 高级自定义服务类
            'classes' => '',
            // 套接字上下文选项
            'context' => [],
            // 服务进程参数配置
            'worker'  => [
                // 进程名称
                "name"                 => 'Gateway',
                'pingInterval'         => 10,
                'pingNotResponseLimit' => 0,
                'pingData'             => '{"type":"ping"}',
                // 进程数量
                // "count"  => 4,
                "lanIp"                => '127.0.0.1',
                "startPort"            => 2000,
                // 注册服务地址
                "registerAddress"      => '127.0.0.1:1236',
                "onWorkerStart"        => function () {
                    echo "Gateway onWorkerStart" . PHP_EOL;
                },
                "onWorkerStop"         => function () {
                    echo "Gateway onWorkerStop" . PHP_EOL;
                }
            ]
        ],
        // 自定义 Register 服务
        'register'  => [
            // 进程类型(Workerman|Gateway|Register|Business)
            'type'   => 'Register',
            // 监听地址(<协议>://<地址>:<端口>)
            'listen' => 'text://127.0.0.1:1236'
        ],
        // 自定义 Business 服务
        'business'  => [
            // 进程类型(Workerman|Gateway|Register|Business)
            'type'    => 'Business',
            // 高级自定义服务类
            'classes' => '',
            // 服务进程参数配置
            'worker'  => [
                // 进程名称
                "name"            => 'Business',
                // 进程数量
                "count"           => 4,
                // 注册服务地址
                "registerAddress" => '127.0.0.1:1236',
                // "onWorkerStart" => [class, method],
                // "onWorkerStop" => [class, method],
                // 业务处理类
                "eventHandler"    => Events::class,
                "onWorkerStart"   => function () {
                    echo "Business onWorkerStart" . PHP_EOL;
                },
                "onWorkerStop"    => function () {
                    echo "Business onWorkerStart" . PHP_EOL;
                }
            ]
        ],
    ],
];

/**
 * 业务处理类
 * @class Events
 */
class Events
{

    /**
     * 业务进程启动
     * @param $businessWorker
     * @return void
     */
    public static function onWorkerStart($businessWorker)
    {
        echo "Events WorkerStart\n";
    }

    /**
     * 有消息时触发该方法
     * @param int $clientid 发消息的client_id
     * @param mixed $message 消息
     * @throws \Exception
     */
    public static function onMessage($clientid, $message)
    {
        // 群聊，转发请求给其它所有的客户端
        \GatewayWorker\Lib\Gateway::sendToAll("Message By Events : {$message}");
    }
}
```

### 使用方法

在命令行启动服务端

```shell
#========= 启动参数配置 =========#
### 守护方式运行  -d
### 指定监听域名  --host 127.0.0.1
### 指定监听端口  --port 2346 
### 启动指定服务  --custom websocket

# 启动默认 Http 服务
php think xadmin:worker

# 启动自定义 text 服务，注意 text 为 customs 配置项
php think xadmin:worker --custom text

# 启动自定义 WebSocket 服务，注意 websocket 为 customs 配置项
php think xadmin:worker --custom websocket

```

然后就可以通过浏览器直接访问当前应用

```
http://localhost:2346
```

默认使用 `Workerman` 工作方式，如果需要使用  `Gateway` 方式，需要安装 `GatewayWorker` 组件。

安装 `GatewayWorker` 的指令如下：

```shell
# 安装 GatewayWorker 组件
composer require workerman/gateway-worker
```

**注意：** 启用 `Gateway` 时需要单独启动三个进程，分别是 `Gateway`、`Register`、`Business`，中间需要 `Register` 进程连接。

**数据通信模型：**

`Client` `<->` `Gateway` `<->` `Register` `<->` `Business` `<->` `Events`

**Linux** 支持操作指令如下：

```shell
php think xadmin:worker [start|stop|reload|restart|status|-d]

# 以上所有操作效果与 Workerman 官方操作一致，详情请阅读对应文档。
```

**Windows** 支持操作指令如下：

```shell
php think xadmin:worker [start|stop|status|-d]

# 以上 stop|status|-d 操作是基于 wimc 实现，Workerman 官方不支持此种方式操作。  
```

其他 **workerman** 的参数可以在应用配置目录下的 **worker.php** 里面 **worker** 项配置。

更多其他特性请阅读 **workerman** 文档 https://www.workerman.net/doc/workerman

### 版权说明

**ThinkPlugsWorker** 遵循 **Apache2** 开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有 Copyright © 2014-2023 by ThinkAdmin (https://thinkadmin.top) All rights reserved。

更多细节参阅 [LICENSE.txt](license)
