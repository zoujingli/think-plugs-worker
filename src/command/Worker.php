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

use plugin\worker\Server;
use plugin\worker\support\Http;
use think\admin\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Workerman\Worker as Workerman;

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
            ->addOption('custom', 'c', Option::VALUE_OPTIONAL, 'the custom workerman server.', 'default')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the workerman server in daemon mode.')
            ->setDescription('Workerman Http Server for ThinkAdmin');
    }

    public function execute(Input $input, Output $output)
    {
        // 读取配置参数
        [$custom, $this->config] = $this->withConfig();
        if (empty($this->config)) {
            $output->writeln("<error>Configuration Custom {$custom} Undefined.</error> ");
            return;
        }

        // 执行自定义服务
        if (!empty($this->config['classes'])) {
            foreach ((array)$this->config['classes'] as $class) {
                $this->startServer($class);
            }
            Workerman::runAll();
            return;
        }

        // 获取基本运行参数
        $host = $this->getHost();
        $port = $this->getPort();
        $action = $input->getArgument('action');

        // 运行环境初始化处理
        if ($this->process->iswin()) {
            if (!$this->winNext($custom, $action, $port)) return;
        } else {
            if (!$this->unixNext($custom, $action, $port)) return;
        }
        if ('start' == $action) {
            $output->writeln('Starting Workerman http server...');
        }

        if (empty($this->config['worker']['logFile'])) {
            $this->config['worker']['logFile'] = syspath("safefile/worker/worker_{$port}.log");
        }
        if (empty($this->config['worker']['pidFile'])) {
            $this->config['worker']['pidFile'] = syspath("safefile/worker/worker_{$port}.pid");
        }
        is_dir($dir = dirname($this->config['worker']['pidFile'])) or mkdir($dir, 0777, true);
        is_dir($dir = dirname($this->config['worker']['logFile'])) or mkdir($dir, 0777, true);

        if ($custom === 'default') {
            $worker = new Http($host, $port, $this->config['context'] ?? [], $this->config['callable'] ?? null);
            $worker->setRoot($this->app->getRootPath());
        } else {
            if (empty($this->config['listen'])) {
                $this->output->writeln("<error>Configuration Custom {$custom}.listen Undefined.</error> ");
                return;
            }
            $worker = new Workerman($this->config['listen'], $this->config['context'] ?? []);
        }

        // 开启守护进程模式
        if ($this->input->hasOption('daemon')) {
            Workerman::$daemonize = true;
        }

        // 全局静态属性设置
        foreach ($this->config['worker'] ?? [] as $name => $value) {
            if (in_array($name, ['daemonize', 'stdoutFile', 'pidFile', 'logFile'])) {
                Workerman::${$name} = $value;
                unset($this->config['worker'][$name]);
            }
        }

        // 设置服务器参数
        foreach ($this->config['worker'] ?? [] as $name => $value) {
            $worker->$name = $value;
        }

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
        Workerman::runAll();
    }

    /**
     * 自定义服务类
     * @param string $class
     * @return void
     */
    protected function startServer(string $class)
    {
        if (class_exists($class)) {
            $worker = new $class;
            if (!$worker instanceof Server) {
                $this->output->writeln("<error>Worker Server Class Must extends \\plugin\\worker\\Server</error>");
            }
        } else {
            $this->output->writeln("<error>Worker Server Class Not Exists : {$class}</error>");
        }
    }

    /**
     * 初始化 Windows 环境
     * @param string $custom
     * @param string $action
     * @param integer $port
     * @return boolean
     */
    protected function winNext(string $custom, string $action, int $port): bool
    {
        if (!in_array($action, ['start', 'stop', 'status'])) {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|status for Windows .</error>");
            return false;
        }
        $command = "xadmin:worker --custom {$custom} --port {$port}";
        if ($action === 'start' && $this->input->hasOption('daemon')) {
            if (count($query = $this->process->thinkQuery($command)) > 0) {
                $this->output->writeln("<info>Worker daemons [{$custom}:{$port}] started successfully for Process {$query[0]['pid']} </info>");
                return false;
            }
            $this->process->thinkExec($command, 500);
            if (count($query = $this->process->thinkQuery($command)) > 0) {
                $this->output->writeln("<info>Worker daemons [{$custom}:{$port}] started successfully for Process {$query[0]['pid']} </info>");
            } else {
                $this->output->writeln("<error>Worker daemons [{$custom}:{$port}] failed to start. </error>");
            }
            return false;
        } elseif ($action === 'stop') {
            foreach ($result = $this->process->thinkQuery($command) as $item) {
                $this->process->close(intval($item['pid']));
                $this->output->writeln("<info>Successfully sent end signal to Worker:{$port} {$item['pid']} </info>");
                $this->output->writeln("<info>Send stop signal to Worker daemons [{$custom}:{$port}] Process {$item['pid']} </info>");
            }
            if (empty($result)) {
                $this->output->writeln("<error>The Worker daemons [{$custom}:{$port}] is not running. </error>");
            }
            return false;
        } elseif ($action === 'status') {
            foreach ($result = $this->process->thinkQuery($command) as $item) {
                $this->output->writeln("Worker daemons [{$custom}:{$port}] Process {$item['pid']} running");
            }
            if (empty($result)) {
                $this->output->writeln("<error>The Worker daemons [{$custom}:{$port}] is not running. </error>");
            }
            return false;
        }
        return true;
    }

    /**
     * 初始化 Unix 环境
     * @param string $custom
     * @param string $action
     * @param string $port
     * @return boolean
     */
    protected function unixNext(string $custom, string $action, string $port): bool
    {
        if (!in_array($action, ['start', 'stop', 'reload', 'restart', 'status', 'connections'])) {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload|status|connections .</error>");
            return false;
        }
        global $argv;
        array_shift($argv) && array_shift($argv);
        array_unshift($argv, "xadmin:worker", $action, "--custom {$custom} --port {$port}");
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
        } elseif (empty($this->config['listen'])) {
            return empty($this->config['host']) ? '0.0.0.0' : $this->config['host'];
        } else {
            return parse_url($this->config['listen'], PHP_URL_HOST) ?: '0.0.0.0';
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
        } elseif (empty($this->config['listen'])) {
            return empty($this->config['port']) ? 80 : intval($this->config['port']);
        } else {
            return intval(parse_url($this->config['listen'], PHP_URL_PORT) ?: 80);
        }
    }

    /**
     * 获取配置参数
     * @return array
     */
    private function withConfig(): array
    {
        if (($custom = $this->input->getOption('custom')) !== 'default') {
            $config = $this->app->config->get("worker.custom.{$custom}", []);
            return [$custom, empty($config) ? false : $config];
        } else {
            return [$custom, $this->app->config->get('worker', [])];
        }
    }
}