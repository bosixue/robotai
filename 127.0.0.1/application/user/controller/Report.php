<?php // Copyright(C) 2021, All rights reserved.ts reserved.

namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
class Report extends User{
public function _initialize() {
parent::_initialize();
$request = request();
$action = $request->action();
}
public function index()
{
$where = array();
$startDate = input('startDate','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
if($startDate &&$endTime){
$where['last_dial_time'] = ["between time",[$startDate,$endTime]];
}
$list = Db::name('member')
->field('uid,FROM_UNIXTIME(last_dial_time, "%Y-%m-%d") AS last_dial_time,duration,task,sum(duration) AS duration,count(uid) AS dialing,COUNT(status=2) as connection')
->where($where)
->order('uid desc')
->group('last_dial_time')
->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
}