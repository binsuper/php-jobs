<?php

namespace Gino\Jobs\Core;

/**
 * 任务管理中心
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Topic {

    private $__workers      = [];   //进程列表, pid => job
    protected $_topic_name;         //主题名称
    protected $_min_workers = 1;    //最少进程数
    protected $_max_workers = 1;    //最大进程数

    public function __construct(array $topic_info) {
        $this->_topic_name  = $topic_info['name'];
        $this->_min_workers = $topic_info['min_workers'] ?? 1;
        $this->_min_workers = $topic_info['max_workers'] ?? 1;
    }

    /**
     * 执行
     * 
     * @param function $callback 执行实体
     */
    public function execute($callback) {
        //创建最小数量的进程
        for ($i = 0; $i < $this->_min_workers; $i++) {
            call_user_func($callback, $job);
        }
    }

}
