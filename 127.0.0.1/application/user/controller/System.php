<?php 
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Cache;
use think\Session;
use \think\Config;
use Qiniu\json_decode;
use app\common\controller\AdminData;
use app\common\controller\Log;
class System extends User{
public function _initialize()
{
parent::_initialize();
}
public function pay()
{
if(IS_POST){
$udata = array();
$udata['mch_id'] = input('mchId','','trim,strip_tags');
$udata['appid'] = input('appId','','trim,strip_tags');
$udata['partnerkey'] = input('partnerkey','','trim,strip_tags');
$udata['ssl_cer'] = input('sslcer','','trim,strip_tags');
$udata['ssl_key'] = input('sslkey','','trim,strip_tags');
$udata['wx_pay'] = input('wxpay','','trim,strip_tags');
$udata['balance_pay'] = input('balancepay','','trim,strip_tags');
$udata['cash_pay'] = input('cashpay','','trim,strip_tags');
$wxUId = input('wxUId','','trim,strip_tags');
$result = Db::name('wx_user')->where('id',$wxUId)->update($udata);
if($result){
return returnAjax(1,'设置成功',$result);
}else{
return returnAjax(0,'设置失败',0);
}
}else{
$list = Db::name('wx_user')->field('id,wxname,mch_id,appid,partnerkey,ssl_cer,ssl_key,wx_pay,balance_pay,cash_pay')
->where('is_default',1)->find();
if(!$list){
return  "<div>请先设置好默认微信公众号</div>";
}
$this->assign('list',$list);
$sslcer = array();
$sslcer['sslcer'] = $list['ssl_cer'];
$this->assign('sslcer',$sslcer);
$sslkey = array();
$sslkey['sslkey'] = $list['ssl_key'];
$this->assign('sslkey',$sslkey);
return  $this->fetch();
}
}
public function update_basics_config()
{
$site_name = input('site_name','','trim,strip_tags');
$site_url = input('site_url','','trim,strip_tags');
$address = input('address','','trim,strip_tags');
$description = input('description','','trim,strip_tags');
$site_record_number = input('site_record_number','','trim,strip_tags');
$site_contact_number = input('site_contact_number','','trim,strip_tags');
if(empty($site_name)){
return returnAjax(3,'请输入网站名称');
}
if(empty($site_url)){
return returnAjax(3,'请输入网站地址');
}
$user_auth = session('user_auth');
$count = Db::name('basics_config')
->where('member_id',$user_auth['uid'])
->count('id');
$data = [];
if(isset($_FILES['logo']) == true){
$data['logo'] = $this->save_logo_file($_FILES['logo']);
}
$data['site_name'] = $site_name;
$data['site_url'] = $site_url;
$data['address'] = $address;
$data['site_description'] = $description;
$data['site_record_number'] = $site_contact_number;
$data['site_contact_number'] = $site_contact_number;
$data['member_id'] = $user_auth['uid'];
$data['update_time'] = time();
if(!empty($count)){
if(!empty($data['logo'])){
$logo_file_path = Db::name('basics_config')
->where('member_id',$user_auth['uid'])
->value('logo');
$file_suffix = $this->get_file_suffix($logo_file_path);
$file_types = ['png','jpg','gif'];
if(in_array($file_suffix,$file_types) === true){
if(is_file('.'.$logo_file_path) === true){
unlink('.'.$logo_file_path);
}
}
}
$result = Db::name('basics_config')
->where('member_id',$user_auth['uid'])
->update($data);
}else{
$result = Db::name('basics_config')
->insert($data);
}
if(!empty($result)){
return returnAjax(0,'成功');
}
return returnAjax(1,'失败');
}
public function get_file_suffix($file_path)
{
if(empty($file_path)){
return false;
}
$file_paths = explode('/',$file_path);
$file_path = end($file_paths);
$file_path = explode('.',$file_path);
return end($file_path);
}
public function save_logo_file($file_object)
{
if(empty($file_object['tmp_name'])){
return false;
}
\think\Log::record('上传LOGO');
\think\Log::record(json_encode($file_object));
$user_auth = session('user_auth');
$file_name = $file_object['name'];
$suffix = explode('.',$file_name);
$path = './uploads/logo/';
$save_path = '/uploads/logo/';
$rand = rand(100000,999999);
$save_path = $save_path .'logo_'.$user_auth['uid'] .'_'.$rand.'.'.$suffix[1];
$path = $path .'logo_'.$user_auth['uid'] .'_'.$rand.'.'.$suffix[1];
$result = move_uploaded_file($file_object['tmp_name'],$path);
if(!empty($result)){
return $save_path;
}
return false;
}
public function upload_logo_file()
{
$logo = $_FILES['logo'];
if(isset($_FILES['logo']) === false ||empty($logo)){
return returnAjax(3,'无效文件');
}
return returnAjax(0,'$logo',$logo);
}
public function setting(){
$user_auth = session('user_auth');
$member_id = $user_auth['uid'];
if(IS_POST){
$data = $this->request->post();
$mdata = array();
$mdata['email'] = $data['contactMailbox'];
$result = Db::name('admin')->where('id',$member_id)->update($mdata);
if(empty($result)){
return returnAjax(1,'设置失败',$data);
}else{
return returnAjax(0,'设置成功',$data);
}
}
$datas = Db::name('admin')
->field('id,username,mobile,email,logo')
->where('id',$member_id)
->find();
$this->assign('user_data',$datas);
return $this->fetch();
}
function edit_headimg(){
$user_auth = session('user_auth');
$member_id = $user_auth['uid'];
$tmp_name = $_FILES['image']['tmp_name'];
$current_time = date("Y-m-d H-i-s");
if(is_uploaded_file($tmp_name)){
$filename = "public/images/".$current_time.'.jpg';
$return = move_uploaded_file($tmp_name,$filename);
if($return){
$mdata['logo'] = $filename;
$result = Db::name('admin')->where('id',$member_id)->update($mdata);
if($result){
return returnAjax(0,'上传成功！',['filename'=>$filename]);
}else {
return returnAjax('400','上传失败！');
}
}else {
return returnAjax('400','上传失败！');
}
}else{
return returnAjax('555','非法文件！');
}
}
public function notification(){
return  $this->fetch();
}
public function smsConfigure(){
if(IS_POST){
$data = array();
$data['status'] = input('status','','trim,strip_tags');
$data['accessKeyId'] = input('accessKeyId','','trim,strip_tags');
$data['accessKeySecret'] = input('accessKeySecret','','trim,strip_tags');
$data['signName'] = input('signName','','trim,strip_tags');
$data['templateCode'] = input('templateCode','','trim,strip_tags');
$configId = input('configId','','trim,strip_tags');
$insertdata = array();
$insertdata['name'] = 'ALIYUN_SMS';
$insertdata['value'] = serialize($data);
if($configId){
}
else{
$insertdata['group'] = 37;
}
}else{
$extra = unserialize($res['value']);
$this->assign('extra',$extra);
return  $this->fetch();
}
}
public function businessNotice(){
if(IS_POST){
$data = array();
$data['sign'] = input('sign','','trim,strip_tags');
$data['phoneNumber'] = input('phoneNumber','','trim,strip_tags');
$data['content'] = input('content','','trim,strip_tags');
$configId = input('configId','','trim,strip_tags');
$insertdata = array();
$insertdata['value'] = serialize($data);
}else{
$value = unserialize($res['value']);
$this->assign('value',$value);
$this->assign('res',$res);
return  $this->fetch();
}
}
public function setstatus(){
$arrayIds = input('arrayIds/a','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list =Db::name('config')->where('group','in',$arrayIds)->update($data);
if($list){
return returnAjax(0,'修改成功',$list);
}else{
return returnAjax(1,'error!',"修改失败");
}
}
public function interfacePage(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['owner'] = $uid;
}
$list = Db::name('tel_interface')->where($where)->order('id desc')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$admin = Db::name('admin')->field("username")->where('id',$v["owner"])->find();
$list['data'][$k]["username"] = $admin["username"];
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
$vrs = config('view_replace_str');
$path = ".".$vrs["__STATIC__"].'/smartivr.json';
$json_string = file_get_contents($path);
$data = json_decode($json_string,true);
$this->assign('jsdata',$json_string);
return $this->fetch();
}
public function addInterface(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = array();
$data['owner'] = $uid;
$data['app_key'] = htmlspecialchars_decode(input('app_key','','trim,strip_tags'));
$data['app_secret'] = htmlspecialchars_decode(input('app_secret','','trim,strip_tags'));
$data['type'] = input('type','','trim,strip_tags');
$data['status'] = 0;
$result = Db::name('tel_interface')->insertGetId($data);
if ($result){
$this->savejson();
$back = array();
return returnAjax(0,'success!',$back);
}
else{
return returnAjax(1,'failure!');
}
}
public function savejson(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = array();
$where['owner'] = $uid;
$list = Db::name('tel_interface')->where($where)->order('id desc')->select();
$aiuiv2 = array();
$aliyun = array();
$baidu = array();
$enable = array();
foreach ($list as $key=>$val){
$temp = array();
$temp['id'] = $val['app_key'];
$temp['secret'] = $val['app_secret'];
switch ($val['type'])
{
case 'baidu':
array_push($baidu,$temp);
break;
case 'aliyun':
array_push($aliyun,$temp);
break;
case 'xfyun':
array_push($aiuiv2,$temp);
break;
default:
}
}
if(count($aiuiv2) >0){
array_push($enable,"aiuiv2");
}
if(count($aliyun) >0){
array_push($enable,"aliyun");
}
if(count($baidu) >0){
array_push($enable,"baidu");
}
$vrs = config('view_replace_str');
$path = ".".$vrs["__STATIC__"].'/smartivr.json';
$json_string = file_get_contents($path);
$savedata = json_decode($json_string,true);
$savedata['asr']['aiuiv2']['keylist'] = $aiuiv2;
$savedata['asr']['aliyun']['keylist'] = $aliyun;
$savedata['asr']['baidu']['keylist'] = $baidu;
$savedata['asr']['enable'] = $enable;
$savePath = './uploads/asrapi/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$savename = $uid.'_smartivr.json';
$itempath = $savePath.$savename;
$savejson = json_encode($savedata,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
file_put_contents($itempath,$savejson);
}
public function editInterface(){
$data = array();
$data['app_key'] = htmlspecialchars_decode(input('app_key','','trim,strip_tags'));
$data['app_secret'] = htmlspecialchars_decode(input('app_secret','','trim,strip_tags'));
$data['type'] = input('type','','trim,strip_tags');
$interfaceId = input('interfaceId','','trim,strip_tags');
$result = Db::name('tel_interface')->where('id',$interfaceId)->update($data);
if ($result){
$this->savejson();
return returnAjax(0,'success!');
}
else{
return returnAjax(1,'failure!');
}
}
public function getInterfaceInfo(){
$id = input('id','','trim,strip_tags');
$slist = Db::name('tel_interface')->where('id',$id)->find();
echo json_encode($slist,true);
}
public function setInterfaceStatus(){
$sId = input('ifId','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('tel_interface')->where('id',$sId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function delInterface(){
$ids= input('id/a','','trim,strip_tags');
$list = Db::name('tel_interface')->where('id','in',$ids)->delete();
$this->savejson();
if(!$list){
echo "删除失败。";
}
}
public function jichushezhi(){
$user_auth = session('user_auth');
$data = Db::name('basics_config')
->where('member_id',$user_auth['uid'])
->find();
$this->assign('data',$data);
return $this->fetch();
}
public function feedback(){
$user_auth = session('user_auth');
$uid=$user_auth['uid'];
if( input('post.content') != '') {
$content=input('post.content');
$weixin=input('post.weixin');
$phone=input('post.phone');
$email=input('post.email');
$target=5555;
$time=time();
if(empty($weixin)&&empty($phone)&&empty($email)){
return returnAjax(3,'fail','fail');
}
$data = ['owner'=>$uid,'content'=>$content,'phone'=>$phone,'weixin'=>$weixin ,'email'=>$email,'create_time'=>$time ,'target'=>$target];
\think\Log::record('test123');
$insertResult=Db::name('tel_feedback')->insert($data);
if($insertResult){
return returnAjax(0,'success','success');
}
else{
return returnAjax(1,'fail','fail');
}
}
return $this->fetch();
}
public function feedback_list(){
$user_auth = session('user_auth');
$uid=$user_auth['uid'];
if(false=== $users_info=$this->getCacheUser()){
$this->setCacheUser();
$users_info=$this->getCacheUser();
}
$list = Db::name('tel_feedback')
->where("target",$uid)  
->order('create_time DESC')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach($list['data'] as $key=>$value){
$list['data'][$key]['user_info']=$users_info[$value['owner'] ];
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function feedback_del(){
$user_auth = session('user_auth');
$uid=$user_auth['uid'];
if(empty($uid))return false;
$id=input('post.id');
$del = Db::name('tel_feedback')
->where("id",$id)  
->delete();
if($del){
return returnAjax(0,'success','success');
}
else{
return returnAjax(1,'fail','fail');
}
}
public function getCacheUser(){
return Cache::get('users_info');
}
public function setCacheUser(){
$rsData=Db::name('admin')->select();
$returnArray=[];
if(!empty($rsData)){
foreach ($rsData as $k=>$v){
$returnArray[$v['id']]=$v;
}
}
return Cache::set('users_info',$returnArray,3600);
}
}