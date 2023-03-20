<?php

// +----------------------------------------------------------------------
// | Worker Plugin for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2023 ThinkAdmin [ thinkadmin.top ]
// +----------------------------------------------------------------------
// | 官方网站: https://thinkadmin.top
// +----------------------------------------------------------------------
// | 免责声明 ( https://thinkadmin.top/disclaimer )
// | 开源协议 ( http://www.apache.org/licenses/LICENSE-2.0 )
// | 配置参考 ( https://www.workerman.net/doc/workerman/worker/properties.html )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-worker
// | github 代码仓库：https://github.com/zoujingli/think-plugs-worker
// +----------------------------------------------------------------------
// | 配置参数参数：https://www.workerman.net/doc/workerman/worker/properties.html
// +----------------------------------------------------------------------

return [
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
        // 监控检测间隔（单位秒，零不监控）
        'time' => 3,
        // 文件监控目录（默认监控 app 目录）
        'path' => [],
    ],
    // 监控内存超限重载
    'memory'  => [
        // 监控检测间隔（单位秒，零不监控）
        'time'  => 60,
        // 限制内存大小（可选单位有 G M K ）
        'limit' => '1G'
    ],
];