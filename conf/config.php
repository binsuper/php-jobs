<?php

return array(
    //日志模块
    'log'     => [
        'log_dir'  => GINO_JOBS_ROOT_PATH . '/var/log', //日志存储的目录
        'log_file' => 'application.log', //系统日志文件
    ],
    //进程模块
    'process' => [
        'data_dir'           => GINO_JOBS_ROOT_PATH . '/var/data', //数据目录
        'process_name'       => ' :php-jobs', //设置进程的名称
        'process_log_file'   => 'process.log', //进程日志文件
        'child_execute_time' => 1200, //子进程最长执行时间(秒, 0为不限制)，防止内存泄漏
        'child_execute_jobs' => 1000, //子进程最多执行任务数量(0为不限制)，防止内存泄漏
    ],
    //任务模块
    'topics'  => require(__DIR__ . '/jobs.php')
);
