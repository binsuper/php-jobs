<?php


namespace Gino\Jobs\Kit\Monitor;


use Gino\Jobs\Core\Config;
use Gino\Jobs\Core\IFace\IMonitor;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Topic;
use Gino\Jobs\Kit\Message\Notify;

class DefaultMonitor implements IMonitor {

    public $health_failed = 1;
    public $health_reject = 1;

    protected $except_msg = [];

    protected $_cache     = [];
    protected $_cache_hot = [];

    /**
     * @inheritDoc
     */
    public function start() {
        $this->except_msg = [];
        $this->_cache_hot = [];
    }

    public function processing(int $pid, Topic $topic, array $info) {

        $topic_name = $topic->getName();

        // 队列健康度
        if (!isset($this->except_msg[$topic_name])) {
            $queue_size = $topic->getQueueSize() ?: 0;
            if ($queue_size >= $topic->getHealthSize()) { //不健康了哦
                $this->except_msg[$topic_name]['queue_size'] = $queue_size;
                $this->except_msg[$topic_name]['avg_time']   = $info['avg_time'];
            }
        }

        $status_failed = ($info['failed'] - ($this->_cache[$pid]['failed'] ?? 0)) >= $this->health_failed;
        $status_reject = ($info['reject'] - ($this->_cache[$pid]['reject'] ?? 0)) >= $this->health_reject;

        if ($status_failed || $status_reject) {
            $this->except_msg[$topic_name]['failed'] = ($this->except_msg[$topic_name]['failed'] ?? 0) + $info['failed'];
            $this->except_msg[$topic_name]['reject'] = ($this->except_msg[$topic_name]['reject'] ?? 0) + $info['reject'];
        }

        // 缓存
        if ($status_failed) {
            $this->_cache_hot[$pid]['failed'] = $info['failed'];
        } else {
            $this->_cache_hot[$pid]['failed'] = $this->_cache[$pid]['failed'] ?? 0;
        }
        if ($status_reject) {
            $this->_cache_hot[$pid]['reject'] = $info['reject'];
        } else {
            $this->_cache_hot[$pid]['reject'] = $this->_cache[$pid]['reject'] ?? 0;
        }

        // 记录名称
        if (isset($this->except_msg[$topic_name])) {
            $this->except_msg[$topic_name]['topic'] = $topic->getAlias() ?: $topic_name;
        }


    }

    /**
     * @inheritDoc
     */
    public function finish() {
        unset($this->_cache);
        $this->_cache = $this->_cache_hot;

        if (!empty($this->except_msg)) {
            foreach ($this->except_msg as $node) {
                $msg = $this->buildReportMsg($node);

                \Swoole\Coroutine::create(function () use ($msg) {
                    Notify::all($msg);
                });
            }
        }
    }

    /**
     * @param array $node
     *
     * @return string
     */
    protected function buildReportMsg(array $node): string {
        $topic_name = $node['topic'];
        $queue_size = $node['queue_size'] ?? false;
        $avg_time   = $node['avg_time'] ?? 0;
        $failed     = $node['failed'] ?? 0;
        $reject     = $node['reject'] ?? 0;

        $msg = '应用：' . Config::get('app.name', 'unkown') . PHP_EOL;
        $msg .= '时间：' . date('Y-m-d H:i:s') . PHP_EOL;
        $msg .= "主题：{$topic_name}" . PHP_EOL;
        $msg .= '异常：' . PHP_EOL;
        if ($queue_size) {
            $msg .= "\t• 消息积压太多了（数量：{$queue_size}), 平均处理时长：{$avg_time}" . PHP_EOL;
        }
        if ($failed > 0 || $reject > 0) {
            $msg .= "\t• 消息处理异常，失败(Failed)的数量：{$failed}，拒绝(Reject)的数量：{$reject}" . PHP_EOL;
        }

        return $msg;
    }

}