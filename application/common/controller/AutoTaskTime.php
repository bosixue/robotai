<?php
namespace app\common\controller;

use think\Db;
use app\common\controller\Log;

class AutoTaskTime extends Base{
	public $tabel_name = 'auto_task_time';
	/**
	 * adddate()添加任务工作时间
	 * 
	 * @param int $member_id 用户ID
	 * @param int $task_id 任务ID
	 * @param array $start_date 开始日期
	 * @param array $end_date 结束日期
	 * @return bool
	*/
	public function insert($member_id, $task_id, $start_time, $end_time){
		//删除原先的日期
		$delete_result = $this->delete($member_id, $task_id);
		//添加新的日期
		$datas = [];
		foreach($start_time as $key=>$value){
			$datas[$key]['member_id'] = $member_id;
			$datas[$key]['task_id'] = $task_id;
			$datas[$key]['start_time'] = $value;
			$datas[$key]['end_time'] = $end_time[$key];
		}
		$result = Db::name($this->tabel_name)
							->insertAll($datas);
		\think\Log::record('添加任务工作时间' . json_encode($datas));
		if(!empty($result)){
			return true;
		}
		return false;
	}
	
	/**
	 * 删除任务工作时间
	 * 
	 * @param int $member_id
	 * @param int $task_id
	 * @return bool
	*/
	public function delete($member_id, $task_id)
	{
		if(empty($member_id) || empty($task_id)){
			return false;
		}
		$result = Db::name($this->tabel_name)
							->where([
								'member_id'	=> $member_id,
								'task_id' => $task_id
							])
							->delete();
		if(!empty($result)){
			return true;
		}
		return false;
	}
}
