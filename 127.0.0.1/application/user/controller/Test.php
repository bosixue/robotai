<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace app\user\controller;
use think\Db;
use think\Session;
use think\Cache;
use \think\Controller;
use app\common\controller\User;
use app\user\controller\Wechat;
require_once(EXTEND_PATH .'/phpqrcode/phpqrcode.php');
class Test extends User{
public  function index(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$yunying_id = $this->get_operator_id($uid);
$config = Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$appid=$config['app_id'];
$appsecret=$config['app_secret'];
$url = config('domain_name').'/user/Wechat/getUserInfo?userid='.$uid.'&appid='.$appid.'&appsecret='.$appsecret;
$errorCorrectionLevel = "L";
$matrixPointSize = "8";
$image = \QRcode::png($url,false,$errorCorrectionLevel,$matrixPointSize);
echo $image;
exit;
}
public function send(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$obj = new Wechat();
$xx = $obj->send_massage_to_user_one($uid,'','15845818190','A',252,828);
halt($xx);
}
public function send_all(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$obj = new Wechat();
$xx = $obj->send_massage_to_user_all($uid,'','15845818190','A',252,828);
halt($xx);
}
public function get_operator_id($uid){
$admin = Db::name('admin')->where(['id'=>$uid])->find();
$roleNme = getRoleNameByUserId($uid);
if(!empty($uid) &&$roleNme!='运营商'&&$roleNme!='管理员'){
$admin_father = Db::name('admin')->where(['id'=>$admin['pid']])->find();
$father_role_name = getRoleNameByUserId($admin_father['id']);
if($father_role_name == '运营商'){
return $admin_father['id'];
}else{
$admin_granddad = Db::name('admin')->where(['id'=>$admin_father['pid']])->find();
$granddad_role_name = getRoleNameByUserId($admin_granddad['id']);
if($granddad_role_name == '运营商'){
return $admin_granddad['id'];
}else{
$admin_last = Db::name('admin')->where(['id'=>$admin_granddad['pid']])->find();
return $admin_last['id'];
}
}
}
if(!empty($uid) &&$roleNme=='运营商'){
return  $admin['id'];
}
}
public function  create_qrcode(){
$url="https://www.163.com";
$errorCorrectionLevel = "L";
$matrixPointSize = "8";
\QRcode::png($url,false,$errorCorrectionLevel,$matrixPointSize);
exit;
}
}