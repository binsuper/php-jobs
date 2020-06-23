<?php

namespace Gino\Jobs\Kit\Message;

/**
 * 钉钉机器人
 */
class DingMessage extends Notify implements \Gino\Jobs\Core\IFace\INotifier {

    private $_api          = 'https://oapi.dingtalk.com/robot/send';
    private $_access_token = '';
    private $__params;

    public function __construct(array $params) {
        $this->_access_token = $params['token'] ?? '';
        $this->_api          .= '?access_token=' . $this->_access_token;
    }

    protected function _send($data) {
        $ret = $this->curlHttp($this->_api, json_encode($data), 'POST', array('Content-Type: application/json;charset=utf-8'));
        if ($ret) {
            $ret = json_decode($ret, true);
        }
        return $ret;
    }

    public function sendText($text, $atAll = false) {
        $data = [
            'msgtype' => 'text',
            'text'    => [
                'content' => $text
            ],
            'at'      => [
                'isAtAll' => $atAll
            ]
        ];
        return $this->_send($data);
    }

    public function notify(string $msg) {
        $this->sendText($msg);
    }

}
