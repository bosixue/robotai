<?php
// +----------------------------------------------------------------------
// | RuiKeCMS [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.ruikesoft.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Wayne <wayne@ruikesoft.com> <http://www.ruikesoft.com>
// +----------------------------------------------------------------------

namespace app\common\controller;
use app\common\model\AuthRule;
use app\common\model\AuthGroup;
//日志处理类
use app\common\controller\Log;
//数据库
use think\Db;
//后台用户
use app\common\controller\AdminData;

class RobotDistribution extends Base{


	/**
	 * 获取分配的机器人数量
	 *
	 * @param int $id
	 * @return int
	*/
	public function get_robot_count($id)
	{
		if(empty($id)){
			return false;
		}
		$count = Db::name('robot_distribution')
							->where('id', $id)
							->value('number');
		return $count;
	}

	/**
	 * 给用户开通机器人
	 *
	 * @param int $pid 开通者用户ID
	 * @param int $member_id 用户ID
	 * @param int $number 机器人数量
	 * @param int $duration 时长(天数)
	 * @param int $note 备注
	 * @return bool
	*/
	// public function open_up($pid, $member_id, $number, $duration, $note = '')
	// {
	// 	if(empty($pid) || empty($member_id) || empty($duration) || empty($number)){
	// 		return false;
	// 	}
	// 	/**
	// 	 * 1.获取分配这批机器人，需要涉及到哪些机器人分配记录，获取到这些数据
	// 	*/
	// 	//获取所有有效的机器人分配记录
	// 	$robot_distribution = Db::name('robot_distribution')
	// 												->where([
	// 													'member_id'	=>	$pid,
	// 													'state'	=>	1,
	// 													'end_time'	=>	['>', time()]
	// 												])
	// 												->select();
	// 	Log::info(json_encode($robot_distribution));
	// 	//需要用到的机器人分配记录
	// 	$datas = [];
	// 	$i = 0;
	// 	$count = $number;
	// 	//获取开通者的角色
	// 	$AdminData = new AdminData();
	// 	$role_name = $AdminData->get_role_name($pid);
	// 	if($role_name !== '管理员'){
	// 		// while($count !== 0){
	// 		// 	if($robot_distribution[$i]['usable_number'] <= $count){
	// 		// 		$datas[$i] = $robot_distribution[$i];
	// 		// 		$datas[$i]['number'] = $datas[$i]['usable_number'];
	// 		// 		$count = $count - $robot_distribution[$i]['usable_number'];
	// 		// 	}else{
	// 		// 		$datas[$i] = $robot_distribution[$i];
	// 		// 		$datas[$i]['number'] = $count;
	// 		// 		$datas[$i]['usable_number'] = $count;
	// 		// 		$count = 0;
	// 		// 	}
	// 		// 	json_encode(json_encode($datas));
	// 		// 	$i++;
	// 		// }
	// 	}
	// 	//生成分配记录
	// 	//获取当前时间
	// 	$start_time = time();
	// 	//计算结束时间
	// 	$end_time = $start_time + ($duration * 24 * 3600);
	// 	$rd_id = Db::name('robot_distribution')
	// 						->insertGetId([
	// 							'pid'	=>	$pid,
	// 							'member_id'	=>	$member_id,
	// 							'number'	=>	$number,
	// 							// 'usable_number'	=>	$number,
	// 							'state'	=>	1,
	// 							'start_time'	=>	$start_time,
	// 							'end_time'	=>	$end_time,
	// 							'note'	=>	$note
	// 						]);
	// 	// if(!empty($rd_id)){
	// 	// 	$ids = [];
	// 	// 	foreach($datas as $key=>$value){
	// 	// 		if($value['number'] === 0){
	// 	// 			$result = Db::name('robot_recovery_rule')
	// 	// 								->insertGetId([
	// 	// 									'rd_id'	=>	$rd_id,
	// 	// 									'rd_pid_id'	=>	$value['id'],
	// 	// 									'count'	=>	$value['number']
	// 	// 								]);
	// 	// 			if(empty($result)){
	// 	// 				//删除分配记录和回收规则 - 效果类似回滚
	// 	// 				$del_rd_result = Db::name('robot_distribution')
	// 	// 													->where('id', $rd_id)
	// 	// 													->delete();
	// 	// 				if(empty($del_rd_result)){
	// 	// 					Log::info('分配记录删除失败的记录ID：' . $rd_id);
	// 	// 				}
	// 	// 				$result = Db::name('robot_recovery_rule')
	// 	// 									->where('id', 'in', $ids)
	// 	// 									->delete();
	// 	// 				if(empty($result)){
	// 	// 					Log::info('删除机器人回收规则失败');
	// 	// 					Log::info('ID为：');
	// 	// 					Log::info(json_encode($ids));
	// 	// 				}
	// 	// 				return false;
	// 	// 			}else{
	// 	// 				//收集成功写入的回收规则的ID
	// 	// 				$ids[] = $result;
	// 	// 			}
	// 	// 			$result = Db::name('robot_distribution')
	// 	// 								->where('id', $value['id'])
	// 	// 								->setDec('usable_number', $value['usable_number']);
	// 	// 			if(empty($result)){
	// 	// 				Log::info('扣除分配记录的可用机器人数量失败');
	// 	// 				Log::info(json_encode($value));
	// 	// 			}
	// 	// 		}
	// 	// 	}
	// 	// }else{
	// 	// 	//删除分配记录 - 效果类似回滚
	// 	// 	$del_rd_result = Db::name('robot_distribution')
	// 	// 										->where('id', $rd_id)
	// 	// 										->delete();
	// 	// 	if(empty($del_rd_result)){
	// 	// 		Log::info('分配记录删除失败的记录ID：' . $rd_id);
	// 	// 	}
	// 	// 	return false;
	// 	// }
	// 	/**
	// 	 * 如果以上正常走完 开始增加机器人数量和扣除机器人数量
	// 	*/
	// 	if($role_name != '管理员'){
	// 		//扣除开通者的机器人数量
	// 		$bool = $this->deduction_robot_number($pid, $number);
	// 	}
	// 	//增加指定用户的机器人数量
	// 	$bool = $this->increase_find_member_robot_number($member_id, $number);
	// 	return true;
	// }

