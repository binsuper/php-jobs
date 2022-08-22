<?php

namespace Gino\Jobs;

use Gino\Jobs\Core\IFace\ICommand;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Config;
use Gino\Jobs\Core\Process;
use Gino\Jobs\Core\Utils;

/**
 * 控制台
 *
 * @author Gino Huang <binsuper@126.com>
 */
class Console {

    protected $_logger;
    private   $__run_opts = [];
    private   $__run_args = [];

    public function __construct(array $config) {
        //检测配置信息
        if (empty($config['log']) || empty($config['log']['log_dir'])) {
            die('config log.log_dir must be set' . PHP_EOL);
        }

        //初始化配置
        Config::setConfig($config);
        //初始化日志实例
        Logger::regist(Config::getConfig('log', 'log_dir'), Config::getConfig('log', 'log_file', 'application.log'), '__MAIN__', Config::getConfig('log', 'log_level', ''));
        Logger::regist(Config::getConfig('log', 'log_dir'), Config::getConfig('process', 'process_log_file', 'process.log'), 'PROCESS', Config::getConfig('log', 'log_level', ''));

        //初始化对象
        $this->_logger = Logger::getLogger();
        $this->_logger->info('123');
        $this->_logger->flush();
    }

    /**
     * 解析参数
     *
     * @return string
     */
    public function parseArgs() {
        if (empty($this->__run_args)) {
            //解析参数
            global $argv;

            $command_args = array_slice($argv, 1);
            $this->__run_opts = getopt('', ['no-delay']) ?: []; //启动配置项
            foreach ($command_args as $arg) {
                if ($arg[0] === '-') {
                    continue;
                }
                $this->__run_args[] = $arg;
            }
        }
        return $this->__run_args[0] ?? 'help';
    }

    /**
     *  运行控制台
     *
     * @global array $argv
     */
    public function run() {

        if (!extension_loaded('swoole')) {
            //die('I need ext-swoole！！！');
        }

        $act = $this->parseArgs();

        //动作
        switch ($act) {
            case 'help': //打印帮助信息
                $this->printHelpMessage();
                $this->_logger->flush();
                break;
            case 'start': //开始运行
                $this->start($this->__run_opts);
                break;
            case 'stop': //停止运行
                $this->stop();
                break;
            case 'restart': //重启
                $this->restart();
                break;
            case 'status': //重启
                $this->showStatus();
                break;
            case 'zombie': //杀死僵尸进程
                $this->killZombie();
                break;
            case 'check': //检查配置是否正确
                $this->checkConfig();
                break;
            case 'flush': //刷新日志
                $this->flush();
                break;
            case 'exec': // 执行脚本
                $this->executeCommand(array_slice($this->__run_args, 1));
                break;
        }
    }

