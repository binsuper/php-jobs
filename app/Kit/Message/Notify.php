<?php


namespace Gino\Jobs\Kit\Message;


use Gino\Jobs\Core\Config;
use Gino\Jobs\Core\IFace\INotifier;
use Gino\Jobs\Core\Logger;

class Notify {

    private static $__notifier = null;

    /**
     * 初始化
     */
    final private static function init() {
        if (static::$__notifier == NULL) {
            static::$__notifier = [];
            //消息模块
            $notifier = Config::getConfig('notifier', '', []);
            foreach ($notifier as $name => $node) {
                $class  = $node['class'] ?? false;
                $params = $node['params'] ?? [];
                if (!$class) {
                    continue;
                }
                static::$__notifier[$name] = new $class($params);
            }
        }
    }

    /**
     * 发送给所有消息通道
     *
     * @param $msg
     */
    public static function all(string $msg) {
        static::init();
        foreach (static::$__notifier as $notify) {
            /**
             * @var INotifier $notify
             */
            $notify->notify($msg);

        }
    }

    /**
     * 发送给特定的消息通道
     *
     * @param array|string $to
     * @param string $msg
     */
    public static function to($to, string $msg) {
        if (!is_array($to)) {
            $to = [$to];
        }

        $to = array_intersect($to, array_keys(static::$__notifier));

        foreach ($to as $name) {
            static::$__notifier[$name]->notify($msg);
        }
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

}