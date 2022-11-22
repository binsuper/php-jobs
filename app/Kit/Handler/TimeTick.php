<?php
/**
 * 自动触发job，模拟自动执行脚本
 */

namespace Gino\Jobs\Kit\Handler;

use Gino\Jobs\Core\IFace\IAutomatic;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Queue\Queue;
use Gino\Jobs\Core\Utils;
use Swoole\Timer;

class TimeTick extends DefaultHandler implements IAutomatic {

    protected $timer_id = null;

    public function auto(): void {
        $queue = Queue::getQueue($this->getTopic(), false);
        if(!$queue->clear()){
            Logger::getLogger()->warning(sprintf('clear queue <%s> failed', $queue->getQueueName()));
        }
        $time = $this->getParams()[0] ?? 1000; // 默认1秒
        $msg  = json_encode($this->getParams()[1] ?? []);

        $this->timer_id = Timer::tick($time, function () use ($queue, $msg) {
            try {
                $queue->push($msg, $this->getTopic()->getName());
            } catch (\Throwable $ex) {
                Utils::catchError(Logger::getLogger(), $ex);
            }
        });
    }

    public function finish() {
        try {
            Timer::clear($this->timer_id);
        } catch (\Throwable $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
        }


    }

}