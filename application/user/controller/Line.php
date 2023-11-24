<?php 

namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use think\Validate;
use Qiniu\json_decode;
use app\common\controller\TaskData;
use app\common\controller\LinesData;
class Line extends User{
private $uid;
private $pid;
private $username;
private $role_id;
private $time;
private $pageLimit=10;
private $postData;
private $pageData;
public function _initialize() {
parent::_initialize();
$this->getInfo();
$this->getPostDatas();
$this->assign('pageLimit',$this->pageLimit);
}
public function update_line_group_data()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$line_group_id = input('line_group_id','','trim,strip_tags');
$sales_price = input('sales_price','','trim,strip_tags');
$line_group_name = input('line_group_name','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$user_auth = session('user_auth');
if(empty($line_group_id)){
return returnAjax(2,'线路组ID不能为空');
}
$LinesData = new LinesData();
if($LinesData->line_group_whether_belong_to_user($line_group_id,$user_auth['uid']) == false){
return returnAjax(2,'当前线路组不可编辑');
}
$tel_line_group = Db::name('tel_line_group')->field('name,sales_price')->where('id',$line_group_id)->find();
$update_result = $LinesData->update_line_group_data($line_group_id,$line_group_name,$sales_price,$remark);
if($update_result == true){
$str='';
$datax['owner']=$uid;
$datax['user_id']=$uid;
$datax['operation_type']=3;
$datax['operation_fu']="编辑线路组";
if($line_group_name!=$tel_line_group['name']){
$str.="编辑线路组,线路组名从以前的名字:".$tel_line_group['name']."修改为现在的名字".$line_group_name;
}
if($sales_price!=$tel_line_group['sales_price']){
$str.="。编辑线路组,线路组价格修改为:".$sales_price;
}
$str=trim($str,'。');
$datax['record_content']=$str;
$datax['operation_date']=time();
if(!empty($str)){
Db::name('operation_record')->insertGetId($datax);
}
return returnAjax(0,'更新成功');
}
return returnAjax(1,'更新失败');
}
public function get_line_group_data()
{
$line_group_id = input('line_group_id','','trim,strip_tags');
$user_auth = session('user_auth');
$LinesData = new LinesData();
if($LinesData->line_group_whether_belong_to_user($line_group_id,$user_auth['uid']) == false){
return returnAjax(2,'当前线路不可读取');
}
$line_data = $LinesData->get_line_group_find($line_group_id);
if($line_data == false){
return returnAjax(1,'获取失败');
}
return returnAjax(0,'获取成功',$line_data);
}
public function getInfo(){
if(!$this->uid){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if(!$uid)return false;
$this->uid=$uid;
}
$this->time=time();
$userinfo = Db::name('admin')->where('id',$this->uid)->find();
$this->role_id=$userinfo['role_id'];
$this->pid=$userinfo['pid'];
$this->username=$userinfo['username'];
}
public function getPostDatas(){
$this->postData['user_id'] = $this->uid;
$postArr=['id','group_id','name','line_name'];
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
public function line_statistics(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$name = input('post.username','','trim,strip_tags');
$lineid = input('post.lineid','','trim,strip_tags');
$limit = input('post.limit','','trim,strip_tags');
$arr=[];
if(!empty($lineid)){
$where['t.line_id'] =['in',$lineid];
}
if(!empty($name)){
$where['a.username']=['like','%'.$name.'%'];
}
$where['member_id']=$uid;
if(empty($limit)){
$Page_size = 10;
}else{
$Page_size = $limit;
}
$page = input('page','1','trim,strip_tags');
$lines = Db::name('tel_line_charging_statistics')->alias('t')->field('t.*,a.username,a.role_id')->join('admin a','t.find_member_id = a.id')->where($where)->page($page,$Page_size)->order('date desc')->select();
foreach($lines as $k=>$v){
$fatherid = getFatherLineIdBySonId($v['line_id']);
$name =getPhoneName($fatherid);
$lines[$k]['laiyuan']=$name;
$line_name = Db::name('tel_line_group')->field('name')->where(['id'=>$v['line_id']])->value('name');
if(!$line_name){
$line_name = '【线路异常】';
}
$lines[$k]['line_name']=$line_name;
$username=Db::name('admin')->field('username')->where(['id'=>$v['find_member_id']])->value('username');
$lines[$k]['username']=$username;
$lines[$k]['role_name'] = getRoleNameByUserId($v['find_member_id']);
}
$totale = Db::name('tel_line_charging_statistics')->alias('t')->join('admin a','t.find_member_id = a.id')->where($where)->count('*');
$totalPage =ceil($totale/$Page_size);
$chengbenTotal = Db::name('tel_line_charging_statistics')->alias('t')->join('admin a','t.find_member_id = a.id')->where($where)->sum('cost_price_statistics');
$chengbenTotal = round($chengbenTotal,3);
$xiaoshouTotal = Db::name('tel_line_charging_statistics')->alias('t')->join('admin a','t.find_member_id = a.id')->where($where)->sum('sale_price_statistics');
$xiaoshouTotal = round($xiaoshouTotal,3);
$lirunTotal = Db::name('tel_line_charging_statistics')->alias('t')->join('admin a','t.find_member_id = a.id')->where($where)->sum('profit');
$lirunTotal = round($lirunTotal,3);
$sumDuration = Db::name('tel_line_charging_statistics')->alias('t')->join('admin a','t.find_member_id = a.id')->where($where)->sum('duration');
$sumDuration = round($sumDuration,3);
$data['sumDuration']=$sumDuration;
$data['lirunTotal']=$lirunTotal;
$data['xiaoshouTotal']=$xiaoshouTotal;
$data['chengbenTotal']=$chengbenTotal;
$data['limit']=$Page_size;
$data['total']=$totale;
$data['Nowpage']=$page;
$data['page_count']=$totalPage;
$data['lines']=$lines;
return returnAjax(0,'显示数据成功',$data);
}
public function get_lines(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$wherexxx['user_id']=$uid;
$wherexxx['status']=1;
$lineMembers = Db::name('tel_line_group')->where($wherexxx)->select();
$data['lineMembers']=$lineMembers;
return returnAjax(0,'显示数据成功',$data);
}
public function delete_statistics(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$arr = input('data/a','','trim,strip_tags');
$res = Db::name('tel_line_charging_statistics')->where(['id'=>['in',$arr]])->delete();
if($res){
$data['owner']=$uid;
$data['user_id']=$uid;
$data['operation_type']=3;
$data['operation_fu']="删除";
$arr = implode(',',$arr);
$data['record_content']="删除了tel_line_charging_statistics表中的数据,数据id为:".$arr;
$data['operation_date']=time();
Db::name('operation_record')->insertGetId($data);
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
public function view_lines(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where_admin['id'] = array('eq',$uid);
$where_admin['status'] = array('eq',1);
$where_admin['role_id'] = array('neq',20);
$admin = Db::name('admin')->field('username,role_id,id')->where($where_admin)->find();
$rolename = Db::name('admin_role')->field('name')->where(['id'=>$admin['role_id']])->find();
$admin['role_name'] = $rolename['name'];
$name = input('post.username','','trim,strip_tags');
if(!empty($name)){
$where['name']=['like','%'.$name.'%'];
}
$where['user_id']=$uid;
$limit = input('limit','','trim,strip_tags');
if(empty($limit)){
$Page_size = 10;
}else{
$Page_size = $limit;
}
$page = input('post.page','1','trim,strip_tags');
$where['status']=1;
$lines = Db::name('tel_line_group')->field('id,name,sales_price,create_time,line_group_pid,remark')->order('id desc')->where($where)->page($page,$Page_size)->select();
foreach($lines as $k=>$line){
if($line['line_group_pid']==0){
$line['laiyuan']="自有线路";
}else{
$name = Db::name('tel_line_group')->field('name')->where(['id'=>$line['line_group_pid']])->value('name');
$line['laiyuan']=$name;
}
$group_info1 = $group_info=Db::name('tel_line_group')->where('id',$line['id'])->find();
while( !empty($group_info1) &&$group_info1['line_group_pid']!=0){
$group_info1 = Db::name('tel_line_group')->where('id',$group_info1['line_group_pid'])->find();
}
if(empty($group_info1)) return false;
$line['line_num'] = Db::name('tel_line')->where(['group_id'=>$group_info1['id'],'member_id'=>$group_info1['user_id'] ] )->count('*');
$line['sales_price'] = aitel_round($line['sales_price'],'线路');
$lines[$k]=$line;
}
$count = Db::name('tel_line_group')->where($where)->count('1');
$page_count = ceil($count/$Page_size);
$data['total']=$count;
$data['page_count']=$page_count;
$data['lines']=$lines;
$data['Nowpage']=$page;
$data['limit']=$Page_size;
$fenpeilines = Db::name('tel_line_group')->where(['user_id'=>$uid,'status'=>1])->select();
$adminid = input('post.adminid','','trim,strip_tags');
if(!empty($adminid)){
$whereadmin['id']=$adminid;
}
$adminname = input('post.adminname','','trim,strip_tags');
if(!empty($adminname)){
$whereadmin['username']=['like','%'.$adminname.'%'];
}
$whereadmin['pid']=$uid;
$whereadmin['status']=['eq',1];
$whereadmin['role_id']=['neq',20];
$adminsInfo =Db::name('admin')->where($whereadmin)->select();
$data['adminInfo']=$adminsInfo;
$data['fenpeilines']=$fenpeilines;
$data['admin']=$admin;
return returnAjax(0,'数据显示成功',$data);
}
public function fenpei_view(){
$uid = input('post.uid','','trim,strip_tags');
$where['user_id']=$uid;
$where['status']=1;
$where['line_group_pid']=['>',0 ];
$lines = Db::name('tel_line_group')->where($where)->select();
foreach($lines as $k=>$line){
$linechengben=Db::name('tel_line_group')->where(['id'=>$line['line_group_pid']])->find();
$lines[$k]['chengben'] = aitel_round($linechengben['sales_price'],'线路');
$lines[$k]['sales_price'] = aitel_round($line['sales_price'],'线路');
}
$lines = array_values($lines);
$username = Db::name('admin')->field('username')->where(['id'=>$uid])->value('username');
$role_name = getRoleNameByUserId($uid);
$data['username']=$username;
$data['role_name']=$role_name;
$data['lines']=$lines;
return returnAjax(0,'获取成功',$data);
}
public function fenpei_get_username(){
$uid = input('post.uid','','trim,strip_tags');
$where = [];
$where['id'] = array('eq',$uid);
$admin = Db::name('admin')->where($where)->find();
if(!empty($admin)){
return returnAjax(0,'',$admin);
}
}
public function fenpei_line_price(){
$line_id = input('post.line_id','','trim,strip_tags');
$uid =input('post.uid','','trim,strip_tags');
if(empty($line_id)){
$line['sales_price']=0;
return returnAjax(1,'',$line);
}
$line_befor = Db::name('tel_line_group')->where(['line_group_pid'=>$line_id,'user_id'=>$uid])->find();
if(!empty($line_befor)){
$line = Db::name('tel_line_group')->where(['id'=>$line_id])->find();
$line['sales_price_fen'] = aitel_round($line_befor['sales_price'],'线路');
return returnAjax(0,'',$line);
}
$line = Db::name('tel_line_group')->where(['id'=>$line_id])->find();
if(!empty($line)){
$line['sales_price_fen']=0;
return returnAjax(0,'',$line);
}else{
$line['sales_price']=0;
return returnAjax(1,'',$line);
}
}
public function add_fenpei(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$fenpeiuid = input('post.uid','','trim,strip_tags');
$line_id = input('post.line_id','','trim,strip_tags');
$sales_price = input('post.sales_price','','trim,strip_tags');
if(!is_numeric($sales_price) &&$user_auth['role'] != '商家'){
return returnAjax(1,'价格必须是数字');
}
if($user_auth['role'] == '商家'){
$sales_price = Db::name('tel_line_group')
->where('id',$line_id)
->value('sales_price');
}
$pid = Db::name('admin')->where(['id'=>$fenpeiuid])->value('pid');
if($pid!=$uid){
return returnAjax(1,'当前账户不是被分配账户的上一级请重新分配');
}
$notes = input('post.notes','','trim,strip_tags');
$where = ['l.user_id'=>$fenpeiuid,'l.line_group_pid'=>$line_id,'l.status'=>1];
$line = Db::name('tel_line_group')
->alias('l')
->join('admin a','a.id = l.user_id','LEFT')
->join('admin_role ar','ar.id = a.role_id','LEFT')
->field('ar.name as role_name, l.id')
->where($where)
->find();
if(!empty($line) &&count($line)>0){
$ids = [];
$ids[] = $line['id'];
if($line['role_name'] == '商家'){
$find_ids = Db::name('tel_line_group')->where('line_group_pid',$line['id'])->field('id')->select();
foreach($find_ids as $find_key=>$find_value){
$ids[] = $find_value['id'];
}
}
$line = [];
$line['sales_price'] = $sales_price;
$line['remark'] = $notes;
$line['create_time'] = $this->time;
$rasx = Db::name('tel_line_group')->where('id','in',$ids)->update($line);
if($rasx){
$datax['owner']=$uid;
$datax['user_id']=$fenpeiuid;
$datax['operation_type']=3;
$datax['operation_fu']="重新分配线路组";
$datax['record_content']="给用户名为:".getUsernameById($fenpeiuid).'的用户重新分配线路组,线路组名为：'.getPhoneName($line_id).',价格修改为'.$sales_price;
$datax['operation_date']=time();
Db::name('operation_record')->insertGetId($datax);
return returnAjax(0,'重新分配成功');
}else{
return returnAjax(1,'重新分配失败');
}
}
$line=Db::name('tel_line_group')->where(['id'=>$line_id])->find();
$data['name']=$line['name'];
$data['user_id']=$fenpeiuid;
$data['line_group_pid']=$line_id;
$data['sales_price']=$sales_price;
$data['remark'] = $notes;
$data['create_time']=$this->time;
$res = Db::name('tel_line_group')->insertGetId($data);
if($res){
$datax['owner']=$uid;
$datax['user_id']=$fenpeiuid;
$datax['operation_type']=3;
$datax['operation_fu']="分配线路组";
$datax['record_content']="给用户名为:".getUsernameById($fenpeiuid).'的用户分配线路组，线路组名为：'.$line['name'].'，线路组价格为：'.$sales_price;
$datax['operation_date']=time();
Db::name('operation_record')->insertGetId($datax);
return returnAjax(0,'分配成功');
}else{
return returnAjax(1,'分配失败');
}
}
public function fenpeiDetails($line_group_pid,$line_user_pid,$fenpei_group_id,$fenpei_user_id){
Db::startTrans();
try{
Db::name('tel_line')->where(['group_id'=>$fenpei_group_id,'member_id'=>$fenpei_user_id])->delete();
$datas=Db::name('tel_line')->where(['group_id'=>$line_group_pid,'member_id'=>$line_user_pid])->select();
$datas=array_map(function($arr)use($fenpei_group_id){
$arr['group_id']=$fenpei_group_id;return $arr;
},$datas);
Db::name('tel_line')->insertAll($datas);
}catch(Exception $e){
Db::rollback();
}
Db::commit();
}
public function  getUsernameByLineId($lineid){
if(empty($lineid)){
return false;
}
$user_id = Db::name('tel_line_group')->where(['id'=>$lineid])->value('user_id');
if(empty($user_id)){
return false;
}
$username = Db::name('admin')->where(['id'=>$user_id])->value('username');
if(empty($username)){
return false;
}
return $username;
}
public function  getUsernameByLineGroupId($line_group_id){
if(empty($line_group_id)){
return false;
}
$member_id = Db::name('tel_line_group')->where(['id'=>$line_group_id])->value('user_id');
if(empty($member_id)){
return false;
}
$username = Db::name('admin')->where(['id'=>$member_id])->value('username');
if(empty($username)){
return false;
}
return $username;
}
public function delet_line(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$id = input('post.id','','trim,strip_tags');
$arr = input('post.arr/a','','trim,strip_tags');
if(!empty($id)){
$crr = $this->get_son_line($id);
$crr[]=$id;
$whereone['id']=['in',$crr];
$lineName = getPhoneName($id);
$lineUserName = $this->getUsernameByLineId($id);
$res = Db::name('tel_line_group')->where($whereone)->delete();
if(!empty($res)){
$data['owner']=$uid;
$data['user_id']=$uid;
$data['operation_type']=3;
$data['operation_fu']="删除线路组";
if(empty($lineUserName)){
$data['record_content']="删除了线路组数据,线路组名:".$lineName;
}else{
$data['record_content']="删除所属用户名为:".$lineUserName."的线路组数据,线路组名:".$lineName;
}
$data['operation_date']=time();
Db::name('operation_record')->insertGetId($data);
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}elseif(!empty($arr)){
$brr=[];
$xrr = $this->get_son_line($arr);
foreach($arr as $k=>$v){
$xrr[]=$v;
$brr[$k]['line_name']=getPhoneName($v);
$brr[$k]['id']=$v;
$brr[$k]['username']=$this->getUsernameByLineId($v);
}
$where['id']=['in',$xrr];
$res=Db::name('tel_line_group')->where($where)->delete();
if(!empty($res)){
foreach($brr as $k=>$v){
if(empty($v['username'])){
$username='';
$data['record_content']="删除了线路数据,线路名:".$v['line_name'];
}else{
$username=$v['username'];
$data['record_content']="删除了所属用户名为:".$username."线路数据,线路名:".$v['line_name'];
}
$data['owner']=$uid;
$data['user_id']=$uid;
$data['operation_type']=3;
$data['operation_fu']="删除线路";
$data['operation_date']=time();
Db::name('operation_record')->insertGetId($data);
}
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}else{
return returnAjax(1,'请至少选择一项再删除');
}
}
public function delet_line_group(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$id = input('post.id','','trim,strip_tags');
$arr = input('post.arr/a','','trim,strip_tags');
if(!empty($id)){
if($this->getGroupSonLine($id)){
return returnAjax(1,'请先删除线路组内线路。删除失败！');
}
$crr = $this->get_son_line($id);
$crr[]=$id;
$whereone['id']=['in',$crr];
$lineName = $this->getLineGroupName($id);
$lineUserName = $this->getUsernameByLineGroupId($id);
$res = Db::name('tel_line_group')->where($whereone)->delete();
if(!empty($res)){
$data['owner']=$uid;
$data['user_id']=$uid;
$data['operation_type']=3;
$data['operation_fu']="删除线路组";
if(empty($lineUserName)){
$data['record_content']="删除了线路组数据,线路组名:".$lineName;
}else{
$data['record_content']="删除所属用户名为:".$lineUserName."的线路组数据,线路名:".$lineName;
}
$data['operation_date']=time();
Db::name('operation_record')->insertGetId($data);
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}elseif(!empty($arr)){
$brr=[];
$xrr = $this->get_son_line($arr);
foreach($arr as $k=>$v){
if($this->getGroupSonLine($v)){
return returnAjax(1,'请先删除线路组内线路。删除失败！');
}
$xrr[]=$v;
$brr[$k]['line_name']=$this->getLineGroupName($v);
$brr[$k]['id']=$v;
$brr[$k]['username']=$this->getUsernameByLineGroupId($v);
}
$where['id']=['in',$xrr];
$res=Db::name('tel_line_group')->where($where)->delete();
if(!empty($res)){
foreach($brr as $k=>$v){
if(empty($v['username'])){
$username='';
$data['record_content']="删除了线路数据,线路名:".$v['line_name'];
}else{
$username=$v['username'];
$data['record_content']="删除了所属用户名为:".$username."线路数据,线路名:".$v['line_name'];
}
$data['owner']=$uid;
$data['user_id']=$uid;
$data['operation_type']=3;
$data['operation_fu']="删除线路";
$data['operation_date']=time();
Db::name('operation_record')->insertGetId($data);
}
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}else{
return returnAjax(1,'请至少选择一项再删除');
}
}
public function add_line(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$id = input('post.id','','trim,strip_tags');
$name = input('post.name','','trim,strip_tags');
$type_link = input('type_link','','trim,strip_tags');
$group_id = input('group_id','','trim,strip_tags');
if(empty($name)){
return returnAjax(1,'线路名字不能为空');
}
$line_count = Db::name('tel_line')
->where([
'member_id'=>$uid,
'group_id'=>$group_id,
'name'=>$name,
'status'=>1,
'id'=>['neq',$id],
])
->count();
if (!empty($line_count)) {
return returnAjax(1,'线路组名称不能重复');
}
if(!$type_link){
return returnAjax(1,'请选择线路类型');
}
$inter_ip = input('post.inter_ip','','trim,strip_tags');
$call_prefix = input('post.call_prefix','','trim,strip_tags');
$type = input('post.type','','trim,strip_tags');
if($type==1){
$data['dial_format']='sofia/external/'.$call_prefix.'%s@'.$inter_ip;
}elseif($type==0){
$data['dial_format']='sofia/gateway/'.$call_prefix.'%s@'.$inter_ip;
}
if(!isIp($inter_ip)){
return returnAjax(1,'ip格式错误');
}
$remark = input('post.remark','','trim,strip_tags');
$data['name']=$name;
$data['type']=$type;
$data['inter_ip']=$inter_ip;
$data['call_prefix']=$call_prefix;
$data['remark']=$remark;
$data['status']=1;
$data['pid']=0;
$data['group_id']=$group_id;
$data['member_id']=$uid;
$data['create_time']=$this->time;
$data['type']=$type;
$data['type_link']=$type_link;
if(!empty($id)){
$line = Db::name('tel_line')->where(['id'=>$id])->find();
if($line['pid']!=0 ||!empty($line['pid'])){
return returnAjax(1,'您没有编辑此线路的的权限');
}
if($id){
$tel_line = Db::name('tel_line')->where('id',$id)->find();
$res = Db::name('tel_line')->where(['id'=>$id])->update($data);
if(!empty($res)){
$group_name = Db::name('tel_line_group')->where('id',$group_id)->value('name');
$datax['owner']=$uid;
$datax['user_id']=$uid;
$datax['operation_type']=3;
$datax['operation_fu']="编辑线路";
$str='';
if($name!=$tel_line['name']){
$str.='编辑线路，在线路组名字为:'.$group_name."中编辑线路，线路以前的名字为:".$tel_line['name'].'修改为现在的名字：'.$name;
}
if($inter_ip!=$tel_line['inter_ip']){
if(empty($str)){
$str.='编辑线路，在线路组名字为:'.$group_name."中编辑线路，修改线路的ip为:".$inter_ip;
}else{
$str.="。修改线路ip为:".$inter_ip;
}
}
if($call_prefix!=$tel_line['call_prefix']){
if(empty($str)){
$str.='编辑线路，在线路组名字为:'.$group_name."中编辑线路，修改呼叫前缀为:".$call_prefix;
}else{
$str.="。修改线路呼叫前缀为:".$call_prefix;
}
}
$type_name = $type_link==1?'ip':'网关';
if($type_link!=$tel_line['type_link']){
if(empty($str)){
$str.='编辑线路，在线路组名字为:'.$group_name."中编辑线路，修改线路对接类型为:".$type_name;
}else{
$str.="。修改线路线路对接类型为:".$type_name;
}
}
$str=trim($str,'。');
$datax['record_content'] = $str;
$datax['operation_date']=time();
if(!empty($str)){
Db::name('operation_record')->insertGetId($datax);
}
return returnAjax(0,'更新成功');
}else{
return returnAjax(1,'更新失败');
}
}
}else{
$res = Db::name('tel_line')->insertGetId($data);
if(!empty($res)){
$group_name = Db::name('tel_line_group')->where('id',$group_id)->value('name');
$datax['owner']=$uid;
$datax['user_id']=$uid;
$datax['operation_type']=3;
$datax['operation_fu']="新增线路";
$datax['record_content']="新增线路，在线路组名字为:".$group_name."中添加新的线路，线路名:".$data['name'];
$datax['operation_date']=time();
Db::name('operation_record')->insertGetId($datax);
return returnAjax(0,'新增成功');
}
}
}
public function add_line_group(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$id = input('post.id','','trim,strip_tags');
$name = input('post.name','','trim,strip_tags');
$sales_price = input('sales_price','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
if(empty($name)){
return returnAjax(1,'线路组名字不能为空');
}
$line_count = Db::name('tel_line_group')
->where([
'user_id'=>$uid,
'name'=>$name,
'status'=>1,
'id'=>['neq',$id],
])
->count();
if (!empty($line_count)) {
return returnAjax(1,'线路组名称不能重复');
}
if(!is_numeric($sales_price)){
return returnAjax(1,'价格必须是数字');
}
$data['name']=$name;
$data['sales_price']=$sales_price;
$data['remark']=$remark;
$data['status']=1;
$data['user_id']=$uid;
$data['create_time']=$this->time;
$data['line_group_pid']=0;
if(!empty($id)){
$line = Db::name('tel_line_group')->where(['id'=>$id])->find();
if($line['line_group_pid']!=0 ||!empty($line['line_group_pid'])){
return returnAjax(1,'您没有编辑此线路的的权限');
}
if(empty($this->get_son_line($id))){
$sales_price_befo = Db::name('tel_line_group')->where(['id'=>$id])->value('sales_price');
$res = Db::name('tel_line_group')->where(['id'=>$id])->update($data);
if(!empty($res)){
$datax['owner']=$uid;
$datax['user_id']=$uid;
$datax['operation_type']=3;
$datax['operation_fu']="编辑线路组";
if($sales_price_befo!=$sales_price){
$datax['record_content']="编辑线路组价格:".$sales_price.",线路组名:".getPhoneName($id);
}else{
$datax['record_content']="编辑线路,线路名:".getPhoneName($id);
}
$datax['operation_date']=time();
Db::name('operation_record')->insertGetId($datax);
return returnAjax(0,'更新成功');
}else{
return returnAjax(1,'更新失败');
}
}else{
Db::startTrans();
$res = Db::name('tel_line')->where(['id'=>$id])->update($data);
$xrr=$this->get_son_line($id);
$resx = Db::name('tel_line')->where(['id'=>['in',$xrr]])->update(['name'=>$data['name'] ] );
if(!empty($res) &&!empty($resx)){
Db::commit();
$datax['owner']=$uid;
$datax['user_id']=$uid;
$datax['operation_type']=3;
$datax['operation_fu']="编辑线路";
$datax['record_content']="编辑线路,线路名:".getPhoneName($id);
$datax['operation_date']=time();
Db::name('operation_record')->insertGetId($datax);
return returnAjax(0,'更新成功');
}else{
Db::rollback();
return returnAjax(1,'更新失败');
}
}
}else{
$res = Db::name('tel_line_group')->insertGetId($data);
if(!empty($res)){
$datax['owner']=$uid;
$datax['user_id']=$uid;
$datax['operation_type']=3;
$datax['operation_fu']="新增线路组";
$datax['record_content']="新增线路组,线路组名:".$data['name']."线路组价格：".$data['sales_price'].'元/分钟';
$datax['operation_date']=time();
Db::name('operation_record')->insertGetId($datax);
return returnAjax(0,'新增成功');
}
}
}
public function editLine_view(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$id = input('post.id','','trim,strip_tags');
$line = Db::name('tel_line')->where(['id'=>$id])->find();
if($line['pid']!=0 ||!empty($line['pid'])){
return returnAjax(1,'您没有编辑此线路的的权限');
}
$where['id']=$id;
$where['member_id']=$uid;
if(empty($id)){
return returnAjax(1,'请选择一项再编辑');
}
$line = Db::name('tel_line')->where($where)->find();
return returnAjax(0,'显示成功',$line);
}
public function get_son_line($line_group_id,&$array=[]){
$where['line_group_pid']=['in',$line_group_id];
$lines = Db::name('tel_line_group')->where($where)->select();
if(!empty($lines)){
foreach($lines as $k=>$v){
$arr[$k]=$v['id'];
$array[]=$v['id'];
}
$this->get_son_line($arr,$array);
}
return $array;
}
function getLineGroupName($line_group_id){
if(empty($line_group_id)){
return '';
}
$where['id']=$line_group_id;
$tel_line= Db::name("tel_line_group")->field('name')->where($where)->find();
if(!empty($tel_line)){
return $tel_line['name'];
}else{
return '';
}
}
public function getLineInGroup(){
$where=$this->postData;
$lineDb=Db::name('tel_line');
$group_info1 = $group_info=Db::name('tel_line_group')->where('id',$where['group_id'])->find();
while( !empty($group_info1) &&$group_info1['line_group_pid']!=0){
$group_info1 = Db::name('tel_line_group')->where('id',$group_info1['line_group_pid'])->find();
}
if(empty($group_info1)) return false;
if(!empty($where['line_name']) ){$where['name']=['like','%'.$where['line_name'].'%'];unset($where['line_name']);}
if(!empty($where['user_id']) ){$where['member_id']=$group_info1['user_id'];unset($where['user_id']);}
if(!empty($where['group_id']) ){$where['group_id']=$group_info1['id'] ;}
$count=$lineDb->where($where)->count('*');
$lines=$lineDb->where($where)->page($this->pageData['page'],$this->pageData['pageLimit'])->select();
$lines=array_map(function($v)use($group_info){
$v['create_time'] =date('Y-m-d H:i:s',$v['create_time']);
$v['group_name']  = $group_info['name'] ;
$v['allow_operate']=$group_info['line_group_pid']==0?1:0;
return $v;
},$lines);
$data['totalCount']=$count;
$data['data']=$lines;
if($lines){
return    returnAjax(0,'获取成功',$data);
}else{
return   returnAjax(1,'获取失败',$data);
}
}
public function delInGroup()
{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$id= input('id','','trim,strip_tags');
$tel_line = Db::name('tel_line')->where('id',$id)->find();
$extensionDb=Db::name('tel_line');
$where=$this->postData;
if(!empty($where['user_id']) ){$where['member_id']=$where['user_id'];unset($where['user_id']);}
$extensionDb->where($where);
$result=$extensionDb->delete();
$group_name = Db::name('tel_line_group')->where('id',$tel_line['group_id'])->value('name');
$datax['owner']=$uid;
$datax['user_id']=$uid;
$datax['operation_type']=3;
$datax['operation_fu']="删除线路";
$datax['record_content']="删除，在线路组名字为:".$group_name."中删除线路，线路名:".$tel_line['name'];
$datax['operation_date']=time();
Db::name('operation_record')->insertGetId($datax);
if(!empty($result)){
$this->afterDelLine($where['id']);
return $this->Json(0,'删除成功');
}
return $this->Json(1,'删除失败');
}
public function afterDelLine($id)
{
if (empty($id)) return false;
$taskData = new TaskData();
$taskArr = Db::name('tel_config')->where('call_phone_id',$id)->where('status','not in','-1,3')->select();
if (empty($taskArr)) return false;
foreach ($taskArr as $v) {
if (empty($v['fs_num'])) continue;
$line_id = $taskData->getMinLineId($v['call_phone_group_id']);
if(!$line_id){
$this->updateFsStopIt($v['fs_num'],$v['id']);
Db::name('tel_config')->where('id',$v['id'])->update(['status'=>6]);
}
Db::name('tel_config')->where('id',$v['id'])->update(['call_phone_id'=>$line_id]);
$this->updateFsLineData( $v['fs_num'],$line_id,$v['id']);
}
}
public function updateFsLineData($fs_num,$line_id,$task_id){
$line_data=Db::name('tel_line')->where('id',$line_id)->find();
$task_id = ['uuid'=>$task_id];
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$task['dial_format'] = $line_data['dial_format']??'';
$task['_origination_caller_id_number'] = $line_data['phone']??'';
$task['originate_variables'] = $line_data['originate_variables']??'';
$fs_config=config('db_configs.fs'.$fs_num);
if(!$fs_config)return false;
$rs=Db::connect($fs_config)->table('autodialer_task')->where($task_id)->update($task);
if(!$rs)return false;
return true;
}
public function updateFsStopIt($fs_num,$task_id){
$task_id = ['uuid'=>$task_id];
$task  =[];
$task['start'] = 2;
$task['alter_datetime'] = date("Y-m-d H:i:s",time());
$fs_config=config('db_configs.fs'.$fs_num);
if(!$fs_config)return false;
$rs=Db::connect($fs_config)->table('autodialer_task')->where($task_id)->update($task);
if(!$rs)return false;
return true;
}
public function getGroupSonLine($line_group_id){
return Db::name('tel_line')->where('group_id',$line_group_id)->value('id');
}
}
