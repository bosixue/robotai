<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use think\Loader;
use Qiniu\json_decode;
use app\common\controller\Log;
use app\common\controller\MemberGroup;
use app\common\controller\TelConfig;
use app\common\controller\TelScenarios;
use app\common\controller\TelCallRecord;
use app\common\controller\PlanData;
use app\common\controller\LinesData;
use app\common\controller\AdminData;
use app\common\controller\RedisConnect;
use app\common\controller\Audio;
use app\common\controller\TaskData;
use app\common\controller\AutoTaskTime;
use app\common\controller\AutoTaskDate;
use app\user\controller\Scenarios;
class Member extends User{
private $connect;
public $call_pause_second = 0;
public function _initialize() {
parent::_initialize();
$request = request();
$action = $request->action();
$count = count(config('db_configs'));
$config = Db::name('tel_config')->order('id desc')->find();
if($config['fs_num'] >= $count){
if( config('db_configs')['fs1'] ){
$this->connect = Db::connect('db_configs.fs1');
$this->fs_num=1;
}else{
for( $i=1;$i<$count;$i++){
if( config('db_configs')['fs'.($i+1)] ){
$this->connect = Db::connect('db_configs.fs'.($i+1));
$this->fs_num=$i+1;
break;
}
}
}
}else{
if( config('db_configs')['fs'.($config['fs_num']+1)] ){
$this->connect = Db::connect('db_configs.fs'.($config['fs_num']+1));
$this->fs_num=$config['fs_num']+1;
}else{
for( $i=2;$i<$count;$i++){
if($config['fs_num']+$i>$count){
break;
}
if( config('db_configs')['fs'.($config['fs_num']+$i)] ){
$this->connect = Db::connect('db_configs.fs'.($config['fs_num']+$i));
$this->fs_num=$config['fs_num']+$i;
break;
}
}
if( empty($this->fs_num) ){
for( $i=1;$i<=$count;$i++){
if( config('db_configs')['fs'.$i] ){
$this->connect = Db::connect('db_configs.fs'.$i);
$this->fs_num=$i;
break;
}
}
}
}
}
}
public function update_crm_level()
{
$id = input('id','','trim,strip_tags');
$level = input('level','','trim,strip_tags');
if(empty($id) ||empty($level)){
return returnAjax(3,'参数错误');
}
$result = Db::name('crm')
->where('id',$id)
->update([
'level'=>$level
]);
if(!empty($result)){
return returnAjax(0,'成功');
}
return returnAjax(1,'失败');
}
public function index() {
return $this->fetch();
}
public function current_record(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = (int)$uid;
}
$where['status'] = [">=",0];
$tasklist = Db::name('tel_config')->field('id,task_id,task_name')->where($where)->order('id desc')->select();
$this->assign('tasklist',$tasklist);
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id desc')->select();
$this->assign('scenarioslist',$scenarioslist);
$label = array();
if(!$super){
$label['member_id'] = $uid;
}
$label['type'] = 1;
$flowLabels = Db::name('tel_label')->where($label)->order('id desc')->select();
$this->assign('flowLabels',$flowLabels);
$label['type'] = 0;
$semanticLabels = Db::name('tel_label')->where($label)->order('id desc')->select();
$this->assign('semanticLabels',$semanticLabels);
$label['type'] = 2;
$knlgLabels = Db::name('tel_label')->where($label)->order('id desc')->select();
$this->assign('knlgLabels',$knlgLabels);
$wx_push_users = Db::name('wx_push_users')
->field('id,name')
->where([
'member_id'=>$uid,
])
->select();
$this->assign('wx_push_users',$wx_push_users);
$config = array();
if(!$super){
$config['member_id'] = $uid;
}
$config['status'] = ['>',-1];
$list = Db::name('tel_config')->field('id,task_id,task_name')->where($config)->order('id desc')->select();
$this->assign('list',$list);
return $this->fetch();
}
public function historical_records(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = (int)$uid;
}
$where['status'] = [">=",0];
$tasklist = Db::name('tel_config')->field('id,task_id,task_name')->where($where)->select();
$this->assign('tasklist',$tasklist);
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('scenarioslist',$scenarioslist);
$label = array();
if(!$super){
$label['member_id'] = $uid;
}
$label['type'] = 1;
$flowLabels = Db::name('tel_label')->where($label)->order('id asc')->select();
$this->assign('flowLabels',$flowLabels);
$label['type'] = 0;
$semanticLabels = Db::name('tel_label')->where($label)->order('id asc')->select();
$this->assign('semanticLabels',$semanticLabels);
$label['type'] = 2;
$knlgLabels = Db::name('tel_label')->where($label)->order('id asc')->select();
$this->assign('knlgLabels',$knlgLabels);
$config = array();
if(!$super){
$config['member_id'] = $uid;
}
$config['status'] = ['>',-1];
$list = Db::name('tel_config')->field('id,task_id,task_name')->where($config)->order('id desc')->select();
$this->assign('list',$list);
return $this->fetch();
}
public function seat_management(){
$user_auth = session('user_auth');
$line_datas = Db::name('tel_line_group')
->field('id, name')
->where('user_id',$user_auth['uid'])
->select();
$this->assign('line_datas',$line_datas);
return $this->fetch();
}
public function get_seatmanager_api(){
$uid = session('user_auth.uid');
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$realname = input('realname','','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
$seat_data = Db::name('admin')
->field('id,username,realname,status,create_time,remark,seat_number')
->where('pid',$uid)
->where('role_id',20);
$count = Db::name('admin')->where('pid',$uid)->where('role_id',20);
if(!empty($mobile)){
$ids = Db::name('seat_transfer_numbers')->where('number','like','%'.$mobile.'%')->select();
$id = array();
foreach ($ids as $k =>$vid) {
$id[$k] = $vid['member_id'];
}
$seat_data = $seat_data->where('id','in',$id);
$count = $count->where('id','in',$id);
}
if(!empty($username)){
$seat_data = $seat_data->where('username','like','%'.$username.'%');
$count = $count->where('username','like','%'.$username.'%');
}
if(!empty($realname)){
$seat_data = $seat_data->where('realname','like','%'.$realname.'%');
$count = $count->where('realname','like','%'.$realname.'%');
}
$count = $count->count('id');
$seat_data = $seat_data->page($page,$limit)
->order('create_time','desc')
->select();
foreach ($seat_data as $key =>$value) {
$seat_data[$key]['sequence'] = ($page-1)*10+($key+1);
if(!empty($value['status'])){
$seat_data[$key]['status'] = '已开启';
$seat_data[$key]['status_name'] = '锁定';
}else {
$seat_data[$key]['status'] = '已锁定';
$seat_data[$key]['status_name'] = '开启';
}
if(empty($value['create_time'])){
$seat_data[$key]['create_time'] = "暂无显示";
}else {
$seat_data[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
}
$seat_data[$key]['mobile'] = Db::name('seat_transfer_numbers')->field('number')->where('member_id',$value['id'])->select();
if(empty($value['remark'])){
$seat_data[$key]['remark'] = '暂无备注';
}
}
$page_count = ceil($count/$limit);
$data = array();
$data['seat_data'] = $seat_data;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function add_seat_api(){
$uid = session('user_auth.uid');
$username = input('username','','trim,strip_tags');
$password = input('password','','trim,strip_tags');
$realname = input('realname','','trim,strip_tags');
$mobile = input('mobile/a','','trim,strip_tags');
$seat_number = input('seat_number','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$transfer_line_id = input('transfer_line_id','','trim,strip_tags');
if(empty($transfer_line_id)){
return returnAjax(1,'请选择转接线路');
}
$seat_name = Db::name('admin')->field('username')->where('username',$username)->find();
if(!empty($seat_name)){
return returnAjax(1,'该坐席账号已存在');
}
$salt = rand_string(6);
$mdata = [
'role_id'=>20,
'username'=>$username,
'password'=>md5($password.$salt),
'realname'=>$realname,
'status'=>1,
'create_time'=>time(),
'salt'=>$salt,
'pid'=>$uid,
'remark'=>$remark,
'seat_number'=>$seat_number,
'transfer_line_id'=>$transfer_line_id
];
$seat_transfer_numbers_id = Db::name('admin')->insertGetId($mdata);
if(empty($mobile) === false &&is_array($mobile) === true &&count($mobile) != 0){
for($i = 0;$i <count($mobile);$i++){
$sdata = [
'member_id'=>$seat_transfer_numbers_id,
'number'=>$mobile[$i]
];
$result[$i] = Db::name('seat_transfer_numbers')->insertGetId($sdata);
}
if($result >= 0){
return returnAjax(0,'保存成功',$result);
}else{
return returnAjax(1,'error!',"保存失败");
}
}else {
if($seat_transfer_numbers_id >= 0){
return returnAjax(0,'保存成功',$mdata);
}else{
return returnAjax(1,'error!',$mdata);
}
}
}
public function edit_seat_api(){
$id = input('id','','trim,strip_tags');
$mobile = input('mobile/a','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$password = input('password','','trim,strip_tags');
$transfer_line_id = input('transfer_line_id','','trim,strip_tags');
$mdata = array();
if(!empty($password)){
$salt = rand_string(6);
$mdata['password'] = md5($password.$salt);
$mdata['update_time'] = time();
$mdata['salt'] = $salt;
}else {
$mdata['update_time'] = time();
}
$mdata['remark'] = $remark;
$mdata['transfer_line_id'] = $transfer_line_id;
$resulta = Db::name('admin')->where('id',$id)->update($mdata);
$mobile_count = Db::name('seat_transfer_numbers')->where('member_id',$id)->delete();
if(empty($mobile) === false &&is_array($mobile) === true &&count($mobile) != 0){
$sdata = [];
for($i = 0;$i <count($mobile);$i++){
$sdata = [
'member_id'=>$id,
'number'=>$mobile[$i],
];
$result[$i] = Db::name('seat_transfer_numbers')->where('member_id',$id)->insertGetId($sdata);
}
}
if($resulta >= 0){
return returnAjax(0,'保存成功',['mdata'=>$mdata,'sdata'=>$sdata]);
}else{
return returnAjax(1,'error!',['mdata'=>$mdata,'sdata'=>$sdata]);
}
}
public function get_editseat_info_api(){
$id = input('id','','trim,strip_tags');
$seat_info = Db::name('admin')
->field('username,realname,seat_number,remark,transfer_line_id')
->where('id',$id)
->find();
$mobile_info = Db::name('seat_transfer_numbers')->field('number')->where('member_id',$id)->select();
foreach ($mobile_info as $k =>$v) {
$seat_info['mobile'][$k] = $v['number'];
}
return returnAjax(0,'获取数据成功',$seat_info);
}
public function delelte_seat_api(){
$where = [];
$type = input('type','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$realname = input('realname','','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
if(!empty($mobile)){
$where['number'] = array('like','%'.$mobile.'%');
}
if(!empty($username)){
$where['username'] = array('like','%'.$username.'%');
}
if(!empty($realname)){
$where['realname'] =array('like','%'.$realname.'%');
}
$uid = session('user_auth.uid');
$where['pid'] = array('eq',$uid);
$where['role_id'] = array('eq',20);
if($type == 1){
Db::startTrans();
try {
$idsArr = Db::name('admin')->where($where)->column('id');
Db::name('admin')->where($where)->delete();
Db::name('seat_transfer_numbers')->where('member_id','in',$idsArr)->delete();
Db::commit();
return returnAjax(0,'批量删除成功');
}
catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'批量删除失败');
}
}else{
$where = [];
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
Db::startTrans();
try {
Db::name('admin')
->where('id','in',$ids)
->delete();
Db::name('seat_transfer_numbers')
->where('member_id','in',$ids)
->delete();
Db::commit();
return returnAjax(0,'批量删除成功');
}
catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'批量删除失败');
}
}
}
public function get_seatdata_id_api(){
$uid = session('user_auth.uid');
$username = input('username','','trim,strip_tags');
$realname = input('realname','','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
$seat_data = Db::name('admin')
->field('id')
->where('pid',$uid)
->where('role_id',20)
->where('status',1);
if(!empty($mobile)){
$ids = Db::name('seat_transfer_numbers')->where('number','like','%'.$mobile.'%')->select();
$id = array();
foreach ($ids as $k =>$vid) {
$id[$k] = $vid['member_id'];
}
$seat_data = $seat_data->where('id','in',$id);
}
if(!empty($username)){
$seat_data = $seat_data->where('username','like','%'.$username.'%');
}
if(!empty($realname)){
$seat_data = $seat_data->where('realname','like','%'.$realname.'%');
}
$seat_data = $seat_data->order('create_time','desc')
->select();
return returnAjax(0,'success',$seat_data);
}
public function lockname_api(){
$id = input('id','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
if($type == '开启'){
$adata = ['status'=>1];
}else{
$adata = ['status'=>0];
}
$result = Db::name('admin')->where('id',$id)->update($adata);
if(empty($result)){
return returnAjax(1,'锁定失败',$type);
}else {
return returnAjax(0,'锁定成功',$result);
}
}
public function intentional_member(){
$pageSize=10;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$admin_info = Db::name('admin')->where('id',$uid)->find();
$this->assign('role_id',$admin_info['role_id']);
$super = $user_auth["super"];
$is_verification = Db::name('admin')->where('id',$uid)->value('is_verification');
$this->assign('is_verification',$is_verification);
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$where['status'] = 1;
$where['check_statu'] = ['<>',1];
$where['is_variable']=0;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$asr_list = Db::name('tel_interface')
->field('id,name')
->where('owner',$uid)
->select();
$this->assign('asr_list',$asr_list);
$line_datas = Db::name('tel_line_group')
->field('id,name')
->where('user_id',$uid)
->select();
$this->assign('line_datas',$line_datas);
$default_line_id = Db::name('admin')
->where('id',$uid)
->value('default_line_id');
$this->assign('default_line_id',$default_line_id);
$wx_push_users = Db::name('wx_push_users')
->field('id,name')
->where('member_id',$uid)
->select();
$this->assign('wx_push_users',$wx_push_users);
$crm_push_users = Db::name('admin')
->field('id,username')
->where([
'pid'=>$uid,
'role_id'=>20,
])
->select();
$this->assign('crm_push_users',$crm_push_users);
$crm_push_config= Db::name('tel_config')
->field('id,task_name')
->where([
'member_id'=>$uid,
'status'=>['>=',0]
])
->select();
$this->assign('crm_push_config',$crm_push_config);
$seats = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->field('a.username,a.id')
->where([
'ar.name'=>'坐席',
'a.pid'=>$uid
])
->select();
$sms_where = array();
$sms_where['st.owner'] = $uid;
$sms_where['st.status'] = 3;
$sms_template = Db::name('sms_template')
->alias('st')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->field('st.id,st.name,ss.name as sign_name,st.content')
->where($sms_where)
->select();
$this->assign('sms_template',$sms_template);
$this->assign('seats',$seats);
$this->assign('line_datas',$line_datas);
$this->assign('scenarioslist',$scenarioslist);
$task_temp = DB::name('tel_tasks_templates')->where('member_id',$uid)->order('id','desc')->column('template','id');
$this->assign('task_temp',$task_temp);
$asr_list = Db::name('tel_interface')
->field('id,name')
->where('owner',$uid)
->select();
$this->assign('asr_list',$asr_list);
return $this->fetch();
}
public function add_intentional_member(){
$id=input('post.id','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$name=input('post.name','','trim,strip_tags');
$sex=input('post.sex',0,'trim,strip_tags');
if($sex===0){
$sex="未知";
}
$compay_name=input('post.compay_name','','trim,strip_tags');
$crm_cate=input('post.crm_cate','','trim,strip_tags');
$note=input('post.note','','trim,strip_tags');
$status=1;
$data=[
'member_id'=>$uid,
'sex'=>$sex,
'compay_name'=>$compay_name,
'crm_cate'=>$crm_cate,
'note'=>$note,
'status'=>$status,
'name'=>$name,
];
if(empty($id)){
$phone = input('post.phone','','trim,strip_tags');
$data['name']=$name;
$data['phone']= $phone;
if( empty($name) ||empty($phone) ){
return returnAjax(0,'用户名或者手机号码不能为空');
}
$count = Db::name('crm')->where(['phone'=>$phone,'member_id'=>$uid,'status'=>1])->count();
if($count >0){
return returnAjax(0,'手机号是唯一的');
}
$data['create_time']=time();
$saveId = Db::name('crm')->insertGetId($data);
if($saveId){
return returnAjax(1,'新增成功');
}else{
return returnAjax(0,'新增失败');
}
}else{
$datax=[
'sex'=>$sex,
'compay_name'=>$compay_name,
'name'=>$name,
];
$data['update_time']=time();
$res = Db::name('crm')->where(['id'=>$id])->update($datax);
if($res){
return returnAjax(1,'编辑成功');
}else{
return returnAjax(0,'编辑失败');
}
}
}
public function get_crm_record_befor(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$phone = input('phone','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$create_startDate = strtotime(input('startCreateDate','','trim,strip_tags'));
$create_endTime = strtotime(input('endCreateDate','','trim,strip_tags'));
$sitchair = input('sitchair','','trim,strip_tags');
$config_id = input('config_id','','trim,strip_tags');
$crm_id = input('crm_id','','trim,strip_tags');
$arr  = input('arr/a','','trim,strip_tags');
$brr  = input('brr/a','','trim,strip_tags');
$crr  = input('crr/a','','trim,strip_tags');
$drr  = input('drr/a','','trim,strip_tags');
$page = input('page','1','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
if(!empty($limit)){
$Page_size = $limit;
}else{
$Page_size = 10;
}
$crm_where = array();
if($user_auth['role'] == '坐席'){
$crm_where['seat_id']=$uid;
}else{
$crm_where['member_id']=$uid;
}
if($phone){
$crm_where['phone'] = ['like','%'.$phone.'%'];
}
if($name){
$crm_where['name'] = ['like','%'.$name.'%'];
}
$crm_where['status']=1;
if(!empty($arr)){
$crm_where['level']=['in',$arr];
}
if(!empty($brr)){
$crm_where['is_look']=['in',$brr];
}
if(!empty($crr)){
if($crr[0]==1){
$crm_where['seat_id']=['neq',0];
}elseif($crr[0]==0){
$crm_where['seat_id']=0;
}
}
if(!empty($drr)){
$crm_where['crm_cate']=['in',$drr];
}
if(!empty($sitchair)){
$crm_where['seat_id']=['eq',$sitchair];
}
if(!empty($config_id)){
$crm_where['task_id']=['eq',$config_id];
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime >$create_startDate ){
$crm_where['create_time'] = [['egt',$create_startDate],['elt',$create_endTime],'and'];
}
if(!empty($create_startDate) &&empty($create_endTime)){
$crm_where['create_time'] = ['egt',$create_startDate];
}
if(empty($create_startDate) &&!empty($create_endTime)){
$crm_where['create_time'] = ['elt',$create_endTime];
}
$Db_crm = Db::name('crm');
$count = $Db_crm ->where($crm_where)->count(1);
$page_count = ceil($count/$limit);
$list=$Db_crm ->where($crm_where)->order('id desc')->page($page,$limit)->select();
$befor_list=[];
foreach($list as $key=>$value){
$list[$key]['task_name']=isset($value['task_name'])&&!empty($value['task_name'])?$value['task_name']:'暂无任务';
$list[$key]['page']= $page;
$call_record_id=$Db_crm ->where('id',$value['id'])->value('call_record_id');
if( $call_record_id!=0 &&!empty($call_record_id) ){
$befor_list[]=$value['id'];
}
}
$weizhi_key = array_search($crm_id,$befor_list);
if($page <= 0){
return returnAjax(1,'没有上一条了',[]);
}
if($weizhi_key==0){
$befor_list_new=[];
$page_new=$page-1;
if($page_new<=0){
return returnAjax(1,'没有上一条了',[]);
}
$list_1=$Db_crm ->where($crm_where)->distinct('phone')->order('id desc')->page($page_new,$limit)->select();
$list_1 = array_reverse($list_1);
if(empty($list_1)||empty($list_1[$limit-1]) ||count($list_1) <=0){
return returnAjax(1,'没有上一条了',[]);
}
foreach($list_1 as $key_1 =>$value_1){
$call_record_id=$Db_crm ->where('id',$value_1['id'])->value('call_record_id');
if( $call_record_id!=0 &&!empty($call_record_id) ){
$befor_list_new[]=$value_1['id'];
break;
}
}
if(!empty($befor_list_new)&&count($befor_list_new)>0){
$list_new=[];
$list_new['id']=$befor_list_new[0];
$list_new['page']=$page_new;
return returnAjax(0,'成功',$list_new);
}else{
for($i=1;$i<=$page_count;$i++){
$page_new_x=$page_new-$i;
$list_x=$Db_crm ->where($crm_where)->distinct('phone')->order('id desc')->page($page_new_x,$limit)->select();
$list_x = array_reverse($list_x);
if(empty($list_x)||empty($list_x[$limit-1]) ||count($list_x) <=0){
return returnAjax(1,'没有上一条了',[]);
}
foreach( $list_x as $key_x =>$value_x){
$call_record_id=$Db_crm ->where('id',$value_x['id'])->value('call_record_id');
if( $call_record_id!=0 &&!empty($call_record_id) ){
$befor_list_new[]=$value_x['id'];
break;
}
}
if(!empty($befor_list_new)&&count($befor_list_new)>0){
$list_new=[];
$list_new['id']=$befor_list_new[count($befor_list_new)-1];
$list_new['page']=$page_new_x;
return returnAjax(0,'成功',$list_new);
}
}
}
}
if(isset($befor_list[$weizhi_key-1])===false){
return returnAjax(1,'没有上一条了',[]);
}
$list_new=[];
$list_new['id']=$befor_list[$weizhi_key-1];
$list_new['page']=$page;
return returnAjax(0,'成功',$list_new);
}
public function get_crm_record_next(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$phone = input('phone','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$create_startDate = strtotime(input('startCreateDate','','trim,strip_tags'));
$create_endTime = strtotime(input('endCreateDate','','trim,strip_tags'));
$sitchair = input('sitchair','','trim,strip_tags');
$config_id = input('config_id','','trim,strip_tags');
$crm_id = input('crm_id','','trim,strip_tags');
$arr  = input('arr/a','','trim,strip_tags');
$brr  = input('brr/a','','trim,strip_tags');
$crr  = input('crr/a','','trim,strip_tags');
$drr  = input('drr/a','','trim,strip_tags');
$page = input('page','1','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
if(!empty($limit)){
$Page_size = $limit;
}else{
$Page_size = 10;
}
$crm_where = array();
if($user_auth['role']=='坐席'){
$crm_where['seat_id']=$uid;
}else{
$crm_where['member_id']=$uid;
}
if($phone){
$crm_where['phone'] = ['like','%'.$phone.'%'];
}
if($name){
$crm_where['name'] = ['like','%'.$name.'%'];
}
$crm_where['status']=1;
if(!empty($arr)){
$crm_where['level']=['in',$arr];
}
if(!empty($brr)){
$crm_where['is_look']=['in',$brr];
}
if(!empty($crr)){
if($crr[0]==1){
$crm_where['seat_id']=['neq',0];
}elseif($crr[0]==0){
$crm_where['seat_id']=0;
}
}
if(!empty($drr)){
$crm_where['crm_cate']=['in',$drr];
}
if(!empty($sitchair)){
$crm_where['seat_id']=['eq',$sitchair];
}
if(!empty($config_id)){
$crm_where['task_id']=['eq',$config_id];
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime >$create_startDate ){
$crm_where['create_time'] = [['egt',$create_startDate],['elt',$create_endTime],'and'];
}
if(!empty($create_startDate) &&empty($create_endTime)){
$crm_where['create_time'] = ['egt',$create_startDate];
}
if(empty($create_startDate) &&!empty($create_endTime)){
$crm_where['create_time'] = ['elt',$create_endTime];
}
$Db_crm = Db::name('crm');
$count = $Db_crm ->where($crm_where)->count(1);
$page_count = ceil($count/$limit);
$list=$Db_crm ->where($crm_where)->order('id desc')->page($page,$limit)->select();
$next_list=[];
foreach($list as $key=>$value){
$list[$key]['task_name']=isset($value['task_name'])&&!empty($value['task_name'])?$value['task_name']:'暂无任务';
$list[$key]['page']= $page;
$call_record_id=$Db_crm ->where('id',$value['id'])->value('call_record_id');
if( $call_record_id!=0 &&!empty($call_record_id) ){
$next_list[]=$value['id'];
}
}
$weizhi_key = array_search($crm_id,$next_list);
if($page >$page_count){
return returnAjax(1,'没有下一条了',[]);
}
if($weizhi_key==count($next_list)-1){
$next_list_new=[];
$page_new= $page+1;
if($page_new >$page_count){
return returnAjax(1,'没有下一条了',[]);
}
$list_1=$Db_crm ->where($crm_where)->distinct('phone')->order('id desc')->page($page_new,$limit)->select();
foreach($list_1 as $key_1 =>$value_1){
$call_record_id=$Db_crm ->where('id',$value_1['id'])->value('call_record_id');
if( $call_record_id!=0 &&!empty($call_record_id) ){
$next_list_new[]=$value_1['id'];
break;
}
}
if(!empty($next_list_new)&&count($next_list_new)>0){
$list_new=[];
$list_new['id']=$next_list_new[0];
$list_new['page']=$page_new;
return returnAjax(0,'成功',$list_new);
}else{
for($i=1;$i<=$page_count;$i++){
$page_new_x=$page_new+$i;
$list_x=$Db_crm ->where($crm_where)->distinct('phone')->order('id desc')->page($page_new_x,$limit)->select();
if(empty($list_x)||empty($list_x[0]) ||count($list_x) <=0){
return returnAjax(1,'没有下一条了',[]);
}
foreach( $list_x as $key_x =>$value_x){
$call_record_id=$Db_crm ->where('id',$value_x['id'])->value('call_record_id');
if( $call_record_id!=0 &&!empty($call_record_id) ){
$next_list_new[]=$value_x['id'];
break;
}
}
if(!empty($next_list_new)&&count($next_list_new)>0){
$list_new=[];
$list_new['id']=$next_list_new[0];
$list_new['page']=$page_new_x;
return returnAjax(0,'成功',$list_new);
}
}
}
}
if(isset($next_list[$weizhi_key+1])===false){
return returnAjax(1,'没有上一条了',[]);
}
$list_new=[];
$list_new['id']=$next_list[$weizhi_key+1];
$list_new['page']=$page;
return returnAjax(0,'成功',$list_new);
}
public function crm_backdetail(){
$id = input('id','','trim,strip_tags');
$memberInfo = Db::name('crm')->field('c.id as cid,c.task_name as ctn,tccr.*')->alias('c')->join('tel_crm_call_record tccr','c.call_record_id=tccr.id','INNER')->where('c.id',$id)->find();
$record_id=$memberInfo['id'];
$crm_id=$memberInfo['cid'];
$memberInfo['task_name'] = $memberInfo['ctn']?$memberInfo['ctn']:'暂无任务';
$memberInfo['speechname'] = getScenariosName($memberInfo['scenarios_id']);
$memberInfo['successyaoyue']=$memberInfo['invitations']==0 ?'否':'是';
if(empty($memberInfo['semantic_label'])){
$memberInfo['semantic_label']='无';
}
if(empty($memberInfo['knowledge_label'])){
$memberInfo['knowledge_label']='无';
}
if(empty($memberInfo['flow_label'])){
$memberInfo['flow_label']='无';
}
$flow_name = Db::name('tel_crm_call_record')->where('id',$record_id)->value('flow_label');
$knowledge_name = Db::name('tel_crm_call_record')->where('id',$record_id)->value('knowledge_label');
$semantic_name = Db::name('tel_crm_call_record')->where('id',$record_id)->value('semantic_label');
$Keyword_name = $flow_name;
if(!$flow_name &&!$knowledge_name){
$Keyword_name .= $semantic_name;
}else{
if($knowledge_name){
$Keyword_name .= ','.$knowledge_name;
}
if($semantic_name){
$Keyword_name .= ','.$semantic_name;
}
}
if(!$Keyword_name){
$Keyword_name = '暂无数据';
}
$bills = Db::name('crm_bills')->where('call_id',$memberInfo['call_id'])->order('create_time asc')->select();
if(count($bills) == 0 &&$memberInfo['duration'] >0){
$redis = RedisConnect::get_redis_connect();
$redis_key = 'bills-'.$memberInfo['call_id'] .'-'.$memberInfo['mobile'];
$redis_datas = $redis->lrange($redis_key,0,-1);
foreach($redis_datas as $key=>$value){
$new_value = json_decode($value,true);
$bills = array_merge($bills,$new_value);
}
}
foreach($bills as $key=>$value){
if($this->file_exists(Config('history_cut_audio_server_url') .$value['path'])){
$bills[$key]['path'] = Config('history_cut_audio_server_url') .$value['path'];
}else{
$bills[$key]['path'] = Config('cut_audio_server_url') .$value['path'];
}
}
if(!empty($memberInfo['record_path'])){
if($this->file_exists(config("record_path").$memberInfo['record_path'])){
$memberInfo['record_path'] = config("record_path").$memberInfo['record_path'];
}else{
$memberInfo['record_path'] = config("history_record_path").$memberInfo['record_path'];
}
}else{
$memberInfo['record_path'] = '';
}
$memberInfo['last_dial_time'] = date('Y-m-d H:i:s',$memberInfo['last_dial_time']);
$data = array();
$data['memberInfo'] = $memberInfo;
$data['bills'] = $bills;
$data['num'] = $Keyword_name;
$res = Db::name('crm')->where('id',$crm_id)->update(['is_look'=>1]);
return returnAjax(0,'获取成功',$data);
}
public function ajax_intentional_member(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$phone = input('phone','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$create_startDate = strtotime(input('startCreateDate','','trim,strip_tags'));
$create_endTime = strtotime(input('endCreateDate','','trim,strip_tags'));
$sitchair = input('sitchair','','trim,strip_tags');
$config_id = input('config_id','','trim,strip_tags');
$arr  = input('arr/a','','trim,strip_tags');
$brr  = input('brr/a','','trim,strip_tags');
$crr  = input('crr/a','','trim,strip_tags');
$drr  = input('drr/a','','trim,strip_tags');
$page = input('page','1','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
if(!empty($limit)){
$Page_size = $limit;
}else{
$Page_size = 10;
}
$crm_where = array();
if($user_auth['role'] == '坐席'){
$crm_where['seat_id']=$uid;
}else{
$crm_where['member_id']=$uid;
}
if($phone){
$crm_where['phone'] = ['like','%'.$phone.'%'];
}
if($name){
$crm_where['name'] = ['like','%'.$name.'%'];
}
$crm_where['status']=1;
if(!empty($arr)){
$crm_where['level']=['in',$arr];
}
if(!empty($brr)){
$crm_where['is_look']=['in',$brr];
}
if(!empty($crr)){
if($crr[0]==1){
$crm_where['seat_id']=['neq',0];
}elseif($crr[0]==0){
$crm_where['seat_id']=0;
}
}
if(!empty($drr)){
$crm_where['crm_cate']=['in',$drr];
}
if(!empty($sitchair)){
$crm_where['seat_id']=['eq',$sitchair];
}
if(!empty($config_id)){
$crm_where['task_id']=['eq',$config_id];
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime <$create_startDate){
return returnAjax(0,'创建日期不合法',[]);
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime >$create_startDate ){
$crm_where['create_time'] = [['egt',$create_startDate],['elt',$create_endTime],'and'];
}
if(!empty($create_startDate) &&empty($create_endTime)){
$crm_where['create_time'] = ['egt',$create_startDate];
}
if(empty($create_startDate) &&!empty($create_endTime)){
$crm_where['create_time'] = ['elt',$create_endTime];
}
$Db_crm = Db::name('crm');
$count = $Db_crm ->where($crm_where)->count(1);
$list_=$Db_crm ->where($crm_where)->order('id desc')->page($page,$Page_size)->select();
foreach($list_ as $key=>$value){
$count_record = Db::name('crm')->field('c.id as cid')->alias('c')->join('tel_crm_call_record tccr','c.call_record_id=tccr.id','INNER')->where('c.id',$value['id'])->count('*');
$list_[$key]['count_record']=$count_record;
$list_[$key]['task_name']=isset($value['task_name'])&&!empty($value['task_name'])?$value['task_name']:'暂无任务';
$list_[$key]['seat_name']=$value['seat_id']!=0?getUsernameById($value['seat_id']):0;
}
$page_count = ceil($count/$Page_size);
$data = array();
$data['list'] = $list_;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
$data['limit'] = $Page_size;
return returnAjax(1,'获取数据成功',$data);
}
public  function get_data_by_id(){
$id = input('post.id/s','','trim,strip_tags');
if(empty($id)){
return returnAjax(0,'id不能为空');
}
$Db_crm = Db::name('crm');
$where['id'] = $id;
$info = $Db_crm->where($where)->find();
if(empty($info)){
return returnAjax(0,'信息不存在');
}
return returnAjax(1,'id不能为空',$info);
}
public function followup_add(){
$content = input('post.content','','trim,strip_tags');
$id = input('post.id','','trim,strip_tags');
$crm_cate = input('post.crm_cate',0,'trim,strip_tags');
Db::name('crm')->where(['id'=>$id])->update(['crm_cate'=>$crm_cate]);
$data['crm_id'] = $id;
$data['note'] = $content;
$data['crm_cate'] = $crm_cate;
$data['create_time'] = time();
$resId = Db::name('crm_follow_up_record')->insertGetId($data);
if($resId){
return returnAjax(1,'添加客户意向跟进成功');
}else{
return returnAjax(0,'添加客户意向跟进失败');
}
}
public function distribution_crm_api()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$type = input('type','','trim,strip_tags');
$distribution_seat_ids = input('distribution_seat_ids/a','','trim,strip_tags');
if($type == 2){
$name = input('name','','trim,strip_tags');
$create_startDate = strtotime(input('startCreateDate','','trim,strip_tags'));
$create_endTime = strtotime(input('endCreateDate','','trim,strip_tags'));
$sitchair = input('sitchair','','trim,strip_tags');
$config_id = input('config_id','','trim,strip_tags');
$arr  = input('arr/a','','trim,strip_tags');
$brr  = input('brr/a','','trim,strip_tags');
$crr  = input('crr/a','','trim,strip_tags');
$drr  = input('drr/a','','trim,strip_tags');
$crm_where = array();
if($user_auth['role'] == '坐席'){
$crm_where['seat_id']=$uid;
}else{
$crm_where['member_id']=$uid;
}
if($name){
$crm_where['name'] = ['like','%'.$name.'%'];
}
$crm_where['status']=1;
if(!empty($arr)){
$crm_where['level']=['in',$arr];
}
if(!empty($brr)){
$crm_where['is_look']=['in',$brr];
}
if(!empty($crr)){
if($crr[0]==1){
$crm_where['seat_id']=['neq',0];
}elseif($crr[0]==0){
$crm_where['seat_id']=0;
}
}
if(!empty($drr)){
$crm_where['crm_cate']=['in',$drr];
}
if(!empty($sitchair)){
$crm_where['seat_id']=['eq',$sitchair];
}
if(!empty($config_id)){
$crm_where['task_id']=['eq',$config_id];
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime <$create_startDate){
return returnAjax(0,'创建日期不合法',[]);
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime >$create_startDate ){
$crm_where['create_time'] = [['egt',$create_startDate],['elt',$create_endTime],'and'];
}
if(!empty($create_startDate) &&empty($create_endTime)){
$crm_where['create_time'] = ['egt',$create_startDate];
}
if(empty($create_startDate) &&!empty($create_endTime)){
$crm_where['create_time'] = ['elt',$create_endTime];
}
$list_=Db::name('crm') ->where($crm_where)->order('id desc')->select();
foreach($list_ as $k=>$v){
$arr[]=$v['id'];
}
$arrLength=count($arr);
$seatLength=count($distribution_seat_ids);
$seatLengthI=0;
$seatDistributeArr=[];
for($i=0;$i<$arrLength;$i++,$seatLengthI++){
if($seatLengthI>=$seatLength)$seatLengthI=0;
$seatDistributeArr[$distribution_seat_ids [$seatLengthI  ]  ][]=	$arr[$i];
}
foreach($seatDistributeArr as $k=>$v){
$update_result = Db::name('crm')->where(['id'=>['in',$v]])->update(['seat_id'=>$k] );
}
}elseif($type == 0 ||$type == 1){
$arr = input('ids/a','','trim,strip_tags');
$arrLength=count($arr);
$seatLength=count($distribution_seat_ids);
$seatLengthI=0;
$seatDistributeArr=[];
for($i=0;$i<$arrLength;$i++,$seatLengthI++){
if($seatLengthI>=$seatLength)$seatLengthI=0;
$seatDistributeArr[$distribution_seat_ids [$seatLengthI  ]  ][]=	$arr[$i];
}
foreach($seatDistributeArr as $k=>$v){
$update_result = Db::name('crm')->where(['id'=>['in',$v]])->update(['seat_id'=>$k] );
}
}
if(!empty($update_result)){
return $this->Json(0,'成功');
}else{
return $this->Json(1,'失败');
}
}
public function intention_customer_details(){
$id = input('id','','trim,strip_tags');
if(empty($id)){
echo "<script>alert('id不能为空');window.history.back(-1);  </script>";
}
$Db_crm = Db::name('crm');
$where['id'] = array('eq',$id);
$info = $Db_crm->where($where)->find();
if(empty($info)){
echo "<script> alert('此客户不存在'); window.history.back(-1);  </script>";
}
$crm_where['crm_id'] = array('eq',$id);
$end_up_time = Db::name('crm_follow_up_record')->where($crm_where)->order('create_time','desc')->value('create_time');
$this->assign('info',$info);
$this->assign('end_up_time',$end_up_time);
$this->assign('id',$id);
return $this->fetch();
}
public function ajax_uplist(){
$Page_size = 4;
$currentPage = input('currentPage','','trim,strip_tags');
$id = input('id','','trim,strip_tags');
$where['crm_id'] = array('eq',$id);
$list = Db::name('crm_follow_up_record')->where($where)->page($currentPage,$Page_size)->order('id','desc')->select();
foreach($list as $key =>$vo){
switch ($vo['crm_cate']){
case 0:
$list[$key]['crm_cate']="未分类";
break;
case 1:
$list[$key]['crm_cate']="意向客户";
break;
case 2:
$list[$key]['crm_cate']="潜在客户";
break;
case 3:
$list[$key]['crm_cate']="试用客户";
break;
case 4:
$list[$key]['crm_cate']="成交客户";
break;
}
}
$count = Db::name('crm_follow_up_record')->where($where)->count();
$pageCount = ceil($count / $Page_size);
$data['list'] =$list;
$data['pageNo'] = $Page_size ;
$data['pageCount'] = $pageCount;
return returnAjax(1,'获取数据成功',$data);
}
public function  ajax_call_list(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$limit=input('post.limit','','trim,strip_tags');
$crm_id=input('post.crm_id','','trim,strip_tags');
if(empty($limit)){
$Page_size = 10;
}else{
$Page_size =$limit;
}
$page = input('post.page','','trim,strip_tags');
$phone=input('post.id_phone','','trim,strip_tags');
$where['mobile'] =$phone;
$where['owner'] =$uid;
$seat_id = Db::name('crm')->where(['id'=>$crm_id])->value('seat_id');
Db::name('crm')->where('id',$crm_id)->update(['is_look'=>1]);
$config_table_days = config('call_table_days');
if(!isset($config_table_days) ||!is_numeric($config_table_days)){
$config_table_days = 5;
}
if(isset($seat_id) &&!empty($seat_id)){
$member_id = Db::name('crm')->where(['id'=>$crm_id])->value('member_id');
$list_call_records = Db::name('tel_call_record')->where(['mobile'=>$phone,'owner'=>$member_id])->select();
for($i=1;$i<=$config_table_days;$i++){
$table_name =  get_table_name($i);
$list_call_record_historys[$i] = Db::name($table_name)->where(['mobile'=>$phone,'owner'=>$member_id])->select();
}
}else{
$list_call_records = Db::name('tel_call_record')->where($where)->select();
for($i=1;$i<=$config_table_days;$i++){
$table_name =  get_table_name($i);
$list_call_record_historys[$i] = Db::name($table_name)->where($where)->select();
}
}
$datas=[];
if(count($list_call_records)>0){
foreach($list_call_records as $key =>$list_call_record){
$list_call_record['source']='record';
$datas[] = $list_call_record;
}
}
for($i=1;$i<=$config_table_days;$i++) {
if (count($list_call_record_historys[$i]) >0) {
foreach ($list_call_record_historys[$i] as $key =>$list_call_record_history) {
$list_call_record_history['source'] = 'historical';
$datas[] = $list_call_record_history;
}
}
}
array_multisort(array_column($datas,'last_dial_time'),SORT_DESC,$datas);
$list =array_slice($datas,($page-1)*$Page_size,$Page_size);
foreach($list as $key =>$vo){
if(!empty($vo['level'])){
switch ($vo['level'])
{
case 1:
$list[$key]['level']="F级(无效号码)";
break;
case 2:
$list[$key]['level']="E级(有效未接通)";
break;
case 3:
$list[$key]['level']="D级(无有效对话)";
break;
case 4:
$list[$key]['level']="C级(简单对话)";
break;
case 5:
$list[$key]['level']="B级(一般意向)";
break;
case 6:
$list[$key]['level']="A级(意向客户)";
break;
default:
$list[$key]['level']="暂无意向";
}
}
$list[$key]['mobile_j']=hide_phone_middle($list[$key]['mobile']);
$list[$key]['last_dial_time'] = date('Y-m-d H:i',$vo['last_dial_time']);
$list[$key]['old_last_dial_time'] = $vo['last_dial_time'];
}
$count = count($datas);
$page_count = ceil($count/$Page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
$data['limit']=$Page_size;
return returnAjax( 1,'获取数据成功',$data);
}
public function delete_customer(){
$id = input('post.id','','trim,strip_tags');
if(empty($id)||$id==0){
return returnAjax(0,'请选择一个再删除');
}
$Db_crm = Db::name('crm');
$phone = $Db_crm->where(['id'=>$id])->value('phone');
$crm_where['id'] = $id;
$data['status']=0;
$res =  $Db_crm->where($crm_where)->update($data);
$ras = Db::name('tel_call_record')->where(['mobile'=>$phone])->update(['state_crm'=>0]);
if($res){
return returnAjax(1,'删除成功');
}else{
return returnAjax(0,'删除失败');
}
}
public function delete_all_customer(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$vals = input('vals/a','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$create_startDate = strtotime(input('startCreateDate','','trim,strip_tags'));
$create_endTime = strtotime(input('endCreateDate','','trim,strip_tags'));
$level = input('level/a',array(),'trim,strip_tags');
$distribution = input('distribution/a',array(),'trim,strip_tags');
$desire = input('desire/a',array(),'trim,strip_tags');
$call = input('call/a',array(),'trim,strip_tags');
$min_call_count = input('min_call_count','','trim,strip_tags');
$max_call_count = input('max_call_count','','trim,strip_tags');
$sitchair = input('sitchair','','trim,strip_tags');
$brr  = input('brr/a','','trim,strip_tags');
$crm_where = array();
$crm_where['member_id|seat_id']=$uid;
if($phone){
$crm_where['phone'] = ['like','%'.$phone.'%'];
}
if($name){
$crm_where['name'] = ['like','%'.$name.'%'];
}
if(!empty($level)){
$crm_where['level'] = ['in',$level];
}
if(!empty($call)){
$crm_where['is_look'] = ['in',$call];
}
if(!empty($desire)){
$crm_where['crm_cate'] = ['in',$desire];
}
if(in_array(1,$distribution) &&in_array(0,$distribution)){
}else if(in_array(1,$distribution)){
$crm_where['seat_id'] = ['>',0];
}else if(in_array(0,$distribution)){
$crm_where['seat_id'] = 0;
}
if($min_call_count &&$max_call_count){
$crm_where['call_times'] = array('BETWEEN',[$min_call_count,$max_call_count]);
}else if($min_call_count &&!$max_call_count){
$crm_where['call_times'] = array('>=',$min_call_count);
}else if(!$min_call_count &&$max_call_count){
$crm_where['call_times'] = array('<=',$max_call_count);
}
$crm_where['status']=1;
if(!empty($brr)){
$crm_where['crm_cate']=['in',$brr];
}
if(!empty($sitchair)){
$crm_where['seat_id']=['eq',$sitchair];
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime >$create_startDate ){
$crm_where['create_time'] = [['egt',$create_startDate],['elt',$create_endTime],'and'];
}
if(!empty($create_startDate) &&empty($create_endTime)){
$crm_where['create_time'] = ['egt',$create_startDate];
}
if(empty($create_startDate) &&!empty($create_endTime)){
$crm_where['create_time'] = ['elt',$create_endTime];
}
if($type==1){
$crms =  Db::name('crm')->field('id,call_record_id')->where($crm_where)->select();
foreach($crms as $key=>$crm){
$call_record = Db::name('tel_crm_call_record')->where('id',$crm['call_record_id'])->find();
Db::name('crm_bills')->where('call_id',$call_record['call_id'])->delete();
Db::name('tel_crm_call_record')->where('id',$crm['call_record_id'])->delete();
}
$ras =  Db::name('crm')->where($crm_where)->delete();
if($ras){
return returnAjax(1,'删除成功');
}else{
return returnAjax(0,'删除失败');
}
}else{
$Db_crm = Db::name('crm');
$crm_where['id'] = ['in',$vals];
$crms =  $Db_crm->field('id,call_record_id')->where($crm_where)->select();
foreach($crms as $key=>$crm){
$call_record = Db::name('tel_crm_call_record')->where('id',$crm['call_record_id'])->find();
Db::name('crm_bills')->where('call_id',$call_record['call_id'])->delete();
Db::name('tel_crm_call_record')->where('id',$crm['call_record_id'])->delete();
}
$data['status']=0;
$res =  $Db_crm->where($crm_where)->delete();
if($res){
return returnAjax(1,'删除成功');
}else{
return returnAjax(0,'删除失败');
}
}
}
public function delete_call(){
$id = input('id','','trim,strip_tags');
$where['id'] = array('eq',$id);
$res = Db::name('tel_call_record')->where($where)->delete();
if($res){
return returnAjax(1,'删除成功');
}else{
return returnAjax(0,'删除失败');
}
}
public function import_data(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$arr = input('arr/a','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$chaos_num = input('chaos_num','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$create_startDate = strtotime(input('startCreateDate','','trim,strip_tags'));
$create_endTime = strtotime(input('endCreateDate','','trim,strip_tags'));
$min_call_count = input('min_call_count','','trim,strip_tags');
$max_call_count = input('max_call_count','','trim,strip_tags');
$sitchair = input('sitchair','','trim,strip_tags');
$config_id = input('config_id','','trim,strip_tags');
$xrr  = input('xrr/a','','trim,strip_tags');
$arr  = input('arr/a','','trim,strip_tags');
$brr  = input('brr/a','','trim,strip_tags');
$crr  = input('crr/a','','trim,strip_tags');
$drr  = input('drr/a','','trim,strip_tags');
$Db_crm = Db::name('crm');
$crm_where = array();
$crm_where['c.member_id']=['eq',$uid];
$crm_where['c.status']=['eq',1];
if(empty($type)){
if(!empty($xrr)){
$crm_where['c.id'] = array('in',$xrr);
$list =  $Db_crm
->alias('c')
->join('crm_follow_up_record b','c.id = b.crm_id','LEFT')
->field('c.*,b.create_time as get_times,b.note as get_note')
->where($crm_where)
->select();
}
}else{
$crm_where=array();
$crm_where['member_id|seat_id']=['eq',$uid];
$crm_where['status']=['eq',1];
if($phone){
$crm_where['phone'] = ['like','%'.$phone.'%'];
}
if($name){
$crm_where['name'] = ['like','%'.$name.'%'];
}
if(!empty($arr)){
$crm_where['level']=['in',$arr];
}
if(!empty($brr)){
$crm_where['is_look']=['in',$arr];
}
if(!empty($crr)){
if($crr[0]==1){
$crm_where['seat_id']=['neq',0];
}elseif($crr[0]==0){
$crm_where['seat_id']=0;
}
}
if(!empty($drr)){
$crm_where['crm_cate']=['in',$drr];
}
if(!empty($config_id)){
$crm_where['task_id']=['eq',$config_id];
}
if(!empty($sitchair)){
$crm_where['seat_id']=['eq',$sitchair];
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime >$create_startDate ){
$crm_where['create_time'] = [['egt',$create_startDate],['elt',$create_endTime],'and'];
}
if(!empty($create_startDate) &&empty($create_endTime)){
$crm_where['create_time'] = ['egt',$create_startDate];
}
if(empty($create_startDate) &&!empty($create_endTime)){
$crm_where['create_time'] = ['elt',$create_endTime];
}
$Db_crm = Db::name('crm');
$list = $Db_crm ->where($crm_where)->distinct('phone')->order('id desc')->select();
}
$list_count = count($list);
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$RedisConnect->set($key,$list_count);
$objPHPExcel = new \PHPExcel();
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A1','客户名称')
->setCellValue('B1','性别')
->setCellValue('C1','电话')
->setCellValue('D1','客户公司')
->setCellValue('E1','最后跟进时间')
->setCellValue('F1','跟进内容')
->setCellValue('G1','创建时间')
->setCellValue('H1','备注信息')
->setCellValue('I1','坐席');
$accuracy_num = 0;
foreach ($list as $k =>$v) {
$num = $k +2;
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A'.$num,$v['name'])
->setCellValue('B'.$num,$v['sex'])
->setCellValue('C'.$num,hide_phone_middle($v['phone']) )
->setCellValue('D'.$num,$v['compay_name'])
->setCellValue('E'.$num,empty($v['get_times']) ?'暂时无最近跟进': date('Y-m-d H-i-s',$v['get_times']))
->setCellValue('F'.$num,empty($v['get_note']) ?'无': $v['get_note'])
->setCellValue('G'.$num,empty($v['create_time']) ?'创建时间为空': date('Y-m-d H-i-s',$v['create_time']))
->setCellValue('H'.$num,$num,$v['note'])
->setCellValue('I'.$num,getUsernameById($v['seat_id']) );
$accuracy_num++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$accuracy_num);
}
$setTitle='Sheet1';
$fileName='文件名称';
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
return returnAjax(0,'导出成功',config('res_url').ltrim($execlpath,"."));
}
public function add_task(){
}
public function cesday($day1,$day2){
$day1 = strtotime($day1);
$day2 = strtotime($day2);
$distance = ($day2 -$day1)/86400;
$daylist = array();
for($i=0;$i<=$distance;$i++){
$daylist[]= date('Y-m-d',$day1+(86400 * $i));
}
$daylist =implode(',',$daylist);
return $daylist;
}
public function addPlan(){
if (IS_POST) {
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$call_type = input('call_type','0','trim,strip_tags');
$robot_cnt = input('robot_cnt/d',0,'trim,strip_tags');
if($user_auth['role'] == '销售人员'){
$uid_where['member_id'] = array('eq',$uid);
$Db_tel_line = Db::name('tel_line');
$phoneId = $Db_tel_line
->where($uid_where)
->order('id','asc')
->value('id');
}else{
$phoneId = input('phone_id','','trim,strip_tags');
}
$memberInfo = Db::name('admin')
->field('usable_robot_cnt,asr_type')
->where("id",$uid)
->find();
$totalRobotCnt =  Db::name('tel_config')->where('member_id',$uid)->where('status','1')->sum('robot_cnt');
$totalRobotCnt = $robot_cnt+(int)$totalRobotCnt;
if ($totalRobotCnt >$memberInfo['usable_robot_cnt']){
return returnAjax(1,'超出购买机器人数量，请联系销售人员');
}
if ($memberInfo['asr_type'] >0){
$tilist = Db::name('tel_interface')->where('owner',$uid)->find();
if(!$tilist){
return returnAjax(1,'您没有接口，请到接口配置表里面添加数据。');
}
}
if ($call_type){
$sim = Db::name('tel_line')
->field('call_prefix,originate_variables,member_id,phone,inter_ip,dial_format')
->where("id",$phoneId)->find();
if (!$sim){
return returnAjax(1,'线路不存在！');
}
}else{
$sim = Db::name('tel_sim')
->field('call_prefix,member_id,phone,device_id')
->where("id",$phoneId)->find();
$gatewayInfo = Db::name('tel_device')->field('dial_format')->where('id',$sim['device_id'])->find();
if ($gatewayInfo &&$gatewayInfo['dial_format']){
$sim['dial_format'] = $gatewayInfo['dial_format'];
}
else{
return returnAjax(1,'网关账号不可为空');
}
}
$status = input('startup','0','trim,strip_tags');
if($status == 1){
$AdminData = new AdminData();
if($AdminData->verify_member_open_task_condition($uid) === false){
return returnAjax(1,'当前用户或上级余额不足');
}
}
$timegroup = array();
$timegroup['name'] = uniqid();
$timegroup['domain'] = uniqid();
$timegroup['member_id'] = $uid;
$tgresult = $this->connect->table('autodialer_timegroup')->insertGetId($timegroup);
$TimeRange = array();
$TimeRange['onetime'] = input('onetime','','trim,strip_tags');
$TimeRange['twotime'] = input('twotime','','trim,strip_tags');
$TimeRange['threetime'] = input('threetime','','trim,strip_tags');
$TimeRange['fourtime'] = input('fourtime','','trim,strip_tags');
$SaveRange = array();
foreach ($TimeRange as $tkey =>$tvalue) {
$temp = array();
$temp['group_uuid'] = $tgresult;
$temp['member_id'] = $uid;
if($tkey == 'onetime'){
$temp['begin_datetime'] = "00:00:00";
$temp['end_datetime'] = $tvalue;
array_push($SaveRange,$temp);
}
if($tkey == 'threetime'){
$temp['begin_datetime'] = $TimeRange['twotime'];
$temp['end_datetime'] = $tvalue;
array_push($SaveRange,$temp);
}
if($tkey == 'fourtime'){
$temp['begin_datetime'] = $tvalue;
$temp['end_datetime'] = "23:59:59";
array_push($SaveRange,$temp);
}
}
$TRresult = $this->connect->table('autodialer_timerange')->insertAll($SaveRange);
$task_name = input('name','','trim,strip_tags');
$task = array();
$task['name'] =  $task_name ;
$task['create_datetime'] = date("Y-m-d H:i:s",time());
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$task['disable_dial_timegroup'] = $tgresult;
$task['member_id'] = $uid;
$task['remark'] = input('remark','','trim,strip_tags');
$task['call_pause_second'] = 0 ;
$task['call_pause_second'] = $task['call_pause_second'] +$this->call_pause_second;
$task['call_notify_url'] = config('notify_url');
$task['start'] = $status;
$task['call_notify_type'] = 2;
$task['cache_number_count'] = 0;
$max_destination_extension = Db::name('tel_config')->field('destination_extension')->order('id desc')->find();
if ($max_destination_extension &&$max_destination_extension['destination_extension'] >0){
$destination_extension = ((int)$max_destination_extension['destination_extension'])+1;
}else{
$destination_extension =  config('destination_extension');
}
$task['destination_extension'] = $destination_extension;
if ($call_type){
$task['dial_format'] = $sim['dial_format'];
$task['_origination_caller_id_number'] = $sim['phone'];
$task['originate_variables'] = $sim['originate_variables'];
}else{
$task['dial_format'] = $sim['dial_format'];
$task['_origination_caller_id_number'] = $sim['call_prefix'];
}
if (config('start_da2')){
if (isset($task['originate_variables']) &&$task['originate_variables']){
$task['originate_variables'] = $task['originate_variables'].','.config('start_da2');
}else{
$task['originate_variables']  = config('start_da2');
}
}
$task['destination_dialplan'] = "XML";
$task['destination_context'] = "default";
$task['maximumcall'] = $robot_cnt;
$taskresult = $this->connect->table('autodialer_task')->insertGetId($task);
$week = array();
$zhou = array();
$week['Monday'] = input('Monday','','trim,strip_tags');
$week['Tuesday'] = input('Tuesday','','trim,strip_tags');
$week['Wednesday'] = input('Wednesday','','trim,strip_tags');
$week['Thursday'] = input('Thursday','','trim,strip_tags');
$week['Friday'] = input('Friday','','trim,strip_tags');
$week['Saturday'] = input('Saturday','','trim,strip_tags');
$week['Sunday'] = input('Sunday','','trim,strip_tags');
foreach ($week as $key =>$value) {
if($value == 0){
array_push($zhou,$key);
}
}
$weeklist = implode(",",$zhou);
$timertask = array();
$timertask['group_id'] = $tgresult;
$day1 = input('startdata');
$day2 = input('enddata');
$timertask['date_list'] = $this->cesday($day1,$day2);
$timertask['week_list'] = '';
$timertask["task_id"] = $taskresult;
$timerresult = $this->connect->table('autodialer_timer_task')->insertGetId($timertask);
$cdata = array();
$cdata['member_id'] = $uid;
$levelArr = input('level/a','','trim,strip_tags');
if ($levelArr){
$cdata['level'] =  implode(",",$levelArr);
}
$sale_ids_arr = input('sale_ids/a','','trim,strip_tags');
if ($sale_ids_arr){
$cdata['sale_ids'] =  implode(",",$sale_ids_arr);
}
$cdata["task_id"] = $taskresult;
$cdata["task_name"] = $task_name;
$cdata["scenarios_id"] = input('scenarios_id','','trim,strip_tags');
$cdata["call_type"] = $call_type;
$cdata["status"] = $status;
$cdata["destination_extension"] = $destination_extension;
$cdata["phone"] = $sim['phone'];
$cdata["call_prefix"] = $sim['call_prefix'];
$cdata['remarks'] =input('remark','','trim,strip_tags');
$cdata['robot_cnt'] = $robot_cnt;
$cdata['create_time'] = time();
$cdata['call_phone_id'] = $phoneId;
$cfresult = Db::name('tel_config')->insertGetId($cdata);
if ($taskresult){
$backdata = array();
$backdata['url'] = Url("User/Plan/index");
$type = input('type','','trim,strip_tags');
$vals = input('vals/a','','trim,strip_tags');
$create_startDate = strtotime(input('create_startDate','','trim,strip_tags'));
$create_endTime = strtotime(input('create_endTime','','trim,strip_tags'));
$keyword = input('keyword','','trim,strip_tags');
$crm_where = array();
if($type == 1){
if($create_startDate != ""&&$create_endTime != ""){
$crm_where['create_time'] = array(array('egt',$create_startDate),array('elt',$create_endTime));
}else if($create_startDate != ""&&$create_endTime == ""){
$crm_where['create_time'] = array('egt',$create_startDate);
}else if($create_startDate == ""&&$create_endTime != ""){
$crm_where['create_time'] = array('elt',$create_endTime);
}
if($keyword !=''){
$crm_where['name'] = array('like','%'.$keyword.'%');
}
}else if($type == 0){
$crm_where['id'] = array('in',$vals);
}
$Db_crm = Db::name('crm');
$data =  $Db_crm->where($crm_where)->select();
foreach($data as $key =>$vo){
$where['crm_id'] = array('eq',$vo['id']);
$data[$key]['get_times'] = Db::name('crm_follow_up_record')->where($where)->order('id','desc')->value('create_time');
$data[$key]['get_note'] = Db::name('crm_follow_up_record')->where($where)->order('id','desc')->value('note');
}
\think\Log::record('号码导入结果-2');
if(!empty($data) &&is_array($data) === true &&count($data) >0){
$members = [];
$request = request();
$ip = $request->ip(0,true);
$data_count = count($data);
Log::info($data_count);
foreach($data as $key=>$value){
$members[$key]['owner'] = $uid;
$members[$key]['reg_time'] = time();
$members[$key]['mobile'] = $value['phone'];
$members[$key]['salt'] = '';
$members[$key]['password'] = '';
$members[$key]['reg_ip'] = $ip;
$members[$key]['is_new'] = 1;
$members[$key]['task'] = $cdata["task_id"];
$members[$key]['status'] = 1;
$members[$key]['scenarios_id'] = $cdata["scenarios_id"];
$task_datas[$key]['number'] = $value['phone'];
if(count($members) === 1000 ||$data_count == ($key+1)){
$input_result = Db::name('member')
->insertAll($members);
$this->connect->table("autodialer_number_".$taskresult)
->insertAll($task_datas);
unset($members);
unset($task_datas);
$task_datas = [];
$members = [];
if(empty($input_result)){
\think\Log::record('写入失败');
}else{
\think\Log::record('写入成功');
}
}
}
\think\Log::record('号码导入结果');
}
return returnAjax(0,'添加成功',$backdata);
$get_id = Db::name('tel_config')->getLastInsID();
}else{
$backdata = array();
$backdata['url'] = Url("User/Plan/addPlan");
return returnAjax(1,'添加任务失败',$backdata);
}
}
}
public function get_scenarios_node_name($id){
if(!empty($id)){
$name = Db::name('tel_scenarios_node')->where(['id'=>$id])->value('name');
return $name;
}
return '没有场景节点';
}
public  function get_flow_node_name_by_arr($arr){
if(!is_array($arr)){
return '';
}
if(empty($arr)){
return '';
}
$name_str='';
foreach($arr as $value){
$name = Db::name('tel_flow_node')->field('name')->where(['id'=>$value])->value('name');
$name_str .= $name.'-';
}
return trim($name_str,'-');
}
public function create_task()	{
$task_name = input('task_name','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$start_date = input('start_date/a','','trim,strip_tags');
$end_date = input('end_date/a','','trim,strip_tags');
$start_time = input('start_time/a','','trim,strip_tags');
$end_time = input('end_time/a','','trim,strip_tags');
$is_auto = input('is_auto','','trim,strip_tags');
$robot_count = input('robot_count','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
$asr_id = input('asr_id','','trim,strip_tags');
$is_default_line = input('is_default_line','','trim,strip_tags');
$task_abnormal_remind_phone = input('task_abnormal_remind_phone','','trim,strip_tags');
$is_again_call = input('is_again_call','','trim,strip_tags');
$again_call_status = input('again_call_status/a','','trim,strip_tags');
if(is_array($again_call_status) === true){
$again_call_status = implode(',',$again_call_status);
}else{
$again_call_status = '';
}
$again_call_count = input('again_call_count/d','','trim,strip_tags');
$send_sms_status = input('send_sms_status','','trim,strip_tags');
$send_sms_level = input('send_sms_level/a','','trim,strip_tags');
if(is_array($send_sms_level) &&count($send_sms_level) >0){
$send_sms_level = implode(',',$send_sms_level);
}else{
$send_sms_level = '';
}
$yunkong_push_status = input('yunkong_push_status',0,'trim,strip_tags');
$yunkong_push_username = input('yunkong_push_username','','trim,strip_tags');
$yunkong_push_level = input('yunkong_push_level/a','','trim,strip_tags');
if(is_array($yunkong_push_level) &&count($yunkong_push_level) >0){
$yunkong_push_level = implode(',',$yunkong_push_level);
}else{
$yunkong_push_level = '';
}
$sms_template_id = input('sms_template_id','','trim,strip_tags');
$is_add_crm = input('is_add_crm','','trim,strip_tags');
$add_crm_level = input('add_crm_level/a','','trim,strip_tags');
if(is_array($add_crm_level) &&count($add_crm_level)){
$add_crm_level = implode(',',$add_crm_level);
}else{
$add_crm_level = '';
}
$crm_push_user_id = input('crm_push_user_id','','trim,strip_tags');
$wx_push_status = input('wx_push_status','','trim,strip_tags');
$wx_push_level = input('wx_push_level/a','','trim,strip_tags');
if(is_array($wx_push_level) &&count($wx_push_level)){
$wx_push_level = implode(',',$wx_push_level);
}else{
$wx_push_level = '';
}
$wx_push_user_id = input('wx_push_user_id','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$call_type = 1;
$status = 0;
if(empty($task_name)){
return $this->Json(2,'任务名不能为空');
}
if(empty($scenarios_id)){
return $this->Json(2,'请选择话术');
}
if(empty($robot_count)){
return $this->Json(2,'机器人数量不能为空');
}
if(empty($line_id)){
return $this->Json(2,'请选择线路');
}
if(empty($asr_id)){
return $this->Json(2,'请选择ASR');
}
$regex = config('phone_regular');
if(preg_match($regex,$task_abnormal_remind_phone) === false){
return returnAjax(2,'任务异常短信提醒的手机号码格式错误');
}
if($is_again_call == 1){
if(empty($again_call_status)){
return returnAjax(2,'请选择需要进行重新呼叫的通话状态');
}
if(empty($again_call_count)){
return returnAjax(2,'请选择重新呼叫次数');
}
}
if($yunkong_push_status == 1){
if(empty($yunkong_push_username)){
return returnAjax(2,'推送微信云控的用户名不能为空');
}
if(empty($yunkong_push_level)){
return returnAjax(2,'请选择需要推送到微信云控的意向等级');
}
}
if($send_sms_status == 1){
if(empty($send_sms_level)){
return returnAjax(2,'没有选择触发发送短信的意向等级');
}
if(empty($sms_template_id)){
return returnAjax(2,'没有选中指定短信模版');
}
}
if($is_add_crm == 1){
if(empty($add_crm_level)){
return returnAjax(2,'请选择加入CRM的客户意向等级');
}
}
if($wx_push_status == 1){
if(empty($wx_push_level)){
return returnAjax(2,'请选择微信推送的客户意向等级');
}
if(empty($wx_push_user_id)){
return returnAjax(2,'请选择推动的人员');
}
}
$line_count = Db::name('tel_line_group')
->where(['id'=>$line_id,'status'=>1])
->count('id');
if(empty($line_count)){
return returnAjax(2,'线路组不存在');
}
$user_auth = session('user_auth');
$usable_robot_cnt = Db::name('admin')
->where("id",$user_auth['uid'])
->value('usable_robot_cnt');
$run_robot_count =  Db::name('tel_config')
->where('member_id',$user_auth['uid'])
->where('status','1')
->sum('robot_cnt');
$usable_robot_count = $usable_robot_cnt -$run_robot_count;
if($robot_count >$usable_robot_count){
return returnAjax(2,'机器人数量不足');
}
$scenarios_count = Db::name('tel_scenarios')
->where('id',$scenarios_id)
->count('id');
if(empty($scenarios_count)){
return returnAjax(2,'话术不存在');
}
$task_name_count = Db::name('tel_config')
->where([
'task_name'=>$task_name,
'member_id'=>$user_auth['uid'],
'status'=>['neq',-1],
])
->count('id');
if($task_name_count >0){
return returnAjax(2,'任务名已重复');
}
if($is_default_line == 1){
$update_line_id = Db::name('admin')
->where('id',$user_auth['uid'])
->update([
'default_line_id'=>$line_id
]);
}else if($is_default_line == 0){
Db::name('admin')->where('id',$user_auth['uid'])
->update(['default_line_id'=>0 ]);
}
$max_destination_extension = Db::name('tel_config')->field('destination_extension')->order('id desc')->find();
if ($max_destination_extension &&$max_destination_extension['destination_extension'] >0){
$destination_extension = ((int)$max_destination_extension['destination_extension'])+1;
}else{
$destination_extension =  config('destination_extension');
}
$line_data = Db::name('tel_line')
->field('dial_format,phone,originate_variables,call_prefix,type_link')
->where('id',$line_id)
->find();
$task_config = [];
$task_config['fs_num'] = 0;
$task_config['member_id'] = $user_auth['uid'];
$task_config["task_name"] = $task_name;
$task_config["scenarios_id"] = $scenarios_id;
$task_config["call_type"] = $call_type;
$task_config["status"] = $status;
$task_config["destination_extension"] = $destination_extension;
$task_config["phone"] = $line_data['phone'];
$task_config["call_prefix"] = $line_data['call_prefix'];
$task_config['remarks'] = $remark;
$task_config['default_line_id'] = $is_default_line;
$task_config['robot_cnt'] = $robot_count;
$task_config['create_time'] = time();
$task_config['is_auto'] = $is_auto;
$task_config['asr_id'] = $asr_id;
$task_config['call_phone_group_id'] = $line_id;
$task_config['send_sms_status'] = $send_sms_status;
$task_config['send_sms_level'] = $send_sms_level;
$task_config['sms_template_id'] = $sms_template_id;
$task_config['is_add_crm'] = $is_add_crm;
$task_config['add_crm_level'] = $add_crm_level;
$task_config['add_crm_zuoxi']=$crm_push_user_id;
$task_config['wx_push_status'] = $wx_push_status;
$task_config['wx_push_level'] = $wx_push_level;
$task_config['wx_push_user_id'] = $wx_push_user_id;
$task_config['is_again_call'] = $is_again_call;
$task_config['again_call_status'] = $again_call_status;
$task_config['again_call_count'] = $again_call_count;
$task_config['yunkong_push_status'] = $yunkong_push_status;
$task_config['yunkong_push_username'] = $yunkong_push_username;
$task_config['yunkong_push_level'] = $yunkong_push_level;
$task_config['task_abnormal_remind_phone'] = $task_abnormal_remind_phone;
Db::startTrans();
try{
$task_id = Db::name('tel_config')->insertGetId($task_config);
if(empty($task_id)){
\think\Log::record('创建WEB端的任务配置表失败');
}
Db::name('tel_config')->where(['id'=>$task_id])->update(['task_id'=>$task_id]);
if($start_date &&$end_date &&$start_time &&$end_time){
$AutoTaskDate = new AutoTaskDate();
$insert_result = $AutoTaskDate->insert($user_auth['uid'],$task_id,$start_date,$end_date);
if(empty($insert_result)){
\think\Log::record('创建指定日期失败');
}
$AutoTaskTime = new AutoTaskTime();
foreach($start_time as $key=>$value){
$start_time[$key] = $value .':00';
$end_time[$key] = $end_time[$key] .':00';
}
$insert_result = $AutoTaskTime->insert($user_auth['uid'],$task_id,$start_time,$end_time);
if(empty($insert_result)){
\think\Log::record('创建指定时间失败');
}
}
Db::commit();
\think\Log::record('新建任务成功');
$checkAllType = input('checkAllType','','trim,strip_tags');
if($checkAllType == 1){
$phone = input('phone','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$create_startDate = strtotime(input('startCreateDate','','trim,strip_tags'));
$create_endTime = strtotime(input('endCreateDate','','trim,strip_tags'));
$min_call_count = input('min_call_count','','trim,strip_tags');
$max_call_count = input('max_call_count','','trim,strip_tags');
$sitchair = input('sitchair','','trim,strip_tags');
$brr  = input('brr/a','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$crm_where = array();
$crm_where['member_id|seat_id']=$uid;
if($phone){
$crm_where['phone'] = ['like','%'.$phone.'%'];
}
if($name){
$crm_where['name'] = ['like','%'.$name.'%'];
}
if($min_call_count &&$max_call_count){
$crm_where['call_times'] = array('BETWEEN',[$min_call_count,$max_call_count]);
}else if($min_call_count &&!$max_call_count){
$crm_where['call_times'] = array('>=',$min_call_count);
}else if(!$min_call_count &&$max_call_count){
$crm_where['call_times'] = array('<=',$max_call_count);
}
$crm_where['status']=1;
if(!empty($brr)){
$crm_where['crm_cate']=['in',$brr];
}
if(!empty($sitchair)){
$crm_where['seat_id']=['eq',$sitchair];
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime <$create_startDate){
return returnAjax(0,'创建日期不合法',[]);
}
if(!empty($create_startDate) &&!empty($create_endTime) &&$create_endTime >$create_startDate ){
$crm_where['create_time'] = [['egt',$create_startDate],['elt',$create_endTime],'and'];
}
if(!empty($create_startDate) &&empty($create_endTime)){
$crm_where['create_time'] = ['egt',$create_startDate];
}
if(empty($create_startDate) &&!empty($create_endTime)){
$crm_where['create_time'] = ['elt',$create_endTime];
}
$list = Db::name('crm')->field('phone,member_id')->where($crm_where)->order('id desc')->select();
$list = array_unique($list,SORT_REGULAR);
$redis = RedisConnect::get_redis_connect();
$now_time = strtotime(date("Y-m-d"));
$incr_key_all_count = "incr_owner_".$user_auth['uid'] ."_".$now_time ."_all_count";
$incr_key_per_task_count = "incr_owner_".$user_auth['uid'] ."_".$task_id ."_".$now_time ."_per_task_count";
foreach($list as $k =>$v){
$list[$k]['task']=$task_id;
$list[$k]['owner']=$v['member_id'];
$list[$k]['mobile']=$v['phone'];
$list[$k]['status']=1;
$list[$k]['reg_time'] =time();
$numbers[$k]['number'] = $v['phone'];
if($v) {
$redis->incrby($incr_key_all_count,1);
$redis->incrby($incr_key_per_task_count,1);
}
unset( $list[$k]['member_id']);
unset( $list[$k]['phone']);
}
$count = Db::name('member')->insertAll($list);
$fs_num= Db::name('tel_config')->where(['id'=>$task_id])->value('fs_num');
if(!empty($fs_num)){
$insert_result = Db::connect('db_configs.fs'.$fs_num)
->table('autodialer_number_'.$task_id)
->insertAll($numbers);
}
if(!empty($count)){
return returnAjax(0,'新建任务成功');
}
}else{
$redis = RedisConnect::get_redis_connect();
$now_time = strtotime(date("Y-m-d"));
$incr_key_all_count = "incr_owner_".$user_auth['uid'] ."_".$now_time ."_all_count";
$incr_key_per_task_count = "incr_owner_".$user_auth['uid'] ."_".$task_id ."_".$now_time ."_per_task_count";
$xrr = input('xrr/a','','trim,strip_tags');
$crm_where = [];
$crm_where['id'] = array('in',$xrr);
$list = Db::name('crm')->field('phone,member_id,name')->where($crm_where)->order('id desc')->select();
$list = array_unique($list,SORT_REGULAR);
foreach($list as $k =>$v){
$list[$k]['task']=$task_id;
$list[$k]['nickname']=$v['name'];
$list[$k]['owner']=$v['member_id'];
$list[$k]['mobile']=$v['phone'];
$list[$k]['status']=1;
$list[$k]['reg_time'] =time();
$numbers[$k]['number'] = $v['phone'];
if($v) {
$redis->incrby($incr_key_all_count,1);
$redis->incrby($incr_key_per_task_count,1);
}
unset( $list[$k]['member_id']);
unset( $list[$k]['phone']);
}
$count = Db::name('member')->insertAll($list);
$fs_num= Db::name('tel_config')->where(['id'=>$task_id])->value('fs_num');
if(!empty($fs_num)){
$insert_result = Db::connect('db_configs.fs'.$fs_num)
->table('autodialer_number_'.$task_id)
->insertAll($numbers);
}
if(!empty($count)){
return returnAjax(0,'新建任务成功');
}
}
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'新建任务失败');
}
}
public function customer_edit(){
$Db_crm = Db::name('crm');
$type = input('type','','trim,strip_tags');
$data['name'] = input('name','','trim,strip_tags');
$data['sex'] = input('sex','','trim,strip_tags');
$data['phone'] = input('phone','','trim,strip_tags');
$data['compay_name']= input('compay_name','','trim,strip_tags');
$data['note'] = input('note','','trim,strip_tags');
if($type >0 ){
$get_phone['phone'] = array('eq',$data['phone']);
$get_phone['id'] = array('neq',$type);
$nojb = $Db_crm ->where($get_phone)->count();
if($nojb >0){
return returnAjax(0,'联系电话是唯一的2');
}else{
$data['update_time'] = time();
$where['id']  = array('eq',$type);
$res = $Db_crm->where($where)->update($data);
if($res){
return returnAjax(1,'编辑成功');
}else{
return returnAjax(0,'编辑失败');
}
}
}else if($type == 0){
$get_phone['phone'] = array('eq',$data['phone']);
$nojb = $Db_crm ->where($get_phone)->count();
if($nojb >0){
return returnAjax(0,'联系电话是唯一的1');
}else{
$data['create_time'] = time();
$res = $Db_crm->insert($data);
if($res){
return returnAjax(1,'添加成功');
}else{
return returnAjax(0,'添加失败');
}
}
}
}
public function add(){
$model = \think\Loader::model('User');
if(IS_POST){
$data = $this->request->param();
$uid = $model->register($data['username'],$data['password'],$data['repassword'],$data['email'],false);
if(0 <$uid){
$userinfo = array('nickname'=>$data['username'],'status'=>1,'reg_time'=>time(),'last_login_time'=>time(),'last_login_ip'=>get_client_ip(1));
if(!db('Member')->where(array('uid'=>$uid))->update($userinfo)){
return $this->error('用户添加失败！','');
}else {
return $this->success('用户添加成功！',url('admin/user/index'));
}
}else{
return $this->error($model->getError());
}
}else{
$data = array(
'keyList'=>$model->addfield
);
$this->assign($data);
$this->setMeta("添加用户");
return $this->fetch('public/edit');
}
}
public function edit() {
$model = model('User');
if(IS_POST){
$data = $this->request->post();
unset($data['id']);
$reuslt = $model->editUser($data,true);
if (false !== $reuslt) {
return $this->success('修改成功！',url('user/user/index'));
}else{
return $this->error($model->getError(),'');
}
}else{
$info = $this->getUserinfo();
$data = array(
'info'=>$info,
'keyList'=>$model->editfield
);
$this->assign($data);
$this->setMeta("编辑用户");
return $this->fetch('public/edit');
}
}
private function getUserinfo($uid = null,$pass = null,$errormsg = null){
$user = model('User');
$uid = $uid ?$uid : input('id');
$uid = $uid ?$uid : session('user_auth.uid');
$map['uid'] = $uid;
if($pass != null ){
unset($map);
$map['password'] = $pass;
}
$list = $user::where($map)
->field('uid,username,nickname,sex,email,qq,score,signature,status,salt')
->find();
if(!$list){
return $this->error($errormsg ?$errormsg : '不存在此用户！');
}
return $list;
}
public function auth(){
$access = model('AuthGroupAccess');
$group = model('AuthGroup');
if (IS_POST) {
$uid = input('uid','','trim,intval');
$access->where(array('uid'=>$uid))->delete();
$group_type = config('user_group_type');
foreach ($group_type as $key =>$value) {
$group_id = input($key,'','trim,intval');
if ($group_id) {
$add = array(
'uid'=>$uid,
'group_id'=>$group_id,
);
$access->save($add);
}
}
return $this->success("设置成功！");
}else{
$uid = input('id','','trim,intval');
$row = $group::select();
$auth = $access::where(array('uid'=>$uid))->select();
$auth_list = array();
foreach ($auth as $key =>$value) {
$auth_list[] = $value['group_id'];
}
foreach ($row as $key =>$value) {
$list[$value['module']][] = $value;
}
$data = array(
'uid'=>$uid,
'auth_list'=>$auth_list,
'list'=>$list
);
$this->assign($data);
$this->setMeta("用户分组");
return $this->fetch();
}
}
public function editpwd() {
if (IS_POST) {
$user = model('User');
$data = $this->request->post();
if ($data['password'] != $data['repassword']){
return $this->error('两次输入新密码不一致!');
}
unset($data['repassword']);
$data['uid'] = session('user_auth.uid');
$res = $user->editpw($data);
if($res){
return returnAjax(1,"修改密码成功！");
}else{
return returnAjax(0,"修改密码失败！");
}
}else{
$this->setMeta('修改密码');
return $this->fetch();
}
}
public function del($id){
$uid = array('IN',is_array($id) ?implode(',',$id) : $id);
$find = $this->getUserinfo($uid);
model('User')->where(array('uid'=>$uid))->delete();
return $this->success('删除用户成功！');
}
public function grade(){
$list = Db::name('member_level')->paginate(5,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function gradeStatus(){
$gid = input('id');
$data=array();
$data['status'] = input('status');
$list = Db::name('member_level')->where('id',$gid)->update($data);
if(!$list){
echo "修改失败。";
}
}
public function addGrade(){
if(IS_POST){
$ctype=array();
$ctype['name'] = input('name');
$ctype['order_money'] = input('order_money');
$ctype['order_count'] = input('order_count');
$ctype['discount'] = input('discount');
$result = Db::name('member_level')->insertGetId($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "新建成功";
$data['url'] = Url("User/Member/grade");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "新建失败";
$data['url'] = Url("User/Member/addGrade");
echo json_encode($data);
}
}else{
$this->assign('current','添加');
return $this->fetch();
}
}
public function editGrade(){
if(IS_POST){
$ctype=array();
$ctype['name'] = input('name');
$ctype['order_money'] = input('order_money');
$ctype['order_count'] = input('order_count');
$ctype['discount'] = input('discount');
$result = Db::name('member_level')->where('id',input('gradeId'))->update($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "编辑成功";
$data['url'] = Url("User/Member/grade");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "编辑失败";
$data['url'] = Url("User/Member/editGrade",array('id'=>input('gradeId')));
echo json_encode($data);
}
}else{
$id = input('id');
$levellist =  Db::name('member_level')->where('id',$id)->find();
$this->assign('levellist',$levellist);
$this->assign('current','编辑');
return $this->fetch('addgrade');
}
}
public function delGrade($id = ''){
$list = Db::name('member_level')->where('id',$id)->delete();
if(!$list){
echo "删除失败。";
}
}
public function userList(){
$mobile = input('mobile');
$email = input('email');
if ($mobile ||$email) {
$list = Db::name('member')->where('mobile like "%'.$mobile.'%" or email = "'.$email.'"')->paginate(6,false,array('query'=>$this->param));
}else {
$list = Db::name('member')->paginate(5,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function memberList(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = (int)$uid;
}
$where['status'] = [">=",0];
$tasklist = Db::name('tel_config')->field('task_id,task_name')->where($where)->select();
$where = [];
if(!$super){
$where['member_id'] = (int)$uid;
}
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('tasklist',$tasklist);
$this->assign('scenarioslist',$scenarioslist);
$label = array();
if(!$super){
$label['member_id'] = $uid;
}
$label['type'] = 0;
$semanticLabels = Db::name('tel_label')->where($label)->order('id asc')->select();
$this->assign('semanticLabels',$semanticLabels);
$LinesData = new LinesData();
$lines_data = $LinesData->get_distribution_lines($uid,[]);
$this->assign('lines_data',$lines_data);
$groupList = array();
$groupList = Db::name('member_group')->field('id,name')->where('status',1)->order('id asc')->select();
$this->assign('groupList',$groupList);
$usable_robot_cnt = Db::name('admin')->where('id',$uid)->value('usable_robot_cnt');
$sum = array();
$sum['member_id'] = $uid;
$sum['status'] = ['=',1];
$rnum = Db::name('tel_config')->where($sum)->sum('robot_cnt');
$rnum = $usable_robot_cnt -$rnum;
if($rnum <0){
$rnum = 0;
}
$this->assign('rnum',$rnum);
return $this->fetch();
}
public function memberListAjax(){
$Page_size = 10;
$page = input('page','1','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
$startDate = input('startDate','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$startNum = input('startNum','','trim,strip_tags');
$endNum = input('endNum','','trim,strip_tags');
$calltask = input('calltask','','trim,strip_tags');
$calltaskId = input('calltaskId','','trim,strip_tags');
$levelids = input('levelids/a');
$statusids = input('statusids/a');
$scenarios = input('scenarios','','trim,strip_tags');
$scenariosId = input('scenariosId','','trim,strip_tags');
$semanticLabels = input('semanticLabels/a');
$flowLabels = input('flowLabels/a');
$knlgLabels = input('knlgLabels/a');
$call_times = input('call_times/d',0,'trim,strip_tags');
$affirm_times = input('affirm_times/d',0,'trim,strip_tags');
$negative_times = input('negative_times/d',0,'trim,strip_tags');
$neutral_times = input('neutral_times/d',0,'trim,strip_tags');
$effective_times = input('effective_times/d',0,'trim,strip_tags');
$hit_times = input('hit_times/d',0,'trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if($mobile){
$where["mobile"] = $mobile;
}
if($calltaskId){
$where['task'] = $calltaskId;
}
if($scenariosId){
$where['scenarios_id'] = $scenariosId;
}
if (!$super){
$where["owner"] = $uid;
}
if($startDate &&$endTime){
$where["reg_time"] = ["between time",[$startDate,$endTime]];
}
if($startNum>=0 &&$endNum){
$where["duration"] = ["between",[$startNum,$endNum]];
}else if($startNum >0){
$where["duration"] = [">=",$startNum];
}
$where["user_type"] = 0;
if (is_array($levelids) &&count($levelids)){
$where["level"] = ["in",$levelids];
}
if (is_array($statusids) &&count($statusids)){
$where["status"] = ["in",$statusids];
}
$list = Db::name('member')
->field('uid,nickname,mobile,level,status,last_dial_time,duration,review,reg_time,task')
->where($where)
->group('mobile')
->order('last_dial_time desc')
->page($page,$Page_size)
->select();
$TelConfig = new TelConfig();
$TelScenarios = new TelScenarios();
$MemberGroup = new MemberGroup();
$TelCallRecord = new TelCallRecord();
foreach ($list as $k=>$v){
if($v["reg_time"]){
$list[$k]["reg_time"] = date("Y-m-d H:i:s",$v["reg_time"]);
}else{
$list[$k]["reg_time"] = "--";
}
if(!$v["nickname"]){
$list[$k]["nickname"] = "--";
}
if($v["last_dial_time"]){
$list[$k]["last_dial_time"] = date("Y-m-d H:i:s",$v["last_dial_time"]);
}else{
$list[$k]["last_dial_time"] = "--";
}
}
$count = Db::name('member')
->where($where)
->group('mobile')
->order('reg_time desc')
->count(1);
$page_count = ceil($count/$Page_size);
$back = array();
$back['total'] = $count;
$back['Nowpage'] = $page;
$back['list'] = $list;
$back['page'] = $page_count;
return returnAjax(0,'获取数据成功',$back);
}
public function detail(){
$uid = input('id','','trim,strip_tags');
$memberInfo = Db::name('member')->field('mobile,nickname,status,level,sex,duration,last_dial_time,record_path,call_id')->where('uid',$uid)->find();
if ($memberInfo['call_id']){
$bills = Db::name('tel_bills')->where('call_id',$memberInfo['call_id'])->order('id asc')->select();
}
else{
$bills = Db::name('tel_bills')->where('phone',$memberInfo['mobile'])->order('id asc')->select();
}
if ($memberInfo['sex'] >= 0){
$memberInfo['sex'] = $memberInfo['sex']?'女':'男';
}
else{
$memberInfo['sex'] = '未知';
}
$memberInfo['nickname'] = $memberInfo['nickname']?$memberInfo['nickname']:'--';
$memberInfo['last_dial_time'] = date('Y-m-d H:i:s',$memberInfo['last_dial_time']);
switch ($memberInfo['status']) {
case 1:
$memberInfo['status'] = '排除中';
break;
case 2:
$memberInfo['status'] = '已接通';
break;
case 3:
$memberInfo['status'] = '未接听挂断/关机/欠费';
break;
default:
$memberInfo['status'] = '排除中';
}
$this->assign('memberInfo',$memberInfo);
$this->assign('bills',$bills);
return $this->fetch();
}
function file_exists($url)
{
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_NOBODY,1);
curl_setopt($ch,CURLOPT_FAILONERROR,1);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
echo curl_exec($ch);
if(curl_exec($ch)!==false)
return true;
else
return false;
}
public function backdetail(){
$uid = input('id','','trim,strip_tags');
$froms = input('froms','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$recordId = input('recordId','','trim,strip_tags');
$select_type = input('select_type','','trim,strip_tags');
$select_time = input('select_time','','trim,strip_tags');
if($type == 'historical'){
if($select_type){
$table_name = get_table_name($select_type);
}elseif($select_time){
$table_name = get_table_name_by_time($select_time);
}
}else if($type=='allRecords'){
if(!empty(Db::name('tel_call_record')->where(['task_id'=>$taskId,'mobile'=>$mobile])->find() )){
$table_name = 'tel_call_record';
}else{
if($select_type){
$table_name = get_table_name($select_type);
}elseif($select_time){
$table_name = get_table_name_by_time($select_time);
}
}
}else{
$table_name = 'tel_call_record';
}
if($froms == 'record'){
$Info = Db::name($table_name)
->field('mobile,task_id,status,level,duration,last_dial_time,call_id,call_times,originating_call,record_path,invitations,scenarios_id,affirm_times,neutral_times,negative_times,hit_times,effective_times,neutral_times')
->where('id',$recordId)
->find();
$memberInfo = Db::name('member')
->field('uid,mobile,task,nickname,status,level,sex,duration,semantic_label,knowledge_label,flow_label,last_dial_time,record_path,call_id,call_times,originating_call,call_rotation,semantic_label,knowledge_label')
->where('mobile',$Info['mobile'])
->where('task',$Info['task_id'])
->find();
$memberInfo['affirm_times'] = $Info['affirm_times'];
$memberInfo['neutral_times'] = $Info['neutral_times'];
$memberInfo['negative_times'] = $Info['negative_times'];
$memberInfo['hit_times'] = $Info['hit_times'];
$memberInfo['effective_times'] = $Info['effective_times'];
$memberInfo['neutral_times'] = $Info['neutral_times'];
$memberInfo['mobile'] = isset($memberInfo['mobile']) ?$memberInfo['mobile'] : $Info['mobile'];
$memberInfo['record_path'] = $Info['record_path'];
$memberInfo['status'] = $Info['status'];
$memberInfo['level'] = $Info['level'];
$memberInfo['duration'] = $Info['duration'];
$memberInfo['last_dial_time'] = $Info['last_dial_time'];
$memberInfo['call_id'] = $Info['call_id'];
$memberInfo['call_times'] = $Info['call_times'];
$memberInfo['originating_call'] = $Info['originating_call'];
$memberInfo['task_name'] = getTaskName($taskId);
$memberInfo['speechname'] = getScenariosName($Info['scenarios_id']);
$memberInfo['successyaoyue']=$Info['invitations']==0 ?'否':'是';
$memberInfo['mobile'] = $Info['mobile'];
if(empty($memberInfo['semantic_label'])){
$memberInfo['semantic_label']='无';
}
if(empty($memberInfo['knowledge_label'])){
$memberInfo['knowledge_label']='无';
}
if(empty($memberInfo['flow_label'])){
$memberInfo['flow_label']='无';
}
if(isset($memberInfo['call_rotation']) === false ||empty($memberInfo['call_rotation'])){
$memberInfo['call_rotation'] = 0;
}
if(count($memberInfo)){
$review = array();
$review['review'] = 1;
Db::name($table_name)->where('id',$recordId)->update($review);
$redis = RedisConnect::get_redis_connect();
$redis_key = $table_name.'_review';
$redis->setbit($redis_key,$recordId,1);
$redis->expire($redis_key,3600);
}
}else{
$memberInfo = Db::name('member')
->field('mobile,task,nickname,status,level,sex,duration,last_dial_time,record_path,call_id,call_times,originating_call,call_rotation')
->where('uid',$uid)
->find();
if($memberInfo){
$review = array();
$review['review'] = 1;
Db::name('member')->where('uid',$uid)->update($review);
}else{
return returnAjax(1,'获取失败，该用户不存在或者已经被删除');
}
}
$flow_name = Db::name($table_name)->where('id',$recordId)->value('flow_label');
$knowledge_name = Db::name($table_name)->where('id',$recordId)->value('knowledge_label');
$semantic_name = Db::name($table_name)->where('id',$recordId)->value('semantic_label');
$Keyword_name = $flow_name;
if(!$flow_name &&!$knowledge_name){
$Keyword_name .= $semantic_name;
}else{
if($knowledge_name){
$Keyword_name .= ','.$knowledge_name;
}
if($semantic_name){
$Keyword_name .= ','.$semantic_name;
}
}
if(!$Keyword_name){
$Keyword_name = '暂无数据';
}
$bills = Db::name('tel_bills')->where('call_id',$memberInfo['call_id'])->order('create_time asc')->select();
if(count($bills) == 0 &&$memberInfo['duration'] >0){
$redis = RedisConnect::get_redis_connect();
$redis_key = 'bills-'.$memberInfo['call_id'] .'-'.$memberInfo['mobile'];
$redis_datas = $redis->lrange($redis_key,0,-1);
foreach($redis_datas as $key=>$value){
$new_value = json_decode($value,true);
$bills = array_merge($bills,$new_value);
}
}
foreach($bills as $key=>$value){
if($this->file_exists(Config('history_cut_audio_server_url') .$value['path'])){
$bills[$key]['path'] = Config('history_cut_audio_server_url') .$value['path'];
}else{
$bills[$key]['path'] = Config('cut_audio_server_url') .$value['path'];
}
}
if(!empty($memberInfo['record_path'])){
if($this->file_exists(config("record_path").$memberInfo['record_path'])){
$memberInfo['record_path'] = config("record_path").$memberInfo['record_path'];
}else{
$memberInfo['record_path'] = config("history_record_path").$memberInfo['record_path'];
}
}else{
$memberInfo['record_path'] = '';
}
$memberInfo['last_dial_time'] = date('Y-m-d H:i:s',$memberInfo['last_dial_time']);
$data = array();
$data['memberInfo'] = $memberInfo;
$data['bills'] = $bills;
$data['num'] = $Keyword_name;
Db::name($table_name)->where(['id'=>$recordId,'task_id'=>$taskId,'mobile'=>$mobile])->update(['is_see_call'=>1]);
return returnAjax(0,'获取成功',$data);
}
public function order_datas($datas){
$len = count($datas);
for ($i = 1;$i <$len;$i++) {
$flag = false;
for ($k = 0;$k <$len -$i;$k++) {
if ($datas[$k] >$datas[$k +1]) {
$tmp = $arr[$k +1];
$datas[$k +1] = $datas[$k];
$datas[$k] = $tmp;
$flag = true;
}
}
if(!$flag) return $datas;
}
}
public function phone_list(){
$mobile = input('mobile','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if($mobile){
$where["mobile"] = $mobile;
}
if (!$super){
$where["owner"] = $uid;
}
$phone_list = Db::name('member')->field('uid,last_dial_time')->where($where)->select();
$result = array();
foreach($phone_list as $key =>$value){
if($value['last_dial_time']){
$result[$key]['last_dial_time'] = date('Y-m-d H:i:s',$value['last_dial_time']);
$result[$key]['uid'] = $value['uid'];
}
}
return returnAjax(0,'获取数据成功',$result);
}
public function delMember($mobiles=''){
$user_auth = session('user_auth');
foreach ($mobiles as $k=>$v){
$list = Db::name('member')
->where('mobile',$v)
->where('owner',$user_auth['uid'])
->delete();
if(!$list){
break;
}
}
if(!$list){
echo "删除失败。";
}
}
public function editMember(){
if(IS_POST){
$ctype=array();
$ctype['real_name'] = input('realname');
$ctype['nickname'] = input('nickname');
$ctype['sex'] = input('sex');
$ctype['mobile'] = input('phonenumber','','trim,strip_tags');
$ctype['group_id'] = input('groupId','0','trim,strip_tags');
$uid =  input('mumid');
$user_auth = session('user_auth');
$owner = $user_auth["uid"];
$memberInfo = Db::name('member')->where(array('mobile'=>$ctype['mobile'],'owner'=>$owner))->find();
if ($memberInfo &&$memberInfo['uid'] != $uid){
return returnAjax(1,'号码已存在！');
}
$result = Db::name('member')->where('uid',input('mumid'))->update($ctype);
if($result >= 0){
return returnAjax(0,'success');
}else{
return returnAjax(1,'success');
}
}else{
$id = input('id','','trim,strip_tags');
$mlist = Db::name('member')->where('uid',$id)->find();
$groupList = array();
$groupList = Db::name('member_group')->field('id,name')->where('status',1)->order('id asc')->select();
$this->assign('groupList',$groupList);
$this->assign('dvlist',$mlist);
$this->assign('current','编辑');
return $this->fetch('adddriver');
}
}
public function addMember(){
if(IS_POST){
$taskId = input('taskID','','trim,strip_tags');
$ctype=array();
$ctype['real_name'] = input('nickname');
$ctype['nickname'] = input('nickname');
$ctype['group_id'] = input('groupId','0','trim,strip_tags');
$ctype['username'] = input('phonenumber','','trim,strip_tags');
$ctype['sex'] = input('sex');
$ctype['mobile'] = input('phonenumber','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = array();
$where['mobile'] = $ctype['mobile'];
if($uid){
$where['owner'] = $uid;
}
if($taskId){
$where['task'] = $taskId;
}else{
$where['task'] = "";
}
$memberInfo = Db::name('member')->where($where)->find();
if ($memberInfo){
return returnAjax(1,'号码已存在！');
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$ctype['owner'] = $uid;
$ctype['reg_time'] = time();
$ctype['salt'] = rand_string(6);
$ctype['password'] = md5(substr(input('phonenumber'),5).$ctype['salt']);
$request = request();
$ctype['reg_ip'] = $request->ip(0,true);
$ctype['is_new'] = 1;
if ($taskId){
$telConfig = Db::name('tel_config')->field('scenarios_id')->where('task_id',$taskId)->find();
$ctype['task'] = $taskId;
$ctype['status'] = 1;
if ($telConfig){
$ctype['scenarios_id'] = $telConfig['scenarios_id'];
}
}
$result = Db::name('member')->insertGetId($ctype);
if($result){
if ($taskId){
if (config('db_config1')){
$this->connect = Db::connect('db_config1');
}
else{
$this->connect = Db::connect();
}
$taskdata = array();
$taskdata['number'] = input('phonenumber','','trim,strip_tags');
$timerresult = $this->connect->table('autodialer_number_'.$taskId)->insertGetId($taskdata);
}
return returnAjax(0,'success');
}else{
return returnAjax(1,'success');
}
}else{
$groupList = array();
$groupList = Db::name('member_group')->field('id,name')->where('status',1)->order('id asc')->select();
$this->assign('groupList',$groupList);
$picdata=array();
$this->assign('picdata',$picdata);
$cardpic = array();
$this->assign('cardpic',$cardpic);
$bsnscardpic = array();
$this->assign('bsnscardpic',$bsnscardpic);
$cbgcardpic = array();
$this->assign('cbgcardpic',$cbgcardpic);
$idcardpic2 = array();
$this->assign('idcardpic2',$idcardpic2);
$this->assign('current','新建');
return $this->fetch('adddriver');
}
}
public function importExcelCallMember(){
set_time_limit(60*60*16);
ini_set('memory_limit','256M');
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES ['excel'] ['tmp_name'];
}else{
return returnAjax(1,'上传失败,请下载模板，填好再选择文件上传。');
}
$chaos_num = input('chaos_num','','trim,strip_tags');
$file_types = explode ( ".",$_FILES ['excel'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
$savePath = './uploads/Excel/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy ( $tmp_file,$savePath .$file_name )){
return returnAjax(1,'上传失败');
}
$foo = new \PHPExcel();
$extension = strtolower( pathinfo($file_name,PATHINFO_EXTENSION) );
if($extension == 'xlsx') {
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
else{
$inputFileType = 'Excel5';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$objPHPExcel = $objReader->load($savePath.$file_name,$encode = 'utf-8');
$excelArr = $objPHPExcel->getsheet(0)->toArray();
if (count($excelArr) <2){
return returnAjax(1,'导入的文件没有数据!');
}
unset($excelArr[0]);
foreach($excelArr as $k=>$value){
if(empty($value[1])){
unset($excelArr[$k]);
}
}
$countSum=count($excelArr);
$number_count = $countSum;
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_number_count';
$total_key = 'task_'.$chaos_num .'_total_number_count';
$RedisConnect->set($key,$number_count);
$RedisConnect->set($total_key,$number_count);
$number = [];
foreach($excelArr as $key=>$value){
if(count($value) <4){
$length_null = 4 -count($value);
for($i = 0 ;$i <$length_null ;$i++){
array_push($excelArr[$key],null);
}
}
if(!isset($number[$value[1]])  &&!empty($value[1])){
$number[$value[1]] = 1;
}elseif(!empty($value[1])){
unset($excelArr[$key]);
}
}
$excelArr = $this->blacklistRule($excelArr);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = array();
$taskdata = array();
$totalCnt = 0;
$successCnt = 0;
$long = count($excelArr);
$numlist = array();
$success_count = 0;
$existence_number_rows = [];
$existence_number_count = 0;
$existence_number_count_empty=0;
foreach ( $excelArr as $k =>$v ){
$isMob="/^(13[0-9]|14[579]|15[0-3,5-9]|16[6]|17[0135678]|18[0-9]|19[289])\d{8}$/";
$user['phone'] = trim(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$v[1]));
$totalCnt++;
if(preg_match($isMob,$user['phone'])){
$success_count++;
$user['name'] = trim($v[0]);
$user['status']=1;
$user['member_id'] = $uid;
$user['sex'] = trim($v[2]);
$user['compay_name']= trim($v[3]);
if(!empty($user['phone'])){
$successCnt++;
array_push($data,$user);
array_push($numlist,$user['phone']);
}
}else{
$existence_number_count_empty++;
$number_count--;
}
if($successCnt == 1000 ||$totalCnt == $long){
$where = array();
$where['member_id'] = $uid;
$where['phone']=['in',$numlist];
$where['status']=1;
$mlist = Db::name('crm')->field('member_id,phone')
->where($where)->select();
if(!empty($mlist)){
foreach ($data as $dakey =>$davalue) {
foreach ($mlist as $key =>$value) {
if( $davalue['phone'] == $value['phone']){
if(isset($data[$dakey]) === true ){
unset($data[$dakey]);
$existence_number_count++;
$success_count--;
$existence_number_rows[] = ($dakey +1);
$number_count--;
}
}
}
}
}
foreach($data as $k=>$v){
$data[$k]['create_time']=time();
}
if($data){
$countData = count($data);
$result = Db::name('crm')->insertAll($data);
$number_count = $number_count -$result;
$number_count_key = 'task_'.$chaos_num .'_number_count';
$RedisConnect->set($number_count_key,$number_count);
array_splice($data,0,count($data));
}
$successCnt = 0;
array_splice($numlist,0,count($numlist));
}
}
ini_set('memory_limit','-1');
return returnAjax(0,'总共导入'.$countSum.'条信息,成功导入'.$success_count.'条信息,过滤重复信息'.$existence_number_count.'条',[]);
}
public function effectTmp2(){
$chaos_num = input('chaos_num','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_number_count';
$number_count = $RedisConnect->get($key);
$total_key = 'task_'.$chaos_num .'_total_number_count';
$total_number_count = $RedisConnect->get($total_key);
$number_count = $total_number_count -$number_count;
$data = [];
$data['zongshu'] = $total_number_count;
$data['sy_zongshu'] = $number_count;
if($total_number_count == false){
$data['zongshu'] = '?';
$data['sy_zongshu'] = '?';
$data['baifenbi'] = 0;
return returnAjax(1,'',$data);
}
if($total_number_count == 0){
$data['baifenbi'] = 100;
return returnAjax(1,'',$data);
}else{
$data['baifenbi'] = $number_count / $total_number_count * 100;
$count = $number_count / $total_number_count * 100;
return returnAjax(1,'',$data);
}
}
public function filterRuler($val){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$phone=$val[1];
$rules = Db::name('blacklist_rules')->field('rule')->where(['member_id'=>$uid])->select();
foreach($rules as $k=>$v){
if(strpos($phone,$v['rule'])){
return false;
}
}
return true;
}
public function blacklistRule($phone){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$black_array = DB::name('blacklist_phones')->where('member_id',$uid)->column('phone');
$rule_array =  Db::name('blacklist_rules')->where('member_id',$uid)->column('rule');
if($rule_array){
$rule_array = implode("|",$rule_array);
$pattern = "/".$rule_array."/";
}
foreach ($phone as $key =>$value) {
if(in_array($value[1],$black_array) == true){
unset($phone[$key]);
continue;
}
if(isset($pattern)){
if(preg_match($pattern,$value[1]) == true ){
unset($phone[$key]);
continue;
}
}
}
return $phone;
}
public function effectTmp(){
$task_id = input('task_id','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$task_id .'_number_count';
$number_count = $RedisConnect->get($key);
$total_key = 'task_'.$task_id .'_total_number_count';
$total_number_count = $RedisConnect->get($total_key);
$number_count = $total_number_count -$number_count;
$data = [];
$data['zongshu'] = $total_number_count;
$data['sy_zongshu'] = $number_count;
if($total_number_count == false){
$data['zongshu'] = '?';
$data['sy_zongshu'] = '?';
$data['baifenbi'] = 0;
return returnAjax(1,'',$data);
}
if($total_number_count == 0){
$data['baifenbi'] = 100;
return returnAjax(1,'',$data);
}else{
$data['baifenbi'] = $number_count / $total_number_count * 100;
$count = $number_count / $total_number_count * 100;
return returnAjax(1,'',$data);
}
}
public function importExcel(){
set_time_limit(60*60*16);
ini_set('memory_limit','256M');
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES ['excel']['tmp_name'];
}else{
return returnAjax(1,'上传失败,请下载模板，填好再选择文件上传。');
}
$file_types = explode(".",$_FILES['excel']['name']);
$file_type = $file_types [count ( $file_types ) -1];
$taskId = input('taskID','','trim,strip_tags');
if(empty($taskId) ||empty($_FILES ['excel']['tmp_name'])){
return returnAjax(1,'请选择要导入到哪个任务中');
}
$scenarios_id = Db::name('tel_config')->where('task_id',$taskId)->value('scenarios_id');
$is_variable = Db::name('tel_scenarios')->where('id',$scenarios_id)->value('is_variable');
$savePath = './uploads/Excel/';
if (!is_dir($savePath)){
mkdir($savePath);
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
$objPHPExcel = $objReader->load($savePath.$file_name,$encode = 'utf-8');
$excelArr = $objPHPExcel->getsheet(0)->toArray();
$number_count = count($excelArr) -1;
$Column_arr=$excelArr[0];
if (count($excelArr) <2){
return returnAjax(1,'导入的文件没有数据!');
}
if($is_variable==1){
$audio_variables = Db::name('audio_variable')->where('scenarios_id',$scenarios_id)->select();
if(count($excelArr[0])!=2+count($audio_variables)){
return returnAjax(1,'此任务的话术为变量话术,导入的模板不正确,请修改模板！');
}
foreach($audio_variables as $key=>$audio_variable){
$data_variable[$key]=$audio_variable['annotation'].'_'.$audio_variable['variable_name'];
}
foreach($Column_arr as $key=>$value){
if($key==0 ||$key==1){
continue;
}
if(!in_array($value,$data_variable)){
return returnAjax(1,'此任务的话术为变量话术,导入的模板不正确,请修改模板！');
}
}
}
unset($excelArr[0]);
}else if($extension=='xls'){
$inputFileType = 'Excel5';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
$objPHPExcel = $objReader->load($savePath.$file_name,$encode = 'utf-8');
$excelArr = $objPHPExcel->getsheet(0)->toArray();
$number_count = count($excelArr) -1;
$Column_arr=$excelArr[0];
if (count($excelArr) <2){
return returnAjax(1,'导入的文件没有数据!');
}
if($is_variable==1){
$audio_variables = Db::name('audio_variable')->where('scenarios_id',$scenarios_id)->select();
if(count($excelArr[0])!=2+count($audio_variables)){
return returnAjax(1,'此任务的话术为变量话术,导入的模板不正确,请修改模板！');
}
foreach($audio_variables as $key=>$audio_variable){
$data_variable[$key]=$audio_variable['annotation'].'_'.$audio_variable['variable_name'];
}
foreach($Column_arr as $key=>$value){
if($key==0 ||$key==1){
continue;
}
if(!in_array($value,$data_variable)){
return returnAjax(1,'此任务的话术为变量话术,导入的模板不正确,请修改模板！');
}
}
}
unset($excelArr[0]);
}else if($extension=='txt'){
if($is_variable==1){
return returnAjax(1,'此任务是变量话术,模板不能用txt类型的,请修改模板');
}
if(file_exists($savePath.$file_name)){
$file = fopen($savePath.$file_name,"r");
$excelArr=array();
$i=0;
while(!feof($file))
{
$excelArr[$i][0]='';
$excelArr[$i][1]= fgets($file);
$i++;
}
fclose($file);
$number_count = count($excelArr);
}
}
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$taskId .'_number_count';
$total_key = 'task_'.$taskId .'_total_number_count';
$RedisConnect->set($key,$number_count);
$RedisConnect->set($total_key,$number_count);
$task_status = Db::name('tel_config')
->where([
'task_id'=>$taskId,
'status'=>['in',[3,-1]]
])
->count('task_id');
if($task_status >0){
return returnAjax(1,'已完成的任务不能导入号码');
}
foreach($excelArr as $key=>$value){
if(empty(trim($value[1])) ||strlen(trim($value[1]))==0){
unset($excelArr[$key]);
}
}
$countSum = count($excelArr);
$number = [];
foreach($excelArr as $key=>$value){
if(!isset($number[$value[1]])  &&!empty($value[1])){
$number[$value[1]] = 1;
}elseif(!empty($value[1])){
unset($excelArr[$key]);
}
}
$count_chongfu=$countSum-count($excelArr) ;
$befor_ruler = count($excelArr);
$end_ruler = count($excelArr);
$count_ruler=$befor_ruler-$end_ruler;
$end_black = count($excelArr);
$count_black=$end_ruler-$end_black;
$telConfig = Db::name('tel_config')->field('scenarios_id,member_id')->where('task_id',$taskId)->find();
$data = array();
$taskdata = array();
$totalCnt = 0;
$successCnt = 0;
$count = count($excelArr);
$numlist = array();
$success_count = 0;
$existence_number_rows = [];
$existence_number_count = 0;
foreach($excelArr as $k =>$v){
$variable_box=[];
$isMob="/^\d{8,}$/";
$isTel="/^([0-9]{3,4}-)?[0-9]{7,8}$/";
$user['mobile'] = trim(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$v[1]));
$totalCnt++;
if(preg_match($isTel,$user['mobile'])){
$success_count++;
$user['owner'] = $telConfig['member_id'];
$user['nickname'] = trim($v[0]);
$user['task'] = $taskId;
$user['status'] = 1;
if ($telConfig){
$user['scenarios_id'] = $telConfig['scenarios_id'];
}
if($extension!='txt'){
$Column_num = count($Column_arr);
if($Column_num>2){
for($i=2;$i<$Column_num;$i++){
$key = explode('_',$Column_arr[$i])[0];
$variable_box[$key] = $v[$i];
}
$variable_box=json_encode($variable_box);
}
}
$user['variable_box'] = $variable_box;
if(!empty($user['mobile'])){
$successCnt++;
array_push($data,$user);
$taskuser['number'] = $user['mobile'];
array_push($taskdata,$taskuser);
array_push($numlist,$user['mobile']);
}
}elseif(preg_match($isMob,$user['mobile'])){
$success_count++;
$user['owner'] = $telConfig['member_id'];
$user['nickname'] = trim($v[0]);
$user['task'] = $taskId;
$user['status'] = 1;
if ($telConfig){
$user['scenarios_id'] = $telConfig['scenarios_id'];
}
if($extension!='txt'){
$Column_num = count($Column_arr);
if($Column_num>2){
for($i=2;$i<$Column_num;$i++){
$key = explode('_',$Column_arr[$i])[0];
$variable_box[$key] = $v[$i];
}
$variable_box=json_encode($variable_box);
}
}
$user['variable_box'] = $variable_box;
if(!empty($user['mobile'])){
$successCnt++;
array_push($data,$user);
$taskuser['number'] = $user['mobile'];
array_push($taskdata,$taskuser);
array_push($numlist,$user['mobile']);
}
}else{
$number_count--;
$count_ruler++;
}
if($successCnt == 1000 ||$totalCnt == $count){
$where = array();
if($taskId){
$where['task'] = $taskId;
}else{
$where['task'] = "";
}
$where['owner'] = $telConfig['member_id'];
$where['mobile']=['in',$numlist];
$mlist = Db::name('member')
->field('owner,mobile')
->where($where)
->order('uid asc')
->select();
if(!empty($mlist)){
foreach ($data as $dakey =>$davalue) {
foreach ($mlist as $key =>$value) {
if( $davalue['mobile'] == $value['mobile']){
if(isset($data[$dakey]) === true &&isset($taskdata[$dakey]) === true){
unset($data[$dakey]);
unset($taskdata[$dakey]);
$existence_number_count++;
$success_count--;
$number_count--;
}
}
}
}
}
if ($data){
$result = Db::name('member')->insertAll($data);
$number_count = $number_count -$result;
$number_count_key = 'task_'.$taskId .'_number_count';
$RedisConnect->set($number_count_key,$number_count);
array_splice($data,0,count($data));
}
if ($taskId &&$taskdata){
$fs_num = Db::name('tel_config')->where(['id'=>$taskId])->value('fs_num');
$redis = RedisConnect::get_redis_connect();
$redis_key = 'task_id_fs_num_'.$taskId;
$redis_fs_num = $redis->get($redis_key);
if(!empty($redis_fs_num)){
$ret = Db::connect('db_configs.fs'.$redis_fs_num)->table('autodialer_number_'.$taskId)->insertAll($taskdata);
}else if(!empty($fs_num)){
$ret = Db::connect('db_configs.fs'.$fs_num)->table('autodialer_number_'.$taskId)->insertAll($taskdata);
}
array_splice($taskdata,0,count($taskdata));
}
$successCnt = 0;
array_splice($numlist,0,count($numlist));
}
}
$chongfu = $count_chongfu+$existence_number_count;
ini_set('memory_limit','-1');
if($success_count >0){
$redis = RedisConnect::get_redis_connect();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$now_time = strtotime(date("Y-m-d"));
$incr_key_all_count = "incr_owner_".$uid."_".$now_time."_all_count";
$incr_key_per_task_count = "incr_owner_".$uid."_".$taskId."_".$now_time."_per_task_count";
$redis->incrby($incr_key_all_count,$success_count);
$redis->incrby($incr_key_per_task_count,$success_count);
$redis->expire($incr_key_all_count,86400);
$redis->expire($incr_key_per_task_count,86400);
}
return returnAjax(0,'总共导入'.$countSum.'条,成功导入'.$success_count.'条信息,去掉重复号码'.$chongfu.',号码规则滤掉'.$count_ruler.'条信息,黑名单滤掉'.$count_black.'条信息');
}
public function phone_book(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$box_id = input("id","",'trim,strip_tags');
$task_id = input('task_id','','trim,strip_tags');
if ($task_id){
$telConfig = Db::name('tel_config')
->field('scenarios_id')
->where('task_id',$task_id)
->find();
}
$list = Db::name('tel_phone_data')->where('pid',$box_id)->select();
$data = array();
$taskdata =array();
$successCnt = 0;
$numlist = array();
$count = count($list);
$success_count = 0;
$existence_number_count = 0;
foreach ($list as $key=>$vo){
$successCnt++;
$success_count++;
$user['mobile'] = $vo['queue'];
$user['owner'] = $uid;
$user['nickname'] = $vo['nickname'];
if($task_id){
$user['task'] = $task_id;
$user['status'] = 1;
if ($telConfig){
$user['scenarios_id'] = $telConfig['scenarios_id'];
}
}else{
$user['status'] = 0;
}
array_push($data,$user);
$taskuser['number'] = $user['mobile'];
array_push($taskdata,$taskuser);
array_push($numlist,$user['mobile']);
if($successCnt == 1000 ||$success_count == $count){
$where = array();
if($task_id){
$where['task'] = $task_id;
}else{
$where['task'] = "";
}
$where['owner'] = $uid;
$where['mobile']=['in',$numlist];
$mlist = Db::name('member')->field('owner,mobile')
->where($where)
->select();
if(!empty($mlist)){
foreach ($data as $dakey =>$davalue) {
foreach ($mlist as $key =>$value) {
if( $davalue['mobile'] == $value['mobile']){
if(isset($data[$dakey]) === true &&isset($taskdata[$dakey]) === true){
unset($data[$dakey]);
unset($taskdata[$dakey]);
$existence_number_count++;
$success_count--;
}
}
}
}
}
if($data){
$result = Db::name('member')
->insertAll($data);
array_splice($data,0,count($data));
}
if ($task_id &&$taskdata){
$fs_num = Db::name('tel_config')->where(['id'=>$task_id])->value('fs_num');
$redis = RedisConnect::get_redis_connect();
$redis_key = 'task_id_fs_num_'.$task_id;
$redis_fs_num = $redis->get($redis_key);
if(!empty($fs_num)){
$ret = Db::connect('db_configs.fs'.$fs_num)->table('autodialer_number_'.$task_id)->insertAll($taskdata);
}else if(!empty($redis_fs_num)){
$ret = Db::connect('db_configs.fs'.$redis_fs_num)->table('autodialer_number_'.$task_id)->insertAll($taskdata);
}
array_splice($taskdata,0,count($taskdata));
}
$successCnt = 0;
array_splice($numlist,0,count($numlist));
}
}
ini_set('memory_limit','-1');
if($success_count >0){
$redis = RedisConnect::get_redis_connect();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$now_time = strtotime(date("Y-m-d"));
$incr_key_all_count = "incr_owner_".$uid."_".$now_time."_all_count";
$incr_key_per_task_count = "incr_owner_".$uid."_".$task_id."_".$now_time."_per_task_count";
$redis->incrby($incr_key_all_count,$success_count);
$redis->incrby($incr_key_per_task_count,$success_count);
$redis->expire($incr_key_all_count,86400);
$redis->expire($incr_key_per_task_count,86400);
}
return returnAjax(0,'总共导入'.$count.'条，成功导入'.$success_count.'条信息。');
}
public function copyData(){
$idlist = input('id/a','','trim,strip_tags');
$taskId = input("taskId","",'trim,strip_tags');
$flag = input("flag","",'trim,strip_tags');
if (config('db_config1')){
$connect = Db::connect('db_config1');
}
else{
$connect = Db::connect();
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$where['owner'] = (int)$uid;
if($flag == "join"){
$result = $connect->table('autodialer_number_'.$taskId)->field('id')->order('id asc')->find();
\think\Log::record('result='.json_encode($result));
if($result){
$Maximum = $result["id"];
}
$cwhere = array();
$mobile = input('mobile','','trim,strip_tags');
$startDate = input('startDate','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$startNum = input('startNum','','trim,strip_tags');
$endNum = input('endNum','','trim,strip_tags');
$calltask = input('calltask','','trim,strip_tags');
$levelids = input('levelids/a');
$statusids = input('statusids/a');
$cwhere["user_type"] = 0;
if (!$super){
$cwhere["owner"] = $uid;
}
if($mobile){
$cwhere["mobile"] = $mobile;
}
if($calltask){
$cwhere["task"] = $calltask;
}
if($startDate &&$endTime){
$cwhere["reg_time"] = ["between time",[$startDate,$endTime]];
}
if($startNum>=0 &&$endNum){
$cwhere["duration"] = ["between",[$startNum,$endNum]];
}
else if($startNum >0){
$cwhere["duration"] = [">=",$startNum];
}
if (count($levelids)){
$cwhere["level"] = ["in",$levelids];
}
if (count($statusids)){
$cwhere["status"] = ["in",$statusids];
}
$mList = Db::name('member')->field('uid,mobile,is_new,user_type')->where($cwhere)->select();
$idlist = $mList;
foreach ($idlist as $k=>$v){
$where['uid'] = $v['uid'];
if ($v['user_type']){
continue;
}
if ($v['mobile'] == null){
continue;
}
$numarr = array();
$numarr['number'] = $v['mobile'];
if($v['is_new'] == 1 &&$result){
$Maximum = $Maximum -1;
$numarr['id'] = $Maximum;
}
$connect->table('autodialer_number_'.$taskId)->insertGetId($numarr);
$res = Db::name('member')->where($where)->update(['status'=>1,'task'=>$taskId]);
}
}else if($flag == "all"){
$where['status'] = 0;
$count = Db::name('member')->field('mobile')->where($where)->count(1);
$page = 0;
$pageSize = 30;
$result = $connect->table('autodialer_number_'.$taskId)->field('id')->order('id asc')->find();
if($result){
$Maximum = $result["id"];
}
while($page <$count){
$mlist = Db::name('member')->field('uid,mobile,is_new')->where($where)->limit($pageSize)->select();
foreach ($mlist as $k=>$v){
$numarr = array();
$numarr['number'] = $v['mobile'];
if($v['is_new'] == 1 &&$result){
$Maximum = $Maximum -1;
$numarr['id'] = $Maximum;
}
$connect->table('autodialer_number_'.$taskId)->insertGetId($numarr);
$where['uid'] = $v['uid'];
$res = Db::name('member')->where($where)->update(['status'=>1,'task'=>$taskId]);
}
$page += $pageSize;
\think\Log::record('#####copyData#####page='.$page.'#######count='.$count);
}
}else if($flag == "finish"){
foreach ($idlist as $k=>$v){
$where['uid'] = $v;
$mlist = Db::name('member')->field('mobile,status,task')->where($where)->find();
if($mlist['status'] == 1){
$connect->execute("DELETE FROM `autodialer_number_".$mlist['task']."` WHERE `number` = ".$mlist['mobile']."");
$res = Db::name('member')->where('uid',$v)->update(array('status'=>0));
}
}
}else if($flag == "stopAll"){
\think\Log::record('flag="stopAll"');
$where['status'] = 1;
$idlist = Db::name('member')->field('uid,mobile,status,task')->where($where)->select();
foreach ($idlist as $k=>$v){
if($v['status'] == 1){
$connect->execute("DELETE FROM `autodialer_number_".$v['task']."` WHERE `number` = ".$v['mobile']."");
$res = Db::name('member')->where('uid',$v['uid'])->update(array('status'=>0));
}
}
}
return returnAjax(0,'成功');
}
public function adminList(){
$username = input('username');
$mobile = input('mobile');
$sqlStr = "";
if($username){
$sqlStr = 'username like "%'.$username.'%"';
}
if($mobile){
if($sqlStr){
$sqlStr .= 'or mobile = "'.$mobile.'"';
}else{
$sqlStr = 'mobile = "'.$mobile.'"';
}
}
if ($sqlStr) {
$list = Db::name('master')->field('uid,username,nickname,email,mobile,sex,reg_time,id_card,logo,status')
->where($sqlStr)
->paginate(10,false,array('query'=>$this->param));
}else {
$list = Db::name('master')->field('uid,username,nickname,email,mobile,sex,reg_time,id_card,logo,status')
->paginate(10,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
if($v["reg_time"]){
$list['data'][$k]["reg_time"] = date("Y-m-d H:i:s",$v["reg_time"]);
}else{
$list['data'][$k]["reg_time"] = "";
}
$piclist = Db::name('picture')->field('path')->where('id',$v['logo'])->find();
if($piclist['path']){
$list['data'][$k]['user_logo'] = $piclist['path'];
}else{
$list['data'][$k]['user_logo'] = "/application/user/static/images/innin.png";
}
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function adminStatus(){
$mem_id = $_POST['mem_id'];
$data=array();
$data['status'] = input('status');
$list = Db::name('master')->where('uid','in',$mem_id)->update($data);
if(!$list){
echo "修改失败。";
}
}
public function addAdmin(){
if(IS_POST){
$ctype=array();
$ctype['username'] = input('username');
$ctype['nickname'] = input('nickname');
$ctype['email'] = input('email');
$ctype['logo'] = input('logo');
$ctype['mobile'] = input('mobile');
$ctype['sex'] = input('sex');
$ctype['birthday'] = input('birthday');
$ctype['qq'] = input('qq');
$ctype['score'] = input('score');
$ctype['money'] = input('money');
$ctype['is_admin'] = 1;
$ctype['reg_time'] = time();
$request = request();
$ctype['reg_ip'] = $request->ip(0,true);
$result = Db::name('master')->insertGetId($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "新建成功";
$data['url'] = Url("User/Member/adminList");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "新建失败";
$data['url'] = Url("User/Member/addAdmin");
echo json_encode($data);
}
}else{
$picdata=array();
$this->assign('picdata',$picdata);
$this->assign('create_time',date("Y-m-d H:i:s",time()));
$this->assign('current','新建');
return $this->fetch("addmember");
}
}
public function editAdmin(){
if(IS_POST){
$ctype=array();
$ctype['username'] = input('username');
$ctype['nickname'] = input('nickname');
$ctype['email'] = input('email');
$ctype['logo'] = input('logo');
$ctype['mobile'] = input('mobile');
$ctype['sex'] = input('sex');
$ctype['birthday'] = input('birthday');
$ctype['qq'] = input('qq');
$ctype['score'] = input('score');
$ctype['money'] = input('money');
$result = Db::name('master')->where('uid',input('uid'))->update($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "编辑成功";
$data['url'] = Url("User/Member/adminList");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "编辑失败";
$data['url'] = Url("User/Member/editAdmin",array('id'=>input('uid')));
echo json_encode($data);
}
}else{
$id = input('id');
$mlist =  Db::name('master')->where('uid',$id)->find();
$pic = Db::name('picture')->where('id',$mlist['logo'])->find();
$picdata=array();
if($pic){
$picdata['logo']=$mlist['logo'];
}
$this->assign('picdata',$picdata);
if($mlist["birthday"] == "0000-00-00"){
$mlist["birthday"] = "";
}
$this->assign('mlist',$mlist);
$this->assign('create_time',date("Y-m-d H:i:s",time()));
$this->assign('current','编辑');
return $this->fetch('addmember');
}
}
public function delAdmin($id=''){
foreach ($id as $k=>$v){
$list = Db::name('master')->where('uid',$v)->delete();
if(!$list){
break;
}
}
if(!$list){
echo "删除失败。";
}
}
public function get_microtime_str(){
list($msec,$sec)=explode(' ',microtime());
return  $sec.$msec*1000000;
}
public function add_call()
{
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$line_id = input('line_group_id','','trim,strip_tags');
$asr_id = input('asr_id','','trim,strip_tags');
$joinPhone = input('phone','','trim,strip_tags');
$call_type = 1;
$robot_count = 1;
$start_date =[date('Y-m-d')];
$end_date =[date('Y-m-d',strtotime('+1 day'))];
$start_time=['8:00'];
$end_time=['21:30'];
$status = 1;
if(empty($scenarios_id)){
return $this->Json(2,'请选择话术');
}
if(empty($line_id)){
return $this->Json(2,'请选择线路');
}
if(empty($asr_id)){
return $this->Json(2,'请选择ASR');
}
$line_count = Db::name('tel_line_group')
->where(['id'=>$line_id,'status'=>1])
->count('id');
if(empty($line_count)){
return returnAjax(2,'线路组不存在');
}
$user_auth = session('user_auth');
$usable_robot_cnt = Db::name('admin')
->where("id",$user_auth['uid'])
->value('usable_robot_cnt');
$run_robot_count =  Db::name('tel_config')
->where('member_id',$user_auth['uid'])
->where('status','1')
->sum('robot_cnt');
$usable_robot_count = $usable_robot_cnt -$run_robot_count;
if($robot_count >$usable_robot_count){
return returnAjax(2,'机器人数量不足');
}
$scenarios_count = Db::name('tel_scenarios')
->where('id',$scenarios_id)
->count('id');
if(empty($scenarios_count)){
return returnAjax(2,'话术不存在');
}
$max_destination_extension = Db::name('tel_config')->field('destination_extension')->order('id desc')->find();
if ($max_destination_extension &&$max_destination_extension['destination_extension'] >0){
$destination_extension = ((int)$max_destination_extension['destination_extension'])+1;
}else{
$destination_extension =  config('destination_extension');
}
$task_name ='加入呼叫_'.$this->get_microtime_str().'_'.$joinPhone;
$task_config = [];
$task_config['type'] = 1;
$task_config['fs_num'] = 0;
$task_config['member_id'] = $user_auth['uid'];
$task_config["task_name"] = $task_name;
$task_config["scenarios_id"] = $scenarios_id;
$task_config["call_type"] = $call_type;
$task_config["status"] = $status;
$task_config["destination_extension"] = $destination_extension;
$task_config['robot_cnt'] = $robot_count;
$task_config['create_time'] = time();
$task_config['asr_id'] = $asr_id;
$task_config['call_phone_group_id'] = $line_id;
Db::startTrans();
try{
$task_id = Db::name('tel_config')->insertGetId($task_config);
if(empty($task_id)){
\think\Log::record('创建WEB端的任务配置表失败');
}
Db::name('tel_config')->where(['id'=>$task_id])->update(['task_id'=>$task_id]);
$member_data['task'] = $task_id;
$member_data['status'] = 1;
$member_data['owner'] = $user_auth['uid'];
$member_data['mobile'] = $joinPhone;
Db::name('member')->insertGetId($member_data);
$AutoTaskDate = new AutoTaskDate();
$insert_result = $AutoTaskDate->insert($user_auth['uid'],$task_id,$start_date,$end_date);
if(empty($insert_result)){
\think\Log::record('创建指定日期失败');
}
$AutoTaskTime = new AutoTaskTime();
foreach($start_time as $key=>$value){
$start_time[$key] = $value .':00';
$end_time[$key] = $end_time[$key] .':00';
}
$TaskData = new TaskData();
$result = $TaskData->start_task($task_id);
if($result == true){
Db::commit();
return returnAjax(0,'新建任务成功');
}else{
Db::rollback();
return returnAjax(1,'新建任务失败');
}
}catch (\Exception $e) {
Db::rollback();
\think\Log::record($e->getMessage());
return returnAjax(1,'新建任务失败');
}
}
public function getUser(){
$username = input('username');
$mobile = input('mobile');
$Page_size = 5;
$page = input('page');
if(!$page){
$page = 1;
}
$sqlStr = "";
if($username){
$sqlStr = 'username like "%'.$username.'%"';
}
if($mobile){
if($sqlStr){
$sqlStr .= 'or mobile = "'.$mobile.'"';
}else{
$sqlStr = 'mobile = "'.$mobile.'"';
}
}
if ($sqlStr) {
$list = Db::name('member')->field('uid,username,nickname,email,mobile,sex,reg_time,id_card,logo,status,department,positional')
->where($sqlStr)
->page($page,$Page_size)->select();
}else {
$list = Db::name('member')->field('uid,username,nickname,email,mobile,sex,reg_time,id_card,logo,status,department,positional')
->page($page,$Page_size)->select();
}
foreach ($list as $k=>$v){
if($v["reg_time"]){
$list[$k]["reg_time"] = date("Y-m-d H:i:s",$v["reg_time"]);
}else{
$list[$k]["reg_time"] = "";
}
$piclist = Db::name('picture')->field('path')->where('id',$v['logo'])->find();
if($piclist['path']){
$list[$k]['user_logo'] = $piclist['path'];
}else{
$list[$k]['user_logo'] = "/application/user/static/images/innin.png";
}
}
if ($sqlStr) {
$count = Db::name('member')->where($sqlStr)->count(1);
}else{
$count = Db::name('member')->count(1);
}
$page_count = ceil($count/$Page_size);
$this->assign('Nowpage',$page);
$this->assign('list',$list);
$this->assign('page',$page_count);
return $this->fetch();
}
public function distribution(){
if(IS_POST){
$coor = input('lng').",".input('lat');
$list = Db::name('mall_order')
->field('order_id,order_sn,member_id,consignee,address,add_time')
->where('coordinate',$coor)
->order('add_time desc')
->select();
foreach ($list as $k=>$v){
if($v["add_time"]){
$list[$k]["add_time"] = date("Y-m-d H:i:s",$v["add_time"]);
}else{
$list[$k]["add_time"] = "";
}
$memlist = Db::name('member')->field('username,nickname,logo')->where('uid',$v['member_id'])->find();
$list[$k]['username'] = $memlist['username'];
$list[$k]['nickname'] = $memlist['nickname'];
$goodsdata = Db::name('mall_order_goods')->field('goods_name')
->where(array('order_id'=>$v['order_id']))->find();
$list[$k]["goods_name"] = $goodsdata["goods_name"];
$piclist = Db::name('picture')->field('path')->where('id',$memlist['logo'])->find();
if($piclist['path']){
$list[$k]['user_logo'] = $piclist['path'];
}else{
$list[$k]['user_logo'] = "/application/user/static/images/innin.png";
}
$list[$k]['count'] = count($list);
}
if(count($list)){
return returnAjax(1,'返回成功',$list);
}else{
return returnAjax(0,'error',$list);
}
}else{
$result = Db::name('mall_order')->field('coordinate')->select();
$coordinate = array();
foreach ($result as $k=>$v){
if ($v["coordinate"]){
$temp = explode(",",$v["coordinate"]);
$coordinate[$k]['lng'] = $temp[0];
$coordinate[$k]['lat'] = $temp[1];
}
}
$this->assign('list',$coordinate);
return $this->fetch();
}
}
public function group(){
$arr = array();
$arr['id'] = 0;
$arr['name'] = "无分组";
$arr['status'] = 1;
$arr['remark'] = "没有进行分组的成员";
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if (!$super){
$where['owner'] = $uid;
}
$list = Db::name('member_group')->where($where)->paginate(5,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
$where['group_id'] = 0;
$number = Db::name('member')->field('id')->where($where)->count();
$arr['number'] = $number;
$this->assign('arr',$arr);
foreach($list['data'] as $k=>$v){
$tempnum = Db::name('member')->field('id')->where('group_id',$v['id'])->count();
$list['data'][$k]['number'] = $tempnum;
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function addGroup(){
if(IS_POST){
$ctype=array();
$ctype['name'] = input('name');
$ctype['status'] = input('status');
$ctype['remark'] = input('remark');
$result = Db::name('member_group')->insertGetId($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "新建成功";
$data['url'] = Url("User/Member/group");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "新建失败";
$data['url'] = Url("User/Member/addGroup");
echo json_encode($data);
}
}else{
$this->assign('current','添加');
return $this->fetch();
}
}
public function editGroup(){
if(IS_POST){
$ctype=array();
$ctype['name'] = input('name');
$ctype['status'] = input('status');
$ctype['remark'] = input('remark');
$result = Db::name('member_group')->where('id',input('groupId'))->update($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "编辑成功";
$data['url'] = Url("User/Member/group");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "编辑失败";
$data['url'] = Url("User/Member/editGroup",array('id'=>input('groupId')));
echo json_encode($data);
}
}else{
$id = input('id');
$grouplist =  Db::name('member_group')->where('id',$id)->find();
$this->assign('grouplist',$grouplist);
$this->assign('current','编辑');
return $this->fetch('addgroup');
}
}
public function delGroup($id = ''){
$list = Db::name('member_group')->where('id',$id)->delete();
$result = Db::name('member')->where('group_id',$id)->update(array('group_id'=>0));
if(!$list){
echo "删除失败。";
}
}
public function groupList(){
$groupId = input('groupId');
$sqlStr = 'group_id = "'.$groupId.'"';
$list = Db::name('member')->field('uid,username,nickname,email,mobile,sex,reg_time,id_card,logo,status')
->where($sqlStr)
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
if($v["reg_time"]){
$list['data'][$k]["reg_time"] = date("Y-m-d H:i:s",$v["reg_time"]);
}else{
$list['data'][$k]["reg_time"] = "";
}
$piclist = Db::name('picture')->field('path')->where('id',$v['logo'])->find();
if($piclist['path']){
$list['data'][$k]['user_logo'] = $piclist['path'];
}else{
$list['data'][$k]['user_logo'] = "/application/user/static/images/innin.png";
}
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function importAdmin($user_id=''){
$mlist =  Db::name('member')->where('uid',$user_id[0])->find();
$uresult =  Db::name('master')->where('mobile',$mlist['mobile'])->find();
if($uresult){
echo "保存失败，该用户已经是管理员";
}else{
unset($mlist['uid']);
unset($mlist['department']);
unset($mlist['positional']);
$result = Db::name('master')->insertGetId($mlist);
}
}
public function submitNickname() {
$nickname = input('post.nickname');
$password = input('post.password');
if (empty($nickname)) {
return $this->error('请输入昵称');
}
if (empty($password)) {
return $this->error('请输入密码');
}
$User = new UserApi();
$uid = $User->login(UID,$password,4);
if ($uid == -2) {
return $this->error('密码不正确');
}
$Member = model('User');
$data = $Member->create(array('nickname'=>$nickname));
if (!$data) {
return $this->error($Member->getError());
}
$res = $Member->where(array('uid'=>$uid))->save($data);
if ($res) {
$user = session('user_auth');
$user['username'] = $data['nickname'];
session('user_auth',$user);
session('user_auth_sign',data_auth_sign($user));
return $this->success('修改昵称成功！');
}
else {
return $this->error('修改昵称失败！');
}
}
public function changeStatus($method = null) {
$id = array_unique((array)input('id',0));
if (in_array(config('user_administrator'),$id)) {
return $this->error("不允许对超级管理员执行该操作!");
}
$id = is_array($id) ?implode(',',$id) : $id;
if (empty($id)) {
return $this->error('请选择要操作的数据!');
}
$map['uid'] = array('in',$id);
switch (strtolower($method)) {
case 'forbiduser':
$this->forbid('Member',$map);
break;
case 'resumeuser':
$this->resume('Member',$map);
break;
case 'deleteuser':
$this->delete('Member',$map);
break;
default:
return $this->error('参数非法');
}
}
public function myOrder(){
$where = array();
$member_id = trim(input('id'));
if($member_id != ''){
$where["from_memer_id"] = $member_id;
}
$list = Db::name('express')->where($where)->order('create_time desc')->paginate(10,false,array('query'=>$this->param));
$amounttotal = Db::name('express')->where($where)->sum('real_pay_money');
$total = Db::name('express')->where($where)->count(1);
$page = $list->render();
$list = $list->toArray();
foreach($list['data'] as $k=>$v){
if($v['express_type'] == 1 ||$v['express_type'] == 2 ||$v['express_type'] == 4){
$list['data'][$k]['to'] = json_decode($v['to'],true);
}
}
$this->assign('orderList',$list['data']);
$this->assign('page',$page);
$this->assign('amounttotal',$amounttotal);
$this->assign('total',$total);
return $this->fetch("myorder");
}
public function uploadimg(){
$picdata = input('picdata');
$date = date("Ymd",time());
$dir = "./uploads/picture/".$date;
if (!is_dir($dir)){
mkdir($dir);
}
$str = explode(',',$picdata);
$string = explode(';',$str[0]);
$type = explode('/',$string[0]);
$tmp = base64_decode($str[1]);
$fp = "./uploads/picture/".$date."/".time().rand(1,100).".".$type[1];
if (!file_exists($fp)){
file_put_contents($fp,$tmp);
}else{
$string = "fuben";
$fp = "./uploads/picture/".$date."/".time().rand(1,100).$string.".".$type[1];
file_put_contents($fp,$tmp);
}
$backurl = ltrim($fp,".");
$backurl = ltrim($backurl,"/");
$return['url'] = config('res_url').$backurl;
$return['code'] = 1;
echo json_encode($return);
}
public function set(){
if (IS_POST){
$act = input('act');
$data['reg_reward'] = input('reg_reward');
$data['invite_reward_l1'] = input('invite_reward1');
$data['invite_reward_l2'] = input('invite_reward2');
$data['invite_reward_l3'] = input('invite_reward3');
if ($act == 'insert'){
$ret = Db::name('member_set')->insert($data);
}
else{
$ret = Db::name('member_set')->where('id',1)->update($data);
}
return returnAjax();
}else{
$memberSet = Db::name('member_set')->find();
$this->assign('act',$memberSet?'update':'insert');
$this->assign('set',$memberSet);
return $this->fetch();
}
}
public function orderList(){
$where = array();
$member_id = trim(input('id','','trim,strip_tags'));
if($member_id != ''){
$where["express_id"] = $member_id;
}
$list = Db::name('express')->where($where)->order('create_time desc')->paginate(10,false,array('query'=>$this->param));
$total = Db::name('express')->where($where)->count(1);
$page = $list->render();
$list = $list->toArray();
foreach($list['data'] as $k=>$v){
if($v['express_type'] == 1 ||$v['express_type'] == 2 ||$v['express_type'] == 4){
$list['data'][$k]['to'] = json_decode($v['to'],true);
}
}
$this->assign('orderList',$list['data']);
$this->assign('page',$page);
$this->assign('total',$total);
return $this->fetch();
}
public function changeLevel(){
$level = input('level');
$uid = input('id');
$froms = input('froms');
$res = Db::name('tel_call_record')->where('id',$uid)->update(array('level'=>$level));
if ($res >= 0){
return returnAjax(0,'修改成功');
}
else{
return returnAjax(1,'修改失败');
}
}
public function changelevel_his(){
$level = input('level');
$uid = input('id');
$day = input('day');
$froms = input('froms');
$date = date('Ymd',(time()-$day*24*3600));
$res = Db::name('tel_call_record_'.$date)->where('id',$uid)->update(array('level'=>$level));
if ($res >= 0){
return returnAjax(0,'修改成功');
}
else{
return returnAjax(1,'修改失败');
}
}
public function old_callrecord(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$mobile = input('keyword','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
if($mobile){
$where["m.mobile"] = $mobile;
}
if (!$super){
$where["m.owner"] = $uid;
}
$where["m.status"] = ['>',0];
if($status >0){
$where["m.status"] = $status;
}
$startNum = input('startNum','','trim,strip_tags');
$endNum = input('endNum','','trim,strip_tags');
if($startNum>=0 &&$endNum){
$where["m.duration"] = ["between",[$startNum,$endNum]];
}
else if($startNum >0){
$where["m.duration"] = [">=",$startNum];
}
$level = input('level','','trim,strip_tags');
if($level){
$where["m.level"] = $level;
}
$list = Db::name('member')
->field('g.name,m.uid,m.username,m.nickname,m.mobile,m.last_dial_time,m.status,m.task,m.uid,m.level,m.duration,m.review')
->alias('m')
->join('member_group g','g.id = m.group_id','LEFT')
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
if($level){
$cwhere["level"] = $level;
}
if (!$super){
$cwhere["owner"] = $uid;
}
$count = Db::name('member')->where($cwhere)->count(1);
$this->assign('mList',$list['data']);
$this->assign('page',$page);
$this->assign('total',$count);
return $this->fetch();
}
public function callrecord(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = (int)$uid;
}
$where['status'] = [">=",0];
$tasklist = Db::name('tel_config')->field('id,task_id,task_name')->where($where)->select();
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('tasklist',$tasklist);
$this->assign('scenarioslist',$scenarioslist);
$label = array();
if(!$super){
$label['member_id'] = $uid;
}
$label['type'] = 0;
$semanticLabels = Db::name('tel_label')->where($label)->order('id asc')->select();
$this->assign('semanticLabels',$semanticLabels);
$config = array();
if(!$super){
$config['member_id'] = $uid;
}
$config['status'] = ['>',-1];
$list = Db::name('tel_config')->field('id,task_id,task_name')->where($config)->order('id desc')->select();
$this->assign('list',$list);
return $this->fetch();
}
public function callLog(){
$type = input('type','','trim,strip_tags');
$Page_size = 10;
$page = input('page','1','trim,strip_tags');
$calltask = input('calltask','','trim,strip_tags');
$scenarios = input('scenarios','','trim,strip_tags');
$startDate = input('startDate','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
$startNum = input('startNum','','trim,strip_tags');
$endNum = input('endNum','','trim,strip_tags');
$levelids = input('levelids/a');
$statusids = input('statusids/a');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if($scenarios){
$where["scenarios_id"] = $scenarios;
}
if($calltask){
$where["task_id"] = $calltask;
}
if (!$super){
$where["owner"] = $uid;
}
if(!$type){
$todaystart = strtotime(date('Y-m-d'.'00:00:00',time()));
$todayend = strtotime(date('Y-m-d'.'00:00:00',time()+3600*24));
$where["last_dial_time"] = ["between time",[$todaystart,$todayend]];
}else{
if($startDate &&$endTime){
$where["last_dial_time"] = ["between time",[$startDate,$endTime]];
}
}
if($startNum>=0 &&$endNum){
$where["duration"] = ["between",[$startNum,$endNum]];
}else if($startNum >0){
$where["duration"] = [">=",$startNum];
}
if (is_array($levelids) &&count($levelids)){
if(!empty($levelids)){
$where["level"] = ["in",$levelids];
}
}
if (is_array($statusids) &&count($statusids)){
if(!empty($statusids)){
$where["status"] = ["in",$statusids];
}
}
$list = Db::name('tel_call_record')
->field('id,owner,mobile,task_id,scenarios_id,review,originating_call,affirm_times,
						negative_times,neutral_times,effective_times,hit_times,flow_label,
						call_times,semantic_label,status,level,last_dial_time,duration')
->where($where)
->order('id desc')
->page($page,$Page_size)
->select();
foreach($list as $key =>$item){
$mlist = Db::name('admin')->field('username')->where("id",$item['owner'])->find();
if($mlist){
$list[$key]['username'] = $mlist['username'];
}
$customer_name = Db::name('member')->field('nickname')->where('mobile',$item['mobile'])->find();
if($customer_name){
$list[$key]['customer_name'] = $customer_name;
}
$tlist = Db::name('tel_config')->field('task_name')->where("task_id",$item['task_id'])->find();
if($tlist){
$list[$key]['task_name'] = $tlist['task_name'];
}
$slist = Db::name('tel_scenarios')->field('name')->where("id",$item['scenarios_id'])->find();
if($slist){
$list[$key]['scenename'] = $slist['name'];
}
if ($item['last_dial_time'] >0){
$list[$key]['last_dial_time'] = date('Y-m-d H:i:s',$item['last_dial_time']);
}
else{
$list[$key]['last_dial_time'] = "";
}
}
$count =  Db::name('tel_call_record')->where($where)->count(1);
$page_count = ceil($count/$Page_size);
$back = array();
$back['total'] = $count;
$back['Nowpage'] = $page;
$back['list'] = $list;
$back['page'] = $page_count;
$back['uid'] = $uid;
$back['where'] = $where;
$back['levelids'] = $levelids;
$back['statusids'] = $statusids;
return returnAjax(0,'获取数据成功',$back);
}
public function whitelist(){
$mobile = input('mobile','','trim,strip_tags');
$startDate = input('startDate','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if($mobile){
$where["m.mobile"] = $mobile;
}
if (!$super){
$where["m.owner"] = $uid;
}
if($startDate &&$endTime){
$where["m.reg_time"] = ["between time",[$startDate,$endTime]];
}
$where["m.user_type"] = 1;
$list = Db::name('member')
->field('g.name,m.uid,m.username,m.nickname,m.mobile,m.reg_time,m.status,m.uid')
->alias('m')
->where($where)
->join('member_group g','g.id = m.group_id','LEFT')
->order('m.reg_time desc')
->paginate(10,false,array('query'=>$this->param));
$total = Db::name('member')->alias('m')->where($where)->count(1);
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
if($v["reg_time"]){
$list['data'][$k]["reg_time"] = date("Y-m-d H:i:s",$v["reg_time"]);
}else{
$list['data'][$k]["reg_time"] = "";
}
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
$this->assign('total',$total);
$groupList = array();
$groupList = Db::name('member_group')->field('id,name')->where('status',1)->order('id asc')->select();
$this->assign('groupList',$groupList);
return $this->fetch();
}
public function addwhite(){
if(IS_POST){
$ctype=array();
$ctype['real_name'] = input('realname');
$ctype['nickname'] = input('nickname');
$ctype['group_id'] = input('groupId','0','trim,strip_tags');
$ctype['username'] = input('phonenumber','','trim,strip_tags');
$ctype['sex'] = input('sex');
$ctype['mobile'] = input('phonenumber','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$ctype['owner'] = $uid;
$ctype['reg_time'] = time();
$ctype['salt'] = rand_string(6);
$ctype['password'] = md5(substr(input('phonenumber'),5).$ctype['salt']);
$request = request();
$ctype['reg_ip'] = $request->ip(0,true);
$ctype['is_new'] = 1;
$ctype['user_type'] = 1;
$result = Db::name('member')->insertGetId($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "添加成功";
$data['url'] = Url("User/Express/template");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "添加失败";
$data['url'] = Url("User/Express/add");
echo json_encode($data);
}
}
else{
$groupList = array();
$groupList = Db::name('member_group')->field('id,name')->where('status',1)->order('id asc')->select();
$this->assign('groupList',$groupList);
$this->assign('current','新建');
return $this->fetch('addwhite');
}
}
public function editwhite(){
if(IS_POST){
$ctype=array();
$ctype['nickname'] = input('nickname');
$ctype['sex'] = input('sex');
$ctype['mobile'] = input('phonenumber','','trim,strip_tags');
$ctype['group_id'] = input('groupId','0','trim,strip_tags');
$result = Db::name('member')->where('uid',input('mumid'))->update($ctype);
if($result >= 0){
$data = array();
$data['code'] = 1;
$data['msg'] = "编辑成功";
$data['url'] = Url("User/Member/memberList");
return returnAjax(1,'编辑成功',$data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "编辑失败";
$data['url'] = Url("User/Member/editMember",array('id'=>input('uid')));
return returnAjax(1,'编辑失败',$data);
}
}else{
$id = input('id','','trim,strip_tags');
$mlist = Db::name('member')->where('uid',$id)->find();
$groupList = array();
$groupList = Db::name('member_group')->field('id,name')->where('status',1)->order('id asc')->select();
$this->assign('groupList',$groupList);
$this->assign('dvlist',$mlist);
$this->assign('current','编辑');
return $this->fetch('addwhite');
}
}
public function getwhite(){
$id = input('id','','trim,strip_tags');
$mlist = Db::name('member')->where('uid',$id)->find();
return returnAjax(0,'获取数据成功',$mlist);
}
public function exportExcel()
{
$columName = ['客户号码'];
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$cwhere = array();
$mobile = input('mobile','','trim,strip_tags');
$levelids = input('levelids/a');
$statusids = input('statusids/a');
$usercheck = input('usercheck/a','','trim,strip_tags');
if (!$super){
$cwhere["owner"] = $uid;
}
if($mobile){
$cwhere["mobile"] = $mobile;
}
if(is_array($levelids) === true){
if (count($levelids)){
$cwhere["level"] = ["in",$levelids];
}
}
if(is_array($statusids) === true){
if (count($statusids)){
$cwhere["status"] = ["in",$statusids];
}
}
if(is_array($usercheck) === true &&count($usercheck) >0){
$cwhere['id'] = ['in',$usercheck];
}
$mList = Db::name('tel_call_record')
->field('mobile')
->where($cwhere)
->order('id asc')
->select();
$list = $mList;
$setTitle='Sheet1';
$fileName='文件名称';
if ( empty($columName) ||empty($list) ) {
return returnAjax(2,'导出数据不能为空');
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(2,'列名跟数据的列不一致');
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
public function redial(){
if (config('db_config1')){
$connect = Db::connect('db_config1');
}
else{
$connect = Db::connect();
}
$task = input('task','','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
$res = $connect->execute("UPDATE `autodialer_number_".$task."` SET state = 0 WHERE `number` = ".$mobile." limit 1");
if ($res >=0 ){
return  returnAjax(0,'添加到拨打对列成功');
}
else{
return  returnAjax(1,'添加到拨打对列失败');
}
}
public function exportmemberExcel()
{
$columName = ['通话记录号码'];
$recordId = input('usercheck/a','','trim,strip_tags');
if(is_array($recordId) === true &&count($recordId) >0){
if(!empty($recordId)){
$cwhere['id'] = ['in',$recordId];
}
}
$mList = Db::name('tel_call_record')
->field('mobile')
->where($cwhere)
->order('id asc')
->select();
$list = $mList;
$setTitle='Sheet1';
$fileName='文件名称';
if ( empty($columName) ||empty($list) ) {
return returnAjax(2,'导出数据不能为空');
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(2,'列名跟数据的列不一致');
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
public function exportmobileExcel(){
$columName = ['通话记录号码'];
$cwhere = array();
$usercheck = input('usercheck/a','','trim,strip_tags');
if(is_array($usercheck) === true &&count($usercheck) >0){
$cwhere['mobile'] = ['in',$usercheck];
}
$mList = Db::name('tel_call_record')
->field('mobile')
->where($cwhere)
->order('id asc')
->select();
$list = $mList;
$setTitle='Sheet1';
$fileName='文件名称';
if ( empty($columName) ||empty($list) ) {
return returnAjax(2,'导出数据不能为空');
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(2,'列名跟数据的列不一致');
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
public function exportlistExcel(){
$columName = ['意向等级','客户号码','客户姓名','任务名称','话术名称','交互次数','通话状态','拨打时间','通话时长'];
$cwhere = array();
$usercheck = input('usercheck/a','','trim,strip_tags');
if(is_array($usercheck) === true &&count($usercheck) >0){
$cwhere['id'] = ['in',$usercheck];
}
$mList = Db::name('tel_call_record')
->field('mobile,task_id,scenarios_id,level,call_times,status,last_dial_time,duration')
->where($cwhere)
->order('id asc')
->select();
$list = array();
foreach($mList as $key =>$item){
switch ($item['level']) {
case 6:
$list[$key]['level'] = 'A类';
break;
case 5:
$list[$key]['level'] = 'B类';
break;
case 4:
$list[$key]['level'] = 'C类';
break;
case 3:
$list[$key]['level'] = 'D类';
break;
case 2:
$list[$key]['level'] = 'E类';
break;
default:
$list[$key]['level'] = 'F类';
}
$list[$key]['mobile'] = $item['mobile'];
$customer_name = Db::name('member')->field('nickname')->where('mobile',$item['mobile'])->find();
if($customer_name){
$list[$key]['customer_name'] = $customer_name;
}else{
$list[$key]['customer_name'] = '暂无数据';
}
$tlist = Db::name('tel_config')->field('task_name')->where("task_id",$item['task_id'])->find();
if($tlist){
$list[$key]['task_name'] = $tlist['task_name'];
}else{
$list[$key]['task_name'] = "暂无数据";
}
$slist = Db::name('tel_scenarios')->field('name')->where("id",$item['scenarios_id'])->find();
if($slist){
$list[$key]['scenename'] = $slist['name'];
}else{
$list[$key]['scenename'] = "暂无数据";
}
$list[$key]['call_times'] = $item['call_times'];
switch ($item['status']) {
case 3:
$list[$key]['status'] = '未接听挂断/关机/欠费';
break;
case 2:
$list[$key]['status'] = '已接通';
break;
case 1:
$list[$key]['status'] = '拨打排队中';
break;
default:
$list[$key]['status'] = '未拨打';
}
$list[$key]['last_dial_time'] = date("Y-m-d H:i:s",$item['last_dial_time']);
$list[$key]['duration'] = $item['duration'];
}
$setTitle='Sheet1';
$fileName='文件名称';
if ( empty($columName) ||empty($list) ) {
return returnAjax(2,'导出数据不能为空');
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(2,'列名跟数据的列不一致');
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
public function getLable(){
$sceneId = input('sceneId','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$label = array();
if(!$super){
$label['member_id'] = $uid;
}
$label['type'] = ['>',0];
if($sceneId){
$label['scenarios_id'] = $sceneId;
}
$flowLabels = Db::name('tel_label')->field('id,label,keyword,type')->where($label)->order('id asc')->select();
return returnAjax(0,'获取数据成功',$flowLabels);
}
public function delelte_tel_bills()
{
$ids = input('ids/a','','trim,strip_tags');
if(empty($ids) === true ||is_array($ids) === false ||count($ids) === 0){
return returnAjax(2,'参数错误');
}
$result = Db::name('tel_call_record')
->where('id','in',$ids)
->delete();
if(!empty($result)){
return returnAjax(0,'成功');
}
return returnAjax(1,'失败');
}
public function insert_crm(){
$crm = array();
$crm['member_id'] = input('member_id','','trim,strip_tags');
$crm['phone'] = input('phone','','trim,strip_tags');
$crm_phone = Db::name('crm')->field('phone')->where('member_id',$crm['member_id'])->select();
foreach ($crm_phone as $key =>$value) {
if($value['phone'] == $crm['phone']){
return returnAjax(3,'已经在crm列表中');
}
}
$userinfo = Db::name('admin')->field('username,sex')->where('id',$crm['member_id'])->find();
$crm['name']  = $userinfo['username'];
$crm['sex'] = $userinfo['sex'];
$memberinfo = Db::name('member')->field('call_times')->where('mobile',$crm['phone'])->find();
if(empty($memberinfo['call_times'])){
$crm['call_times'] = 0;
}else{
$crm['call_times'] = $memberinfo['call_times'];
}
if(empty($crm['member_id']) ||empty($crm['phone'])){
return returnAjax(2,'用户id或者电话为空');
}
$crm['create_time'] = strtotime(date('Y-m-d H:i:s',time()));
$insertId = Db::name('crm')->insertGetId($crm);
if(!empty($insertId)){
return returnAjax(0,'成功',$crm['phone']);
}else{
return returnAjax(1,'失败');
}
}
public function get_constomerDetail(){
$id = input('id','','trim,strip_tags');
$info = DB::name('crm')->where('id',$id)->find();
if(!empty($info)){
$info['phone']=hide_phone_middle($info['phone']);
}
return returnAjax(1,'获取数据成功',$info);
}
public function ajax_transaction_order(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$crm_id = input('crm_id','','trim,strip_tags');
$page  = input('page','','trim,strip_tags');
$page_size  = input('limit','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$page_size){
$page_size = 4 ;
}
$where = [];
$where['owner'] = array('eq',$uid);
$where['crm_id'] = array('eq',$crm_id);
$list = DB::name('transaction_order')->where($where)->page($page,$page_size)->order('create_time','desc')->select();
$count = DB::name('transaction_order')->where($where)->count();
$pageCount = ceil($count / $page_size);
$data = [];
$data['list'] =$list;
$data['pageNo'] = $page_size ;
$data['pageCount'] = $pageCount;
$data['count'] = $count;
return returnAjax(1,'列表获取成功',$data);
}
public function add_transaction_order(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = [];
$data['order_name'] = input('order_name','','trim,strip_tags');
$data['owner'] = $uid;
$data['transaction_date'] = input('transaction_date','','trim,strip_tags');
if($data['transaction_date']){
$data['transaction_date'] = strtotime($data['transaction_date']);
}else{
$data['transaction_date'] = time();
}
$data['product_name'] = input('product_name','','trim,strip_tags');
$data['number'] = input('number','','trim,strip_tags');
$data['money'] = input('money','','trim,strip_tags');
$data['salesman'] = input('salesman','','trim,strip_tags');
$data['remarks'] = input('remarks','','trim,strip_tags');
$data['create_time'] = time();
$id = input('id','','trim,strip_tags');
$data['crm_id'] = input('crm_id','','trim,strip_tags');
if($id){
$res = Db::name('transaction_order')->where('id',$id)->update($data);
if($res){
return returnAjax(0,'修改成功');
}else{
return returnAjax(1,'修改失败');
}
}else{
$res = Db::name('transaction_order')->insert($data);
if($res){
return returnAjax(0,'添加成功');
}else{
return returnAjax(1,'添加失败');
}
}
}
public function get_transaction_order(){
$id = input('id','','trim,strip_tags');
$info = Db::name('transaction_order')->where('id',$id)->find();
if($info){
return returnAjax(1,'获取数据成功',$info);
}else{
return returnAjax(0,'获取数据失败');
}
}
public function delOrder(){
$id = input('id','','trim,strip_tags');
$res = Db::name('transaction_order')->where('id',$id)->delete();
if($res){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
public function timer ($name = 'default',$unset_timer = TRUE)
{
static $timers = array();
if ( isset( $timers[$name ] ) )
{
list($s_sec,$s_mic) = explode(' ',$timers[$name ]);
list($e_sec,$e_mic) = explode(' ',microtime());
if ( $unset_timer )
unset( $timers[$name ] );
return $e_sec -$s_sec +( $e_mic -$s_mic );
}
$timers[$name ] = microtime();
}
public function get_call_phone(){
$user_auth=session('user_auth');
$uid=$user_auth['uid'];
$id=input('id','trim strip_tags');
$tableName='crm';
$whereArr=['id'=>$id];
$info=Db::name($tableName)->where($whereArr)->find();
if(!empty($info)){
return  returnAjax(0,'获取成功',$info);
}
return returnAjax(1,'数据为空');
}
}
