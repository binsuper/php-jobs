<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\IFace\IQueueDriver;
use Gino\Jobs\Core\IFace\IConsumer;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Jobs {

    private $__name;

    /**
     * 最后一次执行的时间
     * @var float 
     */
    private $__last_busy_time = 0;

    /**
     * 已完成的任务数
     * @var int 
     */
    private $__done_count = 0;

    /**
     *
     * @var IJob
     */
    private $__job;

    /**
     * 
     * @var IQueueDriver 
     */
    private $__queue;

    /**
     * 任务总的执行时长
     * @var float
     */
    private $__cost_time;

    public function __construct(IQueueDriver $queue, IConsumer $job) {
        $this->__queue          = $queue;
        $this->__job            = $job;
        $this->__last_busy_time = microtime(true);
    }

    /**
     * 得到任务空闲的时长
     * @return float 空闲时长(整数为秒)
     */
    public function idleTime() {
        return bcsub(microtime(true), $this->__last_busy_time, 10);
    }

    /**
     * 得到以已处理的任务数
     */
    public function doneCount() {
        return $this->__done_count;
    }

    /**
     * 任务的平均用时
     * @return string
     */
    public function avgTime() {
        if ($this->__done_count == 0 || $this->__cost_time == 0) {
            return 0 . 'ms';
        }
        $avg = bcdiv($this->__cost_time, $this->__done_count, 6);
        if (bccomp($avg, 10) > 0) {
            return round($avg, 2) . 's';
        }
        return round(($avg * 1000), 1) . 'ms';
    }

    /**
     * 执行
     */
    public function run() {
        $msg = $this->__queue->pop();
        if (null === $msg) {
            return;
        }
        try {
            $before_time = microtime(true);
            //消费消息
            if ($this->__job->consume($msg)) {
                $this->__done_count++;
                $this->__last_busy_time = microtime(true);
                $this->__cost_time      = bcadd($this->__cost_time, bcsub(microtime(true), $before_time, 10), 10);
            }
        } catch (\Throwable $ex) {
            //消费时发生错误
            Utils::catchError(Logger::getLogger(), $ex);
        }
    }

}