	/**
	 * 给用户开通机器人 2.0
	 *
	 * @param int $pid 开通者用户ID
	 * @param int $member_id 用户ID
	 * @param int $number 机器人数量
	 * @param int $duration 时长(天数)
	 * @param int $note 备注
	 * @return bool
	*/
	public function open_up($pid, $member_id, $number, $duration, $note = '')
	{
		if(empty($pid) || empty($member_id) || empty($duration) || empty($number)){
			return false;
		}
		/**
		 * 1.获取分配这批机器人，需要涉及到哪些机器人分配记录，获取到这些数据
		*/
		//需要用到的机器人分配记录
		$datas = [];
		$i = 0;
		$count = $number;
		//获取开通者的角色
		$AdminData = new AdminData();
		$role_name = $AdminData->get_role_name($pid);
		if($role_name !== '管理员'){
			//获取所有有效的机器人分配记录
			$robot_distribution_record = Db::name('robot_distribution_record')
														->field('id')
														->where([
															'member_id'	=>	$pid,
															'state'	=>	1,
															'end_time'	=>	['>', time()]
														])
														->select();
			$ids = [];
			foreach($robot_distribution_record as $key=>$value){
				$ids[] = $value['id'];
			}
			$robot_distribution = Db::name('robot_distribution')
														->where('record_id', 'in', $ids)
														->select();
			while($count !== 0){
				if($robot_distribution[$i]['usable_count'] <= $count){
					$datas[$i] = $robot_distribution[$i];
					$datas[$i]['count'] = $datas[$i]['usable_count'];
					$count = $count - $robot_distribution[$i]['usable_count'];
				}else{
					$datas[$i] = $robot_distribution[$i];
					$datas[$i]['count'] = $count;
					$count = 0;
				}
				$i++;
			}
		}else{
			$datas[$i]['count'] = $count;
			$datas[$i]['id'] = 0;
			$datas[$i]['usable_count'] = $count;
			$datas[$i]['record_id'] = 0;
		}

		//生成分配记录
		//获取当前时间
		$start_time = time();
		//计算结束时间
		$end_time = $start_time + ($duration * 24 * 3600);
		$rd_id = Db::name('robot_distribution_record')
							->insertGetId([
								'pid'	=>	$pid,
								'member_id'	=>	$member_id,
								'count'	=>	$number,
								'usable_count'	=>	$number,
								'start_time'	=>	$start_time,
								'end_time'	=>	$end_time,
								'note'	=>	$note
							]);
		Log::info('记录ID：' . $rd_id);
		Log::info(json_encode($datas));
		if(!empty($rd_id)){
			$ids = [];
			foreach($datas as $key=>$value){
				if($value['count'] !== 0){
					$result = Db::name('robot_distribution')
										->insertGetId([
											'record_id'	=>	$rd_id,
											'source_id'	=>	$value['id'],
											'member_id'	=>	$member_id,
											'count'	=>	$value['count'],
											'usable_count'	=>	$value['count']
										]);
					Log::info($result);
					$result = Db::name('robot_distribution_record')
										->where('id', $value['record_id'])
										->setDec('usable_count', $value['count']);
					if(empty($result)){
						Log::info('扣除分配记录的可用机器人数量失败');
						Log::info(json_encode($value));
					}
					$result = Db::name('robot_distribution')
										->where('id', $value['id'])
										->setDec('usable_count', $value['count']);
					if(empty($result)){
						Log::info('扣除分配机器人数量失败@');
						Log::info(json_encode($value));
					}
				}
			}
		}else{
			//删除分配记录 - 效果类似回滚
			$del_rd_result = Db::name('robot_distribution')
												->where('id', $rd_id)
												->delete();
			if(empty($del_rd_result)){
				Log::info('分配记录删除失败的记录ID：' . $rd_id);
			}
			return false;
		}
		/**
		 * 如果以上正常走完 开始增加机器人数量和扣除机器人数量
		*/
		if($role_name != '管理员'){
			//扣除开通者的机器人数量
			$bool = $this->deduction_robot_number($pid, $number);
		}
		//增加指定用户的机器人数量
		$bool = $this->increase_find_member_robot_number($member_id, $number);
		return true;
	}

