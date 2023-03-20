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

use think\exception\Handle;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkerRequest;
use Workerman\Protocols\Http\Response as WorkerResponse;

/**
 * 定制基础类
 * @class App
 * @package plugin\worker\support
 * @property Request $request
 */
class App extends \think\App
{
    protected $beginTime;
    protected $beginMemory;

    /**
     * @param TcpConnection $connection
     * @param WorkerRequest $request
     */
    public function worker(TcpConnection $connection, WorkerRequest $request)
    {
        try {
            // 初始化请求
            $this->delete('view');
            $this->db->clearQueryTimes();
            $this->beginTime = microtime(true);
            $this->beginMemory = memory_get_usage();
            while (ob_get_level() > 1) ob_end_clean();

            // 切换进程数据
            $this->session->setId($request->sessionId());
            $this->request->withWorkerRequest($connection, $request);

            // 开始处理请求
            ob_start();
            $thinkResponse = $this->http->run($this->request);
            $body = ob_get_clean();

            // 处理请求结果
            $header = $thinkResponse->getHeader() + ['Server' => 'x-server'];
            $response = new WorkerResponse($thinkResponse->getCode(), $header, $body . $thinkResponse->getContent());
            if (strtolower($request->header('connection')) === 'keep-alive') {
                $connection->send($response);
            } else {
                $connection->close($response);
            }

            // 结束当前请求
            $this->http->end($thinkResponse);

        } catch (\RuntimeException|\Exception|\Throwable|\Error $exception) {
            // 其他异常处理
            $this->showException($connection, $exception);
        }
    }

    /**
     * 是否运行在命令行下
     * @return boolean
     */
    public function runningInConsole(): bool
    {
        return false;
    }

    /**
     * 输出异常信息
     * @param \Workerman\Connection\TcpConnection $connection
     * @param \RuntimeException|\Exception|\Throwable $exception
     */
    private function showException(TcpConnection $connection, $exception)
    {
        if ($exception instanceof \Exception) {
            ($handler = $this->make(Handle::class))->report($exception);
            $resp = $handler->render($this->request, $exception);
            $connection->send(new WorkerResponse($resp->getCode(), ['Server' => 'x-server'], $resp->getContent()));
        } else {
            $connection->send(new WorkerResponse(500, ['Server' => 'x-server'], $exception->getMessage()));
        }
    }
}