<?php 

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
class Content extends User{
public function _initialize() {
parent::_initialize();
}
public function index() {
$map  = array('status'=>array('gt',-1));
$list = db('Category')->where($map)->order('sort asc,id asc')->column('*','id');
if (!empty($list)) {
$tree = new \com\Tree();
$list = $tree->toFormatTree($list);
}
$this->assign('tree',$list);
$this->setMeta('栏目列表');
return $this->fetch();
}
public function editable($name = null,$value = null,$pk = null) {
if ($name &&($value != null ||$value != '') &&$pk) {
db('Category')->where(array('id'=>$pk))->setField($name,$value);
}
}
public function edit($id = null,$pid = 0) {
if (IS_POST) {
$category = model('Category');
$result = $category->change();
if (false !== $result) {
action_log('update_category','category',$id,session('user_auth.uid'));
return $this->success('编辑成功！',url('index'));
}else {
$error = $category->getError();
return $this->error(empty($error) ?'未知错误！': $error);
}
}else {
$cate = '';
if ($pid) {
$cate = db('Category')->find($pid);
if (!($cate &&1 == $cate['status'])) {
return $this->error('指定的上级分类不存在或被禁用！');
}
}
$info = $id ?db('Category')->find($id) : '';
$this->assign('info',$info);
$this->assign('category',$cate);
$this->setMeta('编辑分类');
return $this->fetch();
}
}
public function add($pid = 0) {
$Category = model('Category');
if (IS_POST) {
$id = $Category->change();
if (false !== $id) {
action_log('update_category','category',$id,session('user_auth.uid'));
return $this->success('新增成功！',url('index'));
}else {
$error = $Category->getError();
return $this->error(empty($error) ?'未知错误！': $error);
}
}else {
$cate = array();
if ($pid) {
$cate = $Category->info($pid,'id,name,title,status');
if (!($cate &&1 == $cate['status'])) {
return $this->error('指定的上级分类不存在或被禁用！');
}
}
$this->assign('info',null);
$this->assign('category',$cate);
$this->setMeta('新增分类');
return $this->fetch('edit');
}
}
public function remove($id) {
if (empty($id)) {
return $this->error('参数错误!');
}
$child = db('Category')->where(array('pid'=>$id))->field('id')->select();
if (!empty($child)) {
return $this->error('请先删除该分类下的子分类');
}
$document_list = db('content')->where(array('category_id'=>$id))->field('id')->select();
if (!empty($document_list)) {
return $this->error('请先删除该分类下的文章（包含回收站）');
}
$res = db('Category')->where(array('id'=>$id))->delete();
if ($res !== false) {
action_log('update_category','category',$id,session('user_auth.uid'));
return $this->success('删除分类成功！');
}else {
return $this->error('删除分类失败！');
}
}
public function operate($type = 'move',$from = '') {
if ($type == 'move') {
$operate = '移动';
}elseif ($type == 'merge') {
$operate = '合并';
}else {
return $this->error('参数错误！');
}
if (empty($from)) {
return $this->error('参数错误！');
}
$map  = array('status'=>1,'id'=>array('neq',$from));
$list = db('Category')->where($map)->field('id,pid,title')->select();
if ($type == 'move') {
$list = tree_to_list(list_to_tree($list));
$pid = db('Category')->getFieldById($from,'pid');
$pid &&array_unshift($list,array('id'=>0,'title'=>'根分类'));
}
$this->assign('type',$type);
$this->assign('operate',$operate);
$this->assign('from',$from);
$this->assign('list',$list);
$this->setMeta($operate .'分类');
return $this->fetch();
}
public function move() {
$to   = input('post.to');
$from = input('post.from');
$res  = db('Category')->where(array('id'=>$from))->setField('pid',$to);
if ($res !== false) {
return $this->success('分类移动成功！',url('index'));
}else {
return $this->error('分类移动失败！');
}
}
public function merge() {
$to    = input('post.to');
$from  = input('post.from');
$Model = model('Category');
$res = Db::name('Content')->where(array('category_id'=>$from))->setField('category_id',$to);
if ($res !== false) {
Db::name('Category')->where(array('id'=>$from))->delete();
return $this->success('合并分类成功！',url('index'));
}else {
return $this->error('合并分类失败！');
}
}
public function status() {
$id = $this->getArrayParam('id');
$status = input('status','0','trim,intval');
if (!$id) {
return $this->error("非法操作！");
}
$map['id'] = array('IN',$id);
$result    = db('Category')->where($map)->setField('status',$status);
if ($result) {
return $this->success("设置成功！");
}else {
return $this->error("设置失败！");
}
}
public function documentList(){
$keyword = input('keyword');
$category = input('category');
$sqlStr = "";
if($keyword){
$sqlStr = 'title like "%'.$keyword.'%"';
}
if($category){
if($sqlStr){
$sqlStr .= 'or category_id = "'.$category.'"';
}else{
$sqlStr = 'category_id = "'.$category.'"';
}
}
if ($sqlStr) {
$list = Db::name('content')->field('id,uid,author,title,is_top,create_time,update_time,status')
->where($sqlStr)
->where("type",1)
->order('id DESC')
->paginate(10,false,array('query'=>$this->param));
}else {
$list = Db::name('content')->field('id,uid,author,title,is_top,create_time,update_time,status')
->where("type",1)
->order('id DESC')
->paginate(10,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$Mlist = Db::name('member')->field('username')->where('uid',$v['uid'])->find();
if($Mlist['username']){
$list['data'][$k]['userName'] = $Mlist['username'];
}else{
$list['data'][$k]['userName'] = '';
}
$list['data'][$k]["createTime"] = date("Y-m-d H:i:s",$v["create_time"]);
$list['data'][$k]["updateTime"] = date("Y-m-d H:i:s",$v["update_time"]);
$list['data'][$k]["content_url"] = config('res_url')."/wap/vote/content/contentId/".$v['id'];
}
$category = "";
$cate_list = parse_field_bind('category',$category,0);
$this->assign('cate_list',$cate_list);
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function addDocument(){
if(IS_POST){
$data=array();
$data['uid'] = $_SESSION["user"]["user_auth"]["uid"];
$data['author'] = input('author');
$data['title'] = input('title');
$data['category_id'] = input('category');
$data['description'] = htmlspecialchars_decode(input('description'));
$data['type'] = input('contentType');
$data['video_link'] = input('videoLink','','trim,strip_tags');
$data['position'] = input('position');
$data['external_link'] = input('link');
$data['cover_id'] = input('cover');
$data['display'] = input('display');
$data['deadline'] = strtotime(input('deadline'));
$data['view'] = input('view');
$data['praise'] = input('praise');
$data['comment'] = input('comment');
$data['level'] = input('level');
$data['is_top'] = 0;
$data['create_time'] = strtotime(input('create_time'));
$data['update_time'] = time();
$data['status'] = 0;
$data['keyword'] = htmlspecialchars_decode(input('keyword'));
$data['is_share'] = input('is_share');
$data['is_comment'] = input('is_comment');
$data['is_examine'] = input('is_examine');
$result = Db::name('content')->insertGetId($data);
if($result){
$contentdata=array();
$contentdata['doc_id'] = $result;
$contentdata['content'] = htmlspecialchars_decode(input('content'));
$contentdata['tags'] = input('Label');
$rescon = Db::name('content_detail')->insertGetId($contentdata);
$kdata=array();
$kdata['key'] = input('keyword');
$kdata['type'] = 'content';
$kdata['module_id'] = $result;
$kdata['sort'] = input('level');
$keyresult = Db::name('wx_keyword')->insertGetId($kdata);
if($rescon &&$keyresult){
if($data['type'] == 1){
$this->redirect("User/Content/documentList");
}else{
$this->redirect("User/Content/videoList");
}
}else{
$this->error("添加内容或关键字失败。",Url("User/Content/addDocument",['id'=>$data['type']]));
}
}else{
$this->error("添加失败。",Url("User/Content/addDocument",['id'=>$data['type']]));
}
}else{
$category = "";
$cate_list = parse_field_bind('category',$category,0);
$this->assign('cate_list',$cate_list);
$picdata=array();
$this->assign('picdata',$picdata);
$imagesdata=array();
$this->assign('imgdata',$imagesdata);
$type = input('type');
$this->assign('type',$type);
$this->assign('create_time',date("Y-m-d H:i:s",time()));
$this->assign('current','添加');
return $this->fetch();
}
}
public function editDocument(){
if(IS_POST){
$data=array();
$data['author'] = input('author');
$data['title'] = input('title');
$data['category_id'] = input('category');
$data['description'] = htmlspecialchars_decode(input('description'));
$data['type'] = input('contentType');
$data['video_link'] = input('videoLink');
$data['position'] = input('position');
$data['external_link'] = input('link');
$data['cover_id'] = input('cover');
$data['display'] = input('display');
$data['deadline'] = strtotime(input('deadline'));
$data['view'] = input('view');
$data['praise'] = input('praise');
$data['comment'] = input('comment');
$data['level'] = input('level');
$data['create_time'] = strtotime(input('create_time'));
$data['update_time'] = time();
$data['keyword'] = htmlspecialchars_decode(input('keyword'));
$data['is_share'] = input('is_share');
$data['is_comment'] = input('is_comment');
$data['is_examine'] = input('is_examine');
$result = Db::name('content')->where('id',input('docId'))->update($data);
Db::name('content_detail')->where('doc_id',input('docId'))->delete();
$contentdata=array();
$contentdata['content'] = htmlspecialchars_decode(input('content'));
$contentdata['tags'] = input('Label');
$contentdata['doc_id'] = input('docId');
$rescon = Db::name('content_detail')->insertGetId($contentdata);
$kdata=array();
$kdata['key'] = input('keyword');
$kdata['type'] = 'content';
$kdata['module_id'] = input('docId');
$kdata['sort'] = input('level');
Db::name('wx_keyword')->where('module_id',input('docId'))->delete();
$keyinsert = Db::name('wx_keyword')->insertGetId($kdata);
if($result ||$rescon){
if($data['type'] == 1){
$this->redirect("User/Content/documentList");
}else{
$this->redirect("User/Content/videoList");
}
}else{
$this->error("编辑失败。",Url("User/Content/editDocument",['id'=>input('docId')]));
}
}else{
$category = "";
$cate_list = parse_field_bind('category',$category,0);
$this->assign('cate_list',$cate_list);
$id = input('id');
$doclist =  Db::name('content')->where('id',$id)->find();
$doclist["create_time"] = date("Y-m-d H:i:s",$doclist["create_time"]);
$doclist["deadline"] = date("Y-m-d H:i:s",$doclist["deadline"]);
$type = $doclist['type'];
$this->assign('type',$type);
$imglist = null;
$rescon = Db::name('content_detail')->where('doc_id',$id)->find();
$pic = Db::name('picture')->where('id',$doclist['cover_id'])->find();
$picdata=array();
if($pic){
$picdata['cover']=$doclist['cover_id'];
}
$this->assign('picdata',$picdata);
$this->assign('doclist',$doclist);
$this->assign('rescon',$rescon);
$this->assign('create_time',date("Y-m-d H:i:s",time()));
$this->assign('current','编辑');
return $this->fetch('adddocument');
}
}
public function settop($id,$is_top){
$data=array();
$data['is_top'] = $is_top;
$rescon = Db::name('content')->where('id',$id)->update($data);
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
public function setstatus($id,$status){
$data=array();
$data['status'] = $status;
$rescon = Db::name('content')->where('id',$id)->update($data);
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
public function del($id=''){
foreach ($id as $k=>$v){
$list = Db::name('content')->where('id',$v)->delete();
Db::name('content_detail')->where('doc_id',$v)->delete();
if(!$list){
break;
}
}
if(!$list){
echo "删除失败。";
}
}
public function videoList(){
$keyword = input('keyword');
$category = input('category');
$sqlStr = "";
if($keyword){
$sqlStr = 'title like "%'.$keyword.'%"';
}
if($category){
if($sqlStr){
$sqlStr .= 'or category_id = "'.$category.'"';
}else{
$sqlStr = 'category_id = "'.$category.'"';
}
}
if ($sqlStr) {
$list = Db::name('content')->field('id,uid,author,title,is_top,create_time,update_time,status')
->where($sqlStr)
->where("type",2)
->order('id DESC')
->paginate(10,false,array('query'=>$this->param));
}else {
$list = Db::name('content')->field('id,uid,author,title,is_top,create_time,update_time,status')
->where("type",2)
->order('id DESC')
->paginate(10,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$Mlist = Db::name('member')->field('username')->where('uid',$v['uid'])->find();
if($Mlist['username']){
$list['data'][$k]['userName'] = $Mlist['username'];
}else{
$list['data'][$k]['userName'] = '';
}
$list['data'][$k]["createTime"] = date("Y-m-d H:i:s",$v["create_time"]);
$list['data'][$k]["updateTime"] = date("Y-m-d H:i:s",$v["update_time"]);
}
$category = "";
$cate_list = parse_field_bind('category',$category,0);
$this->assign('cate_list',$cate_list);
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function comments(){
$id = input('id');
if (!$id) {
return $this->error("非法操作！");
}
$content = input('content');
$userName = input('userName');
$sqlStr = "";
if($content){
$sqlStr = 'content like "%'.$content.'%"';
}
if($userName){
if($sqlStr){
$sqlStr .= 'or username = "'.$userName.'"';
}else{
$sqlStr = 'username = "'.$userName.'"';
}
}
if ($sqlStr) {
$list = Db::name('content_comment')->field('comment_id,content_id,username,content,add_time,ip_address,is_show')
->where($sqlStr)
->where('content_id',$id)
->paginate(6,false,array('query'=>$this->param));
}else {
$list = Db::name('content_comment')->field('comment_id,content_id,username,content,add_time,ip_address,is_show')
->where('content_id',$id)
->paginate(6,false,array('query'=>$this->param));
}
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$clist = Db::name('content')->field('title')->where('id',$v['content_id'])->find();
if($clist['title']){
$list['data'][$k]['content_name'] = $clist['title'];
}else{
$list['data'][$k]['content_name'] = '';
}
$list['data'][$k]["play_time"] = date("Y-m-d H:i:s",$v["add_time"]);
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function replyComment(){
$id = input('id');
$model_id = input('model_id');
if(IS_POST){
$ctype=array();
$ctype['content_id'] = input('content_id');
$ctype['parent_id'] = input('parent_id');
$ctype['username'] = $_SESSION["user"]["user_auth"]["username"];
$ctype['user_id'] = $_SESSION["user"]["user_auth"]["uid"];
$ctype['content'] = htmlspecialchars_decode(input('content'));
$ctype['add_time'] = time();
$ctype['is_show'] = 1;
$rescom = Db::name('content_comment')->insertGetId($ctype);
if($rescom){
$this->redirect('User/Content/comments',['id'=>$ctype['content_id'],'model_id'=>$model_id]);
}else{
$this->error("回复失败。",Url("User/Content/replyComment",['id'=>$ctype['content_id'],'model_id'=>$model_id]));
}
}else{
$list = Db::name('content_comment')->field('comment_id,content_id,user_id,content,add_time')->where('comment_id',$id)->find();
$memlist = Db::name('member')->field('logo')->where('uid',$list['user_id'])->find();
$piclist = Db::name('picture')->field('path')->where('id',$memlist['logo'])->find();
if($piclist['path']){
$list['user_logo'] = $piclist['path'];
}else{
$list['user_logo'] = "/application/user/static/images/innin.png";
}
$list["play_time"] = date("Y-m-d H:i:s",$list["add_time"]);
$rpllist = Db::name('content_comment')->field('comment_id,content_id,user_id,content,add_time')->where('parent_id',$id)->select();
foreach ($rpllist as $k=>$v){
$rplmemlist = Db::name('member')->field('logo')->where('uid',$v['user_id'])->find();
$rplpiclist = Db::name('picture')->field('path')->where('id',$rplmemlist['logo'])->find();
if($rplpiclist['path']){
$rpllist[$k]['user_logo'] = $rplpiclist['path'];
}else{
$rpllist[$k]['user_logo'] = "/application/user/static/images/innin.png";
}
$rpllist[$k]["play_time"] = date("Y-m-d H:i:s",$v["add_time"]);
}
$this->assign('rpllist',$rpllist);
$this->assign('list',$list);
return $this->fetch();
}
}
public function delComment($comment_id = ""){
foreach ($comment_id as $k=>$v){
$list = Db::name('content_comment')->where('comment_id',$v)->delete();
if(!$list){
break;
}
}
if(!$list){
echo "删除失败。";
}
}
public function changeComment(){
$comment_id = $_POST['comment_id'];
$data=array();
$data['is_show'] = input('status');
foreach ($comment_id as $k=>$v){
$list = Db::name('content_comment')->where('comment_id',$v)->update($data);
if(!$list){
break;
}
}
if(!$list){
echo "修改失败。";
}
}
}