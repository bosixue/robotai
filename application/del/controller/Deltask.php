<?php
namespace app\del\controller;
use app\common\controller\User;
use think\Db;
use think\Controller;
use think\Session;
use PHPExcel_IOFactory;
use PHPExcel;
use PHPExcel_Style_NumberFormat;
use Qiniu\Storage\UploadManager;
use Qiniu\Auth;

class Deltask extends Controller{

  //给客服人员用来删除任务的 控制器 和方法
	public function index(){
    return $this->fetch();
	}
	//删除任务方法
	public  function del_task(){
	  $task_id = input('post.task_id','','trim,strip_tags');
	  $task_name = input('post.task_name','','trim,strip_tags');
	  if(empty($task_id) || empty($task_name) ){
	     echo "<script>alert('任务id或者任务名字不可以为空');history.back(-1);</script>";
	     return '';
	  }
	  $num = Db::name('tel_config')->where(['task_id'=>$task_id,'task_name'=>$task_name])->count('*');
	  if($num<=0){
	     echo "<script>alert('任务id或任务名不存在,或者填写不正确');history.back(-1);</script>";
	     return '';
	  }

	  $fs_num = Db::name('tel_config')->where(['task_id'=>$task_id,'task_name'=>$task_name])->value('fs_num');
	  //先更改 web 表状态
	  $res = Db::name('tel_config')->where(['task_id'=>$task_id,'task_name'=>$task_name])->update(['status'=>-1]);
	  if($res){
		  //然后更改fs表状态
		  $connect = Db::connect('db_configs.fs'.$fs_num);
		  $rac = $connect->table('autodialer_task')->where(['uuid'=>$task_id])->update(['start'=>-1]);
		  echo "<script>alert('任务删除成功');history.back(-1);</script>";
          return '';
	  }else{
	      echo "<script>alert('任务删除失败');history.back(-1);</script>";
  	      return '';
	  }
	}

	
	

}