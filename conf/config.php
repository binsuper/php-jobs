<?php

return array(
    //日志模块
    'log'     => [
        'log_dir'  => GINO_JOBS_ROOT_PATH . '/var/logs', //日志存储的目录
        'log_file' => 'application.log', //系统日志文件
    ],
    //进程模块
    'process' => [
        'data_dir'          => GINO_JOBS_ROOT_PATH . '/var/data', //数据目录
        'process_name'      => ' :php-jobs', //设置进程的名称
        'process_log_file'  => 'process.log', //进程日志文件
        'max_execute_time'  => 1200, //子进程最长执行时间(秒, 0为不限制)，防止内存泄漏
        'max_execute_jobs'  => 1000, //子进程最多执行任务数量(0为不限制)，防止内存泄漏
        'dynamic_idle_time' => 600, //动态子进程闲置的最长时间(0为不限制)
        'queue_health_size' => 10, //健康的队列长度, 超出后将开启动态进程
    ],
    //队列模块
    'queue'   => [
        //redis
        'class'            => '\Gino\Jobs\Core\Queue\RedisQueue',
        'host'             => '192.168.1.254',
        'port'             => 6379,
        'pass'             => '',
        'db'               => 0,
        'delay_queue_name' => 'php-jobs-delay', //延迟队列的名称
    /*
      //rabiitmq
      'class' => '\Gino\Jobs\Core\Queue\RabbitmqQueue',
      'host'  => '127.0.0.1',
      'port'  => 5672,
      'user'  => 'admin',
      'pass'  => '123456',
      'vhost' => '/',
      'qos'   => 1
     */
    ],
    //任务模块
    'topics'  => [
        [
            'min_workers' => 2, //最少的进程数
            'max_workers' => 4, //最大的进程数
            'name'        => 'test',
            'action'      => '\Gino\Jobs\Action\Test',
            'exchange'    => 'phpjob'
        ]
    ]
);
