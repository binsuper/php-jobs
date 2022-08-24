<?php
/**
 * 自动触发job，模拟自动执行脚本
 */

namespace Gino\Jobs\Kit\Handler;

use Gino\Jobs\Core\IFace\IAutomatic;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Queue\Queue;
use Gino\Jobs\Core\Utils;
use Swoole\Event;
use Swoole\Timer;

class TimeTick extends DefaultHandler implements IAutomatic {

    public function auto(): void {
        $time = $this->getParams()[0] ?? 1000; // 默认1秒
        Timer::tick($time, function () {
            try {
                Queue::getQueue($this->getTopic(), false)->push('');
            } catch (\Throwable $ex) {
                Utils::catchError(Logger::getLogger(), $ex);
            }
        });
        Event::wait();
    }

}