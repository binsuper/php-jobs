<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\IFace\IHandler;
use Gino\Jobs\Core\Queue\Queue;

/**
 * 任务管理中心
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Topic {

    private $__topic_name  = '';    //主题名称
    private $__alias_name  = '';    //主题别名
    private $__config      = [];
    private $__min_workers = 1;     //最少进程数
    private $__max_workers = 1;     //最大进程数
    private $__action;              //任务类
    private $__workers     = [];    //子进程数组

    /**
     * 每次处理的消息个数
     *
     * @var int
     */
    private $__trans_per_operate = 1;

    /**
     * 每次处理的时间间隔（毫秒）
     *
     * @var int
     */
    private $__interval = 0;

    /**
     * @var IHandler
     */
    private $__handlers = null;

    public function __construct(array $topic_info) {
        $this->__config            = $topic_info;
        $this->__min_workers       = $topic_info['min_workers'] ?? 1;
        $this->__max_workers       = $topic_info['max_workers'] ?? 1;
        $this->__topic_name        = $topic_info['name'];
        $this->__alias_name        = $topic_info['alias'] ?? '';
        $this->__action            = $topic_info['action'];
        $this->__trans_per_operate = $topic_info['tpo'] ?? 1;
        $this->__interval          = $topic_info['interval'] ?? 0;

        if (!empty($topic_info['handler']) && is_array($topic_info['handler'])) {
            foreach ($topic_info['handler'] as $opt) {
                if (is_array($opt)) {
                    $class  = $opt[0];
                    $params = array_slice($opt, 1);
                }
                $this->__handlers[] = new $class($this, $params);
            }
        }
    }

    /**
     * 获取名称
     *
     * @return string
     */
    public function getName() {
        return $this->__topic_name;
    }

    /**
     * 获取别名
     *
     * @return string
     */
    public function getAlias() {
        return $this->__alias_name;
    }

    /**
     * 获取用于展示的名称
     */
    public function getShowName(): string {
        return $this->getAlias() ?: $this->getName();
    }

    /**
     * 获取配置信息
     *
     * @return array
     */
    public function getConfig() {
        return $this->__config;
    }

    /**
     * 管理静态进程
     *
     * @param callable $callback
     */
    public function execStatic($callback) {
        //创建最小数量的进程
        for ($i = 0; $i < $this->__min_workers; $i++) {
            call_user_func($callback);
        }
    }

    /**
     * 管理动态进程
     *
     * @param callable $callback
     */
    public function execDynamic($callback) {
        try {

            $health_size = $this->getHealthSize();
            $queue_size  = $this->getQueueSize();
            if ($health_size == 0 || $health_size > $queue_size) {
                return;
            }

            // 计算出合理的动态进程数
            $dynamic_count = ceil($queue_size / $health_size);
            if ($dynamic_count > $this->__max_workers) {
                $dynamic_count = $this->__max_workers;
            }

            //创建最大数量的进程
            for ($i = count($this->__workers); $i < $dynamic_count; $i++) {
                call_user_func($callback);
            }
        } catch (\Exception $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
        } catch (\Throwable $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
        }
    }

    /**
     * 获取队里中的消息数量
     *
     * @return int
     */
    public function getQueueSize() {
        $queue = Queue::getQueue($this, false); //非消费者队列
        return $queue->getQueueSize($this->__topic_name);
    }

    /**
     * 挂载worker
     *
     * @param \Gino\Jobs\Core\Worker $worker
     */
    public function mountWorker(Worker $worker) {
        $this->__workers[$worker->getPID()] = $worker;
    }

    /**
     * 卸载worker
     *
     * @param \Gino\Jobs\Core\Worker $worker
     */
    public function freeWorker(Worker $worker) {
        unset($this->__workers[$worker->getPID()]);
        $this->__workers = array_slice($this->__workers, 0, null, true);
    }

    /**
     * 生成新任务
     *
     * @return Jobs
     */
    public function newJob() {
        $queue = Queue::getQueue($this);
        if (is_array($this->__action)) {
            $class  = $this->__action[0];
            $params = array_slice($this->__action, 1);
            $job    = new $class(...$params);
        } else {
            $job = new $this->__action();
        }
        $obj = new Jobs($queue, $job, $this->getTPO());
        return $obj;
    }

    /**
     * 投递消息
     *
     * @param string $msg
     *
     * @return bool
     * @throws \Exception
     */
    public function pushMsg(string $msg): bool {
        $queue = Queue::getQueue($this, false); //非消费者队列
        return $queue->push($msg) ? true : false;
    }

    /**
     * 每次消费的消息数量
     *
     * @return int
     */
    public function getTPO(): int {
        return $this->__trans_per_operate;
    }

    /**
     * 每次处理的时间间隔（毫秒）
     *
     * @return int
     */
    public function getInterval(): int {
        return $this->__interval;
    }


    /**
     * 获取健康的队列长度
     *
     * @return int
     */
    public function getHealthSize(): int {
        // 优先去topic的health_size, 如果没有设置，则取process的queue_health_size
        if (isset($this->__config['health_size'])) {
            return $this->__config['health_size'];
        }

        return Config::get('process.queue_health_size', 100);

    }

    /**
     * 获取子进程最大执行的任务数
     *
     * @return int
     */
    public function getMaxExecuteJobs(): int {
        // 优先去topic的health_size, 如果没有设置，则取process的queue_health_size
        if (isset($this->__config['max_execute_jobs'])) {
            return $this->__config['max_execute_jobs'];
        }

        return Config::get('process.max_execute_jobs', 100);
    }

    /**
     * 获取handler
     *
     * @return IHandler[]
     */
    public function getHandlers() {
        return $this->__handlers;
    }

    /**
     * worker数量
     *
     * @return int
     */
    public function workerNum(): int {
        return count($this->__workers);
    }

    /**
     * 最大worker数量
     *
     * @return int
     */
    public function maxWorkerNum(): int {
        return $this->__max_workers;
    }

}
