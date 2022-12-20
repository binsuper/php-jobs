<?php

namespace Gino\Jobs\Core\Connection;

use Gino\Jobs\Core\Config;
use Gino\Jobs\Core\Queue\RabbitmqQueue;
use Gino\Jobs\Core\Queue\RedisQueue;
use Gino\Jobs\Core\IFace\IConnection;

class Factory {

    const DRIVER = [
        'redis'    => RedisConnection::class,
        'rabbitmq' => RabbitmqConnection::class,
    ];

    /** @var IConnection[][] */
    private static $connes = [];

    /**
     * 获取连接
     *
     * @param string $name
     * @return IConnection
     */
    public static function getConnection(string $name = 'default'): IConnection {
        $pid = getmypid();
        if (!isset(static::$connes[$pid])) {
            static::$connes[$pid] = [];
        }

        if (isset(static::$connes[$pid][$name])) {
            return static::$connes[$pid][$name];
        }

        $cfg_key = 'queue.' . $name;

        if (($options = Config::get($cfg_key, false)) === false) {
            throw new \InvalidArgumentException("invalid queue name '{$name}'");
        }

        $class = static::getDriverClass($options['driver'] ?? '', $options['class'] ?? '');

        /** @var IConnection $connection */
        $connection = new $class();
        $connection->setOptions($options);
        static::$connes[$pid][$name] = $connection;

        return $connection;
    }

    /**
     * @param string $driver
     * @param string $queue_class
     * @return string
     */
    protected static function getDriverClass(string $driver, string $queue_class): string {
        if ($driver) {
            if (isset(static::DRIVER[$driver])) {
                return static::DRIVER[$driver];
            }
            if (is_a($driver, IConnection::class, true)) {
                return $driver;
            }

            throw new \InvalidArgumentException("invalid driver '{$driver}'");
        }

        if ($queue_class) {
            if (is_a($queue_class, RedisQueue::class, true)) {
                return static::DRIVER['redis'];
            }

            if (is_a($queue_class, RabbitmqQueue::class, true)) {
                return static::DRIVER['rabbitmq'];
            }
        }
        throw new \InvalidArgumentException("invalid driver '{$queue_class}'");
    }

    /**
     * 管理所有连接
     */
    public static function closeAllConnections() {
        $pid = getmypid();
        if (!isset(static::$connes[$pid])) {
            return;
        }

        foreach (static::$connes[$pid] as $conn) {
            $conn->close();
        }

        static::$connes = [];
    }

}