	/**
	 * 获取机器人分配记录
	 *
	 * @param int $id
	 * @return array
	*/
	public function get_robot_data($id)
	{
		if(empty($id)){
			return false;
		}
		$data = Db::name('robot_distribution_record')
						->field('id,member_id,count,note,pid')
						->where('id', $id)
						->find();
		$AdminData = new AdminData();
		$data['member_name'] = $AdminData->get_username($data['member_id']);
		return $data;
	}

	/**
	 * 回收机器人
	 *
	 * @param int $id
	 * @param int $count
	 * @return bool
	*/
	// public function recovery_robot($id, $count)
	// {
	// 	if(empty($id) || empty($count)){
	// 		return false;
	// 	}
	// 	//1.验证当前分配记录的可用机器人数量
	// 	$robot_distribution_record = Db::name('robot_distribution_record')
	// 															->where('id', $id)
	// 															->find();
	// 	//2.恢复分配记录的可用数量
	// 	$source_id = Db::name('robot_distribution')
	// 								->where('id', $id)
	// 								->value('source_id');
	// 	$record_id = Db::name('robot_distribution')
	// 								->where('id', $source_id)
	// 								->value('record_id');
	// 	$update_result = Db::name('robot_distribution_record')
	// 										->where('id', $record_id)
	// 										->setInc('usable_count', $count);
	// 	//机器人数量不足够
	// 	if($robot_distribution_record['usable_count'] < $count){
	// 		//更新机器人分配记录
	// 		//rk_robot_distribution_record 1.count 2.usable_count
	// 		$result = Db::name('robot_distribution_record')
	// 							->where('id', $id)
	// 							->dec('count', $count)
	// 							->dec('usable_count', $robot_distribution_record['usable_count'])
	// 							->update();
	// 		$count = $count - $robot_distribution_record['usable_count'];
	// 		if(empty($result)){
	// 			\think\Log::record('robot_distribution_record');
	// 		}
			
