<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\LinesData;
use app\common\controller\RobotDistribution;
class Wxinfo extends User{
public function addWx(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$name = input('post.name','','trim,strip_tags');
$app_id = input('post.app_id','','trim,strip_tags');
$template_id = input('post.template_id','','trim,strip_tags');
$app_secret = input('post.app_secret','','trim,strip_tags');
$qr_code = input('post.qr_code','','trim,strip_tags');
$id = input('post.id','','trim,strip_tags');
$data=[
'name'=>$name,
'app_id'=>$app_id,
'template_id'=>$template_id,
'app_secret'=>$app_secret,
'qr_code'=>$qr_code,
'member_id'=>$uid,
'create_time'=>time(),
];
if(!empty($id)){
$ras = Db::name('wx_config')->where(['id'=>$id])->update($data);
if($ras){
return returnAjax(1,'编辑成功');
}else{
return returnAjax(0,'编辑失败');
}
}
$count = Db::name('wx_config')->where(['member_id'=>$uid])->count('*');
if($count){
return returnAjax(0,'您已经添加过了，一个用户只能有一个微信');
}
$res = Db::name('wx_config')->insertGetId($data);
if($res){
return returnAjax(1,'添加成功');
}else{
return returnAjax(0,'添加失败');
}
}
public function delete_wx(){
$id =input('post.id','','trim,strip_tags');
if(empty($id)){
return returnAjax(0,'id不能为空');
}
$res = Db::name('wx_config')->where(['id'=>$id])->delete();
if($res){
return returnAjax(1,'删除成功');
}else{
return returnAjax(0,'删除失败');
}
}
public function setStatus(){
$id =input('post.id','','trim,strip_tags');
$status =input('post.status','','trim,strip_tags');
$res = Db::name('wx_config')->where(['id'=>$id])->update(['status'=>$status]);
if($res){
return returnAjax(1,'状态修改成功');
}else{
return returnAjax(0,'状态修改失败');
}
}
}