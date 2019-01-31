<?php

return array(
    //日志模块
    'log'     => [
        'log_dir'  => GINO_JOBS_ROOT_PATH . '/var/log', //日志存储的目录
        'log_file' => 'application.log', //日志存储的文件名
    ],
    //进程模块
    'process' => [
        'data_dir' => GINO_JOBS_ROOT_PATH . '/var/data', //数据目录
    ]
);
