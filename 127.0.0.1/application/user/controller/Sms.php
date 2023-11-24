<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace app\user\controller;
use app\common\controller\User;
use app\api\controller\Users;
use Flc\Dysms\Client;
use Flc\Dysms\Request\SendSms;
use think\Db;
use think\Session;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\LinesData;
use app\common\controller\RobotDistribution;
use app\common\controller\RedisConnect;
use app\common\controller\OperationRecord;
class Sms extends User{
public function _initialize() {
parent::_initialize();
$request = request();
$action = $request->action();
}
public function channel()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$whereArr=['pid'=>$uid,'role_id'=>['<',20]];
$find_users = Db::name('admin')
->field('id,username')
->where($whereArr)
->select();
$this->assign('find_users',$find_users);
$uid = $user_auth["uid"];
$info = Db::name('admin')->where('id',$uid)->find();
$role_id = $info['role_id'];
$this->assign('role_id',$role_id);
$sms = Db::name('sms_channel')
->field('id,name,price')
->where('owner',$uid)
->select();
foreach($sms as $key=>$value){
$sms[$key]['price'] = aitel_round($value['price'],'短信');
}
$this->assign('sms',$sms);
return $this->fetch();
}
public function add_channel()
{
$name = input('name','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$url = input('url','','trim,strip_tags');
$sms_username = input('username','','trim,strip_tags');
$sms_password = input('password','','trim,strip_tags');
$sms_price = input('price/f','','trim,strip_tags');
$sms_note = input('note','','trim,strip_tags');
$userid = input('userid','','trim,strip_tags');
$user_auth = session('user_auth');
if(empty($name)){
return $this->Json(3,'通道名称不能为空');
}
if(empty($type)){
return $this->Json(3,'通道类型不能为空');
}
if($type == '云片网'){
if(empty($sms_username)){
return $this->Json(3,'账户不能为空');
}
}else{
if(empty($sms_username)){
return $this->Json(3,'账户不能为空');
}
if(empty($sms_password)){
return $this->Json(3,'密码不能为空');
}
}
if(empty($url)){
return $this->Json(3,'接口地址不能为空');
}
if($sms_price == ''){
return $this->Json(3,'短信单价不能为空');
}
$user_auth = session('user_auth');
$insert_data = [
'name'=>$name,
'type'=>$type,
'url'=>$url,
'user_id'=>$sms_username,
'owner'=>$user_auth['uid'],
'relation_member_id'=>$user_auth['uid'],
'status'=>1,
'password'=>$sms_password,
'price'=>$sms_price,
'remarks'=>$sms_note,
'create_time'=>time(),
'enterprise_id'=>$userid
];
$result = Db::name('sms_channel')->insertGetId($insert_data);
if(!empty($result)){
$OperationRecord = new OperationRecord();
$record_content = $OperationRecord->get_operation_content('add_sms_channel',[],$insert_data);
$OperationRecord->insert_sms_channel($user_auth['uid'],$user_auth['uid'],'添加短信通道',$record_content,json_encode([]),json_encode($insert_data));
return $this->Json(0,'成功');
}
return $this->Json(1,'失败');
}
public function update_channel()
{
$id = input('id/d','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$url = input('url','','trim,strip_tags');
$sms_username = input('username','','trim,strip_tags');
$sms_password = input('password','','trim,strip_tags');
$sms_price = input('price/f','','trim,strip_tags');
$sms_note = input('note','','trim,strip_tags');
$userid = input('userid','','trim,strip_tags');
if(empty($id)){
return $this->Json(2,'ID参数错误');
}
if(empty($name)){
return $this->Json(3,'通道名称不能为空');
}
if(empty($type)){
return $this->Json(3,'通道类型不能为空');
}
if(empty($url)){
return $this->Json(3,'接口地址不能为空');
}
if($type == '云片网'){
if(empty($sms_username)){
return $this->Json(3,'账户不能为空');
}
}else{
if(empty($sms_username)){
return $this->Json(3,'账户不能为空');
}
if(empty($sms_password)){
return $this->Json(3,'密码不能为空');
}
}
if($sms_price == ''){
return $this->Json(3,'短信单价不能为空');
}
$user_auth = session('user_auth');
if(empty($user_auth['uid'])){
return $this->Json(3,'未登录');
}
$OperationRecord = new OperationRecord();
$old_sms_channel_data = Db::name('sms_channel')->where('id',$id)->find();
$new_sms_channel_data = [
'name'=>$name,
'type'=>$type,
'url'=>$url,
'user_id'=>$sms_username,
'password'=>$sms_password,
'price'=>$sms_price,
'remarks'=>$sms_note,
'enterprise_id'=>$userid
];
$record_content = $OperationRecord->get_operation_content('update_sms_channel',$old_sms_channel_data,$new_sms_channel_data);
\think\Log::record('$record_content===='.$record_content);
if(!empty($record_content)){
$result = $OperationRecord->insert_sms_channel($user_auth['uid'],$user_auth['uid'],'编辑短信通道',$record_content,json_encode($old_sms_channel_data),json_encode($new_sms_channel_data));
\think\Log::record('$result===='.$result);
}
$result = Db::name('sms_channel')->where('id',$id)->update($new_sms_channel_data);
if(!empty($result)){
$ids = Db::name('sms_channel')
->where('pid',$id)
->column('id');
$screen_ids = [];
$screen_ids = array_merge($screen_ids,$ids);
while(count($ids) >0){
$ids = Db::name('sms_channel')
->where('pid','in',$ids)
->column('id');
$screen_ids = array_merge($screen_ids,$ids);
}
$new_sms_channel_data = [
'name'=>$name,
'type'=>$type,
'url'=>$url,
'user_id'=>$sms_username,
'password'=>$sms_password,
];
$record_content = $OperationRecord->get_operation_content('update_sms_channel',$old_sms_channel_data,$new_sms_channel_data);
if(!empty($record_content)){
foreach($screen_ids as $key=>$value){
$OperationRecord->insert_sms_channel($user_auth['uid'],$value,'编辑短信通道',$record_content,json_encode($old_sms_channel_data),json_encode($new_sms_channel_data));
}
}
if(count($screen_ids) >0){
$update_result = Db::name('sms_channel')
->where('id','in',$screen_ids)
->update($new_sms_channel_data);
if(empty($update_result)){
\think\Log::record('更新短信子通道失败');
}
}
}
\think\Log::record('更新短信通道');
if(!empty($result)){
if(count($screen_ids) >0 &&empty($update_result)){
return $this->Json(1,'更新失败');
}
return $this->Json(0,'更新成功');
}
return $this->Json(1,'更新失败');
}
public function get_channels()
{
$keyword = input('keyword','','trim,strip_tags');
$page = input('page','','trim,strip_tags');
if(empty($page)){
$page = 1;
}
$limit = input('limit','','trim,strip_tags');
if(empty($limit)){
$limit = 10;
}
$where = [];
$where_count = [];
$user_auth = session('user_auth');
$where['sc.owner'] = $user_auth['uid'];
$where_count['owner'] = $user_auth['uid'];
if(!empty($keyword)){
$where['sc.name'] = ['like','%'.$keyword.'%'];
$where_count['name'] = ['like','%'.$keyword.'%'];
}
$datas = Db::name('sms_channel')
->field('sc.*,a.username as source')
->alias('sc')
->join('sms_channel p_sc','sc.pid = p_sc.id','LEFT')
->join('admin a','a.id = p_sc.owner','LEFT')
->where($where)
->order('create_time desc')
->page($page,$limit)
->select();
$i = ($page -1) * $limit +1;
foreach($datas as $key=>$value){
if(empty($value['source'])){
$datas[$key]['source'] = '自有通道';
}
$datas[$key]['key'] = $i;
$datas[$key]['price'] = aitel_round($value['price'],'短信');
$i++;
}
$count =  Db::name('sms_channel')
->where($where_count)
->count('id');
$result = [
'data'=>$datas,
'count'=>$count
];
return $this->Json(0,'成功',$result);
}
public function get_sms_channel()
{
$id = input('id','','trim,strip_tags');
if(empty($id)){
return $this->Json('参数错误');
}
$data = Db::name('sms_channel')
->where('id',$id)
->find();
return $this->Json(0,'成功',$data);
}
public function show_optional_sms_channel()
{
$user_auth = session('user_auth');
$user_id = $user_auth['uid'];
$find_user_id = input('find_user_id','','trim,strip_tags');
$datas = Db::name('sms_channel')
->field('id,name,price')
->where([
'owner'=>$user_id,
])
->select();
foreach($datas as $key=>$value){
$datas[$key]['price'] = aitel_round($value['price'],'短信');
$find_data = Db::name('sms_channel')
->field('price as find_price,remarks as find_note')
->where('pid',$value['id'])
->find();
$datas[$key]['find_price'] = aitel_round($find_data['find_price'],'短信');
$datas[$key]['find_note'] = $find_data['find_note'];
}
return $this->Json(0,'成功',$datas);
}
public function get_find_distribution_sms_channel_api()
{
$find_user_id = input('find_user_id','','trim,strip_tags');
if(empty($find_user_id)){
return $this->Json(3,'请先指定用户');
}
$user_auth = session('user_auth');
$is_find_user = Db::name('admin')
->where([
'pid'=>$user_auth['uid'],
'id'=>$find_user_id
])
->count('id');
if(empty($is_find_user)){
return $this->Json(3,'无效用户');
}
$datas = Db::name('sms_channel')
->alias('sc')
->join('sms_channel p_sc','p_sc.id = sc.pid','LEFT')
->field('sc.name,sc.price,sc.count,sc.create_time,sc.remarks,sc.id,p_sc.price as cost,p_sc.count as surplus_count')
->where([
'sc.owner'=>$find_user_id,
'sc.pid'=>['<>',0]
])
->select();
foreach($datas as $key=>$value){
$datas[$key]['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
$datas[$key]['price'] = aitel_round($value['price'],'短信');
}
return $this->Json(0,'成功',$datas);
}
public function distribution_sms_channel_api()
{
$user_auth = session('user_auth');
$find_user_id = input('find_user_id/d','','trim,strip_tags');
$sms_channel_id = input('sms_channel_id/d','','trim,strip_tags');
if($user_auth['role'] != '商家'){
$price = input('price/f','','trim,strip_tags');
}else{
$price = Db::name('sms_channel')
->where('id',$sms_channel_id)
->value('price');
}
$note = input('note','','trim,strip_tags');
$where = [
'owner'=>$find_user_id,
'pid'=>$sms_channel_id
];
$old_data = Db::name('sms_channel')->where($where)->find();
if(!empty($old_data)){
$find_sms_channl_id = $old_data['id'];
$sms_count = DB::name('sms_channel')
->where([
'id'=>$find_sms_channl_id,
'pid'=>$sms_channel_id,
'owner'=>$find_user_id,
'price'=>$price,
'remarks'=>$note
])
->count('id');
if(!empty($sms_count)){
return $this->Json(0,'成功');
}
$new_data = [
'price'=>$price,
'remarks'=>$note
];
$result = Db::name('sms_channel')
->where('id',$find_sms_channl_id)
->update($new_data);
$OperationRecord = new OperationRecord();
$record_content = $OperationRecord->get_operation_content('update_sms_channel',$old_data,$new_data);
$OperationRecord->insert_sms_channel($user_auth['uid'],$old_data['owner'],'编辑短信通道',$record_content,json_encode($old_data),json_encode($new_data));
$find_sms_user_role_name = Db::name('admin')->alias('a')->join('admin_role ar','a.role_id = ar.id','LEFT')->where('a.id',$old_data['owner'])->value('ar.name');
if($find_sms_user_role_name == '商家'){
Db::name('sms_channel')->where('pid',$find_sms_channl_id)->update($new_data);
$find_user_ids = Db::name('admin')->where('pid',$old_data['owner'])->field('id')->select();
foreach($find_user_ids as $key=>$value){
$OperationRecord->insert_sms_channel($user_auth['uid'],$value,'编辑短信通道',$record_content,json_encode($old_data),json_encode($new_data));
}
}
}else{
$sms_channel_data = Db::name('sms_channel')
->where([
'owner'=>$user_auth['uid'],
'id'=>$sms_channel_id
])
->find();
if(empty($sms_channel_data)){
return $this->Json(3,'短信通道不存在');
}
unset($sms_channel_data['id']);
$sms_channel_data['pid'] = $sms_channel_id;
$sms_channel_data['owner'] = $find_user_id;
$sms_channel_data['price'] = $price;
$sms_channel_data['remarks'] = $note;
$result = Db::name('sms_channel')->insert($sms_channel_data);
$OperationRecord = new OperationRecord();
$record_content = $OperationRecord->get_operation_content('distribution_sms_channel',[],$sms_channel_data);
$OperationRecord->insert_sms_channel($user_auth['uid'],$find_user_id,'分配短信通道',$record_content,json_encode([]),json_encode($sms_channel_data));
}
if(!empty($result)){
return $this->Json(0,'成功');
}
return $this->Json(1,'失败');
}
public function delete_sms_channel_api()
{
$sms_channel_id = input('sms_channel_id','','trim,strip_tags');
if(empty($sms_channel_id)){
return $this->Json(3,'请先选择指定短信通道');
}
$user_auth = session('user_auth');
$ids = [$sms_channel_id];
$screen_ids = [$sms_channel_id];
$OperationRecord = new OperationRecord();
$sms_channel_data = Db::name('sms_channel')->where('id',$sms_channel_id)->find();
if($sms_channel_data['owner'] != $user_auth['uid']){
$record_content = $OperationRecord->get_operation_content('recovery_sms_channel',$sms_channel_data,[]);
$record_fu = '收回短信通道';
}else{
$record_content = $OperationRecord->get_operation_content('delete_sms_channel',$sms_channel_data,[]);
$record_fu = '删除短信通道';
}
while(count($ids) >0){
$datas = Db::name('sms_channel')
->field('id')
->where('pid','in',$ids)
->select();
$ids = [];
foreach($datas as $key=>$value){
$screen_ids[] = $value['id'];
$ids[] = $value['id'];
}
}
Db::startTrans();
try {
$sms_channels = Db::name('sms_channel')->where('id','in',$screen_ids)->field('id, owner')->select();
$delete_result = Db::name('sms_channel')
->where('id','in',$screen_ids)
->delete();
foreach($sms_channels as $key=>$value){
$OperationRecord->insert_sms_channel($user_auth['uid'],$value['owner'],$record_fu,$record_content,json_encode([]),json_encode([]),'');
}
Db::commit();
return $this->Json(0,'成功',$screen_ids);
}catch (\Exception $e) {
Db::rollback();
return $this->Json(1,'失败');
}
}
public function getChannel()
{
$id = input('id');
$result =  Db::name('sms_channel')->where('id',$id)->find();
$result["price"] = unserialize($result["price"]);
if($result){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function delChannel(){
$ids = input('ids/a','','trim,strip_tags');
$list = Db::name('sms_channel')->where('id','in',$ids)->delete();
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function signature(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$keyword = input('keyword','','trim,strip_tags');
if($keyword){
$where['name'] = $keyword;
}
if(!$super){
$where['owner'] = $uid;
}
$list = Db::name('sms_sign')->order('create_time desc')->where($where)
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
if($v["create_time"]){
$list['data'][$k]["create_time"] = date("Y-m-d H:i:s",$v["create_time"]);
}else{
$list['data'][$k]["create_time"] = "--";
}
}
$this->assign('isSuper',$super);
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function auditing_sign()
{
$sign_id = input('sign_id/d','','trim,strip_tags');
$update_result = Db::name('sms_sign')
->where('id',$sign_id)
->update([
'status'=>1
]);
if(!empty($update_result)){
return $this->Json(0,'成功');
}
return $this->Json(1,'失败');
}
public function get_signs()
{
$where = [];
$start_create_time = input('start_create_time','','trim,strip_tags');
$end_create_time = input('end_create_time','','trim,strip_tags');
if(!empty($start_create_time) &&!empty($end_create_time)){
$where['sg.create_time'] = ['between time',[strtotime($start_create_time),strtotime($end_create_time)]];
}else{
if(!empty($start_create_time) &&empty($end_create_time)){
$where['sg.create_time'] = [">=",strtotime($start_create_time)];
}else if(!empty($end_create_time) &&empty($start_create_time)){
$where['sg.create_time'] = ["<=",strtotime($end_create_time)];
}
}
$status = input('status','','trim,strip_tags');
if($status != ''){
$where['sg.status'] = $status;
}
$keyword = input('keyword');
if(!empty($keyword)){
$where['sg.name'] = ['like','%'.$keyword.'%'];
}
$page = input('page');
if(empty($page)){
$page = 1;
}
$limit = input('limit');
if(empty($limit)){
$limit = 10;
}
$user_auth = session('user_auth');
$where['owner'] = $user_auth['uid'];
$datas = [];
$datas['data'] = Db::name('sms_sign')
->alias('sg')
->field('sg.*,a.username')
->join('admin a','a.id = sg.owner','LEFT')
->where($where)
->page($page,$limit)
->order('sg.create_time desc')
->select();
$datas['count'] = Db::name('sms_sign')
->alias('sg')
->field('sg.*,a.username')
->join('admin a','a.id = sg.owner','LEFT')
->where($where)
->count('*');
$i = ($page -1) * $limit +1;
foreach($datas['data'] as $key=>$value){
$datas['data'][$key]['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
if($value['status'] == 0){
$datas['data'][$key]['status_name'] = '未提交审核';
}else if($value['status'] == 1){
$datas['data'][$key]['status_name'] = '审核中';
}else if($value['status'] == 2){
$datas['data'][$key]['status_name'] = '审核未通过';
}else if($value['status'] == 3){
$datas['data'][$key]['status_name'] = '审核通过';
}else if($value['status'] == 4){
$datas['data'][$key]['status_name'] = '审核未通过';
}
$datas['data'][$key]['key'] = $i;
$i++;
}
return $this->Json(0,'成功',$datas);
}
public function get_sign()
{
$id = input('id/d','','trim,strip_tags');
if(empty($id)){
return $this->Json(2,'参数错误');
}
$sign_name = Db::name('sms_sign')
->where('id',$id)
->value('name');
return $this->Json(0,'成功',$sign_name);
}
public function add_sign()
{
$name = input('name','','trim,strip_tags');
if(empty($name)){
return $this->Json(2,'参数错误');
}
$user_auth = session('user_auth');
$where['owner'] = array('eq',$user_auth['uid']);
$where['name'] = array('eq',$name);
$res = Db::name('sms_sign')->where($where)->count();
if($res >0){
return $this->Json(3,'添加失败,短信签名是唯一的');
}else{
$data = [
'owner'=>$user_auth['uid'],
'name'=>$name,
'status'=>0,
'create_time'=>time(),
];
$sign_id = Db::name('sms_sign')
->insertGetId($data);
if(!empty($sign_id)){
return $this->Json(0,'添加成功');
}
return $this->Json(1,'添加失败');
}
}
public function update_sign()
{
$id = input('id','','trim,strip_tags');
$sign_name = input('sign_name','','trim,strip_tags');
if(empty($id)){
return $this->Json(2,'参数错误');
}
if(empty($sign_name)){
return $this->Json(3,'短信签名不能为空');
}
$where['id'] = array('eq',$id);
$where['name'] = array('eq',$sign_name);
$res = Db::name('sms_sign')->where($where)->count();
if($res >1){
return $this->Json(3,'修改失败,短信签名是唯一的');
}else{
$update_result = Db::name('sms_sign')
->where('id',$id)
->update([
'name'=>$sign_name,
]);
if(!empty($update_result)){
$update_template_result = Db::name('sms_template')
->where('sign_id',$id)
->update([
'status'=>0
]);
return $this->Json(0,'修改成功');
}
return $this->Json(1,'修改失败');
}
}
public function delete_sign(){
$id = input('id/d','','trim,strip_tags');
if($id){
$res = Db::name('sms_sign')->where('id',$id)->delete();
if($res){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
$where = [];
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where['owner'] = array('eq',$uid);
$start_create_time = input('start_create_time','','trim,strip_tags');
$end_create_time = input('end_create_time','','trim,strip_tags');
if(!empty($start_create_time) &&!empty($end_create_time)){
$where['sg.create_time'] = ['between time',[strtotime($start_create_time),strtotime($end_create_time)]];
}else{
if(!empty($start_create_time) &&empty($end_create_time)){
$where['sg.create_time'] = [">=",strtotime($start_create_time)];
}else if(!empty($end_create_time) &&empty($start_create_time)){
$where['sg.create_time'] = ["<=",strtotime($end_create_time)];
}
}
$status = input('status','','trim,strip_tags');
if($status != ''){
$where['sg.status'] = $status;
}
$keyword = input('keyword');
if(!empty($keyword)){
$where['sg.name'] = ['like','%'.$keyword.'%'];
}
$type =  input('type','','trim,strip_tags');
if($type == 1){
$res =Db::name('sms_sign')->where($where)->delete();
}else{
$where = [];
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
$res = Db::name('sms_sign')->where($where)->delete();
}
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}
public function setSignStatus(){
$sId = input('sId','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('sms_sign')->where('id',$sId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function template(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$sms_channels = Db::name('sms_channel')
->field('id,name')
->where([
'owner'=>$uid
])
->select();
$this->assign('sms_channels',$sms_channels);
$sms_signs = Db::name('sms_sign')
->field('id,name')
->where('owner',$uid)
->select();
$this->assign('sms_signs',$sms_signs);
return $this->fetch();
}
public function getTemplateData(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$limit = input('post.limit','','trim,strip_tags');
$page = input('post.page','','trim,strip_tags');
$username = input('post.username','','trim,strip_tags');
$startDate = strtotime(input('post.startDate','','trim,strip_tags'));
$endTime = strtotime(input('post.endTime','','trim,strip_tags'));
$status = input('post.status','','trim,strip_tags');
$shenghe = input('post.shenghe',' ','trim,strip_tags');
$keyword = input('post.keyword','','trim,strip_tags');
if(!empty($username)){
$where['s.name']=['like','%'.$username.'%'];
}
if($status!=""){
$where['s.type']=$status;
}
if($shenghe!=""){
$where['s.status']=$shenghe;
}
if(!empty($keyword)){
$where['s.content']=['like','%'.$keyword.'%'];
}
if(!empty($startDate)&&!empty($endTime)){
$where['s.create_time']=[['>=',$startDate],['<',$endTime],'and'];
}
$where['s.owner']=$uid;
if(empty($limit)){
$Page_size = 10;
}else{
$Page_size = $limit;
}
$templates = Db::name('sms_template')
->alias('s')
->join('admin a','a.id=s.owner','LEFT')
->join('sms_channel sc','sc.id = s.channel_id','LEFT')
->join('admin channel_a','channel_a.id = sc.relation_member_id','LEFT')
->field('s.*,a.username,channel_a.username as channel_username')
->where($where)
->page($page,$Page_size)
->select();
foreach($templates as $k=>$v){
$templates[$k]['username'] = getUsernameById($v['owner']);
if(getSmsChannelName($v['channel_id'])){
$templates[$k]['tongdaoname'] = getSmsChannelName($v['channel_id']);
}else{
$templates[$k]['tongdaoname'] = "【通道异常】";
}
$templates[$k]['sign'] = getSmsSignlName($v['sign_id']);
$templates[$k]['shuangtai'] = getStatusSms($v['status']);
if(!$v['channel_username']){
$templates[$k]['channel_username'] = '【角色异常】';
}
}
$total =  Db::name('sms_template')
->alias('s')
->field('s.*,a.username')
->join('admin a','a.id=s.owner')
->where($where)
->count('*');
$totalPage =ceil($total/$Page_size);
$data['limit']=$Page_size;
$data['total']=$total;
$data['Nowpage']=$page;
$data['page_count']=$totalPage;
$data['templates']=$templates;
return returnAjax(0,'显示数据成功',$data);
}
public function setTemplateStatus(){
$id = input('post.id','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'请选择一项再提交审核');
}
$res = Db::name('sms_template')->where(['id'=>$id])->update(['status'=>1]);
if($res){
return returnAjax(0,'审核提交成功');
}else{
return returnAjax(1,'审核提交失败');
}
}
public function delete_template(){
$id = input('post.id','','trim,strip_tags');
$arr = input('post.arr/a','','trim,strip_tags');
if(!empty($id)){
$where['id']=['in',$id];
}else{
$where['id']=['in',$arr];
}
$res = Db::name('sms_template')->where($where)->delete();
if($res){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
public  function add_template(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$id = input('post.id','','trim,strip_tags');
if(!empty($id)){
$name = input('post.templateName','','trim,strip_tags');
$channel_id = input('post.channel_id','','trim,strip_tags');
$sign_id = input('post.sign_id','','trim,strip_tags');
$conent = input('post.content','','trim,strip_tags');
$varname = input('post.varname','','trim,strip_tags');
$data['name'] = $name;
$data['channel_id'] = $channel_id;
$data['sign_id'] = $sign_id;
$data['content'] = $conent;
$data['variable'] = $varname;
$data['update_time']=time();
$data['status'] = 0;
$res = Db::name('sms_template')->where(['id'=>$id])->update($data);
if($res){
return returnAjax(0,'编辑成功');
}else{
return returnAjax(1,'编辑失败');
}
}else{
$name = input('post.templateName','','trim,strip_tags');
$channel_id = input('post.channel_id','','trim,strip_tags');
$sign_id = input('post.sign_id','','trim,strip_tags');
$conent = input('post.content','','trim,strip_tags');
$varname = input('post.varname','','trim,strip_tags');
$data['owner']=$uid;
$data['name'] = $name;
$data['channel_id'] = $channel_id;
$data['sign_id'] = $sign_id;
$data['content'] = $conent;
$data['variable'] = $varname;
$data['create_time']=time();
$res = Db::name('sms_template')->insertGetId($data);
if($res){
return returnAjax(0,'新增成功');
}else{
return returnAjax(1,'新增失败');
}
}
}
public function edit_template_vie(){
$id = input('id','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'请选择一项再编辑');
}
$template =  Db::name('sms_template')->where('id',$id)->find();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$sms_channels = Db::name('sms_channel')
->field('id,name')
->where([
'owner'=>$uid
])
->select();
$sms_signs = Db::name('sms_sign')
->field('id,name')
->where('owner',$uid)
->select();
$data['template']=$template;
$data['sms_channels']=$sms_channels;
$data['sms_signs']=$sms_signs;
if($template){
return returnAjax(0,'有数据了',$data);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function setTplStatus(){
$sId = input('sId','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('sms_template')->where('id',$sId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function delTpl(){
$ids = input('ids/a','','trim,strip_tags');
$list = Db::name('sms_template')->where('id','in',$ids)->delete();
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function sendSms(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
if (IS_POST) {
$owner = $uid;
$mobile = input('mobile','','trim,strip_tags');
$templateId = input('tpl','','trim,strip_tags');
$result = sendMsg($owner,$templateId,$mobile);
if($result){
return returnAjax(0,'发送成功');
}else{
return returnAjax(1,'发送失败');
}
}
else{
$ch = array();
if(!$super){
$ch['owner'] = $uid;
}
$ch['status'] = 1;
$tpllist = Db::name('sms_template')->field('id,name,conent')->where($ch)->select();
$this->assign('tpllist',$tpllist);
return $this->fetch();
}
}
public function sendMsg_old($tid,$mobile){
$send = array();
$send['number'] = '122333';
$send['mobile'] = $mobile;
$con = array();
$con['number'] = $tpllist['name'];
$con['mobile'] = $tpllist['conent'];
$para = "{\"name\":\"郭涛\",\"number\":\"316\"}";
$smsConfig = [
'accessKeyId'=>$chlist['user_id'],
'accessKeySecret'=>$chlist['access_secret'],
'signName'=>$signlist['name'],
'templateCode'=>'SMS_119910993',
];
$client  = new Client($smsConfig);
$sendSms = new SendSms;
$sendSms->setPhoneNumbers($send['mobile']);
$sendSms->setSignName($smsConfig['signName']);
$sendSms->setTemplateCode($smsConfig['templateCode']);
if($tpllist['type'] == '0'){
$sendSms->setTemplateParam(['code'=>$send['number']]);
}else if($tpllist['type'] == '1'){
$sendSms->setTemplateParam(['product'=>$tpllist['conent']]);
}else{
$sendSms->setTemplateParam(['product'=>$tpllist['conent']]);
}
$sendSms->setOutId('demo');
$resp = $client->execute($sendSms);
$result = json_decode(json_encode($resp),true);
if (isset($result['Code']) &&$result['Code'] == 'OK'){
return True;
}else{
return False;
}
\think\Log::record('sms inferface failure='.json_encode($result));
}
public function sendRecord(){
return $this->fetch();
}
public function export_sms_sendrecord(){
$columName = ['手机号','短信类型','短信内容','任务名称','计费（元）','发送时间','发送状态'];
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$where = [];
$export_type = input('export_type','','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
$status = input('statusz','','trim,strip_tags');
$sTime = strtotime(input('startDate','','trim,strip_tags'));
$eTime = strtotime(input('endTime','','trim,strip_tags'));
if(!empty($export_type)){
$where['sms.owner'] = $uid;
if($mobile){
$where['sms.mobile'] = ['like','%'.$mobile.'%'];
}
if($status != ""){
$where['sms.status'] = $status;
}
if($sTime &&$eTime){
$where["sms.create_time"] = ["between time",[$sTime,$eTime]];
}
}else{
$usercheck = input('usercheck/a','','trim,strip_tags');
if(is_array($usercheck) === true &&count($usercheck) >0){
$where['sms.id'] = ['in',$usercheck];
}
}
$mList = Db::name('sms_record')
->alias('sms')
->join('tel_config t','t.task_id = sms.task_id','LEFT')
->field('sms.*,t.task_name')
->where($where)
->order('sms.create_time desc')
->select();
$list = array();
foreach($mList as $k=>$v){
$list[$k]['mobile'] = $v['mobile'];
$list[$k]['sms_type'] = '短信通道';
$list[$k]['content'] = $v['content'];
$list[$k]['task_name'] = $v['task_name'] == ''?'任务已删除': $v['task_name'];
$list[$k]['money'] = $v['money'];
$list[$k]['create_time'] = date("Y-m-d H:i",$v['create_time']);
$list[$k]['status'] = $v['status'] == 0 ?'失败':'成功';
}
$setTitle='Sheet1';
$fileName='文件名称';
if ( empty($columName) ||empty($list) ) {
return returnAjax(2,'导出数据不能为空');
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(2,'列名跟数据的列不一致',$list[0]);
}
$PHPExcel = new \PHPExcel();
$PHPSheet = $PHPExcel->getActiveSheet();
$PHPSheet->setTitle($setTitle);
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
for ($i=0;$i <count($list[0]);$i++) {
$PHPSheet->setCellValue("$letter[$i]1","$columName[$i]");
}
foreach ($list as $key =>$val) {
foreach (array_values($val) as $key2 =>$val2) {
$PHPSheet->setCellValue($letter[$key2].($key+2),$val2);
}
}
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xls';
$PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
return returnAjax(0,'导出成功',config('res_url').ltrim($execlpath,"."));
}
public function ajax_sendrecord(){
if(request()->isPost()){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$mobile = input('mobile','','trim,strip_tags');
if($mobile){
$where['sms.mobile'] = ['like','%'.$mobile.'%'];
}
$status = input('statusz','','trim,strip_tags');
if($status != ""){
$where['sms.status'] = $status;
}
$sTime = strtotime(input('startDate','','trim,strip_tags'));
$eTime = strtotime(input('endTime','','trim,strip_tags'));
if($sTime &&$eTime){
$where["sms.create_time"] = ["between time",[$sTime,$eTime]];
}
if(!$super){
$where['sms.owner'] = $uid;
}
$page = input('page','','trim,strip_tags');
$page_size  = input('page_size','','trim,strip_tags');
$list = Db::name('sms_record')
->alias('sms')
->join('tel_config t','t.task_id = sms.task_id','LEFT')
->field('sms.*,t.task_name')
->where($where)
->order('create_time desc')
->page($page,$page_size)
->select();
foreach($list as $k=>$v){
$list[$k]['sms_type'] = '短信通道';
$list[$k]['task_id'] = $v['task_name'] == ''?'任务已删除': $v['task_name'];
$list[$k]['create_time'] = date("Y-m-d H:i",$v['create_time']);
$list[$k]['status'] = $v['status'] == 0 ?'失败':'成功';
$list[$k]['mobile'] = hide_phone_middle( $list[$k]['mobile'] );
}
$count = Db::name('sms_record')->alias('sms')->join('tel_config t','t.task_id = sms.task_id','LEFT')->field('sms.*,t.task_name')->where($where)->count();
$page_count = ceil($count/$page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取成功',$data);
}
}
public function del_message(){
$id = input('id','','trim,strip_tags');
if($id){
$where['id'] = array('eq',$id);
$res = Db::name('sms_record')->where($where)->delete();
if($res){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}else{
$type = input('type','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['owner'] = array('eq',$uid);
if($type == 1){
$res = Db::name('sms_record')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}else if($type == 0){
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
$res = Db::name('sms_record')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}
}
}
public function statistics(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$this->assign('super',$super);
$list = Db::name('admin')->field('id,username')->select();
$this->assign('adlist',$list);
return $this->fetch();
}
public function get_sms_consumption_statistics()
{
$page = input('page',1,'trim,strip_tags');
$limit = input('limit',10,'trim,strip_tags');
$start_time = input('start_time','','trim,strip_tags');
$end_time = input('end_time','','trim,strip_tags');
$user_auth = session('user_auth');
$where = [];
$where['scs.member_id'] = $user_auth['uid'];
if(!empty($start_time) &&!empty($end_time)){
$where['scs.date'] = [['>=',$start_time],['<',$end_time],'and'];
}else{
if(!empty($start_time)){
$where['scs.date'] = ['>=',$start_time];
}else if(!empty($end_time)){
$where['scs.date'] = ['<',$end_time];
}
}
$datas = Db::name('sms_consumption_statistics')
->alias('scs')
->join('admin a','a.id = scs.member_id','LEFT')
->field('scs.*,a.username')
->where($where)
->page($page,$limit)
->order('date desc')
->select();
$i = ($page -1) * $limit +1;
foreach($datas as $key=>$value){
$datas[$key]['key'] = $i;
$i++;
}
$count = Db::name('sms_consumption_statistics')
->alias('scs')
->where($where)
->count('id');
return $this->Json(0,'成功',['datas'=>$datas,'count'=>$count]);
}
public function export_sms_consumption(){
$columName = ['账号','消费日期','消费数量（条）','费率（元/条）','合计消费'];
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$export_type = input('export_type','','trim,strip_tags');
$start_time = input('start_time','','trim,strip_tags');
$end_time = input('end_time','','trim,strip_tags');
if(!empty($export_type)){
$where = [];
$where['scs.member_id'] = $user_auth['uid'];
if(!empty($start_time) &&!empty($end_time)){
$where['scs.date'] = [['>=',$start_time],['<',$end_time],'and'];
}else{
if(!empty($start_time)){
$where['scs.date'] = ['>=',$start_time];
}else if(!empty($end_time)){
$where['scs.date'] = ['<',$end_time];
}
}
$mList = Db::name('sms_consumption_statistics')
->alias('scs')
->join('admin a','a.id = scs.member_id','LEFT')
->field('scs.*,a.username')
->where($where)
->order('date desc')
->select();
}else{
$cwhere = array();
$usercheck = input('usercheck/a','','trim,strip_tags');
if(is_array($usercheck) === true &&count($usercheck) >0){
$cwhere['scs.id'] = ['in',$usercheck];
}
$mList = Db::name('sms_consumption_statistics')
->alias('scs')
->join('admin a','a.id = scs.member_id','LEFT')
->field('scs.*,a.username')
->where($cwhere)
->order('date desc')
->select();
}
$list = array();
foreach($mList as $key =>$item){
$list[$key]['username'] = $item['username'];
if(empty($item['date'])){
$list[$key]['date'] = '暂无日期';
}else{
$list[$key]['date'] = $item['date'];
}
$list[$key]['count'] = $item['count'];
$list[$key]['price'] = $item['price'];
$list[$key]['money'] = $item['money'];
}
$setTitle='Sheet1';
$fileName='文件名称';
if ( empty($columName) ||empty($list) ) {
return returnAjax(2,'导出数据不能为空');
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(2,'列名跟数据的列不一致',$list[0]);
}
$PHPExcel = new \PHPExcel();
$PHPSheet = $PHPExcel->getActiveSheet();
$PHPSheet->setTitle($setTitle);
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
for ($i=0;$i <count($list[0]);$i++) {
$PHPSheet->setCellValue("$letter[$i]1","$columName[$i]");
}
foreach ($list as $key =>$val) {
foreach (array_values($val) as $key2 =>$val2) {
$PHPSheet->setCellValue($letter[$key2].($key+2),$val2);
}
}
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xls';
$PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
return returnAjax(0,'导出成功',config('res_url').ltrim($execlpath,"."));
}
public function statisticsAjax(){
$pageSize = 10;
$page = input('page','1','trim,strip_tags');
$where = array();
$sTime = input('sTime','','trim,strip_tags');
$eTime = input('eTime','','trim,strip_tags');
if($sTime &&$eTime){
$where["create_time"] = ["between time",[$sTime,$eTime]];
}
$status = input('status','','trim,strip_tags');
if($status != ""){
$where['status'] = $status;
}
$username = input('username','','trim,strip_tags');
if($username){
$where['owner'] = $username;
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
if(!$super){
$where['owner'] = $uid;
}
$list = Db::name('sms_record')->field('sum(money) as money,count(1) as total,create_time,
					FROM_UNIXTIME(create_time,"%Y-%m-%d") days,owner')
->where($where)->group('days')->order('days desc')
->page($page,$pageSize)
->select();
$where = array();
$where["owner"] = $uid;
$count = Db::name('sms_record')
->where($where)->sum('money');
$pagecount = Db::name('sms_record')
->where($where)->group('create_time')->count(1);
$pageCount = ceil($pagecount/$pageSize);
$back = array();
$back['total'] = $count;
$back['Nowpage'] = $page;
$back['list'] = $list;
$back['page'] = $pageCount;
if($list){
return returnAjax(0,"获取数据成功",$back);
}else{
return returnAjax(1,"失败");
}
}
public function test()
{
$content = '【测试】测试';
$phone = '15914040778';
if(empty($content) ||empty($phone)){
return false;
}
$username = '15587719952';
$pwd = '15587719952';
$data = [];
$data['userid'] = 147258;
$data['timestamp'] = time();
$data['sign'] = md5($username +$pwd +$data['timestamp']);
$data['mobile'] = $phone;
$data['content'] = $content;
$data['sendTime'] = '';
$data['action'] = 'send';
$data['extno'] = '';
$url = "http://www.lcqxt.com/Index.aspx";
$param = http_build_query(
$data
);
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_HEADER,0);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,$param);
$result = curl_exec($ch);
curl_close($ch);
return $result;
}
public function audit_record(){
return $this->fetch();
}
public function get_audit_record()
{
$start_create_time = input('start_create_time','','trim,strip_tags');
$end_create_time = input('end_create_time','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$page = input('page',1,'trim,strip_tags');
$limit = input('limit',10,'trim,strip_tags');
$user_auth = session('user_auth');
$where = [];
$where_like = [];
$where['sc.relation_member_id'] = ['=',$user_auth['uid']];
$where['st.status'] = ['>=',1];
if(!empty($start_create_time) &&!empty($end_create_time)){
$where['st.create_time'] = ['between time',[strtotime($start_create_time),strtotime($end_create_time)]];
}else{
if(!empty($start_create_time)){
$where['st.create_time'] = ['>=',strtotime($start_create_time)];
}else if(!empty($end_create_time)){
$where['st.create_time'] = ['<=',strtotime($end_create_time)];
}
}
if($status != ''){
$where['st.status'] = $status;
}
if(!empty($keyword)){
$where_like['st.content'] = ['like','%'.$keyword.'%'];
$where_like['ss.name'] = ['like','%'.$keyword.'%'];
}
if(!empty($username)){
$where['a.username'] = ['like','%'.$username.'%'];
}
$datas = Db::name('sms_template')
->alias('st')
->join('sms_channel sc','st.channel_id = sc.id','LEFT')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->join('admin a','a.id = st.owner','LEFT')
->join('admin relation_a','relation_a.id = sc.relation_member_id','LEFT')
->field('a.username, sc.name as channel_name, st.id as template_id, ss.name as sign_name, st.content, st.create_time, st.status, relation_a.username as auditing_username');
$count = Db::name('sms_template')
->alias('st')
->join('sms_channel sc','st.channel_id = sc.id','LEFT')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->join('admin a','a.id = st.owner','LEFT')
->join('admin relation_a','relation_a.id = sc.relation_member_id','LEFT');
if(count($where_like) >0){
$i = 0;
foreach($where_like as $key=>$value){
if($i != 0){
$wheres = $where;
$datas = $datas->whereOr([$key=>$value])->where($where);
$count = $count->whereOr([$key=>$value])->where($where);
}else{
$wheres = $where;
$wheres[$key] = $value;
$datas = $datas->where($wheres);
$count = $count->where($wheres);
}
$i++;
}
$datas = $datas->order('st.create_time desc')->page($page,$limit)->select();
$count = $count->count('st.id');
}else{
$datas = $datas->where($where)->page($page,$limit)->order('st.create_time desc')->select();
$count = $count->where($where)->count('st.id');
}
\think\Log::record('审核记录查询');
$status_names = [
'未提交审核',
'待审核',
'审核未通过',
'审核通过',
'管理员审核未通过'
];
$i = ($page -1) * $limit +1;
foreach($datas as $key=>$value){
$datas[$key]['status_name'] = $status_names[$value['status']];
$datas[$key]['key'] = $i;
$datas[$key]['create_time'] = date('Y-m-d',$value['create_time']);
$datas[$key]['sign_name'] = $value['sign_name'] == ''?'暂未设置':$value['sign_name'];
$i++;
}
$result = [
'datas'=>$datas,
'count'=>$count,
];
return $this->Json(0,'成功',$result);
}
public function get_sms_auditing_record()
{
$template_id = input('template_id/d','','trim,strip_tags');
$datas = Db::name('sms_auditing_record')
->alias('sar')
->join('admin a','a.id = sar.member_id','LEFT')
->field('sar.status,a.username,sar.create_time,sar.note')
->where([
'type'=>'短信模板',
'relation_id'=>$template_id
])
->select();
$status_names = [
'未提交审核',
'待审核',
'审核未通过',
'审核通过',
'管理员审核未通过'
];
foreach($datas as $key=>$value){
$datas[$key]['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
$datas[$key]['status'] = $status_names[$value['status']];
}
return $this->Json(0,'成功',$datas);
}
public function get_sms_auditing_info()
{
$template_id = input('template_id','','trim,strip_tags');
if(empty($template_id)){
return $this->Json(2,'参数错误');
}
$data = Db::name('sms_template')
->alias('st')
->join('sms_channel sc','st.channel_id = sc.id','LEFT')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->join('admin a','a.id = st.owner','LEFT')
->field('st.id,a.username,sc.name as channel_name,ss.name as sign_name,st.content,st.status')
->where('st.id',$template_id)
->find();
$status_names = [
'未提交审核',
'待审核',
'审核未通过',
'审核通过',
'管理员审核未通过'
];
return $this->Json(0,'成功',$data);
}
public function submit_auditing_result()
{
$id = input('id','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$note = input('note','','trim,strip_tags');
if(empty($id)){
return $this->Json(2,'提交失败');
}
if(empty($status)){
return $this->Json(3,'请选择审核结果');
}
$user_auth = session('user_auth');
$data = [
'member_id'=>$user_auth['uid'],
'type'=>'短信模板',
'relation_id'=>$id,
'status'=>$status,
'create_time'=>time(),
'note'=>$note
];
Db::startTrans();
try {
$result = Db::name('sms_auditing_record')->insert($data);
if($result >0){
$status_update = Db::name('sms_template')
->where('id',$id)
->update([
'status'=>$status
]);
if($status == 3){
$sign_id = Db::name('sms_template')
->where('id',$id)
->value('sign_id');
$sign_data = Db::name('sms_sign')
->where('id',$sign_id)
->find();
if($sign_data['status'] != 3){
$update_sign_status = Db::name('sms_sign')
->where('id',$sign_id)
->update([
'status'=>$status
]);
$data = [
'member_id'=>$user_auth['uid'],
'type'=>'短信签名',
'relation_id'=>$sign_id,
'status'=>$status,
'create_time'=>time(),
'note'=>$note
];
$insert_result = Db::name('sms_auditing_record')
->insert($data);
}
}
}
Db::commit();
return $this->Json(0,'提交成功');
}catch(\Exception $e) {
Db::rollback();
return $this->Json(0,'提交失败');
}
}
public function get_signature_record()
{
$start_create_time = input('start_create_time','','trim,strip_tags');
$end_create_time = input('end_create_time','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$page = input('page',1,'trim,strip_tags');
$limit = input('limit',10,'trim,strip_tags');
$user_auth = session('user_auth');
$where = [];
$where_like = [];
$where['sc.relation_member_id'] = ['=',$user_auth['uid']];
if(!empty($start_create_time) &&!empty($end_create_time)){
$where['st.create_time'] = ['between time',[strtotime($start_create_time),strtotime($end_create_time)]];
}else{
if(!empty($start_create_time)){
$where['st.create_time'] = ['>=',strtotime($start_create_time)];
}else if(!empty($end_create_time)){
$where['st.create_time'] = ['<=',strtotime($end_create_time)];
}
}
if($status != ''){
$where['st.status'] = $status;
}
if(!empty($keyword)){
$where['ss.name'] = ['like','%'.$keyword.'%'];
}
if(!empty($username)){
$where['a.username'] = ['like','%'.$username.'%'];
}
$datas = Db::name('sms_template')
->alias('st')
->join('sms_channel sc','st.channel_id = sc.id','LEFT')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->join('admin a','a.id = st.owner','LEFT')
->join('admin relation_a','relation_a.id = sc.relation_member_id','LEFT')
->field('a.username, sc.name as channel_name, st.id as template_id, ss.name as sign_name, st.content, st.create_time, st.status, relation_a.username as auditing_username')
->where($where)->order('st.create_time desc')
->page($page,$limit)
->select();
$count = Db::name('sms_template')
->alias('st')
->join('sms_channel sc','st.channel_id = sc.id','LEFT')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->join('admin a','a.id = st.owner','LEFT')
->join('admin relation_a','relation_a.id = sc.relation_member_id','LEFT')
->where($where)
->count('st.id');
\think\Log::record('审核记录查询');
$status_names = [
'未提交审核',
'待审核',
'审核未通过',
'审核通过',
'管理员审核未通过'
];
$i = ($page -1) * $limit +1;
foreach($datas as $key=>$value){
$datas[$key]['status_name'] = $status_names[$value['status']];
$datas[$key]['key'] = $i;
$datas[$key]['create_time'] = date('Y-m-d',$value['create_time']);
$datas[$key]['sign_name'] =  $value['sign_name'] == ''?'签名已删除': $value['sign_name'] ;
$i++;
}
$result = [
'datas'=>$datas,
'count'=>$count,
];
return $this->Json(0,'成功',$result);
}
public function signature_verification(){
return $this->fetch();
}
public function template_audit(){
return $this->fetch();
}
public function get_find_users()
{
$user_auth = session('user_auth');
$where = [];
$where['a.status'] = 1;
$where['a.pid'] = $user_auth['uid'];
$where['a.role_id'] = ['<',20];
$role_name = input('role_name','','trim,strip_tags');
if(!empty($role_name)){
$where['ar.name'] = ['like','%'.$role_name.'%'];
}
$user_id = input('user_id','','trim,strip_tags');
if(!empty($user_id)){
$where['a.id'] = $user_id;
}else{
$user_name = input('username','','trim,strip_tags');
if(!empty($user_name)){
$where['a.username'] = ['like','%'.$user_name.'%'];
}
}
$count = input('count','','trim,strip_tags');
if(!empty($count)){
$page = ceil($count / 15) +1;
}else{
$page = 1;
}
$where['role_id'] = array('neq',20);
$find_users = Db::name('admin')
->field('a.*,ar.name as role_name')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where($where)
->page($page,15)
->select();
return $this->Json(0,'成功',$find_users);
}
public function get_sms_statistical_data(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
if(empty($page)){
$page = 1;
}
$limit = input('limit','','trim,strip_tags');
if(empty($limit)){
$limit = 10;
}
$smsname = input('smsname','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$AdminData = new AdminData();
$where = [];
$where['sms.member_id'] = $uid;
if($smsname == '0'){
$smsname = '';
}
if(!empty($smsname)){
$where['c.name'] = $smsname;
}
if($username == '0'){
$username = '';
}
if(!empty($username)){
$where['a.username'] = ['like','%'.$username.'%'];
}
$statistical_data = Db::name('sms_charging_statistics')
->alias('sms')
->field('sms.*,c.name as smsname,c.pid as sms_pid,a.username')
->join('sms_channel c','sms.sms_id = c.id','LEFT')
->join('admin a','sms.find_member_id = a.id','LEFT')
->where($where)
->page($page,$limit)
->order('date desc')
->select();
$count = Db::name('sms_charging_statistics')
->alias('sms')
->field('sms.*,c.name as smsname,c.pid as sms_pid,a.username')
->join('sms_channel c','sms.sms_id = c.id','LEFT')
->join('admin a','sms.find_member_id = a.id','LEFT')
->where($where)
->count();
\think\Log::record('短信计费统计查询');
foreach ($statistical_data as $key =>$value) {
if(empty($value['sms_pid'])){
$statistical_data[$key]['source_name'] = '自有通道';
}else{
$sms_ppid[$key] = Db::name('sms_channel')->where('id',$value['sms_pid'])->value('pid');
if(empty($sms_ppid[$key])){
$statistical_data[$key]['source_name'] = '自有通道';
}else{
$source[$key] = Db::name('sms_channel')
->alias('c')
->field('c.name,a.username')
->join('admin a','c.owner = a.id','LEFT')
->where('c.id',$sms_ppid[$key])
->find();
$statistical_data[$key]['source_name'] = $source[$key]['username'].$source[$key]['name'];
}
}
$statistical_data[$key]['key'] = ($page-1)*$limit+($key+1);
$statistical_data[$key]['usertype'] = $AdminData->get_role_name($value['find_member_id']);
}
$sum_sms_cnt =  Db::name('sms_charging_statistics')
->alias('sms')
->field('sms.*,c.name as smsname,c.pid as sms_pid,a.username')
->join('sms_channel c','sms.sms_id = c.id','LEFT')
->join('admin a','sms.find_member_id = a.id','LEFT')
->where($where)
->sum('sms_cnt');
if(empty($sum_sms_cnt)){
$sum_sms_cnt = 0;
}
$sum_cost_price_statistics = Db::name('sms_charging_statistics')
->alias('sms')
->field('sms.*,c.name as smsname,c.pid as sms_pid,a.username')
->join('sms_channel c','sms.sms_id = c.id','LEFT')
->join('admin a','sms.find_member_id = a.id','LEFT')
->where($where)
->sum('cost_price_statistics');
if(empty($sum_cost_price_statistics)){
$sum_cost_price_statistics = 0;
}
$sum_sale_price_statistics = Db::name('sms_charging_statistics')
->alias('sms')
->field('sms.*,c.name as smsname,c.pid as sms_pid,a.username')
->join('sms_channel c','sms.sms_id = c.id','LEFT')
->join('admin a','sms.find_member_id = a.id','LEFT')
->where($where)
->sum('sale_price_statistics');
if(empty($sum_sale_price_statistics)){
$sum_sale_price_statistics = 0;
}
$sum_profit = Db::name('sms_charging_statistics')
->alias('sms')
->field('sms.*,c.name as smsname,c.pid as sms_pid,a.username')
->join('sms_channel c','sms.sms_id = c.id','LEFT')
->join('admin a','sms.find_member_id = a.id','LEFT')
->where($where)
->sum('profit');
if(empty($sum_profit)){
$sum_profit = 0;
}
$sum_info['sum_sms_cnt'] = $sum_sms_cnt;
$sum_info['sum_cost_price_statistics'] = round($sum_cost_price_statistics,3);
$sum_info['sum_sale_price_statistics'] = round($sum_sale_price_statistics,3);
$sum_info['sum_profit'] = round($sum_profit,3);
$sum_info['limit'] = $limit;
$sum_info['total'] = $count;
$sum_info['Nowpage'] = $page;
$sum_info['page_count']=ceil($count/$limit);
return returnAjax(0,'success',['list'=>$statistical_data,'count'=>$count,'sum_info'=>$sum_info]);
}
public function smschannel(){
if(request()->isPost()){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$name = input('name','','trim,strip_tags');
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$limit){
$page = 10;
}
$where = [];
$where['ch.owner'] = array('eq',$uid);
if($name){
$where['ch.name'] = array('like','%'.$name.'%');
}
$list = Db::name('sms_channel')
->alias('ch')
->join('sms_channel el ','el.id = ch.pid','LEFT')
->join('admin a ','el.owner = a.id','LEFT')
->where($where)
->field('ch.*,a.username')
->select();
foreach($list as $key =>$vo){
$list[$key]['create_time'] = date("Y-m-d H:i:s",$vo['create_time']);
$list[$key]['price'] = round($vo['price'],3);
}
$count = Db::name('sms_channel')->alias('ch')->where($where)->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取成功',$data);
}
return $this->fetch();
}
public function smssend(){
return $this->fetch();
}
public function get_msm_path(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = [];
$data['template'] =  Db::name('sms_template')->where(['owner'=>$uid,'status'=>3])->select();
$channel  =  Db::name('sms_channel')->where('owner',$uid)->select();
foreach($channel as $key =>$vo){
if($vo['type'] != '爱讯短信'){
continue;
}else{
$data['channel'][] = $vo;
}
}
return returnAjax(1,'',$data);
}
public function whether_can_send_sms($sms_channel_id,$send_sms_length)
{
if(empty($sms_channel_id)){
return false;
}
if(empty($send_sms_length)){
return true;
}
$user_and_sms_data = Db::name('sms_channel')
->alias('sc')
->join('admin a','a.id = sc.owner','LEFT')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('sc.id',$sms_channel_id)
->field('sc.id, sc.pid as sms_channel_pid, sc.price, a.username, a.money, a.is_jizhang, a.credit_line, ar.name as role_name')
->find();
if($user_and_sms_data['money'] +$user_and_sms_data['credit_line'] <($user_and_sms_data['price'] * $send_sms_length) &&$user_and_sms_data['is_jizhang'] == 0){
return $user_and_sms_data['username'];
}
$is_jizhang = $user_and_sms_data['is_jizhang'];
while(!empty($user_and_sms_data['sms_channel_pid'])){
$user_and_sms_data = Db::name('sms_channel')
->alias('sc')
->join('admin a','a.id = sc.owner','LEFT')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('sc.id',$user_and_sms_data['sms_channel_pid'])
->field('sc.id, sc.pid as sms_channel_pid, sc.price, a.username, a.money, a.is_jizhang, a.credit_line, ar.name as role_name')
->find();
if($user_and_sms_data['role_name'] == '商家'&&$is_jizhang == 1 ||$user_and_sms_data['role_name'] != '商家'){
if($user_and_sms_data['money'] +$user_and_sms_data['credit_line'] <($user_and_sms_data['price'] * $send_sms_length)){
return $user_and_sms_data['username'];
}
}
}
return true;
}
public function sending_msm(){
set_time_limit(60*60*16);
ini_set('memory_limit','256M');
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES ['excel']['tmp_name'];
}else{
return returnAjax(1,'上传失败,请下载模板，填好再选择文件上传。');
}
$file_types = explode(".",$_FILES['excel']['name']);
$file_type = $file_types [count ( $file_types ) -1];
$savePath = './uploads/Excel/';
if (!is_dir($savePath)){
mkdir($savePath,0777,true);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy($tmp_file,$savePath .$file_name)){
return returnAjax(1,'上传失败');
}
$foo = new \PHPExcel_Reader_Excel2007();
$extension = strtolower( pathinfo($file_name,PATHINFO_EXTENSION) );
if($extension == 'xlsx') {
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}else{
$inputFileType = 'Excel5';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$objPHPExcel = $objReader->load($savePath.$file_name,$encode = 'utf-8');
$excelArr = $objPHPExcel->getsheet(0)->toArray();
if (count($excelArr) <2){
return returnAjax(1,'导入的文件没有数据!');
}
unset($excelArr[0]);
foreach($excelArr as $key=>$value){
if(empty($value[1])){
unset($excelArr[$key]);
}
}
$countSum = count($excelArr);
$number_count = $countSum;
$chaos_num = input('chaos_num','','trim,strip_tags');
$number = [];
foreach($excelArr as $key=>$value){
if(!isset($number[$value[1]])  &&!empty($value[1])){
$number[$value[1]] = 1;
}elseif(!empty($value[1])){
unset($excelArr[$key]);
}
}
$Identification = rand_string(12,'',time());
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$channel = input('channel','','trim,strip_tags');
$template = input('template','','trim,strip_tags');
$data = array();
$data['create_time'] = time();
$phones = array();
$phone_data = array();
$length_num = 0;
$number_count = count($excelArr);
foreach($excelArr as $k =>$v){
$isMob="/^(13[0-9]|14[579]|15[0-3,5-9]|16[6]|17[0135678]|18[0-9]|19[289])\d{8}$/";
$isTel="/^([0-9]{3,4}-)?[0-9]{7,8}$/";
$phone = trim(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$v[1]));
if(preg_match($isMob,$phone)){
if(!empty($phone)){
if($length_num <10000){
array_push($phones,$phone);
$length_num ++;
}else{
break;
}
}
}
$successCnt = 0;
}
if(count($phones) >0){
$data['phone'] = implode(',',$phones);
$content = '';
$sms_data = Db::name('sms_channel')->where('id',$channel)->find();
$sms_template = Db::name('sms_template')
->alias('te')
->join('sms_sign si','te.sign_id = si.id','LEFT')
->field('te.*,si.name as sign_name')
->where('te.id',$template)
->find();
$url = $sms_data['url'];
if($sms_data['type'] == "爱讯短信"){
$password = md5($sms_data['user_id'].md5($sms_data['password']));
$vars = explode(',',$sms_template['variable']);
$content = "【".$sms_template['sign_name']."】".str_replace('[变量]',$vars[$sms_template['variable_i'] %count($vars)],$sms_template['content']);
$param = http_build_query(
array(
'username'=>$sms_data['user_id'],
'password'=>$password,
'mobile'=>$data['phone'],
'content'=>$content,));
$length = mb_strlen($content);
$count = ceil($length / 70);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$phone_number = count($phones);
$estimate_price = $count * $phone_number * $sms_data['price'];
$admin_info = DB::name('admin')->where('id',$uid)->find();
$result = $this->whether_can_send_sms($channel,($count * $phone_number));
if($result == false ||$result === $user_auth['username']){
return returnAjax(0,'您的余额不足，不支持发送所有短信，请充值后再发送！');
}else if($result != $user_auth['username'] &&$result != true){
return returnAjax(0,'用户名为:"'.$result.'"的余额不足，请联系上级用户！');
}
$is_OK = $this->sms_charging($count,$phone_number,$sms_data['price'],$channel,$phones,$chaos_num);
$redis = RedisConnect::get_redis_connect();
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_HEADER,0);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,$param);
$result = curl_exec($ch);
curl_close($ch);
if ($result <= 0) {
$count = 0;
}else {
$length = mb_strlen($content);
$count = ceil($length / 70);
$today = strtotime(date('Y-m-d'));
foreach($is_OK['money'] as $find_key=>$find_value){
if(!empty($find_value)){
$user_data = Db::name('admin')
->alias('a')
->join('admin_role ar','ar.id = a.role_id','LEFT')
->where('a.id',$find_key)
->field('a.is_jizhang, a.pid, ar.name as role_name')
->find();
if($user_data['is_jizhang'] == 1 &&$user_data['role_name'] == '销售人员'){
}else{
$new_admin = DB::name('admin')
->where('id',$find_key)
->setDec('money',$find_value);
}
$msm_price_key = "incr_owner_".$find_key."_".$today."_sms_money";
$redis->INCRBYFLOAT($msm_price_key,$find_value);
$incr_key_money = "incr_owner_".$find_key."_".$today."_money";
$redis->INCRBYFLOAT($incr_key_money,$find_value);
}
}
foreach($is_OK['sms_count'] as $find_key=>$find_value){
$msm_num_key = "incr_owner_".$find_key."_".$today."_sms_count";
$redis->incrby($msm_num_key,$find_value);
}
$redis_key = 'tel_order_list';
foreach($is_OK['tel_order_datas'] as $find_key=>$find_value){
$redis->lpush($redis_key,json_encode($find_value));
}
$result = '1968563197467';
}
$data['owner'] = $uid;
$data['channel'] = $channel;
$data['template'] = $template;
$data['phone_count'] = count($phones);
$data['content'] = $content;
$data['ok_time'] = time();
$data['is_state'] = $count >0 ?1 : 0;
$data['sms_order'] = $result;
$res = Db::name('sending_msm')->insert($data);
$msg = $count >0 ?'成功': '失败';
if($res){
$msg = '发送完毕，短信添加成功，短信发送：'.$msg;
return returnAjax(1,$msg);
}else{
$msg = '发送完毕，短信添加失败，请联系管理员，短信发送：'.$msg;
return returnAjax(0,$msg);
}
}else{
return returnAjax(0,'文件数据过滤后为空');
}
}
}
protected function sms_charging($msm_length ,$phone_number ,$d_price ,$channel ,$phones ,$chaos_num){
$redis = RedisConnect::get_redis_connect();
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$sms_datas = [];
$user_data = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('a.id',$uid)
->field('ar.name as role_name, a.is_jizhang')
->find();
$current_role_name = $user_data['role_name'];
$is_jizhang = $user_data['is_jizhang'];
$today = strtotime(date('Y-m-d'));
$sms_channel = DB::name('sms_channel')
->alias('sc')
->join('admin a','sc.owner = a.id','LEFT')
->join('admin_role ar','ar.id = a.role_id','LEFT')
->where('sc.id',$channel)
->field('sc.*, a.pid as user_pid, ar.name as role_name')
->find();
$sms_datas[] = $sms_channel;
while(!empty($sms_channel['pid'])){
$sms_channel = DB::name('sms_channel')
->alias('sc')
->join('admin a','sc.owner = a.id','LEFT')
->join('admin_role ar','ar.id = a.role_id','LEFT')
->where('sc.id',$sms_channel['pid'])
->field('sc.*, a.pid as user_pid, ar.name as role_name')
->find();
$sms_datas[] = $sms_channel;
}
$data_zong_length = count($phones) * count($sms_datas);
$key = 'task_'.$chaos_num .'_count';
$redis->set($key,$data_zong_length);
$complete_num = 0;
$result = [
'tel_order_datas'=>[],
'money'=>[],
'sms_count'=>[],
];
foreach($phones as $key=>$value){
$sms_channel_find_id = 0;
$find_member_id = $sms_datas[0]['owner'];
foreach($sms_datas as $find_key=>$find_value){
$data = [];
$data['owner'] = $find_value['owner'];
$data['member_id'] = $find_member_id;
$find_member_id = $find_value['owner'];
if($current_role_name == '销售人员'&&$find_value['role_name'] == '商家'&&$is_jizhang == 0 ||empty($find_value['pid'])){
$data['money'] = 0;
$data['sms_money'] = 0;
$data['sms_count'] = 0;
$data['is_jizhang'] = 0;
}else if($current_role_name == '销售人员'&&$find_value['role_name'] == '商家'&&$is_jizhang == 1){
$data['sms_count'] = $msm_length;
$data['money'] = $msm_length * $find_value['price'];
$data['sms_money'] = $msm_length * $find_value['price'];
$data['is_jizhang'] = 1;
}else{
if($is_jizhang == 1 &&$find_value['role_name'] == '销售人员'){
$data['is_jizhang'] = 1;
}else{
$data['is_jizhang'] = 0;
}
$data['sms_count'] = $msm_length;
$data['money'] = $msm_length * $find_value['price'];
$data['sms_money'] = $msm_length * $find_value['price'];
}
$data['create_time'] = time();
$data['sms_channel_id'] = $find_value['id'];
$data['sms_channel_find_id'] = $sms_channel_find_id;
$sms_channel_find_id = $find_value['id'];
$data['sms_price'] = $find_value['price'];
$data['end_time'] = time();
$data['note'] = '短信群发';
$data['type'] = 2;
$result['tel_order_datas'][$key][] = $data;
if(isset($result['sms_count'][$find_value['owner']]) == false){
$result['sms_count'][$find_value['owner']] = 0;
}
$result['sms_count'][$find_value['owner']] += $data['sms_count'];
if(isset($result['money'][$find_value['owner']]) == false){
$result['money'][$find_value['owner']] = 0;
}
$result['money'][$find_value['owner']] += $data['money'];
$result['money'][$find_value['owner']] = sprintf("%.2f",$result['money'][$find_value['owner']]);
$complete_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$redis->set($complete_key,$complete_num);
}
}
$redis->del($complete_key);
$redis->del($key);
return $result;
}
public function ajax_smssend_list(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$page = input('page','','strip_tags');
$limit = input('limit','','strip_tags');
$startTime = input('startTime','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$limit){
$limit = 10;
}
$where = [];
$where['se.owner'] = array('eq',$uid);
if($startTime &&$endTime){
$where['se.create_time'] = array('between time',[$startTime,$endTime]);
}
$list = Db::name('sending_msm')
->alias('se')
->join('sms_channel ch','se.channel = ch.id','LEFT')
->join('sms_template te','se.template = te.id','LEFT')
->field('se.*,ch.name as channel_name, te.name as template_name')
->where($where)
->order('id','desc')
->page($page,$limit)
->select();
foreach($list as $key=>$vo){
$list[$key]['create_time'] = date("Y-m-d H:i:s",$vo['create_time']);
$list[$key]['ok_time'] = date("Y-m-d H:i:s",$vo['ok_time']);
$list[$key]['is_state'] = $vo['is_state'] == 1 ?'成功':'失败';
}
$count = Db::name('sending_msm')
->alias('se')
->where($where)
->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function smsdetail(){
if(request()->isPost()){
$id = input('id','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$limit){
$limit = 10;
}
$where = [];
$where['se.id'] = array('eq',$id);
$sending_msm_info = Db::name('sending_msm')
->alias('se')
->join('sms_channel ch','se.channel = ch.id','LEFT')
->join('sms_template te','se.template = te.id','LEFT')
->field('se.*,ch.name as channel_name, te.name as template_name')
->where($where)
->find();
$arr = explode(',',$sending_msm_info['phone']);
$list_phone = [];
if($phone){
foreach($arr as $key=>$values ){
if (strstr( $values ,$phone ) !== false ){
array_push($list_phone,$values);
}
}
}else{
$list_phone = $arr;
}
$count = count($list_phone);
$start=($page-1)*$limit;
$list_phone = array_slice($list_phone,$start,$limit);
$list = [];
foreach($list_phone as $key =>$vo){
$list[$key]['channel'] = $sending_msm_info['channel_name'];
$list[$key]['template'] = $sending_msm_info['template_name'];
$list[$key]['phone'] = $vo;
$list[$key]['is_state'] = $sending_msm_info['is_state'] == 1 ?'发送成功':'发送失败';
$list[$key]['ok_time'] = date("Y-m-d H:i:s",$sending_msm_info['ok_time']);
}
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
return $this->fetch();
}
}
