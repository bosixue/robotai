<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use \think\Config;
use Qiniu\json_decode;
class Exportmanagement extends User{
public function _initialize()
{
parent::_initialize();
}
public function export_record(){
return  $this->fetch();
}
}