<?php 

namespace app\user\controller;
use app\common\controller\User;
use app\common\controller\AdminData;
use app\common\controller\AsrData;
use think\Db;
class Asr extends User{
public $table_name = 'tel_interface';
public function list(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$info = Db::name('admin')->where('id',$uid)->find();
$role_id = $info['role_id'];
$this->assign('role_id',$role_id);
$asrs = Db::name($this->table_name)
->field('id,name,sale_price')
->where('owner',$user_auth['uid'])
->select();
$this->assign('asrs',$asrs);
if($user_auth['role'] == '管理员'){
$role_options = [];
}else if($user_auth['role'] == '运营商'){
$role_options = [
'代理商',
'商家',
'销售人员'
];
}else if($user_auth['role'] == '代理商'){
$role_options = [
'商家',
'销售人员'
];
}else{
$role_options = [
'销售人员'
];
}
$this->assign('role_options',$role_options);
$where = [];
$where['a.pid'] = array('eq',$user_auth['uid']);
$where['a.role_id'] = array('neq',20);
$find_users = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->field('a.id,a.username,ar.name as role_name')
->where($where)
->select();
$this->assign('find_users',$find_users);
$support_asr_type = config('support_asr_type');
$this->assign('support_asr_type',$support_asr_type);
return $this->fetch();
}
public function get_find_users()
{
$user_auth = session('user_auth');
$where = [];
$where['a.status'] = 1;
$where['a.pid'] = $user_auth['uid'];
$where['a.role_id'] = ['<',20];
$role_name = input('role_name','','trim,strip_tags');
if(!empty($role_name)){
$where['ar.name'] = ['like','%'.$role_name.'%'];
}
$user_id = input('user_id','','trim,strip_tags');
if(!empty($user_id)){
$where['a.id'] = $user_id;
}else{
$user_name = input('username','','trim,strip_tags');
if(!empty($user_name)){
$where['a.username'] = ['like','%'.$user_name.'%'];
}
}
$count = input('count','','trim,strip_tags');
if(!empty($count)){
$page = ceil($count / 15) +1;
}else{
$page = 1;
}
$find_users = Db::name('admin')
->field('a.*,ar.name as role_name')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where($where)
->page($page,15)
->select();
return $this->Json(0,'成功',$find_users);
}
public function distribution_asr_api()
{
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$asr_id = input('asr_id','','trim,strip_tags');
$member_id = input('member_id','','trim,strip_tags');
if($user_auth['role'] != '商家'){
$sale_price = input('sale_price','','trim,strip_tags');
}else{
$sale_price = Db::name($this->table_name)
->where('id',$asr_id)
->value('sale_price');
}
$note = input('note','','trim,strip_tags');
if(empty($asr_id) ||empty($member_id)){
return $this->Json(3,'参数错误');
}
$count = Db::name($this->table_name)
->where([
'owner'=>$user_auth['uid'],
'id'=>$asr_id
])
->count('id');
if($count == 0){
return $this->Json(2,'无效ASR');
}
$find_asr_id = Db::name($this->table_name)
->where([
'owner'=>$member_id,
'pid'=>$asr_id
])
->value('id');
if(empty($find_asr_id)){
$asr_data = Db::name($this->table_name)
->where('id',$asr_id)
->find();
unset($asr_data['id']);
$asr_data['pid'] = $asr_id;
$asr_data['sale_price'] = $sale_price;
$asr_data['owner'] = $member_id;
$asr_data['create_time'] = time();
$asr_data['note'] = $note;
$new_asr_id = Db::name($this->table_name)
->insertGetId($asr_data);
if(!empty($new_asr_id)){
$AsrData = new AsrData();
$AsrData->update_asr_config_file([$new_asr_id]);
$data['owner'] = $uid;
$data['user_id'] = $member_id;
$data['operation_type'] = 8;
$data['operation_fu'] = 'ASR管理';
$data['record_content'] = "给用户名为:".getUsernameById($member_id).'的用户，分配ASR名字为：'.$asr_data['name'].'，价格为:'.$sale_price;
$data['operation_date'] = time();
$data['remark'] = !empty($remark) ??'';
Db::name('operation_record')->insert($data);
return $this->Json(0,'成功');
}else{
return $this->Json(1,'失败');
}
}else{
$name = Db::name($this->table_name)
->where('id',$find_asr_id)
->value('name');
$count = Db::name($this->table_name)
->where([
'id'=>$find_asr_id,
'sale_price'=>$sale_price,
'note'=>$note,
])
->count('id');
if(!empty($count)){
return $this->Json(0,'成功');
}
$update_data = [
'sale_price'=>$sale_price,
'note'=>$note
];
$update_result = Db::name($this->table_name)
->where('id',$find_asr_id)
->update($update_data);
$current_asr_user_role = Db::name($this->table_name)
->alias('ti')
->join('admin a','a.id = ti.owner','LEFT')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('ti.id',$find_asr_id)
->value('ar.name');
if($current_asr_user_role == '商家'){
Db::name($this->table_name)->where('pid',$find_asr_id)->update($update_data);
}
if(!empty($update_result)){
$data['owner'] = $uid;
$data['user_id'] = $member_id;
$data['operation_type'] = 8;
$data['operation_fu'] = 'ASR管理';
$data['record_content'] = "给用户名为:".getUsernameById($member_id).'的用户，重新分配ASR，名字为：'.$name.'，价格为:'.$sale_price;
$data['operation_date'] = time();
$data['remark'] = !empty($remark) ??'';
Db::name('operation_record')->insert($data);
return $this->Json(0,'成功');
}else{
return $this->Json(1,'失败');
}
}
}
public function add_asr()
{
$name = input('name','','trim,strip_tags');
$app_key = input('app_key','','trim,strip_tags');
$app_secret = input('app_secret','','trim,strip_tags');
$project_key = input('project_key','','trim,strip_tags');
$sale_price = input('sale_price','','trim,strip_tags');
$type = input('type','aliyun','trim,strip_tags');
$note = input('note','','trim,strip_tags');
if(empty($name) ||empty($app_key) ||empty($app_secret)){
return $this->Json(3,'参数错误');
}
if($type != 'xfyun'&&$project_key == ''){
return $this->Json(3,'请填写项目密钥');
}
$support_asr_type = config('support_asr_type');
if(isset($support_asr_type[$type]) == false){
return $this->Json(3,'无效ASR类型');
}
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$new_asr_id = Db::name($this->table_name)
->insertGetId([
'name'=>$name,
'app_key'=>$app_key,
'app_secret'=>$app_secret,
'project_key'=>$project_key,
'sale_price'=>$sale_price,
'type'=>$type,
'owner'=>$user_auth['uid'],
'note'=>$note,
'status'=>1,
'pid'=>0,
'create_time'=>time()
]);
if(!empty($new_asr_id)){
$AsrData = new AsrData();
$AsrData->update_asr_config_file([$new_asr_id]);
$data['owner'] = $uid;
$data['user_id'] =$uid;
$data['operation_type'] = 8;
$data['operation_fu'] = 'ASR管理';
$data['record_content'] = '新增ASR，名字为：'.$name.'，价格：'.$sale_price;
$data['operation_date'] = time();
$data['remark'] = !empty($remark) ??'';
Db::name('operation_record')->insert($data);
return $this->Json(0,'成功');
}
return $this->Json(1,'失败');
}
public function update_asr(){
$data = array();
$data['name'] = input('name','','trim,strip_tags');
$data['app_key'] = htmlspecialchars_decode(input('app_key','','trim,strip_tags'));
$data['app_secret'] = htmlspecialchars_decode(input('app_secret','','trim,strip_tags'));
$data['project_key'] = htmlspecialchars_decode(input('project_key','','trim,strip_tags'));
$data['type'] = input('type','','trim,strip_tags');
$sale_price = input('sale_price','','trim,strip_tags');
$data['sale_price'] = $sale_price;
$data['note'] = input('note','','trim,strip_tags');
$data['update_time'] = time();
$asr_id = input('asr_id','','trim,strip_tags');
$asr = Db::name($this->table_name)
->where('id',$asr_id)
->find();
$update_result = Db::name($this->table_name)
->where('id',$asr_id)
->update($data);
if(empty($update_result)){
return $this->Json(3,'没有更新任何内容');
}
unset($data['sale_price']);
$user_ids = [];
$screen_ids = [];
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$user_ids[] = $user_auth['uid'];
$ids = Db::name($this->table_name)
->where('pid',$asr_id)
->column('id,owner');
while(count($ids) >0){
$in = [];
foreach($ids as $key=>$value){
$in[] = $key;
$screen_ids[] = $key;
if(in_array($value,$user_ids) === false){
$user_ids[] = $value;
}
}
$asrs = Db::name($this->table_name)
->where('pid','in',$in)
->column('id,owner');
$ids = $asrs;
foreach($asrs as $key=>$value){
if(in_array($value,$user_ids) === false){
$user_ids[] = $value;
}
}
}
if(count($screen_ids) >0){
$update_find_result = Db::name($this->table_name)->where('id','in',$screen_ids)->update($data);
}
if(!empty($update_result) ||(isset($update_find_result) &&!empty($update_find_result))){
$screen_ids[] = $asr_id;
$AsrData = new AsrData();
$AsrData->update_asr_config_file($screen_ids);
$str="";
$data_log['owner'] = $uid;
$data_log['user_id'] =$uid;
$data_log['operation_type'] = 8;
$data_log['operation_fu'] = 'ASR管理';
if($data['name'] !=$asr['name']){
$str.="编辑ASR，将ASR名字：".$asr['name']."修改为".$data['name'];
}
if($sale_price!=$asr['sale_price']){
if(!empty($str)){
$str.='。ASR价格从'.$asr['sale_price'].'修改为'.$sale_price;
}else{
$str.="编辑ASR，ASR价格从：".$asr['sale_price']."修改为".$sale_price;
}
}
$data_log['record_content'] =$str;
$data_log['operation_date'] = time();
$data_log['remark'] = !empty($remark) ??'';
if(!empty($str)){
Db::name('operation_record')->insert($data_log);
}
return $this->Json(0,'成功');
}else{
return $this->Json(1,'失1败');
}
}
public function get_asr(){
$id = input('id','','trim,strip_tags');
$data = Db::name($this->table_name)->where('id',$id)->find();
return $this->Json(0,'成功',$data);
}
public function delete_asr(){
$ids = input('id/a','','trim,strip_tags');
$user_auth = session('user_auth');
foreach($ids as $key=>$value){
$where = [
'owner'=>$user_auth['uid'],
'id'=>$value
];
$count = Db::name($this->table_name)->where($where)->count(1);
if($count == 0){
return returnAjax(2,'您删除的asr中存在不属于您的ASR ');
}
}
$asr_ids = [];
$asr_ids = array_merge($asr_ids,$ids);
$current_asr_ids = [];
$current_asr_ids = array_merge($current_asr_ids,$ids);
while(count($current_asr_ids)){
$current_asr_ids = Db::name($this->table_name)->where('pid','in',$current_asr_ids)->column('id');
$asr_ids = array_merge($asr_ids,$current_asr_ids);
}
$asr_datas = Db::name($this->table_name)->where('id','in',$asr_ids)->field('id, owner,name')->select();
$delete_result = Db::name($this->table_name)->where('id','in',$asr_ids)->delete();
if(empty($delete_result)){
\think\Log::record('ASR-delete_asr-删除ASR失败 - '.json_encode($asr_datas));
return returnAjax(1,'删除失败');
}
$vrs = config('view_replace_str');
$path = './uploads/asrapi/';
$name='';
foreach($asr_datas as $key=>$value){
$file = $path .$value['owner'] .'_'.$value['id'] .'_smartivr.json';
if(file_exists($file) === true){
unlink($file);
}
$name.='-'.$value['name'];
}
$name=trim($name,'-');
$data_log['owner'] = $user_auth['uid'];
$data_log['user_id'] = $user_auth['uid'];
$data_log['operation_type'] = 8;
$data_log['operation_fu'] = 'ASR管理';
$data_log['record_content'] = '删除ASR，名字为：'.$name;
$data_log['operation_date'] = time();
$data_log['remark'] = !empty($remark) ??'';
Db::name('operation_record')->insert($data_log);
return $this->Json(0,'删除成功');
}
public function delete_find_asr(){
$ids = input('id/a','','trim,strip_tags');
$user_auth = session('user_auth');
$member_id= Db::name('tel_interface')->where('id',$ids[0])->value('owner');
$asr_ids = [];
$asr_ids = array_merge($asr_ids,$ids);
$current_asr_ids = [];
$current_asr_ids = array_merge($current_asr_ids,$ids);
while(count($current_asr_ids)){
$current_asr_ids = Db::name($this->table_name)->where('pid','in',$current_asr_ids)->column('id');
$asr_ids = array_merge($asr_ids,$current_asr_ids);
}
$asr_datas = Db::name($this->table_name)->where('id','in',$asr_ids)->field('id, owner,name')->select();
$delete_result = Db::name($this->table_name)->where('id','in',$asr_ids)->delete();
if(empty($delete_result)){
\think\Log::record('ASR-delete_asr-删除ASR失败 - '.json_encode($asr_datas));
return returnAjax(1,'删除失败');
}
$vrs = config('view_replace_str');
$path = './uploads/asrapi/';
$name='';
foreach($asr_datas as $key=>$value){
$file = $path .$value['owner'] .'_'.$value['id'] .'_smartivr.json';
if(file_exists($file) === true){
unlink($file);
}
$name.='-'.$value['name'];
}
$name=trim($name,'-');
$data_log['owner'] = $user_auth['uid'];
$data_log['user_id'] = $user_auth['uid'];
$data_log['operation_type'] = 8;
$data_log['operation_fu'] = 'ASR管理';
$data_log['record_content'] = '删除给用户名为:'.getUsernameById($member_id).'，分配的ASR，ASR名字为：'.$name;
$data_log['operation_date'] = time();
$data_log['remark'] = !empty($remark) ??'';
Db::name('operation_record')->insert($data_log);
return $this->Json(0,'删除成功');
}
public function get_asrs()
{
$page = input('page','','trim,strip_tags');
if(empty($page)){
$page = 1;
}
$limit = input('limit','','trim,strip_tags');
if(empty($limit)){
$limit = 10;
}
$user_auth = session('user_auth');
$keyword = input('keyword','','trim,strip_tags');
$where = [];
$where['ti1.owner'] = $user_auth['uid'];
if(!empty($keyword)){
$where['ti1.name'] = ['like','%'.$keyword.'%'];
}
$asr_datas = Db::name($this->table_name)
->alias('ti1')
->join($this->table_name .' ti2','ti1.pid = ti2.id','LEFT')
->join('admin a','a.id = ti2.owner','LEFT')
->field('ti1.id,ti1.name,ti1.pid,ti1.sale_price,ti1.note,a.username as parent_username,ti1.asr_from,ti1.money')
->where($where)
->page($page,$limit)
->order('ti1.create_time desc')
->select();
$i = ($page -1) * $limit +1;
foreach($asr_datas as $key=>$value){
$asr_datas[$key]['key'] = $i;
if($value['pid'] != 0){
$asr_datas[$key]['p_name'] = $value['parent_username'];
}else{
$money = $value['money'];
$asr_datas[$key]['p_name'] = ('1'== $value['asr_from']) ?"批发[ 余额：{$money} ]": '自有ASR';
}
$asr_datas[$key]['sale_price'] = aitel_round($value['sale_price'],'ASR');
$asr_datas[$key]['asr_from'] = $value['asr_from'];
$i++;
}
$count = Db::name($this->table_name)
->alias('ti1')
->where($where)
->count('id');
return $this->Json(0,'成功',['data'=>$asr_datas,'count'=>$count]);
}
public function get_user_lines()
{
$member_id = input('member_id','','trim,strip_tags');
$asrs = Db::name($this->table_name)
->field('ti.*,ti_p.sale_price as cost')
->alias('ti')
->join($this->table_name .' ti_p','ti.pid = ti_p.id','LEFT')
->where([
'ti.owner'=>$member_id,
'ti.pid'=>['<>',0]
])
->select();
foreach($asrs as $key=>$value){
$asrs[$key]['create_time'] = date('Y-m-d H:i:s',$value['create_time']);
$asrs[$key]['sale_price'] = aitel_round($value['sale_price'],'ASR');
$asrs[$key]['cost'] = aitel_round($value['cost'],'ASR');
}
return $this->Json(0,'成功',$asrs);
}
public function get_distributable_asrs()
{
$member_id = input('member_id/d','','trim,strip_tags');
if(empty($member_id)){
return $this->Json(1,'用户ID不能为空');
}
$asr_datas = Db::name($this->table_name)
->where([
'owner'=>$member_id,
'pid'=>['<>',0]
])
->column('pid,sale_price');
$user_auth = session('user_auth');
$asrs = Db::name($this->table_name)
->field('id,name,sale_price as cost_price')
->where([
'owner'=>['=',$user_auth['uid']],
])
->select();
foreach($asrs as $key=>$value){
if(isset($asr_datas[$value['id']]) === true &&!empty($asr_datas[$value['id']])){
$asrs[$key]['sale_price'] = aitel_round($asr_datas[$value['id']],'ASR');
}else{
$asrs[$key]['sale_price'] = 0;
}
}
\think\Log::record('获取可分配的ASR');
return $this->Json(0,'成功',$asrs);
}
public function get_asr_statistical_data(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
if(empty($page)){
$page = 1;
}
$limit = input('limit','','trim,strip_tags');
if(empty($limit)){
$limit = 10;
}
$asrname = input('asrname','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$AdminData = new AdminData();
$where = [];
$where['asr.member_id'] = $uid;
if($asrname == '0'){
$asrname = '';
}
if(!empty($asrname)){
$where['i.name'] = $asrname;
}
if($username == '0'){
$username = '';
}
if(!empty($username)){
$where['a.username'] = ['like','%'.$username.'%'];
}
$statistical_data = Db::name('asr_charging_statistics')
->alias('asr')
->field('asr.*,i.name as asrname,i.pid as asr_pid,a.username')
->join('tel_interface i','asr.asr_id = i.id','LEFT')
->join('admin a','asr.find_member_id = a.id','LEFT')
->where($where)
->page($page,$limit)
->order('date desc')
->select();
$count = Db::name('asr_charging_statistics')
->alias('asr')
->field('asr.*,i.name as asrname,i.pid as asr_pid,a.username')
->join('tel_interface i','asr.asr_id = i.id','LEFT')
->join('admin a','asr.find_member_id = a.id','LEFT')
->where($where)
->count();
\think\Log::record('ASR查询');
foreach ($statistical_data as $key =>$value) {
if(empty($value['asr_pid'])){
$statistical_data[$key]['source_name'] = '自有ASR';
}else{
$asr_ppid[$key] = Db::name('tel_interface')->where('id',$value['asr_pid'])->value('pid');
if(empty($asr_ppid[$key])){
$statistical_data[$key]['source_name'] = '自有ASR';
}else{
$source[$key] = Db::name('tel_interface')
->alias('t')
->field('t.name,a.username,t.pid')
->join('admin a','t.owner = a.id','LEFT')
->where('t.id',$asr_ppid[$key])
->find();
$statistical_data[$key]['source_name'] = $source[$key]['username'].$source[$key]['name'];
}
}
if(empty($value['asrname'])){
$statistical_data[$key]['asrname'] = 'ASR已删除';
}
$statistical_data[$key]['key'] = ($page-1)*$limit+($key+1);
$statistical_data[$key]['usertype'] = $AdminData->get_role_name($value['find_member_id']);
}
$sum_asr_cnt =  Db::name('asr_charging_statistics')
->alias('asr')
->field('asr.*,i.name as asrname,i.pid as asr_pid,a.username')
->join('tel_interface i','asr.asr_id = i.id','LEFT')
->join('admin a','asr.find_member_id = a.id','LEFT')
->where($where)
->sum('asr_cnt');
if(empty($sum_asr_cnt)){
$sum_asr_cnt = 0;
}
$sum_cost_price_statistics = Db::name('asr_charging_statistics')
->alias('asr')
->field('asr.*,i.name as asrname,i.pid as asr_pid,a.username')
->join('tel_interface i','asr.asr_id = i.id','LEFT')
->join('admin a','asr.find_member_id = a.id','LEFT')
->where($where)
->sum('cost_price_statistics');
$sum_sale_price_statistics = Db::name('asr_charging_statistics')
->alias('asr')
->field('asr.*,i.name as asrname,i.pid as asr_pid,a.username')
->join('tel_interface i','asr.asr_id = i.id','LEFT')
->join('admin a','asr.find_member_id = a.id','LEFT')
->where($where)
->sum('sale_price_statistics');
$sum_profit = Db::name('asr_charging_statistics')
->alias('asr')
->field('asr.*,i.name as asrname,i.pid as asr_pid,a.username')
->join('tel_interface i','asr.asr_id = i.id','LEFT')
->join('admin a','asr.find_member_id = a.id','LEFT')
->where($where)
->sum('profit');
$sum_info['sum_asr_cnt'] = $sum_asr_cnt;
$sum_info['sum_cost_price_statistics'] = round($sum_cost_price_statistics,3);
$sum_info['sum_sale_price_statistics'] = round($sum_sale_price_statistics,3);
$sum_info['sum_profit'] = round($sum_profit,3);
$sum_info['limit'] = $limit;
$sum_info['total'] = $count;
$sum_info['Nowpage'] = $page;
$sum_info['page_count']=ceil($count/$limit);
\think\Log::record('查询ASR计费统计数据');
return returnAjax(0,'success',['list'=>$statistical_data,'count'=>$count,'sum_info'=>$sum_info]);
}
}
