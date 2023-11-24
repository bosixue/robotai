<?php 

namespace app\user\controller;
use app\common\controller\User;
use Flc\Dysms\Client;
use Flc\Dysms\Request\SendSms;
use think\Db;
use think\Session;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\TaskData;
use app\common\controller\Encrypt;
use app\common\controller\RobotDistribution;
use app\common\controller\RedisApiData;
use app\common\controller\RedisConnect;
class Extension extends User{
private $method;
private $uid;
private $pid;
private $username;
private $role_id;
private $time;
private $pageLimit=10;
private $recharge_in_future;
private $middleData;
private $postData;
private $pageData;
private $encrypt;
private $RedisApiData;
public function _initialize() {
parent::_initialize();
$request = request();
$this->method  = $request->method();
$this->getInfo();
$this->getPostDatas();
$this->assign('pageLimit',$this->pageLimit);
\think\Log::init( ['path'=>LOG_PATH.'/extension'] ) ;
}
public function getInfo(){
if(!$this->uid){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if(!$uid)return false;
$this->uid=$uid;
}
$this->time=time();
$this->encrypt=new Encrypt();
$this->RedisApiData=new RedisApiData();
$userinfo = Db::name('admin')->where('id',$this->uid)->find();
$this->role_id=$userinfo['role_id'];
$this->pid=$userinfo['pid'];
$this->username=$userinfo['username'];
if($this->role_id>=20){
$line_group_info = Db::name('tel_line_group')->where('user_id',$this->pid)->select();
$this->assign('isseat',1);
}else{
$line_group_info = Db::name('tel_line_group')->where('user_id',$this->uid)->select();
$this->assign('isseat',0);
}
$this->assign('lineinfo',$line_group_info);
$line_group_info_byid = [];
foreach($line_group_info as $v){
$line_group_info_byid [$v['id'] ]= $v;
}
$this->assign('line_group_info_byid',$line_group_info_byid);
}
public function getPostDatas(){
if($this->view->isseat){
$this->postData['user_id'] = $this->pid;
}else{
$this->postData['user_id'] = $this->uid;
}
$postArr=['extension_account','id','cname','underuser_id','tel_line_id','extension_ip','default','extension_pass','call_unique_id'];
$this->assignMent($postArr,'postData');
$this->pageData['page'] =input('page','1','trim,strip_tags')??1;
$this->pageData['pageLimit']  =input('pageLimit',$this->pageLimit,'trim,strip_tags')??$this->pageLimit;
}
public function assignMent($arr,$variable){
if(!is_array($arr)){
input($arr,'','trim,strip_tags')&&$this->$variable[$arr]  =input($arr,'','trim,strip_tags');
return;
}
foreach($arr as $v){
input($v,'','trim,strip_tags')&&$this->$variable[$v]  =input($v,'','trim,strip_tags');
}
}
public function index()
{
$where=$this->postData;
$extensionsDb=Db::name('tel_extension');
$count=$extensionsDb->where($where)->count('*');
$extensionsDbPage=$extensionsDb->where($where)->paginate($this->pageData['pageLimit'])->render();
$extensions=$extensionsDb->where($where)->page($this->pageData['page'],$this->pageData['pageLimit'])->select();
$extensions=array_map(function($v){
$v['extension_pass'] =$this->encrypt->encrypt($v['extension_pass'] ,'D');
return $v;
},$extensions);
$this->assign('currentPage',$this->pageData['page']);
$this->assign('pageStr',$extensionsDbPage);
$this->assign('count',$count);
$this->assign('extensions',$extensions);
return $this->fetch();
}
public function ajaxExtension()
{
$where=$this->postData;
$extensionsDb=Db::name('tel_extension');
if(!empty($where['extension_account']) )$where['extension_account']=['like','%'.$where['extension_account'].'%'];
$count=$extensionsDb->where($where)->count('*');
$extensions=$extensionsDb->where($where)->page($this->pageData['page'],$this->pageData['pageLimit'])->select();
$extensions=array_map(function($v){
$v['extension_pass'] =$this->encrypt->encrypt($v['extension_pass'] ,'D');
$v['create_time'] =date('Y-m-d H:i:s',$v['create_time']);
$v['tel_line_name'] =$this->view->line_group_info_byid[$v['tel_line_id'] ]['name']??'';
return $v;
},$extensions);
$data['totalCount']=$count;
$data['data']=$extensions;
if($extensions){
return    returnAjax(0,'获取成功',$data);
}else{
return   returnAjax(1,'获取失败',$data);
}
}
public function setDefault(){
$where=$this->postData;
$extensionsDb=Db::name('tel_extension');
$extensionsDb->where(['user_id'=>$where['user_id']])->update(['default'=>0] );
$res=$extensionsDb->where($where)->update(['default'=>1]);
if($res){
return    returnAjax(0,'设置成功');
}else{
return   returnAjax(1,'设置失败');
}
}
public function add()
{
$where=$this->postData;
$where['user_id']=$this->uid;
$where['create_time']=$this->time;
$where['extension_pass'] =$this->encrypt->encrypt($where['extension_pass'] ,'E');
$where['status'] =$where['status']??1;
if( empty($where['tel_line_id'])||empty($where['extension_account'])||empty($where['extension_pass']) ){
return $this->Json(3,'传入信息不完整。');
}
if(!empty($where['id']) ){
$result = Db::name('tel_extension')->where('id',$where['id']) ->update($where);
}else{
$result = Db::name('tel_extension') ->insertGetId($where);
}
if(!empty($result)){
return $this->Json(0,'操作成功');
}
return $this->Json(1,'操作失败');
}
public function del()
{
$extensionDb=Db::name('tel_extension');
$where=$this->postData;
$ids = input('ids/a',[],'trim,strip_tags');
if(!empty($where['extension_id']) )$where['extension_id']=['like','%'.$where['extension_id'].'%'];
$extensionDb->where($where);
if($ids[0] !='all'){
$extensionDb->where(['id'=>['in',$ids] ]);
}
$result=$extensionDb->delete();
if(!empty($result)){
return $this->Json(0,'删除成功');
}
return $this->Json(1,'删除失败');
}
public function startCalling(){
$where=$this->postData;
$where['charge_id']=$where['user_id'];
$where['user_id']=session('user_auth')['uid'];
$where['extension_id']=$this->getDefaultExtension()['id'];
$haveNoMoneyId=$this->getUserHaveNoMoney();
if($haveNoMoneyId){
$userInfo=$this->getOneRelativeInfos($haveNoMoneyId);
return returnAjax('1','用户'.$userInfo['name'].'欠费，影响到您这次拨打。');
}
$where['tel_line_id']=$this->getOneRelativeInfos($where['extension_id'],'extension')['tel_line_id'];
$where['flag']=1;
$where['create_time']=$this->time;
$insertFlag   =    Db::name('sipphone_record')->insert($where);
if($insertFlag){
return returnAjax('0');
}else{
return returnAjax('1','操作失败');
}
}
public function extensionCharge()
{
$destination_number = input('destination_number','','trim,strip_tags');
$duration = input('duration','','trim,strip_tags');
$call_unique_id = input('call_unique_id','','trim,strip_tags');
if($duration<0)return false;
if(!$call_unique_id)return false;
$beginCallDetails=$this->getSipphoneRecord(session('user_auth')['uid'],$call_unique_id );
if(!$beginCallDetails)return false;
$call_begin_time=$beginCallDetails['create_time'];
$call_end_time=$this->time;
$charged_user_id=$beginCallDetails['charge_id'];
$extension_id=$beginCallDetails['extension_id'];
$line_id=$beginCallDetails['tel_line_id'];
$source_number=$this->getOneRelativeInfos($beginCallDetails['extension_id'],'extension')['extension_account'];
$duration= ceil($duration/1000);
if( !$duration ||$duration <0 ){
$this->recharge_in_future=1;
$duration=0;
}
$this->middleData=array(
'extension_id'=>$extension_id,
'destination_number'=>$destination_number,
'charged_user_id'=>$charged_user_id,
'call_begin_time'=>$call_begin_time,
'call_end_tim'=>$call_end_time,
'duration'=>$duration,
'call_unique_id'=>$call_unique_id,
'call_id'=>$call_unique_id,
'recharge_in_future'=>$this->recharge_in_future
);
\think\Log::log('扣费对应的通话详细信息记录：');
\think\Log::info( $this->middleData);
\think\Log::log("extension准备计费uid:{$this->uid},extension_id:{$extension_id}");
if(empty($extension_id) ){
\think\Log::log("extension_charge:extension_id 为空");
}
$line_infos=$this->getAllRelativeInfos( $line_id     ,'line');
if(empty($line_infos))\think\Log::error('error - 线路没有查到');
$technology_infos=$this->getAllRelativeInfos( $this->uid     ,'user');
if(empty($technology_infos))\think\Log::error('error - 用户信息没有查到');
$line_charge_datas=[];
foreach($line_infos as $key=>$value){
$line_charge_datas[$value['member_id']  ]  ['line_id'] =  $value['id'];
$line_charge_datas[$value['member_id']  ]  ['line_price'] =  $value['sales_price'];
$line_charge_datas[$value['member_id']  ]  ['line_sales_money'] =  $value['sales_price'] *  ceil($duration / 60) ;
$line_charge_datas[$value['member_id']  ]  ['line_duration'] =  $duration ;
if($this->role_id==20){
if($this->getOneRelativeInfos( $value['member_id'] ,'user')['role_id']==18  ){
$line_charge_datas[$value['member_id']  ]  ['line_sales_money'] =0;
}
}
if ( $value['id']==5555)$line_charge_datas[$value['member_id']  ]  ['line_sales_money']=0;
}
$technology_service_datas=[];
foreach($technology_infos as $key=>$value){
$technology_service_datas[$value['id']  ]  ['technology_service_price'] =  $value['technology_service_price'];
$technology_service_datas[$value['id']  ]  ['technology_service_cost_money'] =  $value['technology_service_price'] *  ceil($duration / 60) ;
$technology_service_datas[$value['id']  ]  ['from_user_id'] =  $value['from_user_id'] ;
if($this->role_id==20){
if($this->getOneRelativeInfos( $value['id'] ,'user')['role_id']==18  ){
$technology_service_datas[$value['id']  ]  ['technology_service_cost_money'] =0;
}
}
if ( $value['id']==5555)$technology_service_datas[$value['id']  ]  ['technology_service_cost_money']=0;
}
$money_datas = array_replace_recursive ( $technology_service_datas,$line_charge_datas );
\think\Log::record('extension:进行集中收费');
$this->caculate_money($money_datas);
}
public function caculate_money( $money_datas ){
$tel_orders=[];
foreach($money_datas as $key=>$value){
if(empty($key)){
continue;
}
$sms_sales_price = $value['sms_sales_price']??0;
$asr_sales_price = $value['asr_sales_price']??0;
$line_sales_price = $value['line_sales_price']??0;
$line_sales_money = $value['line_sales_money']??0;
$line_duration = $value['line_duration']??0;
$asr_sales_money = $value['asr_sales_money']??0;
$asr_count = $value['asr_count']??0;
$sms_sales_money = $value['sms_sales_money']??0;
$sms_count =$value['sms_count']??0;
$technology_service_cost = $value['technology_service_cost_money']??0;
$money = ($sms_sales_money +$asr_sales_money +$line_sales_money +$technology_service_cost);
$find_member_id = $value['from_user_id']??'';
$username = $this->username??'';
$line_id = $value['line_id']??0;
$asr_id = $value['asr_id']??0;
$sms_channe_id = $value['sms_channel_id']??0;
$tel_orders[] = [
'owner'=>$key,
'member_id'=>$find_member_id,
'call_id'=>$this->middleData['call_id'],
'mobile'=>$this->middleData['destination_number'],
'money'=>$money,
'duration'=>$line_duration,
'create_time'=>$this->time,
'asr_id'=>$asr_id,
'asr_money'=>$asr_sales_money,
'asr_price'=>$asr_sales_price,
'asr_cnt'=>$asr_count,
'call_money'=>$line_sales_money,
'sms_channel_id'=>$sms_channe_id,
'sms_price'=>$sms_sales_price,
'sms_count'=>$sms_count,
'sms_money'=>$sms_sales_money,
'time_price'=>$line_sales_price,
'note'=>'软电话'.$username .'通话,消费'.$money .'元',
'technology_service_price'=>$value['technology_service_price'],
'technology_service_cost'=>$value['technology_service_cost_money'],
'call_phone_id'=>$line_id
];
db('admin')
->where('id',$key)
->setDec('money',$money);
}
$this->writeTelOrderCache($tel_orders);
}
public function writeTelOrderCache(array $tel_orders){
$pid = 0;
foreach($tel_orders as $key=>$value){
$value['pid'] = $pid;
$pid = db('tel_order')
->insertGetId($value);
$now_time = strtotime(date("Y-m-d"));
$redis = RedisConnect::get_redis_connect();
$charging_duration = ceil($value['duration']/60);
$redis_duration = $value['duration'];
$asr_cnt = $value['asr_cnt'];
$sms_count = $value['sms_count'];
$call_money = $value['call_money'];
$asr_money = $value['asr_money'];
$sms_money = $value['sms_money'];
$technology_service_cost = $value['technology_service_cost'];
$money = $value['money'];
$incr_key_charging_duration = "incr_owner_".$value['owner']."_".$now_time."_charging_duration";
$incr_key_duration = "incr_owner_".$value['owner']."_".$now_time."_duration";
$incr_key_asr_cnt = "incr_owner_".$value['owner']."_".$now_time."_asr_cnt";
$incr_key_sms_count = "incr_owner_".$value['owner']."_".$now_time."_sms_count";
$incr_key_call_money = "incr_owner_".$value['owner']."_".$now_time."_call_money";
$incr_key_asr_money = "incr_owner_".$value['owner']."_".$now_time."_asr_money";
$incr_key_sms_money = "incr_owner_".$value['owner']."_".$now_time."_sms_money";
$incr_key_technology_service_cost = "incr_owner_".$value['owner']."_".$now_time."_technology_service_cost";
$incr_key_money = "incr_owner_".$value['owner']."_".$now_time."_money";
$redis->incrby($incr_key_charging_duration,$charging_duration);
$redis->incrby($incr_key_duration,$redis_duration);
$redis->incrby($incr_key_asr_cnt,$asr_cnt);
$redis->incrby($incr_key_sms_count,$sms_count);
$redis->INCRBYFLOAT($incr_key_call_money,$call_money);
$redis->INCRBYFLOAT($incr_key_asr_money,$asr_money);
$redis->INCRBYFLOAT($incr_key_sms_money,$sms_money);
$redis->INCRBYFLOAT($incr_key_technology_service_cost,$technology_service_cost);
$redis->INCRBYFLOAT($incr_key_money,$money);
}
\think\Log::record('extension:集中收费完成。等待redis处理话单');
}
public function ajaxExtensionRecord(){
$where = $this->postData;
$extensionsSipRecordDb=Db::name('sipphone_record');
if(!empty($where['extension_account']) )$where['extension_account']=['like','%'.$where['extension_account'].'%'];
$count=$extensionsSipRecordDb->where($where)->count('*');
$extensions=$extensionsDb->where($where)->page($this->pageData['page'],$this->pageData['pageLimit'])->select();
$data['totalCount']=$count;
$data['data']=$extensions;
if($extensions){
return    returnAjax(0,'获取成功',$data);
}else{
return   returnAjax(1,'获取失败',$data);
}
}
public function getAllRelativeInfos($id,$table_name='user'){
switch(true){
case (bool)strstr('line',$table_name):
$funcName='get_line_group_find';
break;
case (bool)strstr('user',$table_name):
$funcName='get_user_find';
break;
case (bool)strstr('asr',$table_name):
$funcName='get_asr_find';
break;
default:
$funcName='get_user_find';
break;
}
$currentInfoArr=$this->RedisApiData->$funcName($id);
$currentInfoArr['from_user_id']=$id;
$returnArr[]=$currentInfoArr;
$i=0;
while( !empty( $currentInfoArr['pid']  )  &&$currentInfoArr['pid'] >0  &&$i<50 ){
$below_user_id=$currentInfoArr['id'];
$currentInfoArr=$this->RedisApiData->$funcName($currentInfoArr['pid']);
$currentInfoArr['from_user_id']=$below_user_id;
$returnArr[]=$currentInfoArr;
$i++;
}
if($i==50){
\think\Log::log('死循环！'.__LINE__);
}
return   $returnArr;
}
public function getOneRelativeInfos($id,$table_name='user'){
switch(true){
case (bool)strstr('line',$table_name):
$funcName='get_line_find';
break;
case (bool)strstr('user',$table_name):
$funcName='get_user_find';
break;
case (bool)strstr('asr',$table_name):
$funcName='get_asr_find';
break;
case (bool)strstr('extension',$table_name):
$funcName='get_extension_find';
break;
default:
$funcName='get_user_find';
break;
}
return  $this->RedisApiData->$funcName($id);
}
public function getSipphoneRecord($user_id,$call_unique_id){
return Db::name('sipphone_record')->where('user_id',$user_id )->where('call_unique_id',$call_unique_id )->find();
}
public function getPhone(){
$id = input('id','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
switch(true){
case (bool)strstr('crm',$name):
$tableName='crm';$columnName='phone';
break;
case (bool)strstr('record_today',$name):
$tableName='tel_call_record';$columnName='mobile';
break;
case (bool)strstr('record_7',$name):
$tableName='rk_tel_call_record_sevendays_history';$columnName='mobile';
break;
case (bool)strstr('record_history',$name):
$tableName='rk_tel_call_record_history';$columnName='mobile';
break;
case (bool)strstr('member',$name):
$tableName='member';$columnName='mobile';
break;
default:
$tableName='crm';$columnName='phone';
break;
}
$rs=Db::name($tableName)->where('id',$id)->field('*,'.$columnName.' as phoneNumber')->find();
\think\Log::record('GetPhoneFunc[]');
if($rs){
return    returnAjax(0,'',$rs);
}else{
return   returnAjax(1,'获取失败',$rs);
}
}
public function getSwitchTag(){
if( config('softcall_switch') ){
return true;
}
return false;
}
public function getDefaultExtension(){
$extensionDb=Db::name('tel_extension');
$where=$this->postData;
$res=$extensionDb->where('user_id',$where['user_id'])->where('default',1)->find();
if($res){
$res['extension_pass'] = $this->encrypt->encrypt( $res['extension_pass'] ,'D');
return $res;
}else{
return false;
}
}
public function getDefaultLine(){
$taskData=new TaskData();
$extensionDb=Db::name('tel_extension');
$where=$this->postData;
$res=$extensionDb->where($where)->where('default',1)->find();
if(!empty( $res['tel_line_id'] ) ){
$line_id=$taskData->getMinLineId($res['tel_line_id']);
$line_info=Db::name('tel_line')->where('id',$line_id)->find();
}
if(!empty($line_info )){
return returnAjax(0,'获取成功',$line_info);
}else{
return returnAjax(1,'获取失败');
}
}
public function getUserHaveNoMoney(){
$returnFlag=false;
$relativeUsers=$this->getAllRelativeInfos($this->uid);
foreach($relativeUsers as $v){
$userInfo=Db::name('admin')->where('id',$v['id'])->find();
if($userInfo['money']>=0)continue;
if($userInfo['role_id']==12)continue;
return $v['id'];
}
return false;
}
}
