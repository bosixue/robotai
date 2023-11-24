<?php
// +----------------------------------------------------------------------
// | RuiKeCMS [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.ruikesoft.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Wayne <wayne@ruikesoft.com> <http://www.ruikesoft.com>
// +----------------------------------------------------------------------

namespace app\user\controller;
use think\Db;
use app\common\controller\User;


//微信控制器 
class Wechat {
	public function index(){
	   halt($this->getNoticerOpenid());
	}
	public  function getUserInfo(){
       //1 获取到code
       
		$appid=config('WeChat.appid');
		$redirect_uri=urlencode("http://ai.sc-yun.com/user/Wechat/getOpenid");
		$url="https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_uri."&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
        header('location:'.$url);
		exit();
	}
	//此为获取openid方法 然后判断此openid 是否存在数据库中 如果存在那么就跳转 预约页面 如果不存在 跳转注册页面
	public function getOpenid(){
		$appid=config('WeChat.appid');
		$appsecret=config('WeChat.appsecret');
		$code=input('param.code');  //上面的 getUserInfo()中 的$url会传递 code参数
		//2 获取网页授权的access_token
		$url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=" .$appid. "&secret=" .$appsecret. "&code=" .$code. "&grant_type=authorization_code";
		$res=$this->http_curl($url,'get');
		$access_token=$res['access_token'];
		$openid=$res['openid'];
		

	}
	
	
	
	/*
	  作用：得到关注者的数据
	  @return array 
	*/
	
	public function getNoticerOpenid(){
		 $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=".$this->getWxAccessToken();
		 $res=$this->http_curl($url,'get','json');
		 return $res;
	}
	
	
	//使用curl 得到access_token码 *session解决方法 存MySQL或memcache中
	public function getWxAccessToken(){
		//将access_token 存在session或者cookie中
		if(session('access_token') && session('expire_time')>time()){
			//如果 access_token 在session中 并且没有过期
			return session('access_token');
		}else{
			//如果access_token不存在 或者已经过期了 重新取access_token 并且保存session中
			$appid=config('WeChat.appid');
			$appsecret=config('WeChat.AppSecret');
			$url="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
			$res=$this->http_curl($url,'get','json');
			$access_token=$res['access_token'];
			//将重新获取到的access_token码放入session中
			session('access_token',$access_token) ;
			session('expire_time',time()+7000);//过期时间
			return $access_token;
		}	
	}
	 //包装curl函数 
	/*
	 * $url  curl要抓取数据地址
	 * $type curl抓取数据的类型 是post类型还是get类型
	 * $res  抓取完成后，返回的数据类型 默认为json
	 * $arr  如果是post类型 需要给post类型传递数据 是json形式的
	*/
	public  function http_curl($url,$type='get',$res='json',$arr=''){
		//初始化curl
		$ch=curl_init();
		//设置curl的参数
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		//判断传递数据是什么类型 get还是post
		if($type=='post'){
			//是否开启post传递
			curl_setopt($ch,CURLOPT_POST,1);
			//传递数据 $arr是在post方式中要传递的数据 是json形式的
			curl_setopt($ch,CURLOPT_POSTFIELDS,$arr);
		}
	      //采集数据 因为get根本不需要上面 那2行代码 所以 type=get的话直接就可以采集了
			$output=curl_exec($ch);
		 
		  //判断返回信息的类型 默认为json
		  if($res=='json'){
			  if( curl_errno($ch) ){
				  //请求失败 返回错误信息
				  return  curl_error($ch);
			  }else{
				  //请求成功
				  return json_decode($output,true);//curl抓取的返回值 然后返回数组形式
			  }
		  }
		   //关闭curl
		  curl_close($ch);
	}
}