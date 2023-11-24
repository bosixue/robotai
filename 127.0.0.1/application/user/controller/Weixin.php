<?php 

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
class Weixin extends User{
public function _initialize(){
parent::_initialize();
}
public function weix_push(){
return $this->fetch();
}
public function show_weixin_info(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$taskName= input('taskName','','trim,strip_tags');
$page= input('page','','trim,strip_tags');
$limit= input('limit','','trim,strip_tags');
$where=[];
$where['wpi.member_id']=$uid;
if(!empty($taskName)){
$where['tc.task_name']=['like','%'.$taskName.'%'];
}
if(empty($page)){
$page=1;
}
if(empty($limit)){
$Page_size=10;
}else{
$Page_size=$limit;
}
$result=Db::name('wx_push_info')
->field('tc.task_name as tcname,wpi.member_id,wpi.push_id,wpi.task_id,max(wpi.create_time) as create_time ,count(*) as count ')
->alias('wpi')
->join('tel_config tc','tc.task_id=wpi.task_id','LEFT')
->where($where)
->group('wpi.task_id,wpi.push_id')
->order('wpi.create_time desc')
->page($page,$limit)
->select();
foreach($result as $key=>$value){
$a_num = Db::name('wx_push_info')->where(['push_id'=>$value['push_id'],'task_id'=>$value['task_id'],'level'=>'A'])->count('*');
$b_num = Db::name('wx_push_info')->where(['push_id'=>$value['push_id'],'task_id'=>$value['task_id'],'level'=>'B'])->count('*');
$c_num = Db::name('wx_push_info')->where(['push_id'=>$value['push_id'],'task_id'=>$value['task_id'],'level'=>'C'])->count('*');
$result[$key]['a_num']= $a_num;
$result[$key]['b_num']= $b_num;
$result[$key]['c_num']= $c_num;
if(empty($value['tcname'])){
$result[$key]['tcname']="任务已被删除";
}
$name= Db::name('wx_push_users')->where('id',$value['push_id'])->value('name');
$result[$key]['name'] = empty($name)?'微信已删除':$name;
$result[$key]['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
}
$count=Db::name('wx_push_info')
->field('tc.task_name as tcname,wpi.*,count(*) as count')
->alias('wpi')
->join('tel_config tc','tc.task_id=wpi.task_id','LEFT')
->where($where)
->group('wpi.task_id,wpi.push_id')
->count();
$data = [
'data'=>$result,
'count'=>$count,
'page'=>$page,
'pagesize'=>$Page_size
];
if($result){
return returnAjax(0,'有数据了',$data);
}else{
return returnAjax(0,'暂时没有数据',$data);
}
}
public function del_weixin_info(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$taskName= input('taskName','','trim,strip_tags');
$ids = input('del_ids/a','','trim,strip_tags');
$all = input('dele_all','','trim,strip_tags');
if(empty($all)){
foreach($ids as $key=>$value){
$arr = explode('_',$value);
$res = Db::name('wx_push_info')->where(['push_id'=>$arr[0],'task_id'=>$arr[1]])->delete();
}
}else{
$where['wpi.member_id']=$uid;
if(!empty($taskName)){
$where['tc.task_name']=['like','%'.$taskName.'%'];
}
$result=Db::name('wx_push_info')
->field('wpi.id')
->alias('wpi')
->join('tel_config tc','tc.task_id=wpi.task_id','LEFT')
->where($where)
->select();
foreach($result as $key=>$value){
$ids[$key]=$value['id'];
}
$res = Db::name('wx_push_info')->where('id','in',$ids)->delete();
}
if(!empty($res)){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
public function index()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$list = Db::name('wx_user')->where('uid',$uid)->paginate(5,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $key=>$val){
if($val['create_time']){
$list['data'][$key]['create_time'] = date("Y-m-d H:i:s",$val['create_time']);
}else{
$list['data'][$key]['create_time'] = '';
}
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function add(){
if(IS_POST){
$ctype=array();
$ctype['token'] = $this->get_rand_str(6,1,0);
$ctype['create_time'] = time();
$ctype['url'] = input('url');
$ctype['status'] = 1;
$ctype['wxname'] = input('wxname');
$ctype['wxid'] = input('wxid');
$ctype['weixin'] = input('weixin');
$ctype['headerpic'] = input('headImg');
$ctype['appid'] = input('appid');
$ctype['appsecret'] = input('appsecret');
$ctype['type'] = input('type');
$ctype['qr'] = input('ewm');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$ctype['uid'] = $uid;
if(input('is_default')){
$ctype['is_default'] = input('is_default');
}else{
$ctype['is_default'] = 0;
}
$result = Db::name('wx_user')->insertGetId($ctype);
if($result){
$this->redirect('User/Weixin/index');
}else{
$this->error("新建失败。","User/Weixin/add");
}
}else{
$wechat = array();
$wechat['apiur'] = 1;
$this->assign('wechat',$wechat);
$this->assign('current','添加');
$picdata=array();
$this->assign('picdata',$picdata);
$picewm=array();
$this->assign('picewm',$picewm);
return $this->fetch();
}
}
public function edit(){
if(IS_POST){
$ctype=array();
$ctype['create_time'] = time();
$ctype['wxname'] = input('wxname');
$ctype['wxid'] = input('wxid');
$ctype['weixin'] = input('weixin');
$ctype['headerpic'] = input('headImg');
$ctype['url'] = input('url');
$ctype['appid'] = input('appid');
$ctype['appsecret'] = input('appsecret');
$ctype['type'] = input('type');
$ctype['qr'] = input('ewm');
if(input('is_default')){
$ctype['is_default'] = input('is_default');
}else{
$ctype['is_default'] = 0;
}
$result = Db::name('wx_user')->where('id',$_POST['watchId'])->update($ctype);
if($result){
$this->redirect('User/Weixin/index');
}else{
$this->error("编辑失败。",Url("User/Weixin/edit",['id'=>$_POST['watchId']]));
}
}else{
$id = input('id');
$wxlist = Db::name('wx_user')->where('id',$id)->find();
$wxlist['apiurl'] = 'http://'.$_SERVER['HTTP_HOST'].Url("wap/weixin/api",['token'=>$wxlist['token']]);
$this->assign('wxlist',$wxlist);
$picdata=array();
$picdata['headImg']=$wxlist['headerpic'];
$this->assign('picdata',$picdata);
$picewm=array();
$picewm['ewm']=$wxlist['qr'];
$this->assign('picewm',$picewm);
$this->assign('current','编辑');
return $this->fetch('add');
}
}
public function setstatus($id,$status){
$data=array();
$data['status'] = $status;
$rescon = Db::name('wx_user')->where('id',$id)->update($data);
if($rescon){
$return['msg'] = "修改成功。";
$return['key'] = 0;
echo json_encode($return);
}else{
$return['msg'] = "修改失败。";
$return['key'] = 1;
echo json_encode($return);
}
}
public function delWeixin(){
$id = input('id');
if(!$id){
exit('没有传入id');
}
$list = Db::name('wx_user')->where('id',$id)->delete();
if(!$list){
exit('删除失败');
}
}
public function wxMenu(){
$wxId = input('wxId','','trim,strip_tags');
$wechat = Db::name('wx_user')->where(array('id'=>$wxId))->find();
if(IS_POST){
if (!$wxId){
$this->error("请先选择公众号！");
}
$idlist = Db::name('wx_menu')->where(array('token'=>$wechat['token']))->field('id')->select();
$listId = array();
$parId = '';
foreach ($idlist as $idk=>$idval){
array_push($listId,$idval['id']);
}
$postMenu = input('menu/a','','trim,strip_tags');
if ($postMenu){
foreach($postMenu as $k=>$v){
$v['token'] = $wechat['token'];
if(in_array($k,$listId)){
Db::name('wx_menu')->where('id',$k)->update($v);
}else{
$parId = Db::name('wx_menu')->insertGetId($v);
}
}
}
$this->redirect('user/Weixin/wxmenu',['wxId'=>$wxId]);
}
else{
$wxlist = Db::name('wx_user')->field('id,wxname')->select();
$this->assign('wxlist',$wxlist);
$p_menus = Db::name('wx_menu')->where(array('token'=>$wechat['token'],'pid'=>0))->order('id ASC')->select();
$p_menus = $this->convert_arr_key($p_menus,'id');
$c_menus = Db::name('wx_menu')->where(array('token'=>$wechat['token'],'pid'=>array('gt',0)))->order('id ASC')->select();
$c_menus = $this->convert_arr_key($c_menus,'id');
$max_id = Db::name('wx_menu')->where(array('token'=>$wechat['token']))->field('max(id) as id')->find();
$this->assign('p_lists',$p_menus);
$this->assign('c_lists',$c_menus);
$this->assign('max_id',$max_id['id']);
$this->assign('wxId',$wxId);
return $this->fetch();
}
}
public function del_menu(){
$id = input('id');
if(!$id){
exit('fail');
}
$list = Db::name('wx_menu')->where('id',$id)->delete();
$row = Db::name('wx_menu')->where('pid',$id)->delete();
if($list ||$row){
exit('success');
}else{
exit('fail');
}
}
function convert_arr_key($arr,$key_name)
{
$arr2 = array();
foreach($arr as $key =>$val){
$arr2[$val[$key_name]] = $val;
}
return $arr2;
}
private function convert_menu($p_menus,$token){
$key_map = array(
'scancode_waitmsg'=>'rselfmenu_0_0',
'scancode_push'=>'rselfmenu_0_1',
'pic_sysphoto'=>'rselfmenu_1_0',
'pic_photo_or_album'=>'rselfmenu_1_1',
'pic_weixin'=>'rselfmenu_1_2',
'location_select'=>'rselfmenu_2_0',
);
$new_arr = array();
$count = 0;
foreach($p_menus as $k =>$v){
$new_arr[$count]['name'] = $v['name'];
$c_menus = Db::name('wx_menu')->where(array('token'=>$token,'pid'=>$k))->select();
if($c_menus){
foreach($c_menus as $kk=>$vv){
$add = array();
$add['name'] = $vv['name'];
$add['type'] = $vv['type'];
if($add['type'] == 'click'){
$add['key'] = $vv['value'];
}elseif($add['type'] == 'view'){
$add['url'] = $vv['value'];
}else{
$add['key'] = $key_map[$add['type']];
}
$add['sub_button'] = array();
if($add['name']){
$new_arr[$count]['sub_button'][] = $add;
}
}
}else{
$new_arr[$count]['type'] = $v['type'];
if($new_arr[$count]['type'] == 'click'){
$new_arr[$count]['key'] = $v['value'];
}elseif($new_arr[$count]['type'] == 'view'){
$new_arr[$count]['url'] = $v['value'];
}else{
$new_arr[$count]['key'] = $key_map[$v['type']];
}
}
$count++;
}
return array('button'=>$new_arr);
}
function get_rand_str($randLength=6,$addtime=1,$includenumber=0){
if ($includenumber){
$chars='abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQEST123456789';
}else {
$chars='abcdefghijklmnopqrstuvwxyz';
}
$len=strlen($chars);
$randStr='';
for ($i=0;$i<$randLength;$i++){
$randStr.=$chars[rand(0,$len-1)];
}
$tokenvalue=$randStr;
if ($addtime){
$tokenvalue=$randStr.time();
}
return $tokenvalue;
}
public function publish(){
$wxId = input('wxId','','trim,strip_tags');
if (!$wxId){
echo 'wxid is null!';
exit;
}
$wechat = Db::name('wx_user')->where(array('id'=>$wxId))->find();
$p_menus =  Db::name('wx_menu')->where(array('token'=>$wechat['token'],'pid'=>0))->order('id ASC')->select();
$p_menus = $this->convert_arr_key($p_menus,'id');
if(!count($p_menus) >0){
return returnAjax(1,'没有菜单可发布');
}
$post_str = $this->convert_menu($p_menus,$wechat['token']);
$menu = &load_wechat('menu',$wechat);
$result = $menu->deleteMenu();
if($result===FALSE){
return returnAjax(1,$menu->errMsg);
}
$result = $menu->createMenu($post_str);
if($result===FALSE){
return returnAjax(1,$menu->errMsg);
}else{
}
return returnAjax(0,'success');
}
public function reply(){
if (IS_POST){
$subscribe = input('subscribe','','trim,strip_tags');
$subscribeId = input('subscribeId','','trim,strip_tags');
if ($subscribeId){
$result = Db::name('wx_reply')->where(array('id'=>$subscribeId))->update(array('content'=>$subscribe,'type'=>0));
}
else{
$result = Db::name('wx_reply')->insertGetId(array('content'=>$subscribe,'type'=>0));
}
if (!$result){
}
$noAnwer = input('noAnwer','','trim,strip_tags');
$noAnwerId = input('noAnwerId','','trim,strip_tags');
if ($noAnwerId){
$result = Db::name('wx_reply')->where(array('id'=>$noAnwerId))->update(array('content'=>$noAnwer,'type'=>1));
}
else{
$result = Db::name('wx_reply')->insertGetId(array('content'=>$noAnwer,'type'=>1));
}
if ($result){
}
else{
}
$this->redirect('User/Weixin/reply');
}
else{
$subscribe = Db::name('wx_reply')->where(array('type'=>0))->find();
$this->assign('subscribe',$subscribe);
$noAnwer = Db::name('wx_reply')->where(array('type'=>1))->find();
$this->assign('noAnwer',$noAnwer);
return $this->fetch();
}
}
public function template(){
$keyword = input('keyword');
$sqlStr = "";
if($keyword){
$sqlStr = 'title like "%'.$keyword.'%"';
}
if ($sqlStr) {
$list = Db::name('wx_template')->where($sqlStr)->order('create_time desc')->paginate(10,false,array('query'=>$this->param));
}else {
$list = Db::name('wx_template')->order('create_time desc')->paginate(10,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$list['data'][$k]['create_time'] = date('Y-m-d H-m-s',$v['create_time']);
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function changeStatus(){
$tpl_id = $_POST['tpl_id'];
$data=array();
$data['status'] = input('status');
$list = Db::name('wx_template')->where('id','in',$tpl_id)->update($data);
if(!$list){
echo "修改失败。";
}
}
public function delTpl(){
$tpl_id = $_POST['tpl_id'];
$list = Db::name('wx_template')->where('id','in',$tpl_id)->delete();
if(!$list){
echo "删除失败。";
}
}
public function addTpl(){
if(IS_POST){
$ctype=array();
$ctype['template_id'] = input('template_id');
$ctype['title'] = input('title');
$ctype['content'] = input('content');
$ctype['status'] = 1;
$ctype['create_time'] = time();
$result = Db::name('wx_template')->insertGetId($ctype);
if($result >= 0){
$data = array();
$data['code'] = 1;
$data['msg'] = "新建成功";
$data['url'] = Url("User/Weixin/template");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "新建失败";
$data['url'] = Url("User/Weixin/addTpl");
echo json_encode($data);
}
}else{
$this->assign('current','添加');
return $this->fetch();
}
}
public function editTpl(){
if(IS_POST){
$ctype=array();
$ctype['template_id'] = input('template_id');
$ctype['title'] = input('title');
$ctype['content'] = input('content');
$ctype['status'] = 1;
$result = Db::name('wx_template')->where('id',input('tplId'))->update($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "编辑成功";
$data['url'] = Url("User/Weixin/template");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "编辑失败";
$data['url'] = Url("User/Weixin/editTpl",array('id'=>input('tplId')));
echo json_encode($data);
}
}else{
$id = input('id');
$wxlist = Db::name('wx_template')->where('id',$id)->find();
$this->assign('tpllist',$wxlist);
$this->assign('current','编辑');
return $this->fetch('addtpl');
}
}
public function tplRecord(){
$tpl_id = input('id');
$sqlStr = "";
if($tpl_id){
$sqlStr = 'tp_id = "'.$tpl_id.'"';
}
if ($sqlStr) {
$list = Db::name('wx_template_record')->where($sqlStr)->order('create_time desc')->paginate(10,false,array('query'=>$this->param));
}else {
$list = Db::name('wx_template_record')->order('create_time desc')->paginate(10,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$tplist = Db::name('wx_template')->field('title')->where('id',$v['tp_id'])->find();
$list['data'][$k]['tp_title'] = $tplist['title'];
$uxlist = Db::name('wx_user')->field('wxname')->where('id',$v['wx_id'])->find();
if($uxlist['wxname']){
$list['data'][$k]['wx_name'] = $uxlist['wxname'];
}else{
$list['data'][$k]['wx_name'] = '';
}
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
$this->assign('tpl_id',$tpl_id);
return $this->fetch();
}
public function addRecord(){
if(IS_POST){
$ctype=array();
$ctype['tp_id'] = input('tp_id');
$ctype['wx_id'] = input('wx_id');
$ctype['group_id'] = input('group_id');
$ctype['open_id'] = input('open_id');
$ctype['url'] = input('url');
$ctype['color'] = input('color');
$ctype['status'] = 1;
$ctype['create_time'] = date('Y-m-d H:m:s',time());
$result = Db::name('wx_template_record')->insertGetId($ctype);
if($result >= 0 ){
$data = array();
$data['code'] = 1;
$data['msg'] = "添加成功";
$data['url'] = Url("User/Weixin/tplRecord");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "添加失败";
$data['url'] = Url("User/Weixin/addRecord",array('tplId'=>input('tp_id')));
echo json_encode($data);
}
}else{
$this->assign('current','添加');
$wxlist = Db::name('wx_user')->field('id,wxname')->select();
$this->assign('wxlist',$wxlist);
$tplist = Db::name('wx_template')->field('id,title')->select();
$this->assign('tplist',$tplist);
$this->assign('thistpl',input('tplId'));
return $this->fetch();
}
}
public function editRecord(){
if(IS_POST){
$ctype=array();
$ctype['tp_id'] = input('tp_id');
$ctype['wx_id'] = input('wx_id');
$ctype['group_id'] = input('group_id');
$ctype['open_id'] = input('open_id');
$ctype['url'] = input('url');
$ctype['color'] = input('color');
$ctype['status'] = 1;
$result = Db::name('wx_template_record')->where('id',input('tplRcId'))->update($ctype);
if($result){
$data = array();
$data['code'] = 1;
$data['msg'] = "编辑成功";
$data['url'] = Url("User/Weixin/tplRecord");
echo json_encode($data);
}else{
$data = array();
$data['code'] = 0;
$data['msg'] = "编辑失败";
$data['url'] = Url("User/Weixin/editRecord",array('id'=>input('tplRcId')));
echo json_encode($data);
}
}else{
$wxlist = Db::name('wx_user')->field('id,wxname')->select();
$this->assign('wxlist',$wxlist);
$tplist = Db::name('wx_template')->field('id,title')->select();
$this->assign('tplist',$tplist);
$id = input('id');
$rclist = Db::name('wx_template_record')->where('id',$id)->find();
$this->assign('tplrclist',$rclist);
$this->assign('thistpl',$rclist['tp_id']);
$this->assign('current','编辑');
return $this->fetch('addRecord');
}
}
public function trStatus(){
$r_id = $_POST['r_id'];
$data=array();
$data['status'] = input('status');
$list = Db::name('wx_template_record')->where('id','in',$r_id)->update($data);
if(!$list){
echo "修改失败。";
}
}
public function getTmplMsg(){
$tpId = input('tpId');
$wxTmplInfo = Db::name('wx_template')->where('id',$tpId)->find();
}
public function sycnFans(){
$wxInfo = Db::name('wx_user')->where(array('status'=>1))->find();
$user = &load_wechat('User',$wxInfo);
$fans = Db::name('fans')->where(array('nickname'=>'','is_focus'=>1))->select();
$getFansInfo = $user->getUserInfo('oA8PywiFtj238XWWw44FxXrNsvWY');
foreach($fans as $item){
$getFansInfo = $user->getUserInfo($item['open_id']);
Db::name('fans')->where('id',$item['id'])->update(array('nickname'=>$getFansInfo['nickname']));
Db::name('member')->where('open_id',$item['open_id'])->update(array('nickname'=>$getFansInfo['nickname']));
}
}
}
