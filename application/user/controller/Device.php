<?php 
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\LinesData;
use app\common\controller\RobotDistribution;
require_once(EXTEND_PATH .'/phpqrcode/phpqrcode.php');
class Device extends User{
public function _initialize() {
parent::_initialize();
$request = request();
$action = $request->action();
}
public  function bangdingwx(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
controller('user/wechat')->getNoticerOpenid($uid) ;
$yunying_id = $this->get_operator_id($uid);
$config = Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$status =$config['status'];
if($status==1){
$userinfo = Db::name('wx_push_users')->where(['member_id'=>$uid,'wx_config_id'=>$config['id']])->select();
}else{
$userinfo='';
}
$this->assign('userinfo',$userinfo);
$yunwei_info = Db::name('wx_config')->where(['member_id'=>$yunying_id,'status'=>1])->find();
$this->assign('yunwei_info',$yunwei_info);
return $this->fetch();
}
public  function get_bangding_qrcode(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$yunying_id = $this->get_operator_id($uid);
$config = Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$appid=$config['app_id'];
$appsecret=$config['app_secret'];
$url = config('domain_name').'/user/Wechat/getUserInfo?userid='.$uid.'&appid='.$appid.'&appsecret='.$appsecret;
$errorCorrectionLevel = "L";
$matrixPointSize = "8";
$image = \QRcode::png($url,false,$errorCorrectionLevel,$matrixPointSize);
return  $image;
exit;
}
public function get_operator_id($uid){
$admin = Db::name('admin')->where(['id'=>$uid])->find();
$roleNme = getRoleNameByUserId($uid);
if(!empty($uid) &&$roleNme=='运营商'){
return  $admin['id'];
}
if(!empty($uid) &&$roleNme!='运营商'&&$roleNme!='管理员'){
$admin_father = Db::name('admin')->where(['id'=>$admin['pid']])->find();
$father_role_name = getRoleNameByUserId($admin_father['id']);
if($father_role_name == '运营商'){
return $admin_father['id'];
}else{
$admin_granddad = Db::name('admin')->where(['id'=>$admin_father['pid']])->find();
$granddad_role_name = getRoleNameByUserId($admin_granddad['id']);
if($granddad_role_name == '运营商'){
return $admin_granddad['id'];
}else{
$admin_last = Db::name('admin')->where(['id'=>$admin_granddad['pid']])->find();
return $admin_last['id'];
}
}
}
}
public function delete_bangding_wx(){
$id = input('id','','trim,strip_tags');
if(empty($id)){
return returnAjax(0,'id不能为空');
}
$res = Db::name('wx_push_users')->where(['id'=>$id])->delete();
if($res){
return returnAjax(1,'删除成功');
}else{
return returnAjax(0,'删除失败');
}
}
public function index()
{
return $this->fetch();
}
public function voip()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$list = Db::name('tel_device')->where($where)->order('id desc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function lines(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
$where['member_id'] = ['=',$uid];
$list = Db::name('tel_line')
->where($where)
->order('id desc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$admin = Db::name('admin')->field("username")->where('id',$v["member_id"])->find();
$list['data'][$k]["username"] = $admin["username"];
$memberInfo = Db::name('admin')->field("username")->where('id',$v["member_id"])->find();
$list['data'][$k]["username"] = $memberInfo["username"];
}
$subordinate_members = $this->get_subordinate_members($uid);
$this->assign('subordinate_members',$subordinate_members);
$this->assign('list',$list['data']);
$this->assign('page',$page);
$this->assign('super',$super);
$result = Db::name('admin')->where(array('status'=>1,'super'=>0))->select();
$this->assign('memberList',$result);
$this->assign('uid',$uid);
return $this->fetch();
}
public function edit_line(){
return $this->fetch();
}
public function review_speech(){
return $this->fetch();
}
public function allocation_line(){
return $this->fetch();
}
public function public_configuration(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$yunying_id = $this->get_operator_id($uid);
$wx = Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$this->assign('wx',$wx);
$roleName = getRoleNameByUserId($uid);
$this->assign('roleName',$roleName);
return $this->fetch();
}
public function add_public(){
$id = input('get.id','','trim,strip_tags');
if(!empty($id)){
$wx = Db::name('wx_config')->where(['id'=>$id])->find();
$this->assign('wx',$wx);
return $this->fetch();
}
return $this->fetch();
}
public function addLine(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = array();
$data['name'] = input('name','','trim,strip_tags');
$data['member_id'] = $uid;
$data['phone'] = input('phone','0','trim,strip_tags');
$data['call_prefix'] = input('call_prefix','','trim,strip_tags');
$data['inter_ip'] = input('inter_ip','','trim,strip_tags');
$data['gateway'] = input('gateway','','trim,strip_tags');
$data['sales_price'] = input('price','','trim,strip_tags');
$type = input('type/d','','trim,strip_tags');
$data['type'] = $type;
if ($type == 0){
$dial_format  = 'sofia/external/';
if ($data['call_prefix']){
$dial_format  .= $data['call_prefix'];
}
$dial_format  .= '%s@'.$data['inter_ip'];
}
else{
$dial_format  = 'sofia/gateway/';
if ($data['gateway']){
$dial_format  .= $data['gateway'];
}
$dial_format  .= '/%s';
}
$data['dial_format'] = $dial_format;
$data['remark'] = input('remark','','trim,strip_tags');
$data['status'] = 1;
$result = Db::name('tel_line')->insertGetId($data);
if ($result){
return returnAjax(0,'success!');
}
else{
return returnAjax(1,'failure!');
}
}
public function editLine(){
$data = array();
$data['name'] = input('name','','trim,strip_tags');
$user_auth = session('user_auth');
if($user_auth['super'] == 0){
}
$simId = input('simId','','trim,strip_tags');
$line_pid = Db::name('tel_line')
->where('id',$simId)
->value('pid');
if($line_pid != 0){
return returnAjax(0,'error','无效线路');
}
$data['phone'] = input('phone','0','trim,strip_tags');
$data['call_prefix'] = input('call_prefix','','trim,strip_tags');
$data['inter_ip'] = input('inter_ip','','trim,strip_tags');
$data['gateway'] = input('gateway','','trim,strip_tags');
$data['sales_price'] = input('price','','trim,strip_tags');
$type = input('type/d','','trim,strip_tags');
$data['type'] = $type;
if ($type == 0){
$dial_format  = 'sofia/external/';
if ($data['call_prefix']){
$dial_format  .= $data['call_prefix'];
}
$dial_format  .= '%s@'.$data['inter_ip'];
}
else{
$dial_format  = 'sofia/gateway/';
if ($data['gateway']){
$dial_format  .= $data['gateway'];
}
$dial_format  .= '/%s';
}
$data['dial_format'] = $dial_format;
$data['remark'] = input('remark','','trim,strip_tags');
$ids = [];
$ids[] = $simId;
$o = 0;
while(count($ids) >0){
if($o !== 0 &&isset($data['sales_price']) === true){
unset($data['sales_price']);
}
foreach($ids as $key=>$value){
$result = Db::name('tel_line')->where('id',$value)->update($data);
}
$o++;
$ids_result = Db::name('tel_line')
->field('id')
->where('pid','in',$ids)
->select();
$ids = [];
foreach($ids_result as $key=>$value){
$ids[] = $value['id'];
}
}
if ($result){
return returnAjax(0,'success!');
}else{
return returnAjax(1,'failure!');
}
}
public function getLineInfo(){
$id = input('id','','trim,strip_tags');
$slist = Db::name('tel_line')->where('id',$id)->find();
echo json_encode($slist,true);
}
public function delLine(){
$user_auth = session('user_auth');
$ids= input('id/a','','trim,strip_tags');
if($user_auth['super'] == 0){
if(!empty($ids) &&is_array($ids) === true){
$ids_count = count($ids);
if($ids_count >1){
echo '最多只能删除一个。';
exit;
}
$LinesData = new LinesData();
$member_id = $LinesData->get_line_member_id($ids[0]);
if($member_id != $user_auth['uid']){
echo '删除失败。';
exit;
}
}else{
echo '删除失败';
exit;
}
}
$list = Db::name('tel_line')->where('id','in',$ids)->delete();
if(!$list){
echo "删除失败。";
}
}
public function setLineStatus(){
$sId = input('sId','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('tel_line')->where('id',$sId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function addDevice(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = array();
$data['name'] = htmlspecialchars_decode(input('name','','trim,strip_tags'));
$data['number'] = input('number','','trim,strip_tags');
$data['dial_format'] = input('dial_format','','trim,strip_tags');
$data['type'] = input('type','','trim,strip_tags');
$data['desc'] = input('desc','','trim,strip_tags');
$result = Db::name('tel_device')->insertGetId($data);
if ($result){
return returnAjax(0,'success!');
}
else{
return returnAjax(1,'failure!');
}
}
public function editDevice(){
$data = array();
$data['name'] = htmlspecialchars_decode(input('name','','trim,strip_tags'));
$data['dial_format'] = input('dial_format','','trim,strip_tags');
$data['number'] = input('number','','trim,strip_tags');
$data['type'] = input('type','','trim,strip_tags');
$data['desc'] = input('desc','','trim,strip_tags');
$deviceId = input('deviceId','','trim,strip_tags');
$result = Db::name('tel_device')->where('id',$deviceId)->update($data);
if ($result){
return returnAjax(0,'success!');
}
else{
return returnAjax(1,'failure!');
}
}
public function getDeviceInfo(){
$id = input('id','','trim,strip_tags');
$slist = Db::name('tel_device')->where('id',$id)->find();
echo json_encode($slist,true);
}
public function delDevice(){
$ids= input('id/a','','trim,strip_tags');
$list = Db::name('tel_device')->where('id','in',$ids)->delete();
if(!$list){
echo "删除失败。";
}
}
public function robot(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$list = Db::name('tel_sim')->where($where)->order('id desc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function simPage(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$id = input('id','','trim,strip_tags');
$where['device_id'] = $id;
$list = Db::name('tel_sim')->where($where)->order('position asc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$device = Db::name('tel_device')->field("name")->where('id',$v["device_id"])->find();
$list['data'][$k]["devicename"] = $device["name"];
$memberInfo = Db::name('admin')->field("username")->where('id',$v["member_id"])->find();
$list['data'][$k]["username"] = $memberInfo["username"];
}
$this->assign('thisId',$id);
$this->assign('list',$list['data']);
$this->assign('page',$page);
$result = Db::name('admin')->where(array('status'=>1,'super'=>0))->select();
$this->assign('memberList',$result);
return $this->fetch();
}
public function addSim(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = array();
$data['member_id'] = input('member_id','','trim,strip_tags');
$data['phone'] = htmlspecialchars_decode(input('phone','','trim,strip_tags'));
$data['call_prefix'] = input('call_prefix','','trim,strip_tags');
$sim = Db::name('tel_sim')->where('phone',$data['phone'])->find();
if ($sim){
return returnAjax(1,'号码已存在!');
}
$data['device_id'] = input('deviceId','','trim,strip_tags');
$data['position'] = input('position','0','trim,strip_tags');
$data['remark'] = input('remark','','trim,strip_tags');
$data['status'] = 1;
$result = Db::name('tel_sim')->insertGetId($data);
if ($result){
return returnAjax(0,'success!');
}
else{
return returnAjax(1,'failure!');
}
}
public function editSim(){
$data = array();
$data['phone'] = htmlspecialchars_decode(input('phone','','trim,strip_tags'));
$data['position'] = input('position','0','trim,strip_tags');
$data['member_id'] = input('member_id','','trim,strip_tags');
$data['call_prefix'] = input('call_prefix','','trim,strip_tags');
$data['remark'] = input('remark','','trim,strip_tags');
$simId = input('simId','','trim,strip_tags');
$result = Db::name('tel_sim')->where('id',$simId)->update($data);
if ($result){
return returnAjax(0,'success!');
}
else{
return returnAjax(1,'failure!');
}
}
public function getSimInfo(){
$id = input('id','','trim,strip_tags');
$slist = Db::name('tel_sim')->where('id',$id)->find();
echo json_encode($slist,true);
}
public function delSim(){
$ids= input('id/a','','trim,strip_tags');
$list = Db::name('tel_sim')->where('id','in',$ids)->delete();
if(!$list){
echo "删除失败。";
}
}
public function setSimStatus(){
$sId = input('sId','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('tel_sim')->where('id',$sId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
protected function get_subordinate_members($member_id)
{
if(empty($member_id)){
return false;
}
$datas = Db::name('admin')
->field('id,username')
->where([
'pid'=>$member_id
])
->select();
return $datas;
}
protected function verify_whether_existence($member_id,$line_id)
{
if(empty($member_id) ||empty($line_id)){
return false;
}
$count = Db::name('tel_line')
->where([
'member_id'=>$member_id,
'id'=>$line_id
])
->count();
if($count !== 0){
return true;
}
return false;
}
public function insert_line_allocation_api()
{
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign)){
return returnAjax(1,'error','未登陆');
}
$user_auth = session('user_auth');
$member_id = input('member_id','','trim,strip_tags');
$sales_price = input('sales_price','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
if(empty($member_id) ||empty($sales_price) ||empty($line_id)){
return returnAjax(1,'error','参数错误');
}
$count = Db::name('tel_line')
->where([
'member_id'=>$member_id,
'id'=>$line_id
])
->count();
if(!empty($count)){
return returnAjax(2,'error','该线路已存在');
}
$result = $this->insert_line_allocation($member_id,$user_auth['uid'],$sales_price,$line_id);
if($result === true){
return returnAjax(0,'success','成功！');
}
return returnAjax(1,'error','失败');
}
protected function insert_line_allocation($member_id,$have_member_id,$sales_price,$line_id)
{
if(empty($member_id) ||empty($have_member_id) ||empty($sales_price) ||empty($line_id)){
return false;
}
$line_data = $this->get_line_data($have_member_id,$line_id);
$line_data['member_id'] = $member_id;
$line_data['sales_price'] = $sales_price;
$line_data['pid'] = $line_id;
unset($line_data['id']);
$result = Db::name('tel_line')
->insert($line_data);
if(!empty($result)){
return true;
}
return false;
}
public function get_line_data_api($member_id,$line_id)
{
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign)){
return returnAjax(1,'error','未登陆');
}
$member_id = input('member_id','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
if(empty($member_id) ||empty($line_id)){
return returnAjax(1,'error!','参数错误');
}
$line_data = $this->get_line_data($member_id,$line_id);
if($line_data !== false){
return returnAjax($line_data,'success!',$line_data);
}
return returnAjax(1,'error!','失败');
}
protected function get_line_data($member_id,$line_id)
{
if(empty($line_id) ||empty($member_id)){
return false;
}
$line_data = Db::name('tel_line')
->where([
'id'=>$line_id,
'member_id'=>$member_id
])
->find();
return $line_data;
}
public function get_member_line_data_api()
{
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign)){
return returnAjax(1,'error','未登陆');
}
$member_id = input('member_id','','trim,strip_tags');
if(empty($member_id)){
return returnAjax(1,'error!','参数错误');
}
$user_auth = session('user_auth');
$line_data = $this->get_member_line_data_func($member_id);
$line_ids = [];
foreach($line_data as $key=>$value){
$line_data[$key]['price'] = $this->get_line_price($value['pid']);
$line_ids[] = $value['pid'];
}
$LinesData = new LinesData();
$current_user_lines = $LinesData->get_distribution_lines($user_auth['uid'],$line_ids);
return returnAjax(0,'success!',['lines'=>$line_data,'user_lines'=>$current_user_lines]);
}
protected function get_member_line_data_func($member_id)
{
if(empty($member_id)){
return false;
}
$line_data = Db::name('tel_line')
->where('member_id',$member_id)
->where('pid','>',0)
->select();
return $line_data;
}
protected function get_line_price($line_id)
{
if(empty($line_id)){
return false;
}
$price = Db::name('tel_line')
->where('id',$line_id)
->value('sales_price');
return $price;
}
public function delete_subordinate_line_api()
{
$user_auth_sign = session('user_auth_sign');
$uid = session('user_auth.uid');
if(empty($user_auth_sign) ||empty($uid)){
return returnAjax(1,'error','未登陆');
}
$member_id = input('member_id','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
if(empty($member_id) ||empty($line_id)){
return returnAjax(1,'error','参数错误');
}
$line_data = Db::name('tel_line')
->where([
'id'=>$line_id,
'member_id'=>$member_id
])
->find();
if(empty($line_data)){
return returnAjax(1,'error','线路不存在');
}
$count = Db::name('tel_line')
->where([
'id'=>$line_data['pid'],
'member_id'=>$uid
])
->count();
if(empty($count)){
return returnAjax(1,'error','该线路无权限删除');
}
if($this->delete_subordinate_line($member_id,$line_id) === true){
return returnAjax(0,'success','成功');
}
return returnAjax(1,'error','失败');
}
protected function delete_subordinate_line($member_id,$line_id)
{
if(empty($member_id) ||empty($line_id)){
return false;
}
if($this->verify_whether_existence($member_id,$line_id) === false){
Log::info('不存在');
return false;
}
$line_ids = [];
$line_ids[] = $line_id;
$line_data = Db::name('tel_line')
->where('pid',$line_id)
->find();
while(isset($line_data['id']) === true &&!empty($line_data['id'])){
$line_ids[] = $line_data['id'];
$line_data = Db::name('tel_line')
->where('pid',$line_data['id'])
->find();
}
Log::info(json_encode($line_ids));
$result = Db::name('tel_line')
->where('id','in',$line_ids)
->delete();
if(!empty($result)){
return true;
}
return false;
}
public function robot_distribution()
{
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
$AdminData = new AdminData();
$users = $AdminData->get_find_users($uid);
$ids = [];
$ids[] = $uid;
foreach($users as $key=>$value){
$ids[] = $value['id'];
}
$args = [
'rd.pid'=>['in',$ids]
];
$screens = [
'username'=>'',
'open_username'=>'',
];
$userName = input('userName','','trim,strip_tags');
if(!empty($userName)){
$args['ma.username'] = ['like','%'.$userName.'%'];
$screens['username'] = $userName;
}
$openuserName = input('openuserName','','trim,strip_tags');
if(!empty($openuserName)){
$args['pa.username'] = ['like','%'.$openuserName.'%'];
$screens['open_username'] = $openuserName;
}
$RobotDistribution = new RobotDistribution();
$robot_datas = $RobotDistribution->get_robot_datas($uid,$args,$page);
$this->assign('screens',$screens);
$this->assign('uid',$uid);
$this->assign('users',$users);
$this->assign('robot_datas',$robot_datas);
return $this->fetch();
}
public function get_robot_data_api()
{
if(IS_POST){
$user_auth = session('user_auth');
$pid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$id = input('id','','trim,strip_tags');
if(empty($id)){
return returnAjax(3,'error','参数错误');
}
$RobotDistribution = new RobotDistribution();
$robot_data = $RobotDistribution->get_robot_data($id);
return returnAjax(0,'success',$robot_data);
}
}
public function open_up_api()
{
if(IS_POST){
$user_auth = session('user_auth');
$pid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$member_id = input('member_id','','trim,strip_tags');
$number = input('number','','trim,strip_tags');
$duration = input('duration','','trim,strip_tags');
$note = input('note','','strip_tags');
if(empty($member_id) ||empty($number) ||empty($duration)){
return returnAjax(3,'error','参数错误');
}
$AdminData = new AdminData();
$role_name = $AdminData->get_role_name($pid);
$RobotDistribution = new RobotDistribution();
if($role_name != '管理员'){
$p_number = $RobotDistribution->get_usable_robot_count($pid);
if($p_number <$number){
return returnAjax(4,'error','机器人数量不足');
}
}
$result = $RobotDistribution->open_up($pid,$member_id,$number,$duration,$note);
if($result === true){
return returnAjax(0,'success','成功');
}
return returnAjax(1,'error','失败');
}
}
public function extend_duration_api()
{
if(IS_POST){
$user_auth = session('user_auth');
$pid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$id = input('id','','trim,strip_tags');
$duration = input('duration','','trim,strip_tags');
if(empty($id) ||empty($duration)){
return returnAjax(3,'error','参数错误');
}
$RobotDistribution = new RobotDistribution();
$data = $RobotDistribution->get_robot_data($id);
if($RobotDistribution->verify_whether_belong_to_find_account($data['member_id']) === false){
return returnAjax(4,'error','不属于子账户');
}
$state = $RobotDistribution->get_state($id);
if($state == 0){
$AdminData = new AdminData();
$role_name = $AdminData->get_role_name($pid);
$RobotDistribution = new RobotDistribution();
if($role_name != '管理员'){
$p_number = $RobotDistribution->get_usable_robot_count($pid);
if($p_number <$data['member_id']){
return returnAjax(5,'error','机器人数量不足');
}
$result = $RobotDistribution->deduction_robot_number($pid,$data['member_id']);
if($result === false){
Log::info('扣除开通者用户的机器人失败');
return returnAjax(6,'error','失败');
}
}
$result = $RobotDistribution->increase_find_member_robot_number($data['member_id'],$data['member_id']);
if($result === false){
return returnAjax(6,'error','失败');
}
}
if($RobotDistribution->extend_duration($id,$duration) === true){
return returnAjax(0,'success','成功');
}
return returnAjax(1,'error','失败');
}
}
public function recovery_robot_api()
{
if(IS_POST){
$user_auth = session('user_auth');
$pid = $user_auth['uid'];
$user_auth_sign = session('user_auth_sign');
if(empty($user_auth_sign) ||empty($user_auth)){
return returnAjax(2,'error','未登陆');
}
$id = input('id','','trim,strip_tags');
$number = input('number','','trim,strip_tags');
if(empty($id) ||empty($number)){
return returnAjax(3,'error','参数错误');
}
$RobotDistribution = new RobotDistribution();
$robot_data = $RobotDistribution->get_robot_data($id);
if($robot_data['count'] <$number){
return returnAjax(4,'error','机器人不足');
}
$result = $RobotDistribution->recovery_robot($id,$number);
if(!empty($result)){
return returnAjax(0,'success','成功');
}
return returnAjax(1,'error','失败');
}
}
public function bindingwx(){
return $this->fetch();
}
}
