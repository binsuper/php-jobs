<?php

return array(
    //日志模块
    'log'     => [
        'log_dir'  => GINO_JOBS_ROOT_PATH . '/var/log', //日志存储的目录
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
        'queue_health_size' => 10, //健康的队列长度, 超出后将启动动态进程
    ],
    //队列模块
    'queue'   => [
        'host'     => '192.168.1.254',
        'port'     => 6379,
        'database' => 0,
        'password' => '',
        'class'    => '\\Gino\\Jobs\\Core\\Queue\\RedisQueue'
    ],
    //任务模块
    'topics'  => [
        [
            'min_workers' => 3, //最少的进程数
            'max_workers' => 5, //最大的进程数
            'name'        => 'test',
            'action'      => '\\Gino\\Jobs\\Jobs\\Test',
        ]
    ]
);
