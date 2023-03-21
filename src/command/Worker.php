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
// | 参考文件 ( https://github.com/top-think/think-worker/blob/3.0/src/command/Worker.php )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-worker
// | github 代码仓库：https://github.com/zoujingli/think-plugs-worker
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace plugin\worker\command;

use plugin\worker\support\Http;
use think\admin\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

/**
 * Worker
 * Class Worker
 * @package think\admin\server\command
 */
class Worker extends Command
{
    protected $config = [];

    public function configure()
    {
        $this->setName('xadmin:worker')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status|connections", 'start')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'the host of workerman server.')
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'the port of workerman server.')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the workerman server in daemon mode.')
            ->setDescription('Workerman Http Server for ThinkAdmin');
    }

    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        // 运行环境初始化处理
        if ($this->process->iswin()) {
            if (!$this->winNext($action)) return;
        } else {
            if (!$this->unixNext($action)) return;
        }
        if ('start' == $action) {
            $output->writeln('Starting Workerman http server...');
        }
        $this->config = $this->app->config->get('worker', []);
        [$host, $port] = [$this->getHost(), $this->getPort()];

        if (empty($this->config['worker']['logFile'])) {
            $this->config['worker']['logFile'] = syspath("safefile/worker/worker_{$port}.log");
        }
        if (empty($this->config['worker']['pidFile'])) {
            $this->config['worker']['pidFile'] = syspath("safefile/worker/worker_{$port}.pid");
        }
        is_dir($dir = dirname($this->config['worker']['pidFile'])) or mkdir($dir, 0777, true);
        is_dir($dir = dirname($this->config['worker']['logFile'])) or mkdir($dir, 0777, true);

        $worker = new Http($host, $port, $this->config['context'] ?? []);

        // 设置应用根目录
        $worker->setRoot($this->app->getRootPath());

        // 开启守护进程模式
        if ($this->input->hasOption('daemon')) {
            $worker->setStaticOption('daemonize', true);
        }

        // 全局静态属性设置
        foreach ($this->config['worker'] ?? [] as $name => $val) {
            if (in_array($name, ['daemonize', 'stdoutFile', 'pidFile', 'logFile'])) {
                $worker->setStaticOption($name, $val);
                unset($this->config['worker'][$name]);
            }
        }

        // 设置服务器参数
        $worker->setOption($this->config['worker'] ?? []);

        // 运行环境提示
        if ($this->process->isWin()) {
            $output->writeln('You can exit with <info>`CTRL-C`</info>');
        } else {
            // 设置文件变更及内存超限监控管理
            if (empty($this->config['files']['path'])) {
                $this->config['files']['path'] = [$this->app->getBasePath(), $this->app->getConfigPath()];
            }
            $worker->setMonitorFiles(intval($this->config['files']['time'] ?? 0), $this->config['files']['path']);
            $worker->setMonitorMemory(intval($this->config['memory']['time'] ?? 0), $this->config['memory']['limit'] ?? null);
        }

        // 应用并启动服务
        $worker->start();
    }

    /**
     * 初始化 Windows 环境
     * @param string $action
     * @return boolean
     */
    protected function winNext(string $action): bool
    {
        if (!in_array($action, ['start', 'stop', 'status'])) {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|status for Windows .</error>");
            return false;
        }
        if ($action === 'start' && $this->input->hasOption('daemon')) {
            if (count($query = $this->process->thinkQuery('xadmin:worker')) > 0) {
                $this->output->writeln("<info>Worker daemons started successfully for pid {$query[0]['pid']} </info>");
                return false;
            }
            $this->process->thinkExec('xadmin:worker', 500);
            if (count($query = $this->process->thinkQuery('xadmin:worker')) > 0) {
                $this->output->writeln("<info>Worker daemons started successfully for pid {$query[0]['pid']} </info>");
            } else {
                $this->output->writeln("<error>Worker daemons failed to start. </error>");
            }
            return false;
        } elseif ($action === 'stop') {
            foreach ($result = $this->process->thinkQuery('xadmin:worker') as $item) {
                $this->process->close(intval($item['pid']));
                $this->output->writeln("<info>Successfully sent end signal to process {$item['pid']} </info>");
            }
            if (empty($result)) {
                $this->output->writeln("<error>The Worker daemons process is not running. </error>");
            }
            return false;
        } elseif ($action === 'status') {
            foreach ($result = $this->process->thinkQuery('xadmin:worker') as $item) {
                $this->output->writeln("Worker daemons Process {$item['pid']} running");
            }
            if (empty($result)) {
                $this->output->writeln("<error>The Worker daemons process is not running. </error>");
            }
            return false;
        }
        return true;
    }

    /**
     * 初始化 Unix 环境
     * @param string $action
     * @return boolean
     */
    protected function unixNext(string $action): bool
    {
        if (!in_array($action, ['start', 'stop', 'reload', 'restart', 'status', 'connections'])) {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload|status|connections .</error>");
            return false;
        }
        global $argv;
        array_shift($argv) && array_shift($argv);
        array_unshift($argv, 'think xadmin:worker', $action);
        return true;
    }

    /**
     * 获取监听主机
     * @return string
     */
    private function getHost(): string
    {
        if ($this->input->hasOption('host')) {
            return $this->input->getOption('host');
        } else {
            return empty($this->config['host']) ? '0.0.0.0' : $this->config['host'];
        }
    }

    /**
     * 获取监听端口
     * @return integer
     */
    private function getPort(): int
    {
        if ($this->input->hasOption('port')) {
            return intval($this->input->getOption('port'));
        } else {
            return empty($this->config['port']) ? 80 : intval($this->config['port']);
        }
    }
}