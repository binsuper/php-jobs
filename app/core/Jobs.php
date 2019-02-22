<?php

namespace Gino\Jobs\Core;

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
     * @var IFace\IEvent
     */
    private $__event;

    public function __construct(string $name) {
        $this->__name           = $name;
        $this->__last_busy_time = microtime(true);
        $this->__event          = new \Gino\Jobs\Jobs\Test();
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
        if ($this->__event->exec()) {
            $this->__done_count++;
            $this->__last_busy_time = microtime(true);
        }
    }

}