	// 		//rk_admin
	// 		//回收机器人数量(扣除用户机器人数量和可用机器人数量)
	// 		$result = Db::name('admin')
	// 							->where('id', $robot_distribution_record['member_id'])
	// 							->dec('robot_cnt', $count)
	// 							->dec('usable_robot_cnt', $robot_distribution_record['usable_count'])
	// 							->update();
	// 		if(empty($result)){
	// 			\think\Log::record('回收机器人数量(扣除用户机器人数量和可用机器人数量) - 失败');
	// 		}
	// 		//回收机器人数量(增加用户机器人数量)
	// 		$result = Db::name('admin')
	// 							->where('id', $robot_distribution_record['pid'])
	// 							->setInc('usable_robot_cnt', $count);
	// 		if(empty($result)){
	// 			\think\Log::record('回收机器人数量(增加用户机器人数量) - 失败');
	// 		}
			
	// 		$datas = Db::name('robot_distribution')
	// 							->where('record_id', $id)
	// 							->select();
	// 		Log::info(json_encode($datas));
	// 		//1.获取数量
	// 		while($count != 0 || $count != 0 && count($datas) != 0){
	// 			//获取下一层的数据
	// 			Log::info('进来了');
	// 			// $robot_data = Db::name('');
	// 			$record_id = [];
	// 			foreach($datas as $key=>$value){
	// 				Log::info(json_encode($value));
	// 				if($value['count'] != 0 && $count != 0){
	// 					if($count > $value['count']){
	// 						$count = $count - $value['count'];
	// 						$deduction = $value['count'];
	// 						Log::info(1);
	// 					}else{
	// 						$deduction = $count;
	// 						$count = 0;
	// 						Log::info(2);
	// 					}
	// 					if($deduction != 0){
	// 						//扣除机器人分配的记录的数量
	// 						$result = Db::name('robot_distribution')
	// 											->where('id', $value['id'])
	// 											->setDec('count', $deduction);
	// 						if(empty($result)){
	// 							Log::info('扣除失败');
	// 							Log::info(json_encode($value));
	// 						}
	// 						$result = Db::name('robot_distribution_record')
	// 											->where('id', $value['record_id'])
	// 											->dec('count', $deduction)
	// 											->dec('usable_count', $deduction)
	// 											->update();
	// 						if(empty($result)){
	// 							Log::info('扣除失败2');
	// 							Log::info(json_encode($value));
	// 						}
	// 						//扣除用户的机器人数量
	// 						$result = Db::name('admin')
	// 											->where('id', $value['member_id'])
	// 											->dec('robot_cnt', $deduction)
	// 											->dec('usable_robot_cnt', $deduction)
	// 											->update();
	// 						if(empty($result)){
	// 							Log::info('扣除失败3');
	// 							Log::info(json_encode($value));
	// 						}
	// 					}
	// 				}
	// 				$record_id[] = $value['id'];
	// 			}
	// 			$datas = Db::name('robot_distribution')
	// 								->where('source_id', 'in', $record_id)
	// 								->select();
	// 		}
	// 	}else{
	// 		//更新机器人分配记录
	// 		//rk_robot_distribution_record 1.count 2.usable_count
	// 		$result = Db::name('robot_distribution_record')
	// 							->where('id', $id)
	// 							->dec('count', $count)
	// 							->dec('usable_count', $count)
	// 							->update();
	// 		if(empty($result)){
	// 			\think\Log::record('robot_distribution_record');
	// 		}
	// 		//rk_robot_distribution
	// 		$result = Db::name('robot_distribution')
	// 							->where('record_id', $id)
	// 							->setDec('count', $count);
	// 		if(empty($result)){
	// 			\think\Log::record('robot_distribution');
	// 		}
	// 		//rk_admin
	// 		//回收机器人数量(扣除用户机器人数量和可用机器人数量)
	// 		$result = Db::name('admin')
	// 							->where('id', $robot_distribution_record['member_id'])
	// 							->dec('robot_cnt', $count)
	// 							->dec('usable_robot_cnt', $count)
	// 							->update();
	// 		if(empty($result)){
	// 			\think\Log::record('回收机器人数量(扣除用户机器人数量和可用机器人数量) - 失败');
	// 		}
	// 		//回收机器人数量(增加用户机器人数量)
	// 		$result = Db::name('admin')
	// 							->where('id', $robot_distribution_record['pid'])
	// 							->setInc('usable_robot_cnt', $count);
	// 		if(empty($result)){
	// 			\think\Log::record('回收机器人数量(增加用户机器人数量) - 失败');
	// 		}
	// 	}
	// 	return true;
	// }
	
	
	public function recovery_robot($id, $count)
	{
		if(empty($id) || empty($count)){
			return false;
		}
		$usable_count = $count;
		$source_data = Db::name('robot_distribution')
										->where('record_id', $id)
										->select();
		while($count != 0){
			$ids = [];
			foreach($source_data as $key=>$value){
				if($count != 0){
					if($count >= $value['usable_count']){
						$deduction = [
							'count'	=>	$value['count'],
							'usable_count'	=>	$value['usable_count'],
							'member_id'	=>	$value['member_id']
						];
						if($value['count'] > $count){
							$deduction['count'] = $count;
						}
						$count -= $value['usable_count'];
					}else{
						$deduction = [
							'count'	=>	$value['count'],
							'usable_count'	=>	$count,
							'member_id'	=>	$value['member_id']
						];
						if($value['count'] > $count){
							$deduction['count'] = $count;
						}
						$count = 0;
					}
					//扣除用户的机器人总数和可用数量
					$result = Db::name('admin')
										->where('id', $value['member_id'])
										->dec('robot_cnt', $deduction['count'])
										->dec('usable_robot_cnt', $deduction['usable_count'])
										->update();
					if(empty($result)){
						Log::info('扣除用户的机器人总数和可用数量-失败');
						Log::info(json_encode($deduction));
					}
					//扣除分配记录中的总数量和剩余数量
					$result = Db::name('robot_distribution_record')
										->where('id', $value['record_id'])
										->dec('count', $deduction['count'])
										->dec('usable_count', $deduction['usable_count'])
										->update();
					if(empty($result)){
						Log::info('扣除分配记录中的总数量和剩余数量-失败');
						Log::info(json_encode($deduction));
					}
					//扣除分配来源中的数量
					$result = Db::name('robot_distribution')
										->where('id', $value['id'])
										->setDec('usable_count', $deduction['usable_count']);
					if(empty($result)){
						Log::info('扣除分配来源中的数量-失败');
						Log::info(json_encode($deduction));
					}
					$ids[] = $value['id'];
				}
			}
			$source_data = Db::name('robot_distribution')
										->where('source_id', 'in', $ids)
										->select();
		}
		//获取开通者ID
		$pid = Db::name('robot_distribution_record')
						->where('id', $id)
						->value('pid');
		//获取开通者用户角色
		$role_name = Db::name('admin')
									->alias('a')
									->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
									->where('a.id', $pid)
									->value('ar.name');
		//如果是用户角色是管理员 不做以下处理
		if($role_name !== '管理员'){
			//释放开通者的可用机器人数量
			$result = Db::name('admin')
								->where('id', $pid)
								->setInc('usable_robot_cnt', $usable_count);
			if(empty($result)){
				Log::info('释放开通者的可用机器人数量');
				Log::info('用户ID:'.$pid);
				Log::info('数量:'.$usable_count);
			}
		}
		return true;
	}
	

