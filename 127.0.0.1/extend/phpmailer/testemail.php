<?php
header("content-type:text/html;charset=utf-8");
require 'class.phpmailer.php';
require 'class.smtp.php';
date_default_timezone_set('PRC')

$mail=new PHPMailer;
$mail->isSMTP();
//$mail->SMTPDebug=2;  // 启用SMTP调试功能 // 1 = errors and messages  // 2 = messages only 
$mail->Debugoutput='html';
$mail->Host="smtp.163.com";
$mail->Port=25;
$mail->SMPTAuth=true;
$mail->Username="lujianwanmei2009@163.com"
$mail->Password="lujian123456";
$mail->setFrom('lujianwanmei2009@163.com','鲁健的tp5系统');//发件人的地址 和发件人的名称
$mail->addAddress('86718966@qq.com','测试发送邮件');//受邮件地址 收件人的名称
$mail->Subject="鲁健的tp5发送邮件的测试信息";//标题
$mail->msgHTML('鲁健tp5测试邮件系统，这是内容啊');//内容

if(!$mail->send()){
	echo "发送失败了".$mail->ErrorInfo;
}else{
	echo "发送成功";
}














