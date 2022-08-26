<?php

return array(
    //日志模块
    'log'      => [
        'log_dir'   => GINO_JOBS_ROOT_PATH . '/var/logs', //日志存储的目录
        'log_file'  => 'application.log', //系统日志文件
        'log_level' => 'debug', //日志级别, [debug, notice, warning, info, error]
    ],
    //进程模块
    'process'  => [
        'user'              => 'www:www', //启动用户
        'data_dir'          => GINO_JOBS_ROOT_PATH . '/var/data', //数据目录
        'process_name'      => ' :php-jobs', //设置进程的名称
        'process_log_file'  => 'process.log', //进程日志文件
        'max_execute_time'  => 1200, //子进程最长执行时间(秒, 0为不限制)，防止内存泄漏
        'max_execute_jobs'  => 1000, //子进程最多执行任务数量(0为不限制)，防止内存泄漏
        'dynamic_idle_time' => 600, //动态子进程闲置的最长时间(0为不限制)
        'queue_health_size' => 100, //健康的队列长度, 超出后将开启动态进程
        'monitor_interval'  => 60000, // worker监控间隔(毫秒)
    ],
    //队列模块
    'queue'    => [
        'default'  => [
            //redis
            'class'            => \Gino\Jobs\Core\Queue\RedisQueue::class,
            'host'             => '10.19.9.114',
            'port'             => 6379,
            'pass'             => '123456',
            'db'               => 0,
            'delay_queue_name' => 'php-jobs-delay', //延迟队列的名称
        ],
        'rabbitmq' => [
            //rabiitmq
            'class'   => \Gino\Jobs\Core\Queue\RabbitmqQueue::class,
            'host'    => '127.0.0.1',
            'port'    => 5672,
            'user'    => 'admin',
            'pass'    => '123456',
            'vhost'   => '/',
            'qos'     => 1,
            'ssl'     => ['verify_peer_name' => false, 'verify_peer' => false], // 使用ssl连接MQ，并无视证书要求
            'options' => ['locale' => 'zh_CN'] // 配置项
        ],
    ],
    //任务模块
    'topics'   => [
        // 任务: 延迟任务分派
        // 要做延迟任务的，需要加上下面的任务，帮助任务分配到指定的延迟队列
        [
            'min_workers' => 1, //最少的进程数
            'max_workers' => 2, //最大的进程数
            'name'        => 'php-jobs-delay',
            'action'      => \Gino\Jobs\Core\Action\RedisDelayDeliver::class,
            'exchange'    => 'phpjob'
        ],
        // 通用
        [
            'min_workers' => 1, //最少的进程数
            'max_workers' => 4, //最大的进程数
            'name'        => 'test',
            'action'      => \Gino\Jobs\Action\Test::class,
//            'action'      => [\Gino\Jobs\Action\Test::class, 1, 2],
            'exchange'    => 'phpjob',
            //'tpo'         => 100, // redis队列支持一次处理多条消息
            'health_size' => 10, //健康的队列长度, 超出后将开启动态进程
            // 'queue' => 'rabbitmq', // 指定使用队列，默认值为default
            // 'max_execute_jobs' => 5, // 子进程最多执行任务数量(0为不限制)，超出后将重启进程，防止内存泄漏
            // 'command'     => 'Me', // 脚本别名
            // 'alias'       => 'test job'
            // 'interval' => 10, // 任务执行间隔，10毫秒
            // 'queue'      => 'default', // 指定作为特定队列的消费者，默认值 default
            // 'handler' => [[\Gino\Jobs\Kit\Handler\TimeTick::class, 2000],], // 配置处理类
        ],
        /* rabbitmq
        [
            'min_workers' => 1, //最少的进程数
            'max_workers' => 2, //最大的进程数
            'name'        => Config::get('daily_queue_key'),
            'action'      => \Jobs\DailyQueue::class,

            'exchange'    => 'dc_data_queue',
            'routing_key' => '', // rabbitmq有效，替代 name 得 routing_key, name 将仅作为队列名称，
            // dead letter exchange
            'dlx'         => 'dlx.dc_data_queue',
            // dead letter routing key
            'dlrk'        => 'dlx.' . Config::get('daily_queue_key'),
        ],
        */
    ],

    // 自定义监控
    'monitor'  => [
        \Gino\Jobs\Kit\Monitor\DefaultMonitor::class
    ],

    //消息通知模块
    'notifier' => [
        /*
        'wxwork' => [ // 企业微信
            'class'  => \Gino\Jobs\Kit\Message\WxWorkMessage::class,
            'params' => ['token' => 'your code']
        ],
        'ding' => [ // 钉钉
            'class'  => \Gino\Jobs\Kit\Message\DingMessage::class,
            'params' => ['token' => 'your code']
        ],
        'smtp' => [ // 邮箱
            'class'  => \Gino\Jobs\Kit\Message\MailMessage::class,
            'params' => [
                'host'     => 'smtp.exmail.qq.com',
                'username' => 'xxxx@xxxx.com',
                'password' => '******',
                'port'     => 465,
                'charset'  => 'utf-8',
                'from'     => 'xxxx@xxxx.com', //发件人
                'to'       => ['xxxx@xxxx.com'], // 收件人
                'subject'  => 'jobs异常', // 邮件标题
            ]
        ],
        */
    ]

);
