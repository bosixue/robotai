<?php
namespace app\common\controller;

use think\Db;
use app\common\controller\Log;

class AutoTaskDate extends Base{
	
	public $tabel_name = 'auto_task_date';
	/**
	 * adddate()添加任务工作日期
	 * 
	 * @param int $member_id 用户ID
	 * @param int $task_id 任务ID
	 * @param array $start_date 开始日期
	 * @param array $end_date 结束日期
	 * @return bool
	*/
	public function insert($member_id, $task_id, $start_date, $end_date){
		//删除原先的日期
		$delete_result = $this->delete($member_id, $task_id);
		//添加新的日期
		$datas = [];
		foreach($start_date as $key=>$value){
			$datas[$key]['member_id'] = $member_id;
			$datas[$key]['task_id'] = $task_id;
			$datas[$key]['start_date'] = $value;
			$datas[$key]['end_date'] = $end_date[$key];
		}
		$result = Db::name($this->tabel_name)
							->insertAll($datas);
		\think\Log::record('添加任务工作日期' . json_encode($datas));
		if(!empty($result)){
			return true;
		}
		return false;
	}
	
	/**
	 * 删除任务工作日期
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

