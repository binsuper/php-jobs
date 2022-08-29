<?php

namespace Gino\Jobs\Kit\Producer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * 队列消息投递
 *
 * @author GinoHuang <binsuper@126.com>
 */
abstract class Deliverer implements IDeliverer {

    protected $_session = [];
    protected $_channel = null;

    /**
     * 清空信息
     *
     * @param string $name
     * @return $this
     */
    public function clean(string $name) {
        unset($this->_session[$name]);
        return $this;
    }

    /**
     * 消息通道
     *
     * @param string|null $channel
     * @return $this
     */
    public function channel(string $channel = '') {
        $channel = empty($channel) ? '__DEFAULT__' : $channel;
        $this->clean($channel);
        $this->_session[$channel] = ['channel' => $channel];
        $this->_channel           = $channel;
        return $this;
    }

    /**
     * 消息队列
     *
     * @param string $queue
     * @return $this
     */
    public function queue(string $queue) {
        if (is_null($this->_channel)) {
            $this->channel();
        }
        $this->_session[$this->_channel]['queue'] = $queue;
        return $this;
    }

    /**
     * 延迟消息
     *
     * @param int $second 延迟时间，单位-秒
     * @param string $delay_queue 延迟队列名称
     * @return $this
     */
    public function delay(int $second, string $delay_queue = '') {
        if (is_null($this->_channel)) {
            $this->channel();
        }
        $this->_session[$this->_channel]['delay']       = $second;
        $this->_session[$this->_channel]['delay_queue'] = $delay_queue;
        return $this;
    }

    /**
     * 设置消息体
     *
     * @param string $msg
     * @return $this
     */
    public function message(string $msg) {
        if (is_null($this->_channel)) {
            $this->channel();
        }
        $this->_session[$this->_channel]['message'] = $msg;
        return $this;
    }


    /**
     * 获取数据
     *
     * @return mixed
     */
    public function data($name, $default = '') {
        return empty($this->_session[$this->_channel]) ? '' : ($this->_session[$this->_channel][$name] ?? $default);
    }

}

