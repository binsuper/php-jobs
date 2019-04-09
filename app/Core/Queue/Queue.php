<?php

namespace Gino\Jobs\Core\Queue;

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

    private static $__instance = [];

    /**
     * 获取队列
     * @param Topic $topic
     * @param bool $is_consume 设置是否消费队列, 默认为true
     * @return IQueueDriver|IQueueProducer 失败返回false
     */
    public static function getQueue(Topic $topic, bool $is_consume = true) {
        $config                = Config::getConfig('queue');
        $config['is_consumer'] = $is_consume;
        $topic_name            = $topic->getName();
        $topic_config          = $topic->getConfig();
        $class                 = $config['class'];
        $pid                   = getmypid();

        $key = md5($pid . $class . serialize($config));
        if (!isset(static::$__instance[$key]) || !is_object(static::$__instance[$key])) {
            $last_ex = null;
            for ($i = 0; $i != 3; $i++) {
                try {
                    static::$__instance[$key] = $class::getConnection($config, $topic_name, $topic_config);
                    return static::$__instance[$key];
                } catch (\Exception $ex) {
                    $last_ex = $ex;
                }
            }
            if (!$last_ex) {
                throw $last_ex;
            }
        }
        return static::$__instance[$key];
    }

    /**
     * 获取延时队列
     * @return IQueueDriver|IQueueProducer|IQueueDelay 失败返回false
     * @throws \Exception
     */
    public static function getDelayQueue() {
        $delay_queue_name      = Config::getConfig('queue', 'delay_queue_name');
        $config                = Config::getConfig('queue');
        $config['is_consumer'] = false;
        $class                 = $config['class'];
        $pid                   = getmypid();
        if (empty($delay_queue_name)) {
            return false;
        }
        $key = md5($pid . $class . serialize($config) . 'delay');
        if (!isset(static::$__instance[$key]) || !is_object(static::$__instance[$key])) {
            //判断是否实现了延时任务接口
            $refection = new \ReflectionClass($class);
            if (!$refection->implementsInterface(IQueueDelay::class)) {
                return false;
            }
            unset($refection);
            //生成实例
            $last_ex = null;
            for ($i = 0; $i != 3; $i++) {
                try {
                    static::$__instance[$key] = $class::getConnection($config, $delay_queue_name);
                    return static::$__instance[$key];
                } catch (\Exception $ex) {
                    $last_ex = $ex;
                }
            }
            if (!$last_ex) {
                throw $last_ex;
            }
        }
        return static::$__instance[$key];
    }
    
}
