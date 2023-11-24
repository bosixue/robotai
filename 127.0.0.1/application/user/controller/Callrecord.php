<?php 
namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use Qiniu\json_decode;
use app\common\controller\MemberData;
use app\common\controller\Log;
use app\common\controller\MemberGroup;
use app\common\controller\TelConfig;
use app\common\controller\TelScenarios;
use app\common\controller\TelCallRecord;
use app\common\controller\TelCallHistoricalRecord;
use app\common\controller\TelCallHistoricalSevenDaysRecord;
use app\common\controller\PlanData;
use app\common\controller\LinesData;
use app\common\controller\AdminData;
use app\common\controller\AutoTaskDate;
use app\common\controller\AutoTaskTime;
use app\common\controller\TaskData;
use app\common\controller\RedisConnect;
use app\common\controller\Audio;
use app\user\controller\Scenarios;
class Callrecord extends User{
private $connect;
public $call_pause_second = 0;
public $fs_num;
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
public function get_microtime_str(){
list($msec,$sec)=explode(' ',microtime());
return  $sec.$msec*1000000;
}
public function joinPhone(){
$joinPhone =  input('post.joinPhone','','trim,strip_tags');
$line_id = input('post.line_id','','trim,strip_tags');
$asr_id = input('post.asr_id','','trim,strip_tags');
$scenarios_id = input('post.scenarios_id','','trim,strip_tags');
$scenarios = new Scenarios();
$arr_check = $scenarios->scenarios_check_by_xiafa($scenarios_id);
if($arr_check[0]==false){
return returnAjax(2,'当前话术存在异常，请修复后再重新启动任务');
}
$user_auth = session('user_auth');
$task_name ='加入呼叫_'.$this->get_microtime_str().'_'.$joinPhone;
$call_type = 1;
$status = 1;
$type = 1;
$robot_count=1;
$start_date =[date('Y-m-d')];
$end_date =[date('Y-m-d',strtotime('+1 day'))];
$start_time=['8:00'];
$end_time=['21:30'];
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
return returnAjax(2,'线路不存在');
}
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
$task_config = [];
$task_config['fs_num'] = 0;
$task_config['member_id'] = $user_auth['uid'];
$task_config["task_name"] = $task_name;
$task_config["type"] = $type;
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
$member_data['task']=$task_id;
$member_data['owner']=$user_auth['uid'];
$member_data['mobile']=$joinPhone;
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
$insert_result = $AutoTaskTime->insert($user_auth['uid'],$task_id,$start_time,$end_time);
if(empty($insert_result)){
\think\Log::record('创建指定时间失败');
}
$TaskData = new TaskData();
$result = $TaskData->start_task($task_id);
if($result == true){
Db::commit();
}else{
Db::rollback();
}
return returnAjax(0,'新建呼叫成功');
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'新建任务失败');
}
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
public function index(){
return $this->fetch();
}
public function current_record(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$is_verification = Db::name('admin')->where('id',$uid)->value('is_verification');
$this->assign('is_verification',$is_verification);
$start = strtotime(date('Y-m-d',time()));
$where = [];
$where['member_id'] = (int)$uid;
$where['create_time'] = ['>=',$start];
$whereOr1['member_id']=(int)$uid;
$whereOr1['update_time'] = ['>=',$start];
$whereOr1['status'] = ['in',[3]];
$whereOr['status']=['not in',[3]];
$whereOr['member_id']=$uid;
$tasklist = Db::name('tel_config')
->field('id,task_id,task_name')
->where(function ($query) use($where) {
$query->where($where);
})->whereOr(function ($query) use($whereOr){
$query->where($whereOr);
})->whereOr(function ($query) use($whereOr1){
$query->where($whereOr1);
})
->order('create_time','desc')
->select();
$this->assign('tasklist',$tasklist);
$where = [];
$where['status'] = 1;
$where['member_id']=$uid;
$where['check_statu'] = ['<>',1];
$where['is_variable'] = 0;
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
$asr_list = Db::name('tel_interface')
->field('id,name')
->where('owner',$uid)
->select();
$this->assign('asr_list',$asr_list);
$line_datas = Db::name('tel_line_group')
->field('id,name')
->where(['user_id'=>$uid,'status'=>1])
->select();
$this->assign('line_datas',$line_datas);
$default_line_id = Db::name('admin')
->where('id',$uid)
->value('default_line_id');
$this->assign('default_line_id',$default_line_id);
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
$yunying_id = $this->get_operator_id($uid);
$wx_config =Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$wx_push_users = Db::name('wx_push_users')
->field('id,name')
->where([
'member_id'=>$uid,
'wx_config_id'=>$wx_config['id'],
])
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
$config = array();
if(!$super){
$config['member_id'] = $uid;
}
$config['status'] = ['>',-1];
$date = strtotime(date('Y-m-d'));
$config = [
'create_time'=>['>=',$date],
'member_id'=>$uid
];
$task_temp = DB::name('tel_tasks_templates')->where('member_id',$uid)->order('id','desc')->column('template','id');
$this->assign('task_temp',$task_temp);
return $this->fetch();
}
public function get_task_list(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$select_type = input('select_type','','trim,strip_tags');
$day = strtotime(date('Y-m-d',time()));
$where = [];
$where['member_id'] = $uid;
$where['create_time'] = array('<=',$day);
$tasklist = [];
$tasklist = Db::name('tel_config')->field('id,task_id,task_name')->where($where)->select();
return returnAjax(0,'成功',$tasklist);
}
public function historical_records(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$is_verification = Db::name('admin')->where('id',$uid)->value('is_verification');
$this->assign('is_verification',$is_verification);
$super = $user_auth["super"];
$task_id = input('task_id','','trim,strip_tags');
$show_date = 1;
if($task_id){
$MemberData = new MemberData();
$last_dial_time = $MemberData->get_last_dial_time($task_id);
$show_date = show_call_date($last_dial_time);
}
$this->assign('show_date',$show_date);
$data = get_date_name('record');
$this->assign('data',$data);
$show_all_days  = config('call_table_days');
$this->assign('show_all_days',$show_all_days);
$where = [];
$where['status'] = 1;
$where['member_id']=$uid;
$where['check_statu'] = ['<>',1];
$where['is_variable']=0;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('scenarioslist',$scenarioslist);
$line_datas = Db::name('tel_line_group')
->field('id,name')
->where(['user_id'=>$uid,'status'=>1])
->select();
$this->assign('line_datas',$line_datas);
$default_line_id = Db::name('admin')
->where('id',$uid)
->value('default_line_id');
$this->assign('default_line_id',$default_line_id);
$asr_list = Db::name('tel_interface')
->field('id,name')
->where('owner',$uid)
->select();
$this->assign('asr_list',$asr_list);
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
$yunying_id = $this->get_operator_id($uid);
$wx_config =Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$wx_push_users = Db::name('wx_push_users')
->field('id,name')
->where([
'member_id'=>$uid,
'wx_config_id'=>$wx_config['id'],
])
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
$task_temp = DB::name('tel_tasks_templates')->where('member_id',$uid)->order('id','desc')->column('template','id');
$this->assign('task_temp',$task_temp);
return $this->fetch();
}
public function monitor(){
$Choice = input('Choice','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$Db_tel_config = Db::name('tel_config');
$config_where = array();
$id_where = array();
if($Choice){
$config_where['task_id']  = array('eq',$Choice);
$config_where['member_id']  = array('eq',$uid);
$scenarios_id = $Db_tel_config->where($config_where)->value('scenarios_id');
$id_where['id'] = array('eq',$scenarios_id);
}
$id_where['member_id'] = array('eq',$uid);
$scenarios = Db::name('tel_scenarios')->where($id_where)->select();
return $scenarios;
}
public function monitor_scen(){
$Choice = input('Choice','','trim,strip_tags');
if(empty($Choice)){
$data = [];
$data['list1'] = [];
$data['list2'] = [];
$data['list3'] = [];
return returnAjax(1 ,'获取数据成功1',$data);
}
$where1['scenarios_id'] = array('eq',$Choice);
$where1['flow_label'] = array('neq','');
$where1['label_status'] = array('eq',1);
$where2['scenarios_id'] = array('eq',$Choice);
$where2['label'] = array('neq','');
$where2['label_status'] = array('eq',1);
$nodelist1 = Db::name('tel_flow_node')->field('flow_label')->where($where1)->select();
$flow_branch_labels = Db::name('tel_flow_branch')
->alias('branch')
->join('tel_flow_node node','branch.flow_id = node.id','LEFT')
->field('branch.label')
->where(['node.scenarios_id'=>$Choice,'branch.label'=>['not in',['','null']],'branch.label_status'=>1])
->select();
foreach($flow_branch_labels as $key=>$value){
$nodelist1[] = ['flow_label'=>$value['label']];
}
$nodelist2 = Db::name('tel_knowledge')->where($where2)->select();
$nodelist3 = Db::name('tel_label')->where([
'scenarios_id'=>['eq',$Choice],
'label'=>['neq',''],
'type'=>0,
'label_status'=>1
])->select();
$data['list1'] = $nodelist1;
$data['list2'] = $nodelist2;
$data['list3'] = $nodelist3;
return returnAjax(1,'获取数据成功',$data);
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
public function insert_crm(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$select_type = input('select_type','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$id = input('id','','trim,strip_tags');
if(empty($select_type)){
$data_x = Db::name('tel_call_record')->where(['id'=>$id])->find();
$level=$data_x['level'];
$task_id=$data_x['task_id'];
$task_name = getTaskName($task_id);
}else{
$table_name = get_table_name($select_type);
$data_x = Db::name($table_name)->where(['id'=>$id])->find();
$level=$data_x['level'];
$task_id=$data_x['task_id'];
$task_name = getTaskName($task_id);
}
$name = Db::name('member')->where(['mobile'=>$phone,'task'=>$data_x['task_id']])->value('nickname');
if(empty($name)){
$name='';
}
$tel_bills = Db::name('tel_bills')->field('phone,message,duration,path,role,status,hit_keyword,create_time,call_id,hit_type,hit_info')->where('call_id',$data_x['call_id'])->select();
$where['mobile'] = $phone;
$where['owner'] = $uid;
$where_crm['member_id'] = $uid;
$where_crm['phone'] = $phone;
$where_crm['status']=1;
$res = Db::name('crm')->where($where_crm)->count();
if($res >0){
return returnAjax(1,'此号码已经加入crm');
}else{
Db::startTrans();
try {
unset($data_x['id']);
$call_record_id = Db::name('tel_crm_call_record')->insertGetId($data_x);
Db::name('crm_bills')->insertAll($tel_bills);
$data['member_id'] = $uid;
$data['phone'] = $phone;
$data['create_time'] = time();
$data['status'] = 1;
$data['level'] = $level;
$data['name'] = $name;
$data['task_id'] = $task_id;
$data['task_name'] = $task_name;
$data['call_record_id']=$call_record_id;
$resx = Db::name('crm')->insert($data);
if($resx){
Db::commit();
return returnAjax(0,'加入CRM成功');
}else{
Db::rollback();
return returnAjax(1,'加入CRM失败');
}
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'加入CRM失败');
}
}
}
public function joincrm(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$vals = input('vals','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$args = $this->get_condition();
$vals = explode(',',$vals);
$chaos_num  = input('chaos_num','','trim,strip_tags');
$mobile = [];
$list = [];
$TelCallRecord = new TelCallRecord();
if($type == 0){
$where['id'] = array('in',$vals);
$where['owner'] = $uid;
$mobile = Db::name('tel_call_record')->field('id,mobile,task_id')->group('mobile')->where($where)->select();
}else if($type == 1){
$res = $TelCallRecord->screent($args,0,10);
$list = $res['table'];
}
if($list){
foreach($list as $k =>$v){
$mobile[$k]['mobile'] = $v['mobile'];
$mobile[$k]['task_id'] = $v['task_id'];
$mobile[$k]['id'] = $v['id'];
}
$mobile = $this->assoc_unique($mobile,'mobile');
}
$number_count = count($mobile);
$count = count($mobile);
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_number_count';
$total_key = 'task_'.$chaos_num .'_total_number_count';
$RedisConnect->set($key,$number_count);
$RedisConnect->set($total_key,$number_count);
$mobile_nes = $number_count;
$data = array();
$totalCnt = 0;
$mobile_here = 0;
foreach($mobile as $key=>$vo){
$num = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$vo['mobile'],'status'=>1])->count();
if($num <= 0){
$record = Db::name('tel_call_record')->where('id',$vo['id'])->find();
$tel_bills = Db::name('tel_bills')->field('phone,message,duration,path,role,status,hit_keyword,create_time,call_id,hit_type,hit_info')->where('call_id',$record['call_id'])->select();
unset($record['id']);
$call_record_id = Db::name('tel_crm_call_record')->insertGetId($record);
Db::name('crm_bills')->insertAll($tel_bills);
$name = Db::name('member')->where(['mobile'=>$vo['mobile'],'task'=>$vo['task_id']])->value('nickname');
$data[$key]['member_id'] = $uid;
$data[$key]['phone'] = $vo['mobile'];
$data[$key]['create_time'] = time();
$data[$key]['status'] = 1;
$data[$key]['call_record_id']=$call_record_id;
$data[$key]['task_id']=$record['task_id'];
$data[$key]['task_name']=getTaskName($record['task_id']);
$data[$key]['level']=$record['level'];
$data[$key]['name']=$name;
$mobile_here++;
$totalCnt++;
}else{
$mobile_nes --;
$number_count--;
}
if($mobile_here == 1000 ||$totalCnt == $mobile_nes){
if($data){
$result = DB::name('crm')->insertAll($data);
$number_count = $number_count -$result;
$number_count_key = 'task_'.$chaos_num .'_number_count';
$RedisConnect->set($number_count_key,$number_count);
array_splice($data,0,count($data));
}
$mobile_here = 0;
}
}
return returnAjax(1,'添加完成');
}
public function joincrmhistor(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$vals = input('vals','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
$select_type = input('select_type','','trim,strip_tags');
$args = $this->get_condition();
$vals = explode(',',$vals);
$chaos_num  = input('chaos_num','','trim,strip_tags');
$mobile = [];
$list = [];
$TelCallHistoricalRecord = new TelCallHistoricalRecord();
if($type == 0){
$where['id'] = array('in',$vals);
$where['owner'] = $uid;
$table_name = get_table_name($select_type);
$mobile = Db::name($table_name)->group('mobile')->field('id,mobile,task_id')->where($where)->select();
}else if($type == 1){
$res = $TelCallHistoricalRecord->screent($args,0,10);
$list = $res['table'];
}
if($list){
foreach($list as $k =>$v){
$mobile[$k]['mobile'] = $v['mobile'];
$mobile[$k]['task_id'] = $v['task_id'];
$mobile[$k]['id'] = $v['id'];
}
$mobile = $this->assoc_unique($mobile,'mobile');
}
$number_count = count($mobile);
$count = count($mobile);
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_number_count';
$total_key = 'task_'.$chaos_num .'_total_number_count';
$RedisConnect->set($key,$number_count);
$RedisConnect->set($total_key,$number_count);
$mobile_nes = $number_count;
$data = array();
$totalCnt = 0;
$mobile_here = 0;
foreach($mobile as $key=>$vo){
$num = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$vo['mobile'],'status'=>1])->count();
if($num <= 0){
$tableName = get_table_name($select_type);
$record = Db::name($tableName)->where('id',$vo['id'])->find();
$tel_bills = Db::name('tel_bills')->field('phone,message,duration,path,role,status,hit_keyword,create_time,call_id,hit_type,hit_info')->where('call_id',$record['call_id'])->select();
unset($record['id']);
$call_record_id = Db::name('tel_crm_call_record')->insertGetId($record);
Db::name('crm_bills')->insertAll($tel_bills);
$name = Db::name('member')->where(['mobile'=>$vo['mobile'],'task'=>$vo['task_id']])->value('nickname');
$data[$key]['member_id'] = $uid;
$data[$key]['phone'] = $vo['mobile'];
$data[$key]['create_time'] = time();
$data[$key]['status'] = 1;
$data[$key]['call_record_id']=$call_record_id;
$data[$key]['task_id']=$record['task_id'];
$data[$key]['task_name']=getTaskName($record['task_id']);
$data[$key]['level']=$record['level'];
$data[$key]['name']=$name;
$mobile_here++;
$totalCnt++;
}else{
$mobile_nes --;
$number_count--;
}
if($mobile_here == 1000 ||$totalCnt == $mobile_nes){
if($data){
$result = DB::name('crm')->insertAll($data);
$number_count = $number_count -$result;
$number_count_key = 'task_'.$chaos_num .'_number_count';
$RedisConnect->set($number_count_key,$number_count);
array_splice($data,0,count($data));
}
$mobile_here = 0;
}
}
return returnAjax(1,'添加完成');
}
public function scenarios_rule(){
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$leveid = input('leveid','','trim,strip_tags');
$intentionlist = Db::name('tel_intention_rule_template')
->field('tir.*')
->alias('tirt')
->join('tel_intention_rule tir','tir.template_id = tirt.id','LEFT')
->where([
'tirt.scenarios_id'=>$scenarios_id,
'tirt.status'=>1,
'tir.level'=>$leveid
])
->select();
$rule =  array();
foreach($intentionlist as $key =>$vo){
$intentionlist[$key]['rule'] = unserialize($vo['rule']);
}
foreach($intentionlist as $key2 =>$vo2){
if($leveid == $vo2['level']){
if($vo2['rule']){
$rule[$key2]['rule'] = $vo2['rule'];
$rule[$key2]['name'] = $vo2['name'];
}
}
}
return returnAjax(0,'获取数据成功',$rule);
}
public function get_condition(){
$args = array();
$select_type = input('select_type','','trim,strip_tags');
if(!empty($select_type)){
$args['select_type'] = $select_type;
}
$order = input('order','','trim,strip_tags');
if(!empty($order)){
$args['order'] = $order;
}
$type = input('type','','trim,strip_tags');
if(!empty($type)){
$args['type'] = $type;
}
$task_id = input('task_id','','trim,strip_tags');
if(!empty($task_id)){
$args['task_id'] = $task_id;
}
$scenarios_id = input('scenarios_id','','trim,strip_tags');
if(!empty($scenarios_id)){
$args['scenarios_id'] = $scenarios_id;
}
$start_call_time = input('start_call_time','','trim,strip_tags');
if(!empty($start_call_time)){
$args['start_call_time'] = $start_call_time;
}
$end_call_time = input('end_call_time','','trim,strip_tags');
if(!empty($end_call_time)){
$args['end_call_time'] = $end_call_time;
}
$phone = input('phone','','trim,strip_tags');
if(!empty($phone)){
$args['phone'] = $phone;
}
$level = input('level/a','','trim,strip_tags');
if(!empty($level)){
$args['level'] = $level;
}
$status = input('status/a','','trim,strip_tags');
if(is_array($status) &&count($status) >0){
$args['status'] = $status;
}
$min_duration = input('min_duration','','trim,strip_tags');
if($min_duration != ''){
$args['min_duration'] = $min_duration;
}
$max_duration = input('max_duration','','trim,strip_tags');
if($max_duration != ''){
$args['max_duration'] = $max_duration;
}
$call_times = input('call_times','','trim,strip_tags');
if($call_times !=''){
$args['call_times'] = $call_times;
}
$effective_times = input('effective_times','','trim,strip_tags');
if($effective_times != ''){
$args['effective_times'] = $effective_times;
}
$hit_times = input('hit_times','','trim,strip_tags');
if($hit_times!=''){
$args['hit_times'] = $hit_times;
}
$affirm_times = input('affirm_times','','trim,strip_tags');
if($affirm_times !=''){
$args['affirm_times'] = $affirm_times;
}
$neutral_times = input('neutral_times','','trim,strip_tags');
if($neutral_times !=''){
$args['neutral_times'] = $neutral_times;
}
$negative_times = input('negative_times','','trim,strip_tags');
if($negative_times !=''){
$args['negative_times'] = $negative_times;
}
$call_times_sel = input('call_times_sel','','trim');
if($call_times_sel !=''){
$args['call_times_sel'] = $call_times_sel;
}
$effective_times_sel = input('effective_times_sel','','trim');
if($effective_times_sel != ''){
$args['effective_times_sel'] = $effective_times_sel;
}
$hit_times_sel = input('hit_times_sel','','trim');
if($hit_times_sel!=''){
$args['hit_times_sel'] = $hit_times_sel;
}
$affirm_times_sel = input('affirm_times_sel','','trim');
if($affirm_times_sel !=''){
$args['affirm_times_sel'] = $affirm_times_sel;
}
$neutral_times_sel = input('neutral_times_sel','','trim');
if($neutral_times_sel !=''){
$args['neutral_times_sel'] = $neutral_times_sel;
}
$negative_times_sel = input('negative_times_sel','','trim');
if($negative_times_sel !=''){
$args['negative_times_sel'] = $negative_times_sel;
}
$invitation = input('invitations','','trim,strip_tags');
if($invitation !=''){
$args['invitation'] = $invitation;
}
$flow_labels = input('flow_labels/a','','trim,strip_tags');
if(is_array($flow_labels) &&count($flow_labels) >0){
$args['flow_label'] = $flow_labels;
}
$knowledge_labels = input('knowledge_labels/a','','trim,strip_tags');
if(is_array($knowledge_labels) &&count($knowledge_labels) >0){
$args['knowledge_label'] = $knowledge_labels;
}
$semantic_label = input('semantic_label/a','','trim,strip_tags');
if(is_array($semantic_label) &&count($semantic_label) >0){
$args['semantic_label'] = $semantic_label;
}
$review = input('review','','trim,strip_tags');
if($review!='') {
$args['review'] = $review;
}
return $args;
}
public function get_new_call_record_befor(){
$user_auth = session('user_auth');
$page = intval(input('page','','trim,strip_tags'));
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$limit = intval(input('limit','','trim,strip_tags'));
if(empty($limit)){
$limit=10;
}
$uid = $user_auth["uid"];
$args = $this->get_condition();
$befor_list=[];
$TelCallRecord = new TelCallRecord();
$datas = $TelCallRecord->get($args,$page,$limit);
\think\Log::record('当天通话记录');
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
$datas['list'][$k]['page']= $page;
$befor_list[$k] = $list['mobile']."-".$list['task_id'];
}
$search_str=$mobile.'-'.$taskId;
$weizhi_key = array_search($search_str,$befor_list);
if($page <= 0){
return returnAjax(1,'没有上一条了',[]);
}
if($weizhi_key==0){
$page_new=$page-1;
if($page_new<=0){
return returnAjax(1,'没有上一条了',[]);
}
$datas_1 = $TelCallRecord->get($args,$page_new,$limit);
foreach($datas_1['list'] as $k =>$list){
$datas_1['list'][$k]['page']= $page_new;
}
if(empty($datas_1['list'])||empty($datas_1['list'][$limit-1]) ||count($datas_1['list']) <=0){
return returnAjax(1,'没有上一条了',[]);
}
return returnAjax(0,'成功',$datas_1['list'][$limit-1]);
}
if(isset($datas['list'][$weizhi_key-1])===false){
return returnAjax(1,'没有上一条了',[]);
}
return returnAjax(0,'成功',$datas['list'][$weizhi_key-1]);
}
public function get_new_call_record_next(){
$user_auth = session('user_auth');
$page = intval(input('page','','trim,strip_tags'));
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$limit = intval(input('limit','','trim,strip_tags'));
if(empty($limit)){
$limit=10;
}
$uid = $user_auth["uid"];
$args = $this->get_condition();
$next_list=[];
$TelCallRecord = new TelCallRecord();
$datas = $TelCallRecord->get($args,$page,$limit);
\think\Log::record('当天通话记录');
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
$datas['list'][$k]['page']= $page;
$next_list[$k] = $list['mobile']."-".$list['task_id'];
}
$search_str=$mobile.'-'.$taskId;
$weizhi_key = array_search($search_str,$next_list);
if($page >$datas['total']){
return returnAjax(1,'没有下一条了',[]);
}
if($weizhi_key==$limit-1){
$page_new= $page+1;
if($page_new >$datas['total']){
return returnAjax(1,'没有下一条了',[]);
}
$datas_1 = $TelCallRecord->get($args,$page_new ,$limit);
foreach($datas_1['list'] as $k =>$list){
$datas_1['list'][$k]['page']= $page_new;
}
if(empty($datas_1['list'])||empty($datas_1['list'][0]) ||count($datas_1['list']) <=0){
return returnAjax(1,'没有下一条了',[]);
}
return returnAjax(0,'成功',$datas_1['list'][0]);
}
if(isset($datas['list'][$weizhi_key+1])===false){
return returnAjax(1,'没有下一条了',[]);
}
return returnAjax(0,'成功',$datas['list'][$weizhi_key+1]);
}
public function get_new_call_record(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$args = $this->get_condition();
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
if(empty($limit)){
$limit=10;
}
$redis = RedisConnect::get_redis_connect();
$TelCallRecord = new TelCallRecord();
$datas = $TelCallRecord->get($args,$page,$limit);
\think\Log::record('当天通话记录');
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
if( !empty($list['task_id']) &&!empty($list['mobile']) )
$datas['list'][$k]['nickname'] = Db::name('member')->where(array('task'=>$list['task_id'],'mobile'=>$list['mobile']  ))->value('nickname') ??'';
if(empty($list['review'])){
$redis_key = 'tel_call_record_review';
$datas['list'][$k]['review'] = $redis->getbit($redis_key,$list['id']);
}
}
return returnAjax(0,'成功',$datas);
}
public function get_new_call_historical_record(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$args = $this->get_condition();
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$TelCallHistoricalRecord = new TelCallHistoricalRecord();
$datas = $TelCallHistoricalRecord->get($args,$page,$limit);
$table_name = $datas['table_name'];
$redis = RedisConnect::get_redis_connect();
$redis_key = $table_name .'_review';
\think\Log::record('历史通话记录');
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
if( !empty($list['task_id']) &&!empty($list['mobile']) )
$datas['list'][$k]['nickname'] = Db::name('member')->where(array('task'=>$list['task_id'],'mobile'=>$list['mobile']  ))->value('nickname') ??'';
if(empty($list['review'])){
$datas['list'][$k]['review'] = $redis->getbit($redis_key,$list['id']);
}
}
return returnAjax(0,'成功',$datas);
}
public function get_new_call_sevendays_historical_record(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$args = $this->get_condition();
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$TelCallHistoricalSevenDaysRecord = new TelCallHistoricalSevenDaysRecord();
$datas = $TelCallHistoricalSevenDaysRecord->get($args,$page,$limit);
\think\Log::record('历史通话记录');
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
if( !empty($list['task_id']) &&!empty($list['mobile']) )
$datas['list'][$k]['nickname'] = Db::name('member')->where(array('task'=>$list['task_id'],'mobile'=>$list['mobile']  ))->value('nickname') ??'';
}
return returnAjax(0,'成功',$datas);
}
public function get_new_call_sevendays_historical_record_befor(){
$user_auth = session('user_auth');
$page = intval(input('page','','trim,strip_tags'));
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$limit = intval(input('limit','','trim,strip_tags'));
if(empty($limit)){
$limit=10;
}
$uid = $user_auth["uid"];
$args = $this->get_condition();
$befor_list=[];
$TelCallHistoricalSevenDaysRecord = new TelCallHistoricalSevenDaysRecord();
$datas = $TelCallHistoricalSevenDaysRecord->get($args,$page,0);
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
$datas['list'][$k]['page']= $page;
$befor_list[$k] = $list['mobile']."-".$list['task_id'];
}
$search_str=$mobile.'-'.$taskId;
$weizhi_key = array_search($search_str,$befor_list);
if($page <= 0){
return returnAjax(1,'没有上一条了',[]);
}
if($weizhi_key==0){
$page_new=$page-1;
if($page_new<=0){
return returnAjax(1,'没有上一条了',[]);
}
$datas_1 = $TelCallHistoricalRecord->get($args,$page_new,$limit);
foreach($datas_1['list'] as $k =>$list){
$datas_1['list'][$k]['page']= $page_new;
}
if(empty($datas_1['list'])||empty($datas_1['list'][$limit-1]) ||count($datas_1['list']) <=0){
return returnAjax(1,'没有上一条了',[]);
}
return returnAjax(0,'成功',$datas_1['list'][$limit-1]);
}
if(isset($datas['list'][$weizhi_key-1])===false){
return returnAjax(1,'没有上一条了',[]);
}
return returnAjax(0,'成功',$datas['list'][$weizhi_key-1]);
}
public function get_new_call_sevendays_historical_record_next(){
$user_auth = session('user_auth');
$page = intval(input('page','','trim,strip_tags'));
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$limit = intval(input('limit','','trim,strip_tags'));
if(empty($limit)){
$limit=10;
}
$uid = $user_auth["uid"];
$args = $this->get_condition();
$next_list=[];
$TelCallHistoricalSevenDaysRecord = new TelCallHistoricalSevenDaysRecord();
$datas = $TelCallHistoricalSevenDaysRecord->get($args,$page,0);
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
$datas['list'][$k]['page']= $page;
$next_list[$k] = $list['mobile']."-".$list['task_id'];
}
$search_str=$mobile.'-'.$taskId;
$weizhi_key = array_search($search_str,$next_list);
if($page >$datas['total']){
return returnAjax(1,'没有下一条了',[]);
}
if($weizhi_key==$limit-1){
$page_new= $page+1;
if($page_new >$datas['total']){
return returnAjax(1,'没有下一条了',[]);
}
$datas_1 = $TelCallHistoricalRecord->get($args,$page_new ,$limit);
foreach($datas_1['list'] as $k =>$list){
$datas_1['list'][$k]['page']= $page_new;
}
if(empty($datas_1['list'])||empty($datas_1['list'][0]) ||count($datas_1['list']) <=0){
return returnAjax(1,'没有下一条了',[]);
}
return returnAjax(0,'成功',$datas_1['list'][0]);
}
if(isset($datas['list'][$weizhi_key+1])===false){
return returnAjax(1,'没有下一条了',[]);
}
return returnAjax(0,'成功',$datas['list'][$weizhi_key+1]);
}
public function get_new_call_historical_record_befor(){
$user_auth = session('user_auth');
$page = intval(input('page','','trim,strip_tags'));
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$limit = intval(input('limit','','trim,strip_tags'));
if(empty($limit)){
$limit=10;
}
$uid = $user_auth["uid"];
$args = $this->get_condition();
$befor_list=[];
$TelCallHistoricalRecord = new TelCallHistoricalRecord();
$datas = $TelCallHistoricalRecord->get($args,$page,$limit);
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
$datas['list'][$k]['page']= $page;
$befor_list[$k] = $list['mobile']."-".$list['task_id'];
}
$search_str=$mobile.'-'.$taskId;
$weizhi_key = array_search($search_str,$befor_list);
if($page <= 0){
return returnAjax(1,'没有上一条了',[]);
}
if($weizhi_key==0){
$page_new=$page-1;
if($page_new<=0){
return returnAjax(1,'没有上一条了',[]);
}
$datas_1 = $TelCallHistoricalRecord->get($args,$page_new,$limit);
foreach($datas_1['list'] as $k =>$list){
$datas_1['list'][$k]['page']= $page_new;
}
if(empty($datas_1['list'])||empty($datas_1['list'][$limit-1]) ||count($datas_1['list']) <=0){
return returnAjax(1,'没有上一条了',[]);
}
return returnAjax(0,'成功',$datas_1['list'][$limit-1]);
}
if(isset($datas['list'][$weizhi_key-1])===false){
return returnAjax(1,'没有上一条了',[]);
}
return returnAjax(0,'成功',$datas['list'][$weizhi_key-1]);
}
public function get_new_call_historical_record_next(){
$user_auth = session('user_auth');
$page = intval(input('page','','trim,strip_tags'));
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$limit = intval(input('limit','','trim,strip_tags'));
if(empty($limit)){
$limit=10;
}
$uid = $user_auth["uid"];
$args = $this->get_condition();
$next_list=[];
$TelCallHistoricalRecord = new TelCallHistoricalRecord();
$datas = $TelCallHistoricalRecord->get($args,$page,$limit);
\think\Log::record('历史通话记录');
foreach($datas['list'] as $k =>$list){
$count = Db::name('crm')->where(['member_id'=>$uid,'phone'=>$list['mobile'],'status'=>1])->count('*');
if($count>0){
$datas['list'][$k]['state_crm']=1;
}else{
$datas['list'][$k]['state_crm']=0;
}
$datas['list'][$k]['mobile']=hide_phone_middle($datas['list'][$k]['mobile']);
$datas['list'][$k]['page']= $page;
$next_list[$k] = $list['mobile']."-".$list['task_id'];
}
$search_str=$mobile.'-'.$taskId;
$weizhi_key = array_search($search_str,$next_list);
if($page >$datas['total']){
return returnAjax(1,'没有下一条了',[]);
}
if($weizhi_key==$limit-1){
$page_new= $page+1;
if($page_new >$datas['total']){
return returnAjax(1,'没有下一条了',[]);
}
$datas_1 = $TelCallHistoricalRecord->get($args,$page_new ,$limit);
foreach($datas_1['list'] as $k =>$list){
$datas_1['list'][$k]['page']= $page_new;
}
if(empty($datas_1['list'])||empty($datas_1['list'][0]) ||count($datas_1['list']) <=0){
return returnAjax(1,'没有下一条了',[]);
}
return returnAjax(0,'成功',$datas_1['list'][0]);
}
if(isset($datas['list'][$weizhi_key+1])===false){
return returnAjax(1,'没有下一条了',[]);
}
return returnAjax(0,'成功',$datas['list'][$weizhi_key+1]);
}
public function import_phone_today(){
$args = $this->get_condition();
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$type = input('type','','trim,strip_tags');
$daochu_type = input('daochu_type','','trim,strip_tags');
$arr = input('vals','','trim,strip_tags');
$arr = explode(',',$arr);
$alt = input('alt','0','trim,strip_tags');
$chaos_num = input('chaos_num','','trim,strip_tags');
$TelCallRecord = new TelCallRecord();
if($type == 0){
$where['id'] = array('in',$arr);
$where['owner'] = array('eq',$uid);
$list = Db::name('tel_call_record')
->field('id,status,level,mobile,duration,task_id,last_dial_time,record_path')
->where($where)
->select();
$list_count = count($list);
}else{
$res = $TelCallRecord->screent($args,0,0);
$list = $res['table'];
$list_count = Db::name('tel_call_record')->where('owner',$uid)->count();
}
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$RedisConnect->set($key,$list_count);
$complete_key = 'task_'.$chaos_num .'_complete_count';
if(empty($daochu_type) ||$daochu_type=="xlsx"){
$objPHPExcel = new \PHPExcel();
if($alt == 1){
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A1','编号')
->setCellValue('B1','手机号码')
->setCellValue('C1','通话时长(S)')
->setCellValue('D1','状态')
->setCellValue('E1','意向等级')
->setCellValue('F1','任务名称')
->setCellValue('G1','话术名称')
->setCellValue('H1','拨打时间')
->setCellValue('I1','名字')
->setCellValue('J1','录音地址');
$scenariosTmpArr=Db::name('tel_scenarios')->where('member_id',$uid)->field('id,name')->select();
$scenariosArr=[];
foreach ($scenariosTmpArr as $v){
$scenariosArr[$v['id'] ]=$v['name'];
}
unset($scenariosTmpArr);
$telConfigTmpArr=Db::name('tel_config')->where('member_id',$uid)->field('task_id,task_name,scenarios_id')->select();
$telConfigArr=[];
foreach ($telConfigTmpArr as $v){
$telConfigArr[$v['task_id'] ]['task_name'] =$v['task_name'];
$telConfigArr[$v['task_id'] ]['scenario_name'] = $scenariosArr[$v['scenarios_id'] ]??'';
}
unset($telConfigTmpArr);
$accuracy_num = 0;
foreach ($list as $k =>$v) {
$strstatus = '未拨打';
switch ($v['status']) {
case 2:
$strstatus = "已接通";
break;
case 3:
$strstatus = "无人接听";
break;
case 4:
$strstatus = "停机";
break;
case 5:
$strstatus = "空号";
break;
case 6:
$strstatus = "正在通话中";
break;
case 7:
$strstatus = "关机";
break;
case 8:
$strstatus = "用户拒接";
break;
case 9:
$strstatus = "网络忙";
break;
case 10:
$strstatus = "来电提醒";
break;
case 11:
$strstatus = "呼叫转移失败";
break;
default:
$strstatus = "--";
}
$strlevel = "";
switch ($v['level']) {
case 6:
$strlevel = "A级(意向客户)";
break;
case 5:
$strlevel = "B级(一般意向)";
break;
case 4:
$strlevel = "C级(简单对话)";
break;
case 3:
$strlevel = "D级(无效对话)";
break;
case 2:
$strlevel = "E级(有效未接通)";
break;
case 1:
$strlevel = "F级(无效号码)";
break;
default:
$strlevel = "--";
}
$num = $k +2;
if($type == 0){
$v['last_dial_time'] = date('Y-m-d H:i:s',$v['last_dial_time']);
}
if( !empty($v['task_id']) &&!empty($v['mobile']) )
$nickname = Db::name('member')->where(array('task'=>$v['task_id'],'mobile'=>$v['mobile']  ))->value('nickname') ??'';
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A'.$num,$v['id'])
->setCellValue('B'.$num,hide_phone_middle( $v['mobile'] )    )
->setCellValue('C'.$num,$v['duration'])
->setCellValue('D'.$num,$strstatus)
->setCellValue('E'.$num,$strlevel)
->setCellValue('F'.$num,$telConfigArr[$v['task_id'] ]['task_name'] )
->setCellValue('G'.$num,$telConfigArr[$v['task_id'] ]['scenario_name'] )
->setCellValue('H'.$num,$v['last_dial_time'])
->setCellValue('I'.$num,$nickname)
->setCellValue('J'.$num,config('record_path').$v['record_path']);
$accuracy_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$accuracy_num);
}
}else{
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A1','序号')
->setCellValue('B1','电话');
$accuracy_num = 0 ;
$x=0;
foreach ($list as $k =>$v) {
$x+=1;
$num = $k +2;
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A'.$num,$x)
->setCellValue('B'.$num,hide_phone_middle( $v['mobile'] ) );
$accuracy_num ++;
$RedisConnect->set($complete_key,$accuracy_num);
}
}
$setTitle='Sheet1';
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
$PHPWriter->save($execlpath);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
return returnAjax(0,'导出成功',config('res_url').ltrim($execlpath,"."));
}else{
if($alt == 0){
$phone_count=count($list);
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$filename = rand_string(12,'',time()).'txtphone.txt';
$execlpath.=$filename;
$file = fopen($execlpath,"w");
$accuracy_num = 0;
for($i=0;$i<$phone_count;$i++){
fwrite($file,$list[$i]['mobile'] ."\r\n");
$accuracy_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$accuracy_num);
}
fclose($file);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
if($list){
return returnAjax(0,'成功',config('res_url').'/api/file/download?file_path='.$execlpath);
}else{
return returnAjax(1,'失败!',"失败");
}
}
}
}
public function import_phone_histor(){
$args = $this->get_condition();
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$type = input('type','','trim,strip_tags');
$arr = input('vals','','trim,strip_tags');
$daochu_type = input('daochu_type','','trim,strip_tags');
$arr = explode(',',$arr);
$alt = input('alt','0','trim,strip_tags');
$chaos_num = input('chaos_num','','trim,strip_tags');
$select_type = input('select_type','','trim,strip_tags');
$table_name = get_table_name($select_type);
$TelCallRecord = new TelCallHistoricalRecord();
if($type == 0){
$where['id'] = array('in',$arr);
$where['owner'] = array('eq',$uid);
$list = Db::name($table_name)
->field('id,status,level,mobile,duration,task_id,last_dial_time,record_path')
->where($where)
->select();
$list_count = count($list);
}else{
$res = $TelCallRecord->screent($args,0,0);
$list = $res['table'];
$list_count = Db::name($table_name)->where('owner',$uid)->count();
}
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$RedisConnect->set($key,$list_count);
$complete_key = 'task_'.$chaos_num .'_complete_count';
if(empty($daochu_type) ||$daochu_type=="xlsx"){
$objPHPExcel = new \PHPExcel();
if($alt == 1){
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A1','编号')
->setCellValue('B1','手机号码')
->setCellValue('C1','通话时长(S)')
->setCellValue('D1','状态')
->setCellValue('E1','意向等级')
->setCellValue('F1','任务名称')
->setCellValue('G1','话术名称')
->setCellValue('H1','拨打时间')
->setCellValue('I1','名字')
->setCellValue('J1','录音地址');
$scenariosTmpArr=Db::name('tel_scenarios')->where('member_id',$uid)->field('id,name')->select();
$scenariosArr=[];
foreach ($scenariosTmpArr as $v){
$scenariosArr[$v['id'] ]=$v['name'];
}
unset($scenariosTmpArr);
$telConfigTmpArr=Db::name('tel_config')->where('member_id',$uid)->field('task_id,task_name,scenarios_id')->select();
$telConfigArr=[];
foreach ($telConfigTmpArr as $v){
$telConfigArr[$v['task_id'] ]['task_name'] =$v['task_name'];
$telConfigArr[$v['task_id'] ]['scenario_name'] = $scenariosArr[$v['scenarios_id'] ]??'';
}
unset($telConfigTmpArr);
$accuracy_num = 0;
foreach ($list as $k =>$v) {
$strstatus = '未拨打';
switch ($v['status']) {
case 2:
$strstatus = "已接通";
break;
case 3:
$strstatus = "无人接听";
break;
case 4:
$strstatus = "停机";
break;
case 5:
$strstatus = "空号";
break;
case 6:
$strstatus = "正在通话中";
break;
case 7:
$strstatus = "关机";
break;
case 8:
$strstatus = "用户拒接";
break;
case 9:
$strstatus = "网络忙";
break;
case 10:
$strstatus = "来电提醒";
break;
case 11:
$strstatus = "呼叫转移失败";
break;
default:
$strstatus = "--";
}
$strlevel = "";
switch ($v['level']) {
case 6:
$strlevel = "A级(意向客户)";
break;
case 5:
$strlevel = "B级(一般意向)";
break;
case 4:
$strlevel = "C级(简单对话)";
break;
case 3:
$strlevel = "D级(无效对话)";
break;
case 2:
$strlevel = "E级(有效未接通)";
break;
case 1:
$strlevel = "F级(无效号码)";
break;
default:
$strlevel = "--";
}
$num = $k +2;
if($type == 0){
$v['last_dial_time'] = date('Y-m-d H:i:s',$v['last_dial_time']);
}
if( !empty($v['task_id']) &&!empty($v['mobile']) )
$nickname = Db::name('member')->where(array('task'=>$v['task_id'],'mobile'=>$v['mobile']  ))->value('nickname') ??'';
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A'.$num,$v['id'])
->setCellValue('B'.$num,hide_phone_middle($v['mobile'] )  )
->setCellValue('C'.$num,$v['duration'])
->setCellValue('D'.$num,$strstatus)
->setCellValue('E'.$num,$strlevel)
->setCellValue('F'.$num,$telConfigArr[$v['task_id'] ]['task_name']??'')
->setCellValue('G'.$num,$telConfigArr[$v['task_id'] ]['scenario_name']??'')
->setCellValue('H'.$num,$v['last_dial_time'])
->setCellValue('I'.$num,$nickname)
->setCellValue('J'.$num,config('history_record_path').$v['record_path']);
$accuracy_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$accuracy_num);
}
}else{
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A1','序号')
->setCellValue('B1','电话');
$accuracy_num = 0;
$x=0;
foreach ($list as $k =>$v) {
$x+=1;
$num = $k +2;
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A'.$num,$x)
->setCellValue('B'.$num,hide_phone_middle( $v['mobile'] ));
$accuracy_num ++;
$RedisConnect->set($complete_key,$accuracy_num);
}
}
$setTitle='Sheet1';
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
$PHPWriter->save($execlpath);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
return returnAjax(0,'导出成功',config('res_url').ltrim($execlpath,"."));
}else{
if($alt == 0){
$phone_count=count($list);
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$filename = rand_string(12,'',time()).'txtphone.txt';
$execlpath.=$filename;
$file = fopen($execlpath,"w");
$accuracy_num = 0;
for($i=0;$i<$phone_count;$i++){
fwrite($file,$list[$i]['mobile'] ."\r\n");
$accuracy_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$accuracy_num);
}
fclose($file);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
if($list){
return returnAjax(0,'成功',config('res_url').'/api/file/download?file_path='.$execlpath);
}else{
return returnAjax(1,'失败!',"失败");
}
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
public function create_call_record_task()
{
$task_name = input('task_name','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$start_date = input('start_date/a','','trim,strip_tags');
$end_date = input('end_date/a','','trim,strip_tags');
$start_time = input('start_time/a','','trim,strip_tags');
$end_time = input('end_time/a','','trim,strip_tags');
$is_auto = input('is_auto','','trim,strip_tags');
$robot_count = input('robot_count','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
$asr_id = input('asr_id/d','','trim,strip_tags');
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
$add_crm_zuoxi = input('crm_push_user_id','','trim,strip_tags');
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
->where('id',$line_id)
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
$task_config['add_crm_zuoxi'] = $add_crm_zuoxi;
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
$TelCallRecord = new TelCallRecord();
$is_all_selection = input('is_all_selection','','trim,strip_tags');
if($is_all_selection == 1){
$screen_task_id = input('screen_task_id','','trim,strip_tags');
$screen_scenarios_id = input('screen_scenarios_id','','trim,strip_tags');
$screen_call_start_time = input('screen_call_start_time','','trim,strip_tags');
$screen_call_end_time = input('screen_call_end_time','','trim,strip_tags');
$screen_phone = input('screen_phone','','trim,strip_tags');
$screen_level = input('screen_level','','trim,strip_tags');
$screen_call_status = input('screen_call_status/a','','trim,strip_tags');
$screen_min_duration = input('screen_min_duration','','trim,strip_tags');
$screen_max_duration = input('screen_max_duration','','trim,strip_tags');
$screen_call_times = input('screen_call_times','','trim,strip_tags');
$screen_effective_times = input('screen_effective_times','','trim,strip_tags');
$screen_hit_times = input('screen_hit_times','','trim,strip_tags');
$screen_affirm_times = input('screen_affirm_times','','trim,strip_tags');
$screen_neutral_times = input('screen_neutral_times','','trim,strip_tags');
$screen_negative_times = input('screen_negative_times','','trim,strip_tags');
$screen_invitations = input('screen_invitations','','trim,strip_tags');
$screen_semantic_label = input('screen_semantic_label/a','','trim,strip_tags');
$screen_flow_label = input('screen_flow_label/a','','trim,strip_tags');
$screen_knowledge_label = input('screen_knowledge_label/a','','trim,strip_tags');
$user_auth = session('user_auth');
$where = [];
$where['owner'] = $user_auth['uid'];
if(!empty($screen_task_id)){
$where['task_id'] = $screen_task_id;
}
if(!empty($screen_scenarios_id)){
$where['scenarios_id'] = ['=',$screen_scenarios_id];
}
if(!empty($screen_call_start_time) &&!empty($screen_call_end_time)){
$screen_call_start_time = strtotime($screen_call_start_time);
$screen_call_end_time = strtotime($screen_call_end_time);
$where['last_dial_time'] = [['>=',$screen_call_start_time],['<=',$screen_call_end_time]];
}else{
if(!empty($screen_call_start_time)){
$screen_call_start_time = strtotime($screen_call_start_time);
$where['last_dial_time'] = ['>=',$screen_call_start_time];
}elseif(!empty($screen_call_end_time)){
$screen_call_end_time = strtotime($screen_call_end_time);
$where['last_dial_time'] = ['<=',$screen_call_end_time];
}
}
if(isset($screen_phone) === true &&!empty($screen_phone)){
$where['mobile'] = ['like','%'.$screen_phone.'%'];
}
if(!empty($screen_level)){
$where['level'] = ['in',$screen_level];
}
if(is_array($screen_call_status) &&count($screen_call_status) >0){
$where['status'] = ['in',$screen_call_status];
}
if($screen_min_duration != ''&&$screen_max_duration != ''){
$where['duration'] = [['>=',$screen_min_duration],['<=',$screen_max_duration]];
}else{
if($screen_min_duration != ''){
$where['duration'] = ['>=',$screen_min_duration];
}elseif($screen_max_duration != ''){
$where['duration'] = ['<=',$screen_max_duration];
}
}
if($screen_call_times != ''){
$where['call_times'] = ['>=',$screen_call_times];
}
if($screen_effective_times != ''){
$where['effective_times'] = ['>=',$screen_effective_times];
}
if($screen_hit_times != ''){
$where['hit_times'] = ['>=',$screen_hit_times];
}
if($screen_affirm_times != ''){
$where['affirm_times'] = ['>=',$screen_affirm_times];
}
if($screen_neutral_times != ''){
$where['neutral_times'] = ['>=',$screen_neutral_times];
}
if($screen_negative_times != ''){
$where['negative_times'] = ['<=',$screen_negative_times];
}
if($screen_invitations != ''){
$where['invitation'] = $screen_invitations;
}
$subWhere = [];
if(is_array($screen_flow_label) &&count($screen_flow_label)){
$flow_label_str = ',('.implode('|',$screen_flow_label).'),';
$subWhere[] = "concat(',',flow_label,',') regexp '".$flow_label_str."'";
}
if (is_array($screen_semantic_label) &&count($screen_semantic_label)){
$semantic_label_str = ',('.implode('|',$screen_semantic_label).'),';
$subWhere[] = "concat(',',semantic_label,',') regexp '".$semantic_label_str."'";
}
if (is_array($screen_knowledge_label) &&count($screen_knowledge_label)){
$knowledge_label_str = ',('.implode('|',$screen_knowledge_label).'),';
$subWhere[] = "concat(',',knowledge_label,',') regexp '".$knowledge_label_str."'";
}
$subWhereStr = '';
foreach($subWhere as $key=>$value){
if($key != 0){
$subWhereStr .= ' and ';
}
$subWhereStr .= $value;
}
$phones = Db::name('tel_call_record')
->where($where);
if(!empty($subWhereStr)){
$phones = $phones->where($subWhereStr);
}
$phones = $phones->field('mobile,task_id')->group('mobile')
->select();
}else{
$screen_ids = input('screen_ids/a','','trim,strip_tags');
$args = [];
$args['id'] = ['in',$screen_ids];
$phones = Db::name('tel_call_record')
->group('mobile')
->where($args)
->field('mobile,task_id')
->select();
}
$members = [];
$numbers = [];
foreach($phones as $key=>$value){
$members[$key]['owner'] = $user_auth['uid'];
$members[$key]['mobile'] = $value['mobile'];
$members[$key]['task'] = $task_id;
$members[$key]['status']	=	1;
$members[$key]['reg_time'] = time();
$numbers[$key]['number'] = $value['mobile'];
$l_nickname =  Db::name('member')->where(array('task'=>$value['task_id'],'mobile'=>$value['mobile']  ))->value('nickname');
$members[$key]['nickname'] = $l_nickname?$l_nickname :'';
}
$arrayMemberAll=array_chunk($members,3000);
$arrayNumberAll=array_chunk($numbers,3000);
$redis = RedisConnect::get_redis_connect();
$now_time = strtotime(date("Y-m-d"));
$incr_key_all_count = "incr_owner_".$user_auth['uid'] ."_".$now_time ."_all_count";
$incr_key_per_task_count = "incr_owner_".$user_auth['uid'] ."_".$task_id ."_".$now_time ."_per_task_count";
foreach($arrayMemberAll as $vAll){
$insert_result = Db::name('member')
->insertAll($vAll);
if($vAll) {
$redis->incrby($incr_key_all_count,count($vAll));
$redis->incrby($incr_key_per_task_count,count($vAll));
}
}
$fs_num= Db::name('tel_config')->where(['id'=>$task_id])->value('fs_num');
if(!empty($fs_num)){
foreach($arrayNumberAll as $vAll){
$insert_result = Db::connect('db_configs.fs'.$fs_num)
->table('autodialer_number_'.$task_id)
->insertAll($vAll);
}
}
Db::commit();
return returnAjax(0,'成功');
}
public function create_historical_record_task()
{
$task_name = input('task_name','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$start_date = input('start_date/a','','trim,strip_tags');
$end_date = input('end_date/a','','trim,strip_tags');
$start_time = input('start_time/a','','trim,strip_tags');
$end_time = input('end_time/a','','trim,strip_tags');
$is_auto = input('is_auto','','trim,strip_tags');
$robot_count = input('robot_count','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
$asr_id = input('asr_id/d','','trim,strip_tags');
$is_default_line = input('is_default_line','','trim,strip_tags');
$select_type = input('select_type','','trim,strip_tags');
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
$add_crm_zuoxi = input('crm_push_user_id','','trim,strip_tags');
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
->where('id',$line_id)
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
}else{
$update_line_id = Db::name('admin')
->where('id',$user_auth['uid'])
->update([
'default_line_id'=>0
]);
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
$task_config['add_crm_zuoxi'] = $add_crm_zuoxi;
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
$is_all_selection = input('is_all_selection','','trim,strip_tags');
if($is_all_selection == 1){
$screen_task_id = input('screen_task_id','','trim,strip_tags');
$screen_scenarios_id = input('screen_scenarios_id','','trim,strip_tags');
$screen_call_start_time = input('screen_call_start_time','','trim,strip_tags');
$screen_call_end_time = input('screen_call_end_time','','trim,strip_tags');
$screen_phone = input('screen_phone','','trim,strip_tags');
$screen_level = input('screen_level','','trim,strip_tags');
$screen_call_status = input('screen_call_status/a','','trim,strip_tags');
$screen_min_duration = input('screen_min_duration','','trim,strip_tags');
$screen_max_duration = input('screen_max_duration','','trim,strip_tags');
$screen_call_times = input('screen_call_times','','trim,strip_tags');
$screen_effective_times = input('screen_effective_times','','trim,strip_tags');
$screen_hit_times = input('screen_hit_times','','trim,strip_tags');
$screen_affirm_times = input('screen_affirm_times','','trim,strip_tags');
$screen_neutral_times = input('screen_neutral_times','','trim,strip_tags');
$screen_negative_times = input('screen_negative_times','','trim,strip_tags');
$screen_invitations = input('screen_invitations','','trim,strip_tags');
$screen_semantic_label = input('screen_semantic_label/a','','trim,strip_tags');
$screen_flow_label = input('screen_flow_label/a','','trim,strip_tags');
$screen_knowledge_label = input('screen_knowledge_label/a','','trim,strip_tags');
$user_auth = session('user_auth');
$where = [];
$where['owner'] = $user_auth['uid'];
if(!empty($screen_task_id)){
$where['task_id'] = $screen_task_id;
}
if(!empty($screen_scenarios_id)){
$where['scenarios_id'] = ['=',$screen_scenarios_id];
}
if(!empty($screen_call_start_time) &&!empty($screen_call_end_time)){
$screen_call_start_time = strtotime($screen_call_start_time);
$screen_call_end_time = strtotime($screen_call_end_time);
$where['last_dial_time'] = [['>=',$screen_call_start_time],['<=',$screen_call_end_time]];
}else{
if(!empty($screen_call_start_time)){
$screen_call_start_time = strtotime($screen_call_start_time);
$where['last_dial_time'] = ['>=',$screen_call_start_time];
}elseif(!empty($screen_call_end_time)){
$screen_call_end_time = strtotime($screen_call_end_time);
$where['last_dial_time'] = ['<=',$screen_call_end_time];
}
}
if(isset($screen_phone) === true &&!empty($screen_phone)){
$where['mobile'] = ['like','%'.$screen_phone.'%'];
}
if(!empty($screen_level)){
$where['level'] = ['in',$screen_level];
}
if(is_array($screen_call_status) &&count($screen_call_status) >0){
$where['status'] = ['in',$screen_call_status];
}
if($screen_min_duration != ''&&$screen_max_duration != ''){
$where['duration'] = [['>=',$screen_min_duration],['<=',$screen_max_duration]];
}else{
if($screen_min_duration != ''){
$where['duration'] = ['>=',$screen_min_duration];
}elseif($screen_max_duration != ''){
$where['duration'] = ['<=',$screen_max_duration];
}
}
if($screen_call_times != ''){
$where['call_times'] = ['>=',$screen_call_times];
}
if($screen_effective_times != ''){
$where['effective_times'] = ['>=',$screen_effective_times];
}
if($screen_hit_times != ''){
$where['hit_times'] = ['>=',$screen_hit_times];
}
if($screen_affirm_times != ''){
$where['affirm_times'] = ['>=',$screen_affirm_times];
}
if($screen_neutral_times != ''){
$where['neutral_times'] = ['>=',$screen_neutral_times];
}
if($screen_negative_times != ''){
$where['negative_times'] = ['<=',$screen_negative_times];
}
if($screen_invitations != ''){
$where['invitation'] = $screen_invitations;
}
$subWhere = [];
if(is_array($screen_flow_label) &&count($screen_flow_label)){
$flow_label_str = ',('.implode('|',$screen_flow_label).'),';
$subWhere[] = "concat(',',flow_label,',') regexp '".$flow_label_str."'";
}
if (is_array($screen_semantic_label) &&count($screen_semantic_label)){
$semantic_label_str = ',('.implode('|',$screen_semantic_label).'),';
$subWhere[] = "concat(',',semantic_label,',') regexp '".$semantic_label_str."'";
}
if (is_array($screen_knowledge_label) &&count($screen_knowledge_label)){
$knowledge_label_str = ',('.implode('|',$screen_knowledge_label).'),';
$subWhere[] = "concat(',',knowledge_label,',') regexp '".$knowledge_label_str."'";
}
$subWhereStr = '';
foreach($subWhere as $key=>$value){
if($key != 0){
$subWhereStr .= ' and ';
}
$subWhereStr .= $value;
}
$table_name = get_table_name($select_type);
$phones = Db::name($table_name)
->where($where);
if(!empty($subWhereStr)){
$phones = $phones->where($subWhereStr);
}
$phones = $phones->field('mobile,task_id')->group('mobile')
->select();
}else{
$screen_ids = input('screen_ids/a','','trim,strip_tags');
$args = [];
$args['id'] = ['in',$screen_ids];
$table_name = get_table_name($select_type);
$phones = Db::name($table_name)
->where($args)
->group('mobile')
->field('mobile,task_id')
->select();
}
$members = [];
$numbers = [];
foreach($phones as $key=>$value){
$members[$key]['owner'] = $user_auth['uid'];
$members[$key]['mobile'] = $value['mobile'];
$members[$key]['task'] = $task_id;
$members[$key]['status']	=	1;
$members[$key]['reg_time'] = time();
$numbers[$key]['number'] = $value['mobile'];
$l_nickname =  Db::name('member')->where(array('task'=>$value['task_id'],'mobile'=>$value['mobile']  ))->value('nickname');
$members[$key]['nickname'] = $l_nickname?$l_nickname :'';
}
$arrayMemberAll=array_chunk($members,3000);
$arrayNumberAll=array_chunk($numbers,3000);
$redis = RedisConnect::get_redis_connect();
$now_time = strtotime(date("Y-m-d"));
$incr_key_all_count = "incr_owner_".$user_auth['uid'] ."_".$now_time ."_all_count";
$incr_key_per_task_count = "incr_owner_".$user_auth['uid'] ."_".$task_id ."_".$now_time ."_per_task_count";
foreach($arrayMemberAll as $vAll){
$insert_result = Db::name('member')
->insertAll($vAll);
if($vAll) {
$redis->incrby($incr_key_all_count,count($vAll));
$redis->incrby($incr_key_per_task_count,count($vAll));
}
}
$fs_num = Db::name('tel_config')->where(['id'=>$task_id])->value('fs_num');
if(!empty($fs_num)){
foreach($arrayNumberAll as $vAll){
$insert_result = Db::connect('db_configs.fs'.$fs_num)
->table('autodialer_number_'.$task_id)
->insertAll($vAll);
}
}
return returnAjax(0,'成功');
}
public function assoc_unique($arr,$key) {
$tmp_arr = array();
foreach ($arr as $k =>$v) {
if (in_array($v[$key],$tmp_arr)) {
unset($arr[$k]);
}else {
$tmp_arr[] = $v[$key];
}
}
sort($arr);
return $arr;
}
public function schedule_total(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$task_id = input('task_id','','trim,strip_tags');
$where['task'] = $task_id ;
$where['owner'] = $uid ;
$count = Db::name('member')->where($where)->count();
$where_s['task'] = $task_id;
$where_s['owner'] =  $uid;
$where_s['status'] = array('egt',2);
$schedule = Db::name('member')->where($where_s)->count();
$data = array();
$data['count'] = $count;
$data['status'] = $schedule;
return returnAjax(0,'获取成功',$data);
}
public function get_intention_rule(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$where['scenarios_id'] = $scenarios_id ;
$where['status'] = 1 ;
$data = Db::name('tel_intention_rule_template')->where($where)->find();
if(!empty($data)){
$where=[];
$where['template_id'] = $data['id'] ;
$data = Db::name('tel_intention_rule')->where($where)->select();
}
if($data &&count($data)>0){
foreach ($data as $k=>$v){
if(is_string($v['rule']) ){
$data[$k]['rule']=unserialize( $v['rule'] );
}
}
$returnData=[];
foreach($data as $v){
$returnData[(string)$v['level'] ][]=$v;
}
krsort ($returnData);
}
return isset($returnData)?returnAjax(0,'获取成功',$returnData):returnAjax(1,'获取搜索记录失败');
}
public function get_search_condition(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$where['user_id']=$uid;
$where['search_place']='call_record';
$searchdata = Db::name('tel_search_condition')->where($where)->find();
if(!empty($searchdata)){
$data= unserialize($searchdata['search_condition']);
}
return isset($data)?returnAjax(0,'获取搜索记录成功',$data):returnAjax(1,'获取搜索记录失败') ;
}
public function update_search_condition(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$phoneStateStr=input('post.phoneStateStr','trim strip_tags');
$talkTimeStr=input('post.talkTimeStr','trim strip_tags');
$frequencySpeakingStr=input('post.frequencySpeakingStr','trim strip_tags');
$clientToneStr=input('post.clientToneStr','trim strip_tags');
$reviewThingStr=input('post.reviewThingStr','trim strip_tags');
$inputArr=serialize(array(
'phoneStateStr'=>$phoneStateStr,
'talkTimeStr'=>$talkTimeStr,
'frequencySpeakingStr'=>$frequencySpeakingStr,
'clientToneStr'=>$clientToneStr,
'reviewThingStr'=>$reviewThingStr,
));
$inData=['search_condition'=>$inputArr,'user_id'=>$uid,'search_place'=>'call_record'];
$where['user_id']=$uid;
$where['search_place']='call_record';
$searchdata = Db::name('tel_search_condition')->where($where)->find();
if($searchdata){
$rs= Db::name('tel_search_condition')->where($where)->update($inData);
}else{
$rs= Db::name('tel_search_condition')->where($where)->insert($inData);
}
return ($rs)?returnAjax(0,'更新成功'):returnAjax(1,'更新失败');
}
public function get_call_phone(){
$user_auth=session('user_auth');
$uid=$user_auth['uid'];
$select_type = input('select_type','trim strip_tags');
$id=input('id','trim strip_tags');
$recordType=input('recordType','trim strip_tags');
if($recordType=='history'){
$tableName = get_table_name($select_type);
}else{
$tableName='tel_call_record';
}
$whereArr=['id'=>$id];
$info=Db::name($tableName)->where($whereArr)->find();
if(!empty($info)){
return  returnAjax(0,'获取成功',$info);
}
return returnAjax(1,'数据为空');
}
}
