<?php // Copyright(C) 2021, All rights reserved.ts reserved.


namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use app\common\controller\AdminData;
use app\common\controller\RobotDistribution;
use app\common\controller\LinesData;
use app\common\controller\ManagerMethod;
use app\common\controller\Technology_service_cost;
use app\common\controller\OperationRecord;
class Manager extends User{
public function _initialize() {
parent::_initialize();
$request = request();
$action = $request->action();
}
public function mytest2()
{
$jump = input('jump');
$this->assign('jump',$jump);
return $this->fetch();
}
public function jumpfm()
{
$jump = input('jump');
$this->assign('jump',$jump);
return $this->fetch();
}
public function index()
{
$user_name_keyword = input('username_keyword','','trim,strip_tags');
$user_role_id = input('userrole_id','','trim,strip_tags');
$user_auth = session('user_auth');
$user_role = $this->get_user_role($user_auth['uid']);
$screens = [
'username'=>$user_name_keyword,
'user_role'=>$user_role_id
];
$where = [];
if($user_role !== '管理员'){
$find_ids = Db::name('admin')
->field('id')
->where('pid',$user_auth['uid'])
->select();
$ids = [];
$ids[] = $user_auth['uid'];
foreach($find_ids as $key=>$value){
$ids[] = $value['id'];
}
$where['pid'] = ['in',$ids];
}else{
$where['id'] = ['<>',$user_auth['uid']];
}
if(!empty($user_name_keyword)){
$where['username'] = ['like','%'.$user_name_keyword.'%'];
}
if(!empty($user_role_id)){
$where['role_id'] = $user_role_id;
}
$list = Db::name('admin')
->where($where)
->order('id','desc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$list['data'][$k]['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
$role = Db::name('admin_role')->field('name')->where('id',$v['role_id'])->find();
$list['data'][$k]['role_name'] = $role['name'];
$list['data'][$k]['isSuper'] = $v['super']?'是':'否';
$list['data'][$k]['p_user_name'] = Db::name('admin')
->where('id',$v['pid'])
->value('username');
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
$mlist = Db::name('admin_role')
->field('id,name')
->where('status',1)
->where('level','>',$user_auth['level'])
->where('level','<',4)
->order('id asc')
->select();
$AdminData = new AdminData();
$find_users = $AdminData->get_find_users($user_auth['uid']);
$this->assign('find_users',$find_users);
$this->assign('rolelist',$mlist);
$this->assign('date',date('Y-m-d'));
$this->assign('screens',$screens);
$this->assign('uid',$user_auth['uid']);
return $this->fetch();
}
public function service_cost(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->ajax_service_cost();
}else{
$ManagerMethod->ajax_service_cost();
}
$user_auth = session('user_auth');
if($user_auth['role'] == '管理员'){
$role_options = [
'运营商'
];
}else if($user_auth['role'] == '运营商'){
$role_options = [
'代理商',
'商家',
'销售人员'
];
}else if($user_auth['role'] == '代理商'){
$role_options = [
'商家',
'销售人员'
];
}else if($user_auth['role'] == '商家'){
$role_options = [
'销售人员'
];
}else{
$role_options = [];
}
$this->assign('role_options',$role_options);
return $this->fetch();
}
public function service_cost_statistics_api()
{
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$role_name = input('role_name','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$args = [];
if(!empty($role_name)){
$args['role_name'] = $role_name;
}
if(!empty($username)){
$args['username'] = $username;
}
$Technology_service_cost = new Technology_service_cost();
$datas = $Technology_service_cost->get_datas($page,$limit,$args);
return returnAjax(0,'成功',$datas);
}
public function service_cost_total_api()
{
$Technology_service_cost = new Technology_service_cost();
$datas = $Technology_service_cost->get_total_data();
return returnAjax(0,'成功',$datas);
}
protected function get_user_role($user_id)
{
if(empty($user_id)){
return false;
}
$role_name = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where(['a.id'=>['=',$user_id]])
->value('ar.name');
return $role_name;
}
public function sale_account(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = (int)$uid;
}
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('scenarioslist',$scenarioslist);
$this->assign('uid',$uid);
return $this->fetch();
}
public function sales(){
$keyword = input('keyword','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if ($keyword) {
$list = Db::name('admin')
->where('username like "%'.$keyword.'%"')
->where(array('pid'=>$uid,'user_type'=>1))
->order('id','desc')
->paginate(10,false,array('query'=>$this->param));
}else {
$list = Db::name('admin')
->where(array('pid'=>$uid,'user_type'=>1))
->order('id','desc')
->paginate(10,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$list['data'][$k]['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
if ($v['last_login_time']){
$list['data'][$k]['last_login_time'] = date("Y-m-d H:i:s",$v['last_login_time']);
}else{
$list['data'][$k]['last_login_time'] =  "";
}
if(!empty($v['create_time'])){
$list['data'][$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
}
if(!empty($v['expiry_date'])){
$list['data'][$k]['expiry_date'] = date('Y-m-d H:i:s',$v['expiry_date']);
}
$list['data'][$k]['num'] = Db::name('member')->where('salesman',$v['id'])->count("uid");
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function saveSale(){
if(IS_POST){
$id = input('adminId','','trim,strip_tags');
$mdata = array();
$mdata['username'] = input('username','','trim,strip_tags');
$mdata['realname'] = input('realname','','trim,strip_tags');
$mdata['mobile'] = input('mobile','','trim,strip_tags');
$mdata['sex'] = input('sex','','trim,strip_tags');
$mdata['email'] = input('email','','trim,strip_tags');
$mdata['month_price'] = input('month_price','','trim,strip_tags');
$mdata['asr_price'] = input('asr_price','','trim,strip_tags');
$mdata['credit_line'] = input('credit_line','','trim,strip_tags');
$uid = session('user_auth.uid');
$res = Db::name('admin')->field('role_id,open_tsr')->where('id',$uid)->find();
$mdata['open_tsr'] = input('open_tsr','','trim,strip_tags');
$mdata['role_id'] = 19;
if($mdata['open_tsr']){
$number = Db::name('tel_extension_number')
->field('id,extension_number')
->where('owner',0)
->where('status',0)->find();
if($number){
$mdata['extension_number'] = $number['extension_number'];
if ($id){
$exres = Db::name('admin')->field('extension_number')->where('id',$id)->find();
if(!$exres['extension_number']){
$exdata['owner'] = $id;
$exdata['status'] = 1;
$return = Db::name('tel_extension_number')->where('id',$number["id"])->update($exdata);
}
}
}else{
return returnAjax(1,'无法开通，人工坐席不足。可以选择不开通人工坐席。',"保存失败");
}
}else{
if ($id){
$exres = Db::name('admin')->field('extension_number')->where('id',$id)->find();
if($exres['extension_number']){
$mdata['extension_number'] = null;
$exdata['owner'] = 0;
$exdata['status'] = 0;
$return = Db::name('tel_extension_number')->where('extension_number',$exres['extension_number'])->update($exdata);
}
}else{
$mdata['extension_number'] = 0;
}
}
if ($id){
$mdata['update_time'] = time();
$result = Db::name('admin')->where('id',$id)->update($mdata);
}else{
$res = Db::name('admin')->field('id')->where('username',$mdata['username'])->find();
if( $res['id']){
return returnAjax(1,'该用户名已经存在。');
}
$mdata['user_type'] = 1;
$mdata['pid'] = $uid;
$password = input('password','','trim,strip_tags');
$salt = rand_string(6);
$mdata['password'] = md5($password.$salt);
$mdata['salt'] = $salt;
$mdata['create_time'] = time();
$result = Db::name('admin')->insertGetId($mdata);
if($result){
if($mdata['open_tsr']){
if(isset($number) &&$number["id"]){
$exdata['owner'] = $result;
$exdata['status'] = 1;
$return = Db::name('tel_extension_number')->where('id',$number["id"])->update($exdata);
}
}
}
}
if($result >= 0){
return returnAjax(0,'保存成功',$result);
}else{
return returnAjax(1,'error!',"保存失败");
}
}
}
public function getSale(){
$id = input('id','','trim,strip_tags');
$result = Db::name('admin')->field('username,realname,mobile,email,open_tsr,sex,remark')->where('id',$id)->find();
return returnAjax(0,'sucess',$result);
}
public function resetPwd(){
$id = input('id','','trim,strip_tags');
$salt = rand_string(6);
$password = '654321';
$mdata['salt'] = $salt;
$mdata['password'] = md5($password.$salt);
$result = Db::name('admin')->where('id',$id)->update($mdata);
if($result >= 0){
return returnAjax(0,'初始密码是：'.$password,'');
}else{
return returnAjax(1,'重置密码失败!');
}
}
public function myCustomer(){
$id = input('id','','trim,strip_tags');
$where = array();
$mobile = input('keyword','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
if($mobile){
$where["m.mobile"] = $mobile;
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if ($id){
$where["m.salesman"] = $id;
}
else{
if(!$super){
$where['m.salesman'] = $uid;
}
}
$where["m.status"] = ['>',0];
if($status != ""){
$where["m.status"] = $status;
}
$list = Db::name('member')
->field('m.uid,m.username,m.real_name,m.mobile,m.last_dial_time,m.status,m.task,m.uid,m.level')
->alias('m')
->order('m.last_dial_time desc')
->where($where)
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach($list['data'] as &$item){
if ($item['last_dial_time'] >0){
$item['last_dial_time'] = date('Y-m-d H:i:s',$item['last_dial_time']);
}
else{
$item['last_dial_time'] = "";
}
switch ($item['level']) {
case 5:
$item['level'] = 'A类';
break;
case 4:
$item['level'] = 'B类';
break;
case 3:
$item['level'] = 'C类';
break;
case 2:
$item['level'] = 'D类';
break;
default:
$item['level'] = 'E类';
}
}
$cwhere = array();
if($mobile){
$cwhere["mobile"] = $mobile;
}
$cwhere["status"] = ['>',0];
if($status != ""){
$cwhere["status"] = $status;
}
$cwhere["salesman"] = $id;
$count = Db::name('member')->where($cwhere)->count('uid');
$this->assign('list',$list['data']);
$this->assign('page',$page);
$this->assign('total',$count);
return $this->fetch();
}
public function getQRUrl(){
$id = input('id/d','','trim,strip_tags');
$qrImg = "";
$wxInfo = db('wx_user',[],false)->where(array('is_default'=>1,'status'=>1))->find();
$extends = &load_wechat('Extends',$wxInfo);
$result = Db::name('admin')->field('ticket')->where('id',$id)->find();
if (!$result['ticket']){
$result = $extends->getQRCode($id,1);
if ($result['ticket']){
$ret = Db::name('admin')->where('id',$id)->update(array('ticket'=>$result['ticket']));
}
}
$wxInfo = db('wx_user',[],false)->where(array('is_default'=>1,'status'=>1))->find();
$extends = &load_wechat('Extends',$wxInfo);
$qrImg = $extends->getQRUrl($result['ticket']);
return returnAjax(0,'success',$qrImg);
}
public function removeBinding(){
$id = input('id','','trim,strip_tags');
$res = Db::name('admin')->field('open_id')->where('id',$id)->find();
if ($res['open_id']){
$ret = Db::name('admin')->where('id',$id)->update(array('open_id'=>''));
if ($ret >=0){
return returnAjax(0,'解除绑定成功');
}
}
return returnAjax(1,'绑定失败');
}
public function addAdmin(){
if(IS_POST){
$password = input('password','','trim,strip_tags');
$salt = rand_string(6);
$mdata = array();
$mdata['role_id'] = input('roleId','','trim,strip_tags');
$mdata['username'] = input('userName','','trim,strip_tags');
$list = Db::name('admin')->field('id')->where('username',$mdata['username'])->find();
if($list['id']){
$this->error("该用户名已经存在。",Url("User/manager/addadmin"));
}
$mdata['password'] = md5($password.$salt);
$mdata['mobile'] = input('mobile','','trim,strip_tags');
$mdata['email'] = input('email','','trim,strip_tags');
$mdata['status'] = 1;
$mdata['create_time'] = time();
$mdata['salt'] = $salt;
$mdata['open_tsr'] = input('open_tsr','','trim,strip_tags');
$mdata['examine'] = input('examine','','trim,strip_tags');
$mdata['time_price'] = input('time_price','','trim,strip_tags');
$mdata['month_price'] = input('month_price','','trim,strip_tags');
$mdata['asr_price'] = input('asr_price','','trim,strip_tags');
$mdata['credit_line'] = input('credit_line','','trim,strip_tags');
$user_auth = session('user_auth');
$mdata['pid'] = $user_auth['uid'];
$mdata['asr_type'] = input('asr_type','0','trim,strip_tags');
$member_id = Db::name('admin')->insertGetId($mdata);
$this->relation_menu_grouping($user_auth['uid'],$member_id);
if($member_id){
$user_role = $this->get_user_role($member_id);
if($user_role === '管理员'||$user_role === '运营商'){
$pid = $user_auth['uid'];
}else{
}
return returnAjax(0,'新建成功');
}else{
return returnAjax(1  ,'error!',"新建失败");
}
}else{
$mlist = Db::name('admin_role')->field('id,name')->where('status',1)->order('id asc')->select();
$this->assign('rolelist',$mlist);
$this->assign('current','添加');
return $this->fetch();
}
}
public function test()
{
$menus = Db::name('menu')
->field('id,source_id')
->where('menu_grouping',0)
->select();
foreach($menus as $key=>$value){
echo Db::name('menu')
->where('id',$value['id'])
->update([
'source_id'=>$value['id']
]);
}
}
public function synchronization()
{
$users = Db::name('admin')
->field('a.id,a.pid')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('ar.name','运营商')
->select();
foreach($users as $key=>$value){
$find_users = Db::name('admin')
->field('id')
->where('pid',$value['id'])
->select();
$ids = [];
foreach($find_users as $find_key=>$find_value){
$ids[] = $find_value['id'];
}
while(count($ids) >0){
$result = Db::name('admin')
->where('id','in',$ids)
->update([
'menu_grouping'=>$value['id']
]);
if(empty($result)){
echo '错误';
echo '<br />';
echo json_encode($ids);
echo '<br />';
}
$find_users = Db::name('admin')
->field('id')
->where('pid','in',$ids)
->select();
$ids = [];
foreach($find_users as $find_key=>$find_value){
$ids[] = $find_value['id'];
}
}
echo $this->relation_menu_grouping($value['pid'],$value['id']);
echo '<br />';
}
}
protected function relation_menu_grouping($create_user_id,$user_id)
{
if(empty($create_user_id) ||empty($user_id)){
return false;
}
$role_name = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('a.id',$user_id)
->value('ar.name');
if($role_name === '运营商'){
$count = Db::name('menu')
->where('menu_grouping',$user_id)
->count('id');
if($count !== 0){
return false;
}
$menu_grouping = $user_id;
$result = Db::name('admin')
->where('id',$user_id)
->update([
'menu_grouping'=>$menu_grouping
]);
$default_pmenu = Db::name('menu')
->where('menu_grouping',0)
->where('pid',0)
->select();
$menu_ids = [];
foreach($default_pmenu as $key=>$value){
$data = $value;
unset($data['id']);
$data['menu_grouping'] = $menu_grouping;
$menu_ids[$value['id']] = Db::name('menu')
->insertGetId($data);
$default_menu = Db::name('menu')
->where('menu_grouping',0)
->where('pid',$value['id'])
->select();
foreach($default_menu as $find_key=>$find_value){
$find_data = $find_value;
unset($find_data['id']);
$find_data['menu_grouping'] = $menu_grouping;
$find_data['pid'] = $menu_ids[$value['id']];
$menu_ids[$find_value['id']] = Db::name('menu')
->insertGetId($find_data);
}
}
}else{
$menu_grouping = Db::name('admin')
->where('id',$create_user_id)
->value('menu_grouping');
$result = Db::name('admin')
->where('id',$user_id)
->update([
'menu_grouping'=>$menu_grouping
]);
}
return true;
}
public function editAdmin(){
if(IS_POST){
$mdata = array();
$roleId = input('roleId','','trim,strip_tags');
if ($roleId){
$mdata['role_id']  = $roleId;
}
$mdata['username'] = input('userName','','trim,strip_tags');
$adminId = input('adminId','','trim,strip_tags');
$user_auth = session('user_auth');
$list = Db::name('admin')->field('id')->where('username',$mdata['username'])->find();
if($list['id'] != $adminId &&isset($list['id'])){
$this->error("该用户名已经存在。",Url("User/manager/addadmin"));
}
$mdata['mobile'] = input('mobile','','trim,strip_tags');
$mdata['email'] = input('email','','trim,strip_tags');
$mdata['logo'] = input('headpic','','trim,strip_tags');
$mdata['status'] = input('status','1','trim,strip_tags');
$mdata['update_time'] = time();
$mdata['open_tsr'] = input('open_tsr','','trim,strip_tags');
$mdata['examine'] = input('examine','','trim,strip_tags');
$mdata['time_price'] = input('time_price','','trim,strip_tags');
$mdata['month_price'] = input('month_price','','trim,strip_tags');
$mdata['asr_price'] = input('asr_price','','trim,strip_tags');
$mdata['credit_line'] = input('credit_line','','trim,strip_tags');
$mdata['asr_type'] = input('asr_type','0','trim,strip_tags');
$result = Db::name('admin')->where('id',$adminId)->update($mdata);
if($result){
$path = Db::name('picture')
->field('path')
->where('id',$mdata['logo'])
->value('path');
$user_auth['logo'] = $path;
session('user_auth',$user_auth);
session('user_auth_sign',data_auth_sign($user_auth));
return returnAjax(0,'编辑成功',$result);
}else{
return returnAjax(1,'error!',"编辑失败");
}
}else{
$Aid = input('id','','trim,strip_tags');
$result = Db::name('admin')->where('id',$Aid)->find();
$this->assign('list',$result);
$picdata=array();
if($result['logo']){
if (is_numeric($result['logo'])) {
$pic = Db::name('picture')->field('path')->where('id',$result['logo'])->find();
if($pic['path']){
$picdata['headpic'] = $result['logo'];
}
}
}
$this->assign('picdata',$picdata);
$this->assign('current','编辑');
return $this->fetch('addadmin');
}
}
public function edit_personal()
{
if(IS_POST){
$mdata = array();
$roleId = input('roleId','','trim,strip_tags');
if ($roleId){
$mdata['role_id']  = $roleId;
}
$mdata['username'] = input('userName','','trim,strip_tags');
$user_auth = session('user_auth');
$adminId = $user_auth['uid'];
$list = Db::name('admin')->field('id')->where('username',$mdata['username'])->find();
if($list['id'] != $adminId &&isset($list['id'])){
$this->error("该用户名已经存在。",Url("User/manager/addadmin"));
}
$mdata['mobile'] = input('mobile','','trim,strip_tags');
$mdata['email'] = input('email','','trim,strip_tags');
$mdata['logo'] = input('headpic','','trim,strip_tags');
$mdata['status'] = input('status','1','trim,strip_tags');
$mdata['update_time'] = time();
$mdata['open_tsr'] = input('open_tsr','','trim,strip_tags');
$mdata['examine'] = input('examine','','trim,strip_tags');
$mdata['time_price'] = input('time_price','','trim,strip_tags');
$mdata['month_price'] = input('month_price','','trim,strip_tags');
$mdata['asr_price'] = input('asr_price','','trim,strip_tags');
$mdata['credit_line'] = input('credit_line','','trim,strip_tags');
$mdata['asr_type'] = input('asr_type','0','trim,strip_tags');
$result = Db::name('admin')->where('id',$adminId)->update($mdata);
if($result){
$path = Db::name('picture')
->field('path')
->where('id',$mdata['logo'])
->value('path');
$user_auth['logo'] = $path;
session('user_auth',$user_auth);
session('user_auth_sign',data_auth_sign($user_auth));
return returnAjax(0,'编辑成功',$result);
}else{
return returnAjax(1,'error!',"编辑失败");
}
}
}
public function addMoney(){
$user_auth = session('user_auth');
$pid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$Aid = input('thisAdmin','','trim,strip_tags');
$money = input('moneyNum','','trim,strip_tags');
$note = input('note','','trim,strip_tags');
if(empty($Aid) ||empty($money)){
return returnAjax(3,'error!','参数错误');
}
$AdminData = new AdminData();
$role_name = $AdminData->get_role_name($pid);
if($role_name != '管理员'){
if($pid == $Aid){
return returnAjax(4,'error','不能给自己充值');
}
}
$defore_balance = Db::name('admin')
->where('id',$Aid)
->value('money');
$result = Db::name('admin')->where('id',$Aid)->setInc('money',$money);
if($result){
$balance = $defore_balance +$money;
$mdata = array();
$mdata['recharge_member_id'] = $pid;
$mdata['owner'] = $Aid;
$mdata['menoy'] = $money;
$mdata['remak'] = $note;
$mdata['defore_balance'] = $defore_balance;
$mdata['balance'] = $balance;
$mdata['type'] = 1;
$mdata['status'] = 1;
$mdata['create_time'] = time();
Db::name('tel_deposit')->insertGetId($mdata);
$userInfo =  Db::name('admin')->field("money")->where('id',$Aid)->find();
if ($userInfo['money'] >0){
$ret = Db::name('tel_config')->where('member_id',$Aid)->update(array('status'=>1));
}
return returnAjax(0,'充值成功',$result);
}else{
return returnAjax(1,'error!',"充值失败");
}
}
public function setstatus(){
$adminId = input('arrayIds/a','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('admin')->where('id','in',$adminId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'失败',"失败");
}
}
public function openAuditing(){
$adminId = input('arrayIds/a','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['examine'] = $status;
$list = Db::name('admin')->where('id','in',$adminId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function delAdmin(){
$adminId = input('admin_id/a','','trim,strip_tags');
$AdminData = new AdminData();
$LinesData = new LinesData();
foreach($adminId as $admin_id){
$role_name = $AdminData->get_role_name($admin_id);
$RobotDistribution = new RobotDistribution();
$result = $RobotDistribution->reset($admin_id);
if($result === false){
Log::info('回收机器人和删除分配记录失败');
Log::info($admin_id);
}
$LinesData->delete_sale_lines($admin_id);
}
$reslist = Db::name('admin')
->field('id,open_tsr,user_type,extension_number')
->where('id','in',$adminId)
->select();
$exdata['owner'] = 0;
$exdata['status'] = 0;
foreach ($reslist as $k=>$v){
if($v["extension_number"]){
Db::name('tel_extension_number')->where('extension_number',$v['extension_number'])->update($exdata);
}
}
$list = Db::name('admin')->where('id','in',$adminId)->delete();
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function editpwd() {
if (IS_POST) {
$user_auth = session('user_auth');
$data = $this->request->post();
if ($data['password'] != $data['repassword']){
return $this->error('两次输入新密码不一致!');
}
unset($data['repassword']);
$uid = session('user_auth.uid');
$password = $data['password'] ;
$oldpassword = $data['oldpassword'] ;
$userinfo =  Db::name('admin')->field("password,salt")->where('id',$uid)->find();
$mpassword = md5($oldpassword);
if($userinfo['salt']){
$mpassword = md5($oldpassword.$userinfo['salt']);
}
if($mpassword === $userinfo['password']){
$salt = rand_string(6);
$mdata = array();
$mdata['password'] = md5($password.$salt);
$mdata['salt'] = $salt;
$result = Db::name('admin')->where('id',$uid)->update($mdata);
$OperationRecord = new OperationRecord();
$OperationRecord->insert_user('reset_user_password',$user_auth['uid'],$user_auth['uid'],'修改用户密码',['username'=>$user_auth['username']],[]);
}else{
\think\Log::record('uid='.$uid.'原始密码错误！');
return returnAjax(0,"修改密码失败,原始密码错误！");
}
if($result){
return returnAjax(1,"修改密码成功！");
}else{
return returnAjax(0,"修改密码失败！");
}
}else{
$this->setMeta('修改密码');
return $this->fetch();
}
}
public function chackname(){
$name = input('name','','trim,strip_tags');
$list = Db::name('admin')->field('id')->where('username',$name)->find();
if($list['id']){
return returnAjax(0,'该用户名已经存在',$list);
}else{
return returnAjax(1,'ok!',"可以用");
}
}
public function getadmin(){
$id = input('id','','trim,strip_tags');
$mlist = Db::name('admin')->where('id',$id)->find();
if($mlist){
return returnAjax(0,'获取数据成功',$mlist);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function version_update_log(){
return $this->fetch();
}
public function message_station(){
return $this->fetch();
}
public function release_station_news(){
return $this->fetch();
}
public function add_account(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->add_account();
}else{
$ManagerMethod->add_account();
}
$this->assign('check_type',session('check_type'));
return $this->fetch();
}
public function mytest(){
$ManagerMethod = new ManagerMethod();
$arr = [
'uid'=>'3',
'username'=>'用户3',
'role'=>'mainaccount',
'company_name'=>'xx公司',
'email'=>'556677@qq.com',
'mobile'=>'18811223346',
'expiry_time'=>1655497185391,
'notifyCallback'=>'http:xxxx',
'integrationCallback'=>'http:xxxx',
'meals'=>[
['type'=>'测试套餐'],
],
'extra'=>[
'enableCall'=>true,
'extraSubCount'=>0,
'extraViewCount'=>0,
'enableTask'=>true,
'taskQuota'=>100,
],
"integrationCallback"=>"http:xxxx",
];
$jsonArr = json_encode($arr);
$ManagerMethod->curl_openAccount($jsonArr);
echo(1);
}
public function account_management() {
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->ajax_sale_account();
}else{
$ManagerMethod->ajax_sale_account();
}
return $this->fetch();
}
public function soft_deletion(){
$ManagerMethod = new ManagerMethod();
return $ManagerMethod->soft_deletion();
}
public function open_close_state(){
$ManagerMethod = new ManagerMethod();
return $ManagerMethod->open_close_state();
}
public function operation_record(){
return $this->fetch();
}
public function ajax_operation_record(){
$ManagerMethod = new ManagerMethod();
return $ManagerMethod->ajax_operation_record();
}
public function recharge_management(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->ajax_sale_account_recharge();
}else{
$ManagerMethod->ajax_sale_account_recharge();
}
return $this->fetch();
}
public function sale_record(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->sale_record();
}else{
$ManagerMethod->sale_record();
}
}
public function robot_management(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->ajax_robot_management();
}else{
$ManagerMethod->ajax_robot_management();
}
return $this->fetch();
}
public function distribution_robot(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->distribution_robot();
}else{
$ManagerMethod->distribution_robot();
}
}
public function force_recovery(){
$ManagerMethod = new ManagerMethod();
return $ManagerMethod->force_recovery();
}
public function message_passageway(){
return $this->fetch();
}
public function tariff_management(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->ajax_tariff_management();
}else{
$ManagerMethod->ajax_tariff_management();
}
return $this->fetch();
}
public function edit_tariff_management(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->edit_tariff_management();
}else{
$ManagerMethod->edit_tariff_management();
}
}
public function management_record(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->management_record();
}
}
public function line_management(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$info = Db::name('admin')->where('id',$uid)->find();
$role_id = $info['role_id'];
$this->assign('role_id',$role_id);
return $this->fetch();
}
public function edit_service_cost(){
$ManagerMethod = new ManagerMethod();
if(request()->isAjax()){
return $ManagerMethod->edit_service_cost();
}
}
public function line_details(){
$id=input('id','trim,strip_tags');
$this->assign('group_id',$id);
$this->assign('currentPage',1);
$this->assign('pageLimit',10);
$user_auth = session('user_auth');
$role_id = Db::name('admin')->where('id',$user_auth['uid'])->value('role_id')??'';
$line_group_pid = Db::name('tel_line_group')->where('id',$id)->value('line_group_pid')??'';
if($role_id>=17 ||(bool)$line_group_pid){
$this->assign('addLineClass','hidden');
}else{
$this->assign('addLineClass','');
}
return $this->fetch();
}
}
