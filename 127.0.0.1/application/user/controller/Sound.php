<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use think\Request;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\LinesData;
use app\common\controller\RobotDistribution;
class Sound extends User{
public function upload(){
$file=Request::instance()->file('update-audio-file');
$size = $file->getInfo()['size'];
if($size>10485760){
return returnAjax(2,'音频不能超过10M');
}
$info=$file->move('uploads/audio');
if($info &&$info->getPathname()){
$data=[
'size'=>$size,
'path_name'=>$info->getPathname()
];
return returnAjax(1,'success',$data);
}
return returnAjax(0,'upload error');
}
}
