<?php

namespace Gino\Jobs\Core\Queue\Delay;

/**
 * 延迟队列的消息数据
 * @author GinoHuang <binsuper@126.com>
 */
class Message {

    /**
     * 投递目标的任务名称
     * @var string
     */
    private $__target_topic_name;

    /**
     * 剩余的圈数
     * @var int 
     */
    private $__roll_times;

    /**
     * 投递的消息
     * @var string 
     */
    private $__payload;

    public function __construct(string $body) {
        $info = json_decode($body, true);
        if (!$info) {
            throw new \Exception(json_last_error_msg());
        }
        $this->__target_topic_name = $info['target'] ?? '';
        $this->__roll_times        = $info['times'] ?? 0;
        $this->__payload           = $info['payload'] ?? '';
    }

    public function __toString() {
        return json_encode([
            'target'  => $this->__target_topic_name,
            'times'   => $this->__roll_times,
            'payload' => $this->__payload,
                ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 目标的任务名称
     * @return string
     */
    public function getTargetName(): string {
        return $this->__target_topic_name;
    }

    /**
     * 到点执行
     * @return bool
     */
    public function onTime(): bool {
        return $this->__roll_times == 0;
    }

    /**
     * 获取消息体
     * @return string
     */
    public function getPayload(): string {
        return $this->__payload;
    }

    /**
     * 滚动
     * @return int
     */
    public function roll() {
        $this->__roll_times = $this->__roll_times <= 0 ? 0 : $this->__roll_times - 1;
        return $this->__roll_times;
    }

}