	/**
	 * 获取机器人数量
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_usable_robot_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$number = Db::name('admin')
							->where('id', $member_id)
							->value('usable_robot_cnt');
		return $number;
	}



	/**
	 * 验证指定用户是否属于当前用户的子账户
	 *
	 * @param int $member_id
	 * @return bool
	*/
	public function verify_whether_belong_to_find_account($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$user_auth = session('user_auth');

		$pid = Db::name('admin')
						->where('id', $member_id)
						->value('pid');
		if($pid === $user_auth['uid']){
			return true;
		}
		return false;
	}

	/**
	 * 获取状态
	 *
	 * @param int $id
	 * @return 1|0
	*/
	public function get_state($id)
	{
		if(empty($id)){
			return false;
		}
		$state = Db::name('robot_distribution_record')
							->where('id', $id)
							->value('state');
		return $state;
	}

	/**
	 * 更新状态为关闭(state = 0)
	 *
	 * @param int $id
	 * @return bool
	*/
	public function update_state_close($id)
	{
		if(empty($id)){
			return false;
		}
		$result = Db::name('robot_distribution_record')
							->where('id', $id)
							->update([
								'state'	=>	0
							]);
		if(!empty($result)){
			return true;
		}
		return false;
	}

	/**
	 * 延长有效时长
	 *
	 * @param int $id
	 * @param int $duration 时长(天数)
	 * @return bool
	*/
	public function extend_duration($id, $duration)
	{
		if(empty($id) || empty($duration)){
			return false;
		}
		//天数转秒
		$duration = $duration * 24 * 3600;
		$result = Db::name('robot_distribution_record')
							->where('id', $id)
							->setInc('end_time', $duration);
		if(!empty($result)){
			return true;
		}
		return false;
	}

