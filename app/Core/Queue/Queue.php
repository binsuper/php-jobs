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
     * @return IQueueDriver|IQueueProducer|IQueueDelay 失败返回false
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
                } catch (Exception $ex) {
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
