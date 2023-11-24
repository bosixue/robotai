<?php 

namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use \think\Config;
use Qiniu\json_decode;
use app\common\controller\AdminData;
use app\common\controller\ConsumptionStatistics;
use app\common\controller\RechargeRecord;
use app\common\controller\EnterpriseData;
class Enterprise extends User{
public function _initialize()
{
parent::_initialize();
}
public function account()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$AdminData = new AdminData();
$user_list = $AdminData->get_find_users($uid);
array_unshift($user_list,['username'=>'自己','id'=>$user_auth['uid']]);
$this->assign('user_list',$user_list);
return  $this->fetch();
}
public function test()
{
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$EnterpriseData = new EnterpriseData();
$AdminData = new AdminData();
print_r($EnterpriseData->get_userall_id(5652,'商家'));
$yesterday = date("Y-m-d",strtotime('-1 day'));
$start_time = strtotime($yesterday);
$end_time = $start_time +(24 * 3600);
$args_yesterday = [
'start_time'=>$start_time,
'end_time'=>$end_time,
'type'=>'day'
];
}
public function get_recharge_record_api()
{
if(IS_POST){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$start_time = input('start_time','','trim,strip_tags');
$end_time = input('end_time','','trim,strip_tags');
if($uid == '5555'){
$datas = Db::name('tel_deposit')
->field('td.*,a.username as member_name')
->alias('td')
->join('admin a','a.id = td.owner','LEFT')
->where(1);
$count = Db::name('tel_deposit')
->where(1);
}else{
$datas = Db::name('tel_deposit')
->field('td.*,a.username as member_name')
->alias('td')
->join('admin a','a.id = td.owner','LEFT')
->where('td.owner',$uid);
$count = Db::name('tel_deposit')
->where('owner',$uid);
}
if(!empty($start_time)){
$datas = $datas->where('td.create_time','>=',strtotime($start_time));
$count = $count->where('create_time','>=',strtotime($start_time));
}
if(!empty($end_time)){
$datas = $datas->where('td.create_time','<=',strtotime($end_time));
$count = $count->where('create_time','<=',strtotime($end_time));
}
$count = $count->count('id');
$datas = $datas->page($page ,$limit)
->order('create_time','desc')
->select();
foreach($datas as $key=>$value){
$datas[$key]['date'] = date('Y-m-d H:i',$value['create_time']);
$datas[$key]['sequence'] = ($page-1)*$limit+($key+1);
$datas[$key]['recharge_person'] = Db::name('admin')
->where('id',$value['recharge_member_id'])
->value('username');
}
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $datas;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'success',$data);
}
}
public function recharge_record(){
return $this->fetch();
}
public function call_statistics(){
return $this->fetch();
}
public function sale_account_recharge(){
return $this->fetch();
}
public function sales_recharge_record(){
return $this->fetch();
}
public function admin_survey()
{
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$EnterpriseData = new EnterpriseData();
$user_type = $EnterpriseData->get_user_type($uid);
$this->assign('user_type',$user_type);
return $this->fetch();
}
public function get_username_api(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$type = input('type','','trim,strip_tags');
$user_name = [];
if($type == '全部类型'){
return returnAjax(0,'成功');
}else if($type == $user_auth['role']){
$user_name[0] = Db::name('admin')->field('id,username,role_id')->where('id',$uid)->find();
$user_name[0]['name'] = $type;
}else{
$EnterpriseData = new EnterpriseData();
$user_name = $EnterpriseData->get_user_name($uid,$type);
}
return returnAjax(0,'成功',$user_name);
}
public function search_survey_api(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$type = input('type','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$EnterpriseData = new EnterpriseData();
$user_info = $EnterpriseData->get_user_survey_data($type,$username);
session('check_name',$username);
session('check_type',$type);
return returnAjax(0,'success',['user_info'=>$user_info]);
}
public function get_rate_api(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$type = input('type','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$rate_type = input('rate_type','','trim,strip_tags');
$data = array();
$EnterpriseData = new EnterpriseData();
$rate_info = $EnterpriseData->get_callrate_info($uid,$username,$type,$rate_type);
$data['rate_info'] = $rate_info;
$data['type'] = $rate_type;
return returnAjax(0,'success',$data);
}
public function admin_consumption_statistics()
{
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$EnterpriseData = new EnterpriseData();
$user_type = $EnterpriseData->get_user_type($uid);
$user_list = $EnterpriseData->get_user_list($user_auth);
$this->assign('user_list',$user_list);
$this->assign('user_type',$user_type);
return $this->fetch();
}
public function get_user_list_api(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$type = input('type','','trim,strip_tags');
$user_id = input('user_id','','trim,strip_tags');
if($user_id){
$where['pid'] = $user_id;
}else{
if($user_auth['role'] == '代理商'||$user_auth['role'] == '运营商'||$user_auth['role'] == '商家'){
$where['pid'] = $uid;
}
}
if($type == '运营商'){
$where['role_id'] = 17;
$user_list = Db::name('admin')->where($where)->field('id,username')->select();
}else if($type == '代理商'){
$where['role_id'] = 18;
$user_list = Db::name('admin')->where($where)->field('id,username')->select();
}else if($type == '商家'){
$where['role_id'] = 19;
$user_list = Db::name('admin')->where($where)->field('id,username')->select();
}
return returnAjax(0,'success',$user_list);
}
public function get_consumption_api(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$usertype = input('usertype','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$start_time = input('start_time','','trim,strip_tags');
$end_time = input('end_time','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$user_id = input('user_id','','trim,strip_tags');
$EnterpriseData = new EnterpriseData();
if($type == 'day'){
$data = $EnterpriseData->get_today_user_statistics($user_auth,$type,$page,$limit,$user_id,$username,$usertype,$start_time,$end_time);
}else {
if ($usertype == '全部类型') {
$data = $EnterpriseData->get_all_statistics($uid,$type,$page,$limit,$start_time,$end_time);
}else {
if ($username == '全部账户') {
$data = $EnterpriseData->get_all_statistics($uid,$type,$page,$limit,$start_time,$end_time,$usertype);
}else {
$data = $EnterpriseData->get_user_statistics($uid,$type,$page,$limit,$username,$usertype,$start_time,$end_time);
}
}
}
session('check_name',$username);
session('check_type',$usertype);
return returnAjax(0,'success',$data);
}
public function get_consumption_details_api(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$usertype = input('usertype','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$linename = input('linename','','trim,strip_tags');
$asrname = input('asrname','','trim,strip_tags');
$smsname = input('smsname','','trim,strip_tags');
$start_time = input('start_time','','trim,strip_tags');
$end_time = input('end_time','','trim,strip_tags');
$callNum = input('callNum','','trim,strip_tags');
$select_type = input('select_type','','trim,strip_tags');
$args = [
'page'=>$page,
'limit'=>$limit,
'usertype'=>$usertype,
'username'=>$username,
'linename'=>$linename,
'asrname'=>$asrname,
'smsname'=>$smsname,
'start_time'=>$start_time,
'end_time'=>$end_time,
'callNum'=>$callNum,
'select_type'=>$select_type
];
$EnterpriseData = new EnterpriseData();
if($usertype == '全部类型'||$username == '全部账户'){
$data = $EnterpriseData->get_allconsumption_statistics($uid,$args);
}else{
$data = $EnterpriseData->get_consumption_statistics($uid,$args);
}
session('check_name',$username);
session('check_type',$usertype);
return returnAjax(0,'success',$data);
}
public function export_consumption_statistics(){
$columName = ['账户名','统计日期','呼叫次数','接通次数','接通率','平均通话时长(秒)','计费时长(分钟)','通话费用(元)','语音识别次数','语音识别费用(元)','机器人月租费用(元)','发送短信数','短信费用(元)','合计'];
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$usertype = input('usertype','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$start_time = input('start_time','','trim,strip_tags');
$end_time = input('end_time','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$user_id = input('user_id','','trim,strip_tags');
$export_type = input('export_type','','trim,strip_tags');
if(!empty($export_type)){
$ids = [];
$EnterpriseData = new EnterpriseData();
if($usertype == '全部类型'){
$ids = $EnterpriseData->get_userall_id($uid);
}else{
if($username == '全部账户'){
$ids = $EnterpriseData->get_userall_id($uid,$usertype);
}else{
if($type == 'day'){
$ids = $EnterpriseData->get_user_ids($uid,$user_auth['role'],$user_id,$usertype,$username);
}else {
$ids[] = Db::name('admin')->where('username',$username)->value('id');
}
}
}
$mList = Db::name('consumption_statistics')
->field('cs.*,a.username as member_name,a.month_price')
->alias('cs')
->join('admin a','a.id = cs.member_id','LEFT')
->where('member_id','in',$ids)
->where('type',$type);
if(!empty($start_time)){
$start_time = strtotime($start_time);
$mList = $mList->where('cs.date','>=',$start_time);
}
if(!empty($end_time)){
$end_time = strtotime($end_time);
$mList = $mList->where('cs.date','<=',$end_time);
}
$mList = $mList->order('cs.date','desc')->select();
}else{
$cwhere = array();
$usercheck = input('usercheck/a','','trim,strip_tags');
$mList = Db::name('consumption_statistics')
->field('cs.*,a.username as member_name,a.month_price')
->alias('cs')
->join('admin a','a.id = cs.member_id','LEFT');
if(is_array($usercheck) === true &&count($usercheck) >0){
$cwhere['cs.id'] = ['in',$usercheck];
}
$mList = $mList->where($cwhere)
->order('cs.date','desc')
->select();
}
$list = array();
foreach($mList as $key =>$item){
$list[$key]['username'] = $item['member_name'];
if(empty($item['date'])){
$list[$key]['date'] = '暂无日期';
}else {
$list[$key]['date'] = date('Y-m-d',$item['date']);
}
$list[$key]['call_count'] = $item['call_count'];
$list[$key]['connect_count'] = $item['connect_count'];
$list[$key]['connect_rate'] = $item['connect_rate'];
$list[$key]['average_duration'] = $item['average_duration'];
$list[$key]['charging_duration'] = $item['charging_duration'];
$list[$key]['connect_cost'] = $item['connect_cost'];
$list[$key]['asr_count'] = $item['asr_count'];
$list[$key]['asr_cost'] = $item['asr_cost'];
$list[$key]['robot_cost'] = $item['robot_cost'];
$list[$key]['send_sms_count'] = $item['send_sms_count'];
$list[$key]['sms_cost'] = $item['sms_cost'];
$list[$key]['total_cost'] = $item['total_cost'];
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
$sum = 0;
foreach ($list as $key =>$val) {
foreach (array_values($val) as $key2 =>$val2) {
$PHPSheet->setCellValue($letter[$key2].($key+2),$val2);
}
$sum  += $val['total_cost'];
}
$PHPSheet->setCellValue($letter[count($list[0])-2].(count($list)+2),'总计:');
$PHPSheet->setCellValue($letter[count($list[0])-1].(count($list)+2),$sum);
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
public function export_detailed_consumption(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$usertype = input('usertype','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$linename = input('linename','','trim,strip_tags');
$asrname = input('asrname','','trim,strip_tags');
$smsname = input('smsname','','trim,strip_tags');
$start_time = input('start_time','','trim,strip_tags');
$end_time = input('end_time','','trim,strip_tags');
$callNum = input('callNum','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$export_type = input('export_type','','trim,strip_tags');
$select_type = input('select_type','','trim,strip_tags');
$table_name = get_order_table_name($select_type);
if(!empty($export_type)){
$ids = [];
$EnterpriseData = new EnterpriseData();
if($usertype == '全部类型'){
$ids = $EnterpriseData->get_userall_id($uid);
}else{
if($username == '全部账户'){
$ids = $EnterpriseData->get_userall_id($uid,$usertype);
}else{
$ids[] = Db::name('admin')->where('username',$username)->value('id');
}
}
$mList = Db::name($table_name)->alias('o')->field('o.*,a.username')->join('admin a','o.owner = a.id','LEFT')->where('o.owner','in',$ids);
if(!empty($linename)){
$mList = $mList->where('o.call_phone_id','=',$linename);
}
if(!empty($asrname)){
$mList = $mList->where('o.asr_id','=',$asrname);
}
if(!empty($smsname)){
$mList = $mList->where('o.sms_channel_id','=',$smsname);
}
if(!empty($start_time)){
$mList = $mList->where('o.create_time','>=',strtotime($start_time));
}
if(!empty($end_time)){
$end_time =  date('Y-m-d 23:59:59',strtotime($end_time));
$mList = $mList->where('o.create_time','<=',strtotime($end_time));
}
$mList = $mList->order('o.create_time','desc')->select();
}else{
$cwhere = array();
$usercheck = input('usercheck/a','','trim,strip_tags');
$mList = Db::name($table_name)->alias('o')->field('o.*,a.username')->join('admin a','o.owner = a.id','LEFT');
if(!empty($linename)){
$mList = $mList->where('o.call_phone_id','=',$linename);
}
if(!empty($asrname)){
$mList = $mList->where('o.asr_id','=',$asrname);
}
if(!empty($smsname)){
$mList = $mList->where('o.sms_channel_id','=',$smsname);
}
if(!empty($start_time)){
$mList = $mList->where('o.create_time','>=',strtotime($start_time));
}
if(!empty($end_time)){
$end_time =  date('Y-m-d 23:59:59',strtotime($end_time));
$mList = $mList->where('o.create_time','<=',strtotime($end_time));
}
if(is_array($usercheck) === true &&count($usercheck) >0){
$cwhere['o.id'] = ['in',$usercheck];
}
$mList = $mList->where($cwhere)
->order('o.create_time desc')
->select();
}
$new_tel_line_table = Db::name('tel_line_group')->field('id,name')->select();
if($new_tel_line_table)
{
$new_tel_line_table_ = [];
foreach ($new_tel_line_table as $key=>$val){
$new_tel_line_table_[$val['id']] = $val;
}
$tel_line_ids = array_keys($new_tel_line_table_);
}
$new_tel_interface_table = Db::name('tel_interface')->field('id,name')->select();
if($new_tel_interface_table)
{
$new_tel_interface_table_ = [];
foreach ($new_tel_interface_table as $key=>$val){
$new_tel_interface_table_[$val['id']] = $val;
}
$tel_interface_ids = array_keys($new_tel_interface_table_);
}
$new_sms_channel_table = Db::name('sms_channel')->field('id,name')->select();
if($new_sms_channel_table)
{
$new_sms_channel_table_ = [];
foreach ($new_sms_channel_table as $key=>$val){
$new_sms_channel_table_[$val['id']] = $val;
}
$sms_channel_ids = array_keys($new_sms_channel_table_);
}
$list = array();
foreach($mList as $key =>$item){
$list[$key]['username'] = $item['username'];
if(empty($item['mobile'])){
$list[$key]['mobile'] = '暂无拨打';
}else {
$list[$key]['mobile'] = $item['mobile'];
}
$list[$key]['duration'] = $item['duration'];
if(in_array($item['call_phone_id'],$tel_line_ids))
{
$list[$key]['linename'] = $new_tel_line_table_[$item['call_phone_id']]['name'];
}else{
$list[$key]['linename'] = "暂无";
}
$list[$key]['call_money'] = $item['call_money'];
$list[$key]['asr_cnt'] = $item['asr_cnt'];
if(in_array($item['asr_id'],$tel_interface_ids))
{
$list[$key]['asrname'] = $new_tel_interface_table_[$item['asr_id']]['name'];
}else{
$list[$key]['asrname'] = "暂无";
}
$list[$key]['asr_money'] = $item['asr_money'];
$list[$key]['sms_count'] = $item['sms_count'];
if(in_array($item['sms_channel_id'],$sms_channel_ids))
{
$list[$key]['smsname'] = $new_sms_channel_table_[$item['sms_channel_id']]['name'];
}else{
$list[$key]['smsname'] = "暂无";
}
$list[$key]['sms_money'] = $item['sms_money'];
$list[$key]['technology_service_cost'] = $item['technology_service_cost'];
$list[$key]['money'] = $item['money'];
if(empty($item['create_time'])){
$list[$key]['create_time'] = "暂无拨打时间";
}else {
$list[$key]['create_time'] = date('Y-m-d H:i',$item['create_time']);
}
$list[$key]['mobile']=hide_phone_middle($list[$key]['mobile']);
}
$columName = ['账户名称','呼叫号码','通话时长（秒）','线路','通话费用（元）','语音识别次数','ASR','语音识别费用（元）','短信条数','通道','短信费用','技术服务费','总费用','拨打时间'];
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
public function consumption_detail(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$EnterpriseData = new EnterpriseData();
$user_type = $EnterpriseData->get_user_type($uid);
$this->assign('user_type',$user_type);
$show_all_days  = config('order_table_days');
$this->assign('show_all_days',$show_all_days);
$data = get_date_name('order');
$this->assign('data',$data);
return $this->fetch();
}
}
