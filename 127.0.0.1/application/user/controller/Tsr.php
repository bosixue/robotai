<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
class Tsr extends User{
public function _initialize() {
parent::_initialize();
$request = request();
$action = $request->action();
}
public function index()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['owner'] = $uid;
}
$list = Db::name('tsr_group')->where($where)->order('id desc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$group = array();
$group['tsr_group_id'] = $v["id"];
$res = Db::name('tel_tsr')->where($group)->count(1);
$list['data'][$k]["num"] = $res;
}
$this->assign('page',$page);
$this->assign('list',$list['data']);
return $this->fetch();
}
public function setStatus(){
$id = input('id','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('tsr_group')->where('id',$id)->update($data);
if($list){
returnAjax(0,'成功了。',$result);
}else{
return returnAjax(1,'失败!');
}
}
public function getGroupInfo() 
{
$id = input('id');
$result =  Db::name('tsr_group')->where('id',$id)->find();
if($result){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function add(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = array();
$data['name'] = input('name','','trim,strip_tags');
$data['status'] = 0;
$data['owner'] = $uid;
$data['remark'] = input('remark','','trim,strip_tags');
$groupId = input('groupId','','trim,strip_tags');
if($groupId){
$result = Db::name('tsr_group')->where('id',$groupId)->update($data);
}else{
$result = Db::name('tsr_group')->insertGetId($data);
}
if($result ||$result == 0){
return returnAjax(0,'保存成功',$result);
}else{
return returnAjax(1,'保存失败');
}
}
public function delete(){
$ids= input('id/a','','trim,strip_tags');
$result = Db::name('tsr_group')->where('id','in',$ids)->delete();
$return = Db::name('tel_tsr')->where('tsr_group_id','in',$ids)->delete();
if($result){
return returnAjax(0,'成功了',$result);
}else{
return returnAjax(1,'失败!');
}
}
public function simpage(){
if (IS_POST) {
$data = array();
$data['phone'] = input('phone','','trim,strip_tags');
$data['tsr_group_id'] = input('groupId','','trim,strip_tags');
$data['type'] = input('gtype','','trim,strip_tags');
$data['line_id'] = input('adminList','','trim,strip_tags');
if($data['type'] == 1){
$data['line_id'] = input('lineoption','','trim,strip_tags');
}
$data['status'] = 1;
$itemId = input('itemId','','trim,strip_tags');
if($itemId){
$result = Db::name('tel_tsr')->where('id',$itemId)->update($data);
}else{
$data['create_time'] = time();
$result = Db::name('tel_tsr')->insertGetId($data);
}
if($result){
return returnAjax(0,'保存成功',$result);
}else{
return returnAjax(1,'保存失败');
}
}
else{
$where = array();
$gId = input('gId','','trim,strip_tags');
if($gId){
$where['tsr_group_id'] = $gId;
}else{
$this->redirect('Tsr/index');
}
$list = Db::name('tel_tsr')->order('create_time desc')->where($where)
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
if($v["create_time"]){
$list['data'][$k]["create_time"] = date("Y-m-d H:i:s",$v["create_time"]);
}else{
$list['data'][$k]["create_time"] = "--";
}
$adminlist = Db::name('admin')->field('mobile')->where("id",$v["line_id"])->find();
if($adminlist){
$list['data'][$k]["phone"] = $adminlist["mobile"];
}
}
$this->assign('gId',$gId);
$this->assign('list',$list['data']);
$this->assign('page',$page);
$linelist = Db::name('tel_line')->field('id,name')->where("status",1)->select();
$this->assign('linelist',$linelist);
$adminlist = Db::name('admin')->field('id,username,realname')->where(array("user_type"=>1,"open_tsr"=>1))->where("status",1)->select();
$this->assign('adminlist',$adminlist);
return $this->fetch();
}
}
public function getItemInfo(){
$id = input('id');
$result =  Db::name('tel_tsr')->where('id',$id)->find();
if($result){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function setStrStatus(){
$id = input('sId','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$result = Db::name('tel_tsr')->where('id',$id)->update($data);
if($result){
returnAjax(0,'成功',$result);
}else{
return returnAjax(1,'失败!');
}
}
public function delTsr(){
$ids= input('id/a','','trim,strip_tags');
$result = Db::name('tel_tsr')->where('id','in',$ids)->delete();
if($result){
returnAjax(0,'成功',$result);
}else{
return returnAjax(1,'失败!');
}
}
public function member_list(){
return $this->fetch();
}
}