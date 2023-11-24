<?php 
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use Qiniu\time;
class Menu extends User{
public function _initialize() {
parent::_initialize();
$request = request();
$action = $request->action();
}
public function index()
{
$user_auth = session('user_auth');
if(isset($user_auth['menu_grouping']) === false){
$user_auth['menu_grouping'] = 0;
}
$list = Db::name("Menu")
->where('menu_grouping',$user_auth['menu_grouping'])
->order('sort asc,id asc')
->select();
int_to_string($list,array('hide'=>array(1=>'是',0=>'否'),'is_dev'=>array(1=>'是',0=>'否')));
if (!empty($list)) {
$tree = new \com\Tree();
$list = $tree->toFormatTree($list);
}
Cookie('__forward__',$_SERVER['REQUEST_URI']);
$id2 = array();
foreach($list as $key=>$vo){
$list[$key]['grade'] = 3;
if($vo['pid'] == 0){
$list[$key]['grade'] = 1;
if(isset($vo['childs'])){
foreach($vo['childs'] as $key1=>$vo1){
$id2[]= $vo1;
}
}
}
}
foreach($list as $key=>$vo){
if(in_array($vo['id'],$id2) === true){
$list[$key]['grade'] = 2;
}
}
$this->setMeta('菜单列表');
$this->assign('role_name',$user_auth['role']);
$this->assign('list',$list);
return $this->fetch();
}
public function add(){
$user_auth = session('user_auth');
$role_name = $user_auth['role'];
if(IS_POST){
$mdata = array();
$mdata['title'] = input('title','','trim,strip_tags');
$mdata['type'] = input('type','','trim,strip_tags');
$mdata['icon'] = input('icon','','trim,strip_tags');
$mdata['pid'] = input('pid','','trim,strip_tags');
$mdata['sort'] = input('sort','','trim,strip_tags');
$mdata['url'] = input('url','','trim,strip_tags');
$mdata['hide'] = input('hide','','trim,strip_tags');
$mdata['tip'] = input('tip','','trim,strip_tags');
$mdata['group'] = input('group','','trim,strip_tags');
$mdata['is_dev'] = input('is_dev','','trim,strip_tags');
$mdata['create_time'] = date("Y-m-d H:i:s",time());
$mdata['status'] = 1;
$result = Db::name('Menu')->insertGetId($mdata);
$update_result = Db::name('Menu')
->where('id',$result)
->update([
'source_id'=>$result
]);
$mdata['status'] = 1;
if($result){
return returnAjax(0,'新建成功',$result);
}else{
return returnAjax(1,'error!',"新建失败");
}
}else{
$this->assign('info',array('pid'=>input('pid')));
$menus = Db::name('Menu')->where([
'menu_grouping'=>0
])->select();
$menu_result = [];
foreach($menus as $key=>$value){
if($value['pid'] === 0){
$menu_result[] = $value;
foreach($menus as $find_key=>$find_value){
if($find_value['pid'] === $value['id']){
$menu_result[] = $find_value;
}
}
}
}
$tree = new \com\Tree();
$menus = $tree->toFormatTree($menu_result);
if (!empty($menus)) {
$menus = array_merge(array(0=>array('id'=>0,'title_show'=>'顶级菜单')),$menus);
}else{
$menus = array(0=>array('id'=>0,'title_show'=>'顶级菜单'));
}
$this->assign('Menus',$menus);
$this->setMeta('新增菜单');
$this->assign('role_name',$role_name);
$this->assign('current','添加');
return $this->fetch();
}
}
public function edit(){
$role_name = session('user_auth.role');
if(IS_POST){
$mdata = array();
$mdata['title'] = input('title','','trim,strip_tags');
$mdata['type'] = input('type','','trim,strip_tags');
$mdata['icon'] = input('icon','','trim,strip_tags');
$mdata['pid'] = input('pid','','trim,strip_tags');
$mdata['sort'] = input('sort','','trim,strip_tags');
$mdata['status'] = input('status','','trim,strip_tags');
if($role_name === '管理员'||$mdata['status'] != 0){
$mdata['url'] = input('url','','trim,strip_tags');
}
$mdata['hide'] = input('hide','','trim,strip_tags');
$mdata['tip'] = input('tip','','trim,strip_tags');
$mdata['group'] = input('group','','trim,strip_tags');
$mdata['is_dev'] = input('is_dev','','trim,strip_tags');
$mdata['create_time'] = date("Y-m-d H:i:s",time());
$adminId = input('id','','trim,strip_tags');
$result = Db::name('Menu')->where('id',$adminId)->update($mdata);
if($result){
return returnAjax(0,'编辑成功',$result);
}else{
return returnAjax(1,'error!',"编辑失败");
}
}else{
$user_auth = session('user_auth');
$id = input('id','','trim,strip_tags');
$info = array();
$info = Db::name('Menu')->field(true)->find($id);
$menus = Db::name('Menu')->where([
'menu_grouping'=>$user_auth['menu_grouping']
])
->field(true)
->select();
$menu_result = [];
foreach($menus as $key=>$value){
if($value['pid'] === 0){
$menu_result[] = $value;
foreach($menus as $find_key=>$find_value){
if($find_value['pid'] === $value['id']){
$menu_result[] = $find_value;
}
}
}
}
$tree = new \com\Tree();
$menus = $tree->toFormatTree($menu_result);
$menus = array_merge(array(0=>array('id'=>0,'title_show'=>'顶级菜单')),$menus);
$this->assign('Menus',$menus);
if(false === $info){
return $this->error('获取后台菜单信息错误');
}
$this->assign('info',$info);
$this->assign('role_name',$role_name);
$this->assign('current','编辑');
return $this->fetch('add');
}
}
public function del(){
$id = $this->getArrayParam('id');
if (empty($id) ) {
return $this->error('请选择要操作的数据!');
}
$user_auth = session('user_auth');
if(is_array($id) === true){
foreach($id as $key=>$value){
$menu = Db::name('menu')
->field('status,menu_grouping')
->where('id',$value)
->find();
if($user_auth['role'] != '管理员'&&$menu['status'] == 0){
return returnAjax(2,'非管理员不能删除默认菜单');
}
if($user_auth['uid'] != $menu['menu_grouping'] &&$user_auth['role'] != '管理员'){
return returnAjax(2,'无改动权限');
}
}
}else{
$menu = Db::name('menu')
->field('status,menu_grouping')
->where('id',$id)
->find();
if($user_auth['role'] != '管理员'&&$menu['status'] == 0){
return returnAjax(2,'非管理员不能删除默认菜单');
}
if($user_auth['uid'] != $menu['menu_grouping'] &&$user_auth['role'] != '管理员'){
return returnAjax(2,'无改动权限');
}
}
$map = array('source_id'=>array('in',$id) );
if(db('Menu')->where($map)->delete()){
session('admin_menu_list',null);
action_log('update_menu','Menu',$id,$user_auth['uid']);
return $this->success('删除成功');
}else {
return $this->error('删除失败！');
}
}
public function setstatus(){
$adminId = input('arrayIds/a','','trim,strip_tags');
$status = input('status','','trim,strip_tags');
$data=array();
$data['status'] = $status;
$list = Db::name('admin')->where('id','in',$adminId)->update($data);
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function delAdmin(){
$adminId = input('admin_id/a','','trim,strip_tags');
$list = Db::name('admin')->where('id','in',$adminId)->delete();
if($list){
return returnAjax(0,'成功',$list);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function editable($name=null,$value=null,$pk=null){
if ($name &&($value != null ||$value != '') &&$pk) {
Db::name('Menu')->where(array('id'=>$pk))->setField($name,$value);
}
}
public function toogleHide($id,$value = 1){
session('admin_menu_list',null);
$result = Db::name('Menu')->where(array('id'=>$id))->setField(array('hide'=>$value));
if($result !==false ) {
return $this->success('操作成功！');
}else{
return $this->error('操作失败！');
}
}
public function toogleDev($id,$value = 1){
session('admin_menu_list',null);
$result = Db::name('Menu')->where(array('id'=>$id))->setField(array('is_dev'=>$value));
if($result !==false ) {
return $this->success('操作成功！');
}else{
return $this->error('操作失败！');
}
}
public function sort(){
if(IS_GET){
$ids = input('ids');
$pid = input('pid');
$map = array('status'=>array('gt',-1));
if(!empty($ids)){
$map['id'] = array('in',$ids);
}else{
if($pid !== ''){
$map['pid'] = $pid;
}
}
$list = db('Menu')->where($map)->field('id,title')->order('sort asc,id asc')->select();
$this->assign('list',$list);
$this->setMeta('菜单排序');
return $this->fetch();
}elseif (IS_POST){
$ids = input('post.ids');
$ids = explode(',',$ids);
foreach ($ids as $key=>$value){
$res = db('Menu')->where(array('id'=>$value))->setField('sort',$key+1);
}
if($res !== false){
session('admin_menu_list',null);
return $this->success('排序成功！');
}else{
return $this->error('排序失败！');
}
}else{
return $this->error('非法请求！');
}
}
public function importFile($tree = null,$pid=0){
if($tree == null){
$file = APP_PATH."Admin/Conf/Menu.php";
$tree = require_once $file;
}
$menuModel = D('Menu');
foreach ($tree as $value) {
$add_pid = $menuModel->add(
array(
'title'=>$value['title'],
'url'=>$value['url'],
'pid'=>$pid,
'hide'=>isset($value['hide'])?(int)$value['hide'] : 0,
'tip'=>isset($value['tip'])?$value['tip'] : '',
'group'=>$value['group'],
)
);
if($value['operator']){
$this->import($value['operator'],$add_pid);
}
}
}
public function import(){
if(IS_POST){
$tree = input('post.tree');
$lists = explode(PHP_EOL,$tree);
$menuModel = db('Menu');
if($lists == array()){
return $this->error('请按格式填写批量导入的菜单，至少一个菜单');
}else{
$pid = input('post.pid');
foreach ($lists as $key =>$value) {
$record = explode('|',$value);
if(count($record) == 4){
$menuModel->add(array(
'title'=>$record[0],
'url'=>$record[1],
'pid'=>$record[2],
'sort'=>0,
'hide'=>0,
'tip'=>'',
'is_dev'=>0,
'group'=>$record[3],
));
}
}
session('admin_menu_list',null);
return $this->success('导入成功',url('index?pid='.$pid));
}
}else{
$this->setMeta('批量导入后台菜单');
$pid = (int)input('get.pid');
$this->assign('pid',$pid);
$data = db('Menu')->where("id={$pid}")->field(true)->find();
$this->assign('data',$data);
return $this->fetch();
}
}
}