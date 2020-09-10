<?php

namespace Gino\Jobs\Core;

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

    public function __construct(array $topic_info) {
        $this->__config            = $topic_info;
        $this->__min_workers       = $topic_info['min_workers'] ?? 1;
        $this->__max_workers       = $topic_info['max_workers'] ?? 1;
        $this->__topic_name        = $topic_info['name'];
        $this->__alias_name        = $topic_info['alias'];
        $this->__action            = $topic_info['action'];
        $this->__trans_per_operate = $topic_info['tpo'] ?? 1;
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
     * @param function $callback
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
        $job   = new $this->__action();
        return new Jobs($queue, $job, $this->getTPO());
    }

    /**
     * 投递消息
     *
     * @param string $msg
     * @return bool
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
     * 获取健康的队列长度
     *
     * @return int
     */
    public function getHealthSize(): int {
        // 优先去topic的health_size, 如果没有设置，则取process的queue_health_size
        if (isset($this->__config['health_size'])) {
            return $this->__config['health_size'];
        }

        return Config::getConfig('process', 'queue_health_size', 100);

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

        return Config::getConfig('process', 'max_execute_jobs', 100);
    }

}
