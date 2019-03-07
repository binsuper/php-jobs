<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\Queue\Queue;

/**
 * 任务管理中心
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Topic {

    private $__topic_name;          //主题名称
    private $__config      = [];
    private $__min_workers = 1;     //最少进程数
    private $__max_workers = 1;     //最大进程数
    private $__action;              //任务类
    private $__workers     = [];    //子进程数组

    public function __construct(array $topic_info) {
        $this->__config      = $topic_info;
        $this->__min_workers = $topic_info['min_workers'] ?? 1;
        $this->__max_workers = $topic_info['max_workers'] ?? 1;
        $this->__topic_name  = $topic_info['name'];
        $this->__action      = $topic_info['action'];
    }

    /**
     * 获取名称
     * @return string
     */
    public function getName() {
        return $this->__topic_name;
    }

    /**
     * 获取配置信息
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
            $health_size = Config::getConfig('process', 'queue_health_size');
            $queue_size  = $this->getQueueSize();
            if ($health_size == 0 || $health_size > $queue_size) {
                return;
            }
            //创建最小数量的进程
            for ($i = count($this->__workers); $i < $this->__max_workers; $i++) {
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
     * @return int
     */
    public function getQueueSize() {
        $queue = Queue::getQueue($this, false); //非消费者队列
        return $queue->size();
    }

    /**
     * 挂载worker
     * @param \Gino\Jobs\Core\Worker $worker
     */
    public function mountWorker(Worker $worker) {
        $this->__workers[$worker->getPID()] = $worker;
    }

    /**
     * 卸载worker
     * @param \Gino\Jobs\Core\Worker $worker
     */
    public function freeWorker(Worker $worker) {
        unset($this->__workers[$worker->getPID()]);
    }

    /**
     * 生成新任务
     * @return Jobs
     */
    public function newJob() {
        $queue = Queue::getQueue($this);
        $job   = new $this->__action();
        return new Jobs($queue, $job);
    }

}
