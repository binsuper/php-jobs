<?php

namespace Gino\Jobs\Kit\Producer;

/**
 * Redis队列消息投递
 * @author GinoHuang <binsuper@126.com>
 */
class RedisProducer {

    protected $_deliverer;
    protected $_payload     = '';
    protected $_target_job  = '';
    protected $_delay_queue = '';
    protected $_delay_time  = 0;

    public function __construct(IDeliverer $deliverer, string $job_name) {
        $this->_deliverer  = $deliverer;
        $this->_target_job = $job_name;
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
     * 延迟执行
     * @param int $second 时间，单位秒
     * @param string $delay_queue 延时队列名称
     * @return $this
     */
    public function delay(int $second, string $delay_queue) {
        $this->_delay_time = $second ?: 1;
        $this->_delay_queue = $delay_queue;
        return $this;
    }

    /**
     * 获取投递的键名
     * @return string
     */
    protected function _getResultKey(): string {
        if ($this->_delay_time == 0) {
            return $this->_target_job;
        } else {
            return $this->_delay_queue;
        }
    }

    /**
     * 获取投递的消息体
     * @return string
     */
    protected function _getResultBody(): string {
        if ($this->_delay_time == 0) {
            return $this->_payload;
        } else {
            $data = [
                'payload' => $this->_payload,
                'target'  => $this->_target_job,
                'delay'   => $this->_delay_time
            ];
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 投递信息
     * @return bool
     */
    public function deliver(): bool {
        $ret = $this->_deliverer->send($this->_getResultKey(), $this->_getResultBody());
        return $ret ? true : false;
    }

}
