<?php

namespace Gino\Jobs\Kit\Producer;

/**
 * 往队列投递消息
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Producer {

    protected $_deliverer;

    /**
     * 设置投递对象
     *
     * @param IDeliverer $deliverer
     * @return $this
     */
    public function setDeliverer(IDeliverer $deliverer) {
        $this->_deliverer = $deliverer;
        return $this;
    }

    /**
     * 获取投递对象
     *
     * @return IDeliverer
     */
    public function getDeliverer(): IDeliverer {
        return $this->_deliverer;
    }

}
