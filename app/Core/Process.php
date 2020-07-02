<?php

namespace Gino\Jobs\Core;

use Cassandra\Time;
use Gino\Jobs\Core\IFace\IMonitor;
use Gino\Jobs\Core\Exception\ExitException;
use Gino\Jobs\Core\IFace\IQueueDelay;
use Gino\Jobs\Core\Queue\Delay\Message as DelayMessage;
use Swoole\Timer;

/**
 * 进程管理
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Process {

    const VERSION        = '1.0.14';
    const STATUS_RUNNING = 'running';   //运行中
    const STATUS_WAIT    = 'wait';      //等待所有子进程平滑结束
    const STATUS_STOP    = 'stop';      //运行中

    private $__pid_dir;
    private $__pid_file        = 'master.pid';
    private $__pid_info_file   = 'master.info';
    private $__pid_status_file = 'status.info';
    private $__worker_info_dir;
    private $__pid;
    private $__status; //进程状态
    private $__process_log_file;
    private $__workers         = []; //子进程列表
    private $__topics          = [];
    private $__topic_info      = []; // 主题信息

    /**
     * 进程用户
     *
     * @var
     */
    private $__user = '';

    /**
     * 最大执行任务数量, 0为不限制
     *
     * @var int
     */
    private $__max_exeucte_jobs = 0;

    /**
     * 动态进程最长闲置时间(秒), 0为不限制
     *
     * @var int
     */
    private $__dynamic_idle_time = 0;

    /**
     * 健康的队列长度, 超出后将启动动态进程
     *
     * @var int
     */
    private $__queue_health_size = 0;

    /**
     * Worker监控间隔
     *
     * @var int
     */
    private $__monitor_interval = 60000;

    /**
     * 日志操作对象
     *
     * @var Logger
     */
    protected $_logger;

    /**
     * 进程名称
     *
     * @var string
     */
    protected $_processName;

    /**
     * 记录进程开始时间
     *
     * @var int
     */
    private $__begin_time = 0;

    /**
     * 延迟队列的名称
     *
     * @var string
     */
    private $__delay_queue_name = '';

    /**
     * 延迟队列的执行索引
     *
     * @var int
     */
    private $__roll_slot = 0;

    /**
     * 是否允许延时队列
     *
     * @var bool
     */
    private $__opt_delay_enable = false;

    /**
     * 监视器
     *
     * @var array
     */
    private $__monitors = [];

    public function __construct() {
        $config = Config::getConfig('process');
        if (empty($config) || empty($config['data_dir'])) {
            die('config process.data_dir must be set' . PHP_EOL);
        }

        $this->_logger             = Logger::getLogger();
        $this->__user              = $config['user'] ?? '';
        $this->__pid_dir           = $config['data_dir'];
        $this->__pid_file          = $this->__pid_dir . DIRECTORY_SEPARATOR . $this->__pid_file;
        $this->__pid_info_file     = $this->__pid_dir . DIRECTORY_SEPARATOR . $this->__pid_info_file;
        $this->__worker_info_dir   = $this->__pid_dir . DIRECTORY_SEPARATOR . 'worker';
        $this->__process_log_file  = strtr($config['process_log_file'] ?? 'process.log', ['.log' => '']);
        $this->_processName        = $config['process_name'] ?? ' :php-jobs';
        $this->__max_exeucte_time  = $config['max_execute_time'] ?? 0;
        $this->__max_exeucte_jobs  = $config['max_execute_jobs'] ?? 0;
        $this->__dynamic_idle_time = $config['dynamic_idle_time'] ?? 0;
        $this->__queue_health_size = $config['queue_health_size'] ?? 0;
        $this->__monitor_interval  = $config['monitor_interval'] ?? 60000;
        $this->__delay_queue_name  = Config::getConfig('queue', 'delay_queue_name', '');
        Utils::mkdir($this->__pid_dir);
        Utils::mkdir($this->__worker_info_dir);

        if ($this->__delay_queue_name) {
            $this->__opt_delay_enable = true;
        }

        // 监视器
        $monitors = Config::getConfig('monitor', '', []);
        foreach ($monitors as $monitor) {
            $this->__monitors[] = new $monitor();
        }

    }

    /**
     * 初始化
     *
     * @param array $run_opt 运行时配置
     * @throws \RuntimeException
     */
    protected function _init(array $run_opt = []) {
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
        //用户
        if (empty($this->__user)) {
            $user = $group = '';
            if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
                $uinfo = posix_getpwuid(posix_getuid());
                $user  = $uinfo ? $uinfo['name'] : '';
            }
            if (function_exists('posix_getgrgid') && function_exists('posix_getgid')) {
                $ginfo = posix_getgrgid(posix_getgid());
                $group = $ginfo ? $ginfo['name'] : '';
            }
            $this->__user = $user . ':' . $group;
        } else {
            $info = explode(':', $this->__user);
            $user = $info[0];
            if (empty($user)) {
                throw new \RuntimeException('user who run the process is null');
            }
            $group = $info[1] ?? $user;
            if (function_exists('posix_getgrnam') && function_exists('posix_setgid')) {
                $ginfo = posix_getgrnam($group);
                if ($ginfo) {
                    posix_setgid($ginfo['gid']);
                }
            }
            if (function_exists('posix_getpwnam') && function_exists('posix_setuid')) {
                $uinfo = posix_getpwnam($user);
                if ($uinfo) {
                    posix_setuid($uinfo['uid']);
                }
            }
        }

        $this->__pid = getmypid();
        if (!$this->__pid) {
            throw new \RuntimeException('can not get the master PID');
        }
        $this->__status = self::STATUS_RUNNING;

        $data = [
            'pid'     => $this->__pid,
            'status'  => $this->__status,
            'options' => $run_opt
        ];

        $this->__setPidFile();
        $this->__setMasterInfo($data);
        $this->__setProcessName('master' . $this->_processName);

        $this->__begin_time = time();
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
     *
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
     *
     * @param string $process_name
     */
    private function __setProcessName(string $process_name) {
        //mac os 不支持设置进程名称
        if (function_exists('swoole_set_process_name') && PHP_OS != 'Darwin') {
            swoole_set_process_name($process_name);
        }
    }

    /**
     * 关闭延时队列功能
     */
    public function noDelay() {
        $this->__opt_delay_enable = false;
    }

    /**
     * 启动进程
     *
     * @param array $run_opts 运行时的配置
     */
    public function start(array $run_opts = []) {
        $this->_init($run_opts);
        $this->_registSignal();
        $this->_registTopics();
        $this->_registTimer();
        $this->_forkDelayWork();
    }

    /**
     * 注册topic
     */
    protected function _registTopics() {
        $topics_config = Config::getConfig('topics');
        foreach ($topics_config as $topic_info) {
            $topic = new Topic($topic_info);
            if (!$this->__opt_delay_enable) {
                if ($topic->getName() === $this->__delay_queue_name) {
                    continue;
                }
            }
            $topic->execStatic(function () use ($topic) {
                if (self::STATUS_RUNNING !== $this->__status) {
                    return;
                }
                $pid = $this->_forkWorker($topic, Worker::TYPE_STATIC);
                if (!$pid) {
                    $errno  = swoole_errno();
                    $errmsg = swoole_strerror($errno);
                    $this->_logger->log("worker start failed, it will exited later; \nERRNO: {$errno}\nERRMSG: {$errmsg}", Logger::LEVEL_ERROR, 'error');
                    $this->waitWorkers();
                } else {
                    $this->_logger->log("worker start, PID={$pid}, TYPE=" . Worker::TYPE_STATIC, Logger::LEVEL_INFO, $this->__process_log_file, true);
                }
            });

            $this->__topics[] = $topic;

            $this->__topic_info[$topic->getName()] = [];
        }
    }

    /**
     * fork子进程
     *
     * @param \Gino\Jobs\Core\Topic $topic
     * @param string $child_type 子进程类型
     * @return int 成功返回子进程的ID，失败返回false
     */
    protected function _forkWorker(Topic $topic, string $child_type) {
        $worker = new Worker($child_type);
        $worker->setTopic($topic);
        $worker->action(function () use ($worker, $topic) {
            //运行内容
            $this->_checkMpid($worker);
            $this->__setProcessName('worker' . $this->_processName);
            try {
                $job = $topic->newJob();
                if (!$job) {
                    throw new ExitException('topic(' . $topic->getName() . '): no job');
                }
            } catch (\Exception $ex) {
                Utils::catchError($this->_logger, $ex);
                $this->notifyMasterExited();
                return;
            } catch (\Throwable $ex) {
                Utils::catchError($this->_logger, $ex);
                $this->notifyMasterExited();
                return;
            }
            $update_status_ticker = 0;
            do {
                //每100毫秒检测一次主进程的运行状态
                if (microtime(true) - ($this->__status_updatetime ?? 0) > 0.01) {
                    $data                      = $this->getMasterInfo();
                    $this->__status            = $data['status'];
                    $this->__status_updatetime = microtime(true);
                    //flush log
                    go(function () use ($data) {
                        $flush = $data['flush'] ?? false;
                        if (false !== $flush && time() - $flush <= 30) {
                            if (!isset($this->__flush_time) || $this->__flush_time < $flush) {
                                $this->__flush_time = $flush;
                                $this->_logger->flush();
                            }
                        }
                    });
                }
                try {
                    //执行任务
                    $job->run();

                    //更新子进程状态
                    if ($update_status_ticker < time() - 5) { //5秒间隔
                        $update_status_ticker = time();
                        try {
                            $info = [
                                'pid'       => getmypid(),
                                'now'       => date('Y-m-d H:i:s'),
                                'duration'  => intval($worker->getDuration()) . 's', //已运行时长
                                'topic'     => $topic->getName(),
                                'type'      => $worker->getType(), //子进程类型
                                'status'    => $job->idleTime() > 30 ? 'idle' : 'running',
                                'done'      => $job->doneCount(), //已完成的任务数
                                'failed'    => $job->failedCount(), //拒绝的任务数量
                                'ack'       => $job->ackCount(), //正确应答的消息数量
                                'reject'    => $job->rejectCount(), //拒绝的消息数量
                                'avg_time'  => $job->avgTime(), //任务执行平均时长
                                'idle_time' => intval($job->idleTime()) . 's', //已闲置的时长
                                'mem_usage' => Utils::getMemoryUsage(), // 内存占用
                            ];
                            $this->_saveWorkerStatus($info);
                        } catch (\Throwable $ex) {
                            Utils::catchError($this->_logger, $ex);
                        }
                    }
                    //结束条件
                    $where = true;
                    if (self::STATUS_RUNNING !== $this->__status) {
                        $where = false;
                    } //执行任务数限制
                    else if ($topic->getMaxExecuteJobs() > 0 && ($job->doneCount() + $job->failedCount()) >= $topic->getMaxExecuteJobs()) {
                        $where = false;
                    } //执行时间限制
                    else if ($this->__max_exeucte_time > 0 && $worker->getDuration() >= $this->__max_exeucte_time) {
                        $where = false;
                    } //动态进程闲置时间限制
                    else if ($this->__dynamic_idle_time > 0 && $worker->getType() == Worker::TYPE_DYNAMIC && $job->idleTime() >= $this->__dynamic_idle_time) {
                        $where = false;
                    }
                    //当长时间处于空闲状态，则让进程进入半休眠
                    if ($where && $job->idleTime() <= $this->__max_exeucte_time && $job->idleTime() > 30) {
                        sleep(3);
                    }
                } catch (ExitException $ex) {
                    $where = false;
                } catch (\Throwable $ex) {
                    $where = true;
                    Utils::catchError($this->_logger, $ex);
                }
            } while ($where);
            unset($job);
            // $this->_saveWorkerStatus([], true);
            $this->_logger->flush();
        });
        try {
            $pid = $worker->start();
            if ($pid) {
                $this->__workers[$pid] = $worker;
            }
        } catch (\Exception $ex) {
            Utils::catchError($this->_logger, $ex);
        } catch (\Throwable $ex) {
            Utils::catchError($this->_logger, $ex);
        }
        return $pid;
    }

    /**
     * 开启演示队列功能
     *
     * @return int 成功返回子进程的ID，失败返回false
     */
    protected function _forkDelayWork() {
        if (!$this->__opt_delay_enable) {
            return false;
        }
        try {
            $worker = new Worker(Worker::TYPE_STATIC);
            $worker->action(function () use ($worker) {
                //运行内容
                $this->_checkMpid($worker);
                $this->__setProcessName('worker#delay' . $this->_processName);

                // 定时器 - 检测主进程的运行状态
                Timer::tick(500, function ($timer_id) {
                    try {
                        $data                      = $this->getMasterInfo();
                        $this->__status            = $data['status'];
                        $this->__status_updatetime = microtime(true);
                        //flush log
                        go(function () use ($data) {
                            $flush = $data['flush'] ?? false;
                            if (false !== $flush && time() - $flush <= 30) {
                                if (!isset($this->__flush_time) || $this->__flush_time < $flush) {
                                    $this->__flush_time = $flush;
                                    $this->_logger->flush();
                                }
                            }
                        });
                    } catch (\Throwable $ex) {
                        Utils::catchError($this->_logger, $ex);
                    }

                    if (self::STATUS_RUNNING !== $this->__status) { // 非运行状态
                        Timer::clear($timer_id);
                        return;
                    }
                });

                // 定时器 - 更新进程状态
                $update_status_ticker = 0;
                Timer::tick(500, function ($timer_id) use ($worker, &$update_status_ticker) {
                    if (self::STATUS_RUNNING !== $this->__status) { // 非运行状态
                        Timer::clear($timer_id);
                        return;
                    }
                    $update_status_ticker += 500;
                    if ($update_status_ticker >= 5000) { //5秒更新
                        $update_status_ticker = 0;
                        try {
                            $info = [
                                'pid'       => getmypid(),
                                'now'       => date('Y-m-d H:i:s'),
                                'name'      => '[delay]',
                                'duration'  => intval($worker->getDuration()) . 's', //已运行时长
                                'type'      => $worker->getType(), //子进程类型
                                'status'    => 'running',
                                'done'      => 0, //已完成的任务数
                                'failed'    => 0, //拒绝的任务数量
                                'ack'       => 0, //正确应答的消息数量
                                'reject'    => 0, //拒绝的消息数量
                                'avg_time'  => 0, //任务执行平均时长
                                'idle_time' => '0s', //已闲置的时长
                                'mem_usage' => Utils::getMemoryUsage(), // 内存占用
                            ];
                            $this->_saveWorkerStatus($info);
                        } catch (\Throwable $ex) {
                            Utils::catchError($this->_logger, $ex);
                        }
                    }
                });

                // 定时器 - 处理延时队列数据
                Timer::tick(1000, function ($timer_id) use ($worker, &$update_status_ticker) {
                    try {
                        if (self::STATUS_RUNNING !== $this->__status) { // 非运行状态
                            Timer::clear($timer_id);
                            return;
                        }

                        try {
                            $queue = Queue\Queue::getDelayQueue();
                        } catch (\Throwable $ex) {
                            Utils::catchError($this->_logger, $ex);
                            Timer::clear($timer_id);
                            return;
                        }
                        if (!$queue) {
                            Timer::clear($timer_id);
                            return;
                        }
                        if (!($queue instanceof IQueueDelay)) { //不支持延迟队列
                            Timer::clear($timer_id);
                            $queue->close();
                            unset($queue);
                            return;
                        }

                        $queue->scanDelayQueue(function (DelayMessage $msg) use ($queue) {
                            return $queue->pushTarget($msg->getTargetName(), $msg->getPayload()) ? true : false;
                        }, function ($leave_count) {
                            // 如果剩余数量不是很多，则等待执行完成, 否则立即结束
                            if ($leave_count >= 10000 && self::STATUS_RUNNING !== $this->__status) { // 非运行状态
                                return false;
                            }

                            // 当数据量过大时，定期进行休眠让出时间片，避免当前进程卡死
                            if ($leave_count > 1000) {
                                \Co::sleep(0.001);
                            }

                            return true;
                        });
                        unset($queue);
                    } catch (\Throwable $ex) {
                        Utils::catchError($this->_logger, $ex);
                    }

                });

                \Swoole\Event::wait();

            });

            try {
                $pid = $worker->start();
                if ($pid) {
                    $this->__workers[$pid] = $worker;
                }
            } catch (\Exception $ex) {
                Utils::catchError($this->_logger, $ex);
            } catch (\Throwable $ex) {
                Utils::catchError($this->_logger, $ex);
            }
            return $pid;

        } catch (\Throwable $ex) {
            Utils::catchError($this->_logger, $ex);
        }
        return false;
    }

    /**
     * 注册信号
     */
    protected function _registSignal() {

        //强制关闭进程
        \Swoole\Process::signal(SIGTERM, function ($signo) {
            $this->_killMaster();
        });

        //强制关闭进程
        \Swoole\Process::signal(SIGKILL, function ($signo) {
            $this->_killMaster();
        });

        //平滑退出
        \Swoole\Process::signal(SIGUSR1, function ($signo) {
            $this->waitWorkers();
        });

        //进程状态信息
        \Swoole\Process::signal(SIGUSR2, function ($signo) {
            $this->_saveMasterStatus();
        });

        //动态进程管理
        \Swoole\Process::signal(SIGALRM, function () {
            $this->__checkDynamic();
        });

        //子进程关闭信号
        \Swoole\Process::signal(SIGCHLD, function ($signo) {
            try {
                while ($ret = \Swoole\Process::wait(false)) { //$ret = array('code' => 0, 'pid' => 15001, 'signal' => 15);
                    $pid = $ret['pid'];

                    /**
                     * @var $worker Worker
                     */
                    $worker = $this->__workers[$pid];
                    unset($this->__workers[$pid]);
                    $this->__workers = array_slice($this->__workers, 0, null, true);

                    // 统计work执行的信息
                    $this->summaryWorkInfo($worker);

                    // 清理状态文件
                    $this->_saveWorkerStatus([], true, $pid);

                    //主进程正常运行且子进程是静态类型，则重启该进程
                    if ($this->__status == self::STATUS_RUNNING && $worker && $worker->getType() === Worker::TYPE_STATIC) {
                        //多次尝试重启进程
                        for ($i = 0; $i != 20; ++$i) {
                            if (!$worker->getTopic()) {
                                $new_pid = 0;
                                // 重启有BUG，子进程协程无效，无法阻塞进程
                                $new_pid = $this->_forkDelayWork();
                            } else {
                                $new_pid = $this->_forkWorker($worker->getTopic(), $worker->getType());
                            }
                            if ($new_pid > 0) {
                                break;
                            }
                        }

                        if (!$new_pid) { //重启失败
                            $errno  = swoole_errno();
                            $errmsg = swoole_strerror($errno);
                            $this->_logger->log("worker process restart failed, it will exited later; \nERRNO: {$errno}\nERRMSG: {$errmsg}", Logger::LEVEL_ERROR, 'error', true);
                            $this->waitWorkers();
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
                Utils::catchError($this->_logger, $ex);
            } catch (\Throwable $ex) {
                Utils::catchError($this->_logger, $ex);
            }
        });
    }

    /**
     * 注册定时器
     */
    protected function _registTimer() {
        //检测topic
        //4.2.10版本开始，不允许在协程中fork进程，所以使用信号处理
        Timer::tick(5000, function () {
            @\Swoole\Process::kill($this->__pid, SIGALRM);
        });

        //每10分钟自动保存当前的状态信息
        Timer::tick(600000, function () {
            @\Swoole\Process::kill($this->__pid, SIGUSR2);
        });

        //处理延迟任务
        /*
        if ($this->__opt_delay_enable) {
            \Swoole\Timer::tick(1000, function ($timer_id) {
                try {
                    if (empty($this->__topics)) {
                        \Swoole\Timer::clear($timer_id);
                        return;
                    }
                    try {
                        $queue = Queue\Queue::getDelayQueue();
                    } catch (\Throwable $ex) {
                        \Swoole\Timer::clear($timer_id);
                        return;
                    }
                    if (!$queue) {
                        \Swoole\Timer::clear($timer_id);
                        return;
                    }
                    if (!($queue instanceof IQueueDelay)) { //不支持延迟队列
                        \Swoole\Timer::clear($timer_id);
                        $queue->close();
                        unset($queue);
                        return;
                    }
                    $queue->scanDelayQueue(function (DelayMessage $msg) use ($queue) {
                        return $queue->pushTarget($msg->getTargetName(), $msg->getPayload()) ? true : false;
                    });
                    unset($queue);
                } catch (\Throwable $ex) {
                    Utils::catchError($this->_logger, $ex);
                }
            });
        }
        */

        //定期检测
        if (!empty($this->__monitors)) {
            Timer::tick($this->__monitor_interval, function () {
                foreach ($this->__monitors as $monitor) {
                    try {
                        /**
                         * @var IMonitor $monitor
                         */
                        $monitor->start();
                    } catch (\Throwable $ex) {
                        Utils::catchError($this->_logger, $ex);
                    }
                }

                //子进程的信息
                foreach ($this->__workers as $pid => $worker) {
                    /**
                     * @var Worker $worker
                     */
                    try {
                        $info = $this->_readWorkerStatus($pid);
                    } catch (\Throwable $ex) {
                        continue;
                    }
                    if ($info) {

                        // 监视器
                        foreach ($this->__monitors as $monitor) {
                            try {
                                /**
                                 * @var IMonitor $monitor
                                 */
                                $topic = $worker->getTopic();
                                if ($topic) {
                                    $monitor->processing($pid, $topic, $info);
                                }
                            } catch (\Throwable $ex) {
                                Utils::catchError($this->_logger, $ex);
                            }
                        }
                    }
                }

                foreach ($this->__monitors as $monitor) {
                    try {
                        /**
                         * @var IMonitor $monitor
                         */
                        $monitor->finish();
                    } catch (\Throwable $ex) {
                        Utils::catchError($this->_logger, $ex);
                    }
                }

            });
        }
    }


    /**
     * 动态进程管理
     */
    private function __checkDynamic() {
        try {
            foreach ($this->__topics as $topic) {
                $topic->execDynamic(function () use ($topic) {
                    if (self::STATUS_RUNNING !== $this->__status) {
                        return;
                    }
                    $pid = $this->_forkWorker($topic, Worker::TYPE_DYNAMIC);
                    if ($pid) {
                        $this->_logger->log("worker start, PID={$pid}, TYPE=" . Worker::TYPE_DYNAMIC, Logger::LEVEL_INFO, $this->__process_log_file, true);
                    }
                });
            }
        } catch (\Throwable $ex) {
            Utils::catchError($this->_logger, $ex);
        }
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
    public function waitWorkers() {
        $this->__status = self::STATUS_WAIT;
        $data           = $this->getMasterInfo();
        $data['status'] = $this->__status;
        $this->__setMasterInfo($data);
        $this->_logger->log('wait workers quit', Logger::LEVEL_INFO, $this->__process_log_file, true);
        //特殊情况下，此时所有子进程全部都已经结束，则直接安全退出
        if (empty($this->__workers) && getmypid() == ($data['pid'] ?? -1)) {
            $this->_exit();
        }
    }

    /**
     * 子进程调用
     * 通知主进程退出
     */
    public function notifyMasterExited() {
        $pid = $this->getMasterInfo('pid');
        if ($pid) {
            \Swoole\Process::kill($pid, SIGUSR1);
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

    /**
     * 展示进程状态
     *
     * @return string
     * @throws \Exception
     */
    public function showStatus() {
        //主进程信息
        $str = '---------------------------------------------' . $this->_processName . ' status-----------------------------------------------' . PHP_EOL;
        $str .= PHP_EOL . '#system' . PHP_EOL;
        $str .= "user: \t\t\t" . $this->__user . PHP_EOL;
        $str .= "php_version: \t\t" . PHP_VERSION . PHP_EOL;
        $str .= "php-jobs_version: \t" . self::VERSION . PHP_EOL;

        $str .= PHP_EOL . '#rumtime' . PHP_EOL;
        $str .= "start_at: \t\t" . date('Y-m-d H:i:s', $this->__begin_time) . PHP_EOL;
        $str .= "now: \t\t\t" . date('Y-m-d H:i:s') . PHP_EOL;
        $str .= "duration: \t\t" . floor((time() - $this->__begin_time) / 86400) . ' days ' . floor(((time() - $this->__begin_time) % 86400) / 3600) . ' hours' . PHP_EOL;
        $str .= "loadavg: \t\t" . Utils::getSysLoadAvg() . PHP_EOL;
        $str .= "memory_used: \t\t" . Utils::getMemoryUsage() . PHP_EOL;

        $str .= PHP_EOL . '#master' . PHP_EOL;
        $str .= "master_pid: \t\t" . $this->__pid . PHP_EOL;
        $str .= "master_status: \t\t" . $this->__status . PHP_EOL;
        $str .= "woker_num: \t\t" . count($this->__workers) . PHP_EOL;

        if ($this->__opt_delay_enable) {
            $queue = Queue\Queue::getDelayQueue();
            if ($queue) {
                $str .= PHP_EOL . '#queue' . PHP_EOL;
                $str .= "delay_count: \t\t" . $queue->getDelayQueueSize() . PHP_EOL;
            }
        }

        $str .= PHP_EOL . '#topic' . PHP_EOL;
        $str .= ' --------------------------------------------------------------------------------------' . PHP_EOL;
        $str .= ' ' . Utils::formatTablePrint(['', 'Topic', 'Done', 'Failed', 'Ack', 'Reject']) . PHP_EOL;
        $str .= ' --------------------------------------------------------------------------------------' . PHP_EOL;
        foreach ($this->__topic_info as $topic_name => $info) {
            $str .= ' ' . Utils::formatTablePrint([
                    '',
                    $topic_name ?? '-',
                    $info['done'] ?? '-',
                    $info['failed'] ?? '-',
                    $info['ack'] ?? '-',
                    $info['reject'] ?? '-',
                ]) . PHP_EOL;
        }

        //header
        $str .= PHP_EOL . '#worker' . PHP_EOL;
        $str .= ' -----------------------------------------------------------------------------------------------------------------------------------------------------------------------' . PHP_EOL;
        $str .= ' ' . Utils::formatTablePrint(['Pid', 'Topic', 'Type', 'Queue', 'Status', 'Runtime', 'Idletime', 'AvgTime', 'Done', 'Failed', 'Ack', 'Reject', 'Mem', 'Now']) . PHP_EOL;
        $str .= ' -----------------------------------------------------------------------------------------------------------------------------------------------------------------------' . PHP_EOL;
        //子进程的信息
        foreach ($this->__workers as $pid => $worker) {
            try {
                $info = $this->_readWorkerStatus($pid);
            } catch (\Throwable $ex) {
                $info         = [];
                $info['pid']  = $pid;
                $info['type'] = $worker->getType();
            }
            if ($info) {
                if ($worker->getTopic()) {
                    $info['topic']      = $worker->getTopic()->getName();
                    $info['queue_size'] = $worker->getTopic()->getQueueSize();
                }

                $str .= ' ' . Utils::formatTablePrint([
                        $info['pid'] ?? '-',
                        $info['topic'] ?? ($info['name'] ?? '-'),
                        $info['type'] ?? '-',
                        $info['queue_size'] ?? '-',
                        $info['status'] ?? '-',
                        $info['duration'] ?? '-',
                        $info['idle_time'] ?? '-',
                        $info['avg_time'] ?? '-',
                        $info['done'] ?? '-',
                        $info['failed'] ?? '-',
                        $info['ack'] ?? '-',
                        $info['reject'] ?? '-',
                        $info['mem_usage'] ?? '-',
                        $info['now'] ?? '-',
                    ]) . PHP_EOL;
            }
        }
        return $str;
    }

    /**
     * 保存主进程的状态信息
     *
     * @return string
     */
    protected function _saveMasterStatus() {
        try {
            $file = $this->__pid_dir . DIRECTORY_SEPARATOR . $this->__pid_status_file;
            $info = $this->showStatus();
            @file_put_contents($file, "\n\n" . $info, FILE_APPEND);
            return $info;
        } catch (\Throwable $ex) {
            Utils::catchError($this->_logger, $ex);
        }
        return '';
    }

    /**
     * 更新子进程信息
     */
    protected function _saveWorkerStatus(array $info, bool $unlink = false, int $pid = -1) {
        if ($pid == -1) $pid = getmypid();
        $file = $this->__worker_info_dir . DIRECTORY_SEPARATOR . $pid . '.info';
        if ($unlink) {
            file_exists($file) && @unlink($file);
        } else {
            @file_put_contents($file, json_encode($info, JSON_PRETTY_PRINT));
        }
    }

    /**
     * 读取子进程信息
     */
    protected function _readWorkerStatus(int $pid) {
        $file = $this->__worker_info_dir . DIRECTORY_SEPARATOR . $pid . '.info';
        if (!file_exists($file)) {
            return false;
        }
        $data = @file_get_contents($file);
        if (!$data) {
            return false;
        }
        return json_decode($data, true);
    }

    // 统计work执行的信息
    public function summaryWorkInfo(Worker $worker) {
        try {
            $info = $this->_readWorkerStatus($worker->getPID());
            if (false !== $info) {
                $topic = $worker->getTopic();
                if ($topic) {
                    $this->__topic_info[$topic->getName()]['done']   = ($this->__topic_info[$topic->getName()]['done'] ?? 0) + intval($info['done']);
                    $this->__topic_info[$topic->getName()]['failed'] = ($this->__topic_info[$topic->getName()]['failed'] ?? 0) + intval($info['failed']);
                    $this->__topic_info[$topic->getName()]['ack']    = ($this->__topic_info[$topic->getName()]['ack'] ?? 0) + intval($info['ack']);
                    $this->__topic_info[$topic->getName()]['reject'] = ($this->__topic_info[$topic->getName()]['reject'] ?? 0) + intval($info['reject']);
                }
            }

        } catch (\Throwable $ex) {
            // 不做处理
        }
    }

    /**
     * 刷新日志
     */
    public function flush() {
        $data          = $this->getMasterInfo();
        $data['flush'] = time();
        $this->__setMasterInfo($data);
    }

}
