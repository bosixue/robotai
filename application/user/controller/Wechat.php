<?php // Copyright(C) 2016-2018 www.51sanu.com, All rights reserved.ts reserved.

namespace app\user\controller;
use think\Db;
use think\Session;
use think\Cache;
use \think\Controller;
use app\common\controller\RedisConnect;
class Wechat extends Controller{
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
public function index(){
halt(666);
}
public function test(){
$res = $this->send_massage_to_user_one(5888,'',18874028699,'A',6000538,30);
halt($res);
}
public function send_massage_to_user_one(){
$uid =input('get.uid','','trim,strip_tags');
$name=input('get.name','','trim,strip_tags');
$phone=input('get.phone','','trim,strip_tags');
$level=input('get.level','','trim,strip_tags');
$taskId=input('get.taskId','','trim,strip_tags');
$wx_push_user_id=input('get.wx_push_user_id','','trim,strip_tags');
$time=input('get.time','','trim,strip_tags');
$yunying_id = $this->get_operator_id($uid);
$config = Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$appid=$config['app_id'];
$appsecret=$config['app_secret'];
$template_id = $config['template_id'];
$status = $config['status'];
if($status==0){
return json_encode([false]);
}
if(empty($wx_push_user_id)){
return json_encode([false]);
}
$push_user = Db::name('wx_push_users')->where(['id'=>$wx_push_user_id])->find();
if(empty($push_user)){
return json_encode([false]);
}
$openid =  $push_user['open_id'];
$url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$this->getWxAccessToken($appid,$appsecret);
$arr = [
'touser'=>$openid,
'template_id'=>$template_id,
'url'=>config('domain_name').'/user/wechat/backdetail?mobile='.$phone.'&taskId='.$taskId.'&name='.$name.'&time='.$time,
'data'=>[
'first'=>[
'value'=>'客户意向通知',
"color"=>"#173177",
],
'keyword1'=>[
'value'=>$name,
"color"=>"#173177",
],
'keyword2'=>[
'value'=>$phone,
"color"=>"#173177",
],
'keyword3'=>[
'value'=>date('Y-m-d H:i:s'),
"color"=>"#173177",
],
'keyword4'=>[
'value'=>'此用户是'.$level.'级意向',
"color"=>"#173177",
],
],
];
$res = $this->http_curl($url,'post','json',json_encode($arr));
if(isset($res['errcode']) &&$res['errcode']==0){
return json_encode([true]);
}
return json_encode([false]);
}
public function get_operator_id($uid){
$admin = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->field('a.id,a.pid,ar.name as role_name')
->where('a.id',$uid)
->find();
$roleNme = $admin['role_name'];
if(!empty($uid) &&$roleNme=='运营商'){
return  $admin['id'];
}
if(!empty($uid) &&$roleNme!='运营商'&&$roleNme!='管理员'){
$admin_father = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->field('a.id,a.pid,ar.name as role_name')
->where('a.id',$admin['pid'])
->find();
$father_role_name = $admin_father['role_name'];
if($father_role_name == '运营商'){
return $admin_father['id'];
}else{
$admin_granddad = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->field('a.id,a.pid,ar.name as role_name')
->where('a.id',$admin_father['pid'])
->find();
$granddad_role_name = $admin_granddad['role_name'];
if($granddad_role_name == '运营商'){
return $admin_granddad['id'];
}else{
$admin_last = Db::name('admin')->field('id')->where(['id'=>$admin_granddad['pid']])->find();
return $admin_last['id'];
}
}
}
}
public function backdetail(){
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$level = input('level','','trim,strip_tags');
$time = input('time','','trim,strip_tags');
$where['mobile']=$mobile;
$where['task_id']=$taskId;
$where['level']=['>=',4];
$where_m['mobile']=$mobile;
$where_m['task']=$taskId;
$data = date('Ymd',$time);
$Info = Db::connect(config('master_db'))->table('rk_tel_call_record')
->field('status,level,duration,last_dial_time,call_id,call_times,originating_call,record_path,flow_label,knowledge_label,semantic_label')
->where($where)
->find();
if(empty($Info)){
$data = date('Ymd',$time);
try{
$Info = Db::name('tel_call_record_'.$data)
->field('status,level,duration,last_dial_time,call_id,call_times,originating_call,record_path,flow_label,knowledge_label,semantic_label')
->where($where)
->find();
}catch(\Exception $e){
$Info=[];
}
}
if(empty($Info)){
echo '<h1>此通话记录不存在</h1>';
return;
}
$memberInfo=$Info;
$memberInfo['record_path'] = $Info['record_path'];
$memberInfo['status'] = $Info['status'];
$memberInfo['level'] = $Info['level'];
$memberInfo['duration'] = $Info['duration'];
$memberInfo['last_dial_time'] = $Info['last_dial_time'];
$memberInfo['call_id'] = $Info['call_id'];
$memberInfo['call_times'] = $Info['call_times'];
$memberInfo['originating_call'] = $Info['originating_call'];
$lables[] = $Info['flow_label'];
$lables[]= $Info['knowledge_label'];
$lables[] = $Info['semantic_label'];
if(isset($memberInfo['call_rotation']) === false ||empty($memberInfo['call_rotation'])){
$memberInfo['call_rotation'] = 0;
}
if(count($memberInfo)){
$review = array();
$review['review'] = 1;
$tel_call_record_num = Db::connect(config('master_db'))->table('rk_tel_call_record')->where($where)->count('*');
if($tel_call_record_num>0){
Db::connect(config('master_db'))->table('rk_tel_call_record')->where($where)->update($review);
}else{
Db::name('tel_call_record_'.$data)->where($where)->update($review);
}
}
$tel_call_record_num = 	Db::connect(config('master_db'))->table('rk_tel_call_record')->where($where)->count('*');
if($tel_call_record_num>0){
$flow_name = Db::connect(config('master_db'))->table('rk_tel_call_record')->where($where)->value('flow_label');
$knowledge_name = Db::connect(config('master_db'))->table('rk_tel_call_record')->where($where)->value('knowledge_label');
$semantic_name = Db::connect(config('master_db'))->table('rk_tel_call_record')->where($where)->value('semantic_label');
}else{
$flow_name = Db::name('tel_call_record_'.$data)->where($where)->value('flow_label');
$knowledge_name = Db::name('tel_call_record_'.$data)->where($where)->value('knowledge_label');
$semantic_name = Db::name('tel_call_record_'.$data)->where($where)->value('semantic_label');
}
$Keyword_name = $flow_name;
if($knowledge_name){
$Keyword_name .= ','.$knowledge_name;
}
if($semantic_name){
$Keyword_name .= ','.$semantic_name;
}
if(!$flow_name &&!$knowledge_name){
$Keyword_name .= $semantic_name;
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
$bills[$key]['path'] = Config('history_cut_audio_server_url') .$value['path'];
}
if(date('Y-m-d',$memberInfo['last_dial_time']) == date('Y-m-d',time())){
$memberInfo['record_path'] = config("record_path").$memberInfo['record_path'];
}else{
$memberInfo['record_path'] = config("history_record_path").$memberInfo['record_path'];
}
$memberInfo['last_dial_time'] = date('Y-m-d H:i:s',$memberInfo['last_dial_time']);
$data = array();
$data['memberInfo'] = $memberInfo;
$data['bills'] = $bills;
$data['num'] = $Keyword_name;
$leve=[6=>'A',5=>'B',4=>'C',3=>'D',2=>'E',1=>'F'];
$leve_text=[6=>'意向客户',5=>'一般意向',4=>'简单对话',3=>'无有效对话',2=>'有效未接通',1=>'无效号码'];
$scenarios_id = Db::name('tel_config')->field('scenarios_id')->where(['task_id'=>$taskId])->value('scenarios_id');
$call_status=[
3=>'无人接听',
4=>'停机',
5=>'空号',
6=>'正在通话中',
7=>'关机',
8=>'用户拒接',
9=>'网络忙',
10=>'来电提醒',
11=>'呼叫转移失败',
];
$tel_intention_rules = Db::name('tel_intention_rule')->field('rule')->where(['scenarios_id'=>$scenarios_id,'level'=>$memberInfo['level']])->select();
if(!empty($tel_intention_rules)){
foreach($tel_intention_rules as $tel_intention_rule){
$arr=unserialize($tel_intention_rule['rule']);
if(count($arr)==1){
if($arr[0]['key']=='call_status'){
$brr = $arr[0]['value'];
foreach($brr as $k=>$v){
$crr[]=$call_status[$v];
}
$arr[0]['value']=implode(',',$crr);
}
}
if(count($arr)==2){
if($arr[0]['key']=='call_status'){
$brr = $arr[0]['value'];
foreach($brr as $k=>$v){
$crr[]=$call_status[$v];
}
$arr[0]['value']=implode(',',$crr);
}
if($arr[1]['key']=='call_status'){
$brr = $arr[1]['value'];
foreach($brr as $k=>$v){
$crr[]=$call_status[$v];
}
$arr[1]['value']=implode(',',$crr);
}
}
$rules[]=$arr;
}
$this->assign('rules',$rules);
$this->assign('rules_code',1);
}else{
$this->assign('rules','话术已经被删除');
$this->assign('rules_code',0);
}
$daxie=[1=>'一',2=>'二',3=>'三',4=>'四',5=>'五',6=>'六',7=>'七',8=>'八',9=>'九',10=>'十',11=>'十一',12=>'十二',
13=>'十三',14=>'十四',15=>'十五',16=>'十六',17=>'十七',18=>'十八',19=>'十九',20=>'二十',21=>'二十一',22=>'二十二',
23=>'二十三',24=>'二十四',25=>'二十五',26=>'二十六',27=>'二十七',28=>'二十八',29=>'二十九',30=>'三十',31=>'三十一',
32=>'三十二',33=>'三十三',34=>'三十四',35=>'三十五',36=>'三十六',37=>'三十七',38=>'三十八',39=>'三十九',40=>'四十'];
$this->assign('daxie',$daxie);
$tel_bills = Db::name('tel_bills')->where(['call_id'=>$memberInfo['call_id'],'hit_keyword'=>'命中未识别流程'])->select();
$task_name = getTaskName($taskId);
$this->assign('task_name',$task_name);
$this->assign('record_path',$memberInfo['record_path']);
if(!empty($time)){
$this->assign('time',date('Y年m月d日',$time));
}else{
$this->assign('time','暂时不知道推送时间');
}
$this->assign('lables',$lables);
$this->assign('tel_bills',$tel_bills);
$this->assign('level',$leve[$memberInfo['level']]);
$this->assign('level_text',$leve_text[$memberInfo['level']]);
$this->assign('duration',$memberInfo['duration']);
$this->assign('mobile',$mobile);
$this->assign('data',$data);
$this->assign('bills',$data['bills']);
$this->assign('memberInfo',$data['memberInfo']);
$this->assign('name',$name);
return $this->fetch();
}
public  function getUserInfo(){
$uid = input('param.userid');
$appid = input('param.appid');
$appsecret = input('param.appsecret');
$redirect_uri=urlencode(config('domain_name')."/user/Wechat/getOpenid");
$url="https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_uri."&response_type=code&scope=snsapi_userinfo&state=".$uid.'-'.$appid.'-'.$appsecret."#wechat_redirect";
header('location:'.$url);
return;
}
public function getOpenid(){
$code=input('param.code');
$xx=input('param.state');
$uid = explode('-',$xx)[0];
$appid =explode('-',$xx)[1];
$appsecret =explode('-',$xx)[2];
$url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$appid."&secret=".$appsecret."&code=".$code."&grant_type=authorization_code";
$res=$this->http_curl($url,'get');
if(!isset($res['access_token']) ||empty($res['access_token'])){
echo "<script> alert('access_token不存在，请确定运营商的appid 和appsecret填写正确，另外请确定ip是否在白名单中 域名配置是否正确');</script>";
return;
}
$access_token=$res['access_token'];
$openid=$res['openid'];
$urlx = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
$resAll=$this->http_curl($urlx,'get');
$subscribe=1;
$openid=$resAll['openid'];
if($subscribe==0){
echo "<script> alert('您没有关注我们公司的公众号，请关注了，再来扫码绑定！')</script>";
return;
}
if(empty($uid)){
echo "<script> alert('请先登录我公司的网站，再来绑定！！')</script>";
return;
}
$yunying_id = $this->get_operator_id($uid);
$config = Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$count = Db::name('wx_push_users')->where(['open_id'=>$openid,'member_id'=>$uid,'wx_config_id'=>$config['id']])->count('*');
if($count>0){
echo "<script> alert('您已经绑定过了！')</script>";
return;
}
if(!empty($uid)&&$subscribe==1){
$data['member_id']=$uid;
$data['wx_config_id']=$config['id'];
$data['open_id']=$openid;
$data['name']=$resAll['nickname'];
$data['sex'] = $resAll['sex'];
$data['province'] = $resAll['province'];
$data['city'] = $resAll['city'];
$data['headimgurl'] = $resAll['headimgurl'];
$data['create_time'] = time();
$ras = Db::name('wx_push_users')->insertGetId($data);
if(!empty($ras)){
echo "<script> alert('恭喜您绑定成功！')</script>";
return;
}else{
echo "<script> alert('绑定失败请重新绑定！')</script>";
return;
}
}
}
public function getNoticerOpenid($uid){
$configs = Db::name('wx_config')->where(['member_id'=>$uid])->select();
foreach($configs as $key=>$value){
$ret = $this->getWxAccessToken($value['app_id'],$value['app_secret']);
if($ret){
$url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=".$ret;
$res=$this->http_curl($url,'get','json');
if(isset($res['errcode'])){
return false;
}else{
$openid_array =$res['data']['openid'];
$wx_push_users = Db::name('wx_push_users')->where( ['member_id'=>$value['member_id'] ])->select();
foreach($wx_push_users as $key=>$value){
if(!in_array($value['open_id'],$openid_array,true) ){
$res = Db::name('wx_push_users')->where(['id'=>$value['id']])->delete();
if($res){
return '微信推送表中已经删除了取消关注的人';
}else{
return '暂时没有取消关注的人';
}
}
}
}
}else{
echo  '<script> alert("您还没有正确的设置微信公众号，或者请把你的微信公众号白名单设置支持本网站ip");  window.history.back(-1);</script>';
return;
}
}
}
public function getWxAccessToken($appid,$appsecret){
$redis = RedisConnect::get_list_redis_connect_wx();
$key_access_token='access_token_'.$appid;
$access_token = $redis->get($key_access_token);
if(!empty($access_token)){
return $access_token;
}else{
$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
$res=$this->http_curl($url,'get','json');
if(isset($res['errcode'])){
return false;
}else{
if(isset($res['access_token'])){
$access_token=$res['access_token'];
$redis->setex($key_access_token,7200,$access_token);
return $access_token;
}else{
return false;
}
}
}
}
public  function http_curl($url,$type='get',$res='json',$arr=''){
$ch=curl_init();
curl_setopt($ch,CURLOPT_HEADER,0);
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
if($type=='post'){
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,$arr);
}
$output=curl_exec($ch);
if($res=='json'){
if( curl_errno($ch) ){
return  curl_error($ch);
}else{
return json_decode($output,true);
}
}
curl_close($ch);
}
}
