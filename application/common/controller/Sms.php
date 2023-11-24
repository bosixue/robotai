<?php
namespace app\common\controller;
use app\common\model\AuthRule;
use app\common\model\AuthGroup;

use think\Db;

// 表名 rk_sms_channel
// 列	类型	注释
// id	int(11) 自动增量	
// owner	int(11) NULL [0]	所属用户  默认0 为0时所有用户都可用
// name	varchar(255) NULL	接口入商名称
// url	varchar(255) NULL	接口地址
// user_id	varchar(255) NULL	可以是接口用户名，或app_key
// access_secret	varchar(255) NULL	
// count	int(11) NULL [0]	可用数量
// password	varchar(30)	账号密码
// price	varchar(600) NULL [0.00]	单价
// is_default	tinyint(1) NULL [0]	是否是默认通道，0 不是  1是
// status	tinyint(1) NULL [0]	0 审核中  1通过
// create_time	int(11) NULL	
// update_time	int(11) NULL	
// remarks	varchar(600)	备注


// 表名 rk_sms_sign 短信签名
// 列	类型	注释
// id	int(11) 自动增量	
// owner	int(11) NULL	
// name	varchar(255) NULL	签名
// status	tinyint(1) NULL [0]	0 审核中  1通过
// create_time	int(11) NULL	
// update_time	int(11) NULL	


class Sms extends Base{
  
  /**
   * 添加短信签名
   * 
   * @param int $member_id 用户ID
   * @param string $sign_name 短信签名
   * @return bool
  */
  public function add_sign($member_id, $sign_name)
  {
  	if(empty($member_id) || empty($sign_name)){
  		return false;
  	}
  	$data = [
  		'owner'	=>	$member_id,
  		'name'	=>	$sign_name,
  		'status'	=>	0,
  		'create_time'	=>	time(),
  		'update_time'	=>	time()
  	];
  	$result = Db::name('sms_sign')
  						->insert($data);
  	if(!empty($result)){
  		return true;
  	}
  	return false;
  }
  
  /**
   * 更新短信签名
   * 
   * @param int $id 短信签名ID
   * @param int $member_id 用户ID
   * @param string $sign_name 短信签名
   * @return bool
  */
  public function update_sign($id, $member_id, $sign_name)
  {
  	if(empty($id) || empty($member_id) || empty($sign_name)){
  		return false;
  	}
  	$data = [
  		'owner'	=>	$member_id,
  		'name'	=>	$sign_name,
  		'status'	=>	0,
  		'update_time'	=>	time,
  	];
  	$result = Db::name('sms_sign')
  						->where('id', $id)
  						->update($data);
  	if(!empty($result)){
  		return true;
  	}
  	return false;
  }
  /**
   * 删除短信签名
   * 
   * @param int $id 短信签名
   * @param int $member_id 用户ID
   * @return bool
  */
  public function delete_sign($id)
  {
  	if(empty($id)){
  		return false;
  	}
  	$delete_result = Db::name('sms_sign')
  										->where('id', $id)
  										->delete();
  	
  	if(!empty($delete_result)){
  		return true;
  	}
  	return false;
  }
  
  /**
   * 添加短信通道
   * 
   * @param $name 短信通道名称
   * @param $url 短信通道的接口URL
   * @param $user_id 企业ID
   * @param $access_secret 账户
   * @param $count 可用条数
   * @param $password 密码
   * @param $price 价格
   * @param $is_default 是否是默认通道
   * @param $remarks 备注
  */
  public function add_sms_channel($name, $url, $user_id, $access_secret, $count, $password, $price, $is_default, $remarks)
  {
  	if(empty($name) || empty($url) || empty($user_id) || empty($access_secret) || empty($count) || empty($password) || empty($price)){
  		//参数错误
  		return false;
  	}
  	//验证URL
  	$regular = '/http(s|)://(.*)/';
  	if(preg_match($regular, $url) === false){
  		return false;
  	}
  	$user_auth = session('user_auth');
  	$data = [
  		'owner'	=>	$user_auth['uid'],
  		'name'	=>	$sign_name,
  		'url'	=>	$url,
  		'user_id'	=>	$user_id,
  		'access_secret'	=>	$access_secret,
  		'count'	=>	$count,
  		'password'	=>	$password,
  		'price'	=>	$price,
  		'is_default'	=>	$is_default,
  		'status'	=>	0,
  		'create_time'	=>	time(),
  		'update_time'	=>	time(),
  		'remarks'	=>	$remarks
  	];
  	$result = Db::name('sms_channel')
  						->insert($data);
  	if(!empty($result)){
  		return true;
  	}
  	return false;
  }
  /**
   * 编辑短信通道
   * 
   * @param $id 短信通道ID
   * @param $name 短信通道名称
   * @param $url 短信通道的接口URL
   * @param $user_id 企业ID
   * @param $access_secret 账户
   * @param $count 可用条数
   * @param $password 密码
   * @param $price 价格
   * @param $is_default 是否是默认通道
   * @param $remarks 备注
   * @return bool
  */
  public function update_sms_channel($id, $name, $url, $user_id, $access_secret, $count, $password, $price, $is_default, $remarks)
  {
  	if(empty($id) || empty($name) || empty($url) || empty($user_id) || empty($access_secret) || empty($count) || empty($password) || empty($price)){
  		//参数错误
  		return false;
  	}
  	//验证URL
  	$regular = '/http(s|)://(.*)/';
  	if(preg_match($regular, $url) === false){
  		return false;
  	}
  	$data = [
  		'name'	=>	$sign_name,
  		'url'	=>	$url,
  		'user_id'	=>	$user_id,
  		'access_secret'	=>	$access_secret,
  		'count'	=>	$count,
  		'password'	=>	$password,
  		'price'	=>	$price,
  		'is_default'	=>	$is_default,
  		'status'	=>	0,
  		'create_time'	=>	time(),
  		'update_time'	=>	time(),
  		'remarks'	=>	$remarks
  	];
  	$update_result = Db::name('sms_channel')
  										->where('id', $id)
  										->update($data);
  	if(!empty($update_result)){
  		return true;
  	}
  	return false;
  }
  
  /**
   * 删除短信通道
  */
  
  
  /**
   * 以在线信使的短信通道发送短信
   * 
   * @param string $find_user_name 子账号
   * @param string $find_user_password 子账号密码
   * @param string $admin_username 管理员账号
   * @param string $url 接口地址
   * @param string $phone 手机号码
   * @param string $content 短信内容
   * @return bool
  */
  public function send_sms_zaixianxinshi($find_user_name, $find_user_password, $admin_username, $url, $phone, $content)
  {
    if(
      empty($find_user_name)
      ||
      empty($find_user_password)
      ||
      empty($admin_username)
      ||
      empty($url)
      ||
      empty($phone)
      ||
      empty($content)
    ){
      return false;
    }
    $client = new \soapclient($url);
    
    $person = array(
        'in0' => $find_user_name, // 子账号
        'in1' => $find_user_password, // 子账号密码
        'in2'  => $admin_username, // 管理员账号
        'in3' => $phone, // 手机号
        'in4'  =>  $content, // 短信内容
        'in5'  =>  '', // 扩展号不用填写 为空
        'in6'  =>  '' // 数字和字母 可以为空(如果需要查询短信发送的情况 需要填写)
    );  
    //调用服务端的方法  
    $result = $client->sendGWMsg($person); 
    //提交成功
    if($result->out == 1){
      return true;
    }
    //提交失败
    return false;
  }
  
  
  
}