<?php 

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use \think\Config;
use Qiniu\json_decode;
class Label extends User{
public function _initialize()
{
parent::_initialize();
}
public function index()
{
return $this->fetch();
}
public function get_label_data(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$label_name = input('label_name','','trim,strip_tags');
$Db_labellist = Db::name('tel_label')
->where([
'member_id'=>$uid,
'type'=>0
]);
$count = Db::name('tel_label')
->where([
'member_id'=>$uid,
'type'=>0
]);
if(!empty($label_name)){
$Db_labellist = $Db_labellist->where('label','like','%'.$label_name.'%');
$count = $count->where('label','like','%'.$label_name.'%');
}
$Db_labellist = $Db_labellist->page($page,$limit)
->order('id','desc')
->select();
$count = $count->count('id');
$list = $Db_labellist;
foreach ($list as $key =>$value) {
$list[$key]['sequence'] = ($page-1)*10+($key+1);
if(empty($value['label'])){
$list[$key]['label'] = '暂无';
}
if(empty($value['keyword'])){
$list[$key]['keyword'] = '暂无关键字';
}
}
return returnAjax(0,'获取数据成功',['list'=>$list,'count'=>$count]);
}
public function add_label(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$id = input('id','','trim,strip_tags');
$label_name = input('label_name','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$data = [
'member_id'=>$uid,
'label'=>$label_name,
'keyword'=>$keyword
];
$Db_labellist = Db::name('tel_label');
if(empty($id)){
$res = $Db_labellist->insert($data);
}else{
$res = $Db_labellist->where('id',$id)->update($data);
}
if(empty($res)){
return returnAjax(1,'添加或更新失败',$data);
}else{
return returnAjax(0,'添加或更新成功',$data);
}
}
public function delelte_label(){
$ids = input('ids/a','','trim,strip_tags');
if(empty($ids) === true ||is_array($ids) === false ||count($ids) === 0){
return returnAjax(2,'参数错误');
}
$result = Db::name('tel_label')
->where('id','in',$ids)
->delete();
if(!empty($result)){
return returnAjax(0,'成功');
}
return returnAjax(1,'失败');
}
public function labelinfo(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$label_name = input('label_name','','trim,strip_tags');
$info = Db::name('tel_label')
->field('id')
->where('member_id',$uid);
if(!empty($label_name)){
$info = $info->where('label','like','%'.$label_name.'%');
}
$info = $info->order('id','desc')
->select();
return returnAjax(0,'success',$info);
}
public function get_edit_label(){
$id = input('id','','trim,strip_tags');
$label_info = Db::name('tel_label')
->where('id',$id)
->find();
return returnAjax(0,'success',$label_info);
}
}
