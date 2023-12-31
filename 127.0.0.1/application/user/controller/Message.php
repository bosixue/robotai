<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use Qiniu\json_decode;
class Message extends User{
public function index(){
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
public function my() {
return $this->fetch();
}
public function ajax_my(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$status = input('statusz','','trim,strip_tags');
$where = array();
$where['member_id'] = array('eq',$uid) ;
if($status != ''){
$where['status'] = array('eq',$status);
}
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$list = Db::name('message')->where($where)->page($page,$limit)->order('status asc ,create_time desc')->select();
foreach($list as $key =>$vo){
$list[$key]['status'] = $vo['status'] == 1 ?'已读':'未读';
$list[$key]['create_time'] = date("Y-m-d H:i",$vo['create_time']);
}
$count = Db::name('message')->where($where)->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function addMsg(){
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
$data['create_time'] = time();
$data['status'] = 0;
$sendall = input('sendall','','trim,strip_tags');
if ($sendall){
$data['keyword'] = '';
}else{
$keywordArr = input('send_obj/a',[],'trim,strip_tags');
$data['keyword'] = implode(',',$keywordArr);
}
$data['is_share'] = input('is_share');
$data['is_comment'] = input('is_comment');
$data['is_examine'] = input('is_examine');
$result = Db::name('content')->insertGetId($data);
if($result){
$send = input('send','0','trim,strip_tags');
$contentdata=array();
$contentdata['doc_id'] = $result;
$contentdata['content'] = htmlspecialchars_decode(input('content'));
$contentdata['tags'] = input('Label');
$rescon = Db::name('content_detail')->insertGetId($contentdata);
if($send){
$this->sendMsg($result);
}
if($rescon){
if($data['type'] == 1){
$this->redirect("User/Message/index");
}else{
$this->redirect("User/Message/index");
}
}else{
$this->error("添加内容失败。",Url("User/Message/addMsg",['id'=>$data['type']]));
}
}else{
$this->error("添加失败。",Url("User/Message/addMsg",['id'=>$data['type']]));
}
}else{
$where = array();
$where['status'] = 1;
$where['super'] = 0;
$adminlist = Db::name('admin')						
->field('id,username')
->where($where)
->order('id asc')
->select();
$this->assign('adminlist',$adminlist);
$type = input('type');
$this->assign('type',$type);
$this->assign('create_time',date("Y-m-d H:i:s",time()));
$this->assign('current','添加');
return $this->fetch('add');
}
}
public function editMsg(){
if(IS_POST){
$id = input('docId');
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
$data['create_time'] = time();
$sendall = input('sendall','','trim,strip_tags');
if ($sendall){
$data['keyword'] = '';
}
else{
$keywordArr = input('send_obj/a','','trim,strip_tags');
$data['keyword'] = implode(',',$keywordArr);
}
$data['is_share'] = input('is_share');
$data['is_comment'] = input('is_comment');
$data['is_examine'] = input('is_examine');
$result = Db::name('content')->where('id',$id)->update($data);
Db::name('content_detail')->where('doc_id',$id)->delete();
$contentdata=array();
$contentdata['content'] = htmlspecialchars_decode(input('content'));
$contentdata['tags'] = input('Label');
$contentdata['doc_id'] = input('docId');
$rescon = Db::name('content_detail')->insertGetId($contentdata);
$send = input('send','0','trim,strip_tags');
if ($send){
$this->sendMsg($id);
}
if($result){
$this->redirect("User/Message/index");
}else{
$this->error("编辑失败。",Url("User/Message/editMsg",['id'=>input('docId')]));
}
}else{
$id = input('id');
$doclist =  Db::name('content')->where('id',$id)->find();
$doclist["create_time"] = date("Y-m-d H:i:s",$doclist["create_time"]);
$doclist["deadline"] = date("Y-m-d H:i:s",$doclist["deadline"]);
$doclist['sendall'] = $doclist['keyword']?0:1;
$doclist["keyword"] = explode(',',$doclist['keyword']);
$type = $doclist['type'];
$this->assign('type',$type);
$imglist = null;
$rescon = Db::name('content_detail')->where('doc_id',$id)->find();
$where = array();
$where['status'] = 1;
$where['super'] = 0;
$adminlist = Db::name('admin')						
->field('id,username')
->where($where)
->order('id asc')
->select();
$this->assign('adminlist',$adminlist);
$this->assign('doclist',$doclist);
$this->assign('rescon',$rescon);
$this->assign('create_time',date("Y-m-d H:i:s",time()));
$this->assign('current','编辑');
return $this->fetch('add');
}
}
public function sendMsg($id){
if (!$id){
$id = input('id/d','','trim,strip_tags');
}
$doc =  Db::name('content')->field('id,title,keyword')->where('id',$id)->find();
if($doc){
$docDetail =  Db::name('content_detail')->field('doc_id,content')->where('doc_id',$id)->find();
$where = array();
$where['status'] = 1;
$where['super'] = 0;
if($doc['keyword']){
$where['id'] = ['in',$doc['keyword']];
}
$memberList =  Db::name('admin')->field('id')->where($where)->select();
if($memberList){
$datas = array();
foreach($memberList as $item){
$data = array();
$data['title'] = $doc['title'];
$data['content'] = $docDetail['content'];
$data['member_id'] = $item['id'];
$data['create_time'] = time();
$data['status'] = 0;
array_push($datas,$data);
}
if($datas){
$result = Db::name('message')->insertAll($datas);
$data = array();
$data['update_time'] = time();
$data['status'] = 1;
$result = Db::name('content')->where('id',$id)->update($data);
return returnAjax(0,'发送成功');
}
}
}
return returnAjax(1,'发送失败');
}
public function getMessageById(){
$id = input('id/d','','trim,strip_tags');
$message = Db::name('message')->field('id, title,content,create_time')->where('id',$id)->find();
$message['create_time'] = date('Y-m-d H:i:s',$message['create_time']);
$message['content'] = htmlspecialchars_decode($message['content']);
if ($message){
Db::name('message')->where('id',$id)->update(array('status'=>1));
return returnAjax(0,'success',$message);
}
else{
return returnAjax(1,'获取数据失败');
}
}
public function del(){
$idArr = input('id/a','','trim,strip_tags');
$ids = implode(',',$idArr);
$where['id'] = ['in',$ids];
$ret = Db::name('content')->where($where)->delete();
$where = array();
$where['doc_id'] = ['in',$ids];
$ret =  Db::name('content_detail')->where($where)->delete();
return returnAjax(0,'删除成功');
}
public function del_message(){
$type = input('type','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['member_id'] = array('eq',$uid);
if($type == 1){
$res = Db::name('message')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}else if($type == 0){
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
$res = Db::name('message')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}
}
}
