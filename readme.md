# ThinkPlugsWorker for ThinkAdmin

[![Latest Stable Version](https://poser.pugx.org/zoujingli/think-plugs-worker/v/stable)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![Total Downloads](https://poser.pugx.org/zoujingli/think-plugs-worker/downloads)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![Monthly Downloads](https://poser.pugx.org/zoujingli/think-plugs-worker/d/monthly)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![Daily Downloads](https://poser.pugx.org/zoujingli/think-plugs-worker/d/daily)](https://packagist.org/packages/zoujingli/think-plugs-worker)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.1-8892BF.svg)](http://www.php.net)
[![License](https://poser.pugx.org/zoujingli/think-plugs-worker/license)](https://packagist.org/packages/zoujingli/think-plugs-worker)

基于 **Workerman** 的 **HttpServer** 服务插件 ，用于快速构建内存服务环境！

代码主仓库放在 **Gitee**，**Github** 仅为镜像仓库用于发布 **Composer** 包。

### 安装插件

```shell
### 安装前建议更新所有组件
composer update -o

### 注意，插件仅支持在 ThinkAdmin v6.1 中使用
composer require zoujingli/think-plugs-worker
```

### 卸载插件

```shell
composer remove zoujingli/think-plugs-worker
```

### 配置参数

```php
[
    // 服务监听地址
    'host'    => '127.0.0.1',
    // 服务监听端口
    'port'    => 2346,
    // 套接字上下文选项
    'context' => [],
    // 工作进程参数配置
    'worker'  => [
        'name'  => 'ThinkAdmin',
        'count' => 4,
    ],
    // 监控文件变更重载
    'files'   => [
        // 监控检测间隔（单位秒，零表示不监控）
        'time' => 3,
        // 文件监控目录（默认监控 app 目录）
        'path' => [],
    ],
    // 监控内存超限重载
    'memory'  => [
        // 监控检测间隔（单位秒，零表示不监控）
        'time'  => 60,
        // 限制内存大小（可选单位有 G M K ）
        'limit' => '1G'
    ],
];
```

### 使用方法

在命令行启动服务端

```shell
php think xadmin:worker
```

然后就可以通过浏览器直接访问当前应用

```
http://localhost:2346
```

Linux 下面可以支持下面指令

```
php think xadmin:worker [start|stop|reload|restart|status]
```

其他 **workerman** 的参数可以在应用配置目录下的 **worker.php** 里面的 **worker** 项配置。

更多其他特性请阅读 **workerman** 文档 https://www.workerman.net/doc/workerman

### 版权说明

**ThinkPlugsWorker** 遵循 **Apache2** 开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有 Copyright © 2014-2023 by ThinkAdmin (https://thinkadmin.top) All rights reserved。

更多细节参阅 [LICENSE.txt](license)