	/**
	 * 扣除当前用户机器人数量
	 *
	 * @param int $member_id 用户ID
	 * @param int $number 扣除数量
	 * @return bool
	*/
	public function deduction_robot_number($member_id, $number)
	{
		if(empty($member_id) || empty($number)){
			return false;
		}
		$result = Db::name('admin')
							->where('id', $member_id)
							->setDec('usable_robot_cnt', $number);
		if(!empty($result)){
			return true;
		}
		return false;
	}

	/**
	 * 扣除子用户机器人数量
	 *
	 * @param int $member_id 用户ID
	 * @param int $number 扣除数量
	 * @return bool
	*/
	public function deduction_find_member_robot_number($member_id, $number)
	{
		if(empty($member_id) || empty($number)){
			return false;
		}
		$result = Db::name('admin')
							->where('id', $member_id)
							->dec('usable_robot_cnt', $number)
							->dec('robot_cnt', $number)
							->update();
		if(!empty($result)){
			return true;
		}
		return false;
	}

	/**
	 * 增加当前用户机器人数量
	 * @param int $member_id 用户ID
	 * @param int $number 扣除数量
	 * @return bool
	*/
	public function increase_robot_number($member_id, $number)
	{
		if(empty($member_id) || empty($number)){
			return false;
		}
		$result = Db::name('admin')
							->where('id', $member_id)
							->setInc('usable_robot_cnt', $number);
		if(!empty($result)){
			return true;
		}
		return false;
	}
	/**
	 * 增加子用户机器人数量
	 * @param int $member_id 用户ID
	 * @param int $number 扣除数量
	 * @return bool
	*/
	public function increase_find_member_robot_number($member_id, $number)
	{
		if(empty($member_id) || empty($number)){
			return false;
		}
		$result = Db::name('admin')
							->where('id', $member_id)
							->inc('usable_robot_cnt', $number)
							->inc('robot_cnt', $number)
							->update();
		if(!empty($result)){
			return true;
		}
		return false;
	}

	/**
	 * 获取机器人分配记录Api接口
	 *
	 * @return Json
	*/
	public function get_robot_datas_api()
	{
		$user_auth = session('user_auth');
		$user_auth_sign = session('user_auth_sign');
		if(empty($user_auth_sign) || empty($user_auth)){
			return returnAjax(0, 'error', '未登陆');
		}
		$datas = $this->get_robot_datas($user_auth['uid']);
		return returnAjax(1, 'success', $datas);
	}

