<?php

namespace Gino\Jobs\Kit\Message;

use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Utils;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * 钉钉机器人
 */
class MailMessage implements \Gino\Jobs\Core\IFace\INotifier {


    protected $_host;
    protected $_port;
    protected $_username;
    protected $_password;
    protected $_charset;
    protected $_from;
    protected $_to;
    protected $_subject;

    public function __construct(array $params) {
        $this->_host     = $params['host'] ?? '';
        $this->_port     = $params['port'] ?? '';
        $this->_username = $params['username'] ?? '';
        $this->_password = $params['password'] ?? '';
        $this->_charset  = $params['charset'] ?? '';
        $this->_from     = $params['from'] ?? '';
        $this->_to       = $params['to'] ?? [];
        $this->_subject  = $params['subject'] ?? '';
    }

    public function notify(string $msg) {
        $mail = new PHPMailer(true);
        try {
            //服务器配置
            $mail->isSMTP(); // 使用SMTP
            $mail->CharSet    = $this->_charset;    //设定邮件编码
            $mail->SMTPDebug  = 0;                  // 调试模式输出
            $mail->Host       = $this->_host;       // SMTP服务器
            $mail->SMTPAuth   = true;               // 允许 SMTP 认证
            $mail->Username   = $this->_username;   // SMTP 用户名  即邮箱的用户名
            $mail->Password   = $this->_password;   // SMTP 密码  部分邮箱是授权码(例如163邮箱)
            $mail->SMTPSecure = 'ssl';              // 允许 TLS 或者ssl协议
            $mail->Port       = $this->_port;       // 服务器端口 25 或者465 具体要看邮箱服务器支持

            $mail->setFrom($this->_from);           //发件人
            foreach ($this->_to as $to) {
                $mail->addAddress($to);             // 收件人
            }
            $mail->addReplyTo($this->_from);

            $mail->isHTML(true);            // 是否以HTML文档格式发送  发送后客户端可直接显示对应HTML内容
            $mail->Subject = $this->_subject;
            $mail->Body    = strtr($msg, ["\n" => '<br/>', "\t" => '&nbsp;&nbsp;&nbsp;&nbsp;']);

            $mail->send();

            unset($mail);
        } catch (\Exception $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
        }

    }

}
