<?php

namespace Gino\Jobs\Kit\Producer;

/**
 * RabbitMQ队列消息投递
 * @author GinoHuang <binsuper@126.com>
 */
class RabbitmqProducer {

    protected $_deliverer;
    protected $_payload    = '';
    protected $_exchange = '';

    public function __construct(IDeliverer $deliverer, string $exchange) {
        $this->_deliverer  = $deliverer;
        $this->_exchange = $exchange;
    }

    /**
     * 设置消息体
     * @param string $body
     * @return $this
     */
    public function setBody(string $body) {
        $this->_payload = $body;
        return $this;
    }

    /**
     * 投递信息
     * @return bool
     */
    public function deliver(): bool {
        return $this->_deliverer->send($this->_exchange, $this->_payload) ? true : false;
    }

}