	/**
	 * 获取用户机器人分配记录
	 *
	 * @param int $member_id
	 * @param array $args 筛选参数
	 * @param int $page
	 * @param int $limit
	 * @return array
	*/
	public function get_robot_datas($member_id, $args = [], $page = 1, $limit = 10)
	{
		if(empty($member_id)){
			return false;
		}
		$datas = Db::name('robot_distribution_record')
							->alias('rd')
							->field('rd.*,ma.username as member_name,pa.username as p_name')
							->join('admin ma', 'ma.id = rd.member_id', 'LEFT')
							->join('admin pa', 'pa.id = rd.pid', 'LEFT')
							->where($args)
							->paginate($limit);
		$AdminData = new AdminData();
		$paging = $datas->render();
		$result = [];
		$result['data'] = $datas->all();
		// Log::info(json_encode($datas['data']));
		foreach($result['data'] as $key=>$value){
			//用户名称
			// $result['data'][$key]['member_name'] = $AdminData->get_username($value['member_id']);
			// //开通者用户名称
			// $result['data'][$key]['p_name'] = $AdminData->get_username($value['pid']);
			//使用状态
			$result['data'][$key]['state_name'] = time() < $value['end_time']?'正在使用':'已过期';
			//开始时间
			$result['data'][$key]['start_time'] = date('Y-m-d H:i:s', $value['start_time']);
			//结束时间
			$result['data'][$key]['end_time'] = date('Y-m-d H:i:s', $value['end_time']);
		}
		$result['paging'] = $paging;
		return $result;
	}

	/**
	 * 获取用户机器人的分配记录的分页
	 *
	 * @param int $member_id
	 * @param int $page
	 * @param int $limit
	 * @return html
	*/
	public function get_robot_paging($member_id, $page = 1, $limit = 10)
	{
		if(empty($member_id)){
			return false;
		}
		$paging = Db::name('robot_distribution_record')
							->where('pid', $member_id)
							->page($page, $limit)
							->render();
		return $paging;
	}

	/**
	 * 删除分配记录
	 *
	 * @param int $id
	 * @return bool
	*/
	public function delete($id)
	{
		if(empty($id)){
			return false;
		}
		$result = Db::name('robot_distribution_record')
							->where('id', $id)
							->delete();
		if(empty($result)){
			Log::info('机器人分配记录删除失败'. $id);
		}
		$result = Db::name('robot_distribution')
							->where('record_id', $id)
							->delete();
		if(!empty($result)){
			return true;
		}
		return false;
	}

	/**
	 * 回收用户的机器人数量 并 删除分配记
	 *
	 * @param int $member_id
	 * @return bool
	*/
	public function reset($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		//1.获取所有分配的机器人记录
		$robot_data = Db::name('robot_distribution_record')
									->where('member_id', $member_id)
									->select();
		foreach($robot_data as $key=>$value){
			//如果当前记录是有效的 先把机器人回收回来 然后再删除记录 如果无效 直接删除记录即可
			if($value['state'] == '1'){
				//回收机器人
				$result = $this->recovery_robot($value['id'], $value['count']);
				if($result === false){
					Log::info('机器人-回收失败');
				}
			}
			//删除记录
			$result = $this->delete($value['id']);
			if($result === false){
				Log::info('机器人分配-删除失败');
				Log::info(json_encode($value));
			}
		}
		return true;
	}

