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

    /**
     * 最长执行时间(秒), 0为不限制
     * @var int 
     */
    private $__max_exeucte_time = 0;

    /**
     * 最大执行任务数量, 0为不限制
     * @var int 
     */
    private $__max_exeucte_jobs = 0;
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
        $this->__process_log_file = strtr($config['process_log_file'] ?? 'process.log', ['.log' => '']);
        $this->_processName       = $config['process_name'] ?? ' :php-jobs';
        $this->__max_exeucte_time = $config['child_execute_time'] ?? 0;
        $this->__max_exeucte_jobs = $config['child_execute_jobs'] ?? 0;

        mkdirs($this->__pid_dir);
    }

    /**
     * 初始化
     */
    protected function _init() {
        //判断进程是否正在运行
        if (file_exists($this->__pid_file)) {
            $pid = file_get_contents($this->__pid_file);
            if (!$pid) {
                die("process may be runing, because I can't read PID from file({$this->__pid_file})" . PHP_EOL);
            }
            //多次确认主进程是否在运行
            for ($i = 0; $i != 3; $i++) {
                if (\Swoole\Process::kill($pid, 0)) {
                    die('process already runing, please stop or kill it first, PID=' . $pid . PHP_EOL);
                }
                sleep(1);
            }
        }

        //启动进程
        \Swoole\Process::daemon(); //使当前进程蜕变为一个守护进程
        $this->__pid = getmypid();
        if (!$this->__pid) {
            throw new \RuntimeException('can not get the master PID');
        }
        $this->__status = self::STATUS_RUNNING;

        $data = [
            'pid'    => $this->__pid,
            'status' => $this->__status
        ];

        $this->__setPidFile();
        $this->__setMasterInfo($data);
        $this->__setProcessName('master' . $this->_processName);
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
     * 
     * @param array $data
     * @throws \RuntimeException
     */
    private function __setMasterInfo(array $data) {
        $this->_logger->log('master status: ' . $data['status'], Logger::LEVEL_INFO, $this->__process_log_file, true);
        if (!file_put_contents($this->__pid_info_file, json_encode($data, JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('can not save master-info file with pid:' . $this->__pid);
        }
    }

    /**
     * 获取主进程的信息
     * 
     * @param string $key
     * @return array|mixed
     * @throws \RuntimeException
     */
    public function getMasterInfo(string $key = '') {
        if (!file_exists($this->__pid_info_file) || !is_readable($this->__pid_info_file)) {
            return null;
        }
        $data = file_get_contents($this->__pid_info_file);
        if (false === $data) {
            throw new \RuntimeException('can not read master-info file with pid:' . $this->__pid);
        }
        $data = json_decode($data, true);
        if ($key !== '') {
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
        $this->_registTopics();
        $this->_registTimer();
    }

    /**
     * 注册topic
     */
    protected function _registTopics() {
        $topics_config = Config::getConfig('topics');
        foreach ($topics_config as $topic_info) {
            $topic = new Topic($topic_info);
            $topic->execStatic(function() use($topic) {
                if (self::STATUS_RUNNING !== $this->__status) {
                    return;
                }
                $pid = $this->_forkWorker($topic, Worker::TYPE_STATIC);
                if (!$pid) {
                    $errno  = swoole_errno();
                    $errmsg = swoole_strerror($errno);
                    $this->_logger->log("worker start failed, it will exited later; \nERRNO: {$errno}\nERRMSG: {$errmsg}", Logger::LEVEL_ERROR, 'error');
                    $this->_waitWorkers();
                } else {
                    $this->_logger->log("worker start, PID={$pid}, TYPE=" . Worker::TYPE_STATIC, Logger::LEVEL_INFO, $this->__process_log_file, true);
                }
            });
            $this->__topics[] = $topic;
        }
    }

    /**
     * fork子进程
     * @param \Gino\Jobs\Core\Topic $topic
     * @param string $child_type 子进程类型
     * @return int 成功返回子进程的ID，失败返回false
     */
    protected function _forkWorker(Topic $topic, string $child_type) {
        $worker = new Worker($child_type);
        $worker->setTopic($topic);
        $worker->action(function(Worker $worker) use($topic) {
            $this->_checkMpid($worker);
            $this->__setProcessName('worker' . $this->_processName);
            $job          = $topic->newJob();
            $execute_jobs = 0; //已经执行的任务数量
            do {
                //每100毫秒检测一次主进程的运行状态
                if (microtime(true) - ($this->__status_updatetime ?? 0) > 0.01) {
                    $this->__status            = $this->getMasterInfo('status');
                    $this->__status_updatetime = microtime(true);
                }
                //执行任务

                $execute_jobs++; //执行任务数+1
                //$this->_logger->log('job: ' . $execute_jobs);
                sleep(1);

                //当长时间处于空闲状态，则让进程进入休眠
                //结束条件
                $where = true;
                if (self::STATUS_RUNNING !== $this->__status) {
                    $where = false;
                } else if ($this->__max_exeucte_jobs > 0 && $execute_jobs >= $this->__max_exeucte_jobs) { //执行任务数限制
                    $where = false;
                } else if ($this->__max_exeucte_time > 0 && $worker->getDuration() >= $this->__max_exeucte_time) { //执行时间限制
                    $where = false;
                }
            } while ($where);
            unset($this->__status_updatetime);
        });
        try {
            $pid = $worker->start();
            if ($pid) {
                $this->__workers[$pid] = $worker;
            }
        } catch (\Exception $ex) {
            catchError($this->_logger, $ex);
        } catch (\Throwable $ex) {
            catchError($this->_logger, $ex);
        }
        return $pid;
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
            $this->_waitWorkers();
        });

        //待定
        \Swoole\Process::signal(SIGUSR2, function($signo) {
            foreach ($this->__topics as $topic) {
                $topic->execDynamic(function() use($topic) {
                    if (self::STATUS_RUNNING !== $this->__status) {
                        return;
                    }
                    $pid = $this->_forkWorker($topic, Worker::TYPE_DYNAMIC);
                    if ($pid) {
                        $this->_logger->log("worker start, PID={$pid}, TYPE=" . Worker::TYPE_DYNAMIC, Logger::LEVEL_INFO, $this->__process_log_file, true);
                    }
                });
            }
        });
        //子进程关闭信号
        \Swoole\Process::signal(SIGCHLD, function($signo) {
            try {
                while ($ret = \Swoole\Process::wait(false)) { //$ret = array('code' => 0, 'pid' => 15001, 'signal' => 15);
                    $pid    = $ret['pid'];
                    $worker = $this->__workers[$pid];
                    unset($this->__workers[$pid]);

                    //主进程正常运行且子进程是静态类型，则重启该进程
                    if ($this->__status == self::STATUS_RUNNING && $worker && $worker->getType() === Worker::TYPE_STATIC) {
                        //多次尝试重启进程
                        for ($i = 0; $i != 20; ++$i) {
                            $new_pid = $this->_forkWorker($worker->getTopic(), $worker->getType());
                            if ($new_pid > 0) {
                                break;
                            }
                        }

                        if (!$new_pid) { //重启失败
                            $errno  = swoole_errno();
                            $errmsg = swoole_strerror($errno);
                            $this->_logger->log("worker process restart failed, it will exited later; \nERRNO: {$errno}\nERRMSG: {$errmsg}", Logger::LEVEL_ERROR, 'error', true);
                            $this->_waitWorkers();
                            continue;
                        }
                        $this->_logger->log("worker restart, SIGNAL={$signo}, PID={$new_pid}, TYPE={$worker->getType()}", Logger::LEVEL_INFO, $this->__process_log_file, true);
                    } else {
                        $this->_logger->log("worker exit, SIGNAL={$signo}, PID={$pid}, TYPE={$worker->getType()}", Logger::LEVEL_INFO, $this->__process_log_file, true);
                    }

                    //释放worker资源
                    if ($worker) {
                        $worker->free();
                    }

                    //主进程状态为WAIT且所有子进程退出, 则主进程安全退出
                    if (empty($this->__workers) && $this->__status == self::STATUS_WAIT) {
                        $this->_logger->log('all workers exit, master exit security', Logger::LEVEL_INFO, $this->__process_log_file);
                        $this->_exit();
                    }
                }
            } catch (\Exception $ex) {
                catchError($this->_logger, $ex);
            } catch (\Throwable $ex) {
                catchError($this->_logger, $ex);
            }
        });
    }

    /**
     * 注册定时器
     */
    protected function _registTimer() {

        //swoolev4.2.10 开始不支持在协程中fork子进程
        //所以此处新建一个子进程对topic进行管理
        $topic_worker = new \Swoole\Process(function($worker) {
            $this->__setProcessName('manger' . $this->_processName);
            do {
                //每100毫秒检测一次主进程的运行状态
                if (microtime(true) - ($this->__status_updatetime ?? 0) > 0.01) {
                    $this->__status            = $this->getMasterInfo('status');
                    $this->__status_updatetime = microtime(true);
                }
            } while (self::STATUS_WAIT !== $this->__status);
            unset($this->__status_updatetime);
        });
        try {
            $topic_worker->start();
        } catch (\Exception $ex) {
            catchError($this->_logger, $ex);
        } catch (\Throwable $ex) {
            catchError($this->_logger, $ex);
        }

        //检测topic
        /*
          \Swoole\Timer::after(5000, function() {
          foreach ($this->__topics as $topic) {
          $topic->execDynamic(function() use($topic) {
          if (self::STATUS_RUNNING !== $this->__status) {
          return;
          }
          $pid = $this->_forkWorker($topic, Worker::TYPE_DYNAMIC);
          if ($pid) {
          $this->_logger->log("worker start, PID={$pid}, TYPE=" . Worker::TYPE_DYNAMIC, Logger::LEVEL_INFO, $this->__process_log_file, true);
          }
          });
          }
          });
         */
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
                $this->_logger->log('worker was killed, PID=' . $pid, Logger::LEVEL_INFO, $this->__process_log_file);
                $this->_logger->log('worker count: ' . count($this->__workers), Logger::LEVEL_INFO, $this->__process_log_file);
            }
        }
    }

    /**
     * 通知并等待所有子进程退出
     */
    protected function _waitWorkers() {
        $this->__status = self::STATUS_WAIT;
        $data           = $this->getMasterInfo();
        $data['status'] = $this->__status;
        $this->__setMasterInfo($data);
        $this->_logger->log('wait workers quit', Logger::LEVEL_INFO, $this->__process_log_file);
        //特殊情况下，此时所有子进程全部都已经结束，则直接安全退出
        if (empty($this->__workers)) {
            $this->_exit();
        }
    }

    /**
     * 检查主进程，如果主进程已经退出，则子进程也退出
     */
    protected function _checkMpid(Worker $worker) {
        if (!\Swoole\Process::kill($this->__pid, 0)) {
            $this->_logger->log("Master process exited, I [{$worker['pid']}] also quit\n");
            $worker->exitWorker();
        }
    }

}
