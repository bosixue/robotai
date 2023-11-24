<?php 

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
class Document extends User{
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
$data['video_link'] = input('videoLink');
$data['position'] = input('position');
$data['external_link'] = input('link');
$data['cover_id'] = input('cover');
$data['display'] = input('display');
$data['deadline'] = strtotime(input('deadline'));
$data['view'] = input('view');
$data['comment'] = input('comment');
$data['level'] = input('level');
$data['is_top'] = 0;
$data['create_time'] = strtotime(input('create_time'));
$data['update_time'] = time();
$data['status'] = 0;
$result = Db::name('content')->insertGetId($data);
if($result){
$contentdata=array();
$contentdata['doc_id'] = $result;
$contentdata['content'] = htmlspecialchars_decode(input('content'));
$contentdata['tags'] = input('Label');
$rescon = Db::name('content_detail')->insertGetId($contentdata);
if($rescon){
$this->redirect("User/Document/documentList");
}else{
$this->error("添加内容失败。",Url("User/Document/addDocument"));
}
}else{
$this->error("添加失败。",Url("User/Document/addDocument"));
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
$this->assign('current','添加');
return $this->fetch();
}
}
public function editDocument(){
if(IS_POST){
$data=array();
$data['uid'] = $_SESSION["user"]["user_auth"]["uid"];
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
$data['comment'] = input('comment');
$data['level'] = input('level');
$data['is_top'] = 0;
$data['create_time'] = strtotime(input('create_time'));
$data['update_time'] = time();
$data['status'] = 0;
$result = Db::name('content')->where('id',input('docId'))->update($data);
if($result){
$contentdata=array();
$contentdata['content'] = htmlspecialchars_decode(input('content'));
$contentdata['tags'] = input('Label');
$rescon = Db::name('content_detail')->where('doc_id',input('docId'))->update($contentdata);
if($rescon){
$this->redirect("User/Document/documentList");
}else{
$this->error("编辑内容失败。",Url("User/Document/editDocument",['id'=>input('docId')]));
}
}else{
$this->error("编辑失败。",Url("User/Document/editDocument",['id'=>input('docId')]));
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
$imglist = null;
$rescon = Db::name('content_detail')->where('doc_id',$id)->find();
$picdata=array();
$picdata['cover']=$doclist['cover_id'];
$this->assign('picdata',$picdata);
$type = input('type');
$this->assign('type',$type);
$this->assign('doclist',$doclist);
$this->assign('rescon',$rescon);
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
}