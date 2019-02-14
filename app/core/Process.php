<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Config;

/**
 * 进程管理
 * 
 * @author GinoHuang <binsuper@126.com>
 */
class Process {

    const STATUS_RUNNING = 'running';   //运行中
    const STATUS_WAIT    = 'wait';      //等待所有子进程平滑结束
    const STATUS_STOP    = 'stop';      //运行中

    private $__pid_dir;
    private $__pid_file      = 'master.pid';
    private $__pid_info_file = 'master.info';
    private $__pid;
    private $__status; //进程状态
    private $__process_log_file;
    private $__workers       = []; //子进程列表
    private $__topics        = [];
    protected $_logger;
    protected $_processName; //进程名称

    public function __construct() {
        $config = Config::getConfig('process');
        if (empty($config) || empty($config['data_dir'])) {
            die('config process.data_dir must be set' . PHP_EOL);
        }

        $this->_logger            = Logger::getLogger();
        $this->__pid_dir          = $config['data_dir'];
        $this->__pid_file         = $this->__pid_dir . DIRECTORY_SEPARATOR . $this->__pid_file;
        $this->__pid_info_file    = $this->__pid_dir . DIRECTORY_SEPARATOR . $this->__pid_info_file;
        mkdirs($this->__pid_dir);
        $this->__process_log_file = strtr($config['process_log_file'] ?? 'process.log', ['.log' => '']);
        $this->_processName       = $config['process_name'] ?? ':php-jobs';
    }

    /**
     * 初始化
     */
    protected function _init() {
        //启动进程
        \Swoole\Process::daemon(); //使当前进程蜕变为一个守护进程
        $this->__pid = getmypid();
        if (!$this->__pid) {
            throw new \RuntimeException('can not get the master process id');
        }
        $this->__status = self::STATUS_RUNNING;

        $data = [
            'pid'    => $this->__pid,
            'status' => $this->__status
        ];

        $this->__setPidFile();
        $this->__setMasterInfo($data);
        $this->__setProcessName($this->_processName);
    }

    /**
     * 退出主进程
     */
    protected function _exit() {
        @unlink($this->__pid_file);
        @unlink($this->__pid_info_file);
        $this->_logger->log('master process exit', Logger::LEVEL_INFO, $this->__process_log_file);
        $this->_logger->flush();
        sleep(1);
        exit();
    }

    /**
     * 设置pid文件
     * @throws \RuntimeException
     */
    private function __setPidFile() {
        if (!file_put_contents($this->__pid_file, $this->__pid)) {
            throw new \RuntimeException('can not save pid file with pid:' . $this->__pid);
        }
    }

    /**
     * 设置主进程的信息
     * @param array $data
     */
    private function __setMasterInfo(array $data) {
        if (!file_put_contents($this->__pid_info_file, json_encode($data, JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('can not save master-info file with pid:' . $this->__pid);
        }
    }

    /**
     * 获取主进程的信息
     * @return array
     */
    public function getMasterInfo(string $key = ''): array {
        $data = file_get_contents($this->__pid_info_file);
        if (false === $data) {
            throw new \RuntimeException('can not read master-info file with pid:' . $this->__pid);
        }
        $data = json_decode($data, true);
        if ($key === '') {
            return $data[$key] ?? null;
        }
        return $data;
    }

    /**
     * 设置进程名称
     * @param string $process_name
     */
    private function __setProcessName(string $process_name) {
        swoole_set_process_name($process_name);
    }

    /**
     * 启动进程
     */
    public function start() {
        $this->_init();
        $this->_registSignal();
        $this->_forkTopics();
    }

    /**
     * fork子进程处理topic
     */
    protected function _forkTopics() {
        $topics_config = Config::getConfig('topics');
        foreach ($topics_config as $topic_info) {
            $topic = new Topic($topic_info);
            $topic->execute(function($job) use($topic) {
                $worker = new Worker(function($worker) use($job) {
                    $this->_checkMpid($worker);
                    $this->__setProcessName('worker ' . $this->_processName);
                    do {
                        $this->__status = $this->getMasterInfo('status');
                    } while (self::STATUS_RUNNING == $this->__status);
                });
                $pid                   = $worker->start();
                $this->__workers[$pid] = $worker;
                $this->__topics[$pid]  = $topic;
            });
        }
    }

    /**
     * 注册信号
     */
    protected function _registSignal() {

        //强制关闭进程
        \Swoole\Process::signal(SIGTERM, function($signo) {
            $this->_killMaster();
        });

        //强制关闭进程
        \Swoole\Process::signal(SIGKILL, function($signo) {
            $this->_killMaster();
        });

        //平滑退出
        \Swoole\Process::signal(SIGUSR1, function($signo) {
            
        });

        //待定
        \Swoole\Process::signal(SIGUSR2, function($signo) {
            
        });

        //子进程关闭信号
        \Swoole\Process::signal(SIGCHLD, function($signo) {
            try {
                while ($ret = \Swoole\Process::wait(false)) { //$ret = array('code' => 0, 'pid' => 15001, 'signal' => 15);
                    echo "PID={$ret['pid']}\n";
                    $pid = $ret['pid'];
                    unset($this->__workers[$pid]);
                }
            } catch (\Exception $ex) {
                
            } catch (\Throwable $e) {
                
            }
        });
    }

    /**
     * 强制杀死主进程
     */
    protected function _killMaster() {
        $this->__status = self::STATUS_STOP;
        $this->_logger->log('master process receive signal(SIGTEM|SIGKILL), then will be kill all workers', Logger::LEVEL_INFO, $this->__process_log_file);
        $this->_killWorkers();
        $this->_exit();
    }

    /**
     * 强制杀死所有子进程
     */
    protected function _killWorkers() {
        if ($this->__workers) {
            foreach ($this->__workers as $pid => $worker) {
                \Swoole\Process::kill($pid);
                unset($this->__workers[$pid]);
                $this->_logger->log('[pid:' . $pid . ']worker was killed', Logger::LEVEL_INFO, $this->__process_log_file);
                $this->_logger->log('worker count: ' . count($this->__workers), Logger::LEVEL_INFO, $this->__process_log_file);
            }
        }
    }

    /**
     * 检查主进程，如果主进程已经退出，则子进程也退出
     */
    protected function _checkMpid(&$worker) {
        if (!\Swoole\Process::kill($this->__pid, 0)) {
            $this->_logger->log("Master process exited, I [{$worker['pid']}] also quit\n");
            $worker->exit();
        }
    }

}
