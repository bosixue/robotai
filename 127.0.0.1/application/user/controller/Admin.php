<?php 

namespace app\user\controller;
use \think\Db;
use \think\Controller;
class Admin extends Controller{
public function index(){
}
public function edit_accounts(){
if(request()->isPost()){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = [];
$id = input('id','','trim,strip_tags');
$data['username'] = input('username','','trim,strip_tags');
$role_id = input('role_id','','trim,strip_tags');
if($role_id){
$data['role_id'] = $role_id;
}else{
$data['role_id'] = 19;
}
$data['mobile'] = input('mobile','','trim,strip_tags');
$data['spare_mobile'] = input('spare_mobile','','trim,strip_tags');
$pwd = input('password','','trim,strip_tags');
$data['money'] = input('money','','trim,strip_tags');
$data['robot_cnt'] = input('robot_cnt','','trim,strip_tags');
$data['robot_date'] = input('robotr_date','','trim,strip_tags');
$data['month_price'] = input('month_price','','trim,strip_tags');
$data['remark'] = input('remark','','trim,strip_tags');
$data['pid'] = $uid;
$data['create_time']  = time();
$object['owner'] = $uid;
$object['operation_type'] = 5 ;
$object['operation_fu'] = '编辑账号';
$object['operation_date'] = time();
$object['remark'] = $data['remark'];
if($id){
$where['id'] = array('eq',$id);
$object['user_id'] = $id;
$object['record_content'] = '修改账号';
if($data['password']){
$data['password'] = $pwd;
}
Db::startTrans();try {
Db::name('admin')->where($where)->update($data);
Db::name('operation_record')->insert($object);
Db::commit();
return returnAjax(0,'修改账号成功');
}
catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'修改账号失败');
}
}else{
$object['record_content'] = '添加账号';
$data['password'] = $pwd;
Db::startTrans();try {
Db::name('admin')->insert($data);
$object['user_id'] = Db::name('user')->getLastInsID();
Db::name('operation_record')->insert($object);
Db::commit();
return returnAjax(0,'添加账号成功');
}
catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'添加账号失败');
}
}
}
}
public function ajax_sale_account(){
$sale_name = input('name','','trim,strip_tags');
if($sale_name){
$where['username'] = array('eq',$sale_name);
}
$page_size = input('page_size','','trim,strip_tags');
$page = input('page','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where['pid'] = array('eq',$uid);
$list = Db::name('admin')->where($where)->page($page,$Page_size)->select();
$count = count($list);
$page_count = ceil($count/$Page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(1,'获取数据成功',$data);
}
public function open_close_state(){
$type = input('type','','trim,strip_tags');
$alt = input('alt','','trim,strip_tags');
$arr = input('arr','','trim,strip_tags');
$user_name = input('keyword','','trim,strip_tags');
$open['status'] = array('eq',1);
$close['status'] = array('eq',0);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where['pid'] = array('eq',$uid);
if($alt == 0){
if($user_name){
$where['username'] = array('eq',$user_name);
}
if($type == 1){
$res = Db::name('admin')->where($where)->update($open);
}else{
$where['id']  = array('in',$arr);
$res = Db::name('admin')->where($where)->update($open);
}
if($res){
return returnAjax(0,'开启账号成功');
}
}else if($alt == 1){
if($user_name){
$where['username'] = array('eq',$user_name);
}
if($type == 1){
$res = Db::name('admin')->where($where)->update($close);
}else{
$where['id']  = array('in',$arr);
$res = Db::name('admin')->where($where)->update($close);
}
if($res){
return returnAjax(0,'锁定账号成功');
}
}
}
public function ajax_sale_account_recharge(){
$user_name = input('username','','trim,strip_tags');
$start_date = input('start_date','','trim,strip_tags');
$end_date = input('end_date','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
$page_size = input('page_size','','trim,strip_tags');
$where = array();
$where_a = array();
if($user_name){
$where_a['username'] = array('like',"%".$sotitle."%");
}
$a_id = Db::name('admin')->where($where_a)->column('id');
if($a_id){
$where['recharge_member_id'] =array('in',$a_id);
}
if($start_date !=''&&$end_date !=''){
$where['create_time'] = array(array('ge',$start_date),array('le',$end_date),'and');
}
$list = Db::name('tel_deposit')->where($where)->page($page,$page_size)->select();
foreach ($list as $key =>$value) {
$where_a['user_id'] = array('eq',$value['id']);
$where_a['operation_type'] = array('eq',1);
$list[$key]['remark'] = Db::name('operation_record')->where($where_a)->order('operation_date','desc')->value('remark');
}
$count = count($list);
$page_count = ceil($count/$page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(1,'获取数据成功',$data);
}
public function sale_record(){
if(request()->isPost()){
$data = [];
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$user_id= input('user_id','','trim,strip_tags');
$data['menoy'] = floatval(input('menoy','','trim,strip_tags'));
$defore_balance = Db::name('tel_deposit')->where('recharge_member_id',$user_id)->value('balance');
$data['balance'] = $defore_balance +$data['menoy'];
$data['create_time'] = time();
$data['remak'] = input('remak','','trim,strip_tags');
if( (0 -$data['menoy']) >0 ){
$rut['operation_fu'] = '扣除金额';
}else if((0 -$data['menoy']) <= 0) {
$rut['operation_fu'] = '充值金额';
}
$s_menoy = 0 ;
if($uid != 12){
$s_menoy = Db::name('admin')->where('id',$uid)->value('money') -$data['menoy'];
}
$rut['owner'] = $uid ;
$rut['user_id'] = $user_id;
$rut['operation_type'] = 1 ;
$rut['record_content'] = $data['menoy'];
$rut['operation_date'] = time();
$rut['remark'] = $data['remark'];
Db::startTrans();
try {
$res = Db::name('operation_record')->insert($rut);
if($res>0 ){
Db::name('tel_deposit')->where('recharge_member_id',$user_id)->update($data);
Db::name('admin')->where('id',$uid)->update(['money'=>$s_menoy]);
}
Db::commit();
return returnAjax(0,'充值成功');
}catch (\Exception $e) {
Db::rollback();
return returnAjax(0,'充值失败');
}
}
}
public function ajax_robot_management(){
$user_name = input('username','','trim,strip_tags');
if($user_name){
$where['username'] = array('eq',$user_name);
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where['pid'] = array('eq',$uid);
$page = input('page','','trim,strip_tags');
$Page_size = input('page_size','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$Page_size){
$Page_size = 10;
}
$list = Db::name('admin')->where($where)->page($page,$Page_size)->select();
foreach ($list as $key =>$value) {
$where_a['user_id'] = array('eq',$value['id']);
$where_a['operation_type'] = array('eq',2);
$list[$key]['remark'] = Db::name('operation_record')->where($where_a)->order('operation_date','desc')->value('remark');
}
$count = count($list);
$page_count = ceil($count/$Page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(1,'获取数据成功',$data);
}
public function distribution_robot(){
$data = [];
$id = input('id','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$robot_num = floatval(input('robot_num','','trim,strip_tags'));
$remark = input('remark','','trim,strip_tags');
if( (0 -$robot_num) >0 ){
$data['operation_fu'] = '回收机器人';
}else if((0 -$robot_num) <= 0) {
$data['operation_fu'] = '分配机器人';
}
$data['owner'] = $uid;
$data['user_id'] = $id;
$data['operation_type'] = 2;
$data['record_content'] = $robot_num ;
$data['operation_date'] = time();
$data['remark'] = $remark;
Db::startTrans();
try{
$res = Db::name('operation_record')->insert($data);
if($res >0){
$where['id'] = array('eq',$uid);
$usable_robot_cnt = Db::name('admin')->where($where)->value('usable_robot_cnt');
$w_rot_rnumber = $usable_robot_cnt -$robot_num;
$res1 = Db::name('admin')->where($where)->update(['usable_robot_cnt'=>$w_rot_rnumber]);
if($res1 >0 ){
$info = DB::name('admin')->where('')->find();
if($uid !=12){
$num['robot_cnt']= $info['robot_cnt'] +$robot_num;
$num['usable_robot_cnt'] = $info['usable_robot_cnt'] +$robot_num;
}else{
$num['robot_cnt'] = 0 ;
$num['usable_robot_cnt'] = 0 ;
}
Db::name('admin')->where('id',$id)->update($num);
}
}
Db::commit();
return returnAjax(1,'分配成功');
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'分配失败');
}
}
public function force_recovery(){
$id = input('id','','trim,strip_tags');
$num['robot_cnt'] = 0;
$num['usable_robot_cnt'] = 0;
$find_users = Db::name('admin')->where('pid',$id)->select();
Db::startTrans();
try {
while(count($find_users) >0){
$ids = [];
foreach($find_users as $key=>$value){
$ids[] = $value['id'];
Db::name('admin')->where('id',$value['id'])->update($num);
}
$find_users = Db::name('admin')->where('pid','in',$ids)->select();
}
$robot_cnt= Db::name('admin')->where('id',$id)->value('robot_cnt');
Db::name('admin')->where('id',$id)->update(['usable_robot_cnt'=>$robot_cnt]);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data['owner'] = $uid;
$data['user_id'] = $id;
$data['operation_type'] = 6;
$data['operation_fu'] = '回收机器人';
$data['record_content'] = '强制回收机器人';
$data['operation_date'] = time();
$data['remark'] = '一键回收当前用户的下所有机器人。';
Db::name('operation_record')->insert($data);
Db::commit();
return returnAjax(1,'强制回收成功');
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'强制回收失败');
}
}
public function ajax_tariff_management(){
$user_name = input('username','','trim,strip_tags');
if($user_name){
$where['username'] = array('eq',$user_name);
}
$page = input('page','','trim,strip_tags');
if(!$page)
$page = 1;
$page_size = input('page_size','','trim,strip_tags');
if(!$page_size)
$page_size = 10;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where['pid'] = array('eq',$uid);
$list = Db::name('admin')->where($where)->page($page,$Page_size)->select();
foreach ($list as $key =>$value) {
$where_a['user_id'] = array('eq',$value['id']);
$where_a['operation_type'] = array('eq',2);
$list[$key]['remark'] = Db::name('operation_record')->where($where_a)->order('operation_date','desc')->value('remark');
$list[$key]['count_line'] = Db::name('tel_line')->where('member_id',$value['id'])->count();
}
$count = count($list);
$page_count = ceil($count/$Page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(1,'获取数据成功',$data);
}
public function tariff_management_detail(){
$id = input('id','','trim,strip_tags');
$user = Db::name('admin')->where('id',$id)->find();
$info['month_price'] = $user['month_price'];
$info['line'] = Db::name('tel_line')->where('member_id',$id)->select();
return $info;
}
public function edit_tariff_management(){
$id = input('id','','trim,strip_tags');
}
}
