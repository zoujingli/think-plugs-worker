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
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-worker
// | github 代码仓库：https://github.com/zoujingli/think-plugs-worker
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace plugin\worker\support;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkerRequest;
use Workerman\Worker;

/**
 * 定制请求管理类
 * @class Request
 * @package plugin\worker\support
 */
class Request extends \think\Request
{
    public function withWorkerRequest(TcpConnection $connection, WorkerRequest $request): Request
    {
        $this->get = $request->get();
        $this->file = $request->file() ?? [];
        $this->post = $request->post();
        $this->cookie = $request->cookie();
        $this->header = $request->header();
        $this->method = $request->method();
        $this->request = $this->post + $this->get;
        $this->pathinfo = ltrim($request->path(), '\\/');
        // 兼容代理模式
        $this->host = $this->header['x-host'] ?? ($this->header['remote-host'] ?? $request->host());
        $this->realIP = $this->header['x-real-ip'] ?? ($this->header['x-forwarded-for'] ?? $connection->getRemoteIp());
        // 服务变量替换
        return $this->withInput($request->rawBody())->withServer(array_filter([
            'HTTP_HOST'             => $this->host,
            'PATH_INFO'             => $this->pathinfo,
            'REQUEST_URI'           => $request->uri(),
            'SERVER_NAME'           => $request->host(true),
            'SERVER_ADDR'           => $connection->getLocalIp(),
            'SERVER_PORT'           => $connection->getLocalPort(),
            'REMOTE_ADDR'           => $this->realIP,
            'REMOTE_PORT'           => $connection->getRemotePort(),
            'QUERY_STRING'          => $request->queryString(),
            'REQUEST_METHOD'        => $request->method(),
            'HTTP_X_PJAX'           => $this->header['x-pjax'] ?? null,
            'HTTP_X_REQUESTED_WITH' => $this->header['x-requested-with'] ?? null,
            'HTTP_X_FORWARDED_PORT' => $this->header['x-forwarded-port'] ?? null,
            'HTTP_ACCEPT'           => $this->header['accept'] ?? null,
            'HTTP_ACCEPT_ENCODING'  => $this->header['accept-encoding'] ?? null,
            'HTTP_ACCEPT_LANGUAGE'  => $this->header['accept-language'] ?? null,
            'HTTP_USER_AGENT'       => $this->header['user-agent'] ?? null,
            'HTTP_COOKIE'           => $this->header['cookie'] ?? null,
            'HTTP_CACHE_CONTROL'    => $this->header['cache-control'] ?? null,
            'HTTP_PRAGMA'           => $this->header['pragma'] ?? null,
            'SERVER_SOFTWARE'       => 'Server/' . Worker::VERSION,
            'REQUEST_TIME'          => time(),
            'REQUEST_TIME_FLOAT'    => microtime(true),
            'SERVER_PROTOCOL'       => $this->header['x-scheme'] ?? null
        ]));
    }
}