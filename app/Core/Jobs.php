<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\IFace\IQueueDriver;
use Gino\Jobs\Core\IFace\IJob;

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

    public function __construct(IQueueDriver $queue, IJob $job) {
        $this->__queue          = $queue;
        $this->__job            = $job;
        $this->__last_busy_time = microtime(true);
    }

    /**
     * 得到任务空闲的时长
     * @return float 空闲时长(毫秒)
     */
    public function idleTime() {
        return microtime(true) - $this->__last_busy_time;
    }

    /**
     * 得到以已处理的任务数
     */
    public function doneCount() {
        return $this->__done_count;
    }

    /**
     * 执行
     */
    public function run() {
        $msg = $this->__queue->pop();
        if (null === $msg) {
            return;
        }
        if ($this->__job->consume($msg)) {
            $this->__done_count++;
            $this->__last_busy_time = microtime(true);
        }
    }

}
