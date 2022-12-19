<?php

namespace Gino\Jobs\Core\Queue;

use Gino\Jobs\Core\Connection\Factory;
use \Gino\Jobs\Core\IFace\{
    IQueueDriver,
    IQueueProducer,
    IQueueDelay
};
use \Gino\Jobs\Core\Topic;
use \Gino\Jobs\Core\Config;

/**
 * 队列管理
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Queue {

    const DRIVER = [
        'redis'    => RedisQueue::class,
        'rabbitmq' => RabbitmqQueue::class,
    ];

    private static $queue_instances = [];

    /**
     * 获取队列
     *
     * @param string $queue_name
     * @param array $options
     * @return IQueueDriver|IQueueProducer
     */
    public static function getQueue(string $queue_name, array $options = []) {
        /** @var IQueueDriver $class */
        $queue_option_name = $options['queue'] ?? 'default';
        $class             = Config::get("queue.{$queue_option_name}.class", static::DRIVER[Config::get("queue.{$queue_option_name}.driver")] ?? false);

        $unique = getmypid() . '-' . $class . '-' . $queue_option_name;
        if(isset(static::$queue_instances[$unique])){
            return static::$queue_instances[$unique];
        }

        $connection = Factory::getConnection($queue_option_name);
        $connection->connect();
        static::$queue_instances[$unique] = $class::make($queue_name, $connection, $options);

        return static::$queue_instances[$unique];
    }

    /**
     * 获取队列
     *
     * @param Topic $topic
     * @return IQueueDriver|IQueueProducer 失败返回false
     * @throws \Exception
     */
    public static function getQueueByTopic(Topic $topic) {
        $queue_name   = $topic->getName();
        $topic_config = $topic->getConfig();

        return static::getQueue($queue_name, $topic_config);
    }

    /**
     * 获取延时队列
     *
     * @return IQueueDriver|IQueueProducer|IQueueDelay|bool 失败返回false
     * @throws \Exception
     */
    public static function getDelayQueue() {
        $config     = Config::get('queue.__delay__', []);
        $class      = $config['class'];
        $queue_name = $config['delay_queue_name'] ?? false;

        if (!$queue_name) {
            return false;
        }

        if (is_a($class, IQueueDelay::class, true)) {
            return false;
        }

        return static::getQueue($queue_name, ['queue' => '__delay__']);
    }

}
