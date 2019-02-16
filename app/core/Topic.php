<?php

namespace Gino\Jobs\Core;

/**
 * 任务管理中心
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Topic {

    private $__topic_name;          //主题名称
    private $__min_workers = 1;     //最少进程数
    private $__max_workers = 1;     //最大进程数
    private $__workers     = [];    //子进程数组

    public function __construct(array $topic_info) {
        $this->__topic_name  = $topic_info['name'];
        $this->__min_workers = $topic_info['min_workers'] ?? 1;
        $this->__max_workers = $topic_info['max_workers'] ?? 1;
    }

    /**
     * 执行
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
     * 动态执行
     */
    public function execDynamic($callback) {
        //创建最小数量的进程
        for ($i = $this->__min_workers; $i < $this->__max_workers; $i++) {
            call_user_func($callback);
        }
    }

    /**
     * 挂载worker
     * @param \Gino\Jobs\Core\Worker $worker
     */
    public function mountWorker(Worker $worker) {
        if (!in_array($worker, $this->__workers)) {
            $this->__workers[] = $worker;
        }
    }

    /**
     * 卸载worker
     * @param \Gino\Jobs\Core\Worker $worker
     */
    public function freeWorker(Worker $worker) {
        if (($key = array_search($worker, $this->__workers))) {
            unset($key);
        }
    }

    /**
     * 生成新任务
     */
    public function newJob() {
        
    }

}
