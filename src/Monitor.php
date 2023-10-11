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
// | 参考资料 ( https://github.com/walkor/webman/blob/master/process/Monitor.php )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/think-plugs-worker
// | github 代码仓库：https://github.com/zoujingli/think-plugs-worker
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace plugin\worker;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use think\admin\service\ProcessService;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 资源监控管理器
 * Class Monitor
 * @package plugin\worker
 */
abstract class Monitor
{

    const defaultMaxMemory = '1G';

    /**
     * 监控目录
     * @var array
     */
    private static $paths = [];

    /**
     * 暂停锁定标记
     * @var string
     */
    private static $lockFile;

    private static $filesTimerId = -1;
    private static $memoryTimerId = -1;

    /**
     * @return string
     */
    private static function tag(): string
    {
        return self::$lockFile ?: (self::$lockFile = syspath('runtime/monitor.lock'));
    }

    /**
     * Pause monitor
     * @return void
     */
    public static function pause()
    {
        file_put_contents(self::tag(), time());
    }

    /**
     * Resume monitor
     * @return void
     */
    public static function resume(): void
    {
        clearstatcache();
        if (is_file(self::tag())) {
            unlink(self::$lockFile);
        }
    }

    /**
     * Whether monitor is paused
     * @return bool
     */
    public static function isPaused(): bool
    {
        clearstatcache();
        return file_exists(self::tag());
    }

    /**
     * Add Files Monitor
     * @param array|string $monitorDir
     * @param array $monitorExtensions
     * @return void
     */
    public static function listen($monitorDir, array $monitorExtensions = ['php'])
    {
        foreach ((array)$monitorDir as $dir) self::$paths[$dir] = $monitorExtensions;
    }

    /**
     * Remove Files Monitor
     * @param array|string $monitorDir
     * @return void
     */
    public static function remove($monitorDir)
    {
        foreach ((array)$monitorDir as $dir) {
            unset(self::$paths[$dir]);
        }
    }

    /**
     * Enable Files Monitor
     * @param integer $interval
     * @return boolean
     */
    public static function enableFilesMonitor(int $interval = 3): bool
    {
        if ($interval <= 0) return false;
        if (!Worker::getAllWorkers()) return false;
        if (in_array('exec', explode(',', ini_get('disable_functions')), true)) {
            echo "\nMonitor file change turned off because exec() has been disabled by disable_functions setting in " . PHP_CONFIG_FILE_PATH . "/php.ini\n";
            return false;
        } else {
            if (self::$filesTimerId > -1) Timer::del(self::$filesTimerId);
            self::$filesTimerId = Timer::add($interval, [self::class, 'checkAllFilesChange']);
            return true;
        }
    }

    /**
     * Enable Member Monitor
     * @param integer $interval
     * @param ?string $limit
     * @return boolean
     */
    public static function enableMemoryMonitor(int $interval = 60, ?string $limit = null): bool
    {
        if ($interval <= 0) return false;
        if (!Worker::getAllWorkers()) return false;
        if ($memoryLimit = self::getMemoryLimit($limit ?: self::defaultMaxMemory)) {
            if (self::$memoryTimerId > -1) Timer::del(self::$memoryTimerId);
            self::$memoryTimerId = Timer::add($interval, [self::class, 'checkMemory'], [$memoryLimit]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check Files Change
     * @return bool
     */
    public static function checkAllFilesChange(): bool
    {
        if (static::isPaused()) return false;
        foreach (self::$paths as $path => $extensions) {
            if (self::checkFilesChange($path, $extensions)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check Memory Limit
     * @param $memoryLimit
     * @return void
     */
    public static function checkMemory($memoryLimit)
    {
        if (static::isPaused() || $memoryLimit <= 0) return;
        $ppid = posix_getppid();
        $childrenFile = "/proc/$ppid/task/$ppid/children";
        if (!is_file($childrenFile) || !($children = file_get_contents($childrenFile))) {
            return;
        }
        foreach (explode(' ', $children) as $pid) {
            $pid = (int)$pid;
            $statusFile = "/proc/$pid/status";
            if (!is_file($statusFile) || !($status = file_get_contents($statusFile))) {
                continue;
            }
            $mem = 0;
            if (preg_match('/VmRSS\s*?:\s*?(\d+?)\s*?kB/', $status, $match)) {
                $mem = $match[1];
            }
            $mem = (int)($mem / 1024);
            if ($mem >= $memoryLimit) {
                posix_kill($pid, SIGINT);
            }
        }
    }

    /**
     * Get memory limit
     * @return float
     */
    private static function getMemoryLimit($memoryLimit)
    {
        if ($memoryLimit === 0) {
            return 0;
        }
        $usePhpIni = false;
        if (!$memoryLimit) {
            $memoryLimit = ini_get('memory_limit');
            $usePhpIni = true;
        }

        if ($memoryLimit == -1) return 0;
        $unit = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        if ($unit === 'g') {
            $memoryLimit = 1024 * (int)$memoryLimit;
        } else if ($unit === 'm') {
            $memoryLimit = (int)$memoryLimit;
        } else if ($unit === 'k') {
            $memoryLimit = ((int)$memoryLimit / 1024);
        } else {
            $memoryLimit = ((int)$memoryLimit / (1024 * 1024));
        }
        if ($memoryLimit < 30) {
            $memoryLimit = 30;
        }
        if ($usePhpIni) {
            $memoryLimit = (int)(0.8 * $memoryLimit);
        }
        return $memoryLimit;
    }

    /**
     * Check Files Change
     * @param array|string $monitorDir
     * @param array $extensions
     * @return boolean
     */
    private static function checkFilesChange($monitorDir, array $extensions): bool
    {
        static $lastMtime, $tooManyFilesCheck;
        if (!$lastMtime) $lastMtime = time();

        clearstatcache();
        if (!is_dir($monitorDir)) {
            if (!is_file($monitorDir)) return false;
            $iterator = [new SplFileInfo($monitorDir)];
        } else {
            // recursive traversal directory
            $dirIterator = new RecursiveDirectoryIterator($monitorDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS);
            $iterator = new RecursiveIteratorIterator($dirIterator);
        }
        $count = 0;
        foreach ($iterator as $file) {
            $count++;
            /** var SplFileInfo $file */
            if (is_dir($file->getRealPath())) {
                continue;
            }
            // check mtime
            if ($lastMtime < $file->getMTime() && (in_array('*', $extensions) || in_array($file->getExtension(), $extensions, true))) {
                $var = 0;
                exec(ProcessService::php("-l {$file}"), $out, $var);
                if ($var) {
                    $lastMtime = $file->getMTime();
                    continue;
                }
                $lastMtime = $file->getMTime();
                echo $file . " update and reload\n";
                // send SIGUSR1 signal to master process for reload
                if (DIRECTORY_SEPARATOR === '/') {
                    // ProcessService::exec(ProcessService::think('xadmin:worker reload'));
                    posix_kill(posix_getppid(), SIGUSR1);
                } else {
                    return true;
                }
                break;
            }
        }
        if (!$tooManyFilesCheck && $count > 1000) {
            echo "Monitor: There are too many files ($count files) in $monitorDir which makes file monitoring very slow\n";
            $tooManyFilesCheck = 1;
        }
        return false;
    }
}