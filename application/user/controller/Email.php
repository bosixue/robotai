<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace app\user\controller;
class Email {
public  function test(){
$to="86718966@qq.com";
$title="hello lujian";
$content="my name  is lujian. i very happy";
halt(\phpmailer\Email::send($to,$title,$content));
}
}