<?php 
namespace app\user\controller;
use app\common\controller\User;
class Upload extends User {
public function delete(){
$controller = controller('common/Upload');
$action = ACTION_NAME;
return $controller->$action();
}
public function upload(){
$controller = controller('common/Upload');
$action = ACTION_NAME;
return $controller->$action();
}
public function editor(){
$controller = controller('common/Upload');
$action = ACTION_NAME;
return $controller->$action();
}
}