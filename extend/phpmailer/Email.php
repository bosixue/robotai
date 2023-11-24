<?php
/*
   发送邮件类库
*/
namespace phpmailer;
use think\Exception;
class Email{
	/*
	   @param $to
	   @param $title
	   @param $content
	   @param bool
	*/
	public static function send($to,$title,$content){
		 date_default_timezone_set('PRC');
		 if(empty($to)){
			 return false;
		 }
		 try{
			 $mail = new Phpmailer; 
			 $mail->isSMTP();
			 $mail->Debugoutput='html';
			 $mail->Host = config('email.host');	
			 $mail->Port = config('email.port');
			 $mail->SMTPAuth=true;
			 $mail->Username=config('email.username');
			 $mail->Password=config('email.password');//这个不是邮箱密码 是SMPT 服务器端口号密码
			 $mail->setFrom(config('email.username'),'lujian');
			 $mail->addAddress($to);//受邮件地址
			 $mail->Subject=$title;//标题
			 $mail->msgHTML($content);//内容
			 if(!$mail->send()){
				 return false;
				//echo "发送失败了".$mail->ErrorInfo;
			}else{
				return true;
			}
		}catch(phpmailerException $e){
			return false;
		}

		
	}
}

