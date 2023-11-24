<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use Overtrue\Pinyin\Pinyin;
use Qiniu\json_decode;
use think\Config;
use think\Cookie;
use think\request;
use PHPExcel_IOFactory;
use PHPExcel;
use ZipArchive;
use app\common\controller\AdminData;
use app\common\controller\Log;
class Learning extends User{
function getFilesize_lj($bytes,$precision = 2) {
$units = array('B','KB','MB','GB','TB');
$bytes = max($bytes,0);
$pow = floor(($bytes ?log($bytes) : 0) / log(1024));
$pow = min($pow,count($units) -1);
$bytes /= pow(1024,$pow);
return round($bytes,$precision) .' '.$units[$pow];
}
public function learning_chuli(){
$pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
$keyword = input('keyword','','trim,strip_tags');
$data = array();
$data['name'] = input('name','','trim,strip_tags');
$label = input('label','','trim,strip_tags');
$scenariosId = input('sceneId','','trim,strip_tags');
$knowledge_type = input('knowledge_type','','trim,strip_tags');
$Othersettings_know = input('Othersettings_know','','trim,strip_tags');
if($knowledge_type != ""&&$knowledge_type != 'null'){
$data['type'] = $knowledge_type;
}else{
return returnAjax(1,'类型不能为空');
}
$data['break']=$Othersettings_know;
$data['keyword'] = str_replace("，",",",$keyword);
$data['keyword'] = explode(',',$data['keyword']);
foreach($data['keyword'] as $key=>$value){
$data['keyword'][$key] = trim($value);
}
$data['keyword'] = implode(',',$data['keyword']);
if ($data['keyword']){
$cnKeyword = str_replace('|','/',$data['keyword']);
$py = $pinyin->sentence($cnKeyword);
$py = str_replace(') ',' )',$py);
$py = str_replace('| ',' |',$py);
$py = str_replace('( ','(',$py);
$data['keyword_py'] = $py;
}
$id = input('post.id','','trim,strip_tags');
$data['action'] = input('action','','trim,strip_tags');
$data['action_id'] = input('actionId','','trim,strip_tags');
$data['intention'] = input('flowNodeLevel','','trim,strip_tags');
$data['create_time'] = time();
$data['update_time'] = time();
$data['scenarios_id'] = $scenariosId;
$data['label'] = $label;
$pausetime = input('pausetime','','trim,strip_tags');
if($pausetime == ""){
$pausetime = 3000;
}
$data['pause_time'] = $pausetime;
$tplId = input('tplId','','trim,strip_tags');
if($tplId != ""){
$data['sms_template_id'] = $tplId;
}
$bridge = input('groupId','','trim,strip_tags');
if($bridge != ""){
$data['bridge'] = $bridge;
}
$delArr = input('delArr/a');
if(is_array($delArr) &&count($delArr)){
foreach($delArr as $key =>$value){
$timez = [];
Db::name('tel_corpus')->where('id',$value)->update(['src_id'=>0]);
}
}
try{
$knowledgeId = Db::name('tel_knowledge')->insertGetId($data);
if($knowledgeId <= 0){
return returnAjax(1,'添加失败');
}
$content = input('content','','trim,strip_tags');
$content = json_decode($content,TRUE);
$filename = $_FILES;
if (is_array($content) ||is_array($filename)) {
foreach ($content as $key =>$value) {
$tcpus = array();
$tcpus['content'] = $value["con"];
$tcpus['src_id'] = $knowledgeId;
$tcpus['src_type'] = 1;
$tcpus['scenarios_id'] = input('sceneId','','trim,strip_tags');
if(isset($filename['filesname_'.$key]) === true &&is_array($filename['filesname_'.$key]) === true){
$random = rand_string(5).time();
$new_src = 'uploads/audio/'.date("Ymd").'/'.$random.'.wav';
if(is_dir('uploads/audio/'.date("Ymd")) == false){
mkdir('uploads/audio/'.date("Ymd"),0777,true);
}
$file_size = $this->getFilesize_lj($filename['filesname_'.$key]['size']);
$tcpus['file_size'] = $file_size;
$tcpus['source'] = 0;
$info = move_uploaded_file($filename['filesname_'.$key]['tmp_name'],$new_src);
if ($info) {
$tcpus['audio'] = '/'.$new_src;
}else {
return returnAjax(0,'上传失败',$file->getError());
}
}else{
$hecheng = Db::name('tel_corpus')->where(['id'=>$value["voice_idr"]])->find();
$tcpus['file_size'] = $hecheng['file_size'];
$tcpus['source'] = 1;
$tcpus['audio']=$hecheng['audio'];
Db::name('tel_corpus')->where(['id'=>$value["voice_idr"]])->delete();
}
Db::name('tel_corpus')->insertGetId($tcpus);
}
}
$res = Db::name('tel_learning')->where(['id'=>$id])->update(['status'=>1]);
if(!empty($res)){
return returnAjax(0,'处理成功');
}else{
return returnAjax(1,'处理失败');
}
}catch(\Exception $e){
return returnAjax(1,$e->getMessage());
}
}
}
