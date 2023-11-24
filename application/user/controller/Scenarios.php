<?php
namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\api\controller\Users;
use app\common\controller\User;
use think\Db;
use think\Session;
use Overtrue\Pinyin\Pinyin;
use Qiniu\json_decode;
use think\Config;
use think\Cookie;
use think\request;
use PHPExcel_IOFactory;
use PHPExcel;
use ZipArchive;
use app\common\controller\AdminData;
use app\common\controller\Log;
use app\common\controller\RedisConnect;
use app\common\controller\RedisApiData;
use app\common\controller\Audio;
require_once './vendor/aliyuncs/aliyun-openapi-php-sdk/aliyun-php-sdk-core/Config.php';
require_once ROOT_PATH.'/vendor/aliyuncs/aliyun-openapi-php-sdk/aliyun-php-sdk-nls-cloud-meta/nls_cloud_meta/Request/V20180518/CreateTokenRequest.php';
use nls_cloud_meta\Request\V20180518\CreateTokenRequest;
class scenarios extends User{
public function _initialize(){
parent::_initialize();
$request = request();
$action = $request->action();
}
public function get_learning(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$scenarios_id = input('post.scenarios_id','','trim,strip_tags');
$limit = input('post.limit','','trim,strip_tags');
$page = input('page','1','trim,strip_tags');
$type = input('type','','trim,strip_tags');
if(empty($scenarios_id)){
return returnAjax(1,'话术id不能为空');
}
if(empty($limit)){
$Page_size = 10;
}else{
$Page_size = $limit;
}
$where = [];
$where['scenarios_id']= array('eq',$scenarios_id);
$where['owner']= array('eq',$uid);
if($type != ''){
$where['status']= array('eq',$type);
}
$config_table_days = config('call_table_days');
if(!isset($config_table_days) ||!is_numeric($config_table_days)){
$config_table_days = 5;
}
$tel_learnings = Db::name('tel_learning')->where($where)->page($page,$Page_size)->select();
foreach($tel_learnings as $k=>$tel_learning){
$tel_call_record = Db::name('tel_call_record')->where(['mobile'=>$tel_learning['phone'],'call_id'=>$tel_learning['call_id']])->find();
if(!empty($tel_call_record)){
$tel_learnings[$k]['type'] = "now";
$tel_learnings[$k]['record_id']=$tel_call_record['id'];
$tel_learnings[$k]['task_id']=$tel_call_record['task_id'];
}else{
$tel_learnings[$k]['type']="historical";
$table_name = get_table_name_by_time($tel_learning['create_time']);
$tel_call_record_history= Db::name($table_name)->where(['mobile'=>$tel_learning['phone'],'call_id'=>$tel_learning['call_id']])->find();
$tel_learnings[$k]['record_id']=$tel_call_record_history['id'];
$tel_learnings[$k]['task_id']=$tel_call_record_history['task_id'];
}
}
$total= Db::name('tel_learning')->where($where)->count('*');
$data = [];
$data['list']=$tel_learnings;
$data['total']=$total;
$data['Nowpage']=$page;
$data['limit']=$Page_size;
return returnAjax(0,'学习数据显示成功',$data);
}
public function learning_handle(){
$id = input('post.id','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'id不能为空');
}
$tel_learning =	Db::name('tel_learning')->where(['id'=>$id])->find();
return returnAjax(0,'获取处理资料OK',$tel_learning);
}
public function set_status_learning(){
$id = input('post.id','','trim,strip_tags');
$status = input('post.status','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'id不能为空');
}
$res = Db::name('tel_learning')->where(['id'=>$id])->update(['status'=>$status]);
if(!empty($res)){
return returnAjax(0,'状态设置成功');
}else{
return returnAjax(1,'状态设置失败');
}
}
public function delete_learning(){
$id = input('post.id','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'id不能为空');
}
$res = Db::name('tel_learning')->where(['id'=>$id])->delete();
if(!empty($res)){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
public function scenariosView(){
$scenarios_id = input('post.scenarios_id','','trim,strip_tags');
if(empty($scenarios_id)){
return returnAjax(1,'话术id不能为空');
}
$scenarios_config = Db::name('tel_scenarios_config')->where(['scenarios_id'=>$scenarios_id])->find();
if(!empty($scenarios_config)){
return returnAjax(0,'OK',$scenarios_config);
}else{
return returnAjax(2,'数据为空！');
}
}
public function scenariosUpdate(){
$scenarios_id = input('post.scenarios_id','','trim,strip_tags');
$pause_play_ms = input('post.pause_play_ms','','trim,strip_tags');
$min_speak_ms = input('post.min_speak_ms','','trim,strip_tags');
$max_speak_ms = input('post.max_speak_ms','','trim,strip_tags');
$filter_level = input('post.filter_level','','trim,strip_tags');
$volume = input('post.volume','','trim,strip_tags');
if(empty($scenarios_id)){
return returnAjax(1,'场景id不能为空');
}
if(!empty($volume)){
if($volume>100 ||$volume<0){
return returnAjax(1,'机器人音量范围为0-100');
}
}
if(!empty($filter_level)){
if($filter_level>1 ||$filter_level<0){
return returnAjax(1,'防干扰等级范围为0-1');
}
}
$key = 'scenarios_'.$scenarios_id.'_data';
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
$data=[
'pause_play_ms'=>$pause_play_ms,
'min_speak_ms'=>$min_speak_ms,
'max_speak_ms'=>$max_speak_ms,
'filter_level'=>$filter_level,
'volume'=>$volume,
];
$count = Db::name('tel_scenarios_config')->where(['scenarios_id'=>$scenarios_id])->count('*');
if($count >0){
$res = Db::name('tel_scenarios_config')->where(['scenarios_id'=>$scenarios_id])->update($data);
if(!empty($res)){
return returnAjax(0,'修改配置成功');
}else{
return returnAjax(1,'修改配置失败');
}
}else{
$data['scenarios_id']=$scenarios_id;
$rea = Db::name('tel_scenarios_config')->insertGetId($data);
if(!empty($rea)){
return returnAjax(0,'修改配置成功');
}else{
return returnAjax(1,'修改配置失败');
}
}
}
public function tel_flow_branch_order($flow_branchs)
{
if(empty($flow_branchs) ||is_array($flow_branchs) == false ||count($flow_branchs) == 0){
return [];
}
$new_flow_branch = [];
$orders = [
0,3,4,2,5,6
];
foreach($orders as $key=>$value){
foreach($flow_branchs as $find_key=>$find_value){
if($find_value['type'] == $value){
$new_flow_branch[] = $find_value;
}
}
}
return $new_flow_branch;
}
public function index()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$where['is_tpl'] = 0;
if(!$super){
$where['member_id'] = $uid;
}
$list = Db::name('tel_scenarios')
->field('s.*,a.username')
->alias('s')
->join('admin a','a.id = s.member_id')
->where($where)->order('id desc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
$checklist = Db::name('tel_scenarios')->where('is_tpl',1)->order('id desc')->select();
$this->assign('checklist',$checklist);
$this->assign('list',$list['data']);
$this->assign('page',$page);
$adminlist = Db::name('admin')->field('examine')->where('id',$uid)->find();
$this->assign('examine',$adminlist['examine']);
$this->assign('super',$super);
return $this->fetch();
}
public function addScenarios(){
if(IS_POST){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$memberInfo =  Db::name('admin')->field('examine')->where('id',$uid)->find();
$data = array();
$data['name'] = htmlspecialchars_decode(input('name','','trim,strip_tags'));
$data['type'] = input('tradeType','','trim,strip_tags');
$data['member_id'] = $uid;
$isTpl = input('is_tpl','','trim,strip_tags');
$data['is_tpl'] = 0;
$data['status'] = 1;
$data['auditing'] = 0;
if ($memberInfo['examine']){
$data['auditing'] = 1;
}
$data['is_variable'] = input('is_variable','','trim,strip_tags');
$data['break'] = input('break','','trim,strip_tags');
$data['update_time'] = time();
$new_scenarions_id = Db::name('tel_scenarios')->insertGetId($data);
$this->insert_intention_model($new_scenarions_id);
$this->set_default_scenarios_config($new_scenarions_id);
$delete_result = Db::name('tel_knowledge')->where('scenarios_id',$new_scenarions_id)->delete();
$res = Db::name('tel_knowledge')->field("name,type,keyword,keyword_py,action,is_default")->where('scenarios_id',0)->select();
foreach ($res as &$v){
$v['scenarios_id'] = $new_scenarions_id;
$v['create_time'] = time();
$v['update_time'] = time();
}
$result = Db::name('tel_knowledge')->insertAll($res);
return returnAjax(0,'success!');
}
else{
$this->assign('current',"添加");
return $this->fetch();
}
}
public function addtirule($result){
$tidata = array();
$tidata['scenarios_id'] = $result;
$tidata['name'] = '以上规则均不满足时，将客户意向标签设置为';
$tidata['level'] = 4;
$tidata['type'] = 1;
$tidata['sort'] = 0;
$tidata['status'] = 0;
$tidata['create_time'] = time();
$tidata['update_time'] = time();
$tiresult = Db::name('tel_intention_rule')->insertGetId($tidata);
}
public function getmessage(){
$id = input('id','','trim,strip_tags');
$slist =  Db::name('tel_scenarios')->where('id',$id)->find();
echo json_encode($slist,true);
}
public function editscenarios(){
$data = array();
$data['name'] = htmlspecialchars_decode(input('name','','trim,strip_tags'));
$data['type'] = input('tradeType','','trim,strip_tags');
$data['update_time'] = time();
$data['break'] = input('break','','trim,strip_tags');
$id = input('scenariosId','','trim,strip_tags');
$data['is_variable'] = input('is_variable','','trim,strip_tags');
$key = 'scenarios_'.$id.'_data';
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
$result = Db::name('tel_scenarios')->where('id',$id)->update($data);
if($result){
return returnAjax(0,'success!');
}
else{
return returnAjax(1,'error!');
}
}
public function delScenarios(){
$scenariosId= input('id','','trim,strip_tags');
Db::startTrans();
try {
Db::name('tel_scenarios')->where('id',$scenariosId)->delete();
Db::name('tel_scenarios_node')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_flow_node')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_corpus')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_knowledge')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_intention_rule')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_learning')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_intention_rule_template')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_scenarios_config')->where('scenarios_id',$scenariosId)->delete();
$key = 'scenarios_'.$scenariosId.'_data';
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
Db::commit();
return returnAjax(0,'删除成功');
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'删除失败');
}
}
public function setstatus(){
$sId = input('sId','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$list = Db::name('tel_scenarios')->where('id',$sId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function flow(){
$id = input('id','','trim,strip_tags');
$type = input('type','0','trim,strip_tags');
$where = array();
$where['scenarios_id'] = $id;
$where['type'] = $type;
$list = Db::name('tel_flow')->where($where)->order('id asc')
->paginate(20,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$list['data'][$k]["path"] = config('res_url').$v["path"];
}
$this->assign('thisId',$id);
$this->assign('list',$list['data']);
$this->assign('page',$page);
$result = Db::name('tel_keyword')->where('scenarios_id',$id)->order('id asc')->select();
$this->assign('kwlist',$result);
return $this->fetch();
}
public function addflow(){
$tmp_file = $_FILES ['update-audio-file'] ['tmp_name'];
$data = array();
if($tmp_file){
$file_types = explode ( ".",$_FILES ['update-audio-file'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
if (strtolower ( $file_type ) != "wav")
{
$this->error ( '不是wav文件，只能上传wav文件，重新上传');
}
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr.'/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".strtolower($file_type);
if (!copy ( $tmp_file,$savePath .$file_name ))
{
$this->error ( '上传失败');
}
$path = $savePath .$file_name;
$path = ltrim($path,".");
$data['path'] = $path;
}
$flowItemId = input('flowItemId','','trim,strip_tags');
$data['content'] = input('words-content','','trim,strip_tags');
$data['scenarios_id'] = input('scenariosId','','trim,strip_tags');
$data['priority'] = input('priority','0','trim,strip_tags');
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$data['keyword'] = input('hit-keys','','trim,strip_tags');
if ($data['keyword']){
$py = $pinyin->sentence($data['keyword']);
$data['keyword_py'] = $py;
}
$data['bridge'] = input('bridge','0','trim,strip_tags');
$data['type'] = (int)input('flowType','','trim,strip_tags');
$data['break']= input('break','','trim,strip_tags');
$data['classify']= input('classify','','trim,strip_tags');
if($flowItemId){
$result = Db::name('tel_flow')->where('id',$flowItemId)->update($data);
}else{
$result = Db::name('tel_flow')->insertGetId($data);
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$memberInfo =  Db::name('admin')->field('examine')->where('id',$uid)->find();
if ($memberInfo['examine']){
$ret = Db::name('tel_scenarios')->where('id',$data['scenarios_id'])->update(array('auditing'=>1));
}
$this->redirect(Url("User/Scenarios/flow",['id'=>$data['scenarios_id']]));
}
public function getflow(){
$id = input('id','','trim,strip_tags');
$slist =  Db::name('tel_flow')->where('id',$id)->find();
$slist["path"] = config('res_url').$slist["path"];
echo json_encode($slist,true);
}
public function delFlow(){
$ids= input('id/a','','trim,strip_tags');
$scenariosId = input('scenariosId','','trim,strip_tags');
$ret = Db::name('tel_flow')->where('id','in',$ids)->delete();
if(!$ret){
echo "删除失败。";
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$memberInfo =  Db::name('admin')->field('examine')->where('id',$uid)->find();
if ($memberInfo['examine']){
$ret = Db::name('tel_scenarios')->where('id',$scenariosId)->update(array('auditing'=>1));
}
}
public function getSelectflow(){
$scenariosId = input('scenariosId','','trim,strip_tags');
$type = (int)input('type','0','trim,strip_tags');
$classify = (int)input('classify','','trim,strip_tags');
$where = array();
$where['scenarios_id'] = $scenariosId;
if ($classify){
$where['classify'] = $classify;
}
$where['type'] = $type;
$list = Db::name('tel_flow')->where($where)->select();
$back = array();
$back['list'] = $list;
return returnAjax(0,'获取数据成功',$back);
}
public function addKeysWord(){
$data = array();
$kwItemId = input('kwItemId','','trim,strip_tags');
$data['priority'] = input('priority','','trim,strip_tags');
$data['scenarios_id'] = input('skwId','','trim,strip_tags');
$data['keyword'] = input('keyword','','trim,strip_tags');
$data['type'] = input('keystype','','trim,strip_tags');
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
if ($data['keyword']){
$py = $pinyin->sentence($data['keyword']);
$data['pinyin'] = $py;
}
if($kwItemId){
$result = Db::name('tel_keyword')->where('id',$kwItemId)->update($data);
}else{
$result = Db::name('tel_keyword')->insertGetId($data);
}
$this->redirect(Url("User/Scenarios/flow",['id'=>$data['scenarios_id']]));
}
public function getKeywordItem(){
$id = input('id','','trim,strip_tags');
$slist =  Db::name('tel_keyword')->where('id',$id)->find();
echo json_encode($slist,true);
}
public function delKeyword(){
$ids= input('id','','trim,strip_tags');
$klist = Db::name('tel_keyword')->where('id',$ids)->delete();
if(!$klist){
echo "删除失败。";
}
}
public function learning(){
$Page_size = 10;
$page = input('page','1','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$mobile = input('keyword','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
if($status != ""){
$where["status"] = $status;
}
if($sceneId != ""){
$where["scenarios_id"] = $sceneId;
}
if($type != ""){
$where["status"] = $type;
}
if(!$super){
$where['owner'] = $uid;
}
$list = Db::name('tel_learning')
->order('id desc')
->where($where)
->page($page,$Page_size)
->select();
foreach($list as &$item){
if ($item['create_time'] >0){
$item['create_time'] = date('Y-m-d H:i:s',$item['create_time']);
}else{
$item['create_time'] = "";
}
}
$count =  Db::name('tel_learning')->where($where)->count('id');
$page_count = ceil($count/$Page_size);
$back = array();
$back['total'] = $count;
$back['Nowpage'] = $page;
$back['list'] = $list;
$back['page'] = $page_count;
return returnAjax(0,'获取数据成功',$back);
}
public function setAuditing(){
$scenariosId = input('id','','trim,strip_tags');
$result = Db::name('tel_scenarios')->where('id',$scenariosId)->update(array('auditing'=>2));
if ($result >= 0){
return returnAjax(0,'success');
}
else{
return returnAjax(1,'提交失败');
}
}
public function auditing(){
$change = array();
$scenariosId = input('scenariosId','','trim,strip_tags');
$change['remark'] = input('remarks','','trim,strip_tags');
$change['auditing'] = input('status','','trim,strip_tags');
$result = Db::name('tel_scenarios')->where('id',$scenariosId)->update($change);
}
public function backdetail(){
$uid = input('id','','trim,strip_tags');
$mobile = input('mobile','','trim,strip_tags');
if ($uid){
$where['uid'] = $uid;
}
else{
$where['mobile'] = $mobile;
}
$memberInfo = Db::name('member')
->field('mobile,nickname,status,level,sex,duration,last_dial_time,record_path,call_id,call_rotation,originating_call')
->where('uid',$uid)->find();
$num = Db::name('tel_bills')->where('status',1)->where('call_id',$uid)->count("id");
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
$data = array();
$data['memberInfo'] = $memberInfo;
$data['bills'] = $bills;
$data['num'] = $num;
return returnAjax(0,'获取成功',$data);
}
public function changeLevel(){
$level = input('level');
$uid = input('id');
$res = Db::name('member')->where('uid',$uid)->update(array('level'=>$level));
if ($res >= 0){
return returnAjax(0,'修改成功');
}
else{
return returnAjax(1,'修改失败');
}
}
public function delLearning(){
$ids= input('id/a','','trim,strip_tags');
$flist = Db::name('tel_learning')->where('id','in',$ids)->delete();
if(!$flist){
echo "删除失败。";
}
}
public function changeStatus(){
$sId = input('Ids/a','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$list = Db::name('tel_learning')->where('id','in',$sId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function importExcel(){
$tmp_file = $_FILES ['excel'] ['tmp_name'];
$file_types = explode ( ".",$_FILES ['excel'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
$savePath = './uploads/Excel/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy ( $tmp_file,$savePath .$file_name ))
{
$this->error ( '上传失败');
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
$num = $objPHPExcel->getSheetCount();
$scenariosId = input('scenariosId','','trim,strip_tags');
$SheetNames = $objPHPExcel->getSheetNames();
$classify = 0;
for ($i=0;$i <$num ;$i++) {
\think\Log::record('$SheetNames[$i]='.$SheetNames[$i]);
switch ($SheetNames[$i]) {
case '用户提问流程':
$classify = 2;
break;
case '用户挽回流程':
$classify = 1;
break;
case '用户拒绝流程':
$classify = 5;
break;
case '回答不上来流程':
$classify = 8;
break;
case '用户说忙流程':
$classify = 4;
break;
case '用户未说话':
$classify = 7;
break;
case '主动结束流程':
$classify = 6;
break;
default:
$classify = 0;
break;
}
$excelArr = $objPHPExcel->getsheet($i)->toArray();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if (!$scenariosId){
$this->redirect(url("User/Scenarios/flow",array('id'=>$scenariosId)),'场景错误，请选择场景。');
return;
}
$data = array();
foreach ( $excelArr as $k =>$v ){
if ($k == 0){
continue;
}
$user['scenarios_id'] = $scenariosId;
$user['content'] = trim($v[0]);
$user['path'] = $v[2];
if($i == 0){
$user['type'] = 0;
if($v[1] == '否'){
$user['break'] = 0;
}else{
$user['break'] = 1;
}
}else{
$user['type'] = 1;
$user['classify'] = $classify;
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$user['keyword'] = $v[1];
if ($user['keyword']){
$py = $pinyin->sentence($user['keyword']);
$user['keyword_py'] = $py;
}
}
array_push($data,$user);
}
$result = Db::name('tel_flow')->insertAll($data);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$memberInfo =  Db::name('admin')->field('examine')->where('id',$uid)->find();
if ($memberInfo['examine']){
$ret = Db::name('tel_scenarios')->where('id',$scenariosId)->update(array('auditing'=>1));
}
}
$this->redirect(url("User/Scenarios/flow",array('id'=>$scenariosId)),'导入成功');
}
public function get_scenarios_by_type(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$type=input('type','','trim,strip_tags');
$where = array();
$where['is_tpl'] = 0;
if(empty($type)){
$where['is_variable'] = 0;
}else{
$where['is_variable'] = 1;
}
if(!$super){
$where['member_id'] = $uid;
}
$list = Db::name('tel_scenarios')
->field('s.*,a.username')
->alias('s')
->join('admin a','a.id = s.member_id')
->where($where)
->order('id desc')
->select();
$where = [];
if(empty($type)){
foreach ($list as $key=>$val){
if($val['check_statu']==1){
$list[$key]['notempty'] = 1;
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
}else{
$tel_flow_nodes = Db::name('tel_flow_node')->alias('tfn')->field('tfn.id as id,tsn.id as tsnid')->join('tel_scenarios_node tsn','tsn.id = tfn.scen_node_id','inner')->where(['tfn.type'=>0,'tfn.scenarios_id'=>$val['id']])->select();
if(!empty($tel_flow_nodes) &&count($tel_flow_nodes)!=0){
foreach($tel_flow_nodes as $key_1 =>$tel_flow_node){
$where['tfn.type'] = array('eq',0);
$where['ts.id'] = array('eq',$val['id']);
$where['tc.audio'] = array(array('neq',''),array('exp','is not null'),'or');
$where['tc.src_type'] = [['neq',''],['exp','is not null'],'or'];
$where['tc.scenarios_id']=array('eq',$val['id']);
$where['tfn.id']=$tel_flow_node['id'];
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
$res = Db::name('tel_flow_node')
->alias('tfn')
->join('tel_corpus tc','tc.src_id = tfn.id','inner')
->join('tel_scenarios_node tsn','tsn.id = tfn.scen_node_id','inner')
->join('tel_scenarios ts','ts.id = tfn.scenarios_id','inner')
->where($where)
->count('tfn.id');
if($res >0){
$list[$key]['notempty'] = 0;
}else{
$list[$key]['notempty'] = 1;
break;
}
}
}else{
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
$list[$key]['notempty'] = 0;
}
}
}
}elseif($type==1){
foreach ($list as $key=>$val){
if($val['check_statu']==1){
$list[$key]['notempty'] = 1;
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
}else{
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
$list[$key]['notempty'] = 0;
}
}
}
$num = count($list);
$data['num']=$num;
$data['list']=$list;
$adminlist = Db::name('admin')->field('examine')->where('id',$uid)->find();
$data['examine']=$adminlist['examine'];
$data['super']=$super;
return returnAjax(0,'成功',$data);
}
public function scene()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$admin_info = Db::name('admin')->where('id',$uid)->find();
$this->assign('is_verification',$admin_info['is_verification']);
$this->assign('role_id',$admin_info['role_id']);
$this->assign('is_backup',$admin_info['is_backup']);
if($admin_info['is_scenarios'] == 1 ){
if(!getenv("HTTP_REFERER")){
header('location:/user/index/index');
}else{
header('location:'.getenv("HTTP_REFERER"));
}
}
$where = array();
$where['is_tpl'] = 0;
$where['is_variable'] = 0;
if(!$super){
$where['member_id'] = $uid;
}
$list = Db::name('tel_scenarios')
->field('s.*,a.username')
->alias('s')
->join('admin a','a.id = s.member_id')
->where($where)
->order('id desc')
->select();
$list_var = Db::name('tel_scenarios')
->field('s.*,a.username')
->alias('s')
->join('admin a','a.id = s.member_id')
->where(['is_variable'=>1,'is_tpl'=>0,'member_id'=>$uid])
->order('id desc')
->select();
$this->assign('scenarioslist_var',$list_var);
$where = [];
foreach ($list as $key=>$val){
if($val['check_statu']==1){
$list[$key]['notempty'] = 1;
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
}else{
$tel_flow_nodes = Db::name('tel_flow_node')->alias('tfn')->field('tfn.id as id,tsn.id as tsnid')->join('tel_scenarios_node tsn','tsn.id = tfn.scen_node_id','inner')->where(['tfn.type'=>0,'tfn.scenarios_id'=>$val['id']])->select();
if(!empty($tel_flow_nodes) &&count($tel_flow_nodes)!=0){
foreach($tel_flow_nodes as $key_1 =>$tel_flow_node){
$where['tfn.type'] = array('eq',0);
$where['ts.id'] = array('eq',$val['id']);
$where['tc.audio'] = array(array('neq',''),array('exp','is not null'),'or');
$where['tc.src_type'] = [['neq',''],['exp','is not null'],'or'];
$where['tc.scenarios_id']=array('eq',$val['id']);
$where['tfn.id']=$tel_flow_node['id'];
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
$res = Db::name('tel_flow_node')
->alias('tfn')
->join('tel_corpus tc','tc.src_id = tfn.id','inner')
->join('tel_scenarios_node tsn','tsn.id = tfn.scen_node_id','inner')
->join('tel_scenarios ts','ts.id = tfn.scenarios_id','inner')
->where($where)
->count('tfn.id');
if($res >0){
$list[$key]['notempty'] = 0;
}else{
$list[$key]['notempty'] = 1;
break;
}
}
}else{
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
$list[$key]['notempty'] = 0;
}
}
}
$AdminData = new AdminData();
$user_list = $AdminData->get_find_users($uid);
array_unshift($user_list,['id'=>$uid,'username'=>'自己']);
$this->assign('user_list',$user_list);
$this->assign('scenarioslist',$list);
$this->assign('num',count($list));
$adminlist = Db::name('admin')->field('examine')->where('id',$uid)->find();
$this->assign('examine',$adminlist['examine']);
$this->assign('super',$super);
\think\Config::parse(APP_PATH.'intention.json','json');
$intention = \think\Config::get('intention_rule');
$this->assign('intention',$intention);
$checklist = Db::name('tel_scenarios')->where('is_tpl',1)->order('id desc')->select();
$this->assign('checklist',$checklist);
$grgs = array();
if(!$super){
$grgs['owner'] = $uid;
}
$grouplist = Db::name('tsr_group')->field('id,name')->where($grgs)->order('id desc')->select();
$this->assign('grouplist',$grouplist);
$seats = Db::name('admin')
->alias('a')
->join('admin_role ar','ar.id = a.role_id','LEFT')
->field('a.id,a.username')
->where([
'ar.name'=>'坐席',
'pid'=>$uid
])
->select();
$this->assign('seats',$seats);
return $this->fetch('scene_banben2');
}
public function getLabelList(){
$Page_size = 10;
$page = input('page','1','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$mobile = input('keyword','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$type = input('type','','trim,strip_tags');
if($status != ""){
$where["status"] = $status;
}
if($sceneId != ""){
$where["scenarios_id"] = $sceneId;
}
if($type != ""){
$where["status"] = $type;
}
$where["type"] = 0;
$list = Db::name('tel_intention_rule')
->order('level desc')
->where($where)
->page($page,$Page_size)
->select();
foreach($list as &$item){
switch ($item['level']) {
case 6:
$item['level'] = 'A类(有明确意向)';
break;
case 5:
$item['level'] = 'B类(可能有意向)';
break;
case 4:
$item['level'] = 'C类(明确拒绝)';
break;
case 3:
$item['level'] = 'D类(用户忙)';
break;
case 2:
$item['level'] = 'E类(拨打失败)';
break;
default:
$item['level'] = 'F类(无效客户)';
}
if ($item['create_time'] >0){
$item['create_time'] = date('Y-m-d H:i:s',$item['create_time']);
}
else{
$item['create_time'] = "";
}
}
$cwhere = array();
if($status != ""){
$cwhere["status"] = $status;
}
if($sceneId != ""){
$cwhere["scenarios_id"] = $sceneId;
}
if($type != ""){
$cwhere["status"] = $type;
}
$count =  Db::name('tel_intention_rule')->where($cwhere)->count('id');
$page_count = ceil($count/$Page_size);
\think\Log::record('意向等级');
$where = array();
$where["type"] = 1;
$where["scenarios_id"] = $sceneId;
$defualt = Db::name('tel_intention_rule')
->field('id,level')
->where($where)
->find();
if(isset($defualt['level'])){
$defualt["levelNum"] = $defualt["level"];
switch ($defualt['level']) {
case 6:
$defualt['level'] = 'A类(有明确意向)';
break;
case 5:
$defualt['level'] = 'B类(可能有意向)';
break;
case 4:
$defualt['level'] = 'C类(明确拒绝)';
break;
case 3:
$defualt['level'] = 'D类(用户忙)';
break;
case 2:
$defualt['level'] = 'E类(拨打失败)';
break;
default:
$defualt['level'] = 'F类(无效客户)';
}
}else{
$defualt = array();
}
$back = array();
$back['total'] = $count;
$back['Nowpage'] = $page;
$back['list'] = $list;
$back['default'] = $defualt;
$back['page'] = $page_count;
return returnAjax(0,'获取数据成功',$back);
}
public function backdefault(){
$sceneId = input('sceneId','','trim,strip_tags');
$where = array();
$where["type"] = 1;
$where["scenarios_id"] = $sceneId;
$list = Db::name('tel_intention_rule')
->field('id,level')
->where($where)->find();
if(isset($list['level'])){
$list["levelNum"] = $list["level"];
switch ($list['level']) {
case 6:
$list['level'] = 'A类(有明确意向)';
break;
case 5:
$list['level'] = 'B类(可能有意向)';
break;
case 4:
$list['level'] = 'C类(明确拒绝)';
break;
case 3:
$list['level'] = 'D类(用户忙)';
break;
case 2:
$list['level'] = 'E类(拨打失败)';
break;
default:
$list['level'] = 'F类(无效客户)';
}
}else{
$list = array();
}
return returnAjax(0,'成功',$list);
}
public function updateDftype(){
$sceneId = input('sceneId','','trim,strip_tags');
$level = input('level','','trim,strip_tags');
$data = array();
$data['level'] = $level;
$result = Db::name('tel_intention_rule')->where('scenarios_id',$sceneId)->where('type',1)->update($data);
if($result){
return returnAjax(0,'编辑成功了',$result);
}
else{
return returnAjax(1,'编辑失败',$result);
}
}
public function addIntention(){
$data = array();
$ruler = input('ruler/a','','trim');
$data['scenarios_id'] = input('scenarios_id','','trim,strip_tags');
$data['level'] = input('classify','','trim,strip_tags');
$data['type'] = 0;
$data['status'] = 0;
$data['update_time'] = time();
$name = input('name/a','','trim,strip_tags');
$Joan = array();
$rulername = "";
foreach ($ruler as $key =>$value) {
$temp = array();
$temp['key'] = $value['one'];
$temp['type'] = $value['four'];
$temp['op'] = trim($value['two']);
if($value['one'] == 'say_keyword'){
$temp['value'] = explode(",",$value['three']);
}else{
$temp['value'] = $value['three'];
}
$temp['value'] = $value['three'];
array_push($Joan,$temp);
if($key == 0){
\think\Log::record('nametwotxt='.json_encode($name[$key]));
if($name[$key]["twotxt"] == '<='){
\think\Log::record('twotxt dengyu<=');
}else if($name[$key]["twotxt"] == ""){
$name[$key]["twotxt"] = '<=';
}
$rulername = $name[$key]["onetxt"]." ".$name[$key]["twotxt"]." ".$name[$key]["threetxt"];
}else{
if($name[$key]["twotxt"] == '<='){
\think\Log::record('twotxt dengyu<=');
}else if($name[$key]["twotxt"] == ""){
$name[$key]["twotxt"] = '<=';
}
$rulername = $rulername." 并且 ".$name[$key]["onetxt"]." ".$name[$key]["twotxt"]." ".$name[$key]["threetxt"];
}
}
$data['name'] = $rulername;
$data['rule'] = serialize($Joan);
$sceneId = input('sceneId','','trim,strip_tags');
if($sceneId){
$result = Db::name('tel_intention_rule')->where('id',$sceneId)->update($data);
}else{
$data['create_time'] = time();
$result = Db::name('tel_intention_rule')->insertGetId($data);
}
if($result){
return returnAjax(0,'新建成功了',$result);
}else{
return returnAjax(1,'新建失败',$result);
}
}
public function delLabel(){
$ids= input('id/a','','trim,strip_tags');
$flist = Db::name('tel_intention_rule')->where('id','in',$ids)->delete();
if(!$flist){
echo "删除失败。";
}
}
public function getscene(){
$id = input('id','','trim,strip_tags');
$slist =  Db::name('tel_intention_rule')->where('id',$id)->find();
$slist['rule'] = unserialize($slist['rule']);
foreach ($slist['rule'] as $key =>$value) {
if($value['key'] == 'say_keyword'){
if (is_array( $value['value'])){
$slist['rule'][$key]['value'] = implode(",",$value['value']);
}
}
}
return returnAjax(0,'成功',$slist);
}
public function recovery($sceneId = ""){
if($sceneId == ""){
$sceneId = input('sceneId','','trim,strip_tags');
}
if(!empty($sceneId)){
$flist = Db::name('tel_intention_rule')->where('scenarios_id',$sceneId)->delete();
}
$where = array();
$where['scenarios_id'] = 0;
$res = Db::name('tel_intention_rule')->field("name,level,rule,type")->where($where)->select();
$data = array();
foreach ($res as &$v){
$v['scenarios_id'] = $sceneId;
$v['create_time'] = time();
$v['update_time'] = time();
}
$result = Db::name('tel_intention_rule')->insertAll($res);
if($result){
return returnAjax(0,'新建成功了',$result);
}else{
return returnAjax(1,'新建失败',$result);
}
}
public function addflowNote(){
$data = array();
$data['scenarios_id'] = input('sceneId','','trim,strip_tags');
$data['name'] = input('name','','trim,strip_tags');
$data['type'] = input('type','0','trim,strip_tags');
$data['sort'] = 0;
$flowId = input('flowId','','trim,strip_tags');
if($flowId){
$key = 'scenarios_{screnarios_id}_data';
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
$result = Db::name('tel_scenarios_node')->where('id',$flowId)->update($data);
if($result){
return returnAjax(0,'编辑成功了',$result);
}elseif($result==0){
return returnAjax(1,'没有改变内容',$result);
}
}else{
$result = Db::name('tel_scenarios_node')->insertGetId($data);
if($result){
return returnAjax(0,'新建成功了',$result);
}else{
return returnAjax(1,'新建失败',$result);
}
}
}
public function getNoteList(){
$sceneId = input('sceneId','','trim,strip_tags');
$is_variable = Db::name('tel_scenarios')->where('id',$sceneId)->value('is_variable');
$result = Db::name('tel_scenarios_node')->where('scenarios_id',$sceneId)->select();
$where = [];
if(empty($is_variable)){
foreach($result as $key=>$vo){
$tel_flow_nodes = Db::name('tel_flow_node')->where(['type'=>0,'scen_node_id'=>$vo['id']])->select();
if(!empty($tel_flow_nodes)){
foreach($tel_flow_nodes as $key_1 =>$tel_flow_node){
$where['tfn.scen_node_id'] = array('eq',$vo['id']);
$where['tc.audio'] = array(array('neq',''),array('exp','is not null'),'or');
$where['tc.src_type'] = [['neq',''],['exp','is not null'],'or'];
$where['tc.scenarios_id']=array('eq',$sceneId);
$where['tfn.type'] = 0;
$where['tfn.id']=$tel_flow_node['id'];
$res = Db::name('tel_flow_node')
->alias('tfn')
->join('tel_corpus tc','tc.src_id = tfn.id','LEFT')
->where($where)
->count('tfn.id');
if($res >0){
$result[$key]['notempty'] = 0;
}else{
$result[$key]['notempty'] = 1;
break;
}
}
}else{
$result[$key]['notempty'] = 0;
}
}
}elseif($is_variable==1){
foreach($result as $key=>$vo){
$result[$key]['notempty'] = 0;
}
}
if($result){
return returnAjax(0,'有数据了-------',$result);
}else{
return returnAjax(1,'暂时没有数据',$result);
}
}
public function getflowinfo($value='')
{
$noteId = input('id','','trim,strip_tags');
$result = Db::name('tel_scenarios_node')->where('id',$noteId)->find();
if($result){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'暂时没有数据',$result);
}
}
public function delflowNote(){
$ids= input('id','','trim,strip_tags');
$scenarios_id = Db::name('tel_scenarios_node')->where('id',$ids)->value('scenarios_id');
$flist = Db::name('tel_scenarios_node')->where('id',$ids)->delete();
Db::name('tel_flow_node')->where('scen_node_id',$ids)->delete();
$key = 'scenarios_'.$scenarios_id.'_data';
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
if(!$flist){
echo "删除失败。";
}
}
public function addThinkTank(){
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$scenariosId = input('sceneId','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$knowledgeId = input('ThinkTankId','','trim,strip_tags');
if(empty($name)){
return returnAjax(1,'知识库标题不能为空');
}
if(empty($knowledgeId)){
$where_a['scenarios_id']=$scenariosId;
$where_a['name']=$name;
$num = Db::name('tel_knowledge')->where($where_a)->count();
if($num>0){
return returnAjax(1,'知识库标题不能重复');
}
}else{
$where_e['name']=$name;
$where_e['id']=['<>',$knowledgeId];
$where_e['scenarios_id']=$scenariosId;
$num = Db::name('tel_knowledge')->where($where_e)->count();
if($num>0){
return returnAjax(1,'知识库标题不能重复');
}
}
$data = array();
$data['name'] = $name;
$label = input('label','','trim,strip_tags');
$knowledge_type = input('knowledge_type','','trim,strip_tags');
$Othersettings_know = input('Othersettings_know','','trim,strip_tags');
$knlgType = input('knlgType','','trim,strip_tags');
$query_type = input('query_type','','trim,strip_tags');
if($knlgType != ""&&$knlgType != 'null'){
$data['type'] = $knlgType;
}else{
if($knowledge_type==7||$knowledge_type==8||$knowledge_type==9||$knowledge_type==10){
}else{
return returnAjax(1,'类型不能为空');
}
}
$data['break']=$Othersettings_know;
$data['keyword'] = str_replace("，",",",$keyword);
$data['keyword'] = explode(',',$data['keyword']);
foreach($data['keyword'] as $key=>$value){
$data['keyword'][$key] = trim($value);
}
$data['keyword'] = implode(',',$data['keyword']);
if ($data['keyword']){
$cnKeyword = $data['keyword'];
$py = $pinyin->sentence($cnKeyword);
$py = str_replace('）',' )',$py);
$py = str_replace('（','(',$py);
$data['keyword_py'] = $py;
}
$data['action'] = input('action','','trim,strip_tags');
$data['action_id'] = input('actionId','','trim,strip_tags');
$data['intention'] = input('flowNodeLevel','','trim,strip_tags');
$data['create_time'] = time();
$data['update_time'] = time();
$data['scenarios_id'] = $scenariosId;
$data['label'] = $label;
$data['query_type'] = $query_type;
$pausetime = input('pausetime','','trim,strip_tags');
if($pausetime == ""){
$pausetime = 3000;
}
$data['pause_time'] = $pausetime;
$tplId = input('tplId','','trim,strip_tags');
if($tplId != ""){
$data['sms_template_id'] = $tplId;
}
$bridge = input('groupId','','trim,strip_tags');
if($bridge != ""){
$data['bridge'] = $bridge;
}
$delArr = input('delArr');
$arr = explode(',',$delArr);
Db::name('tel_corpus')->where('id','in',$arr)->update(['src_id'=>0]);
if($knowledgeId){
$ret = Db::name('tel_knowledge')->where('id',$knowledgeId)->update($data);
if($ret <0){
return returnAjax(1,'保存失败');
}
$content = input('content','','trim,strip_tags');
$content = json_decode($content,TRUE);
$filename = $_FILES;
if (is_array($content) ||is_array($filename)) {
foreach ($content as $key =>$value) {
$tcpus = array();
$tcpus['content'] = $value["con"];
if($value["id"] >0){
if(isset($value['voice_idr']) === true){
$info1 = Db::name('tel_corpus')->where('id',$value['id'])->find();
Db::name('tel_corpus')->where('id',$value['id'])->update(['src_type'=>3,'src_id'=>0]);
$tcpus['src_type'] = $info1['src_type'];
$tcpus['src_id'] = $info1['src_id'];
Db::name('tel_corpus')->where('id',$value['voice_idr'])->update($tcpus);
}else{
if(isset($filename['filesname_'.$key]) === true &&is_array($filename['filesname_'.$key]) === true){
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
if(!is_dir('uploads/audio/'.date("Ymd").'/')){
mkdir('uploads/audio/'.date("Ymd").'/');
}
$info = move_uploaded_file($filename['filesname_'.$key]['tmp_name'],$new_src);
if ($info) {
$tcpus['audio'] = '/'.$new_src;
}else {
return returnAjax(0,'上传失败',$file->getError());
}
}
Db::name('tel_corpus')->where('id',$value["id"])->update($tcpus);
}
}else{
if(isset($value['voice_idr']) === true){
$tcpus['src_id'] = $knowledgeId;
$tcpus['src_type'] = 1;
Db::name('tel_corpus')->where('id',$value['voice_idr'])->update($tcpus);
}else{
$tcpus['src_id'] = $knowledgeId;
$tcpus['src_type'] = 1;
$tcpus['scenarios_id'] = input('sceneId','','trim,strip_tags');
if(isset($filename['filesname_'.$key]) === true &&is_array($filename['filesname_'.$key]) === true){
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
if(!is_dir('uploads/audio/'.date("Ymd").'/')){
mkdir('uploads/audio/'.date("Ymd").'/');
}
$info = move_uploaded_file($filename['filesname_'.$key]['tmp_name'],$new_src);
if ($info) {
$tcpus['audio'] = '/'.$new_src;
}else {
return returnAjax(0,'上传失败',$file->getError());
}
}
Db::name('tel_corpus')->insertGetId($tcpus);
}
}
}
}
Db::name('tel_label')->where(array('flow_id'=>$knowledgeId,'type'=>2))->delete();
}else{
$knowledgeId = Db::name('tel_knowledge')->insertGetId($data);
if($knowledgeId <= 0){
return returnAjax(1,'添加失败');
}
$content = input('content','','trim,strip_tags');
$content = json_decode($content,TRUE);
$filename = $_FILES;
if (is_array($content) ||is_array($filename)) {
foreach ($content as $key =>$value) {
$tcpus = array();
$tcpus['content'] = $value["con"];
if($value["id"] >0){
if(isset($value['voice_idr']) === true){
$info1 = Db::name('tel_corpus')->where('id',$value['id'])->find();
Db::name('tel_corpus')->where('id',$value['id'])->update(['src_type'=>3,'src_id'=>0]);
$tcpus['src_type'] = $info1['src_type'];
$tcpus['src_id'] = $info1['src_id'];
Db::name('tel_corpus')->where('id',$value['voice_idr'])->update($tcpus);
}else{
if(isset($filename['filesname_'.$key]) === true &&is_array($filename['filesname_'.$key]) === true){
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
if(is_dir('uploads/audio/'.date("Ymd")) ==false){
mkdir('uploads/audio/'.date("Ymd"),0777,true);
}
$info = move_uploaded_file($filename['filesname_'.$key]['tmp_name'],$new_src);
if ($info) {
$tcpus['audio'] = '/'.$new_src;
}else {
return returnAjax(0,'上传失败',$file->getError());
}
}
Db::name('tel_corpus')->where('id',$value["id"])->update($tcpus);
}
}else{
if(isset($value['voice_idr']) === true){
$tcpus['src_id'] = $knowledgeId;
$tcpus['src_type'] = 1;
Db::name('tel_corpus')->where('id',$value['voice_idr'])->update($tcpus);
}else{
$tcpus['src_id'] = $knowledgeId;
$tcpus['src_type'] = 1;
$tcpus['scenarios_id'] = input('sceneId','','trim,strip_tags');
if(isset($filename['filesname_'.$key]) === true &&is_array($filename['filesname_'.$key]) === true){
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
if(is_dir('uploads/audio/'.date("Ymd")) == false){
mkdir('uploads/audio/'.date("Ymd"),0777,true);
}
$info = move_uploaded_file($filename['filesname_'.$key]['tmp_name'],$new_src);
if ($info) {
$tcpus['audio'] = '/'.$new_src;
}else {
return returnAjax(0,'上传失败',$file->getError());
}
}
Db::name('tel_corpus')->insertGetId($tcpus);
}
}
}
}
}
if($label){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$data = array();
$data['member_id'] = (int)$uid;
$data['label'] = $label;
$data['keyword'] = $label;
$data['flow_id'] = $knowledgeId;
$data['scenarios_id'] = $scenariosId;
$data['type'] = 2;
Db::name('tel_label')->insertGetId($data);
}
return returnAjax(0,'保存成功！');
}
public function updateOrder(){
$order = input('order',0,'trim,strip_tags');
$id = input('id',0,'trim,strip_tags');
if(empty($id)){
return returnAjax(0,'id不能为空');
}
if(!is_numeric($order)){
return returnAjax(0,'order排序必须是数字');
}
$res = Db::name('tel_knowledge')->where(['id'=>$id])->update(['order_by'=>$order]);
if(!empty($res)){
return returnAjax(1,'修改排序成功');
}else{
return returnAjax(0,'修改排序失败');
}
}
public function getKnowledgeList(){
$Page_size = 10;
$page = input('page','1','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$sceneId = input('sceneId','','trim,strip_tags');
$processId = input('processId','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$knowledge_type_search = input('knowledge_type_search','','trim,strip_tags');
$is_variable = Db::name('tel_scenarios')->where('id',$sceneId)->value('is_variable');
if($sceneId){
$where["scenarios_id"] = $sceneId;
}else{
return returnAjax( 1,'知识库数据获取失败');
}
if($keyword != ""){
$where["name|keyword|label"] = ['like','%'.$keyword.'%'];
}
if($knowledge_type_search!=0 &&!empty($knowledge_type_search)){
if($knowledge_type_search==8){
$where["type"]=['in',[7,8,9,10]];
}else{
$where["type"] = $knowledge_type_search;
}
}else{
$where['type'] = ['in',[0,1,7,8,9,10]];
}
$KnowledgeList_id = Db::name('tel_knowledge')->field('id')->where($where)->order('id desc')->select();
$list = Db::name('tel_knowledge')
->where($where)
->order('order_by desc,type asc')
->page($page,$Page_size)
->select();
foreach($list as &$item){
if ($item['update_time'] >0){
$item['update_time'] = date('Y-m-d H:i:s',$item['update_time']);
}else{
$item['update_time'] = "";
}
$klist = explode(",",$item['keyword']);
$item["knum"] = count($klist);
if( $item['type']!=7 &&$item['type']!=8 &&$item['type']!=9 &&$item['type']!=10 ){
if($item['action']!=2){
$where_c['src_id']=$item['id'];
$where_c['src_type']=1;
$where_c['scenarios_id']=$sceneId;
$audio_count = Db::name('tel_corpus')->where($where_c)->where('audio','not null')->count('*');
if($is_variable==0){
if($audio_count==0){
$item["color"]='red';
}else{
if(empty($item['keyword'])){
$item["color"]='red';
}else{
$item["color"]='black';
}
}
}else{
$item["color"]='black';
}
}else{
if(empty($item['keyword'])){
$item["color"]='red';
}else{
$item["color"]='black';
}
}
}
}
$cwhere = array();
if($sceneId != ""){
$cwhere["scenarios_id"] = $sceneId;
}
if($keyword != ""){
$cwhere["name|keyword"] = ['like','%'.$keyword.'%'];;
}
if($knowledge_type_search!=0 &&!empty($knowledge_type_search)){
if($knowledge_type_search==8){
$cwhere["type"]=['in',[7,8,9,10]];
}else{
$cwhere["type"] = $knowledge_type_search;
}
}else{
$cwhere['type'] = ['in',[0,1,7,8,9,10]];
}
$count = Db::name('tel_knowledge')
->where($cwhere)
->count('id');
$page_count = ceil($count/$Page_size);
$back = array();
$back['total'] = $count;
$back['Nowpage'] = $page;
$back['list'] = $list;
$back['page'] = $page_count;
$back['knowledge_allid'] = $KnowledgeList_id;
return returnAjax(0,'知识库数据获取成功',$back);
}
public function delKnowledge(){
$knowledgeId = input('id/a','','trim,strip_tags');
$scenarios_id = Db::name('tel_knowledge')->where('id','in',$knowledgeId)->value('scenarios_id');
$flist = Db::name('tel_knowledge')->where('id','in',$knowledgeId)->delete();
$sflist = Db::name('tel_corpus')->where('src_id','in',$knowledgeId)->where('src_type',1)->delete();
$key = 'tel_knowledge_'.$scenarios_id.'_datas';
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
$ret = Db::name('tel_label')->where('flow_id','in',$knowledgeId)->where('type',2)->delete();
if(!$flist &&!$sflist){
echo "删除失败。";
}
}
public function getKnowledgeInfo()
{
$noteId = input('id','','trim,strip_tags');
$result = Db::name('tel_knowledge')
->where('id',$noteId)->find();
$corpus = Db::name('tel_corpus')
->field('id,content,audio')
->where(array("src_id"=>$noteId,'src_type'=>1))
->select();
$result['content'] = $corpus;
if($result){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'暂时没有数据',$result);
}
}
public function addSound(){
$data = array();
if(!empty($_FILES['update-audio-file']['tmp_name'])){
$tmp_file = $_FILES ['update-audio-file'] ['tmp_name'];
if($tmp_file){
$file_types = explode ( ".",$_FILES ['update-audio-file'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
if (strtolower ( $file_type ) != "wav")
{
$this->error ( '不是wav文件，只能上传wav文件，重新上传');
}
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr.'/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".strtolower($file_type);
if (!copy ( $tmp_file,$savePath .$file_name ))
{
$this->error ( '上传失败');
}
$path = $savePath .$file_name;
$path = ltrim($path,".");
$data['audio'] = $path;
}
}
$sid = input('sid','','trim,strip_tags');
$result = Db::name('tel_corpus')->where('id',$sid)->update($data);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if ($result >= 0){
return returnAjax(0,'成功',$data['audio']);
}
else{
return returnAjax(1,'失败',$result);
}
}
public function copyScene(){
$arr_x = [];
$targetObj = input('targetObj','','trim,strip_tags');
$newSName = input('newSName','','trim,strip_tags');
$chaos_num = input('chaos_num','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$scenarios = Db::name('tel_scenarios')
->field('name,member_id,type,is_tpl,status,break,auditing,is_variable')
->where('id',$targetObj)->find();
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$attitude = 30;
$RedisConnect->set($key,$attitude);
$tscen = array();
$tscen['name'] = $newSName;
$tscen['member_id'] = $scenarios["member_id"];
$tscen["type"] = $scenarios["type"];
$tscen['is_tpl'] = $scenarios["is_tpl"];
$tscen['status'] = $scenarios["status"];
$tscen['break'] = $scenarios["break"];
$tscen['auditing'] = $scenarios["auditing"];
$tscen['is_variable'] = $scenarios["is_variable"];
$tscen['update_time'] = time();
$newId = Db::name('tel_scenarios')->insertGetId($tscen);
if($scenarios['is_variable']==1){
$variables = Db::name('audio_variable')->where(['scenarios_id'=>$targetObj])->select();
foreach($variables as $key =>$variable){
$variables[$key]['scenarios_id'] = $newId;
unset($variables[$key]['id']);
}
Db::name('audio_variable')->insertAll($variables);
}
$result = Db::name('tel_scenarios_node')->field('id,name,sort,type')->where('scenarios_id',$targetObj)->select();
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +10;
$RedisConnect->set($key,$attitude);
$tsarray = array();
foreach ($result as $key =>$value) {
$tsdata = array();
$tsdata['scenarios_id'] = $newId;
$tsdata['name'] = $value["name"];
$tsdata['sort'] = $value["sort"];
$tsdata['type'] = $value["type"];
$TRresult = Db::name('tel_scenarios_node')->insertGetId($tsdata);
$tsarray[$value["id"]] = $TRresult;
$flowList = Db::name('tel_flow_node')->where('scen_node_id',$value['id'])->order("pid asc")->select();
$newfnlist = array();
foreach ($flowList as $fnkey =>$fnval) {
$fndata = array();
$fndata['scenarios_id'] = $newId;
$fndata['scen_node_id'] = $TRresult;
$fndata['name'] = $fnval["name"];
$fndata['break'] = $fnval["break"];
$fndata['type'] = $fnval["type"];
$fndata['position'] = $fnval["position"];
if($fnval["pid"] == 0){
$fndata['pid'] = 0;
}
$fndata['action'] = $fnval["action"];
$fndata['action_id'] = $fnval["action_id"];
$fndata['flow_label'] = $fnval["flow_label"];
$fndata['pause_time'] = $fnval["pause_time"];
$fndata['bridge'] = $fnval["bridge"];
$fndata['is_variable'] = $fnval["is_variable"];
$fnresult = Db::name('tel_flow_node')->insertGetId($fndata);
if(!empty($fnval["no_speak_knowledge_id"])){
$arr_x[$fnresult] = $fnval["no_speak_knowledge_id"];
}
$newfnlist[$fnval["id"]] = $fnresult;
if($fnval["type"] == 0){
$label = Db::name('tel_label')->where(array('flow_id'=>$fnval["id"],'type'=>1))->find();
if(!empty($label)){
$insertlabel = array();
$insertlabel['flow_id'] = $fnresult;
$insertlabel['type'] = $label["type"];
$insertlabel['member_id'] = $label["member_id"];
$insertlabel['scenarios_id'] = $newId;
$insertlabel['level'] = $label['level'];
$insertlabel['query_order'] = $label['query_order'];
$insertlabel['label'] = $label["label"];
$insertlabel['keyword'] = $label["keyword"];
Db::name('tel_label')->insertGetId($insertlabel);
}
}
}
foreach ($newfnlist as $nkey =>$nval) {
$data = array();
foreach ($flowList as $okey =>$oval) {
if($oval["id"] == $nkey){
if($oval["pid"] >0){
if(isset($newfnlist[$oval["pid"]])){
$data['pid'] = $newfnlist[$oval["pid"]];
}else{
$data['pid'] = 1;
}
}else{
$data['pid'] = 0;
}
}
}
$result = Db::name('tel_flow_node')->where('id',$nval)->update($data);
}
foreach ($flowList as $fbkey =>$fbval) {
$res = Db::name('tel_corpus')->field('content,audio,src_type,is_variable')
->where(array("src_id"=>$fbval["id"],'src_type'=>0))
->find();
$itemcs = array();
$itemcs['scenarios_id'] = $newId;
$itemcs['src_type'] = $res["src_type"];
$itemcs['src_id'] = $newfnlist[$fbval["id"]];
$itemcs['content'] = $res["content"];
$itemcs['audio'] = $res["audio"];
$itemcs['is_variable'] = $res["is_variable"];
if($res["src_type"]===0){
$csresult = Db::name('tel_corpus')->insertGetId($itemcs);
}
$fbList = Db::name('tel_flow_branch')->where('flow_id',$fbval['id'])->order("id asc")->select();
foreach ($fbList as $itemfb =>$vfb) {
$fbdata = array();
$fbdata['flow_id'] = $newfnlist[$vfb["flow_id"]];
$fbdata['name'] = $vfb["name"];
$fbdata['keyword'] = $vfb["keyword"];
$fbdata['keyword_py'] = $vfb["keyword_py"];
if(!empty($vfb["next_flow_id"])){
if(!empty($newfnlist[$vfb["next_flow_id"]])){
$fbdata['next_flow_id'] = $newfnlist[$vfb["next_flow_id"]];
}
}
$fbdata['is_select'] = $vfb["is_select"];
$fbdata['type'] = $vfb["type"];
$fbdata['label'] = $vfb["label"];
$fbdata['label_status'] = $vfb["label_status"];
$fbdata['query_type'] = $vfb["query_type"];
$fbdata['order_by'] = $vfb["order_by"];
$snresult = Db::name('tel_flow_branch')->insertGetId($fbdata);
}
}
}
$where_lables['scenarios_id']=$targetObj;
$where_lables['flow_id']=['exp','is null'];
$tel_labels = Db::name('tel_label')->where($where_lables)->select();
foreach($tel_labels as $key=>$tel_label){
$ldata = array();
$ldata['flow_id'] = $tel_label['flow_id'];
$ldata['type'] = $tel_label["type"];
$ldata['member_id'] = $tel_label["member_id"];
$ldata['scenarios_id'] = $newId;
$ldata['label'] = $tel_label["label"];
$ldata['keyword'] = $tel_label["keyword"];
$ldata['level'] = $tel_label['level'];
$ldata['query_order'] = $tel_label['query_order'];
$snresult = Db::name('tel_label')->insertGetId($ldata);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +30;
$RedisConnect->set($key,$attitude);
foreach ($tsarray as $ntskey =>$ntsvalue) {
$nflowList = Db::name('tel_flow_node')->where('scen_node_id',$ntsvalue)->order("pid asc")->select();
foreach ($nflowList as $okey =>$oval) {
if($oval["type"] == 1 &&$oval["action"] == 2 &&$oval["action_id"]){
$data = array();
$data['action_id'] = isset($tsarray[$oval["action_id"]]) ?$tsarray[$oval["action_id"]] :0;
$result = Db::name('tel_flow_node')->where('id',$oval["id"])->update($data);
}
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +10;
$RedisConnect->set($key,$attitude);
$tfnw = array();
$tfnw['scenarios_id'] = $targetObj;
$knresult = Db::name('tel_knowledge')
->where($tfnw)
->select();
foreach ($knresult as $knkey =>$knval) {
$kndata = array();
$kndata['scenarios_id'] = $newId;
$kndata['name'] = $knval["name"];
$kndata['type'] = $knval["type"];
$kndata['keyword'] = $knval["keyword"];
$kndata['keyword_py'] = $knval["keyword_py"];
$kndata['action'] = $knval["action"];
$kndata['action_id'] = $knval["action_id"];
if($knval["action"] == 2){
$kndata['action_id'] = isset($tsarray[$knval["action_id"]]) ?$tsarray[$knval["action_id"]] : 0;
}
$kndata['intention'] = $knval["intention"];
$kndata['create_time'] = time();
$kndata['update_time'] = time();
$kndata['pause_time'] = $knval["pause_time"];
$kndata['label'] = $knval["label"];
$kndata['label_status'] = $knval["label_status"];
$kndata['is_default'] = $knval["is_default"];
$kndata['sms_template_id'] = $knval["sms_template_id"];
$kndata['bridge'] = $knval["bridge"];
$kndata['query_type'] = $knval["query_type"];
$knNewRes = Db::name('tel_knowledge')->insertGetId($kndata);
foreach($arr_x as $key=>$value){
if($knval['id']==$value){
Db::name('tel_flow_node')->where(['id'=>$key])->update(['no_speak_knowledge_id'=>$knNewRes]);
}
}
$res = Db::name('tel_corpus')
->where(array("src_id"=>$knval["id"],'src_type'=>1))
->select();
$temp = array();
foreach ($res as $rkey =>$rval) {
$tempcs = array();
$tempcs['src_id'] = $knNewRes;
$tempcs['content'] = $rval["content"];
$tempcs['src_type'] = 1;
$tempcs['source'] = $rval["source"];
$tempcs['audio'] = $rval["audio"];
$tempcs['scenarios_id'] = $newId;
$tempcs['file_name'] = $rval["file_name"];
$tempcs['file_size'] = $rval["file_size"];
$tempcs['is_variable'] = $rval["is_variable"];
array_push($temp,$tempcs);
}
$csresult = Db::name('tel_corpus')->insertAll($temp);
$label = Db::name('tel_label')->where(array('flow_id'=>$knval["id"],'type'=>2))->find();
if(!empty($label)){
$insertlabel = array();
$insertlabel['flow_id'] = $knNewRes;
$insertlabel['type'] = $label["type"];
$insertlabel['scenarios_id'] = $newId;
$insertlabel['member_id'] = $label["member_id"];
$insertlabel['label'] = $label["label"];
$insertlabel['keyword'] = $label["keyword"];
Db::name('tel_label')->insertGetId($insertlabel);
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +10;
$RedisConnect->set($key,$attitude);
$tirres_template = Db::name('tel_intention_rule_template')
->where('scenarios_id',$targetObj)
->select();
foreach($tirres_template as $key =>$value){
$tempz = array();
$tempz['scenarios_id'] = $newId;
$tempz['name'] = $value['name'];
$tempz['description'] = $value['description'];
$tempz['status'] = $value['status'];
$res_id = Db::name('tel_intention_rule_template')->insertGetId($tempz);
$listrule = Db::name('tel_intention_rule')->where(['scenarios_id'=>$targetObj,'template_id'=>$value['id']])->select();
$templates = array();
foreach($listrule as $k =>$v){
$tempy = array();
$tempy['scenarios_id'] = $newId;
$tempy['template_id'] = $res_id;
$tempy['name'] = $v['name'];
$tempy['level'] = $v['level'];
$tempy['type'] = $v['type'];
$tempy['rule'] = $v['rule'];
$tempy['sort'] = $v['sort'];
$tempy['status'] = $v['status'];
$tempy['create_time'] = time();
$tempy['update_time'] = time();
array_push($templates,$tempy);
}
Db::name('tel_intention_rule')->insertAll($templates);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +5;
$RedisConnect->set($key,$attitude);
$scenarios_config = Db::name('tel_scenarios_config')
->where('scenarios_id',$targetObj)
->find();
$s_config = array();
$s_config['scenarios_id'] = $newId;
$s_config['pause_play_ms'] = $scenarios_config['pause_play_ms'] ?$scenarios_config['pause_play_ms'] : 0;
$s_config['min_speak_ms'] = $scenarios_config['min_speak_ms']?$scenarios_config['min_speak_ms']:0;
$s_config['max_speak_ms'] = $scenarios_config['max_speak_ms']?$scenarios_config['max_speak_ms']:0;
$s_config['volume'] = $scenarios_config['volume']?$scenarios_config['volume']:0;
$s_config['filter_level'] = $scenarios_config['filter_level']?$scenarios_config['filter_level']:0;
$sce_config =  Db::name('tel_scenarios_config')->insert($s_config);
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +5;
$RedisConnect->set($key,$attitude);
$RedisConnect->del($key);
if($newId){
return returnAjax(0,'有数据了');
}else{
return returnAjax(1,'暂时没有数据');
}
}
public function drawingImg($value='')
{
return $this->fetch();
}
public function graph()
{
return $this->fetch();
}
public function noteList($value='')
{
$sceneId = input('sceneId','','trim,strip_tags');
$tfnw = array();
$tfnw['scen_node_id'] = $sceneId;
$tfnw['type'] = [['=',0],['=',1],"or"];
$flow_node = Db::name('tel_flow_node')->where('scen_node_id',$sceneId)->find();
$result = Db::name('tel_flow_node')
->field('id,scen_node_id,name,break,type,position,pid,action,action_id')
->where($tfnw)->order("pid asc")
->select();
$is_variable = Db::name('tel_scenarios')->where('id',$flow_node['scenarios_id'])->value('is_variable');
foreach ($result as $key =>$value) {
$result[$key]['is_variable']=$is_variable;
if($value['position']){
$position = explode(",",$value['position']);
if(count($position) >1){
$result[$key]['top'] = $position[0];
$result[$key]['left'] = $position[1];
}else{
$result[$key]['top'] = $position[0];
$result[$key]['left'] = 0;
}
}
$res = Db::name('tel_corpus')->field('id,content,audio')->where(array('src_id'=>$value['id'],'src_type'=>0))->find();
$result[$key]['content'] = $res["content"];
$result[$key]['tc_id'] = $res["id"];
if($res["audio"]){
if(!file_exists(ROOT_PATH.$res["audio"])){
$result[$key]['audio'] = 1;
}else{
$result[$key]['audio'] = 0;
}
}else{
$result[$key]['audio'] = 1;
}
$result[$key]['key'] ="node".$value['id'];
if($value['type'] == 1){
$result[$key]['type'] ="WorkTime";
if($value['action'] == 2){
$ress = Db::name('tel_scenarios_node')->field('scenarios_id,name')->where('id',$value['action_id'])->find();
$result[$key]['next_id'] = $value['action_id'];
$result[$key]['next_name'] = $ress["name"];
}else{
switch ($value['action']) {
case 4:
$result[$key]['next_name'] = "挂机";
break;
case 1:
$result[$key]['next_name'] = "下一场景节点";
break;
case 3:
$result[$key]['next_name'] = "返回当前场景节点中的话术";
break;
case 0:
$result[$key]['next_name'] = "等待用户响应";
break;
default:
$result[$key]['next_name'] = "指定场景节点";
}
}
}else{
$result[$key]['type'] ="Menu";
}
$where = array();
$where['flow_id'] = $value['id'];
$where['is_select'] = 1;
$return = Db::name('tel_flow_branch')->where($where)->order("order_by asc")->select();
$return = $this->tel_flow_branch_order($return);
foreach ($return as $kk =>$val) {
$return[$kk]['nextNode'] = "node-".$val['next_flow_id'];
}
$result[$key]['data']['choices'] = $return;
}
if($sceneId){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'暂时没有数据');
}
}
public function telflowNode(){
$voice_idr = input('voice_idr','','trim,strip_tags');
$top = input('top','','trim,strip_tags');
$left = input('left','','trim,strip_tags');
$otherset = input('otherset','','trim,strip_tags');
$custnode = input('custnode','','trim,strip_tags');
$fixed = input('fixed','','trim,strip_tags');
$custnode = json_decode($custnode,TRUE);
$fixed = json_decode($fixed,TRUE);
$AIStechnique = input('AIStechnique','','trim,strip_tags');
$cNodeName = input('cNodeName','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$nowProcessId = input('nowProcessId','','trim,strip_tags');
$type = input('type','0','trim,strip_tags');
$action = input('action','','trim,strip_tags');
$actionId = input('actionId','','trim,strip_tags');
$nodeLabel = input('nodeLabel','','trim,strip_tags');
$pauseTime = input('pauseTime','3000','trim,strip_tags');
$eightval = input('eightval','','trim,strip_tags');
$tplId = input('tplId','','trim,strip_tags');
$groupId = input('groupId','','trim,strip_tags');
$voice_path = input('voice_path','','trim,strip_tags');
$is_variable = input('is_variable','','trim,strip_tags');
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$back = array();
$data = array();
$data['scen_node_id'] = $nowProcessId;
$data['name'] = $cNodeName;
$data['break'] = $otherset;
$data['type'] = $type;
$data['last_time'] = time();
$data['scenarios_id'] = $sceneId;
$data['position'] = $top.",".$left;
$data['action'] = $action;
$data['action_id'] = $actionId;
$data['flow_label'] = $nodeLabel;
if($pauseTime == ""){
$pauseTime = 3000;
}
$data['pause_time'] = $pauseTime;
$data['no_speak_knowledge_id'] = $eightval;
$data['sms_template_id'] = $tplId;
$data['bridge'] = $groupId;
$data['is_variable'] = $is_variable;
$result = Db::name('tel_flow_node')->insertGetId($data);
$back["fnode"] = $result;
if($nodeLabel &&$result){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$Label = array();
$Label['member_id'] = (int)$uid;
$Label['label'] = $nodeLabel;
$Label['keyword'] = $nodeLabel;
$Label['flow_id'] = $result;
$Label['scenarios_id'] = $sceneId;
$Label['type'] = 1;
Db::name('tel_label')->insertGetId($Label);
}
if ($result){
$tcpus = array();
$tcpus['content'] = $AIStechnique;
$tcpus['src_id'] = $result;
$tcpus['src_type'] = 0;
$tcpus['scenarios_id'] = $sceneId;
$tcpus['is_variable'] = $is_variable;
$filename = Request::instance()->file('voicefile');
if(!empty($filename)){
$info = $filename->move(ROOT_PATH.'uploads'.DS .'audio');
if ($info) {
$path = $info->getSaveName();
$file_size = $info->getSize();
$src = 'uploads/audio/'.$path;
if($file_size>=1572864){
unlink(ROOT_PATH .'/'.$src);
return returnAjax(1,'上传音频不能大于1.5M');
}
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
rename($src ,$new_src);
$tcpus['audio'] = '/'.$new_src;
$tcpus['source'] = 0;
$file_size = $this->getFilesize_lj($file_size);
$tcpus['file_size'] = $file_size;
}else {
return returnAjax(1,'修改失败',$file->getError());
}
}else if(!empty($voice_path) &&empty($filename)){
$file_size = Db::name('tel_corpus')->where(['id'=>$voice_idr])->value('file_size');
$tcpus['audio'] = $voice_path;
$tcpus['source']=1;
$tcpus['file_size'] = $file_size;
$xxx= Db::name('tel_corpus')->where(['id'=>$voice_idr])->delete();
}else{
$tcpus['source']=0;
}
$return = Db::name('tel_corpus')->insertGetId($tcpus);
$back["tcorpus"] = $return;
}
$tfbranch = array();
if ($result &&($type == 0)) {
if (is_array($custnode)){
foreach ($custnode as $key =>$value) {
$flowbr = array();
$flowbr['flow_id'] = $result;
$flowbr['name'] = $value["tempName"];
$flowbr['label'] = $value["label"];
$tempKW = str_replace("，",",",$value["tempKW"]);
$flowbr['keyword'] = $tempKW;
if ($value['tempKW']){
$py = $pinyin->sentence($tempKW);
$flowbr['keyword_py'] = $py;
}
$flowbr['is_select'] = $value["is_select"];
$flowbr['type'] = 0;
$flowbr['query_type'] = $value['query_type'];
$flowbr['order_by'] = $value['order_by'];
$tes = Db::name('tel_flow_branch')->insertGetId($flowbr);
array_push($tfbranch,$tes);
}
}
foreach ($fixed as $fkey =>$fvalue) {
$flowbr = array();
$flowbr['flow_id'] = $result;
$flowbr['name'] = $fvalue["name"];
$flowbr['label'] =$fvalue["label"];
$keyword = str_replace("，",",",$fvalue["keyword"]);
$flowbr['keyword'] = $keyword;
if ($fvalue['keyword']){
$py = $pinyin->sentence($keyword);
$flowbr['keyword_py'] = $py;
}
$flowbr['is_select'] = $fvalue["is_select"];
$flowbr['type'] = $fvalue["sort"];
$flowbr['query_type'] = $fvalue['query_type'];
$flowbr['order_by'] = $fvalue['order_by'];
$res = Db::name('tel_flow_branch')->insertGetId($flowbr);
array_push($tfbranch,$res);
}
}
$back["tfbranch"] = $tfbranch;
if($result){
return returnAjax(0,'有数据了',$back);
}else{
return returnAjax(1,'暂时没有数据');
}
}
function getFilesize_lj($bytes,$precision = 2) {
$units = array('B','KB','MB','GB','TB');
$bytes = max($bytes,0);
$pow = floor(($bytes ?log($bytes) : 0) / log(1024));
$pow = min($pow,count($units) -1);
$bytes /= pow(1024,$pow);
return round($bytes,$precision) .' '.$units[$pow];
}
public function editflowNode(){
$top = input('top','','trim,strip_tags');
$left = input('left','','trim,strip_tags');
$otherset = input('otherset','','trim,strip_tags');
$custnode = input('custnode','','trim,strip_tags');
$custnode = json_decode($custnode,TRUE);
$fixed = input('fixed','','trim,strip_tags');
$AIStechnique = input('AIStechnique','','trim,strip_tags');
$cNodeName = input('cNodeName','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$nowProcessId = input('nowProcessId','','trim,strip_tags');
$type = input('type','0','trim,strip_tags');
$delNode = input('delNode/a','','trim,strip_tags');
$tcId = input('tc_id','','trim,strip_tags');
$nodeId = input('nodeId','','trim,strip_tags');
$nodeLabel = input('nodeLabel','','trim,strip_tags');
$pauseTime = input('pauseTime','3000','trim,strip_tags');
$eightval = input('eightval','','trim,strip_tags');
$tplId = input('tplId','','trim,strip_tags');
$groupId = input('groupId','','trim,strip_tags');
$voice_idr = input('voice_idr','','trim,strip_tags');
$voice_path = input('voice_path','','trim,strip_tags');
$is_variable = input('is_variable','','trim,strip_tags');
$filename = Request::instance()->file('voicefile');
if(!empty($filename)){
$info = $filename->move(ROOT_PATH.'uploads'.DS .'audio');
if ($info) {
$file_size = $info->getSize();
$path = $info->getSaveName();
$src = 'uploads/audio/'.$path;
if($file_size>=1572864){
unlink(ROOT_PATH .'/'.$src);
return returnAjax(1,'上传音频不能大于1.5M');
}
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
$file_size = $this->getFilesize_lj($file_size);
rename($src ,$new_src);
if(empty($tcId)){
$data1 =[];
$data1['audio'] = '/'.$new_src;
$data1['content'] = $AIStechnique;
$data1['src_type'] = 0;
$data1['src_id'] = $nodeId;
$data1['scenarios_id'] = $sceneId;
$data1['source'] = 0;
$data1['file_size'] = $file_size;
$res1= Db::name('tel_corpus')->insertGetId($data1);
}else{
$data =[];
$data['audio'] = '/'.$new_src;
$data['content'] = $AIStechnique;
$data['src_type'] = 0;
$data['source'] = 0;
$data['file_size'] = $file_size;
$res1= Db::name('tel_corpus')->where('id',$tcId)->update($data);
}
}else{
return returnAjax(1,'修改失败',$file->getError());
}
}
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$back = array();
$data = array();
$data['name'] = $cNodeName;
$data['break'] = $otherset;
$data['position'] = $top.",".$left;
$data['flow_label'] = $nodeLabel;
if($pauseTime == ""){
$pauseTime = 3000;
}
$data['pause_time'] = $pauseTime;
$data['no_speak_knowledge_id'] = $eightval;
$data['sms_template_id'] = $tplId;
$data['bridge'] = $groupId;
$data['is_variable'] = $is_variable;
$result = Db::name('tel_flow_node')->where('id',$nodeId)->update($data);
$back["fnode"] = $nodeId;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if($nodeLabel &&$nodeId){
$Label = array();
$Label['label'] = $nodeLabel;
$Label['keyword'] = $nodeLabel;
$Label['scenarios_id'] = $sceneId;
$Label['type'] = 1;
$label = Db::name('tel_label')->field('id')->where(array('flow_id'=>$nodeId,'type'=>1))->find();
if($label){
Db::name('tel_label')->where('id',$label["id"])->update($Label);
}else{
$Label['member_id'] = $uid;
$Label['flow_id'] = $nodeId;
$ret = Db::name('tel_label')->insertGetId($Label);
}
}
if($tcId &&$voice_idr == 'undefined'&&empty($filename)) {
$tcpus = array();
$tcpus['content'] = $AIStechnique;
$tcpus['is_variable'] = $is_variable;
$return = Db::name('tel_corpus')->where('id',$tcId)->update($tcpus);
}else if ($voice_idr == 'undefined'&&!empty($filename)) {
$return = $res1;
}else if ($voice_idr == 'undefined'&&!$tcId &&empty($filename) ) {
$tcpus = array();
$data1['source'] = 0;
$tcpus['src_type'] = 0;
$tcpus['src_id'] = $nodeId;
$tcpus['scenarios_id'] = $sceneId;
$tcpus['content'] = $AIStechnique;
$tcpus['is_variable'] = $is_variable;
$return = Db::name('tel_corpus')->insert($tcpus);
}else if ($voice_idr != 'undefined'&&$tcId) {
$info = Db::name('tel_corpus')->where(['id'=>$voice_idr])->find();
$tcpus = array();
$tcpus['content'] = $AIStechnique;
$tcpus['audio'] = $voice_path;
$tcpus['source'] = 1;
$tcpus['src_type'] = 0;
$tcpus['src_id'] = $nodeId;
$tcpus['scenarios_id'] = $sceneId;
$tcpus['file_size'] = $info['file_size'];
$tcpus['is_variable'] = $is_variable;
$return = Db::name('tel_corpus')->where('id',$tcId)->update($tcpus);
$xxx= Db::name('tel_corpus')->where(['id'=>$voice_idr])->delete();
}else if($voice_idr != 'undefined'&&!$tcId){
$info = Db::name('tel_corpus')->where(['id'=>$voice_idr])->find();
$desr['audio'] = $voice_path;
$desr['content'] = $AIStechnique;
$desr['src_type'] = 0;
$desr['src_id'] = $nodeId;
$desr['scenarios_id'] = $sceneId;
$desr['source'] = 1;
$desr['file_size'] = $info['file_size'];
$desr['is_variable'] = $is_variable;
$return = Db::name('tel_corpus')->insert($desr);
$xxx= Db::name('tel_corpus')->where(['id'=>$voice_idr])->delete();
}
$back["tcorpus"] = $return;
$tfbranch = array();
if(is_array($custnode) &&count($custnode)){
foreach ($custnode as $key =>$value) {
$flowbr = array();
$flowbr['flow_id'] = $nodeId;
$flowbr['name'] = $value["tempName"];
$tempKW = str_replace("，",",",$value["tempKW"]);
$flowbr['keyword'] = $tempKW;
if ($value['tempKW']){
$py = $pinyin->sentence($tempKW);
$flowbr['keyword_py'] = $py;
}
$flowbr['label'] = $value['label'];
$flowbr['is_select'] = $value["is_select"];
if(!$value["is_select"]){
$flowbr['next_flow_id'] = null;
}
$flowbr['query_type'] = $value['query_type'];
$flowbr['order_by'] = $value['order_by'];
$flowbr['type'] = 0;
if($value["branchId"]){
$tes = Db::name('tel_flow_branch')->where('id',$value["branchId"])->update($flowbr);
}else{
$tes = Db::name('tel_flow_branch')->insertGetId($flowbr);
}
array_push($tfbranch,$tes);
}
}
$fixed = json_decode($fixed,TRUE);
if(is_array($fixed) &&count($fixed)){
foreach ($fixed as $fkey =>$fvalue) {
$flowbr = array();
$flowbr['flow_id'] = $nodeId;
$flowbr['name'] = $fvalue["name"];
$flowbr['label'] =$fvalue["label"];
$keyword = str_replace("，",",",$fvalue["keyword"]);
$flowbr['keyword'] = $keyword;
$flowbr['query_type'] = $fvalue['query_type'];
$flowbr['order_by'] = $fvalue['order_by'];
if ($fvalue['keyword']){
$cnKeyword = str_replace('|','/',$keyword);
$py = $pinyin->sentence($cnKeyword);
$py = str_replace(') ',' )',$py);
$py = str_replace('| ',' |',$py);
$py = str_replace('( ','(',$py);
$flowbr['keyword_py'] = $py;
}
$flowbr['is_select'] = $fvalue["is_select"];
$flowbr['type'] = $fvalue["sort"];
if(!$fvalue["is_select"]){
$flowbr['next_flow_id'] = null;
}
if($fvalue["id"]){
$res = Db::name('tel_flow_branch')->where('id',$fvalue["id"])->update($flowbr);
}else{
$res = Db::name('tel_flow_branch')->insertGetId($flowbr);
}
array_push($tfbranch,$res);
}
}
if(is_array($delNode) &&count($delNode)){
$ret = Db::name('tel_flow_branch')->where('id','in',$delNode)->delete();
}
$back["tfbranch"] = $tfbranch;
$back["fnode"] = $nodeId;
if($result >=0){
return returnAjax(0,'有数据了',$back);
}else{
return returnAjax(1,'更新失败');
}
}
public function editJNode(){
$action = input('action','','trim,strip_tags');
$actionId = input('actionId','','trim,strip_tags');
$top = input('top','','trim,strip_tags');
$left = input('left','','trim,strip_tags');
$otherset = input('otherset','','trim,strip_tags');
$custnode = input('custnode','','trim,strip_tags');
$custnode = json_decode($custnode,TRUE);
$fixed = input('fixed','','trim,strip_tags');
$AIStechnique = input('AIStechnique','','trim,strip_tags');
$cNodeName = input('cNodeName','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$nowProcessId = input('nowProcessId','','trim,strip_tags');
$type = input('type','0','trim,strip_tags');
$delNode = input('delNode/a','','trim,strip_tags');
$tcId = input('tc_id','','trim,strip_tags');
$nodeId = input('nodeId','','trim,strip_tags');
$nodeLabel = input('nodeLabel','','trim,strip_tags');
$pauseTime = input('pauseTime','3000','trim,strip_tags');
$eightval = input('eightval','','trim,strip_tags');
$tplId = input('tplId','','trim,strip_tags');
$groupId = input('groupId','','trim,strip_tags');
$voice_idr = input('voice_idr','','trim,strip_tags');
$voice_path = input('voice_path','','trim,strip_tags');
$is_variable = input('is_variable','','trim,strip_tags');
$filename = Request::instance()->file('voicefile');
if(!empty($filename)){
$info = $filename->move(ROOT_PATH.'uploads'.DS .'audio');
if ($info) {
$file_size = $info->getSize();
$path = $info->getSaveName();
$src = 'uploads/audio/'.$path;
if($file_size>=1572864){
unlink(ROOT_PATH .'/'.$src);
return returnAjax(1,'上传音频不能大于1.5M');
}
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
$file_size = $this->getFilesize_lj($file_size);
rename($src ,$new_src);
if(empty($tcId)){
$data1 =[];
$data1['audio'] = '/'.$new_src;
$data1['content'] = $AIStechnique;
$data1['src_type'] = 0;
$data1['src_id'] = $nodeId;
$data1['scenarios_id'] = $sceneId;
$data1['source'] = 0;
$data1['file_size'] = $file_size;
$res1= Db::name('tel_corpus')->insertGetId($data1);
}else{
$data =[];
$data['audio'] = '/'.$new_src;
$data['content'] = $AIStechnique;
$data['src_type'] = 0;
$data['source'] = 0;
$data['file_size'] = $file_size;
$res1= Db::name('tel_corpus')->where('id',$tcId)->update($data);
}
}else{
return returnAjax(1,'修改失败',$file->getError());
}
}
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$back = array();
$data = array();
$data['name'] = $cNodeName;
$data['break'] = $otherset;
$data['position'] = $top.",".$left;
$data['flow_label'] = $nodeLabel;
if($pauseTime == ""){
$pauseTime = 3000;
}
$data['action']=$action;
$data['pause_time'] = $pauseTime;
$data['no_speak_knowledge_id'] = $eightval;
$data['sms_template_id'] = $tplId;
$data['bridge'] = $groupId;
$data['action_id'] = $actionId;
$data['is_variable'] = $is_variable;
$result = Db::name('tel_flow_node')->where('id',$nodeId)->update($data);
$back["fnode"] = $nodeId;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if($nodeLabel &&$nodeId){
$Label = array();
$Label['label'] = $nodeLabel;
$Label['keyword'] = $nodeLabel;
$Label['scenarios_id'] = $sceneId;
$Label['type'] = 1;
$label = Db::name('tel_label')->field('id')->where(array('flow_id'=>$nodeId,'type'=>1))->find();
if($label){
Db::name('tel_label')->where('id',$label["id"])->update($Label);
}else{
$Label['member_id'] = $uid;
$Label['flow_id'] = $nodeId;
$ret = Db::name('tel_label')->insertGetId($Label);
}
}
if($tcId &&$voice_idr == 'undefined'&&empty($filename)) {
$tcpus = array();
$tcpus['content'] = $AIStechnique;
if(!empty($AIStechnique)){
$return = Db::name('tel_corpus')->where('id',$tcId)->update($tcpus);
}else{
$return=1;
}
}else if ($voice_idr == 'undefined'&&!empty($filename)) {
$return = $res1;
}else if ($voice_idr == 'undefined'&&!$tcId &&empty($filename) ) {
$tcpus = array();
$data1['source'] = 0;
$tcpus['src_type'] = 0;
$tcpus['src_id'] = $nodeId;
$tcpus['scenarios_id'] = $sceneId;
$tcpus['content'] = $AIStechnique;
if(!empty($AIStechnique)){
$return = Db::name('tel_corpus')->insertGetId($tcpus);
}else{
$return=1;
}
}else if ($voice_idr != 'undefined'&&$tcId) {
$info = Db::name('tel_corpus')->where(['id'=>$voice_idr])->find();
$tcpus = array();
$tcpus['content'] = $AIStechnique;
$tcpus['audio'] = $voice_path;
$tcpus['source'] = 1;
$tcpus['src_type'] = 0;
$tcpus['src_id'] = $nodeId;
$tcpus['scenarios_id'] = $sceneId;
$tcpus['file_size'] = $info['file_size'];
$return = Db::name('tel_corpus')->where('id',$tcId)->update($tcpus);
$xxx= Db::name('tel_corpus')->where(['id'=>$voice_idr])->delete();
}else if($voice_idr != 'undefined'&&!$tcId){
$info = Db::name('tel_corpus')->where(['id'=>$voice_idr])->find();
$desr['audio'] = $voice_path;
$desr['content'] = $AIStechnique;
$desr['src_type'] = 0;
$desr['src_id'] = $nodeId;
$desr['scenarios_id'] = $sceneId;
$desr['source'] = 1;
$desr['file_size'] = $info['file_size'];
$return = Db::name('tel_corpus')->insertGetId($desr);
$xxx= Db::name('tel_corpus')->where(['id'=>$voice_idr])->delete();
}
if($tcId &&empty($AIStechnique)){
if(empty($voice_path)){
Db::name('tel_corpus')->where(['id'=>$tcId])->delete();
}else{
$filename = ROOT_PATH.$voice_path;
if(!file_exists($filename)){
Db::name('tel_corpus')->where(['id'=>$tcId])->delete();
}
}
}
$back["tcorpus"] = $return;
$tfbranch = array();
if(is_array($custnode) &&count($custnode)){
foreach ($custnode as $key =>$value) {
$flowbr = array();
$flowbr['flow_id'] = $nodeId;
$flowbr['name'] = $value["tempName"];
$tempKW = str_replace("，",",",$value["tempKW"]);
$flowbr['keyword'] = $tempKW;
if ($value['tempKW']){
$py = $pinyin->sentence($tempKW);
$flowbr['keyword_py'] = $py;
}
$flowbr['label'] = $value['label'];
$flowbr['is_select'] = $value["is_select"];
if(!$value["is_select"]){
$flowbr['next_flow_id'] = null;
}
$flowbr['query_type'] = $value['query_type'];
$flowbr['order_by'] = $value['order_by'];
$flowbr['type'] = 0;
if($value["branchId"]){
$tes = Db::name('tel_flow_branch')->where('id',$value["branchId"])->update($flowbr);
}else{
$tes = Db::name('tel_flow_branch')->insertGetId($flowbr);
}
array_push($tfbranch,$tes);
}
}
$fixed = json_decode($fixed,TRUE);
if(is_array($fixed) &&count($fixed)){
foreach ($fixed as $fkey =>$fvalue) {
$flowbr = array();
$flowbr['flow_id'] = $nodeId;
$flowbr['name'] = $fvalue["name"];
$flowbr['label'] =$fvalue["label"];
$keyword = str_replace("，",",",$fvalue["keyword"]);
$flowbr['keyword'] = $keyword;
$flowbr['query_type'] = $fvalue['query_type'];
$flowbr['order_by'] = $fvalue['order_by'];
if ($fvalue['keyword']){
$cnKeyword = str_replace('|','/',$keyword);
$py = $pinyin->sentence($cnKeyword);
$py = str_replace(') ',' )',$py);
$py = str_replace('| ',' |',$py);
$py = str_replace('( ','(',$py);
$flowbr['keyword_py'] = $py;
}
$flowbr['is_select'] = $fvalue["is_select"];
$flowbr['type'] = $fvalue["sort"];
if(!$fvalue["is_select"]){
$flowbr['next_flow_id'] = null;
}
if($fvalue["id"]){
$res = Db::name('tel_flow_branch')->where('id',$fvalue["id"])->update($flowbr);
}else{
$res = Db::name('tel_flow_branch')->insertGetId($flowbr);
}
array_push($tfbranch,$res);
}
}
if(is_array($delNode) &&count($delNode)){
$ret = Db::name('tel_flow_branch')->where('id','in',$delNode)->delete();
}
$back["tfbranch"] = $tfbranch;
$back["fnode"] = $nodeId;
if($result >=0){
return returnAjax(0,'有数据了',$back);
}else{
return returnAjax(1,'更新失败');
}
}
public function getFnodeInfo(){
$flowId = input('fId','','trim,strip_tags');
$senid = input('senid','','trim,strip_tags');
$tfnw = array();
$tfnw['id'] = $flowId;
$result = Db::name('tel_flow_node')->field('id,name,break,action,action_id,flow_label,pause_time,no_speak_knowledge_id,sms_template_id,bridge,scenarios_id')->where($tfnw)->find();
$is_variable = Db::name('tel_scenarios')->where('id',$result['scenarios_id'])->value('is_variable');
$result['is_variable']=$is_variable;
$res = Db::name('tel_corpus')->field('id,content,audio,source')->where(array('src_id'=>$flowId,'src_type'=>0,'scenarios_id'=>$senid))->find();
$result['content'] = $res["content"];
$result['tc_id'] = $res["id"];
$result['audio'] = $res["audio"];
$result['source'] = $res["source"];
$where = array();
$where['flow_id'] = $flowId;
$return = Db::name('tel_flow_branch')->where($where)->order("order_by asc")->select();
foreach($return as $key=>$value){
if($value['type']==2){
if(empty($value['keyword']) ||$value['keyword']=='null'){
$keyword = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>2])->value('keyword');
$return[$key]['keyword']=$keyword;
}
}
if($value['type']==3){
if(empty($value['keyword']) ||$value['keyword']=='null'){
$keyword = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>3])->value('keyword');
$return[$key]['keyword']=$keyword;
}
}
if($value['type']==4){
if(empty($value['keyword']) ||$value['keyword']=='null'){
$keyword = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>4])->value('keyword');
$return[$key]['keyword']=$keyword;
}
}
if($value['type']==5){
if(empty($value['keyword']) ||$value['keyword']=='null'){
$keyword = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>5])->value('keyword');
$return[$key]['keyword']=$keyword;
}
}
if($value['type']==6){
if(empty($value['keyword']) ||$value['keyword']=='null'){
$keyword = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>6])->value('keyword');
$return[$key]['keyword']=$keyword;
}
}
}
$return = $this->tel_flow_branch_order($return);
$result['returns'] = $return;
if($result){
return returnAjax(0,'获取数据成功',$result);
}else{
return returnAjax(1,'暂时没有数据',$result);
}
}
public function saveAllNode()
{
$sceneId = input('sceneId','','trim,strip_tags');
$processId = input('nowProcessId','','trim,strip_tags');
$NodeList = input('NodeList/a','','trim,strip_tags');
if(empty($sceneId) ||empty($processId)){
return returnAjax(0,'节点信息获取失败，请重新点击保存');
}
if(is_array($NodeList) &&count($NodeList)){
foreach ($NodeList as $nkey =>$nval) {
if ($nval['isDelete'] == 1) {
if($nval["id"] >0){
$tfnres = Db::name('tel_flow_node')->where('id',$nval["id"])->delete();
$tcresult = Db::name('tel_corpus')->where(array('src_id'=>$nval["id"],'src_type'=>0))->delete();
$tfbresult = Db::name('tel_flow_branch')->where('flow_id',$nval["id"])->delete();
Db::name('tel_label')->where(array('flow_id'=>$nval["id"],'type'=>1))->delete();
}
}
else{
$flow = array();
$flow['scenarios_id'] = $sceneId;
$flow['scen_node_id'] = $processId;
if($nval["pid"] != ''){
$flow['pid'] = $nval["pid"];
}
if(isset($nval["top"]) &&isset($nval["left"])){
$flow['position'] = $nval["top"].",".$nval["left"];
}
if($nval["id"] >0){
$result = Db::name('tel_flow_node')->where('id',$nval["id"])->update($flow);
}
if($nval["type"] == 'Menu'&&count($nval["thumb"]) &&$nval["id"] >0){
foreach ($nval["thumb"] as $tkey =>$tval) {
$flowbr = array();
$flowbr['flow_id'] = $nval["id"] ;
$flowbr['name'] = $tval["name"];
if($tval["nextNodeId"]){
$flowbr['next_flow_id'] = $tval["nextNodeId"];
}else{
$flowbr['next_flow_id'] = null;
}
if($tval["id"] >0){
$res = Db::name('tel_flow_branch')->where('id',$tval["id"])->update($flowbr);
}
}
}
}
}
}
if(empty($NodeList)){
return returnAjax(0,'编辑成功');
}
if(count($NodeList)){
return returnAjax(0,'编辑成功');
}else{
return returnAjax(1,'编辑失败');
}
}
public function backSingle()
{
$sceneId = input('sceneId','','trim,strip_tags');
$processId = input('nowProcessId','','trim,strip_tags');
$fnodeId = input('nodeId','','trim,strip_tags');
$tfnw = array();
$tfnw['scen_node_id'] = $processId;
$tfnw['type'] = [['=',0],['=',1],"or"];
$tfnw['id'] = $fnodeId;
$result = Db::name('tel_flow_node')
->field('id,scen_node_id,name,break,type,position,pid,action,action_id,scenarios_id')
->where($tfnw)->find();
$is_variable = Db::name('tel_scenarios')->where('id',$result['scenarios_id'])->value('is_variable');
$result['is_variable'] =$is_variable;
if($result['position']){
$position = explode(",",$result['position']);
if(count($position) >1){
$result['top'] = $position[0];
$result['left'] = $position[1];
}else if(count($position) == 1){
$result['top'] = $position[0];
$result['left'] = 0;
}else{
$result['top'] = 0;
$result['left'] = 0;
}
}
$res = Db::name('tel_corpus')->field('id,content,audio')->where(array('src_id'=>$result['id'],'src_type'=>0))->find();
if($res){
$result['content'] = $res["content"];
$result['tc_id'] = $res["id"];
if(!empty($res["audio"])){
if(!file_exists(ROOT_PATH.$res["audio"])){
$result['audio'] = 0;
}else{
$result['audio'] = $res["audio"];
}
}else{
$result['audio'] = 0;
}
}
$result['key'] ="node".$result['id'];
if($result['type'] == 1){
$result['type'] ="WorkTime";
if($result['action'] == 2){
$ress = Db::name('tel_scenarios_node')->field('scenarios_id,name')->where('id',$result['action_id'])->find();
$result['next_id'] = $result['action_id'];
$result['next_name'] = $ress["name"];
}else{
switch ($result['action']) {
case 4:
$result['next_name'] = "挂机";
break;
case 1:
$result['next_name'] = "下一场景节点";
break;
case 3:
$result['next_name'] = "返回当前场景节点中的话术";
break;
case 0:
$result['next_name'] = "等待用户响应";
break;
default:
$result['next_name'] = "指定场景节点";
}
}
}else{
$result['type'] ="Menu";
}
$where = array();
$where['flow_id'] = $result['id'];
$where['is_select'] = 1;
$return = Db::name('tel_flow_branch')->where($where)->order("id asc")->select();
foreach ($return as $kk =>$val) {
$return[$kk]['nextNode'] = "node-".$val['next_flow_id'];
}
$result['data']['choices'] = $return;
return returnAjax(0,'获取数据成功',$result);
}
public function defaultMt()
{
$sceneId = input('sceneId','','trim,strip_tags');
$processId = input('processId','','trim,strip_tags');
$typeList = [2,3,4,5,6];
$where = array();
$where['scenarios_id'] = 0;
$result = Db::name('tel_knowledge')
->field('id,name,type,keyword,label')
->where($where)
->where('type','in',$typeList)
->select();
if(count($result)){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'暂时没有数据');
}
}
public function getsflowInfo()
{
$noteId = input('id','','trim,strip_tags');
$result = Db::name('tel_flow_node')->field('name')->where("id",$noteId)->find();
$corpus = Db::name('tel_corpus')->field('id,content,audio')->where(array("src_id"=>$noteId,'src_type'=>0))->select();
$result['content'] = $corpus;
if($result){
return returnAjax(0,'有数据了',$result);
}else{
return returnAjax(1,'暂时没有数据',$result);
}
}
public function addNodeSound(){
$data = array();
if(!empty($_FILES['update-audio-file']['tmp_name'])){
$tmp_file = $_FILES ['update-audio-file'] ['tmp_name'];
if($tmp_file){
$file_types = explode ( ".",$_FILES ['update-audio-file'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
if (strtolower ( $file_type ) != "wav")
{
$this->error ( '不是wav文件，只能上传wav文件，重新上传');
}
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr.'/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".strtolower($file_type);
if (!copy ( $tmp_file,$savePath .$file_name ))
{
$this->error ( '上传失败');
}
$path = $savePath .$file_name;
$path = ltrim($path,".");
$data['audio'] = $path;
}
}
$sid = input('sid','','trim,strip_tags');
$result = Db::name('tel_corpus')->where('id',$sid)->update($data);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if ($result >= 0){
return returnAjax(0,'成功',$data['audio']);
}
else{
return returnAjax(1,'失败',$result);
}
}
public function exportexcel(){
$sceneId = input('sceneId','','trim,strip_tags');
$chaos_num = input('chaos_num','','trim,strip_tags');
$scenarios = Db::name('tel_scenarios')->where('id',$sceneId)->find();
if (!$scenarios) {
return returnAjax(1,'暂时没有数据11');
}
$scenarios["auditing"] = 0;
$result = Db::name('tel_scenarios_node')->where('scenarios_id',$sceneId)->select();
if (is_array($result) &&count($result) <0) {
return returnAjax(1,'暂时没有数据');
}
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$attitude = 5;
$RedisConnect->set($key,$attitude);
$obpe = new \PHPExcel();
$obpe_pro = $obpe->getProperties();
$obpe_pro->setCreator('midoks')
->setLastModifiedBy(date('Y-m-d H:i:s',time()))
->setTitle('data')
->setSubject('beizhu')
->setDescription('miaoshu')
->setKeywords('keyword')
->setCategory('catagory');
$obpe->setactivesheetindex(0);
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$PHPSheet = $obpe->getactivesheet();
$i = 0;
foreach($scenarios as $key=>$val){
$PHPSheet->setCellValue($letter[$i]."1",$key);
$PHPSheet->setCellValue($letter[$i]."2",$val);
$i = $i+1;
}
$obpe->createSheet();
$obpe->setactivesheetindex(1);
$PHPSheet = $obpe->getactivesheet();
$k = 0;
foreach($result as $kk=>$vv){
$j = 0;
foreach ($vv as $key2 =>$val2) {
if($k == 0){
$PHPSheet->setCellValue($letter[$j].($k+1),$key2);
}
$PHPSheet->setCellValue($letter[$j].($k+2),$val2);
$j = $j+1;
}
$k = $k+1;
}
$flowList = Db::name('tel_flow_node')->where('scenarios_id',$sceneId)->order("pid asc")->select();
$corpus = array();
$branch = array();
$labelarr = array();
$savePath = './uploads/download/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$pathList = array();
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$f = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(2);
$PHPSheet = $obpe->getactivesheet();
foreach ($flowList as $fbkey =>$fbval) {
$res = Db::name('tel_corpus')->where(array("src_id"=>$fbval["id"],'src_type'=>0))->find();
if($res){
array_push($corpus,$res);
}
$fbList = Db::name('tel_flow_branch')->where('flow_id',$fbval['id'])->order("id asc")->select();
if($fbList){
foreach ($fbList as $fbkey =>$fbvalue) {
if($fbvalue){
array_push($branch,$fbvalue);
}
}
}
$a = 0;
foreach ($fbval as $key3 =>$val3) {
if($f == 0){
$PHPSheet->setCellValue($letter[$a].($f+1),$key3);
}
$PHPSheet->setCellValue($letter[$a].($f+2),$val3);
$a = $a+1;
}
$f = $f+1;
if($fbval["type"] == 0){
$label = Db::name('tel_label')->where(array('flow_id'=>$fbval["id"],'type'=>1))->find();
if($label){
array_push($labelarr,$label);
}
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$fc = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(3);
$PHPSheet = $obpe->getactivesheet();
foreach ($corpus as $ckey =>$cval) {
$ac = 0;
foreach ($cval as $key4 =>$val4) {
if($fc == 0){
$PHPSheet->setCellValue($letter[$ac].($fc+1),$key4);
}
$PHPSheet->setCellValue($letter[$ac].($fc+2),$val4);
if($key4 == 'audio'&&$val4){
$source = ltrim($val4,'/');
if(file_exists($source)){
array_push($pathList,$source);
}
}
$ac = $ac+1;
}
$fc = $fc+1;
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$lb = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(4);
$PHPSheet = $obpe->getactivesheet();
foreach ($labelarr as $lbkey =>$lbval) {
$abc = 0;
foreach ($lbval as $keyb =>$valb) {
if($lb == 0){
$PHPSheet->setCellValue($letter[$abc].($lb+1),$keyb);
}
$PHPSheet->setCellValue($letter[$abc].($lb+2),$valb);
$abc = $abc+1;
}
$lb = $lb+1;
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$fb = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(5);
$PHPSheet = $obpe->getactivesheet();
foreach ($branch as $bkey =>$bval) {
$bc = 0;
foreach ($bval as $key5 =>$val5) {
if($fb == 0){
$PHPSheet->setCellValue($letter[$bc].($fb+1),$key5);
}
$PHPSheet->setCellValue($letter[$bc].($fb+2),$val5);
$bc = $bc+1;
}
$fb = $fb+1;
}
$knresult = Db::name('tel_knowledge')->where('scenarios_id',$sceneId)->select();
$knowlist = array();
$knlglabel = array();
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$knl = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(6);
$PHPSheet = $obpe->getactivesheet();
foreach ($knresult as $knkey =>$knval) {
$na = 0;
foreach ($knval as $key6 =>$val6) {
if($knl == 0){
$PHPSheet->setCellValue($letter[$na].($knl+1),$key6);
}
$PHPSheet->setCellValue($letter[$na].($knl+2),$val6);
$na = $na+1;
}
$knl = $knl+1;
$res = Db::name('tel_corpus')->where(array("src_id"=>$knval["id"],'src_type'=>1))->select();
if($res){
foreach ($res as $rkey =>$rval) {
array_push($knowlist,$rval);
}
}
$klabel = Db::name('tel_label')->where(array('flow_id'=>$knval["id"],'type'=>2))->find();
if($klabel){
array_push($knlglabel,$klabel);
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$knc = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(7);
$PHPSheet = $obpe->getactivesheet();
foreach ($knowlist as $kskey =>$ksval) {
$ns = 0;
foreach ($ksval as $key7 =>$val7) {
if($knc == 0){
$PHPSheet->setCellValue($letter[$ns].($knc+1),$key7);
}
$PHPSheet->setCellValue($letter[$ns].($knc+2),$val7);
if($key7 == 'audio'&&$val7){
$source = ltrim($val7,'/');
if(file_exists($source)){
array_push($pathList,$source);
}
}
$ns = $ns +1;
}
$knc = $knc+1;
}
$tirres = Db::name('tel_intention_rule')->where('scenarios_id',$sceneId)->select();
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$rl = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(8);
$PHPSheet = $obpe->getactivesheet();
if($tirres){
foreach ($tirres as $keky =>$valrl) {
$rlm = 0;
foreach ($valrl as $key8 =>$val8) {
if($rl == 0){
$PHPSheet->setCellValue($letter[$rlm].($rl+1),$key8);
}
$PHPSheet->setCellValue($letter[$rlm].($rl+2),$val8);
$rlm = $rlm +1;
}
$rl = $rl+1;
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$knlb = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(9);
$PHPSheet = $obpe->getactivesheet();
foreach ($knlglabel as $klkey =>$klval) {
$abcd = 0;
foreach ($klval as $keykn =>$valkn) {
if($knlb == 0){
$PHPSheet->setCellValue($letter[$abcd].($knlb+1),$keykn);
}
$PHPSheet->setCellValue($letter[$abcd].($knlb+2),$valkn);
$abcd = $abcd+1;
}
$knlb = $knlb+1;
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$scenarios_config = Db::name('tel_scenarios_config')->where('scenarios_id',$sceneId)->select();
$cfig = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(10);
$PHPSheet = $obpe->getactivesheet();
if($scenarios_config){
foreach($scenarios_config as $keyc10 =>$voc10){
$cios = 0;
foreach($voc10 as $keyc =>$voc){
if($cfig == 0){
$PHPSheet->setCellValue($letter[$cios].($cfig+1),$keyc);
}
$PHPSheet->setCellValue($letter[$cios].($cfig+2),$voc);
$cios = $cios +1;
}
$cfig = $cfig+1;
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$labellist = Db::name('tel_label')->where([
'scenarios_id'=>$sceneId,
'type'=>0
])->select();
$vai = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(11);
$PHPSheet = $obpe->getactivesheet();
if($labellist){
foreach($labellist as $keyc11 =>$voc11){
$vaes = 0;
foreach($voc11 as $keyt =>$vot){
if($vai == 0){
$PHPSheet->setCellValue($letter[$vaes].($vai+1),$keyt);
}
$PHPSheet->setCellValue($letter[$vaes].($vai+2),$vot);
$vaes = $vaes +1;
}
$vai = $vai+1;
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +8;
$RedisConnect->set($key,$attitude);
$rule_template = Db::name('tel_intention_rule_template')->where('scenarios_id',$sceneId)->select();
$rul = 0 ;
$obpe->createSheet();
$obpe->setactivesheetindex(12);
$PHPSheet = $obpe->getactivesheet();
if($rule_template){
foreach($rule_template as $key12 =>$vo12){
$rultmp = 0 ;
foreach($vo12 as $keyz =>$voz){
if($rul == 0){
$PHPSheet->setCellValue($letter[$rultmp].($rul+1),$keyz);
}
$PHPSheet->setCellValue($letter[$rultmp].($rul+2),$voz);
$rultmp = $rultmp +1;
}
$rul = $rul+1;
}
}
$obpe->createSheet();
$obpe->setactivesheetindex(13);
$PHPSheet = $obpe->getactivesheet();
$PHPSheet->setCellValue('A0','版本号');
$PHPSheet->setCellValue('A1','6.1.7');
if($scenarios['is_variable']==1){
$audio_variables = Db::name('audio_variable')->where(['scenarios_id'=>$sceneId])->select();
$vai = 0;
$obpe->createSheet();
$obpe->setactivesheetindex(14);
$PHPSheet = $obpe->getactivesheet();
if($audio_variables){
foreach($audio_variables as $key13=>$voc11){
$vaes = 0;
foreach($voc11 as $keyt =>$vot){
if($vai == 0){
$PHPSheet->setCellValue($letter[$vaes].($vai+1),$keyt);
}
$PHPSheet->setCellValue($letter[$vaes].($vai+2),$vot);
$vaes = $vaes +1;
}
$vai = $vai+1;
}
}
}
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xls';
$PHPWriter = \PHPExcel_IOFactory::createWriter($obpe,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.time().'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
array_push($pathList,$execlpath);
$file_template = './uploads/download/kong.zip';
$downname = $scenarios["name"]."-".date ( 'd').'.zip';
$file_name = './uploads/download/'.uniqid().'.zip';
$result = copy($file_template,$file_name);
$zip = new \ZipArchive;
if ($zip->open($file_name,\ZipArchive::CREATE) === TRUE) {
foreach ($pathList as $keydl =>$valuedl) {
$temp = explode("/",$valuedl);
$long = count($temp);
$dest = $temp[$long -1];
$zip->addFromString($dest ,file_get_contents($valuedl));
}
}
$zip->close();
$key = 'task_'.$chaos_num .'_count';
$RedisConnect->del($key);
$data = [];
$data['file_name'] = $file_name;
$data['downname'] = $downname;
return returnAjax(1,'',$data);
}
public function download(){
$file_name = input('file_name','','trim,strip_tags');
$downname = input('downname','','trim,strip_tags');
$fp = fopen($file_name,"r");
$file_size = filesize($file_name);
header("Content-type: application/octet-stream");
header("Accept-Ranges: bytes");
header("Accept-Length:".$file_size);
header("Content-Disposition: attachment");
header("Content-Disposition: attachment; filename=$downname");
header("Content-Transfer-Encoding: binary");
header('Content-Length: '.$file_size);
@readfile($file_name);
$buffer = 1024;
$file_count = 0;
while (!feof($fp) &&$file_count <$file_size) {
$file_con = fread($fp,$buffer);
$file_count += $buffer;
echo $file_con;
}
fclose($fp);
if($file_count >= $file_size){
unlink($file_name);
}
}
public function leadingZip()
{
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES['excel']['tmp_name'];
}else{
return returnAjax(1,'上传失败,请下载模板，填好再选择文件上传。');
}
$file_types = explode ( ".",$_FILES ['excel'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
$savePath = './uploads/upload/';
if (!is_dir($savePath)){
mkdir($savePath,0777,true);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy ( $tmp_file,$savePath .$file_name ))
{
return returnAjax(1,'上传失败');
}
$filename = $savePath .$file_name;
$linkpath = $savePath .$file_name;
if(!file_exists($filename)){
die("文件 $filename 不存在！");
}
$filename = iconv("utf-8","gb2312",$filename);
$path = iconv("utf-8","gb2312",$savePath);
$resource = zip_open($filename);
$flag = false;
$i = 1;
while ($dir_resource = zip_read($resource)) {
if (zip_entry_open($resource,$dir_resource)) {
$file_name = $path.zip_entry_name($dir_resource);
$file_path = substr($file_name,0,strrpos($file_name,"/"));
$realname = substr($file_name,strrpos($file_name,"/"));
$exname = substr($file_name,strrpos($file_name,"."));
if($exname == '.wav'){
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr;
if (!is_dir($savePath)){
mkdir($savePath,0777,true);
}
$file_name = $savePath.$realname;
}else if($exname == '.xls'||$exname == '.xlsx'){
$datestr = date ( 'Ymd');
$savePath = './uploads/Excel';
if (!is_dir($savePath)){
mkdir($savePath,0777,true);
}
$file_name = $savePath.$realname;
}
if(!is_dir($file_path)){
mkdir($file_path,0777,true);
}
if(!is_dir($file_name)){
$file_size = zip_entry_filesize($dir_resource);
if($file_size<(1024*1024*30)){
$file_content = zip_entry_read($dir_resource,$file_size);
if(is_file($file_name) != true){
file_put_contents($file_name,$file_content);
}
if($exname == '.xls'||$exname == '.xlsx'){
$chaos_num = input('chaos_num','','trim,strip_tags');
$flag = $this->new_leading_alter($file_name,$chaos_num);
}
}
else{
echo "<p> ".$i++." 此文件已被跳过，原因：文件过大， -> ".iconv("gb2312","utf-8",$file_name)." </p>";
}
}
zip_entry_close($dir_resource);
}
}
zip_close($resource);
unlink($linkpath);
if($flag){
return returnAjax(0,'话术导入成功！');
}else{
return returnAjax(1,'话术导入失败！');
}
}
public function leading_zip_5_5()
{
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES['excel']['tmp_name'];
}else{
return returnAjax(1,'上传失败,请下载模板，填好再选择文件上传。');
}
$file_types = explode ( ".",$_FILES ['excel'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
$savePath = './uploads/upload/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy ( $tmp_file,$savePath .$file_name ))
{
return returnAjax(1,'上传失败');
}
$filename = $savePath .$file_name;
$linkpath = $savePath .$file_name;
if(!file_exists($filename)){
die("文件 $filename 不存在！");
}
$starttime = explode(' ',microtime());
$filename = iconv("utf-8","gb2312",$filename);
$path = iconv("utf-8","gb2312",$savePath);
$resource = zip_open($filename);
$i = 1;
while ($dir_resource = zip_read($resource)) {
if (zip_entry_open($resource,$dir_resource)) {
$file_name = $path.zip_entry_name($dir_resource);
$file_path = substr($file_name,0,strrpos($file_name,"/"));
$realname = substr($file_name,strrpos($file_name,"/"));
$exname = substr($file_name,strrpos($file_name,"."));
if($exname == '.wav'){
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr;
if (!is_dir($savePath)){
mkdir($savePath);
}
$file_name = $savePath.$realname;
}else if($exname == '.xls'||$exname == '.xlsx'){
$datestr = date ( 'Ymd');
$savePath = './uploads/Excel';
if (!is_dir($savePath)){
mkdir($savePath);
}
$file_name = $savePath.$realname;
}
if(!is_dir($file_path)){
mkdir($file_path,0777,true);
}
if(!is_dir($file_name)){
$file_size = zip_entry_filesize($dir_resource);
if($file_size<(1024*1024*30)){
$file_content = zip_entry_read($dir_resource,$file_size);
file_put_contents($file_name,$file_content);
if($exname == '.xls'||$exname == '.xlsx'){
$flag = $this->leadingAlter($file_name);
}
}
else{
echo "<p> ".$i++." 此文件已被跳过，原因：文件过大， -> ".iconv("gb2312","utf-8",$file_name)." </p>";
}
}
zip_entry_close($dir_resource);
}
}
zip_close($resource);
unlink($linkpath);
if($flag){
return returnAjax(0,'成功了');
}else{
return returnAjax(1,'失败了');
}
}
public function insert_intention_model($scenarios_id){
if(empty($scenarios_id)){
return false;
}
$data = [
'scenarios_id'=>$scenarios_id,
'name'=>'默认模板',
'description'=>'导入话术添加默认意向等级模板',
'status'=>1
];
$intention_modelId = Db::name('tel_intention_rule_template')->insertGetId($data);
$a = [0 =>["key"=>"call_duration","value"=>41,"type"=>"int","op"=>">="],1=>['key'=>'speak_count','value'=>2,'type'=>'int','op'=>'>=']];
$b = [0 =>["key"=>"call_duration","value"=>25,"type"=>"int","op"=>">="],1=>['key'=>'speak_count','value'=>1,'type'=>'int','op'=>'>=']];
$c = [0 =>["key"=>"call_duration","value"=>10,"type"=>"int","op"=>">="],1=>['key'=>'speak_count','value'=>1,'type'=>'int','op'=>'>=']];
$d = [0 =>["key"=>"call_duration","value"=>1,"type"=>"int","op"=>">="]];
$e = [0 =>["key"=>"call_status","value"=>array(3,6,7,8,9,10,11),"type"=>"array","op"=>"="]];
$f = [0 =>["key"=>"call_status","value"=>array(4,5),"type"=>"array","op"=>"="]];
$data_rule[]=[
'scenarios_id'=>$scenarios_id,
'template_id'=>$intention_modelId,
'name'=>'  ',
'type'=>0,
'level'=>6,
'rule'=>serialize($a),
'status'=>0,
'create_time'=>time(),
'update_time'=>time()
];
$data_rule[]=[
'scenarios_id'=>$scenarios_id,
'template_id'=>$intention_modelId,
'name'=>'  ',
'type'=>0,
'level'=>5,
'rule'=>serialize($b),
'status'=>0,
'create_time'=>time(),
'update_time'=>time()
];
$data_rule[]=[
'scenarios_id'=>$scenarios_id,
'template_id'=>$intention_modelId,
'name'=>'  ',
'type'=>0,
'level'=>4,
'rule'=>serialize($c),
'status'=>0,
'create_time'=>time(),
'update_time'=>time()
];
$data_rule[]=[
'scenarios_id'=>$scenarios_id,
'template_id'=>$intention_modelId,
'name'=>'  ',
'type'=>0,
'level'=>3,
'rule'=>serialize($d),
'status'=>0,
'create_time'=>time(),
'update_time'=>time()
];
$data_rule[]=[
'scenarios_id'=>$scenarios_id,
'template_id'=>$intention_modelId,
'name'=>'  ',
'type'=>0,
'level'=>2,
'rule'=>serialize($e),
'status'=>0,
'create_time'=>time(),
'update_time'=>time()
];
$data_rule[]=[
'scenarios_id'=>$scenarios_id,
'template_id'=>$intention_modelId,
'name'=>'  ',
'type'=>0,
'level'=>1,
'rule'=>serialize($f),
'status'=>0,
'create_time'=>time(),
'update_time'=>time()
];
$result = Db::name('tel_intention_rule')->insertAll($data_rule);
if(empty($result)){
return false;
}else{
return true;
}
}
public function set_default_scenarios_config($scenarios_id)
{
if(empty($scenarios_id)){
return false;
}
$scenarios_config = [
'scenarios_id'=>$scenarios_id,
'pause_play_ms'=>0,
'min_speak_ms'=>100,
'max_speak_ms'=>10000,
'volume'=>80,
'filter_level'=>0.0
];
$result = Db::name('tel_scenarios_config')
->insert($scenarios_config);
if(!empty($result)){
return true;
}
return false;
}
public function leadingAlter($file_name)
{
$foo = new \PHPExcel();
$extension = strtolower(pathinfo($file_name,PATHINFO_EXTENSION) );
if($extension == 'xlsx') {
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}else{
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$objPHPExcel = $objReader->load($file_name,$encode = 'utf-8');
$excel_tel_scenarios = $objPHPExcel->getsheet(0)->toArray();
$excel_tel_scenarios_node = $objPHPExcel->getsheet(1)->toArray();
$excel_tel_flow_node = $objPHPExcel->getsheet(2)->toArray();
$excel_flow_node_tel_corpus = $objPHPExcel->getsheet(3)->toArray();
$excel_flow_node_tel_label = $objPHPExcel->getsheet(4)->toArray();
$excel_tel_flow_branch = $objPHPExcel->getsheet(5)->toArray();
$excel_tel_knowledge = $objPHPExcel->getsheet(6)->toArray();
$excel_tel_knowledge_tel_corpus = $objPHPExcel->getsheet(7)->toArray();
$excel_tel_intention_rule = $objPHPExcel->getsheet(8)->toArray();
$excel_tel_knowledge_tel_label = $objPHPExcel->getsheet(9)->toArray();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if(count($excel_tel_scenarios) == 2){
$insert_tel_scenarios = [
'name'=>$excel_tel_scenarios[1][1],
'member_id'=>$uid,
'type'=>$excel_tel_scenarios[1][3],
'is_tpl'=>$excel_tel_scenarios[1][4],
'status'=>$excel_tel_scenarios[1][5],
'break'=>$excel_tel_scenarios[1][6],
'auditing'=>$excel_tel_scenarios[1][7],
'remark'=>$excel_tel_scenarios[1][8],
'update_time'=>time()
];
$new_scenarions_id = Db::name('tel_scenarios')->insertGetId($insert_tel_scenarios);
if(empty($new_scenarions_id)){
return returnAjax(1,'导入失败');
}
}else{
return returnAjax(1,'导入失败');
}
$new_tel_scenarios_node = [];
foreach($excel_tel_scenarios_node as $key=>$value){
if($key == 0){
continue;
}
$tel_scenarios_node_id = $value[0];
$insert_tel_scenarios_node = [
'scenarios_id'=>$new_scenarions_id,
'name'=>$value[2],
'sort'=>$value[3],
'type'=>$value[4]
];
$new_tel_scenarios_node_id = Db::name('tel_scenarios_node')->insertGetId($insert_tel_scenarios_node);
$new_tel_scenarios_node[$tel_scenarios_node_id] = $new_tel_scenarios_node_id;
}
$new_tel_flow_node = [];
foreach($excel_tel_flow_node as $key=>$value){
if($key == 0 ||isset($new_tel_scenarios_node[$value[2]]) === false){
continue;
}
$tel_flow_node_id = $value[0];
$tel_flow_node = [
'scenarios_id'=>$new_scenarions_id,
'scen_node_id'=>$new_tel_scenarios_node[$value[2]],
'name'=>$value[3],
'pid'=>$value[4] &&isset($new_tel_flow_node[$value[4]]) === true?$new_tel_flow_node[$value[4]]:0,
'break'=>$value[5],
'type'=>$value[6],
'action'=>$value[7],
'action_id'=>$value[8] &&isset($new_tel_scenarios_node[$value[8]])?$new_tel_scenarios_node[$value[8]]:0,
'flow_label'=>$value[9],
'position'=>$value[10],
'last_time'=>$value[11],
'pause_time'=>$value[12],
'no_speak_knowledge_id'=>$value[13],
'sms_template_id'=>$value[14],
'bridge'=>$value[15],
];
$new_tel_flow_node[$tel_flow_node_id] = Db::name('tel_flow_node')->insertGetId($tel_flow_node);
}
$new_tel_corpus = [];
foreach($excel_flow_node_tel_corpus as $key=>$value){
if($key == 0 ||isset($new_tel_flow_node[$value[3]]) === false){
continue;
}
$tel_corpus_id = $value[0];
$insert_tel_corpus = [
'scenarios_id'=>$new_scenarions_id,
'src_type'=>$value[2],
'src_id'=>$new_tel_flow_node[$value[3]],
'content'=>$value[4],
'audio'=>$value[5]
];
if(!empty(trim($value[5]))){
$realname = substr(trim($value[5]),strrpos(trim($value[5]),"/"));
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr;
if (!is_dir($savePath)){
mkdir($savePath);
}
$file_name = $savePath.$realname;
$file_name = ltrim($file_name,".");
$insert_tel_corpus['audio'] = $file_name;
}
$new_tel_corpus[$tel_corpus_id] = Db::name('tel_corpus')->insertGetId($insert_tel_corpus);
}
$new_tel_label = [];
foreach($excel_flow_node_tel_label as $key=>$value){
if($key == 0 ||isset($new_tel_flow_node[$value[4]]) === false){
continue;
}
$tel_label_id = $value[0];
$insert_tel_label = [
'member_id'=>$uid,
'label'=>$value[2],
'keyword'=>$value[3],
'flow_id'=>$new_tel_flow_node[$value[4]],
'type'=>$value[5],
'scenarios_id'=>$new_scenarions_id
];
$new_tel_label[$tel_label_id] = Db::name('tel_label')->insertGetId($insert_tel_label);
}
\think\Log::record(json_encode($excel_tel_flow_branch));
\think\Log::record(json_encode($new_tel_flow_node));
$new_tel_flow_branch = [];
foreach($excel_tel_flow_branch as $key=>$value){
if($key == 0 ||empty($value[5]) &&$value[6] == 1 ||isset($new_tel_flow_node[$value[5]]) === false &&$value[6] == 1){
continue;
}
$tel_flow_branch_id = $value[0];
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'keyword'=>trim($value[3]) == 'null'||trim($value[3]) == null?'':trim($value[3]),
'keyword_py'=>$value[4],
'next_flow_id'=>$value[6] == 1?$new_tel_flow_node[$value[5]]:'',
'is_select'=>$value[6],
'type'=>$value[7]
];
$new_tel_flow_branch[$tel_flow_branch_id] = Db::name('tel_flow_branch')->insertGetId($insert_tel_flow_branch);
}
$new_tel_knowledge = [];
foreach($excel_tel_knowledge as $key=>$value){
if($key == 0){
continue;
}
$tel_knowledge_id = $value[0];
$insert_tel_knowledge = [
'scenarios_id'=>$new_scenarions_id,
'name'=>$value[2],
'type'=>$value[3],
'keyword'=>trim($value[4]) == 'null'||trim($value[4]) == null?'':$value[4],
'keyword_py'=>$value[5],
'action'=>$value[6],
'action_id'=>$value[7]?$new_tel_scenarios_node[$value[7]]:0,
'intention'=>$value[8],
'create_time'=>time(),
'update_time'=>time(),
'pause_time'=>$value[11],
'label'=>$value[12],
'is_default'=>$value[13],
'sms_template_id'=>0,
'bridge'=>$value[15],
];
$new_tel_knowledge[$tel_knowledge_id] = Db::name('tel_knowledge')->insertGetId($insert_tel_knowledge);
}
foreach($excel_tel_knowledge_tel_corpus as $key=>$value){
if($key == 0){
continue;
}
$tel_corpus_id = $value[0];
$insert_tel_corpus = [
'scenarios_id'=>$new_scenarions_id,
'src_type'=>$value[2],
'src_id'=>$new_tel_knowledge[$value[3]],
'content'=>$value[4],
'audio'=>$value[5]
];
if(!empty(trim($value[5]))){
$realname = substr(trim($value[5]),strrpos(trim($value[5]),"/"));
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr;
if (!is_dir($savePath)){
mkdir($savePath);
}
$file_name = $savePath.$realname;
$file_name = ltrim($file_name,".");
$insert_tel_corpus['audio'] = $file_name;
}
$new_tel_corpus[$tel_corpus_id] = Db::name('tel_corpus')->insertGetId($insert_tel_corpus);
}
$this->insert_intention_model($new_scenarions_id);
foreach($excel_tel_knowledge_tel_label as $key=>$value){
if($key == 0){
continue;
}
$tel_label_id = $value[0];
$insert_tel_label = [
'member_id'=>$uid,
'label'=>$value[2],
'keyword'=>$value[3],
'flow_id'=>$new_tel_knowledge[$value[4]],
'type'=>$value[5],
'scenarios_id'=>$new_scenarions_id
];
$tel_label[$tel_label_id] = Db::name('tel_label')->insertGetId($insert_tel_label);
}
$RedisApiData = new RedisApiData();
$RedisApiData->repair_scenarios($new_scenarions_id);
return true;
}
public function  scenarios_effectTmp(){
$chaos_num = input('chaos_num','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$num_count = $RedisConnect->get($key);
$data = [];
$data['num_count'] = $num_count;
$data['chaos_num'] = $chaos_num;
$data['key'] = $key;
if($num_count){
return returnAjax(1,'',$data);
}
}
public function new_leading_alter($file_name,$chaos_num)
{
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$foo = new \PHPExcel();
$extension = strtolower(pathinfo($file_name,PATHINFO_EXTENSION) );
if($extension == 'xlsx') {
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}else{
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$objPHPExcel = $objReader->load($file_name,$encode = 'utf-8');
$excel_tel_scenarios = $objPHPExcel->getsheet(0)->toArray();
$excel_tel_scenarios_node = $objPHPExcel->getsheet(1)->toArray();
$excel_tel_flow_node = $objPHPExcel->getsheet(2)->toArray();
$excel_flow_node_tel_corpus = $objPHPExcel->getsheet(3)->toArray();
$excel_flow_node_tel_label = $objPHPExcel->getsheet(4)->toArray();
$excel_tel_flow_branch = $objPHPExcel->getsheet(5)->toArray();
$excel_tel_knowledge = $objPHPExcel->getsheet(6)->toArray();
$excel_tel_knowledge_tel_corpus = $objPHPExcel->getsheet(7)->toArray();
$excel_tel_intention_rule = $objPHPExcel->getsheet(8)->toArray();
$excel_tel_knowledge_tel_label = $objPHPExcel->getsheet(9)->toArray();
$excel_tel_scenarios_config = $objPHPExcel->getsheet(10)->toArray();
$excel_semantics_label = $objPHPExcel->getsheet(11)->toArray();
$excel_tel_intention_rule_template = $objPHPExcel->getsheet(12)->toArray();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$RedisConnect = RedisConnect::get_redis_connect();
Db::startTrans();
try{
if(count($excel_tel_scenarios) == 2){
$insert_tel_scenarios = [
'name'=>$excel_tel_scenarios[1][1],
'member_id'=>$uid,
'type'=>$excel_tel_scenarios[1][3],
'is_tpl'=>$excel_tel_scenarios[1][4],
'status'=>$excel_tel_scenarios[1][5],
'break'=>$excel_tel_scenarios[1][6],
'auditing'=>$excel_tel_scenarios[1][7],
'remark'=>$excel_tel_scenarios[1][8],
'check_statu'=>isset($excel_tel_scenarios[1][10])?$excel_tel_scenarios[1][10] : 0,
'update_time'=>time(),
'is_variable'=>isset($excel_tel_scenarios[1][11])?$excel_tel_scenarios[1][11]:0,
];
$new_scenarions_id = Db::name('tel_scenarios')->insertGetId($insert_tel_scenarios);
if(empty($new_scenarions_id)){
return returnAjax(1,'导入失败');
}
if(isset($excel_tel_scenarios[1][11])&&$excel_tel_scenarios[1][11]==1){
$audio_variables = $objPHPExcel->getsheet(14)->toArray();
foreach($audio_variables as $key=>$audio_variable){
if($key == 0 ){
continue;
}
$data[$key]=[
'scenarios_id'=>$new_scenarions_id,
'variable_name'=>$audio_variable[2],
'annotation'=>$audio_variable[3],
'example'=>$audio_variable[4],
];
}
Db::name('audio_variable')->insertAll($data);
}
}else{
return returnAjax(1,'导入失败');
}
$key = 'task_'.$chaos_num .'_count';
$attitude = 50 +2;
$RedisConnect->set($key,$attitude);
$new_tel_scenarios_node = [];
foreach($excel_tel_scenarios_node as $key=>$value){
if($key == 0){
continue;
}
$tel_scenarios_node_id = $value[0];
$insert_tel_scenarios_node = [
'scenarios_id'=>$new_scenarions_id,
'name'=>$value[2],
'sort'=>$value[3],
'type'=>$value[4]
];
$new_tel_scenarios_node_id = Db::name('tel_scenarios_node')->insertGetId($insert_tel_scenarios_node);
$new_tel_scenarios_node[$tel_scenarios_node_id] = $new_tel_scenarios_node_id;
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
$new_tel_flow_node = [];
$new_tel_flow_node_pids = [];
$new_tel_flow_node_no_speak_knowledge_ids = [];
foreach($excel_tel_flow_node as $key=>$value){
if($key == 0 ||isset($new_tel_scenarios_node[$value[2]]) === false){
continue;
}
$set_id=0;
if(!empty($value[16])){
$where_s['pid']=$uid;
$where_s['id']=$value[16];
$where_s['role_id']=20;
$count = Db::name('admin')->where($where_s)->count('*');
if($count>0){
$set_id = $value[16];
}
}
$tel_flow_node_id = $value[0];
$tel_flow_node = [
'scenarios_id'=>$new_scenarions_id,
'scen_node_id'=>$new_tel_scenarios_node[$value[2]],
'name'=>$value[3],
'pid'=>$value[4],
'break'=>$value[5],
'type'=>$value[6],
'action'=>$value[7],
'action_id'=>$value[8] &&isset($new_tel_scenarios_node[$value[8]])?$new_tel_scenarios_node[$value[8]]:0,
'flow_label'=>$value[9],
'label_status'=>$value[10],
'position'=>$value[11],
'last_time'=>$value[12],
'pause_time'=>$value[13],
'no_speak_knowledge_id'=>'',
'sms_template_id'=>$value[15],
'bridge'=>$set_id,
'is_variable'=>isset($value[17])&&!empty($value[17])?$value[17]:0
];
$new_flow_node_id = Db::name('tel_flow_node')->insertGetId($tel_flow_node);
if(!empty($value[14])){
$new_tel_flow_node_no_speak_knowledge_ids[$new_flow_node_id] = $value[14];
}
$new_tel_flow_node[$tel_flow_node_id] = $new_flow_node_id;
$new_tel_flow_node_pids[$tel_flow_node_id] = $value[4];
usleep(100000);
}
\think\Log::record('$new_tel_flow_node_no_speak_knowledge_ids####'.json_encode($new_tel_flow_node_no_speak_knowledge_ids));
foreach($new_tel_flow_node_pids as $key=>$value){
if(isset($new_tel_flow_node[$value]) == true &&!empty($value)){
Db::name('tel_flow_node')->where('id',$new_tel_flow_node[$key])->update(['pid'=>$new_tel_flow_node[$value]]);
}else{
Db::name('tel_flow_node')->where('id',$new_tel_flow_node[$key])->update(['pid'=>0]);
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
$new_tel_corpus = [];
foreach($excel_flow_node_tel_corpus as $key=>$value){
if($key == 0 ||isset($new_tel_flow_node[$value[4]]) === false){
continue;
}
$tel_corpus_id = $value[0];
$insert_tel_corpus = [
'scenarios_id'=>$new_scenarions_id,
'src_type'=>$value[2],
'source'=>$value[3],
'src_id'=>$new_tel_flow_node[$value[4]],
'content'=>$value[5],
'audio'=>$value[6],
'file_name'=>$value[7],
'file_size'=>$value[8],
'is_variable'=>isset($value[9])?$value[9]:0
];
if(!empty(trim($value[6]))){
$realname = substr(trim($value[6]),strrpos(trim($value[6]),"/"));
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr;
if (!is_dir($savePath)){
mkdir($savePath);
}
$file_name = $savePath.$realname;
$file_name = ltrim($file_name,".");
$insert_tel_corpus['audio'] = $file_name;
}
$new_tel_corpus[$tel_corpus_id] = Db::name('tel_corpus')->insertGetId($insert_tel_corpus);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
$new_tel_label = [];
foreach($excel_flow_node_tel_label as $key=>$value){
if($key == 0 ||isset($new_tel_flow_node[$value[4]]) === false){
continue;
}
$tel_label_id = $value[0];
$insert_tel_label = [
'member_id'=>$uid,
'label'=>$value[2],
'keyword'=>$value[3],
'flow_id'=>$new_tel_flow_node[$value[4]],
'type'=>$value[5],
'scenarios_id'=>$new_scenarions_id,
'label_status'=>$value[7]
];
$new_tel_label[$tel_label_id] = Db::name('tel_label')->insertGetId($insert_tel_label);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
$new_tel_knowledge = [];
foreach($excel_tel_knowledge as $key=>$value){
$value_count = count($value);
if($key == 0){
continue;
}
$set_id=0;
if(!empty($value[16])){
$where_s['pid']=$uid;
$where_s['id']=$value[16];
$where_s['role_id']=20;
$count = Db::name('admin')->where($where_s)->count('*');
if($count>0){
$set_id = $value[16];
}
}
$tel_knowledge_id = $value[0];
if($value_count==17){
$insert_tel_knowledge = [
'scenarios_id'=>$new_scenarions_id,
'name'=>$value[2],
'type'=>$value[3],
'keyword'=>trim($value[4]) == 'null'||trim($value[4]) == null?'':trim($value[4]),
'keyword_py'=>$pinyin->sentence($value[4]),
'action'=>$value[6],
'action_id'=>$value[7] &&$value[6] == 2 &&isset($new_tel_scenarios_node[$value[7]])?$new_tel_scenarios_node[$value[7]]:0,
'intention'=>$value[8],
'create_time'=>time(),
'update_time'=>time(),
'pause_time'=>$value[11],
'label'=>$value[12],
'label_status'=>$value[13],
'is_default'=>$value[14],
'sms_template_id'=>0,
'bridge'=>$set_id,
'order_by'=>0,
'query_type'=>0,
'break'=>0
];
}else if($value_count==18){
$insert_tel_knowledge = [
'scenarios_id'=>$new_scenarions_id,
'name'=>$value[2],
'type'=>$value[3],
'keyword'=>trim($value[4]) == 'null'||trim($value[4]) == null?'':trim($value[4]),
'keyword_py'=>$pinyin->sentence($value[4]),
'action'=>$value[6],
'action_id'=>$value[7] &&$value[6] == 2&&isset($new_tel_scenarios_node[$value[7]])?$new_tel_scenarios_node[$value[7]]:0,
'intention'=>$value[8],
'create_time'=>time(),
'update_time'=>time(),
'pause_time'=>$value[11],
'label'=>$value[12],
'label_status'=>$value[13],
'is_default'=>$value[14],
'sms_template_id'=>0,
'bridge'=>$set_id,
'order_by'=>empty($value[17])?0:$value[17],
'query_type'=>0,
'break'=>0
];
}else if($value_count==20){
$insert_tel_knowledge = [
'scenarios_id'=>$new_scenarions_id,
'name'=>$value[2],
'type'=>$value[3],
'keyword'=>trim($value[4]) == 'null'||trim($value[4]) == null?'':trim($value[4]),
'keyword_py'=>$pinyin->sentence($value[4]),
'action'=>$value[6],
'action_id'=>$value[7] &&$value[6] == 2&&isset($new_tel_scenarios_node[$value[7]])?$new_tel_scenarios_node[$value[7]]:0,
'intention'=>$value[8],
'create_time'=>time(),
'update_time'=>time(),
'pause_time'=>$value[11],
'label'=>$value[12],
'label_status'=>$value[13],
'is_default'=>$value[14],
'sms_template_id'=>0,
'bridge'=>$set_id,
'order_by'=>empty($value[17])?0:$value[17],
'query_type'=>empty($value[18])?0:$value[18],
'break'=>empty($value[19])?0:$value[19]
];
}
$new_tel_knowledge[$tel_knowledge_id] = Db::name('tel_knowledge')->insertGetId($insert_tel_knowledge);
foreach($new_tel_flow_node_no_speak_knowledge_ids as $key=>$value){
if($tel_knowledge_id==$value){
Db::name('tel_flow_node')->where('id',$key)->update(['no_speak_knowledge_id'=>$new_tel_knowledge[$tel_knowledge_id]]);
}
}
}
$sheet_num = $objPHPExcel->getSheetCount();
if($sheet_num==13){
$new_tel_flow_branch = [];
foreach($excel_tel_flow_branch as $key=>$value){
$value_count = count($value);
$knowledge_keyword='';
$knowledge_keyword_py='';
if($value_count == 10){
$tel_knowledge = Db::name('tel_knowledge')->where(['type'=>$value[9],'scenarios_id'=>$new_scenarions_id])->find();
}
if($value_count == 9){
$tel_knowledge = Db::name('tel_knowledge')->where(['type'=>$value[8],'scenarios_id'=>$new_scenarions_id])->find();
}
if($value_count == 11){
$tel_knowledge = Db::name('tel_knowledge')->where(['type'=>$value[9],'scenarios_id'=>$new_scenarions_id])->find();
}
if($value_count == 12){
$tel_knowledge = Db::name('tel_knowledge')->where(['type'=>$value[9],'scenarios_id'=>$new_scenarions_id])->find();
}
if( isset($tel_knowledge) &&!empty($tel_knowledge) &&count($tel_knowledge)>0 ){
$knowledge_keyword=trim($tel_knowledge['keyword']);
$knowledge_keyword_py=trim($tel_knowledge['keyword_py']);
}
if($value_count == 10){
if($key == 0 ||empty($value[7]) &&$value[8] == 1 ||isset($new_tel_flow_node[$value[7]]) === false &&$value[8] == 1 ||isset($new_tel_flow_node[$value[1]]) == false){
continue;
}
}else if($value_count == 9){
if($key == 0 ||empty($value[6]) &&$value[7] == 1 ||isset($new_tel_flow_node[$value[6]]) === false &&$value[7] == 1 ||isset($new_tel_flow_node[$value[1]]) == false){
continue;
}
}else if($value_count == 11 ||$value_count == 12){
if($key == 0 ||empty($value[8]) &&$value[9] == 1 ||isset($new_tel_flow_node[$value[8]]) === false &&$value[9] == 1 ||isset($new_tel_flow_node[$value[1]]) == false){
continue;
}
}
$tel_flow_branch_id = $value[0];
if($value_count == 10){
if(empty($value[3])){
$value[3]=0;
}
$value[5] = str_replace('，',',',$value[5]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>$value[3],
'label'=>$value[4],
'keyword'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5])) ?$knowledge_keyword : trim($value[5]),
'keyword_py'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5]))?$knowledge_keyword_py : $pinyin->sentence(trim($value[5])),
'next_flow_id'=>$value[8] == 1&&isset($new_tel_flow_node[$value[7]])?$new_tel_flow_node[$value[7]]:'',
'is_select'=>$value[8],
'type'=>$value[9]
];
}else if($value_count == 9){
$value[4] = str_replace('，',',',$value[4]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>1,
'label'=>$value[3],
'keyword'=>trim($value[4]) == 'null'||trim($value[4]) == null ||empty(trim($value[4])) ?$knowledge_keyword : trim($value[4]),
'keyword_py'=>trim($value[4]) == 'null'||trim($value[4]) == null ||empty(trim($value[4])) ?$knowledge_keyword_py : $pinyin->sentence(trim($value[4])),
'next_flow_id'=>$value[7] == 1 &&isset($new_tel_flow_node[$value[6]])?$new_tel_flow_node[$value[6]]:'',
'is_select'=>$value[7],
'type'=>$value[8]
];
}else if($value_count == 11){
if(empty($value[3])){
$value[3]=0;
}
$value[5] = str_replace('，',',',$value[5]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>$value[3],
'label'=>$value[4],
'keyword'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5])) ?$knowledge_keyword : trim($value[5]),
'keyword_py'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5])) ?$knowledge_keyword_py : $pinyin->sentence(trim($value[5])),
'next_flow_id'=>$value[8] == 1 &&isset($new_tel_flow_node[$value[7]])?$new_tel_flow_node[$value[7]]:'',
'is_select'=>$value[8],
'type'=>$value[9],
'query_type'=>$value[10]
];
}else if($value_count == 12){
if(empty($value[3])){
$value[3]=0;
}
$value[5] = str_replace('，',',',$value[5]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>$value[3],
'label'=>$value[4],
'keyword'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5]))?$knowledge_keyword : trim($value[5]),
'keyword_py'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5])) ?$knowledge_keyword_py : $pinyin->sentence(trim($value[5])),
'next_flow_id'=>$value[8] == 1&&isset($new_tel_flow_node[$value[7]])?$new_tel_flow_node[$value[7]]:'',
'is_select'=>$value[8],
'type'=>$value[9],
'query_type'=>$value[10],
'order_by'=>$value[11]
];
}
if($value_count >1){
$new_tel_flow_branch[$tel_flow_branch_id] = Db::name('tel_flow_branch')->insertGetId($insert_tel_flow_branch);
}
}
}else{
$new_tel_flow_branch = [];
foreach($excel_tel_flow_branch as $key=>$value){
$value_count = count($value);
if($value_count == 10){
if($key == 0 ||empty($value[7]) &&$value[8] == 1 ||isset($new_tel_flow_node[$value[7]]) === false &&$value[8] == 1 ||isset($new_tel_flow_node[$value[1]]) == false){
continue;
}
}else if($value_count == 9){
if($key == 0 ||empty($value[6]) &&$value[7] == 1 ||isset($new_tel_flow_node[$value[6]]) === false &&$value[7] == 1 ||isset($new_tel_flow_node[$value[1]]) == false){
continue;
}
}else if($value_count == 11 ||$value_count == 12){
if($key == 0 ||empty($value[8]) &&$value[9] == 1 ||isset($new_tel_flow_node[$value[8]]) === false &&$value[9] == 1 ||isset($new_tel_flow_node[$value[1]]) == false){
continue;
}
}
$tel_flow_branch_id = $value[0];
if($value_count == 10){
if(empty($value[3])){
$value[3]=0;
}
$value[5] = str_replace('，',',',$value[5]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>$value[3],
'label'=>$value[4],
'keyword'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5]))?'':$value[5],
'keyword_py'=>$pinyin->sentence($value[5]),
'next_flow_id'=>$value[8] == 1 &&isset($new_tel_flow_node[$value[7]])?$new_tel_flow_node[$value[7]]:'',
'is_select'=>$value[8],
'type'=>$value[9]
];
}else if($value_count == 9){
$value[4] = str_replace('，',',',$value[4]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>1,
'label'=>$value[3],
'keyword'=>trim($value[4]) == 'null'||trim($value[4]) == null ||empty(trim($value[4]))?'':$value[4],
'keyword_py'=>$pinyin->sentence($value[4]),
'next_flow_id'=>$value[7] == 1&&isset($new_tel_flow_node[$value[6]])?$new_tel_flow_node[$value[6]]:'',
'is_select'=>$value[7],
'type'=>$value[8]
];
}else if($value_count == 11){
if(empty($value[3])){
$value[3]=0;
}
$value[5] = str_replace('，',',',$value[5]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>$value[3],
'label'=>$value[4],
'keyword'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5]))?'':$value[5],
'keyword_py'=>$pinyin->sentence($value[5]),
'next_flow_id'=>$value[8] == 1&&isset($new_tel_flow_node[$value[7]])?$new_tel_flow_node[$value[7]]:'',
'is_select'=>$value[8],
'type'=>$value[9],
'query_type'=>$value[10]
];
}else if($value_count == 12){
if(empty($value[3])){
$value[3]=0;
}
$value[5] = str_replace('，',',',$value[5]);
$insert_tel_flow_branch = [
'flow_id'=>$new_tel_flow_node[$value[1]],
'name'=>$value[2],
'label_status'=>$value[3],
'label'=>$value[4],
'keyword'=>trim($value[5]) == 'null'||trim($value[5]) == null ||empty(trim($value[5]))?'':$value[5],
'keyword_py'=>$pinyin->sentence($value[5]),
'next_flow_id'=>$value[8] == 1&&isset($new_tel_flow_node[$value[7]])?$new_tel_flow_node[$value[7]]:'',
'is_select'=>$value[8],
'type'=>$value[9],
'query_type'=>$value[10],
'order_by'=>$value[11]
];
}
if($value_count >1){
$new_tel_flow_branch[$tel_flow_branch_id] = Db::name('tel_flow_branch')->insertGetId($insert_tel_flow_branch);
}
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
foreach($excel_tel_knowledge_tel_corpus as $key=>$value){
if($key == 0){
continue;
}
$tel_corpus_id = $value[0];
$insert_tel_corpus = [
'scenarios_id'=>$new_scenarions_id,
'src_type'=>$value[2],
'source'=>$value[3],
'src_id'=>$new_tel_knowledge[$value[4]],
'content'=>$value[5],
'audio'=>$value[6],
'file_name'=>$value[7],
'file_size'=>$value[8]
];
if(!empty(trim($value[6]))){
$realname = substr(trim($value[6]),strrpos(trim($value[6]),"/"));
$datestr = date ( 'Ymd');
$savePath = './uploads/audio/'.$datestr;
if (!is_dir($savePath)){
mkdir($savePath);
}
$file_name = $savePath.$realname;
$file_name = ltrim($file_name,".");
$insert_tel_corpus['audio'] = $file_name;
}
$new_tel_corpus[$tel_corpus_id] = Db::name('tel_corpus')->insertGetId($insert_tel_corpus);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
if(count($excel_tel_scenarios_config) >= 2){
$insert_tel_scenarios_config = [];
foreach($excel_tel_scenarios_config[1] as $key=>$value){
if($key == 'id'){
continue;
}
$insert_tel_scenarios_config[$excel_tel_scenarios_config[0][$key]] = $value;
if($excel_tel_scenarios_config[0][$key] == 'scenarios_id'){
$insert_tel_scenarios_config['scenarios_id'] = $new_scenarions_id;
}
}
$result = Db::name('tel_scenarios_config')->insertGetId($insert_tel_scenarios_config);
if(empty($result)){
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
$new_tel_intention_rule_template = [];
foreach($excel_tel_intention_rule_template as $key=>$value){
if($key == 0){
continue;
}
$insert_tel_intention_rule_template = [];
foreach($value as $find_key=>$find_value){
if($find_key == 'id'){
$tel_intention_rule_template_id = $find_value;
continue;
}
$insert_tel_intention_rule_template[$excel_tel_intention_rule_template[0][$find_key]] = $find_value;
if($excel_tel_intention_rule_template[0][$find_key] == 'scenarios_id'){
$insert_tel_intention_rule_template['scenarios_id'] = $new_scenarions_id;
}
}
$new_tel_intention_rule_template[$tel_intention_rule_template_id] = Db::name('tel_intention_rule_template')->insertGetId($insert_tel_intention_rule_template);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
foreach($excel_tel_intention_rule as $key=>$value){
if($key == 0){
continue;
}
$insert_tel_intention_rule = [];
foreach($value as $find_key=>$find_value){
$insert_tel_intention_rule[$excel_tel_intention_rule[0][$find_key]] = $find_value;
}
unset($insert_tel_intention_rule['id']);
$insert_tel_intention_rule['scenarios_id'] = $new_scenarions_id;
if(isset($new_tel_intention_rule_template[$insert_tel_intention_rule['template_id']]) === true){
$insert_tel_intention_rule['template_id'] = $new_tel_intention_rule_template[$insert_tel_intention_rule['template_id']];
$result = Db::name('tel_intention_rule')->insertGetId($insert_tel_intention_rule);
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
foreach($excel_tel_knowledge_tel_label as $key=>$value){
if($key == 0){
continue;
}
$tel_label_id = $value[0];
$insert_tel_label = [
'member_id'=>$uid,
'label'=>$value[2],
'keyword'=>$value[3],
'flow_id'=>$new_tel_knowledge[$value[4]],
'type'=>$value[5],
'scenarios_id'=>$new_scenarions_id
];
$tel_label[$tel_label_id] = Db::name('tel_label')->insertGetId($insert_tel_label);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +4;
$RedisConnect->set($key,$attitude);
foreach($excel_semantics_label as $key=>$value){
if($key == 0){
continue;
}
$semantics_label = [];
foreach($value as $find_key=>$find_value){
if($find_key != 'id'){
$semantics_label[$excel_semantics_label[0][$find_key]] = $find_value;
}
if($excel_semantics_label[0][$find_key] == 'scenarios_id'){
$semantics_label['scenarios_id'] = $new_scenarions_id;
}
if($excel_semantics_label[0][$find_key] == 'member_id'){
$semantics_label['member_id'] =  $uid;
}
if($excel_semantics_label[0][$find_key] == 'type'){
$semantics_label['type'] = 0;
}
}
$result = Db::name('tel_label')->insertGetId($semantics_label);
}
$RedisApiData = new RedisApiData();
$RedisApiData->repair_scenarios($new_scenarions_id);
$this->scenarios_check_by_xiafa($new_scenarions_id);
$key = 'task_'.$chaos_num .'_count';
$RedisConnect->del($key);
Db::commit();
return true;
}catch (\Exception $e) {
Db::rollback();
$key = 'task_'.$chaos_num .'_count';
$RedisConnect->del($key);
\think\Log::record($e->getMessage());
return false;
}
}
public function getKnlgEight()
{
$sceneId = input('sceneId','','trim,strip_tags');
if(!$sceneId){
return returnAjax(1,'传入参数错误');
}
$result = Db::name('tel_knowledge')->field('id,name')->where(['scenarios_id'=>$sceneId,'type'=>8,'name'=>['neq','']  ])->order('id asc')->select();
if($result){
return returnAjax(0,'获取数据成功',$result);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function getSmsTpl(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$ch = array();
if(!$super){
$ch['owner'] = $uid;
}
$ch['status'] = 1;
$tpllist = Db::name('sms_template')->field('id,name')->where($ch)->select();
if($tpllist){
return returnAjax(0,'获取数据成功',$tpllist);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function scene_banben2(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$where['is_tpl'] = 0;
if(!$super){
$where['member_id'] = $uid;
}
$list = Db::name('tel_scenarios')
->field('s.*,a.username')
->alias('s')
->join('admin a','a.id = s.member_id')
->where($where)->order('id desc')
->select();
$where = [];
foreach ($list as $key=>$val){
$where['tfn.type'] = array('eq',0);
$where['ts.id'] = array('eq',$val['id']);
$where['tc.audio'] = array(array('eq',''),array('exp','is null'),'or');
$where['tc.src_type'] = [['eq',''],['exp','is null'],'or'];
$list[$key]["update_time"] = date("Y-m-d H:i:s",$val["update_time"]);
$res = Db::name('tel_flow_node')
->alias('tfn')
->join('tel_corpus tc','tc.src_id = tfn.id','LEFT')
->join('tel_scenarios_node tsn','tsn.id = tfn.scen_node_id','LEFT')
->join('tel_scenarios ts','ts.id = tsn.scenarios_id','LEFT')
->where($where)
->count('tfn.id');
if($res == 0){
$list[$key]['notempty'] = 0;
}else{
$list[$key]['notempty'] = 1;
}
}
$AdminData = new AdminData();
$user_list = $AdminData->get_find_users($uid);
array_unshift($user_list,['id'=>$uid,'username'=>'自己']);
$this->assign('user_list',$user_list);
$this->assign('scenarioslist',$list);
$this->assign('num',count($list));
$adminlist = Db::name('admin')->field('examine')->where('id',$uid)->find();
$this->assign('examine',$adminlist['examine']);
$this->assign('super',$super);
\think\Config::parse(APP_PATH.'intention.json','json');
$intention = \think\Config::get('intention_rule');
$this->assign('intention',$intention);
$checklist = Db::name('tel_scenarios')->where('is_tpl',1)->order('id desc')->select();
$this->assign('checklist',$checklist);
$grgs = array();
if(!$super){
$grgs['owner'] = $uid;
}
$grouplist = Db::name('tsr_group')->field('id,name')->where($grgs)->order('id desc')->select();
$this->assign('grouplist',$grouplist);
$seats = Db::name('admin')
->alias('a')
->join('admin_role ar','ar.id = a.role_id','LEFT')
->field('a.id,a.username')
->where([
'ar.name'=>'坐席',
'pid'=>$uid
])
->select();
$this->assign('seats',$seats);
return $this->fetch();
}
public function process(){
return $this->fetch();
}
function processPOSTRequest($appkey,$token,$text,$audioSaveFile,$format,$sampleRate,$perhaps) {
$url = "https://nls-gateway.cn-shanghai.aliyuncs.com/stream/v1/tts";
$taskArr = array(
"appkey"=>$appkey,
"token"=>$token,
"text"=>$text,
"format"=>$format,
"sample_rate"=>$sampleRate,
"voice"=>$perhaps['voice'],
"volume"=>$perhaps['volume'],
"speech_rate"=>$perhaps['speech_rate'],
"pitch_rate"=>$perhaps['pitch_rate']
);
$body = json_encode($taskArr);
$curl = curl_init();
curl_setopt($curl,CURLOPT_RETURNTRANSFER,TRUE);
curl_setopt($curl,CURLOPT_URL,$url);
curl_setopt($curl,CURLOPT_POST,TRUE);
$httpHeaders = array(
"Content-Type: application/json"
);
curl_setopt($curl,CURLOPT_HTTPHEADER,$httpHeaders);
curl_setopt($curl,CURLOPT_POSTFIELDS,$body);
curl_setopt($curl,CURLOPT_HEADER,TRUE);
$response = curl_exec($curl);
if ($response == FALSE) {
curl_close($curl);
return ;
}
$headerSize = curl_getinfo($curl,CURLINFO_HEADER_SIZE);
$headers = substr($response,0,$headerSize);
$bodyContent = substr($response,$headerSize);
curl_close($curl);
if (stripos($headers,"Content-Type: audio/mpeg") != FALSE ||stripos($headers,"Content-Type:audio/mpeg") != FALSE) {
file_put_contents($audioSaveFile,$bodyContent);
return returnAjax( 1 ,$text,$audioSaveFile);
}
else {
return returnAjax( 0 ,$text,$audioSaveFile);
}
}
public function get_synthesis_voice_config()
{
$redis = RedisConnect::get_redis_connect();
$synthesis_voice_config = $redis->get('synthesis_voice_config');
$synthesis_voice_config = json_decode($synthesis_voice_config,true);
if(!empty($synthesis_voice_config) &&$synthesis_voice_config['ExpireTime'] >time()){
}else{
\DefaultProfile::addEndpoint(
"cn-shanghai",
"cn-shanghai",
"nls-cloud-meta",
"nls-meta.cn-shanghai.aliyuncs.com");
# 创建DefaultAcsClient实例并初始化
$clientProfile = \DefaultProfile::getProfile(
"cn-shanghai",# Region ID  
"LTAIsrJZRiHnzpQj",# 您的 AccessKey ID
"Tq6e2ino7lXXpmdQXx7QywahXzY1Mr"# 您的 AccessKey Secret
);
$client = new \DefaultAcsClient($clientProfile);
# 创建API请求并设置参数
$request = new CreateTokenRequest();
# 发起请求并处理返回
$response = $client->getAcsResponse($request);
$synthesis_voice_config = [
'ExpireTime'=>$response->Token->ExpireTime,
'Id'=>$response->Token->Id,
'UserId'=>$response->Token->UserId,
];
$redis->set('synthesis_voice_config',json_encode($synthesis_voice_config));
}
return $synthesis_voice_config;
}
public function synthesisVoice(){
$get_synthesis_voice_config = $this->get_synthesis_voice_config();
$appkey = Config::get('appkey');
$token = $get_synthesis_voice_config['Id'];
$perhaps = [];
$voice = [];
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$url ="uploads/synthesis_voice/".date("Y-m")."/".date("d")."/";
$random = rand_string(5).time();
$perhaps['voice'] = input('voice','Aixia','trim,strip_tags');
$perhaps['volume'] = (int)input('volume',50,'trim,strip_tags');
$perhaps['speech_rate'] = (int)input('speech_rate',0,'trim,strip_tags');
$perhaps['pitch_rate'] = (int)input('pitch_rate',0,'trim,strip_tags');
$dir = iconv("UTF-8","GBK",$url);
if (!file_exists($dir)){
mkdir($dir,0755,true);
}
$text = [];
$text = input('text/a','','trim,strip_tags');
$format = input('format','','trim,strip_tags') ?input('format','','trim,strip_tags') : 'wav';
$sampleRate = input('sampleRate','','strip_tags') ?(int)input('sampleRate','','strip_tags') : 16000 ;
foreach($text as $key =>$vo){
$random = rand_string(5).time();
$audioSaveFile = $url.$random.'.'.$format;
$textUrlEncode = urlencode($vo);
$textUrlEncode = preg_replace('/\+/','%20',$textUrlEncode);
$textUrlEncode = preg_replace('/\*/','%2A',$textUrlEncode);
$textUrlEncode = preg_replace('/%7E/','~',$textUrlEncode);
$result = $this->processPOSTRequest($appkey,$token,$vo,$audioSaveFile,$format,$sampleRate ,$perhaps);
if($result['code'] == 1){
$voice[$key]['text'] = $result['msg'];
$voice[$key]['audioSaveFile'] = $result['data'];
}else if($result['code'] == 0){
$voice[$key]['text'] = $result['msg'];
$voice[$key]['audioSaveFile'] = '';
}
}
$voice_list = $voice;
Cookie::delete('voice_'.$uid);
$voice_list = json_encode($voice_list);
Cookie::set( 'voice_'.$uid,$voice_list,3600);
return returnAjax( 1 ,'文件合成完成','voice_'.$uid);
}
public function delete_voice(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$src = input('src','','trim,strip_tags');
$res = $this->Delete_files($src);
if($res == 1){
$voice = Cookie::get('voice_'.$uid);
$voice_list =	json_decode($voice,true);
$new_list = [];
foreach($voice_list as $key =>$vo){
if('/'.$vo['audioSaveFile'] != $src){
$new_list[$key] = $vo;
}
}
$voice_list = json_encode($new_list);
Cookie::set( 'voice_'.$uid,$voice_list,3600);
return returnAjax(1,'删除成功');
}
}
public function  saveVoice(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$voice_list= Cookie::get('voice_'.$uid);
$voice_list =	json_decode($voice_list,true);
$data = [];
$urls = [];
if($voice_list){
$id = input('id','','trim,strip_tags');
foreach($voice_list as $key=>$vo){
$data[$key]['scenarios_id'] = $id;
$data[$key]['src_type'] = 2;
$data[$key]['src_id'] = 0;
$data[$key]['source'] = 1;
$data[$key]['content'] = $vo['text'];
$data[$key]['audio'] = "/uploads/audio/".date("Ymd")."/".trim(strrchr($vo['audioSaveFile'],'/'),'/');
$urls[$key]['url'] = $vo['audioSaveFile'];
$data[$key]['file_name'] = $data[$key]['content'];
$data[$key]['file_size'] = round($this->getFileSize(filesize($urls[$key]['url'])),2);
}
$res = DB::name('tel_corpus')->insertAll($data);
if($res){
$url ="uploads/audio/".date("Ymd")."/";
$dir = iconv("UTF-8","GBK",$url);
if (!file_exists($dir)){
mkdir($dir,0755,true);
}
foreach($urls as $key=>$vo){
$src ="uploads/audio/".date("Ymd")."/".trim(strrchr($vo['url'],'/'),'/');
rename($vo['url'],$src);
}
Cookie::delete('voice_'.$uid);
return returnAjax( 1 ,'保存成功');
}else{
foreach($urls as $key=>$vo){
$this->Delete_files($vo['url']);
}
Cookie::delete('voice_'.$uid);
return returnAjax( 0 ,'保存失败');
}
}else{
return returnAjax( 0 ,'无效参数');
}
}
public function ajax_audio_synthesis(){
$where = [];
$id = input('id','','trim,strip_tags');
$keyword = input('record_name','','trim,strip_tags');
$page = input('page','','strip_tags') ?input('page','','strip_tags') : 1 ;
$page_size = input('limit','','strip_tags') ?input('limit','','strip_tags') : 10 ;
if($keyword){
$where['file_name'] =array('like',"%".$keyword."%");
}
$where['scenarios_id'] = array('eq',$id);
$where['source'] = array('eq',1);
$list = Db::name('tel_corpus')->where($where)->page($page,$page_size)->order('id','desc')->select();
foreach($list as $key=>$vo){
$list[$key]['format'] = trim(strrchr($vo['audio'],'.'),'.');
}
$count = Db::name('tel_corpus')->where($where)->count();
$page_count = ceil($count/$page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(1,'获取数据成功',$data);
}
public function voice_audition(){
$id = input('id','','trim,strip_tags');
$url = Db::name('tel_corpus')->where('id',$id)->value('audio');
return returnAjax(1,'获取成功',$url);
}
public function delete_listvoice(){
$id = input('id','','trim,strip_tags');
$audio = Db::name('tel_corpus')->where('id',$id)->value('audio');
$res = Db::name('tel_corpus')->where('id',$id)->delete();
if($res){
return returnAjax(1,'删除成功');
}else{
return returnAjax(0,'删除失败');
}
}
public function ajax_voice_manage(){
$id = input('id','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$usource = input('usource','','trim,strip_tags');
$page = input('page','','strip_tags') ?input('page','','strip_tags') : 1 ;
$page_size = input('limit','','strip_tags') ?input('limit','','strip_tags') : 10 ;
$lableName = input('lableName','','trim,strip_tags');
$where = [];
if($keyword){
$where['cor.file_name|cor.content'] = array('like',"%".$keyword."%");
}
if($usource != ''){
$where['cor.source'] = array('eq',$usource);
}
if($lableName){
$where['kn.name|fl.name'] = array('like',"%".$lableName."%");
}
$where['cor.scenarios_id'] = array('eq',$id);
$where['cor.audio']=['exp','is not null'];
$list = Db::name('tel_corpus')
->alias('cor')
->field('cor.*,if(cor.src_type = 1, kn.label, fl.flow_label) as label')
->join('tel_flow_node fl','cor.src_id = fl.id','LEFT')
->join('tel_knowledge kn','cor.src_id = kn.id','LEFT')
->where($where)
->page($page,$page_size)
->order('cor.id','desc')
->select();
foreach($list as $key=>$vo){
$list[$key]['format'] = trim(strrchr($vo['audio'],'.'),'.');
if(!empty($vo['audio'])){
if(file_exists(ROOT_PATH.$vo['audio']) &&filesize(ROOT_PATH.$vo['audio'])>0){
$list[$key]['file_size'] = $this->getFilesize_lj(filesize(ROOT_PATH.$vo['audio']));
}
}
if($vo['src_type'] == 0){
$list[$key]['src_id'] = Db::name('tel_flow_node')->where('id',$vo['src_id'])->value('name');
if(!$list[$key]['src_id']){
$list[$key]['src_id'] = '暂无名称';
}
}else if($vo['src_type'] == 1){
$list[$key]['src_id'] = Db::name('tel_knowledge')->where('id',$vo['src_id'])->value('name');
if(!$list[$key]['src_id']){
$list[$key]['src_id'] = '暂无名称';
}
}else{
$list[$key]['src_id'] = '暂无名称';
}
}
$count =  Db::name('tel_corpus')
->alias('cor')
->field('cor.*,if(cor.src_type = 1, kn.label, fl.flow_label) as label')
->join('tel_flow_node fl','cor.src_id = fl.id','LEFT')
->join('tel_knowledge kn','cor.src_id = kn.id','LEFT')
->where($where)
->count();
$page_count = ceil($count/$page_size);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(1,'获取数据成功',$data);
}
public function ceshi(){
$rule_template = Db::name('tel_intention_rule_template')->where('scenarios_id',127)->select();
foreach($rule_template as $key =>$vo){
$list = Db::name('tel_intention_rule')->where('template_id',$vo['id'])->select();
foreach ($list as $k=>$v){
$list2[] = $v;
}
}
var_dump($list2);
}
public function edit_vioce(){
if(request()->isGet()){
$id = input('id','','trim,strip_tags');
$info = Db::name('tel_corpus')->where('id',$id)->find();
$info['source'] = $info['source'] == 0?'上传音频':'合成音频';
if($info['src_type'] == 0){
$info['src_type'] = '流程节点';
$labelname = Db::name('tel_flow_node')->where('id',$info['src_id'])->value('name');
$info['src_id']  = $labelname ?$labelname:'暂无名称';
}else if($info['src_type'] == 1){
$info['src_type'] = '知识库';
$labelname = Db::name('tel_knowledge')->where('id',$info['src_id'])->value('name');
$info['src_id']  = $labelname ?$labelname:'暂无名称';
}else{
$info['src_type'] = '暂无用途';
$info['src_id']  = '暂无名称';
}
return returnAjax(1,'获取数据成功',$info);
}
}
public function Delete_files($url){
$filename = ROOT_PATH .$url;
if(file_exists($filename)){
unlink($filename);
return 1;
}else{
return 0;
}
}
function getFileSize($size){
$dw="Byte";
if($size >= pow(2,40)){
$size=round($size/pow(2,40),3);
$dw="TB";
}else if($size >= pow(2,30)){
$size=round($size/pow(2,30),3);
$dw="GB";
}else if($size >= pow(2,20)){
$size=round($size/pow(2,20),3);
$dw="MB";
}else if($size >= pow(2,10)){
$size=round($size/pow(2,10),3);
$dw="KB";
}else {
$dw="Bytes";
}
return $size.$dw;
}
public function vociecshi(){
return $this->fetch();
}
public function batchvoicetext(){
$file_types = explode(".",$_FILES['excel']['name']);
$file_type = $file_types [count ( $file_types ) -1];
$fileName = $_FILES['excel']['tmp_name'];
$data = $this->batchfile($fileName,$file_type);
return returnAjax(0,'上传数据成功',$data);
}
public function batchfile($fileName,$file_type){
if (!file_exists($fileName)) {
exit("文件".$fileName."不存在");
}
if($file_type == 'xlsx') {
$inputFileType = 'Excel2007';
$reader = \PHPExcel_IOFactory::createReader($inputFileType);
}else{
$inputFileType = 'Excel5';
$reader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$PHPExcel = $reader->load($fileName,'utf-8');
$sheet = $PHPExcel->getSheet(0);
$highestRow = $sheet->getHighestRow();
$highestColumm = $sheet->getHighestColumn();
$data = array();
for ($rowIndex = 2;$rowIndex <= $highestRow;$rowIndex++) {
for ($colIndex = 'A';$colIndex <= $highestColumm;$colIndex++) {
$addr = $colIndex .$rowIndex;
$cell = $sheet->getCell($addr)->getValue();
if ($cell instanceof PHPExcel_RichText) {
$cell = $cell->__toString();
}
$data[$rowIndex][$colIndex] = $cell;
}
}
return $data;
}
public function filezipbatch(){
$fileName = $_FILES['excel']['tmp_name'];
$Name = $_FILES['excel']['name'];
$id = input('id','','trim,strip_tags');
$file_types = explode(".",$_FILES['excel']['name']);
$file_type = $file_types [count ( $file_types ) -1];
$zip = new ZipArchive;
if ($zip->open($fileName) === TRUE) {
$url ="uploads/synthesis_voice/ZipExcel";
$dir_t = iconv("UTF-8","GBK",$url);
if (!file_exists($dir_t)){
mkdir($dir_t,0755,true);
}
$zip->extractTo('./uploads/synthesis_voice/ZipExcel');
$res = is_file('./uploads/synthesis_voice/ZipExcel/voice_excel.xlsx');
if($res){
$Exename = './uploads/synthesis_voice/ZipExcel/voice_excel.xlsx';
if (!file_exists($Exename)) {
return "文件【voice_excel.xlsx】或者【voice_excel.xls】不存在";
}
$file_type = 'xlsx';
}else{
$Exename = './uploads/synthesis_voice/ZipExcel/voice_excel.xls';
if (!file_exists($Exename)) {
return "文件【voice_excel.xlsx】或者【voice_excel.xls】不存在";
}
$file_type = 'xls';
}
$data = $this->batchfile($Exename,$file_type);
$list = [];
$dir = './uploads/synthesis_voice/ZipExcel/';
$num =[];
foreach($data as $key =>$vo){
if (!empty($vo['C']) &&file_exists($dir.$vo['C'])){
$list[$key]['file_name']= $vo['A'];
$list[$key]['content']= $vo['B'];
$list[$key]['audio']= $vo['C'];
}else{
$num[]=$key;
}
}
$url ="uploads/audio/".date("Ymd")."/";
$dir_t = iconv("UTF-8","GBK",$url);
if (!file_exists($dir_t)){
mkdir($dir_t,0755,true);
}
foreach ($list as $key=>$vo){
$obj['scenarios_id'] = $id;
$obj['file_name'] = $vo['file_name'];
$obj['content'] = $vo['content'];
$obj['src_type'] = 3;
$obj['src_id'] = 0;
$random = rand_string(5).time();
$obj['audio'] = '/'.$url.$random.'.wav';
$rel = Db::name('tel_corpus')->insert($obj);
if($rel){
$src ="./uploads/synthesis_voice/ZipExcel/".$vo['audio'];
rename($src,$url.$random.'.wav');
}
}
$path = './uploads/synthesis_voice/ZipExcel/';
$this->deldir($path);
return returnAjax(0,'批量上传完毕,过滤掉错误信息'.count($num).'条,错误条数第'.implode(",",$num).'条');
}else {
echo 'failed';
}
$zip->close();
}
function deldir($path){
if(is_dir($path)){
$p = scandir($path);
foreach($p as $val){
if($val !="."&&$val !=".."){
if(is_dir($path.$val)){
$this->deldir($path.$val.'/');
@rmdir($path.$val.'/');
}else{
unlink($path.$val);
}
}
}
}
}
public function fileUpload(){
$id = input('id','','strip_tags');
$content = input('record_content','','strip_tags');
$file_name = input('record_name','','strip_tags');
$file = Request::instance()->file('file');
$data =[];
$data['content'] = $content;
$data['file_name'] = $file_name;
if(!empty($file)){
$info = $file->move(ROOT_PATH.'uploads'.DS .'audio');
if ($info) {
$path = $info->getSaveName();
$src = 'uploads/audio/'.$path;
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
rename($src ,$new_src);
$data['audio'] = '/'.$new_src;
$res =  Db::name('tel_corpus')->where('id',$id)->update($data);
if($res){
return returnAjax(0,'修改成功');
}
}else {
return returnAjax(1,'修改失败',$file->getError());
}
}else{
$res =  Db::name('tel_corpus')->where('id',$id)->update($data);
if($res){
return returnAjax(0,'修改成功');
}else{
return returnAjax(1,'修改失败');
}
}
}
public function batchvoice_del(){
$type = input('type','','trim,strip_tags');
$uid = input('uid','','trim,strip_tags');
$where = [];
if($type == 1){
$where['scenarios_id'] = array('eq',$uid);
$list_data = Db::name('tel_corpus')->where($where)->select();
$res = Db::name('tel_corpus')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}else if($type == 0){
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
$list_data = Db::name('tel_corpus')->where($where)->select();
$res = Db::name('tel_corpus')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}
}
public function get_flowlabel_list(){
$sceneId = input('sceneId','','trim,strip_tags');
$page = input('page','1','trim,strip_tags');
$Page_size = input('pagesize','10','trim,strip_tags');
$processName = input('processName','','trim,strip_tags');
$process_content = input('process_content','','trim,strip_tags');
$where = array();
if(empty($sceneId)){
return returnAjax(3,'参数传递错误，话术id为空');
}
$where['c.scenarios_id'] = $sceneId;
if(!empty($process_content)){
$where['c.content'] = ['like','%'.$process_content.'%'];
}
$where['c.src_type'] = 0;
if(!empty($processName)){
$where['f.flow_label'] = ['like','%'.$processName.'%'];
}else{
$where['f.flow_label'] = [['neq',''],['neq','null'],['exp','is not null'],'and'];
}
$result = Db::name('tel_flow_node')
->field('f.id,f.name,f.flow_label,f.label_status,c.content,c.audio,c.id as corpus_id,sn.name as scenarios_node_name')
->alias('f')
->join('tel_scenarios_node sn','sn.id = f.scen_node_id','INNER')
->join('tel_corpus c','c.src_id = f.id','LEFT')
->where($where)
->order('last_time desc')
->page($page,$Page_size)
->select();
\think\Log::record('获取流程标签数据');
$count = Db::name('tel_flow_node')
->field('f.id,f.name,f.flow_label,f.label_status,c.content,c.audio')
->alias('f')
->join('tel_scenarios_node sn','sn.id = f.scen_node_id','INNER')
->join('tel_corpus c','c.src_id = f.id')
->where($where)
->count();
foreach ($result as $key =>$value) {
$result[$key]['key'] = ($page -1) * $Page_size +($key +1);
if(!empty($value['content']) &&!empty($value['audio'])){
$result[$key]['content_type'] = '录音和文字';
}else if(!empty($value['content'])){
$result[$key]['content_type'] = '文字';
}else if(!empty($value['audio'])){
$result[$key]['content_type'] = '录音';
}else{
$result[$key]['content_type'] = '';
}
if(empty($value['label_status'])){
$result[$key]['state'] = '';
}else{
$result[$key]['state'] = 'checked';
}
}
$data = [
'data'=>$result,
'count'=>$count,
'page'=>$page,
'pagesize'=>$Page_size
];
if($result){
return returnAjax(0,'有数据了',$data);
}else{
return returnAjax(1,'暂时没有数据',$data);
}
}
public function change_flowlabel_state(){
$corpus_id = input('corpus_id','','trim,strip_tags');
$label_status = input('state','','trim,strip_tags');
$data=array();
$data['f.label_status'] = $label_status;
$state = Db::name('tel_flow_node')->alias('f')->join('tel_corpus c','c.src_id = f.id')->where('c.id',$corpus_id)->update($data);
if(empty($state)){
return returnAjax(1,'更新失败',$state);
}else{
return returnAjax(0,'更新成功',$state);
}
}
public function get_type_voice(){
$type = input('type','','trim,strip_tags');
$id = input('id','','trim,strip_tags');
$where = [];
if($type){
if($type == 2){
$where['source'] = array('eq',0);
}else if($type == 3){
$where['source'] = array('eq',1);
}
}else{
return returnAjax(1,'获取数据失败');
}
$where['scenarios_id'] = array('eq',$id);
$where['src_type '] = array(array('eq',2),array('eq',3),'or');
$where['src_id '] = array('eq',0);
$list =Db::name('tel_corpus')->where($where)->select();
if($list){
return returnAjax(0,'获取数据成功',$list);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function sure_voice(){
$id = input('id','','trim,strip_tags');
$info = Db::name('tel_corpus')->where('id',$id)->find();
if($info){
return returnAjax(0,'获取数据成功',$info);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function get_Knowledgelabel_list(){
$sceneId = input('sceneId','','trim,strip_tags');
$page = input('page','1','trim,strip_tags');
$Page_size = input('pagesize','10','trim,strip_tags');
$lableProcessName = input('lableProcessName','','trim,strip_tags');
$lablecontent = input('lablecontent','','trim,strip_tags');
$where = array();
if(empty($sceneId)){
return returnAjax(3,'参数传递错误，话术id为空');
}
$where['kn.scenarios_id'] = $sceneId;
if(!empty($lableProcessName)){
$where['kn.label'] = ['like','%'.$lableProcessName.'%'];
}
if(!empty($lablecontent)){
$where['c.content'] = ['like','%'.$lablecontent.'%'];
}
$result = Db::name('tel_knowledge')
->field('kn.id,kn.name,kn.label,kn.label_status,c.content,c.audio,c.id as corpus_id')
->alias('kn')
->join('tel_corpus c','c.src_id = kn.id','LEFT')
->where($where)
->where(function($query){
$query->where('c.src_type',1)->whereOr('c.src_type','null');
})
->where('kn.label','not null')
->where(['kn.label'=>['<>','']])
->order('update_time desc')
->page($page,$Page_size)
->select();
\think\Log::record('获取知识库标签数据');
$count = Db::name('tel_knowledge')
->field('kn.id,kn.name,kn.label,kn.label_status,c.content,c.audio')
->alias('kn')
->join('tel_corpus c','c.src_id = kn.id','LEFT')
->where($where)
->where(function($query){
$query->where('c.src_type',1)->whereOr('c.src_type','null');
})
->where('kn.label','not null')
->where(['kn.label'=>['<>','']])
->count();
foreach ($result as $key =>$value) {
$result[$key]['key'] = ($page -1) * $Page_size +($key +1);
if(!empty($value['content']) &&!empty($value['audio'])){
$result[$key]['content_type'] = '录音和文字';
}else if(!empty($value['content'])){
$result[$key]['content_type'] = '文字';
}else if(!empty($value['audio'])){
$result[$key]['content_type'] = '录音';
}else{
$result[$key]['content_type'] = '';
}
if(empty($value['label_status'])){
$result[$key]['state'] = '';
}else{
$result[$key]['state'] = 'checked';
}
}
$data = [
'data'=>$result,
'count'=>$count,
'page'=>$page,
'pagesize'=>$Page_size
];
if($result){
return returnAjax(0,'有数据了',$data);
}else{
return returnAjax(1,'暂时没有数据',$data);
}
}
public function change_Knowledgelabel_state(){
$corpus_id = input('corpus_id','','trim,strip_tags');
$label_status = input('state','','trim,strip_tags');
$data=array();
$data['kn.label_status'] = $label_status;
$state = Db::name('tel_knowledge')->alias('kn')->join('tel_corpus c','c.src_id = kn.id')->where('c.id',$corpus_id)->update($data);
if(empty($state)){
return returnAjax(1,'更新失败',$state);
}else{
return returnAjax(0,'更新成功',$state);
}
}
public function get_semanticslabel_list(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','1','trim,strip_tags');
$Page_size = input('pagesize','10','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
if(empty($sceneId)){
return returnAjax(3,'参数传递错误，话术id为空');
}
$where = array();
$where['member_id'] = $uid;
$where['scenarios_id'] = $sceneId;
$where['type'] = 0;
if(!empty($keyword)){
$where['keyword'] = ['like','%'.$keyword.'%'];
}
$result = Db::name('tel_label')->where($where)->page($page,$Page_size)->order('query_order','desc')->select();
$count = Db::name('tel_label')->where($where)->count();
$levels = [
'6'=>'A级意向等级',
'5'=>'B级意向等级',
'4'=>'C级意向等级',
'3'=>'D级意向等级',
'2'=>'E级意向等级',
'1'=>'F级意向等级',
'0'=>'不设置意向等级'
];
foreach ($result as $key =>$value) {
$result[$key]['key'] = ($page -1) * $Page_size +($key +1);
if(empty($value['label_status'])){
$result[$key]['state'] = '';
}else{
$result[$key]['state'] = 'checked';
}
$result[$key]['level'] = $levels[$value['level']];
}
$data = [
'data'=>$result,
'count'=>$count,
'page'=>$page,
'pagesize'=>$Page_size
];
if($result){
return returnAjax(0,'语义标签有数据了',$data);
}else{
return returnAjax(1,'语义标签暂时没有数据',$data);
}
}
public function edit_semanticslabel_info(){
$id = input('id','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'参数传递错误');
}
$label_info = Db::name('tel_label')
->where('id',$id)
->find();
return returnAjax(0,'success',$label_info);
}
public function filter_keyword($cnKeyword){
$cnKeyword = str_replace('/','',$cnKeyword);
$cnKeyword = str_replace('\\','',$cnKeyword);
$cnKeyword = str_replace('（','',$cnKeyword);
$cnKeyword = str_replace('）','',$cnKeyword);
$cnKeyword = str_replace('[','',$cnKeyword);
$cnKeyword = str_replace(']','',$cnKeyword);
$cnKeyword = str_replace('\'','',$cnKeyword);
$cnKeyword = str_replace('"','',$cnKeyword);
$cnKeyword = str_replace('‘','',$cnKeyword);
$cnKeyword = str_replace('’','',$cnKeyword);
$cnKeyword = str_replace('“','',$cnKeyword);
$cnKeyword = str_replace('”','',$cnKeyword);
$cnKeyword = str_replace('，','',$cnKeyword);
$cnKeyword = str_replace('?','',$cnKeyword);
$cnKeyword = str_replace('？','',$cnKeyword);
$cnKeyword = str_replace('.','',$cnKeyword);
$cnKeyword = str_replace('。','',$cnKeyword);
$cnKeyword = str_replace('*','',$cnKeyword);
$cnKeyword = str_replace('^','',$cnKeyword);
$cnKeyword = str_replace('&','',$cnKeyword);
$cnKeyword = str_replace('$','',$cnKeyword);
$cnKeyword = str_replace('%','',$cnKeyword);
$cnKeyword = str_replace('@','',$cnKeyword);
return $cnKeyword;
}
public function add_editor_semanticslabel(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$id = input('id','','trim,strip_tags');
$label = input('label','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$level = input('level','','trim,strip_tags');
$keyword=trim($keyword,',');
$keyword= $this->filter_keyword($keyword);
$sceneId = input('sceneId','','trim,strip_tags');
$query_order = input('query_order','','trim,strip_tags');
if(empty($sceneId)){
return returnAjax(5,'参数传递错误，话术id为空');
}
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$keyword_py = $pinyin->sentence($keyword);
$data = [
'member_id'=>$uid,
'label'=>$label,
'keyword'=>$keyword,
'type'=>0,
'scenarios_id'=>$sceneId,
'level'=>$level,
'pinyin'=>$keyword_py,
'query_order'=>$query_order
];
$where = [
'member_id'=>$uid,
'label'=>$label,
'type'=>0,
'scenarios_id'=>$sceneId
];
$label_count = Db::name('tel_label')->where($where)->count('1');
$result = Db::name('tel_label');
if(empty($id)){
if(!empty($label_count)){
return returnAjax(3,'该语义标签名已存在',$data);
}
$res = $result->insert($data);
if(empty($res)){
return returnAjax(1,'添加或更新失败',$data);
}
}else{
$key = 'smartivr_semantic_label_'.$sceneId;
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
$label_name = Db::name('tel_label')->where('id',$id)->value('label');
if($label_name != $label &&!empty($label_count)){
return returnAjax(3,'该语义标签名已存在',$data);
}
$res = $result->where('id',$id)->update($data);
if(empty($res) &&$label_name != $label){
return returnAjax(1,'添加或更新失败',$data);
}
}
return returnAjax(0,'添加或更新成功',$data);
}
public function del_semanticslabel(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$ids = input('del_ids/a','','trim,strip_tags');
$state = input('state','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$key = 'smartivr_semantic_label_'.$sceneId;
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
$del_ids = [];
if(empty($state)){
$where = array();
$where['member_id'] = $uid;
$where['scenarios_id'] = $sceneId;
$where['type'] = 0;
if(!empty($keyword)){
$where['keyword'] = ['like','%'.$keyword.'%'];
}
$result = Db::name('tel_label')->field('id')->where($where)->order('id','desc')->select();
foreach ($result as $value) {
$del_ids[] = $value['id'];
}
}else{
$del_ids = $ids;
}
$del_result = Db::name('tel_label')
->where('id','in',$del_ids)
->delete();
if(!empty($del_result)){
return returnAjax(0,'成功',$del_ids);
}else{
return returnAjax(1,'失败',$ids);
}
}
public function change_semanticslabel_state(){
$labelId = input('labelId','','trim,strip_tags');
$label_status = input('state','','trim,strip_tags');
$data=array();
$data['label_status'] = $label_status;
$state = Db::name('tel_label')->where('id',$labelId)->update($data);
if(empty($state)){
return returnAjax(1,'更新失败',$state);
}else{
return returnAjax(0,'更新成功',$state);
}
}
public function get_grade_classification_data(){
$page = input('page','1','trim,strip_tags');
$Page_size = input('pagesize','10','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
if(empty($sceneId)){
return returnAjax(3,'参数传递错误，话术id为空');
}
$where = array();
$where['scenarios_id'] = $sceneId;
$result = Db::name('tel_intention_rule_template')->where($where)->page($page,$Page_size)->order('id desc')->select();
$count = Db::name('tel_intention_rule_template')->where($where)->count();
foreach ($result as $key =>$value) {
$result[$key]['key'] = ($page -1) * $Page_size +($key +1);
if(empty($value['description'])){
$result[$key]['describe'] = '';
}else{
$result[$key]['describe'] = $value['description'];
}
if(empty($value['status'])){
$result[$key]['state'] = '';
}else{
$result[$key]['state'] = 'checked';
}
}
$data = [
'data'=>$result,
'count'=>$count,
'page'=>$page,
'pagesize'=>$Page_size
];
if($result){
return returnAjax(0,'意向等级模板有数据了',$data);
}else{
return returnAjax(1,'意向等级模板暂时没有数据',$data);
}
}
public function edit_intentionlevel_info(){
$arr=[];
$id = input('id','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'参数传递错误');
}
$where = [];
$where['template_id'] = $id;
$where['type'] = 0;
$intention_name =  Db::name('tel_intention_rule_template')->where('id',$id)->find();
$intention_name['describe'] = $intention_name['description'];
$intention_name['name'] = $intention_name['name'];
$intention_rule = Db::name('tel_intention_rule')
->field('id,name,rule,level')
->where($where)
->select();
foreach($intention_rule as $key=>$value_rul){
if(!empty($value_rul['rule'])){
if($value_rul['level']==6){
$aa = unserialize($value_rul['rule']);
if(count($aa)<2){
foreach($aa as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$aa[$key]['value']=$str;
}else{
$aa[$key]['value']=$value['value'];
}
}
}
$arr['A_or'][]=$aa;
}else{
foreach($aa as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$aa[$key]['value']=$str;
}else{
$aa[$key]['value']=$value['value'];
}
}
}
$arr['A_and'][]=$aa;
}
}
if($value_rul['level']==5){
$bb = unserialize($value_rul['rule']);
if(count($bb)<2){
foreach($bb as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$bb[$key]['value']=$str;
}else{
$bb[$key]['value']=$value['value'];
}
}
}
$arr['B_or'][]=$bb;
}else{
foreach($bb as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$bb[$key]['value']=$str;
}else{
$bb[$key]['value']=$value['value'];
}
}
}
$arr['B_and'][]=$bb;
}
}
if($value_rul['level']==4){
$cc = unserialize($value_rul['rule']);
if(count($cc)<2){
foreach($cc as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$cc[$key]['value']=$str;
}else{
$cc[$key]['value']=$value['value'];
}
}
}
$arr['C_or'][]=$cc;
}else{
foreach($cc as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$cc[$key]['value']=$str;
}else{
$cc[$key]['value']=$value['value'];
}
}
}
$arr['C_and'][]=$cc;
}
}
if($value_rul['level']==3){
$dd = unserialize($value_rul['rule']);
if(count($dd)<2){
foreach($dd as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$dd[$key]['value']=$str;
}else{
$dd[$key]['value']=$value['value'];
}
}
}
$arr['D_or'][]=$dd;
}else{
foreach($dd as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$dd[$key]['value']=$str;
}else{
$dd[$key]['value']=$value['value'];
}
}
}
$arr['D_and'][]=$dd;
}
}
if($value_rul['level']==2){
$ee = unserialize($value_rul['rule']);
if(count($ee)<2){
foreach($ee as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$ee[$key]['value']=$str;
}else{
$ee[$key]['value']=$value['value'];
}
}
}
$arr['E_or'][]=$ee;
}else{
foreach($ee as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$ee[$key]['value']=$str;
}else{
$ee[$key]['value']=$value['value'];
}
}
}
$arr['E_and'][]=$ee;
}
}
if($value_rul['level']==1){
$ff = unserialize($value_rul['rule']);
if(count($ff)<2){
foreach($ff as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$ff[$key]['value']=$str;
}else{
$ff[$key]['value']=$value['value'];
}
}
}
$arr['F_or'][]=$ff;
}else{
foreach($ff as $key=>$value){
if($value['key']=='call_status'){
if(is_array($value['value'])){
$str = implode(',',$value['value']);
$ff[$key]['value']=$str;
}else{
$ff[$key]['value']=$value['value'];
}
}
}
$arr['F_and'][]=$ff;
}
}
}
}
return returnAjax(0,'成功',['intention_name'=>$intention_name['name'],'describe'=>$intention_name['describe'],'intention_rule'=>$arr]);
}
public function add_editor_intentionlevel(){
$array=[];
$data=[];
$data_template=[];
$id = input('id','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$description= input('description','','trim,strip_tags');
$rules = input('rules','','trim,strip_tags');
$rules=json_decode( urldecode($rules),true);
foreach($rules as $k=>$rule){
if(count($rule)<2){
if($rule[0]['level']=='A'){
unset($rule[0]['level']);
$array['A'][]= $rule[0];
}else if($rule[0]['level']=='B'){
unset($rule[0]['level']);
$array['B'][]= $rule[0];
}else if($rule[0]['level']=='C'){
unset($rule[0]['level']);
$array['C'][]= $rule[0];
}else if($rule[0]['level']=='D'){
unset($rule[0]['level']);
$array['D'][]= $rule[0];
}else if($rule[0]['level']=='E'){
unset($rule[0]['level']);
$array['E'][]= $rule[0];
}else if($rule[0]['level']=='F'){
unset($rule[0]['level']);
$array['F'][]= $rule[0];
}
}else{
if($rule[0]['level']=='A'){
unset($rule[0]['level']);
unset($rule[1]['level']);
$array['A'][]= $rule;
}else if($rule[0]['level']=='B'){
unset($rule[0]['level']);
unset($rule[1]['level']);
$array['B'][]= $rule;
}else if($rule[0]['level']=='C'){
unset($rule[0]['level']);
unset($rule[1]['level']);
$array['C'][]= $rule;
}else if($rule[0]['level']=='D'){
unset($rule[0]['level']);
unset($rule[1]['level']);
$array['D'][]= $rule;
}else if($rule[0]['level']=='E'){
unset($rule[0]['level']);
unset($rule[1]['level']);
$array['E'][]= $rule;
}else if($rule[0]['level']=='F'){
unset($rule[0]['level']);
unset($rule[1]['level']);
$array['F'][]= $rule;
}
}
}
$intention_count = Db::name('tel_intention_rule_template')->where('name',$name)->where('scenarios_id',$scenarios_id)->count('1');
if(empty($id)){
if(!empty($intention_count)){
return returnAjax(3,'模板名已存在',$data);
}
$data_template['scenarios_id'] = $scenarios_id;
$data_template['name']=$name;
$data_template['description']=$description;
$data_template['status']=0;
$template_id = Db::name('tel_intention_rule_template')->insertGetId($data_template);
if(empty($template_id)){
return returnAjax(1,'添加意向等级模板失败~');
}
foreach($array as $key=>$value){
if($key=="A"){
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$va=[];
$va[]=$v;
$data_a['rule']=serialize($va);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_a['rule']=serialize($v);
}
$data_a['scenarios_id'] = $scenarios_id;
$data_a['level'] = 6;
$data_a['name']='  ';
$data_a['type']=0;
$data_a['create_time']=time();
$data_a['template_id']=$template_id;
$data[]=$data_a;
}
}
if($key=="B"){
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vb=[];
$vb[]=$v;
$data_b['rule']=serialize($vb);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_b['rule']=serialize($v);
}
$data_b['scenarios_id'] = $scenarios_id;
$data_b['level'] = 5;
$data_b['name']='  ';
$data_b['type']=0;
$data_b['create_time']=time();
$data_b['template_id']=$template_id;
$data[]=$data_b;
}
}
if($key=="C"){
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vc=[];
$vc[]=$v;
$data_c['rule']=serialize($vc);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_c['rule']=serialize($v);
}
$data_c['scenarios_id'] = $scenarios_id;
$data_c['level'] = 4;
$data_c['name']='  ';
$data_c['type']=0;
$data_c['create_time']=time();
$data_c['template_id']=$template_id;
$data[]=$data_c;
}
}
if($key=="D"){
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vd=[];
$vd[]=$v;
$data_d['rule']=serialize($vd);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_d['rule']=serialize($v);
}
$data_d['scenarios_id'] = $scenarios_id;
$data_d['level'] = 3;
$data_d['name']='  ';
$data_d['type']=0;
$data_d['create_time']=time();
$data_d['template_id']=$template_id;
$data[]=$data_d;
}
}
if($key=="E"){
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$ve=[];
$ve[]=$v;
$data_e['rule']=serialize($ve);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_e['rule']=serialize($v);
}
$data_e['scenarios_id'] = $scenarios_id;
$data_e['level'] = 2;
$data_e['name']='  ';
$data_e['type']=0;
$data_e['create_time']=time();
$data_e['template_id']=$template_id;
$data[]=$data_e;
}
}
if($key=="F"){
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vf=[];
$vf[]=$v;
$data_f['rule']=serialize($vf);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_f['rule']=serialize($v);
}
$data_f['scenarios_id'] = $scenarios_id;
$data_f['level'] = 1;
$data_f['name']='  ';
$data_f['type']=0;
$data_f['create_time']=time();
$data_f['template_id']=$template_id;
$data[]=$data_f;
}
}
}
$rest = Db::name('tel_intention_rule')->insertAll($data);
if(!empty($rest)&&$rest){
return returnAjax(0,'添加意向等级成功');
}else{
return returnAjax(1,'添加意向等级失败!');
}
}else{
if(!empty($intention_count)>2){
return returnAjax(3,'模板名已存在',$data);
}
$data_template['name']=$name;
$data_template['description']=$description;
$ras = Db::name('tel_intention_rule_template')->where(['id'=>$id])->update($data_template);
try{
Db::name('tel_intention_rule')->where(['template_id'=>$id,'level'=>0])->delete();
foreach($array as $key=>$value){
if($key=="A"){
Db::name('tel_intention_rule')->where(['template_id'=>$id,'level'=>6])->delete();
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$va=[];
$va[]=$v;
$data_a['rule']=serialize($va);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_a['rule']=serialize($v);
}
$data_a['update_time']=time();
$data_a['scenarios_id']=$scenarios_id;
$data_a['level'] = 6;
$data_a['name']='  ';
$data_a['type']=0;
$data_a['template_id']=$id;
$rest = Db::name('tel_intention_rule')->insertGetId ($data_a);
}
}
if($key=="B"){
Db::name('tel_intention_rule')->where(['template_id'=>$id,'level'=>5])->delete();
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vb=[];
$vb[]=$v;
$data_b['rule']=serialize($vb);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_b['rule']=serialize($v);
}
$data_b['update_time']=time();
$data_b['scenarios_id']=$scenarios_id;
$data_b['level'] = 5;
$data_b['name']='  ';
$data_b['type']=0;
$data_b['template_id']=$id;
$rest = Db::name('tel_intention_rule')->insertGetId($data_b);
}
}
if($key=="C"){
Db::name('tel_intention_rule')->where(['template_id'=>$id,'level'=>4])->delete();
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vc=[];
$vc[]=$v;
$data_c['rule']=serialize($vc);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_c['rule']=serialize($v);
}
$data_c['update_time']=time();
$data_c['scenarios_id']=$scenarios_id;
$data_c['level'] = 4;
$data_c['name']='  ';
$data_c['type']=0;
$data_c['template_id']=$id;
$rest = Db::name('tel_intention_rule')->insertGetId($data_c);
}
}
if($key=="D"){
Db::name('tel_intention_rule')->where(['template_id'=>$id,'level'=>3])->delete();
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vd=[];
$vd[]=$v;
$data_d['rule']=serialize($vd);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_d['rule']=serialize($v);
}
$data_d['update_time']=time();
$data_d['scenarios_id']=$scenarios_id;
$data_d['level'] = 3;
$data_d['name']='  ';
$data_d['type']=0;
$data_d['template_id']=$id;
$rest = Db::name('tel_intention_rule')->insertGetId($data_d);
}
}
if($key=="E"){
Db::name('tel_intention_rule')->where(['template_id'=>$id,'level'=>2])->delete();
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$ve=[];
$ve[]=$v;
$data_e['rule']=serialize($ve);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_e['rule']=serialize($v);
}
$data_e['update_time']=time();
$data_e['scenarios_id']=$scenarios_id;
$data_e['level'] = 2;
$data_e['name']='  ';
$data_e['type']=0;
$data_e['template_id']=$id;
$rest = Db::name('tel_intention_rule')->insertGetId($data_e);
}
}
if($key=="F"){
Db::name('tel_intention_rule')->where(['template_id'=>$id,'level'=>1])->delete();
foreach($value as $k=>$v){
if(count($v) == count($v,1)){
if($v['key']=='call_status'){
$arr=explode(',',$v['value']);
$v['value']=$arr;
}
$vf=[];
$vf[]=$v;
$data_f['rule']=serialize($vf);
}else{
foreach($v as $v_k=>$v_v){
if($v_v['key']=='call_status'){
$arr=explode(',',$v_v['value']);
$v[$v_k]['value']=$arr;
}
}
$data_f['rule']=serialize($v);
}
$data_f['update_time']=time();
$data_f['scenarios_id']=$scenarios_id;
$data_f['level'] = 1;
$data_f['name']='  ';
$data_f['type']=0;
$data_f['template_id']=$id;
$rest = Db::name('tel_intention_rule')->insertGetId($data_f);
}
}
}
return returnAjax(0,'更新意向等级模板成功');
}catch(\Exception $e){
return returnAjax(1,$e->getMessage());
}
}
}
public function del_intentionlevel(){
$ids = input('del_ids/a','','trim,strip_tags');
$state = input('state','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$key = 'smartivr_tel_intention_rule_'.$sceneId;
$redis = RedisConnect::get_redis_connect();
$redis->del($key);
$del_ids = [];
if(empty($state)){
$where = array();
$where['scenarios_id'] = $sceneId;
$result = Db::name('tel_intention_rule_template')->field('id')->where($where)->order('id','desc')->select();
foreach ($result as $value) {
$del_ids[] = $value['id'];
}
}else{
$del_ids = $ids;
}
Db::startTrans();
try {
Db::name('tel_intention_rule_template')
->where('id','in',$del_ids)
->delete();
Db::name('tel_intention_rule')->where('template_id','in',$del_ids)->delete();
Db::commit();
return returnAjax(0,'成功',$del_ids);
}
catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'失败',$del_ids);
}
}
public function change_intentionlevel_state(){
$intentionId = input('intentionId','','trim,strip_tags');
$intention_status = input('state','','trim,strip_tags');
$sceneId = input('sceneId','','trim,strip_tags');
$data = array();
$data['status'] = 1;
$other_data['status'] = 0;
$state = Db::name('tel_intention_rule_template')->where('id',$intentionId)->update($data);
$other_state = Db::name('tel_intention_rule_template')->where('id','neq',$intentionId)->where('scenarios_id',$sceneId)->update($other_data);
if(empty($other_data)){
return returnAjax(1,'模板启用失败',$state);
}else{
return returnAjax(0,'模板启用成功',$state);
}
}
public function ces(){
if(Request()->isPost()){
$a1 = input('a1','','trim,strip_tags');
$a2 = input('a2','','trim,strip_tags');
$a3 = input('a3','','trim,strip_tags');
$a4 = input('a4','','trim,strip_tags');
$a5 = input('a5','','trim,strip_tags');
$a6 = input('a6','','trim,strip_tags');
$a7 = input('a7','','trim,strip_tags');
$content = $a1.$a2.$a3.$a4.$a5.$a6.$a7;
return returnAjax(0,'模板启用成功',$content);
}
return $this->fetch();
}
public function get_flow_branch()
{
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$page = input('page',1,'trim,strip_tags');
$limit = input('limit',10,'trim,strip_tags');
$branch_processName = input('branch_processName','','trim,strip_tags');
$branch_process_content = input('branch_process_content','','trim,strip_tags');
if(empty($scenarios_id)){
return false;
}
$defualt_keywords = Db::name('tel_knowledge')
->field('type,keyword')
->where([
'scenarios_id'=>0,
'type'=>['in',[2,3,4,5,6]]
])
->select();
$defualt_keyword_datas = [];
foreach($defualt_keywords as $key=>$value){
$defualt_keyword_datas[$value['type']] = $value['keyword'];
}
if(!empty($branch_process_content)){
$where['tfb.keyword']=['like','%'.$branch_process_content.'%'];
}
$where['tsn.scenarios_id']=$scenarios_id;
if(!empty($branch_processName)){
$where['tfb.label']=['like','%'.$branch_processName.'%'];
}else{
$where['tfb.label'] = [['neq',''],['neq','null'],['exp','is not null'],'and'];
}
$datas = Db::name('tel_flow_branch')
->alias('tfb')
->join('tel_flow_node tfn','tfn.id = tfb.flow_id','LEFT')
->join('tel_scenarios_node tsn','tsn.id = tfn.scen_node_id','LEFT')
->field('tfb.id, tfb.type, tsn.name as scenarios_node_name, tfn.name as flow_node_name, tfb.name as flow_branch_name, tfb.label as flow_branch_label, tfb.keyword, tfb.label_status')
->where($where)
->page($page,$limit)
->select();
$i = ($page -1) * $limit +1;
foreach($datas as $key=>$value){
$datas[$key]['key'] = $i;
$i++;
if(empty($value['keyword']) &&isset($defualt_keyword_datas[$value['type']])){
$datas[$key]['keyword'] = $defualt_keyword_datas[$value['type']];
}
}
$count = Db::name('tel_flow_branch')
->alias('tfb')
->join('tel_flow_node tfn','tfn.id = tfb.flow_id','LEFT')
->join('tel_scenarios_node tsn','tsn.id = tfn.scen_node_id','LEFT')
->field('tfb.id, tfb.type, tsn.name as scenarios_node_name, tfn.name as flow_node_name, tfb.name as flow_branch_name, tfb.label as flow_branch_label, tfb.keyword, tfb.label_status')
->where($where)
->count('tfb.id');
$result = [
'datas'=>$datas,
'count'=>$count
];
return returnAjax(0,'成功',$result);
}
public function getLiuchengLable(){
$scenarios_id = input('scenarios_id','','trim,strip_tags');
if(empty($scenarios_id)){
return returnAjax(1,'失败话术id为空');
}
$datas=[];
$where_flow['scenarios_id'] = $scenarios_id;
$where_flow['label_status'] = 1;
$where_flow['flow_label'] = [['neq',''],['neq','null'],['exp','is not null'],'and'];
$tel_flow_node_labels = Db::name('tel_flow_node')->field('flow_label,id')->where($where_flow)->select();
foreach($tel_flow_node_labels as $key=>$tel_flow_node_label){
$datas['flow_label'][$tel_flow_node_label['id']]=$tel_flow_node_label['flow_label'];
}
$where_branch['tfb.label_status'] = 1;
$where_branch['tfb.label'] = [['neq',''],['neq','null'],['exp','is not null'],'and'];
$where_branch['tfn.scenarios_id']=$scenarios_id;
$tel_flow_branch_labels = Db::name('tel_flow_branch')
->alias('tfb')
->join('tel_flow_node tfn','tfn.id = tfb.flow_id','LEFT')
->field('tfb.id, tfb.label as flow_branch_label')
->where($where_branch)
->select();
foreach($tel_flow_branch_labels as $key=>$tel_flow_branch_label){
$datas['branch_label'][$tel_flow_branch_label['id']]=$tel_flow_branch_label['flow_branch_label'];
}
return returnAjax(0,'成功',$datas);
}
public function getWendagLable(){
$scenarios_id = input('scenarios_id','','trim,strip_tags');
if(empty($scenarios_id)){
return returnAjax(1,'失败话术id为空');
}
$datas=[];
$where['scenarios_id'] = $scenarios_id;
$where['label_status'] = 1;
$where['label'] = [['neq',''],['neq','null'],['exp','is not null'],'and'];
$tel_knowledge_labels = Db::name('tel_knowledge')->field('label,id')->where($where)->select();
foreach($tel_knowledge_labels as $key=>$tel_knowledge_label){
$datas[$tel_knowledge_label['id']]= $tel_knowledge_label['label'];
}
return returnAjax(0,'成功',$datas);;
}
public function getYuyiLable(){
$scenarios_id = input('scenarios_id','','trim,strip_tags');
if(empty($scenarios_id)){
return returnAjax(1,'失败话术id为空');
}
$datas=[];
$where['scenarios_id'] = $scenarios_id;
$where['label_status'] = 1;
$where['label'] = [['neq',''],['neq','null'],['exp','is not null'],'and'];
$where['type']=0;
$tel_labels = Db::name('tel_label')->where($where)->select();
foreach($tel_labels as $key=>$tel_label){
$datas[$tel_label['id']]= $tel_label['label'];
}
return returnAjax(0,'成功',$datas);
}
function update_flow_branch_label_status()
{
$id = input('id','','trim,strip_tags');
$label_status = input('label_status','','trim,strip_tags');
if(empty($id)){
return returnAjax(3,'参数错误');
}
if(empty($label_status)){
$label_status = 1;
}else{
$label_status = 0;
}
Db::name('tel_flow_branch')->where('id',$id)->update(['label_status'=>$label_status]);
return returnAjax(0,'成功');
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
public function xiuiufu_moren($flow_node_id){
$arr=[3=>'否定',4=>'拒绝',2=>'肯定',5=>'中性',6=>'未识别'];
foreach($arr as $type=>$name){
$where['flow_id'] = $flow_node_id;
$where['name'] = ['<>',$name];
$where['type'] = $type;
$num_kending_no =  Db::name('tel_flow_branch')->where($where)->count();
if( $num_kending_no >=1 ){
Db::name('tel_flow_branch')->where($where)->update(['name'=>$arr[$type]]);
}
$num_kending =  Db::name('tel_flow_branch')->where(['flow_id'=>$flow_node_id,'name'=>$name,'type'=>$type])->count();
if($num_kending==0){
$res = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>$type])->find();
$data=[];
$data['name']=$name;
$data['flow_id']=$flow_node_id;
$data['is_select']=0;
$data['type']=$type;
$data['keyword']=$res['keyword'];
$data['keyword_py']=$res['keyword_py'];
Db::name('tel_flow_branch')->insert($data);
}
}
}
public function xiufu_moren_knowledge($scenarios_id){
$arr=[7=>'未听清楚',8=>'用户不回答处理',9=>'无法回答用户问题',10=>'连续3次无法回答用户问题'];
foreach($arr as $type =>$name){
$num_kending =  Db::name('tel_knowledge')->where(['scenarios_id'=>$scenarios_id,'type'=>$type])->count();
if($num_kending==0){
if($type!=1){
$res = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>$type])->find();
$data=[];
$data['name']=$name;
$data['scenarios_id']=$scenarios_id;
$data['type']=$type;
$data['keyword']=$res['keyword'];
$data['keyword_py']=$res['keyword_py'];
$data['is_default']=1;
$data['create_time']=time();
$data['update_time']=time();
Db::name('tel_knowledge')->insert($data);
}else{
$res = Db::name('tel_knowledge')->where(['scenarios_id'=>0,'type'=>$type])->find();
$data=[];
$data['name']=$name;
$data['scenarios_id']=$scenarios_id;
$data['type']=$type;
$data['keyword']=$res['keyword'];
$data['keyword_py']=$res['keyword_py'];
$data['is_default']=0;
$data['create_time']=time();
$data['update_time']=time();
Db::name('tel_knowledge')->insert($data);
}
}
}
}
public function scenarios_check(){
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$where_undefined['audio']=['like','%undefined%'];
$where_undefined['scenarios_id']=$scenarios_id;
$tel_corpus_undes = Db::name('tel_corpus')->where($where_undefined)->select();
if(!empty($tel_corpus_undes)){
foreach($tel_corpus_undes as $key=>$value){
if(empty($value['content'])){
Db::name('tel_corpus')->where(['id'=>$value['id']])->delete();
}else{
Db::name('tel_corpus')->where(['id'=>$value['id']])->update(['audio'=>'']);
}
}
}
$this->xiufu_moren_knowledge($scenarios_id);
$is_variable = Db::name('tel_scenarios')->where('id',$scenarios_id)->value('is_variable');
if($is_variable==0){
$audios = Db::name('tel_corpus')->where('scenarios_id',$scenarios_id)->where('src_id','<>',0)->field('src_id,src_type,audio')->select();
$Audio = new Audio();
foreach($audios as $key=>$value){
if(!empty($value['audio'])){
if($Audio->audio_whether_correct($value['audio']) == false){
if($value['src_type'] == 0){
$node_info = Db::name('tel_flow_node')
->alias('fn')
->join('tel_scenarios_node tsn','tsn.id = fn.scen_node_id','LEFT')
->where('fn.id',$value['src_id'])
->field('tsn.name as scenarios_node_name, fn.name as node_name')
->find();
$msg = $node_info['scenarios_node_name'] .' - 流程节点 - '.$node_info['node_name'];
}else if($value['src_type'] == 1){
$node_info = Db::name('tel_knowledge')
->where('id',$value['src_id'])
->value('name');
$msg = '知识库节点 - '.$node_info;
}
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,$msg .'的话术录音格式不正确');
}
}
}
}
$tel_scenarios_nodes = Db::name('tel_scenarios_node')->where(['scenarios_id'=>$scenarios_id])->select();
foreach($tel_scenarios_nodes as $key=>$tel_scenarios_node){
$all_node_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id']])->count('*');
$all_tiao_node_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'type'=>1])->count('*');
if($all_node_num==1&&$all_tiao_node_num==1){
continue;
}else{
$zhuliucheng_id = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ,'type'=>0])->value('id');
$main_node_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ])->count('*');
if($main_node_num==0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'，没有主流程节点,请添加主流程节点');
}
$main_node_tiao_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ,'type'=>1])->count('*');
if( $main_node_tiao_num >0 ){
$main_node_tiaos = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ,'type'=>1])->select();
foreach($main_node_tiaos as $key =>$value){
$res = Db::name('tel_flow_node')->where(['id'=>$value['id']])->update(['pid'=>$zhuliucheng_id]);
if(!empty($res)){
$arr_2[]=$value['name'];
}
}
$str_name = implode('和',$arr_2);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'，跳转节点连线不正确，系统已经帮您修复了，修复的跳转节点名字为---'.$str_name.'请您刷新此页面');
}
if( $main_node_num >1 ){
$arr_1=[];
$main_nodes= Db::name('tel_flow_node')->field('name')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0,'type'=>0])->select();
foreach($main_nodes as $key=>$main_node){
$arr_1[]=$main_node['name'];
}
$str_name = implode('和',$arr_1);
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'，主流程节点过多,只允许有一个主流程节点。'.'请删除'.$str_name.'的连接线并重新连接');
}
$tel_flow_nodes = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'type'=>0])->select();
foreach($tel_flow_nodes as $key=>$tel_flow_node){
$this->xiuiufu_moren($tel_flow_node['id']);
$tel_flow_branch_num = Db::name('tel_flow_branch')->where(['flow_id'=>$tel_flow_node['id'],'is_select'=>1])->count('*');
if($tel_flow_branch_num<=0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'，没有钩取任何流程分支，必须钩取一个');
}
$tel_flow_branchs = Db::name('tel_flow_branch')->field('next_flow_id,name,keyword')->where(['flow_id'=>$tel_flow_node['id'],'is_select'=>1])->select();
foreach($tel_flow_branchs as $key=>$tel_flow_branch){
if(!empty($tel_flow_branch['next_flow_id'])){
$count_next_flow = Db::name('tel_flow_node')->where(['id'=>$tel_flow_branch['next_flow_id'],'scen_node_id'=>$tel_scenarios_node['id']])->count('*');
if( $count_next_flow==0 ||empty($count_next_flow) ){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'-'.$tel_flow_branch['name'].'没有连接下一个节点');
}
}else{
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'-'.$tel_flow_branch['name'].'没有连接下一个节点');
}
$str_count_l=substr_count($tel_flow_branch['keyword'],'(');
if($str_count_l>0){
$str_count_r=substr_count($tel_flow_branch['keyword'],')');
if($str_count_l!=$str_count_r){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'-'.$tel_flow_branch['name'].'关键词异常');
}
}
}
if($is_variable==0){
$tel_corpus_audio = Db::name('tel_corpus')->where(['src_type'=>0,'src_id'=>$tel_flow_node['id'],'scenarios_id'=>$scenarios_id])->value('audio');
if(empty($tel_corpus_audio) ||$tel_corpus_audio==null ||$tel_corpus_audio=='null'){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'没有录音');
}
if(!file_exists(ROOT_PATH.$tel_corpus_audio)){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'没有录音');
}
if(filesize(ROOT_PATH.$tel_corpus_audio)<=0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'没有录音');
}
}
}
$tel_flow_node_no_0s = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id']])->select();
foreach($tel_flow_node_no_0s as $key=>$tel_flow_node_no_0){
$tel_flow_branch_no_0s = Db::name('tel_flow_branch')->field('next_flow_id')->where(['flow_id'=>$tel_flow_node_no_0['id'],'is_select'=>1])->select();
foreach($tel_flow_branch_no_0s as $key_branch =>$tel_flow_branch_no_0){
$brr[]=$tel_flow_branch_no_0['next_flow_id'];
}
if($tel_flow_node_no_0['pid']!=0){
$arr[]=$tel_flow_node_no_0['id'];
}
if(!empty($tel_flow_node_no_0['bridge'])){
$num = Db::name('seat_transfer_numbers')->where('member_id',$tel_flow_node_no_0['bridge'])->count('*');
if( $num<=0 ){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的坐席不存在');
}
$seat_transfer_numbers = Db::name('seat_transfer_numbers')->where('member_id',$tel_flow_node_no_0['bridge'])->select();
foreach($seat_transfer_numbers as $key=>$value){
if(empty($value['number'])){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的坐席号码为空');
}
}
$transfer_line_id = Db::name('admin')->where('id',$tel_flow_node_no_0['bridge'])->value('transfer_line_id');
if(!empty($transfer_line_id)){
$num = Db::name('tel_line_group')->where('id',$transfer_line_id)->count('*');
if( $num<=0 ){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的坐席转接线路不存在');
}
}else{
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的转接线路为空');
}
}
}
$result=array_diff($arr,$brr);
if(!empty($result)){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$this->get_flow_node_name_by_arr($result).'没有任何分支节点连接当前流程');
}
}
}
$tel_knowledges = Db::name('tel_knowledge')->where(['scenarios_id'=>$scenarios_id])->select();
foreach($tel_knowledges as $key_know=>$tel_knowledge){
if($tel_knowledge['type']==1 &&$tel_knowledge['action']!=2){
if($is_variable==0){
$know_corpus_audio = Db::name('tel_corpus')->where(['src_type'=>1,'src_id'=>$tel_knowledge['id'],'scenarios_id'=>$scenarios_id])->value('audio');
if(empty($know_corpus_audio) ||$know_corpus_audio==null ||$know_corpus_audio=='null'){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'知识库：'.$tel_knowledge['name'].'没有录音');
}
if(!file_exists(ROOT_PATH.$know_corpus_audio)){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'知识库：'.$tel_knowledge['name'].'没有录音');
}
if(filesize(ROOT_PATH.$know_corpus_audio)<=0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'知识库：'.$tel_knowledge['name'].'没有录音');
}
}
if(empty($tel_knowledge['keyword'])){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'知识库：'.$tel_knowledge['name'].'没有关键词');
}
}
$str_know_count_l=substr_count($tel_knowledge['keyword'],'(');
if($str_know_count_l>0){
$str_know_count_r=substr_count($tel_knowledge['keyword'],')');
if($str_know_count_l!=$str_know_count_r){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return returnAjax(1,'知识库：'.$tel_knowledge['name'].'关键词异常');
}
}
}
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>0,'update_time'=>time()]);
return returnAjax(0,'检测完成没有发现问题');
}
public function scenarios_check_by_xiafa($scenarios_id){
$where_undefined['audio']=['like','%undefined%'];
$where_undefined['scenarios_id']=$scenarios_id;
$tel_corpus_undes = Db::name('tel_corpus')->where($where_undefined)->select();
if(!empty($tel_corpus_undes)){
foreach($tel_corpus_undes as $key=>$value){
if(empty($value['content'])){
Db::name('tel_corpus')->where(['id'=>$value['id']])->delete();
}else{
Db::name('tel_corpus')->where(['id'=>$value['id']])->update(['audio'=>'']);
}
}
}
$is_variable = Db::name('tel_scenarios')->where('id',$scenarios_id)->value('is_variable');
if($is_variable==0){
$audios = Db::name('tel_corpus')->where('scenarios_id',$scenarios_id)->where('src_id','<>',0)->field('src_id,src_type,audio')->select();
$Audio = new Audio();
foreach($audios as $key=>$value){
if(!empty($value['audio'])){
if($Audio->audio_whether_correct($value['audio']) == false){
if($value['src_type'] == 0){
$node_info = Db::name('tel_flow_node')
->alias('fn')
->join('tel_scenarios_node tsn','tsn.id = fn.scen_node_id','LEFT')
->where('fn.id',$value['src_id'])
->field('tsn.name as scenarios_node_name, fn.name as node_name')
->find();
$msg = $node_info['scenarios_node_name'] .' - 流程节点 - '.$node_info['node_name'];
}else if($value['src_type'] == 1){
$node_info = Db::name('tel_knowledge')
->where('id',$value['src_id'])
->value('name');
$msg = '知识库节点 - '.$node_info;
}
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,$msg .'的话术录音格式不正确'];
}
}
}
}
$tel_scenarios_nodes = Db::name('tel_scenarios_node')->where(['scenarios_id'=>$scenarios_id])->select();
foreach($tel_scenarios_nodes as $key=>$tel_scenarios_node){
$all_node_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id']])->count('*');
$all_tiao_node_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'type'=>1])->count('*');
if($all_node_num==1&&$all_tiao_node_num==1){
continue;
}else{
$zhuliucheng_id = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ,'type'=>0])->value('id');
$main_node_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ,'type'=>0])->count('*');
if($main_node_num==0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'，没有主流程节点,请添加主流程节点'];
}
if( $main_node_num >1 ){
$arr_1=[];
$main_nodes= Db::name('tel_flow_node')->field('name')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0,'type'=>0])->select();
foreach($main_nodes as $key=>$main_node){
$arr_1[]=$main_node['name'];
}
$str_name = implode('和',$arr_1);
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'，主流程节点过多,只允许有一个主流程节点。'.'请删除'.$str_name.'的连接线并重新连接'];
}
$main_node_tiao_num = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ,'type'=>1])->count('*');
if( $main_node_tiao_num >0 ){
$main_node_tiaos = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'pid'=>0 ,'type'=>1])->select();
foreach($main_node_tiaos as $key =>$value){
$res = Db::name('tel_flow_node')->where(['id'=>$value['id']])->update(['pid'=>$zhuliucheng_id]);
if(!empty($res)){
$arr_2[]=$value['name'];
}
}
}
$tel_flow_nodes = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id'],'type'=>0])->select();
foreach($tel_flow_nodes as $key=>$tel_flow_node){
$tel_flow_branch_num = Db::name('tel_flow_branch')->where(['flow_id'=>$tel_flow_node['id'],'is_select'=>1])->count('*');
if($tel_flow_branch_num<=0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'，没有钩取任何流程分支，必须钩取一个'];
}
$tel_flow_branchs = Db::name('tel_flow_branch')->field('next_flow_id,name,keyword')->where(['flow_id'=>$tel_flow_node['id'],'is_select'=>1])->select();
foreach($tel_flow_branchs as $key=>$tel_flow_branch){
if(!empty($tel_flow_branch['next_flow_id'])){
$count_next_flow = Db::name('tel_flow_node')->where(['id'=>$tel_flow_branch['next_flow_id'],'scen_node_id'=>$tel_scenarios_node['id']])->count('*');
if( $count_next_flow==0 ||empty($count_next_flow) ){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'-'.$tel_flow_branch['name'].'没有连接下一个节点'];
}
}else{
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'-'.$tel_flow_branch['name'].'没有连接下一个节点'];
}
$str_count_l=substr_count($tel_flow_branch['keyword'],'(');
if($str_count_l>0){
$str_count_r=substr_count($tel_flow_branch['keyword'],')');
if($str_count_l!=$str_count_r){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'-'.$tel_flow_branch['name'].'关键词异常'];
}
}
}
if($is_variable==0){
$tel_corpus_audio = Db::name('tel_corpus')->where(['src_type'=>0,'src_id'=>$tel_flow_node['id'],'scenarios_id'=>$scenarios_id])->value('audio');
if(empty($tel_corpus_audio) ||$tel_corpus_audio==null ||$tel_corpus_audio=='null'){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'没有录音'];
}
if(!file_exists(ROOT_PATH.$tel_corpus_audio)){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'没有录音'];
}
if(filesize(ROOT_PATH.$tel_corpus_audio)<=0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$tel_flow_node['name'].'没有录音'];
}
}
}
$tel_flow_node_no_0s = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'scen_node_id'=>$tel_scenarios_node['id']])->select();
foreach($tel_flow_node_no_0s as $key=>$tel_flow_node_no_0){
$tel_flow_branch_no_0s = Db::name('tel_flow_branch')->field('next_flow_id')->where(['flow_id'=>$tel_flow_node_no_0['id'],'is_select'=>1])->select();
foreach($tel_flow_branch_no_0s as $key_branch =>$tel_flow_branch_no_0){
$brr[]=$tel_flow_branch_no_0['next_flow_id'];
}
if($tel_flow_node_no_0['pid']!=0){
$arr[]=$tel_flow_node_no_0['id'];
}
if(!empty($tel_flow_node_no_0['bridge'])){
$num = Db::name('seat_transfer_numbers')->where('member_id',$tel_flow_node_no_0['bridge'])->count('*');
if( $num<=0 ){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的坐席不存在'];
}
$seat_transfer_numbers = Db::name('seat_transfer_numbers')->where('member_id',$tel_flow_node_no_0['bridge'])->select();
foreach($seat_transfer_numbers as $key=>$value){
if(empty($value['number'])){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的坐席号码为空'];
}
}
$transfer_line_id = Db::name('admin')->where('id',$tel_flow_node_no_0['bridge'])->value('transfer_line_id');
if(!empty($transfer_line_id)){
$num = Db::name('tel_line_group')->where('id',$transfer_line_id)->count('*');
if( $num<=0 ){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的坐席转接线路不存在'];
}
}else{
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'--节点名字：'.$tel_flow_node_no_0['name'].'的转接线路为空'];
}
}
}
$result=array_diff($arr,$brr);
if(!empty($result)){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'流程：'.$this->get_scenarios_node_name($tel_scenarios_node['id']).'-'.$this->get_flow_node_name_by_arr($result).'没有任何分支节点连接当前流程'];
}
}
}
$tel_knowledges = Db::name('tel_knowledge')->where(['scenarios_id'=>$scenarios_id])->select();
foreach($tel_knowledges as $key_know=>$tel_knowledge){
if($tel_knowledge['type']==1 &&$tel_knowledge['action']!=2){
if($is_variable==0){
$know_corpus_audio = Db::name('tel_corpus')->where(['src_type'=>1,'src_id'=>$tel_knowledge['id'],'scenarios_id'=>$scenarios_id])->value('audio');
if(empty($know_corpus_audio) ||$know_corpus_audio==null ||$know_corpus_audio=='null'){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'知识库：'.$tel_knowledge['name'].'没有录音'];
}
if(!file_exists(ROOT_PATH.$know_corpus_audio)){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'知识库：'.$tel_knowledge['name'].'没有录音'];
}
if(filesize(ROOT_PATH.$know_corpus_audio)<=0){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'知识库：'.$tel_knowledge['name'].'没有录音'];
}
}
if(empty($tel_knowledge['keyword'])){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'知识库：'.$tel_knowledge['name'].'没有关键词'];
}
}
$str_know_count_l=substr_count($tel_knowledge['keyword'],'(');
if($str_know_count_l>0){
$str_know_count_r=substr_count($tel_knowledge['keyword'],')');
if($str_know_count_l!=$str_know_count_r){
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>1,'update_time'=>time()]);
return [false,'知识库：'.$tel_knowledge['name'].'关键词异常'];
}
}
}
Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->update(['check_statu'=>0,'update_time'=>time()]);
return [true,''];
}
public function get_train_info(){
$id = input('id','','trim,strip_tags');
$where = [];
$where['id'] = array('eq',$id);
$learning_info= Db::name('tel_learning')->where($where)->find();
if($learning_info){
return returnAjax(1,'获取成功',$learning_info);
}else{
return returnAjax(1,'获取失败');
}
}
public function sms_send_verification(){
$p_user = new Users;
header('Access-Control-Allow-Origin:*');
if (Request::instance()->isPost()){
$phone = input('phone','','trim,strip_tags');
$regex = '/^1(?:3\d|4[4-9]|5[0-35-9]|6[67]|7[013-8]|8\d|9\d)\d{8}$/';
if(preg_match($regex,$phone) === false){
return returnAjax(1,'手机号码格式错误');
}
$username = $p_user->sms_username;
$password = md5($p_user->sms_username .md5($p_user->sms_password));
$url = $p_user->sms_url;
$sms_verify_code = rand(123456,987654);
$content = "【AI智能语音】".'您的手机验证码为'.$sms_verify_code.'，请在1分钟内填写验证码！';
$param = http_build_query(
array(
'username'=>$username,
'password'=>$password,
'mobile'=>$phone,
'content'=>$content,
)
);
$length = mb_strlen($content);
$count = ceil($length/60);
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_HEADER,0);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,$param);
$result = curl_exec($ch);
curl_close($ch);
if($result >0){
$Redis = RedisConnect::get_redis_connect();
$sms_token = time() .substr(microtime(),2,5);
$Redis->setex('create_user_phone_'.$sms_token,80,$phone);
$Redis->setex('sms_verify_code_'.$sms_token,80,$sms_verify_code);
setcookie('sms_token',$sms_token,time() +60,'/');
return returnAjax(0,'发送成功',['sms_token'=>$sms_token]);
}else{
return returnAjax(1,'发送失败');
}
}else{
header('HTTP/1.1 404 Not Found');
}
}
public function join_verification(){
try {
$sms_token_id = $_COOKIE['sms_token'];
if(!$sms_token_id){
return returnAjax(1,'参数错误2');
}
$Redis = RedisConnect::get_redis_connect();
$r_phone  = 'create_user_phone_'.$sms_token_id;
$r_verify  = 'sms_verify_code_'.$sms_token_id;
$get_phone = $Redis->get($r_phone);
$get_verify = $Redis->get($r_verify);
if(!$get_phone ||!$get_verify){
return returnAjax(1,'参数错误');
}
}catch (Exception $e) {
return returnAjax(1,'验证码超时',$e->getMessage());
}
$data['phone'] = $get_phone ;
$data['verify'] = $get_verify ;
$phone = input('phone','','trim,strip_tags');
$code = input('code','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$export = input('export','','trim,strip_tags');
$export_name = input('export_name','','trim,strip_tags');
if($get_phone != $phone){
return returnAjax(1,'提交的号码与接收短信的手机号码不一致');
}
if($get_verify != $code){
return returnAjax(1,'验证码错误');
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = [];
$data['owner'] = $uid;
$data['phone'] = $phone;
$data['ip_address'] = $_SERVER["REMOTE_ADDR"];
$data['export'] = $export;
$data['export_name'] = $export_name;
$data['create_time'] = time();
$data['note'] = $remark;
$res = Db::name('export_record')->insert($data);
if($res){
return returnAjax(0,'确认成功');
}else{
return returnAjax(1,'确认失败');
}
}
public function get_sendout_staff(){
$id = input('id','','trim,strip_tags');
$scenarios_info = Db::name('tel_scenarios')->where('id',$id)->find();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['a.pid'] = array('eq',$uid);
$where['a.role_id'] = array('neq',20);
$where['a.status'] = array('eq',1);
$role_id = input('role_id','','trim,strip_tags');
if($role_id){
$where['a.role_id'] = array('eq',$role_id);
}
$role_list = Db::name('admin')
->alias('a')
->join('admin_role r','a.role_id = r.id','LEFT')
->where($where)
->field('a.id,a.username,a.role_id,r.name as role_name')
->select();
$role_name = $this->assoc_unique($role_list,'role_id');
$data = [];
$data['scenarios_name'] = $scenarios_info['name'];
$data['role_list'] = $role_list;
$data['role_name'] = $role_name;
return returnAjax(1,'',$data);
}
function assoc_unique($arr,$key) {
$tmp_arr = array();
foreach ($arr as $k =>$v) {
if (in_array($v[$key],$tmp_arr)) {
unset($arr[$k]);
}else {
$tmp_arr[] = $v[$key];
}
}
array_multisort(array_column($arr,$key),SORT_ASC,$arr);
return $arr;
}
public function give_subordinate(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$role_id = input('role_id','','trim,strip_tags');
$username = input('username/a','','trim,strip_tags');
$scene_remarks = input('scene_remarks','','trim,strip_tags');
$chaos_num = input('chaos_num','','trim,strip_tags');
$arr_check = $this->scenarios_check_by_xiafa($scenarios_id);
if($arr_check[0]==false){
return returnAjax(0,'当前话术存在异常，请修复后再重新下发');
}
$user_count = count($username);
$attitude = 0;
Db::startTrans();
try {
foreach($username as $user_key=>$user_id){
$scenarios_info = Db::name('tel_scenarios')->where('id',$scenarios_id)->find();
$get_scenarios_name = DB::name('tel_scenarios')->where(['name'=>$scenarios_info['name'],'member_id'=>$user_id])->count();
if($get_scenarios_name == 0){
$new_scenariosname = $scenarios_info['name'];
}else{
$i = 1;
while($get_scenarios_name >0 ){
$new_scenariosname = $scenarios_info['name'].'_('.$i.')';
$get_scenarios_name = DB::name('tel_scenarios')->where(['name'=>$new_scenariosname,'member_id'=>$user_id])->count();
$i++;
};
}
$res = $this->sendout_copyScene($new_scenariosname,$scenarios_id,$user_id,$chaos_num,$user_count,$attitude);
if($res){
$data = [];
$data['owner'] = $uid;
$data['scenarios_name'] = $scenarios_id;
$data['username'] = $user_id;
$data['role_id'] = $role_id;
$data['create_time'] = time();
$data['remark'] = $scene_remarks;
$data['scenarios_new_id'] = $res;
$is_int = Db::name('sendout_scenarios')->insert($data);
if(!$is_int){
Db::rollback();
return returnAjax(0,'下发失败1');
}
}else{
Db::rollback();
return returnAjax(0,'下发失败2');
}
}
$RedisConnect = RedisConnect::get_redis_connect();
$attitude = 95 ;
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +5;
$RedisConnect->set($key,$attitude);
$RedisConnect->del($key);
Db::commit();
return returnAjax(1,'下发成功');
}
catch (\Exception $e) {
halt($e->getLine());
Db::rollback();
return returnAjax(0,'下发失败3');
}
}
public function sendout_copyScene($scenariosname ,$scenarios_id ,$username ,$chaos_num,$user_count = 1,&$attitude){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$scenarios = Db::name('tel_scenarios')
->field('name,member_id,type,is_tpl,status,break,auditing,is_variable')
->where('id',$scenarios_id)->find();
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$attitude += intval(30 / $user_count);
$RedisConnect->set($key,$attitude);
$arr_x = [];
$tscen = array();
$tscen['name'] = $scenariosname;
$tscen['member_id'] = $username;
$tscen["type"] = $scenarios["type"];
$tscen['is_tpl'] = $scenarios["is_tpl"];
$tscen['status'] = $scenarios["status"];
$tscen['break'] = $scenarios["break"];
$tscen['auditing'] = $scenarios["auditing"];
$tscen['is_variable'] = $scenarios["is_variable"];
$tscen['update_time'] = time();
$newId = Db::name('tel_scenarios')->insertGetId($tscen);
if($scenarios['is_variable']==1){
$variables = Db::name('audio_variable')->where(['scenarios_id'=>$scenarios_id])->select();
foreach($variables as $key =>$variable){
$variables[$key]['scenarios_id'] = $newId;
unset($variables[$key]['id']);
}
Db::name('audio_variable')->insertAll($variables);
}
$result = Db::name('tel_scenarios_node')->field('id,name,sort,type')->where('scenarios_id',$scenarios_id)->select();
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +intval(10 / $user_count);
$RedisConnect->set($key,$attitude);
$tsarray = array();
foreach ($result as $key =>$value) {
$tsdata = array();
$tsdata['scenarios_id'] = $newId;
$tsdata['name'] = $value["name"];
$tsdata['sort'] = $value["sort"];
$tsdata['type'] = $value["type"];
$TRresult = Db::name('tel_scenarios_node')->insertGetId($tsdata);
$tsarray[$value["id"]] = $TRresult;
$flowList = Db::name('tel_flow_node')->where('scen_node_id',$value['id'])->order("pid asc")->select();
$newfnlist = array();
foreach ($flowList as $fnkey =>$fnval) {
$fndata = array();
$fndata['scenarios_id'] = $newId;
$fndata['scen_node_id'] = $TRresult;
$fndata['name'] = $fnval["name"];
$fndata['break'] = $fnval["break"];
$fndata['type'] = $fnval["type"];
$fndata['position'] = $fnval["position"];
if($fnval["pid"] == 0){
$fndata['pid'] = 0;
}
$fndata['action'] = $fnval["action"];
$fndata['action_id'] = $fnval["action_id"];
$fndata['flow_label'] = $fnval["flow_label"];
$fndata['pause_time'] = $fnval["pause_time"];
$fndata['bridge'] = 0;
$fndata['is_variable'] = $fnval["is_variable"];
$fnresult = Db::name('tel_flow_node')->insertGetId($fndata);
if(!empty($fnval["no_speak_knowledge_id"])){
$arr_x[$fnresult] = $fnval["no_speak_knowledge_id"];
}
$newfnlist[$fnval["id"]] = $fnresult;
if($fnval["type"] == 0){
$label = Db::name('tel_label')->where(array('flow_id'=>$fnval["id"],'type'=>1))->find();
if(!empty($label)){
$insertlabel = array();
$insertlabel['flow_id'] = $fnresult;
$insertlabel['type'] = $label["type"];
$insertlabel['member_id'] = $username;
$insertlabel['scenarios_id'] = $newId;
$insertlabel['level'] = $label['level'];
$insertlabel['query_order'] = $label['query_order'];
$insertlabel['label'] = $label["label"];
$insertlabel['keyword'] = $label["keyword"];
Db::name('tel_label')->insertGetId($insertlabel);
}
}
}
foreach ($newfnlist as $nkey =>$nval) {
$data = array();
foreach ($flowList as $okey =>$oval) {
if($oval["id"] == $nkey){
if($oval["pid"] >0){
if(isset($newfnlist[$oval["pid"]])){
$data['pid'] = $newfnlist[$oval["pid"]];
}else{
$data['pid'] = 1;
}
}else{
$data['pid'] = 0;
}
}
}
$result = Db::name('tel_flow_node')->where('id',$nval)->update($data);
}
foreach ($flowList as $fbkey =>$fbval) {
$res = Db::name('tel_corpus')->field('content,audio,src_type,is_variable')
->where(array("src_id"=>$fbval["id"],'src_type'=>0))
->find();
$itemcs = array();
$itemcs['scenarios_id'] = $newId;
$itemcs['src_type'] = $res["src_type"];
$itemcs['src_id'] = $newfnlist[$fbval["id"]];
$itemcs['content'] = $res["content"];
$itemcs['audio'] = $res["audio"];
$itemcs['is_variable'] = $res["is_variable"];
if($res["src_type"]===0){
$csresult = Db::name('tel_corpus')->insertGetId($itemcs);
}
$fbList = Db::name('tel_flow_branch')->where('flow_id',$fbval['id'])->order("id asc")->select();
foreach ($fbList as $itemfb =>$vfb) {
$fbdata = array();
$fbdata['flow_id'] = $newfnlist[$vfb["flow_id"]];
$fbdata['name'] = $vfb["name"];
$fbdata['keyword'] = $vfb["keyword"];
$fbdata['keyword_py'] = $vfb["keyword_py"];
if(!empty($vfb["next_flow_id"])){
if(!empty($newfnlist[$vfb["next_flow_id"]])){
$fbdata['next_flow_id'] = $newfnlist[$vfb["next_flow_id"]];
}
}
$fbdata['is_select'] = $vfb["is_select"];
$fbdata['type'] = $vfb["type"];
$fbdata['label'] = $vfb["label"];
$fbdata['label_status'] = $vfb["label_status"];
$fbdata['query_type'] = $vfb["query_type"];
$fbdata['order_by'] = $vfb["order_by"];
$snresult = Db::name('tel_flow_branch')->insertGetId($fbdata);
}
}
}
$where_lables['scenarios_id']=$scenarios_id;
$where_lables['flow_id']=['exp','is null'];
$tel_labels = Db::name('tel_label')->where($where_lables)->select();
foreach($tel_labels as $key=>$tel_label){
$ldata = array();
$ldata['flow_id'] = $tel_label['flow_id'];
$ldata['type'] = $tel_label["type"];
$ldata['member_id'] = $username;
$ldata['scenarios_id'] = $newId;
$ldata['label'] = $tel_label["label"];
$ldata['keyword'] = $tel_label["keyword"];
$ldata['level'] = $tel_label['level'];
$ldata['query_order'] = $tel_label['query_order'];
$snresult = Db::name('tel_label')->insertGetId($ldata);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +intval(30 / $user_count);
$RedisConnect->set($key,$attitude);
foreach ($tsarray as $ntskey =>$ntsvalue) {
$nflowList = Db::name('tel_flow_node')->where('scen_node_id',$ntsvalue)->order("pid asc")->select();
foreach ($nflowList as $okey =>$oval) {
if($oval["type"] == 1 &&$oval["action"] == 2 &&$oval["action_id"]){
$data = array();
$data['action_id'] = isset($tsarray[$oval["action_id"]]) ?$tsarray[$oval["action_id"]] :0;
$result = Db::name('tel_flow_node')->where('id',$oval["id"])->update($data);
}
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +intval(10 / $user_count);
$RedisConnect->set($key,$attitude);
$tfnw = array();
$tfnw['scenarios_id'] = $scenarios_id;
$knresult = Db::name('tel_knowledge')
->where($tfnw)
->select();
foreach ($knresult as $knkey =>$knval) {
$kndata = array();
$kndata['scenarios_id'] = $newId;
$kndata['name'] = $knval["name"];
$kndata['type'] = $knval["type"];
$kndata['keyword'] = $knval["keyword"];
$kndata['keyword_py'] = $knval["keyword_py"];
$kndata['action'] = $knval["action"];
$kndata['action_id'] = $knval["action_id"];
if($knval["action"] == 2){
$kndata['action_id'] = isset($tsarray[$knval["action_id"]]) ?$tsarray[$knval["action_id"]] : 0;
}
$kndata['intention'] = $knval["intention"];
$kndata['create_time'] = time();
$kndata['update_time'] = time();
$kndata['pause_time'] = $knval["pause_time"];
$kndata['label'] = $knval["label"];
$kndata['label_status'] = $knval["label_status"];
$kndata['is_default'] = $knval["is_default"];
$kndata['sms_template_id'] = $knval["sms_template_id"];
$kndata['bridge'] = $knval["bridge"];
$kndata['query_type'] = $knval["query_type"];
$knNewRes = Db::name('tel_knowledge')->insertGetId($kndata);
foreach($arr_x as $key=>$value){
if($knval['id']==$value){
Db::name('tel_flow_node')->where(['id'=>$key])->update(['no_speak_knowledge_id'=>$knNewRes]);
}
}
$res = Db::name('tel_corpus')
->where(array("src_id"=>$knval["id"],'src_type'=>1))
->select();
$temp = array();
foreach ($res as $rkey =>$rval) {
$tempcs = array();
$tempcs['src_id'] = $knNewRes;
$tempcs['content'] = $rval["content"];
$tempcs['src_type'] = 1;
$tempcs['source'] = $rval["source"];
$tempcs['audio'] = $rval["audio"];
$tempcs['scenarios_id'] = $newId;
$tempcs['file_name'] = $rval["file_name"];
$tempcs['file_size'] = $rval["file_size"];
$tempcs['is_variable'] = $rval["is_variable"];
array_push($temp,$tempcs);
}
$csresult = Db::name('tel_corpus')->insertAll($temp);
$label = Db::name('tel_label')->where(array('flow_id'=>$knval["id"],'type'=>2))->find();
if(!empty($label)){
$insertlabel = array();
$insertlabel['flow_id'] = $knNewRes;
$insertlabel['type'] = $label["type"];
$insertlabel['scenarios_id'] = $newId;
$insertlabel['member_id'] = $username;
$insertlabel['label'] = $label["label"];
$insertlabel['keyword'] = $label["keyword"];
Db::name('tel_label')->insertGetId($insertlabel);
}
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +intval(10 / $user_count);
$RedisConnect->set($key,$attitude);
$tirres_template = Db::name('tel_intention_rule_template')
->where('scenarios_id',$scenarios_id)
->select();
foreach($tirres_template as $key =>$value){
$tempz = array();
$tempz['scenarios_id'] = $newId;
$tempz['name'] = $value['name'];
$tempz['description'] = $value['description'];
$tempz['status'] = $value['status'];
$res_id = Db::name('tel_intention_rule_template')->insertGetId($tempz);
$listrule = Db::name('tel_intention_rule')->where(['scenarios_id'=>$scenarios_id,'template_id'=>$value['id']])->select();
$templates = array();
foreach($listrule as $k =>$v){
$tempy = array();
$tempy['scenarios_id'] = $newId;
$tempy['template_id'] = $res_id;
$tempy['name'] = $v['name'];
$tempy['level'] = $v['level'];
$tempy['type'] = $v['type'];
$tempy['rule'] = $v['rule'];
$tempy['sort'] = $v['sort'];
$tempy['status'] = $v['status'];
$tempy['create_time'] = time();
$tempy['update_time'] = time();
array_push($templates,$tempy);
}
Db::name('tel_intention_rule')->insertAll($templates);
}
$key = 'task_'.$chaos_num .'_count';
$attitude = $attitude +intval(5 / $user_count);
$RedisConnect->set($key,$attitude);
$scenarios_config = Db::name('tel_scenarios_config')
->where('scenarios_id',$scenarios_id)
->find();
$s_config = array();
$s_config['scenarios_id'] = $newId;
$s_config['pause_play_ms'] = $scenarios_config['pause_play_ms'] ?$scenarios_config['pause_play_ms'] : 0;
$s_config['min_speak_ms'] = $scenarios_config['min_speak_ms']?$scenarios_config['min_speak_ms']:0;
$s_config['max_speak_ms'] = $scenarios_config['max_speak_ms']?$scenarios_config['max_speak_ms']:0;
$s_config['volume'] = $scenarios_config['volume']?$scenarios_config['volume']:0;
$s_config['filter_level'] = $scenarios_config['filter_level']?$scenarios_config['filter_level']:0;
$sce_config =  Db::name('tel_scenarios_config')->insert($s_config);
if($newId){
return $newId;
}else{
return false;
}
}
public function sendout_scene(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['a.pid'] = array('eq',$uid);
$where['a.role_id'] = array('neq',20);
$where['a.status'] = array('eq',1);
$role_list = Db::name('admin')
->alias('a')
->join('admin_role r','a.role_id = r.id','LEFT')
->where($where)
->group('a.role_id')
->column('r.name','role_id');
$this->assign('role_list',$role_list);
return $this->fetch();
}
public function ajax_sendout_scene(){
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$scenarios_name = input('scenarios_name','','trim,strip_tags');
$role_id = input('role_id','','trim,strip_tags');
$userName = input('userName','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if(!$page){
$page = 1;
}
if(!$limit){
$limit = 10;
}
$where = [];
if($scenarios_name){
$where['bo.name'] = array('like','%'.$scenarios_name.'%');
}
if($role_id){
$where['se.role_id'] = array('eq',$role_id);
}
if($userName){
$where['ad.username'] = array('like','%'.$userName.'%');
}
$where['se.owner'] =array('eq',$uid);
$count = DB::name('sendout_scenarios')
->alias('se')
->join('tel_scenarios bo','se.scenarios_name = bo.id','LEFT')
->join('admin ad','ad.id = se.username','LEFT')
->join('admin_role ro','ro.id = se.role_id','LEFT')
->where($where)
->count();
$page_count = ceil($count/$limit);
if($page >$page_count){
$page = $page_count -1;
}
$list = DB::name('sendout_scenarios')
->alias('se')
->join('tel_scenarios bo','se.scenarios_name = bo.id','LEFT')
->join('admin ad','ad.id = se.username','LEFT')
->join('admin_role ro','ro.id = se.role_id','LEFT')
->where($where)
->field('se.*,bo.name as scenarios_name,ad.username,ro.name')
->order('id','desc')
->page($page,$limit)
->select();
foreach($list as $key =>$v){
$list[$key]['create_time'] = date('Y-m-d H:i',$v['create_time']);
if(!$v['scenarios_name']){
$list[$key]['scenarios_name'] = "话术已删除";
}
if(!$v['username']){
$list[$key]['username'] = "用户已删除";
}
}
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function del_sendout_scene(){
$id = input('id','','trim,strip_tags');
$sendout_scenarios_info = Db::name('sendout_scenarios')->where('id',$id)->find();
$scenariosId= $sendout_scenarios_info['scenarios_new_id'];
Db::startTrans();
try {
Db::name('tel_scenarios')->where('id',$scenariosId)->delete();
Db::name('tel_scenarios_node')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_flow_node')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_corpus')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_knowledge')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_intention_rule')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_learning')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_intention_rule_template')->where('scenarios_id',$scenariosId)->delete();
Db::name('tel_scenarios_config')->where('scenarios_id',$scenariosId)->delete();
DB::name('sendout_scenarios')->where('id',$id)->delete();
Db::commit();
return returnAjax(0,'删除成功');
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'删除失败');
}
}
public function get_variable() {
$id = input('id','','trim,strip_tags');
$where = [];
$where['id'] = array('eq',$id);
$info = Db::name('audio_variable')->where($where)->find();
if ($info) {
return returnAjax(0,'获取变量数据成功',$info);
}else {
return returnAjax(1,'获取变量数据失败',$info);
}
}
public function get_scenarios_type(){
$id = input('sceneId','','trim,strip_tags');
$is_variable = Db::name('tel_scenarios')->where('id',$id)->value('is_variable');
return returnAjax(0,'获取话术类型成功',$is_variable);
}
public function del_variable() {
$id = input('id','','trim,strip_tags');
$ret = Db::name('audio_variable')->where('id',$id)->delete();
if ($ret) {
return returnAjax(0,'语料变量删除成功');
}else {
return returnAjax(1,'语料变量删除失败');
}
}
public function get_variable_explain() {
$name = input('name','','trim,strip_tags');
$name = explode(',',$name);
$id = input('id','','trim,strip_tags');
$where = [];
$i = input('i','','trim,strip_tags');
$where['scenarios_id'] = array('eq',$id);
$forin = [];
foreach($name as $key =>$vo){
$where['variable_name'] = array('eq',$vo);
$info = Db::name('audio_variable')->where($where)->find();
if (!$info) {
$info['results'] = '变量错误';
}
$forin['list'][]= $info;
$forin['i'] = $i ;
}
return returnAjax(0,'获取变量注解失败',$forin);
}
public function edit_variable() {
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$variable_name = input('variable_name','','trim,strip_tags');
$annotation = input('annotation','','trim,strip_tags');
$example = input('example','','trim,strip_tags');
$id = input('id','','trim,strip_tags');
$data = [];
$data['variable_name'] = $variable_name;
$data['annotation'] = $annotation;
$data['example'] = $example;
if ($id) {
$where = [];
$where['variable_name'] = array('eq',$variable_name);
$where['scenarios_id'] = array('eq',$scenarios_id);
$count = Db::name('audio_variable')->where($where)->count('id');
if ($count >1) {
return returnAjax(1,'语料变量是唯一的');
}
$where = [];
$where['id'] = array('eq',$id);
$ret = Db::name('audio_variable')->where($where)->update($data);
if ($ret) {
return returnAjax(0,'语料变量修改成功');
}else {
return returnAjax(1,'语料变量修改失败');
}
}else {
$where = [];
$where['variable_name'] = array('eq',$variable_name);
$where['scenarios_id'] = array('eq',$scenarios_id);
$count = Db::name('audio_variable')->where($where)->count('id');
if ($count >= 1) {
return returnAjax(1,'语料变量是唯一的');
}
$data['scenarios_id'] = $scenarios_id;
$ret = Db::name('audio_variable')->insert($data);
if ($ret) {
return returnAjax(0,'语料变量添加成功');
}else {
return returnAjax(1,'语料变量添加失败');
}
}
}
public function Variabl_template() {
$scenarios_id = input('id','','trim,strip_tags');
$where['scenarios_id'] = array('eq',$scenarios_id);
$objPHPExcel = new \PHPExcel();
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$PHPSheet = $objPHPExcel->getactivesheet();
$objPHPExcel->createSheet();
$objPHPExcel->setactivesheetindex();
$PHPSheet = $objPHPExcel->getactivesheet();
$list = Db::name('audio_variable')->where($where)->select();
$PHPSheet->setCellValue($letter[0].(1),'姓名');
$objPHPExcel->getActiveSheet()->getStyle($letter[0])->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
$objPHPExcel->getActiveSheet()->getColumnDimension($letter[0])->setWidth(20);
$PHPSheet->setCellValue($letter[1].(1),'电话');
$objPHPExcel->getActiveSheet()->getStyle($letter[1])->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
$objPHPExcel->getActiveSheet()->getColumnDimension($letter[1])->setWidth(20);
if ($list) {
foreach ($list as $key =>$vo) {
$PHPSheet->setCellValue($letter[$key+2].(1),$vo['annotation'].'_'.$vo['variable_name']);
$objPHPExcel->getActiveSheet()->getStyle($letter[$key+2])->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
$objPHPExcel->getActiveSheet()->getColumnDimension($letter[$key+2])->setWidth(20);
}
}
$setTitle = 'Sheet1';
$fileName = '文件名称';
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)) {
mkdir($execlpath);
}
$name = DB::name('tel_scenarios')->where('id',$scenarios_id)->value('name');
$execlpath .= $name.'-变量模板'.'.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
return returnAjax(0,'导出成功',ltrim($execlpath,"."));
}
public function Variabl_template1() {
$scenarios_id = input('id','','trim,strip_tags');
$where['scenarios_id'] = array('eq',$scenarios_id);
$objPHPExcel = new \PHPExcel();
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$PHPSheet = $objPHPExcel->getactivesheet();
$objPHPExcel->createSheet();
$objPHPExcel->setactivesheetindex();
$PHPSheet = $objPHPExcel->getactivesheet();
$list = Db::name('audio_variable')->where($where)->select();
$PHPSheet->setCellValue($letter[0].(1),'姓名');
$objPHPExcel->getActiveSheet()->getStyle($letter[0])->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
$objPHPExcel->getActiveSheet()->getColumnDimension($letter[0])->setWidth(20);
$PHPSheet->setCellValue($letter[1].(1),'电话');
$objPHPExcel->getActiveSheet()->getStyle($letter[1])->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
$objPHPExcel->getActiveSheet()->getColumnDimension($letter[1])->setWidth(20);
if ($list) {
foreach ($list as $key =>$vo) {
$PHPSheet->setCellValue($letter[$key+2].(1),$vo['annotation'].'_'.$vo['variable_name']);
$objPHPExcel->getActiveSheet()->getStyle($letter[$key+2])->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
$objPHPExcel->getActiveSheet()->getColumnDimension($letter[$key+2])->setWidth(20);
}
}
$setTitle = 'Sheet1';
$fileName = '文件名称';
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)) {
mkdir($execlpath);
}
$name = DB::name('tel_scenarios')->where('id',$scenarios_id)->value('name');
$execlpath .= $name.'-变量模板'.'.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
header('location:'.config('res_url').ltrim($execlpath,"."));
}
public function variable_config() {
if (request()->isAjax()) {
return $this->ajax_variable_config();
}else {
$this->ajax_variable_config();
}
return $this->fetch();
}
public function ajax_variable_config() {
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$variableName = input('variableName','','trim,strip_tags');
$id = input('id','','trim,strip_tags');
if (!$limit)
$limit = 10 ;
if (!$page)
$page = 1;
$where = [];
if ($variableName) {
$where['variable_name|annotation'] = array('like','%'.$variableName.'%');
}
$where['scenarios_id'] = array('eq',$id);
$list = Db::name('audio_variable')->where($where)->page($page,$limit)->select();
$count = Db::name('audio_variable')->where($where)->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function get_audio_config(){
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$info = Db::name('audio_config')->where('scenarios_id',$scenarios_id)->find();
if($info){
return returnAjax(0,'获取数据成功',$info);
}else{
return returnAjax(1,'获取数据失败');
}
}
public function config_test(){
$perhaps = [];
$perhaps['voice'] = input('scene','Aixia','trim,strip_tags');
$perhaps['volume'] = (int)input('volume',50,'trim,strip_tags');
$perhaps['speech_rate'] = (int)input('speech',0,'trim,strip_tags');
$perhaps['pitch_rate'] = (int)input('intonation',0,'trim,strip_tags');
$text = input('text','','trim,strip_tags');
$cchu = "uploads/variable_voice/temporary/".date("Y-m")."/".date("d")."/";
$url = $this->ttsVoice($text,$cchu,$perhaps);
return returnAjax(0,'获取成功',$url);
}
public function ttsVoice($text ,$url ,$perhaps) {
$get_synthesis_voice_config = $this->get_synthesis_voice_config();
$appkey = Config::get('appkey');
$token = $get_synthesis_voice_config['Id'];
$random = rand_string(5).time();
$dir = iconv("UTF-8","GBK",$url);
if (!file_exists($dir)) {
mkdir($dir,0755,true);
}
$format = "wav";
$audioSaveFile = $url.$random.'.'.$format;
$textUrlEncode = urlencode($text);
$textUrlEncode = preg_replace('/\+/','%20',$textUrlEncode);
$textUrlEncode = preg_replace('/\*/','%2A',$textUrlEncode);
$textUrlEncode = preg_replace('/%7E/','~',$textUrlEncode);
$sampleRate = 16000;
$result = $this->processPOSTRequest($appkey,$token,$text,$audioSaveFile,$format,$sampleRate,$perhaps);
return '/'.$result['data'];
}
public function audio_config(){
$data = [];
$data['scenarios_id'] = input('scenarios_id','','trim,strip_tags');
$data['scene'] = input('scene','','trim,strip_tags');
$data['sampling'] = input('sampling','','trim,strip_tags');
$data['volume'] = input('volume','','trim,strip_tags');
$data['intonation'] = input('intonation','','trim,strip_tags');
$data['speech'] = input('speech','','trim,strip_tags');
$data['format'] = input('format','','trim,strip_tags');
$data['create_time'] = time();
$scenarios_id  = Db::name('audio_config')->where('scenarios_id',$data['scenarios_id'])->value('scenarios_id');
if($scenarios_id){
$res = Db::name('audio_config')->where('scenarios_id',$scenarios_id)->update($data);
}else{
$res = Db::name('audio_config')->insert($data);
}
if($res){
return returnAjax(0,'配置设置成功');
}else{
return returnAjax(1,'配置设置失败');
}
}
public function online_adition() {
$text = input('text','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$patrn ='/[_{}_]/u';
if(preg_match($patrn,$text)){
$info = [];
$text_array = explode('_',$text);
foreach($text_array as $k =>$v){
if(preg_match($patrn,$v)){
$count = Db::name('audio_variable')->where(['annotation'=>$v,'scenarios_id'=>$scenarios_id])->count();
if($count >0){
$info[]= Db::name('audio_variable')->where(['annotation'=>$v,'scenarios_id'=>$scenarios_id])->find();
}else{
return returnAjax(0,'变量名:'.$v.'不存在，请确认后再填写！');
}
}
}
$infonew = array_column($info ,'example','annotation');
foreach($infonew as $k =>$v){
$text = str_replace('_'.$k.'_',$v,$text);
}
}
$info_congig = Db::name('audio_config')->where('scenarios_id',$scenarios_id)->find();
$perhaps = [];
if($info_congig){
$perhaps['voice'] = $info_congig['scene'];
$perhaps['volume'] = $info_congig['volume'];
$perhaps['speech_rate'] = $info_congig['speech'];
$perhaps['pitch_rate'] = $info_congig['intonation'];
}else{
$perhaps['voice'] = 'Aixia';
$perhaps['volume'] = 50;
$perhaps['speech_rate'] = 0;
$perhaps['pitch_rate'] = 0;
}
$cchu = "uploads/variable_voice/temporary/".date("Y-m")."/".date("d")."/";
$urls[] = $this->ttsVoice($text,$cchu,$perhaps);
return returnAjax(1,'',$urls);
}
public function del_voice_temporary() {
$url = "uploads/variable_voice/temporary/";
if(file_exists($url)){
$this->deldir($url);
return returnAjax(1,'');
}else{
return returnAjax(0,'');
}
}
public function create_luyin(){
$redis = RedisConnect::get_redis_connect();
$task_id =  input('task_id','','trim,strip_tags');
$key = 'create_luyin_task_id';
$key1 = 'luyin_is_finish_'.$task_id;
$redis->rpush($key,$task_id);
$redis->set($key1,1);
return returnAjax(0,'录音进入队列');
}
public function create_luyin_scenarios(){
$redis = RedisConnect::get_redis_connect();
$scenarios_id =  input('id','','trim,strip_tags');
$key = 'create_luyin_scenarios_id';
$key1 = 'luyin_is_finish_scenarios_id_'.$scenarios_id;
$redis->rpush($key,$scenarios_id);
$redis->set($key1,1);
return returnAjax(0,'话术进入队列');
}
public function var_luyin_config(){
$redis = RedisConnect::get_redis_connect();
$scenarios_id =  input('id','','trim,strip_tags');
$config = Db::name('audio_config')->where(['scenarios_id'=>$scenarios_id])->find();
if(empty($config)){
$data['voice_2']='Aixia';
$data['amount_2'] = 50;
$data['amount_rate_2'] = 0;
$data['amount_tone_2'] = 0;
return returnAjax(0,'话术进入队列',$data);
}else{
$data['voice_2'] = $config['scene'];
$data['amount_2'] = $config['volume'];
$data['amount_rate_2'] = $config['speech'];
$data['amount_tone_2'] = $config['intonation'];
return returnAjax(0,'话术进入队列',$data);
}
}
public function save_var_config_redis(){
$scenarios_id =  input('scenarios_id','','trim,strip_tags');
$voice_2 =  input('voice_2','','trim,strip_tags');
$amount_tone_2 =  input('amount_tone_2','','trim,strip_tags');
$amount_rate_2 =  input('amount_rate_2','','trim,strip_tags');
$amount_2 =  input('amount_2','','trim,strip_tags');
$num = Db::name('audio_config')->where(['scenarios_id'=>$scenarios_id])->count('*');
if($num==0){
$data=[];
$data['scenarios_id']=$scenarios_id;
$data['scene']=$voice_2;
$data['volume']=$amount_2;
$data['speech']=$amount_rate_2;
$data['intonation']=$amount_tone_2;
$data['format']='MAV';
$data['sampling']=0;
$data['create_time']=time();
Db::name('audio_config')->insertGetId($data);
}else{
$data=[];
$data['scene']=$voice_2;
$data['volume']=$amount_2;
$data['speech']=$amount_rate_2;
$data['intonation']=$amount_tone_2;
$data['create_time']=time();
Db::name('audio_config')->where(['scenarios_id'=>$scenarios_id])->update($data);
}
return returnAjax(0,'变量话术配置保存成功');
}
}
