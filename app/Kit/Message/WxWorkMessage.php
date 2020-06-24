<?php

namespace Gino\Jobs\Kit\Message;

/**
 * 钉钉机器人
 */
class WxWorkMessage extends Notify implements \Gino\Jobs\Core\IFace\INotifier {

    private $__api = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send';
    private $__key = '';
    private $__params;

    public function __construct(array $params) {
        $this->__key = $params['token'] ?? '';
        $this->__api .= '?key=' . $this->__key;
    }

    protected function _send($data) {
        $ret = $this->curlHttp($this->__api, json_encode($data), 'POST', array('Content-Type: application/json;charset=utf-8'));
        if ($ret) {
            $ret = json_decode($ret, true);
        }
        return $ret;
    }

    public function sendText($text) {
        $data = [
            'msgtype'        => 'text',
            'text'           => [
                'content' => $text
            ],
            'mentioned_list' => ['@all']
        ];
        return $this->_send($data);
    }

    public function notify(string $msg) {
        $this->sendText($msg);
    }

}
