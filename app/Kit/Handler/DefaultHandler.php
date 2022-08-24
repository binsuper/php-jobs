<?php

namespace Gino\Jobs\Kit\Handler;

use Gino\Jobs\Core\IFace\IAutomatic;
use Gino\Jobs\Core\IFace\IHandler;
use Gino\Jobs\Core\Topic;

abstract class DefaultHandler implements IHandler {

    /**
     * @var Topic
     */
    protected $_topic;

    /**
     * @var array
     */
    protected $_params;

    public function __construct(Topic $topic, array $params) {
        $this->_topic = $topic;
        $this->_params = $params ?: [];
    }

    /**
     * 执行
     */
    public function run() {
        if ($this instanceof IAutomatic) {
            $this->auto();
        }
    }

    /**
     * 获取 Topic
     *
     * @return Topic
     */
    public function getTopic(): Topic {
        return $this->_topic;
    }

    /**
     * 获取参数
     *
     * @return array
     */
    public function getParams(): array {
        return $this->_params;
    }


}