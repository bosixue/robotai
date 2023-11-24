<?php
namespace app\common\controller;

use think\Db;

//日志
use app\common\controller\Log;


//线路数据处理类
class MemberData extends Base{

	/**
	 * 查询指定任务已接通的次数
	 *
	 * @param int $task_id
	 * @return int
	*/
	public function get_connect_count($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$count = Db::name('member')
							->where([
								'task'	=>	$task_id,
								'status'	=>	2
							])
							->count('uid');
		return $count;
	}

	/**
	 * 获取拨出的数量
	 *
	 * @param int $task_id
	 * @return int
	*/
	public function get_call_count($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$count = Db::name('member')
							->where([
								'task'	=>	['=', $task_id],
								'status'	=>	['>=', 2]
							])
							->count('uid');
		return $count;
	}

	/**
	 * 查询拒接的数量
	 *
	 * @param int $task_id
	 * @return int
	*/
	public function get_not_connect_count($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$count = Db::name('member')
							->where([
								'task'	=>	$task_id,
								'status'	=>	3
							])
							->count('uid');
		return $count;
	}

	/**
	 * 获取等待的数量
	 *
	 * @param int $task_id
	 * @return int
	*/
	public function get_wait_count($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$count = Db::name('member')
							->where([
								'task'	=>	$task_id,
								'status'	=>	['<=', 1]
							])
							->count('uid');
		return $count;
	}

	/**
	 * 获取总数量
	 *
	 * @param int $task_id
	 * @return int
	*/
	public function get_count($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$count = Db::name('member')
							->where([
								'task'	=>	$task_id,
							])
							->count();
		return $count;
	}

	/**
	 * 获取通话时长
	 *
	 * @param int $task_id
	 * @return int
	*/
	public function get_connect_duration($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$duration = Db::name('member')
								->where([
									'task'	=>	$task_id
								])
								->sum('duration');
		return $duration;
	}



	/**
	 * 获取最后拨打时间

	 * @param int $task_id
	 * @return int
	*/
	public function get_last_dial_time($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$last_dial_time = Db::name('member')
										->where([
											'task'	=>	$task_id,
										])
										->order('last_dial_time desc')
										->value('last_dial_time');
		return $last_dial_time;
	}

	/**
	 * 获取任务中客户说话的次数
	 *
	 *
	 * @param int $task_id
	 * @return int
	*/
	public function get_total_speak_count($task_id)
	{
		if(empty($task_id)){
			return false;
		}
		$count = Db::name('member')
							->where('task', $task_id)
							->sum('call_times');
		return $count;
	}


	/**
	 * 获取指定意向的数量
	 *
	 * @param int $task_id
	 * @param int $level
	 * @return int
	*/
	public function get_level_count($task_id, $level = 6)
	{
		if(empty($task_id) || empty($level)){
			return false;
		}
		$count = Db::name('member')
							->where([
								'task'	=>	$task_id,
								'level'	=>	$level
							])
							->count();
		return $count;
	}
	/**
	 * 获取各种通话时长的数量
	 *
	 * @param int $task_id
	 * @param int $type
	 * @return int
	 * $type=1  1-9s  $type=2 10-17s $type=3 18-39s $type=4 >40s
	*/
	public function get_duration_count($task_id, $type)
	{
		$where = [];
		$count = Db::name('member');
		$count = $count->where('task', $task_id);
		if($type === 1){
			$count = $count->where('duration', '>=', 1);
			$count = $count->where('duration', '<=', 9);
		}elseif($type === 2){
			$count = $count->where('duration', '>=', 10);
			$count = $count->where('duration', '<=', 17);
		}elseif($type === 3){
			$count = $count->where('duration', '>=', 18);
			$count = $count->where('duration', '<=', 39);
		}else{
			$count = $count->where('duration', '>=', 40);
		}
		$count = $count->count();
		return $count;
	}
	/**
	 * 获取各种通话的轮次
	 *
	 * @param int $task_id
	 * @param int $type：0为全部 1为1-2次 2为3-4次  3为5-6次 4为7-10次 5为10次以上
	 * @return int
	*/
	public  function get_call_times($task_id, $type){
		if(empty($task_id) || empty($type)){
			return 0;
		}
		$where = [];
		$count = Db::name('member');
		$count = $count->where('task', $task_id);
		if($type === 1){
			$count = $count->where('call_times', '>=', 1);
			$count = $count->where('call_times', '<=', 2);
		}elseif($type === 2){
			$count = $count->where('call_times', '>=', 3);
			$count = $count->where('call_times', '<=', 4);
		}elseif($type === 3){
			$count = $count->where('call_times', '>=', 5);
			$count = $count->where('call_times', '<=',6);
		}elseif($type === 4){
			$count = $count->where('call_times', '>=', 7);
			$count = $count->where('call_times', '<=',10);
		}elseif($type === 5){
			$count = $count->where('call_times', '>', 10);
		}
		//不写$type 得到总轮次
		$count = $count->count();
		return $count;
	}
	//得到平均通话次数 读取该task_id下  状态为2（status=2为已接通）的平均数字
	//$task_id 任务id
	public function call_average_count($task_id){
		$where = [];
		$where['task']=$task_id;
		$where['status']=2;
		$count = Db::name('member')->where($where)->avg('call_times');
		return $count;
	}
	//得到平均通话时长
	public function get_avg_duration($task_id){
		$where = [];
		$where['task']=$task_id;
		$where['status']=2;
		$count = Db::name('member')->where($where)->avg('duration');
		return $count;
	}
	
	/**
	 * 获取各种对话次数的数量
	 * 
	 * @param int $task_id
	 * @param int $type
	 * @return int
	*/
	public function get_speak_count($task_id, $type)
	{
		if(empty($task_id) || empty($type)){
			return 0;
		}
		$where = [];
		$count = Db::name('member');
		$count = $count->where('task', $task_id);
		if($type === 1){
			// $count = $count->where('status', 2);
			$count = $count->where('call_times', '>=', 1);
			$count = $count->where('call_times', '<=', 2);
		}elseif($type === 2){
			$count = $count->where('call_times', '>=', 3);
			$count = $count->where('call_times', '<=', 4);
		}elseif($type === 3){
			$count = $count->where('call_times', '>=', 5);
			$count = $count->where('call_times', '<=', 6);
		}elseif($type === 4){
			$count = $count->where('call_times', '>=', 7);
			$count = $count->where('call_times', '<=', 10);
		}else{
			$count = $count->where('call_times', '>', 10);
		}
		$count = $count->count('uid');
		return $count;
	}
}
