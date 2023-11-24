<?php 
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use think\Request;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\LinesData;
use app\common\controller\RobotDistribution;
class Image extends User{
public function upload(){
$file=Request::instance()->file('image');
$info=$file->move('public/images/qrcode');
if($info &&$info->getPathname()){
return returnAjax(1,'success',$info->getPathname());
}
return returnAjax(0,'upload error');
}
}