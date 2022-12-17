<?php

namespace Gino\Jobs\Core\Queue;

use Gino\Jobs\Core\IFace\IQueueDriver;
use Gino\Jobs\Core\Queue\RabbitmqQueue;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use Gino\Jobs\Core\Exception\ExitException;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Utils;

/**
 * 队列消息
 *
 * @author GinoHuang <binsuper@126.com>
 */
class RabbitmqMessage extends BaseQueueMessage {

    /**
     * 消息体
     *
     * @var AMQPMessage
     */
    protected $_body;

    /**
     * 信道
     *
     * @var AMQPChannel
     */
    protected $_channel;

    /**
     * @var bool
     */
    private $__is_operation = false;

    public function __construct(RabbitmqQueue $driver, $msg) {
        parent::__construct($driver, $msg);
        $this->_body    = $this->_msg->body;
        $this->_channel = $this->_msg->delivery_info['channel'];
    }

    public function __toString() {
        return $this->_body;
    }

    /**
     * 消息实体
     */
    public function getBody() {
        return $this->_body;
    }

    /**
     * 正确应答
     *
     * @return bool
     * @throws ExitException
     */
    protected function _ack(): bool {
        //尝试3次
        $try_times = 3;
        do {
            try {
                $this->_channel->basic_ack($this->_msg->delivery_info['delivery_tag']);
                return true;
            } catch (\Exception $ex) {
                Utils::catchError($ex);
            } catch (\Throwable $ex) {
                Utils::catchError($ex);
            }
        } while (--$try_times > 0);
        throw new ExitException();
    }

    /**
     * 拒绝消息
     *
     * @param bool $requeue true表示将消息重新入队列，false则丢弃该消息
     * @return bool
     * @throws ExitException
     */
    protected function _reject(bool $requeue): bool {
        //尝试3次
        $try_times = 3;
        do {
            try {
                $this->_channel->basic_reject($this->_msg->delivery_info['delivery_tag'], $requeue);
                return true;
            } catch (\Exception $ex) {
                Utils::catchError($ex);
            } catch (\Throwable $ex) {
                Utils::catchError($ex);
            }
        } while (--$try_times > 0);
        throw new ExitException();
    }

    /**
     * @inheritDoc
     */
    public function getQueueDriver(): IQueueDriver {
        return $this->_driver;
    }

}
