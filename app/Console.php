<?php

namespace Gino\Jobs;

use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Config;
use Gino\Jobs\Core\Process;

/**
 * 控制台
 * 
 * @author Gino Huang <binsuper@126.com>
 */
class Console {

    protected $_logger;

    public function __construct(array $config) {
        //检测配置信息
        if (empty($config['log']) || empty($config['log']['log_dir'])) {
            die('config log.log_dir must be set' . PHP_EOL);
        }

        //初始化配置
        Config::setConfig($config);
        //初始化日志实例
        Logger::regist(Config::getConfig('log', 'log_dir'), Config::getConfig('log', 'log_file', 'application.log'));
        Logger::regist(Config::getConfig('log', 'log_dir'), Config::getConfig('process', 'process_log_file', 'process.log'), 'PROCESS');

        //初始化对象
        $this->_logger = Logger::getLogger();
    }

    /**
     *  运行控制台
     * 
     * @global array $argv
     */
    public function run() {
        global $argv;
        $command_args = array_slice($argv, 1);
        $act          = $command_args[0] ?? 'help';

        switch ($act) {
            case 'help': //打印帮助信息
                $this->printHelpMessage();
                $this->_logger->log('test');
                $this->_logger->flush();
                break;
            case 'start': //开始运行
                $this->start();
                break;
            case 'stop': //停止运行
                $this->stop();
                break;
            case 'restart': //重启
                $this->restart();
                break;
        }
    }

    /**
     * 打印帮助信息
     */
    public function printHelpMessage() {
        $txt = <<<HELP

{#y}Usage:
{##}  command [options] [arguments]

{#y}Options:
{##}
{#y}Available commands:
{#g}  help          {##}Displays help message
{#g}  start         {##}start the program
{#g}  stop          {##}stop the program
{#g}  restart       {##}restart the program

HELP;
        $rep = [
            '{#y}' => "\033[0;33m", //黄色
            '{#g}' => "\033[0;32m", //绿色
            '{##}' => "\033[0m" // 清空颜色
        ];
        echo strtr($txt, $rep);
    }

    /**
     * 启动进程
     */
    public function start() {
        $master_process = new Process();
        $master_process->start();
    }

    /**
     * 停止进程, 平滑退出
     */
    public function stop() {
        try {
            $master_process = new Process();
            $pid            = $master_process->getMasterInfo('pid');
            if ($pid) {
                \Swoole\Process::kill($pid, SIGUSR1);
                return true;
            }
        } catch (\Exception $ex) {
            catchError($this->_logger, $ex);
            echo 'stop error';
        }
        return false;
    }

    /**
     * 重启进程
     */
    public function restart() {
        if ($this->stop()) {
            $this->start();
        }
    }

}
