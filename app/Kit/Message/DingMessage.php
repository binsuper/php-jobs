<?php

namespace Gino\Jobs\Kit\Message;

/**
 * 钉钉机器人
 */
class DingMessage implements \Gino\Jobs\Core\IFace\INotifier {

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

    protected function curlHttp($url, $data = '', $method = 'GET', $headers = null) {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
            if ($data != '') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
            }
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        if ($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $tmpInfo = curl_exec($curl); // 执行操作
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据
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