    /**
     * 打印帮助信息
     */
    public function printHelpMessage() {
        $txt = <<<HELP

{#y}Usage:
{##}  [options] command [arguments]

{#y}Options:
{#g}  --no-delay        {##}disable delay jobs

{#y}Available commands:
{#g}  help              {##}displays help message
{#g}  start             {##}start the program
{#g}  stop              {##}stop the program
{#g}  restart           {##}restart the program
{#g}  status            {##}show status
{#g}  zombie            {##}try killing the zombie process
{#g}  check             {##}check the configuration
{#g}  flush             {##}flush log to log_file
{#g}  exec [job]        {##}execute [job] command

HELP;
        $rep = [
            '{#y}' => "\033[0;33m", //黄色
            '{#g}' => "\033[0;32m", //绿色
            '{##}' => "\033[0m" // 清空颜色
        ];
        echo strtr($txt, $rep);
    }

    /**
     * 获取主进程
     *
     * @return Process|null
     */
    public function process() {
        static $process = null;
        if ($process == NULL) {
            $process = new Process();
        }
        return $process;
    }

    /**
     * 启动进程
     */
    public function start(array $run_opts = []) {
        $this->checkConfig();
        $master_process = $this->process();
        if (isset($run_opts['no-delay'])) {
            $master_process->noDelay();
        }
        $master_process->start($run_opts);
    }

    /**
     * 停止进程, 平滑退出
     */
    public function stop($no_close = false) {
        try {
            $master_process = new Process();
            $pid = $master_process->getMasterInfo('pid');
            if ($no_close) {
                if (!$pid) {
                    return true;
                }
                return \Swoole\Process::kill($pid, 0) ? false : true;
            } else {
                if ($pid) {
                    \Swoole\Process::kill($pid, SIGUSR1);
                    return true;
                }
            }
        } catch (\Exception $ex) {
            Core\Utils::catchError($this->_logger, $ex);
            echo 'stop error' . PHP_EOL;
        }
        return false;
    }

    /**
     * 重启进程
     */
    public function restart() {
        //获取关闭前的配置
        $master_process = new Process();
        $run_opt = $master_process->getMasterInfo('options') ?: [];
        $pid = $master_process->getMasterInfo('pid');
        //如果没有启动，则启动,或者重启
        if (!$pid || !\Swoole\Process::kill($pid, 0)) {
            $this->start($run_opt);
        } else {
            //重启
            if ($this->stop()) {
                while (!$this->stop(true)) {
                    sleep(1);
                }
                $this->start($run_opt);
            }
        }
    }

    /**
     * 检查配置
     *
     * @throws \Exception
     */
    public function checkConfig() {
        try {
            //topic
            $topics_config = Config::getConfig('topics');
            if (empty($topics_config)) {
                throw new \Exception('config<topics> is empty');
            }
            if (!is_array($topics_config)) {
                throw new \Exception('config<topics> must be a array');
            }
            foreach ($topics_config as $topic_info) {
                if (empty($topic_info['name'])) {
                    throw new \Exception('topic\'s name must be a non-empty string');
                }
                if (empty($topic_info['action'])) {
                    throw new \Exception('topic\'s action must be a class name');
                }
            }
            //queue
            $config = Config::getConfig('queue');
            if (empty($config)) {
                throw new \Exception('config<queue> is empty');
            }
            $class = $config['class'];
            if (!class_implements($class)[Core\IFace\IQueueDriver::class]) {
                throw new \Exception("queue driver($class) must implements class(" . Core\IFace\IQueueDriver::class . ')');
            }
        } catch (\Exception $ex) {
            Core\Utils::catchError($this->_logger, $ex);
            echo 'the configuration syntax is error;' . PHP_EOL;
            echo 'error: ' . $ex->getMessage() . PHP_EOL;
            exit();
        } catch (\Throwable $ex) {
            Core\Utils::catchError($this->_logger, $ex);
            echo 'the configuration syntax is error;' . PHP_EOL;
            echo 'error: ' . $ex->getMessage() . PHP_EOL;
            exit();
        }
        echo 'the configuration syntax is OK;' . PHP_EOL;
    }

    /**
     * 杀死僵尸进程
     */
    public function killZombie() {
        $master_process = new Process();
        $pid = $master_process->getMasterInfo('pid');
        if ($pid && \Swoole\Process::kill($pid, 0)) {
            echo 'program is running, can not kill zombie process' . PHP_EOL;
            return;
        }
        $master_process->waitWorkers();
    }

    /**
     * 展示状态信息
     */
    public function showStatus() {
        $master_process = new Process();
        $pid = $master_process->getMasterInfo('pid');
        if (!$pid || !\Swoole\Process::kill($pid, 0)) {
            echo 'program is not running' . PHP_EOL;
            return;
        }
        if (@\Swoole\Process::kill($pid, SIGUSR2)) {
            $dir = Config::getConfig('process', 'data_dir');
            echo 'program status was updated; detail in the file ' . $dir . DIRECTORY_SEPARATOR . 'status.info' . PHP_EOL;
        }
    }

    /**
     * 刷新日志
     */
    public function flush() {
        $master_process = new Process();
        $master_process->flush();
        echo 'flush log...' . PHP_EOL;
    }

    /**
     * 执行脚本
     */
    public function executeCommand($args) {
        $command = $args[0] ?? false;
        if (!$command) {
            die('job name is empty' . PHP_EOL);
        }

        $class = '';

        $topics_config = Config::getConfig('topics');
        foreach ($topics_config as $topic_info) {
            if (isset($topic_info['command'])) {
                if ($topic_info['command'] === $command) {
                    $class = $topic_info['action'];
                    break;
                }
            } else {
                if ($topic_info['name'] === $command) {
                    $class = $topic_info['action'];
                    break;
                }
            }
        }

        if (empty($class)) {
            die('job name is fnot found' . PHP_EOL);
        }

        if (!isset(class_implements($class)[ICommand::class])) {
            die('job is not the command' . PHP_EOL);
        }

        try {
            (new $class())->execute(array_slice($args, 1));
        } catch (\Throwable $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
            die($ex->getMessage() . PHP_EOL);
        }

    }

}