	/**
	 * 给用户开通机器人
	 *
	 * @param int $pid 开通者用户ID
	 * @param int $member_id 用户ID
	 * @param int $number 机器人数量
	 * @param int $duration 时长(天数)
	 * @param int $note 备注
	 * @return bool
	*/
	// public function open_up($pid, $member_id, $number, $duration, $note = '')
	// {
	// 	if(empty($pid) || empty($member_id) || empty($duration) || empty($number)){
	// 		return false;
	// 	}
	// 	//获取开通者的角色
	// 	$AdminData = new AdminData();
	// 	$role_name = $AdminData->get_role_name($pid);
	// 	if($role_name !== '管理员'){
	//
	//
	// 		/**
	// 		 * 1.获取分配这批机器人，需要涉及到哪些机器人分配记录，获取到这些数据
	// 		*/
	// 		/**
	// 		 * 表 : rk_robot_distribution_record (分配记录表)
	// 		 * 字段 :
	// 		 *        id
	// 		 *        member_id         用户ID
	// 		 *        pid               开通者用户ID
	// 		 *        count             分配的机器人数量
	// 		 *        usable_count      可分配出去的机器人数量
	// 		 *        state             状态
	// 		 *        start_time        开始时间
	// 		 *        end_time          结束时间
	// 		 *        note              备注
	// 		 * 表 : rk_robot_distribution (分配来源关系表)
	// 		 * 字段 :
	// 		 *        id
	// 		 *        record_id         关联分配记录的ID
	// 		 *        source_id         来源ID(关联当前表的ID)
	// 		 *        member_id         用户ID
	// 		 *        count             数量
	// 		 *
	// 		*/
	// 		//获取所有有效的机器人分配记录
	// 		$robot_distribution = Db::name('robot_distribution_record')
	// 													->field('id,pid,usable_number')
	// 													->where([
	// 														'member_id'	=>	$pid,
	// 														'state'	=>	1,
	// 														'end_time'	=>	['>', time()]
	// 													])
	// 													->select();
	// 		//需要用到的机器人分配记录
	// 		$datas = [];
	// 		$i = 0;
	// 		$count = $number;
	// 		while($count !== 0){
	// 			if($robot_distribution[$i]['usable_number'] <= $count){
	// 				$datas[$i] = $robot_distribution[$i];
	// 				$datas[$i]['count'] = $datas[$i]['usable_count'];
	// 				$count = $count - $robot_distribution[$i]['usable_number'];
	// 			}else{
	// 				$datas[$i] = $robot_distribution[$i];
	// 				$datas[$i]['count'] = $count;
	// 				$count = 0;
	// 			}
	// 			json_encode(json_encode($datas));
	// 			$i++;
	// 		}
	// 	}
	// 	//生成分配记录
	// 	//获取当前时间
	// 	$start_time = time();
	// 	//计算结束时间
	// 	$end_time = $start_time + ($duration * 24 * 3600);
	// 	$rd_id = Db::name('robot_distribution_record')
	// 						->insertGetId([
	// 							'pid'	=>	$pid,
	// 							'member_id'	=>	$member_id,
	// 							'count'	=>	$number,
	// 							'usable_count'	=>	$number,
	// 							'state'	=>	1,
	// 							'start_time'	=>	$start_time,
	// 							'end_time'	=>	$end_time,
	// 							'note'	=>	$note
	// 						]);
	// 	if(!empty($rd_id)){
	// 		$ids = [];
	// 		foreach($datas as $key=>$value){
	// 			if($value['count'] !== 0){
	// 				$result = Db::name('robot_distribution')
	// 									->insertGetId([
	// 										'record_id'	=>	$rd_id,
	// 										'source_id'	=>	$value['id'],
	// 										'member_id' =>  $member_id,
	// 										'count'	=>	$value['count']
	// 									]);
	// 				if(empty($result)){
	// 					Log::info('机器人分配失败：');
	// 					Log::info($rd_id);
	// 				}
	// 				$result = Db::name('robot_distribution_record')
	// 									->where('id', $value['id'])
	// 									->setDec('usable_number', $value['usable_number']);
	// 				if(empty($result)){
	// 					Log::info('扣除分配记录的可用机器人数量失败');
	// 					Log::info(json_encode($value));
	// 				}
	// 			}
	// 		}
	// 	}
	// 	/**
	// 	 * 如果以上正常走完 开始增加机器人数量和扣除机器人数量
	// 	*/
	// 	if($role_name != '管理员'){
	// 		//扣除开通者的机器人数量
	// 		$bool = $this->deduction_robot_number($pid, $number);
	// 	}
	// 	//增加指定用户的机器人数量
	// 	$bool = $this->increase_find_member_robot_number($member_id, $number);
	// 	return true;
	// }


}
