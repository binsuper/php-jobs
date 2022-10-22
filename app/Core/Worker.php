<?php

namespace Gino\Jobs\Core;

/**
 * 子进程管理
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Worker {

    const TYPE_STATIC  = 'static';  //静态
    const TYPE_DYNAMIC = 'dynamic'; //动态

    /**
     * 子进程对象
     *
     * @var \Swoole\Process
     */

    private $__process = null;

    /**
     * 子进程ID
     *
     * @var int
     */
    private $__pid = -1;

    /**
     * @var Topic
     */
    private $__topic;

    /**
     * 开始运行的时间戳，毫秒
     *
     * @var float
     */
    private $__begin_time = 0;

    /**
     * 子进程类型
     *
     * @var string
     */
    private $__child_type;

    public function __construct(string $child_type) {
        $this->__child_type = $child_type;
    }

    public function __destruct() {
        $this->__process = null;
    }

    /**
     * 设置进程启动函数
     *
     * @param callable $action 进程启动后执行的函数
     */
    public function action($action) {
        $this->__process = new \Swoole\Process($action);
    }

    /**
     * 启动进程
     *
     * @return int PID
     */
    public function start() {
        $this->__begin_time = microtime(true);
        $this->__pid        = $this->__process->start();
        if ($this->__topic) { //挂载
            $this->__topic->mountWorker($this);
        }
        return $this->__pid;
    }

    /**
     * 退出进程
     */
    public function exitWorker() {
        $this->free();
        try {
            @$this->__process->exit();
        } catch (\Throwable $ex) {
            // nothing
        }
        $this->__process = null;
    }

    /**
     * 释放资源
     */
    public function free() {
        if ($this->__topic) { //卸载
            $this->__topic->freeWorker($this);
            $this->__topic = null;
        }
    }

    /**
     * 获取PID
     *
     * @return int
     */
    public function getPID() {
        return $this->__pid;
    }

    /**
     * @return Topic
     */
    function getTopic() {
        return $this->__topic;
    }

    /**
     * @param Topic $topic
     */
    function setTopic(Topic $topic) {
        $this->__topic = $topic;
    }

    /**
     * 获取子进程开始运行的时间
     *
     * @return float 毫秒
     */
    public function getBeginTime() {
        return $this->__begin_time;
    }

    /**
     * 获取子进程运行的时长
     *
     * @return float 毫秒
     */
    public function getDuration() {
        if ($this->__begin_time == 0) {
            return 0;
        }
        return microtime(true) - $this->__begin_time;
    }

    /**
     * 获取进程类型
     *
     * @return string
     */
    public function getType() {
        return $this->__child_type;
    }

}
