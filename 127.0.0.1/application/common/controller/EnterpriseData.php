<?php
namespace app\common\controller;
use think\Db;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\ConsumptionStatistics;
//Redis
use app\common\controller\RedisConnect;
//Api从缓存中获取需要的数据
use app\common\controller\RedisApiData;
//财务管理数据处理类
class EnterpriseData extends Base{
	/**
	 * get_user_info 获取账号账户信息
	 *
	 * summary_all_info 汇总当前账号以及子账户的账户信息
	 *
	 * get_yesterday_data 获取昨日数据
	 *
	 * get_consumption_data 获取用户及子账户某个时间段的消费数据
	 *
	 * get_usertoday_consumption 用户今天消费统计累加
	 *
	 * get_today_allconsumption 汇总所有用户今天消费统计累加
	 *
	 * get_ower_type 获取当前账号类型
	 *
	 * get_find_member_ids 获取当前账号及子账户的id（不带商家下的子账户）
	 *
	 * get_userall_id 获取当前账号及子账户的id，（带商家下的子账户id）
	 *
	 * get_user_type 获取用户账号类型以及子账号角色类型
	 *
	 * get_user_name 获取用户账号名以及子账号用户名
	 *
	 * get_callrate_info 获取用户费率信息
	 *
	 * get_robot_rate 获取机器人月租费率
	 *
	 * get_user_statistics 获取用户日通话统计数据,获取月通话统计数据,获取年通话统计数据
	 *
	 * get_all_statistics 获取汇总日通话统计数据,获取月通话统计数据,获取年通话统计数据
	 *
	 * accumulation_month_statistics 用户本月消费统计累加
	 *
	 * accumulation_allmonth_statistics 汇总所有用户本月消费统计累加
	 *
	 * accumulation_year_statistics 用户今年消费统计累加
	 *
	 * accumulation_allyear_statistics 汇总所有用户今年消费统计累加
	 *
	 * get_consumption_statistics 获取用户消费明细
	 *
	 * get_allconsumption_statistics 汇总用户消费明细
	 *
	 * arrunique	过滤二维数组中重复的元素
	 **/


	/**
	 * 	获取账号账户信息
	 *  @param int $uid 当前用户id
	 *  @param string $username 用户名
	 *  @param string $type 用户类型
	 *  @return array
	*/
	public function get_user_info($uid,$username,$type){
		//获取账号的基本信息
		$user_info = Db::name('admin')
							->alias('a')
							->field('a.*,admin_role.name as role_name')
							->join('admin_role', 'a.role_id = admin_role.id', 'LEFT')
							->where('a.username',$username)
							->where('admin_role.name',$type)
							// ->where('type_price',2)   //计费类型 1=天  2 = 月
							->find();
		if(empty($user_info['logo'])){
				$user_info['logo'] = "public//img/e_touxiang.png";
		}
		//查询制定用户
		$user_info['robot_cnt'] = $user_info['usable_robot_cnt'];
		// //获取查询的用户的用户角色
		// $role_name = $this->get_ower_type($user_info['id']);
		// //判断查询的用户是否是商家，并且是不是当前用户
		// $son_id = [];
		// if($role_name == '商家' && $user_info['id'] != $uid){
		// 	//获取查询用户以及查询用户下子账户的汇总信息
		// 	$result = Db::name('admin')
		// 					->field('id')
		// 					->where('pid', $user_info['id'])
		// 					->select();

		// 	foreach($result as $key => $value){
		// 		$son_id[] = $value['id'];
		// 	}
		// 	//获取机器人数量
		// 	$son_robot_cnt = Db::name('admin')->where('id','in',$son_id)->sum('robot_cnt');
		// 	$user_info['robot_cnt'] = $user_info['robot_cnt'] + $son_robot_cnt;
		// }

		//获取子账号机器人到期时间
		$user_info['end_time'] = Db::name('robot_distribution_record')
														->where('member_id',$user_info['id'])
														->value('end_time');
		//获取子账号机器人到期时间(将时间戳转换成日期)
		$user_info['end_time'] = date('Y-m-d', $user_info['robot_date']);
		//获取账号的线路，ASR，短信通道信息
		$user_info['rate_info'] = $this->get_callrate_info($uid,$username,$type);
		//调用昨日数据方法
		$user_info['yesterday'] = $this->get_yesterday_data($user_info['id'],$uid);
		//调用今日数据的方法
		$args = ['member_id' => $user_info['id']];
		$user_info['today'] = $this->get_usertoday_consumption($user_info['id'],$uid);
		return $user_info;
	}

	/**
	 * 	汇总当前账号以及子账户的账户信息
	 *  @param int $uid 当前用户id
	 *	@param string 账户类型 (为空时默认是当前账号以及子账户，否则是当前账号下账户类型是$type的子账户)
	 *  @return array
	*/
	public function summary_all_info($uid,$type=''){
		$all_info = array();
		//获取当前账号以及子账户的id
		$ids = $this->get_userall_id($uid,$type);
		//汇总余额
		$all_info['money'] = Db::name('admin')->where('id','in',$ids)->sum('money');
		if(empty($all_info['money'])){
			$all_info['money'] = '0.00';
		}
		//汇总机器人数量
		$all_info['robot_cnt'] = Db::name('admin')->where('id','in',$ids)->sum('robot_cnt');
	  if(empty($all_info['robot_cnt'])){
	  	$all_info['robot_cnt'] = 0;
	  }
	  //头像,用户名,用户角色
	  $userinfo = Db::name('admin')
							->alias('a')
							->field('a.logo,a.username,a.month_price,admin_role.name as role_name')
							->join('admin_role', 'a.role_id = admin_role.id', 'LEFT')
							->where('a.id',$uid)
							// ->where('type_price',2)
							->find();

		//用户logo
	  $all_info['logo'] = $userinfo['logo'];
	  //用户名,用户角色
	  if(empty($type)){
	  	$all_info['username'] = '所有';
	  	$all_info['role_name'] = $userinfo['role_name'];
	  }else{
	  	$all_info['role_name'] = $type;
	  	$all_info['username'] = '所有';
	  }

	  //机器人到期时间
	  $all_info['end_time'] = '';
	  //获取账号的线路，ASR，短信通道,机器人月租信息
		$all_info['rate_info'] = $this->get_callrate_info($uid);
		//汇总昨日数据
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$args_yesterday = [
				'start_time' => $start_time,
				'end_time' => $end_time,
				'type' => 'day'
			];
		$all_info['yesterday'] = $this->get_consumption_data($uid,$type,$args_yesterday);

		//汇总今日数据
		$all_info['today'] = $this->get_today_allconsumption($ids);
		\think\Log::record('汇总当前账号以及子账户的账户信息');
		return $all_info;
	}

	/**
	 * 	获取用户昨日数据
	 *  @param int $user_id 要查询的用户id
	 *  @param int $uid 当前用户id
	 *  @return array
	*/
	public function get_yesterday_data($user_id,$uid){
		//获取查询的用户的用户角色
		$role_name = $this->get_ower_type($user_id);
		$son_id = [];
		$son_id[] = $user_id;
		//判断查询的用户是否是商家，并且是不是当前用户
		if($role_name == '商家' && $user_id != $uid){
			//获取查询用户以及查询用户下子账户的汇总信息
			$result = Db::name('admin')
							->field('id')
							->where('pid', $user_id)
							->select();

			foreach($result as $key => $value){
				$son_id[] = $value['id'];
			}
		}
		//获取昨日数据
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$data = Db::name('consumption_statistics')
												->field('sum(call_count) as call_count,sum(connect_count) as connect_count,sum(charging_duration) as charging_duration,sum(asr_count) as asr_count,sum(send_sms_count) as send_sms_count,sum(sms_cost) as sms_cost,sum(robot_cost) as robot_cost,sum(connect_cost) as connect_cost,sum(asr_cost) as asr_cost,sum(technology_service_cost) as technology_service_cost,sum(total_cost) as total_cost,sum(duration) as duration')
												->where('member_id','in',$son_id)
												->where('date',$start_time)
												->where('type','day')
												->find();
		//直接读表统计表里面的 duration字段，以下屏蔽  modify by xiangjinkai 2019.06.09
        //$tablename = get_order_table_name(2);
		//$data['duration'] = Db::name($tablename)->where('owner','in',$son_id)->where('create_time','between time',[$start_time,$end_time])->sum('duration');
		if(empty($data['call_count']) || empty($data['connect_count'])){
			$data['connect_rate'] = 0;
		}else{
			$data['connect_rate'] = round($data['connect_count']/$data['call_count'],2)*100;
		}
		if(empty($data['connect_count']) || empty($data['duration'])){
			$data['average_duration'] = 0;
		}else{
			$data['average_duration'] = ceil($data['duration']/$data['connect_count']);
		}
    //昨天机器人月租费用
		//$data['robot_cost'] = Db::name('robot_cost_statistics')->where('member_id','in',$son_id)->where('date','=',$yesterday)->sum('cost');

		// $yesterday_data = Db::name('consumption_statistics')
		// 						->where('type','day')
		// 						->where('member_id',$user_id)
		// 						->where('date',$start_time)
		// 						->find();
		return $data;
	}

	/**
	 * 	获取用户及子账户某个时间段的消费统计数据
	 *  @param int $uid 当前用户id
	 *  @param array $args
	 *  @return array
	*/
	public function get_consumption_data($uid, $type='', $args=[]){
		$consumption_data = array();
		//获取消费统计数据
		$count = Db::name('consumption_statistics')
						->field('sum(call_count) as call_count,sum(connect_count) as connect_count,sum(charging_duration) as charging_duration,sum(asr_count) as asr_count,sum(send_sms_count) as send_sms_count,sum(sms_cost) as sms_cost,sum(robot_cost) as robot_cost,sum(connect_cost) as connect_cost,sum(asr_cost) as asr_cost,sum(technology_service_cost) as technology_service_cost,sum(total_cost) as total_cost, sum(duration) as duration');

		//获取通话时长 取统计表字段 modify by 20190609 xiangjinkai
        //$table_name = get_order_table_name(2);
		//$duration = Db::name($table_name);

		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
				//$duration = $duration->where('create_time','>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
				//$duration = $duration->where('create_time','<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
				//$duration = $duration->where('owner',$value);
				//用户名
				$consumption_data['username'] = Db::name('admin')->where('id',$value)->value('username');
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}

		if(isset($args['member_id']) === false){
			//获取所有id
			$ids = $this->get_userall_id($uid,$type);
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
			//$duration = $duration->where('owner','in',$ids);
			//用户名
			$consumption_data['username'] = Db::name('admin')->where('id',$uid)->value('username');
		}

		$count = $count->find();
		//$duration = $duration->sum('duration');
        $duration = $count['duration'];
		\think\Log::record('获取用户及子账户某个时间段的消费统计数据');
		//总呼叫次数
		$consumption_data['call_count'] = $count['call_count'] ? $count['call_count'] : 0;
		//总接通次数
		$consumption_data['connect_count'] = $count['connect_count'] ? $count['connect_count'] : 0;
		//计算接通率
		if(empty($consumption_data['call_count']) || empty($consumption_data['connect_count'])){
			$consumption_data['connect_rate'] = 0;
		}else{
			$consumption_data['connect_rate'] = round($consumption_data['connect_count']/$consumption_data['call_count'],2)*100;
		}
		//总计费时长(分钟)
		$consumption_data['charging_duration'] = $count['charging_duration'] ? $count['charging_duration'] : 0;
		//总通话时长(秒)
		$consumption_data['connect_duration'] = $duration ? $duration : 0;
		//计算平均通话时长（秒/次）
		if(empty($consumption_data['connect_duration']) || empty($consumption_data['connect_count'])){
			$consumption_data['average_duration'] = '0';
		}else{
			$consumption_data['average_duration'] = ceil($consumption_data['connect_duration']/$consumption_data['connect_count']);
		}

		//通话费用
		$consumption_data['connect_cost'] = $count['connect_cost'] ? $count['connect_cost'] : 0.000;
		//识别费用
		$consumption_data['asr_cost'] = $count['asr_cost'] ? $count['asr_cost'] : 0.0000;
		//识别次数
		$consumption_data['asr_count'] = $count['asr_count'] ? $count['asr_count'] : 0;
		//技术服务费用
		$consumption_data['technology_service_cost'] = $count['technology_service_cost'] ? $count['technology_service_cost'] : 0.0000;
		//发送短信次数
		$consumption_data['send_sms_count'] = $count['send_sms_count'] ? $count['send_sms_count'] : 0;
		//机器人月租费用
		$consumption_data['robot_cost'] = $count['robot_cost'] ? $count['robot_cost'] : 0.000;
		//短信费用
		$consumption_data['sms_cost'] = $count['sms_cost'] ? $count['sms_cost'] : 0.000;
		//账户消费总额
		$consumption_data['total_cost'] = $count['total_cost'] ? $count['total_cost'] : 0.00;

		return $consumption_data;
	}

	/**
	 * 用户今天消费统计累加
	 * @param int $member_id 要查询信息的用户id
	 * @param int $uid 当前用户id
	 * @return array
	 */
	public function get_usertoday_consumption($member_id, $uid){
		//获取查询的用户的用户角色
		$role_name = $this->get_ower_type($member_id);
		$son_id = [];
		$son_id[] = $member_id;
		//判断查询的用户是否是商家，并且是不是当前用户
		if($role_name == '商家' && $member_id != $uid){
			//获取查询用户以及查询用户下子账户的汇总信息
			$result = Db::name('admin')
							->field('id')
							->where('pid', $member_id)
							->select();

			foreach($result as $key => $value){
				$son_id[] = $value['id'];
			}
		}
		//今天时间
		$now_time = date("Y-m-d");
		// //获取子账户的id
		// $son_id = $this->find_member_ids($member_id);
		// $now_statistics = [];
		// if(empty($son_id)){
		// 	//获取用户本身的通话时长，计费时长，识别次数，发送短信次数,通话费用，识别费用，短信费用
		// 	$now_statistics = Db::name('tel_order')
		// 									->field('sum(ceil(duration/60)) as charging_duration,sum(duration) as duration,sum(asr_cnt) as asr_count,sum(sms_count) as send_sms_count,sum(call_money) as connect_cost,sum(asr_money) as asr_cost,sum(sms_money) as sms_cost,sum(money) as use_money')
		// 									->where('owner',$member_id)
		// 									->where('create_time','>=',strtotime($now_time))
		// 									->find();
		// }else{
		// 	//获取用户本身的通话时长，计费时长，识别次数，发送短信次数
		// 	$user_data = Db::name('tel_order')
		// 				->field('sum(ceil(duration/60)) as charging_duration,sum(duration) as duration,sum(asr_cnt) as asr_count,sum(sms_count) as send_sms_count')
		// 				->where('owner',$member_id)
		// 				->where('create_time','>=',strtotime($now_time))
		// 				->find();
		// 	//获取子账户通话时长，计费时长，识别次数，发送短信次数
		// 	$son_data = Db::name('tel_order')
		// 				->field('sum(ceil(duration/60)) as charging_duration,sum(duration) as duration,sum(asr_cnt) as asr_count,sum(sms_count) as send_sms_count')
		// 				->where('owner','in',$son_id)
		// 				->where('create_time','>=',strtotime($now_time))
		// 				->find();
		// 	//获取用户本身的通话费用，识别费用，短信费用
		// 	$now_statistics = Db::name('tel_order')
		// 				->field('sum(call_money) as connect_cost,sum(asr_money) as asr_cost,sum(sms_money) as sms_cost,sum(money) as use_money')
		// 				->where('owner',$member_id)
		// 				->where('create_time','>=',strtotime($now_time))
		// 				->find();
		// 	//计算用户实际的通话时长，计费时长，识别次数，发送短信次数
		// 	$now_statistics['charging_duration'] = $user_data['charging_duration'] - $son_data['charging_duration'];
		// 	$now_statistics['duration'] = $user_data['duration'] - $son_data['duration'];
		// 	$now_statistics['asr_count'] = $user_data['asr_count'] - $son_data['asr_count'];
		// 	$now_statistics['send_sms_count'] = $user_data['send_sms_count'] - $son_data['send_sms_count'];
		// }
		//获取用户本身的通话时长，计费时长，识别次数，发送短信次数,通话费用，识别费用，短信费用
		/*$now_statistics = Db::name('tel_order')
				->field('sum(ceil(duration/60)) as charging_duration,sum(duration) as duration,sum(asr_cnt) as asr_count,sum(sms_count) as send_sms_count,sum(call_money) as connect_cost,sum(asr_money) as asr_cost,sum(sms_money) as sms_cost,sum(technology_service_cost) as technology_service_cost,sum(money) as use_money')
				->where('owner','in',$son_id)
				->where('create_time','>=',strtotime($now_time))
				->find();*/
        $redis = RedisConnect::get_redis_connect();
        $today = strtotime($now_time);
        $connected_numbers = $unconnected_numbers = $sum_charging_duration = $sum_duration = $sum_asr_cnt = $sum_sms_count = $sum_call_money = $sum_asr_money = $sum_sms_money = $sum_technology_service_cost = $sum_money = 0;
        foreach ($son_id as $key => $value) {
            //key name
            $incr_key_charging_duration =  "incr_owner_".$value."_".$today."_charging_duration";
            $incr_key_duration = "incr_owner_".$value."_".$today."_duration";
            $incr_key_asr_cnt = "incr_owner_".$value."_".$today."_asr_cnt";
            $incr_key_sms_count = "incr_owner_".$value."_".$today."_sms_count";
            $incr_key_call_money = "incr_owner_".$value."_".$today."_call_money";
            $incr_key_asr_money = "incr_owner_".$value."_".$today."_asr_money";
            $incr_key_sms_money = "incr_owner_".$value."_".$today."_sms_money";
            $incr_key_technology_service_cost = "incr_owner_".$value."_".$today."_technology_service_cost";
            $incr_key_money = "incr_owner_".$value."_".$today."_money";
            $incr_key_connected_numbers= "incr_owner_".$value."_".$today."_connected_numbers";
            $incr_key_unconnect_count = "incr_owner_".$value."_".$today."_all_unconnect_count";

            $sum_charging_duration  += $redis->get($incr_key_charging_duration);
            $sum_duration  += $redis->get($incr_key_duration);
            $sum_asr_cnt   += $redis->get($incr_key_asr_cnt);
            $sum_sms_count += $redis->get($incr_key_sms_count);
            $sum_call_money += $redis->get($incr_key_call_money);
            $sum_asr_money += $redis->get($incr_key_asr_money);
            $sum_sms_money += $redis->get($incr_key_sms_money);
            $sum_technology_service_cost += $redis->get($incr_key_technology_service_cost);
            $sum_money += $redis->get($incr_key_money);
            //接通次数 未接通号码数
            $connected_numbers += $redis->get($incr_key_connected_numbers);
            $unconnected_numbers += $redis->get($incr_key_unconnect_count);
        }

        $now_statistics = [
            'charging_duration' => $sum_charging_duration,
            'duration'=> $sum_duration,
            'asr_count'=> $sum_asr_cnt,
            'send_sms_count'=> $sum_sms_count,
            'connect_cost'=> $sum_call_money,
            'asr_cost'=> $sum_asr_money,
            'sms_cost' => $sum_sms_money,
            'technology_service_cost'=> $sum_technology_service_cost,
            'use_money'=> $sum_money,
            'call_count' => $connected_numbers + $unconnected_numbers,
            'connect_count' => $connected_numbers
            ];

		//今天机器人月租费用
		$now_statistics['robot_cost'] = Db::name('robot_cost_statistics')->where('member_id','in',$son_id)->where('date','=',$now_time)->sum('cost');
		//今天的呼叫次数
		//$now_statistics['call_count'] = Db::name('tel_order')->where('owner','in',$son_id)->where('create_time', '>=', strtotime($now_time))->count('1');
		//今天的接通次数
		//$now_statistics['connect_count'] = Db::name('tel_order')->where('owner','in',$son_id)->where('duration', '>', 0)->where('create_time', '>=', strtotime($now_time))->count('1');

		if(empty($now_statistics['call_count']) || empty($now_statistics['connect_count'])){
			$now_statistics['connect_rate'] = 0;
		}else{
			$now_statistics['connect_rate'] = round($now_statistics['connect_count']/$now_statistics['call_count'],2)*100;
		}
		if(empty($now_statistics['connect_count']) || empty($now_statistics['duration'])){
			$now_statistics['average_duration'] = 0;
		}else{
			$now_statistics['average_duration'] = ceil($now_statistics['duration']/$now_statistics['connect_count']);
		}
		\think\Log::record('用户今日消费统计累加');
		// //子账户总额
		// $now_statistics['son_cost'] = $now_statistics['use_money'] + $now_statistics['robot_cost'];
		$now_statistics['total_cost'] = $now_statistics['use_money'] + $now_statistics['robot_cost'];
		return $now_statistics;
	}

	/**
	 * 汇总所有用户今天消费统计累加
	 * @param array $ids 子账户id
	 *
	 * @return array
	 */
	public function get_today_allconsumption($ids){
		//今天时间
		$now_time = date("Y-m-d");
		/*$now_statistics = Db::name('tel_order')
										->field('sum(ceil(duration/60)) as charging_duration,sum(duration) as duration,sum(call_money) as connect_cost,sum(asr_cnt) as asr_count,sum(asr_money) as asr_cost,sum(sms_count) as send_sms_count,sum(sms_money) as sms_cost,sum(technology_service_cost) as technology_service_cost, sum(money) as money')
										->where('owner','in',$ids)
										->where('create_time','>=',strtotime($now_time))
										->find();*/
        $a = microtime(true);
        $redis = RedisConnect::get_redis_connect();
        $today = strtotime($now_time);
        $connected_numbers = $unconnected_numbers = $sum_charging_duration = $sum_duration = $sum_asr_cnt = $sum_sms_count = $sum_call_money = $sum_asr_money = $sum_sms_money = $sum_technology_service_cost = $sum_money = 0;
        foreach ($ids as $key => $value) {
            //key name
            $incr_key_charging_duration =  "incr_owner_".$value."_".$today."_charging_duration";
            $incr_key_duration = "incr_owner_".$value."_".$today."_duration";
            $incr_key_asr_cnt = "incr_owner_".$value."_".$today."_asr_cnt";
            $incr_key_sms_count = "incr_owner_".$value."_".$today."_sms_count";
            $incr_key_call_money = "incr_owner_".$value."_".$today."_call_money";
            $incr_key_asr_money = "incr_owner_".$value."_".$today."_asr_money";
            $incr_key_sms_money = "incr_owner_".$value."_".$today."_sms_money";
            $incr_key_technology_service_cost = "incr_owner_".$value."_".$today."_technology_service_cost";
            $incr_key_money = "incr_owner_".$value."_".$today."_money";
            $incr_key_connected_numbers= "incr_owner_".$value."_".$today."_charging_connected_numbers";
            $incr_key_unconnect_count = "incr_owner_".$value."_".$today."_charging_unconnect_count";

            $sum_charging_duration  += $redis->get($incr_key_charging_duration);
            $sum_duration  += $redis->get($incr_key_duration);
            $sum_asr_cnt   += $redis->get($incr_key_asr_cnt);
            $sum_sms_count += $redis->get($incr_key_sms_count);
            $sum_call_money += $redis->get($incr_key_call_money);
            $sum_asr_money += $redis->get($incr_key_asr_money);
            $sum_sms_money += $redis->get($incr_key_sms_money);
            $sum_technology_service_cost += $redis->get($incr_key_technology_service_cost);
            $sum_money += $redis->get($incr_key_money);
            //接通次数 未接通号码数
            $connected_numbers += $redis->get($incr_key_connected_numbers);
            $unconnected_numbers += $redis->get($incr_key_unconnect_count);
        }

        $now_statistics = [
            'charging_duration' => $sum_charging_duration,
            'duration'=> $sum_duration,
            'asr_count'=> $sum_asr_cnt,
            'send_sms_count'=> $sum_sms_count,
            'connect_cost'=> $sum_call_money,
            'asr_cost'=> $sum_asr_money,
            'sms_cost' => $sum_sms_money,
            'technology_service_cost'=> $sum_technology_service_cost,
            'use_money'=> $sum_money,
            'call_count' => $connected_numbers + $unconnected_numbers,
            'connect_count' => $connected_numbers
        ];
		$now_statistics['use_money'] = $now_statistics['connect_cost'] + $now_statistics['asr_cost'] + $now_statistics['sms_cost'] + $now_statistics['technology_service_cost'];
		//今天机器人月租费用
		$new_user_ids = [];
		$user_ids = Db::name('admin')
								->alias('a')
								->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
								->where('a.id', 'in', $ids)
								->field('a.id, ar.name as role_name')
								->select();
		foreach($user_ids as $u_key=>$u_value){
			$new_user_ids[] = $u_value['id'];
			if($u_value['role_name'] == '商家'){
				$where = [
					'pid'  =>  $u_value['id']
				];
				$find_user_ids = Db::name('admin')->where($where)->column('id');
				$new_user_ids = array_merge($new_user_ids, $find_user_ids);
			}
		}
		$now_statistics['robot_cost'] = Db::name('robot_cost_statistics')->where('member_id','in',$new_user_ids)->where('date','=',$now_time)->sum('cost');
		//今天的呼叫次数
		//$now_statistics['call_count'] = Db::name('member')->where('owner', 'in',$ids)->where('status', '>=', 2)->where('last_dial_time', '>=', strtotime($now_time))->count('1');
        //今天的接通次数
		//$now_statistics['connect_count'] = Db::name('member')->where('owner','in',$ids)->where('status', '=', 2)->where('last_dial_time', '>=', strtotime($now_time))->count('1');



		if(empty($now_statistics['call_count']) || empty($now_statistics['connect_count'])){
			$now_statistics['connect_rate'] = 0;
		}else{
			$now_statistics['connect_rate'] = round($now_statistics['connect_count']/$now_statistics['call_count'],2)*100;
		}
		if(empty($now_statistics['connect_count']) || empty($now_statistics['duration'])){
			$now_statistics['average_duration'] = 0;
		}else{
			$now_statistics['average_duration'] = ceil($now_statistics['duration']/$now_statistics['connect_count']);
		}
		// //子账户总额
		// $now_statistics['son_cost'] = $now_statistics['use_money'] + $now_statistics['robot_cost'];
		$now_statistics['total_cost'] = $now_statistics['use_money'] + $now_statistics['robot_cost'];
		return $now_statistics;
	}

	/**
	 * 	获取当前账号类型
	 *	@param int $uid 当前用户id
	 *	@return string
	*/
	public function get_ower_type($uid){
		$role_name = Db::name('admin')
									->alias('a')
									->join('admin_role', 'a.role_id = admin_role.id', 'LEFT')
									->where('a.id', $uid)
									->value('admin_role.name');
		return $role_name;
	}

	/**
	 * 	获取当前账号及子账户的id
	 *	@param int $uid 当前用户id
	 *  @param string $type 账户类型(为空时默认是当前账号下的子账户，否则是当前账号下账户类型是$type的子账户)
	 *	@return array
	*/
	public function get_find_member_ids($uid,$type=''){
		//当前账号角色名
		$role_name = $this->get_ower_type($uid);
		$ids = [];
		if($role_name == '管理员'){
		  if(empty($type)){//获取当前账号的子账户的id
  			$result = Db::name('admin')
  								->alias('a')
  								->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
  							->field('a.id,ar.name as role_name')
  							->where('ar.name','neq','坐席')
  							->select();
  		}else{//账号类型下子账户的id
  			$result = Db::name('admin')
  									->alias('a')
  									->field('a.id,ar.name as role_name')
  									->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
  									->where('ar.name', $type)
  									->select();
  		}
		}else{
		  if(empty($type)){//获取当前账号的子账户的id
			$result = Db::name('admin')
								->alias('a')
								->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
							->field('a.id,ar.name as role_name')
							->where('a.pid', $uid)
							->where('ar.name','neq','坐席')
							->select();
		}else{//账号类型下子账户的id
			$result = Db::name('admin')
									->alias('a')
									->field('a.id,ar.name as role_name')
									->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
									->where('a.pid', $uid)
									->where('ar.name', $type)
									->select();
		}
		}
		$son_id = array();
		foreach($result as $key=>$value){
			$ids[] = $value['id'];
		}
		if($role_name == '管理员' || !empty($type)){
	  	$son_id = $ids;  //如果当前账号是管理员，就只显示子账号,或者当前账户的类型不等于选择的账户类型
	  }else{
	  	$son_id[] = $uid;
	  	$son_id = array_merge($son_id, $ids); //汇总当前账号以及子账户的id
	  }
		return $son_id;
	}

	/**
	 * 	获取当前账号及子账户的id，如果当前账号是管理员，就只显示子账户的id(消费统计专用)
	 *	@param int $uid 当前用户id
	 *  @param string $type 账户类型
	 *	@return array
	*/
	public function get_userall_id($uid,$type=""){
		//当前账号角色名
		$role_name = $this->get_ower_type($uid);
		$ids = [];
	  if($role_name == '管理员'){
	    if(empty($type)){//获取当前账号的子账户的id
  			$result = Db::name('admin')
  								->alias('a')
  								->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
  							->field('a.id,ar.name as role_name')
  							->where('ar.name','neq','坐席')
  							->select();
  		}else{//账号类型下子账户的id
  			$result = Db::name('admin')
  									->alias('a')
  									->field('a.id,ar.name as role_name')
  									->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
  									->where('ar.name', $type)
  									->select();
  		}
	      /*//不管类型是什么查所有  modify by 向金凯 2019.06.03
          $result = Db::name('admin')
              ->alias('a')
              ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
              ->field('a.id,ar.name as role_name')
              ->where('ar.name','neq','坐席')
              ->select();*/
	  }else{
	    if(empty($type)){//获取当前账号的子账户的id
  			$result = Db::name('admin')
  								->alias('a')
  								->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
  							->field('a.id,ar.name as role_name')
  							->where('a.pid', $uid)
  							->where('ar.name','neq','坐席')
  							->select();
  		}else{//账号类型下子账户的id
  			$result = Db::name('admin')
  									->alias('a')
  									->field('a.id,ar.name as role_name')
  									->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
  									->where('a.pid', $uid)
  									->where('ar.name', $type)
  									->select();
  		}
	  }
		$son_id = array();
		foreach($result as $key=>$value){
			$son_id[] = $value['id'];
		// 	if($value['role_name'] == '商家'){ // 商家
		// 		$find_user_ids = Db::name('admin')
		// 											->alias('a')
		// 											->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
		// 											->field('a.id')
		// 											->where('a.pid', $value['id'])
		// 											->where('ar.name', '销售人员')
		// 											->select();
		// 		foreach($find_user_ids as $find_key=>$find_value){
		// 			$son_id[] = $find_value['id'];
		// 		}
		// 	}
		}
		if($role_name == '管理员' || !empty($type)){
	  	$ids = $son_id;  //如果当前账号是管理员，就只显示子账号,或者当前账户的类型不等于选择的账户类型
	  }else{
	  	$ids[] = $uid;
	  	$ids = array_merge($ids, $son_id); //汇总当前账号以及子账户的id
	  }
	  return $ids;
	}

	public function find_member_ids($uid){
		$result = Db::name('admin')
					->alias('a')
					->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
					->field('a.id,ar.name as role_name')
					->where('a.pid', $uid)
					->where('ar.name','neq','坐席')
					->select();
		$ids = [];
		foreach($result as $key=>$value){
			$ids[] = $value['id'];
		}
		return $ids;
	}

	/**
	 * 	获取用户账号类型以及子账号角色类型
	 *  @param int $uid 当前用户id
	 *  @return array
	*/
	public function get_user_type($uid){
		//获取当前账号类型
		$role_name = $this->get_ower_type($uid);
		$user_type = array();
		if($role_name == '管理员'){
			$user_type = Db::name('admin_role')->where('source_id','not in',[12,20])->order('source_id','asc')->column('name');
		}else{
			//获取用户账号类型以及子账号类型
			$user_name = Db::name('admin')
										->alias('a')
										->field('admin_role.name')
										->join('admin_role', 'a.role_id = admin_role.id', 'LEFT')
										->where('a.pid', $uid)
										->where('admin_role.name','neq','坐席')
										->select();
			$user_type[] = $role_name;
			foreach($user_name as $key => $value){
				$user_type[] = $value['name'];
			}
			//去掉重复项
			$user_type = array_unique($user_type);
		}

		if(empty($user_type)){
			$user_type[] = $role_name;
		}
		return $user_type;
	}

	public function get_user_list($user_auth){
        if(!$user_auth || empty($user_auth)){
            $user_auth = session('user_auth');
        }
        $uid = $user_auth['uid'];
        $role_name = $this->get_ower_type($uid);
        if($role_name == '管理员'){
            $user_list = Db::name('admin')->field('id,username')->where(['pid'=>$uid])->select();
        }elseif($role_name == '运营商'){
            $user_list = Db::name('admin')->field('id,username')->where(['id'=>$uid,'role_id'=>16])->select();
        }elseif($role_name == '代理商'){
            $user_list = Db::name('admin')->field('id,username')->where(['id'=>$uid,'role_id'=>17])->select();
        }elseif($role_name == '商家'){
            $user_list = Db::name('admin')->field('id,username')->where(['id'=>$uid,'role_id'=>18])->select();
        }else{
            $user_list = [];
        }
        return $user_list;
    }

	/**
	 * 	获取子账号用户名
	 *  @param int $uid 当前用户id
	 *  @param int $type 用户类型
	 *  @return array
	*/
	public function get_user_name($uid, $type){
		//获取当前账号以及子账号的姓名，角色名称及rold_id
		$admin_info = Db::name('admin')->where('id',$uid)->find();
		if($admin_info['role_id'] == 12){
		   $user_data = Db::name('admin')
									->alias('a')
									->field('a.id,a.username,a.role_id,admin_role.name')
									->join('admin_role', 'a.role_id = admin_role.id', 'LEFT')
									->where('admin_role.name', $type)
									->select();
		}else{
		  $user_data = Db::name('admin')
									->alias('a')
									->field('a.id,a.username,a.role_id,admin_role.name')
									->join('admin_role', 'a.role_id = admin_role.id', 'LEFT')
									->where('a.pid', $uid)
									->where('admin_role.name', $type)
									->select();
		}

		return $user_data;
	}

	/**
	 * 	获取用户费率信息
	 *
	 *  @param int $uid 当前用户id
	 *	@param string $username 用户名
	 *  @param string $type 用户类型
	 *  @param string $rate_type 费率类型
	 *  @return array
	*/
	public function get_callrate_info($uid,$username = '',$type = '',$rate_type = ''){
		$user_auth = session('user_auth');
		if(empty($username) && !empty($type) || $username == '全部账户' && !empty($type)){
			//全部类型或者销售都是获取当前账号的
			if($user_auth['role'] == '管理员'){
			  $where = [
  			  'ar.name' => $type
  			];
			}else{
			  $where = [
			    'a.pid' => $user_auth['uid'],
			    'ar.name' => $type
			  ];
			}
			$ids = Db::name('admin')
			        ->alias('a')
			        ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
			        ->where($where)
			        ->column('a.id');
		}else{
			$ids = Db::name('admin')
			        ->alias('a')
			        ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
			        ->where('a.username',$username)
			        ->where('ar.name',$type)
			        ->column('a.id');
		}
		if(empty($rate_type)){//为空默认是搜索全部
			$line_info = Db::name('tel_line_group')->field('id,name,sales_price')->where('user_id', 'in', $ids)->select();
			$asr_info = Db::name('tel_interface')->field('id,name,sale_price')->where('owner', 'in', $ids)->select();
			$sms_info = Db::name('sms_channel')->field('id,name,price')->where('owner', 'in', $ids)->select();
			$robot_info[0] = $this->get_robot_rate($ids);

			$rate_info = [
				'line_info' => $line_info,
				'asr_info' => $asr_info,
				'sms_info' => $sms_info,
				'robot_info' => $robot_info
			];
		}else if($rate_type == '语音通话费率'){ //语音通话费率
			$rate_info = Db::name('tel_line_group');
			$rate_info = $rate_info->field('name,sales_price')->where('user_id', 'in', $ids)->select();
		}else if($rate_type == '语音识别费率'){ //语音识别费率
			$rate_info = Db::name('tel_interface');
			$rate_info = $rate_info->field('name,sale_price')->where('owner', 'in', $ids)->select();
		}else{ //短信费率
			$rate_info = Db::name('sms_channel');
			$rate_info = $rate_info->field('name,price')->where('owner', 'in', $ids)->select();
		}

		// $rate_info = $this->arrunique($rate_info);
		return $rate_info;
	}

	/**
	 * 	获取机器人月租费率
	 *
	 *  @param int $member_id
	 *  @return float
	*/
	public function get_robot_rate($member_id){
		//获取机器人基本信息
		$robot_info = Db::name('admin')->field('month_price,type_price,robot_cnt')->where('id', 'in', $member_id)->select();
		//计算天数
		$daycount = date('t');
		$sum = 0;
    foreach($robot_info as $key=>$value){
  		//判断机器人费用类型
  		if($value['type_price'] == 1){//天
  			//机器人月租费率
  			$robot_rate = $value['month_price']*$daycount;
  		}else{//月
  			//机器人月租
  			$robot_rate = $value['month_price'];
  		}
  		if(empty($robot_rate)){
  			$robot_rate = '0.00000';
  		}
  		$sum += reserved_decimal($robot_rate,3);//保留三位小数
    }
		return $sum;
	}


	/**
	 * 	获取用户日通话统计数据,获取月通话统计数据,获取年通话统计数据
	 *	@param int $uid 当前用户id
	 *	@param string $type 通话统计类型（day,month,year）
	 *  @param int $page 当前页码
	 *  @param int $limit 每页显示的数量
	 *  @param string $username 用户名
	 *  @param string $start_time 通话开始时间
	 *  @param string $end_time 通话结束时间
	 *	@return array
	*/
	public function get_user_statistics($uid, $type, $page = 1, $limit = 10, $username = '', $user_type = '', $start_time = '', $end_time = ''){
		if(empty($username)){
			$id = $uid;
		}else{
			$id = Db::name('admin')->where('username',$username)->value('id');
		}
		$field = "cs.*";
		$datas = Db::name('consumption_statistics')
						->field($field)
						->alias('cs')
						->where('cs.member_id',$id);
		$count = Db::name('consumption_statistics')
						->where('member_id',$id);

		// if($type == 'day'){
		// 	$datas = $datas->where('cs.call_count','neq','')
		// 					 ->where('cs.connect_count','neq','');
		// 	$count = $count->where('call_count','neq','')->where('connect_count','neq','');
		// }
		$datas = $datas->where('cs.type',$type);
		$count = $count->where('type',$type);
		if(!empty($start_time) && $type == 'day'){
			$start_time = strtotime($start_time);
			$datas = $datas->where('cs.date','>=',$start_time);
			$count = $count->where('date','>=',$start_time);
		}
		if(!empty($end_time) && $type == 'day'){
			$end_time = strtotime($end_time);
			$datas = $datas->where('cs.date','<=',$end_time);
			$count = $count->where('date','<=',$end_time);
		}
		$datas = $datas->page($page,$limit)
						 ->order('cs.date', 'desc')
						 ->select();

		$count = $count->count('id');
		//查找admin
        $admin = Db::name('admin')->field('id,username as member_name,month_price')->select();
        $new_admin = [];
        if($admin){
            foreach ($admin as $key=>$val) {
                $new_admin[$val['id']] = $val;
            }
        }
        $admin_keys = array_keys($new_admin);
        if($datas){
            foreach ($datas as $key => $val){
                if(in_array($val['member_id'],$admin_keys)){
                    $datas[$key]['member_name'] = $new_admin[$val['member_id']]['member_name'];
                    $datas[$key]['month_price'] = $new_admin[$val['member_id']]['month_price'];
                }else{
                    $datas[$key]['member_name'] = '';
                    $datas[$key]['month_price'] = '';
                }
            }
        }
        unset($admin,$new_admin);
		foreach ($datas as $key => $value) {
			if(empty($value['date'])){
				$datas[$key]['date'] = "暂无记录";
			}else {
				if($type == 'day'){
					$datas[$key]['date'] = date('Y-m-d', $value['date']);
				}else if($type == 'month'){
					$datas[$key]['date'] = date('Y-m', $value['date']);
				}else{
					$datas[$key]['date'] = date('Y', $value['date']);
				}
			}

			//序列号
			$datas[$key]['sequence'] = ($page-1)*$limit+($key+1);

		}
		if($type == 'day'){
			//调用今日数据的方法
			$today_data = $this->get_usertoday_consumption($id,$uid);
		}else if($type == 'month'){
			//调用本月数据的方法
			$today_data = $this->accumulation_month_statistics($id,$uid);
		}else{
			//调用今年数据的方法
			$today_data = $this->accumulation_year_statistics($id,$uid);
		}

		$data = ['list'=>$datas,'count'=>$count,'type'=>$type,'today_data'=>$today_data];


		return $data;
	}

    public function user_list($user_id)
    {
        $user_list = [];
        $users = Db::name('admin')->where('pid', $user_id)->field('id, pid')->select();
        foreach($users as $key=>$value){
            if(!empty($value)){
                $user_list[$value['id']] = $value;
                $user_list[$value['id']]['find'] = $this->user_list($value['id']);
            }
        }
        return $user_list;
    }

    /**
     * 将树形结构的数组按照顺序遍历为二维数组
     * @param
     * @author xiangjinkai
     * @date 2019.05.31
     * @return
     */
    function arr_child ($array) {
        static $res;
        if (!is_array($array)) {
            return false;
        }
        foreach ($array as $k=>$v) {
            if (is_array($v) && isset($v['find'])) {
                $child = $v['find']; //将这个数组的子节点赋给变量 $child
                unset($v['find']); //释放这个数组的所有子节点
                $res[] = $v; //将释放后的数组填充到新数组 $res
                arr_child ($child); //递归处理释放前的包含子节点的数组
            } else {
                $res[] = $v;
            }
        }
        return $res;
    }

    /**
     * 查詢用戶ID
     *
     * @param int $uid 当前登录的用户ID
     * @param string $role 当前登录的用户角色
     * @param int $user_id 要查询的用户id
     * @param string $usertype 要查询的用户层级
     * @param string $username 要查询的用户名
     * @return array
     */
    function get_user_ids($uid,$role,$user_id,$usertype,$username){

        $where_xs = $where_xs_or= [];
        if(!empty($user_id)){
            $where['pid'] = $user_id;
            $where_['id'] = $user_id;
        }
        if(!empty($username)){
            $where['username'] = ['=',$username];
            $where_xs['username'] = $username;
        }
        $where_zx['role_id'] = ['<>',20];
        if($role == '管理员'){
            if(empty($user_id) && empty($username)){
                //查所有
                $user_list = Db::name('admin')->field('id')->where(['role_id'=>16])->select();

            }else{
                if($username && $user_id){
                    $user_list = Db::name('admin')->where(" pid = $user_id and username = '" . $username . "'")->select();
                }elseif($username && !$user_id){
                    $user_list = Db::name('admin')->where(" username = '" . $username . "'")->select();
                }else{
                    $user_list = Db::name('admin')->where($where_)->where($where_zx)->select();
                }
            }
        }else{
            if($username){
                if($user_id == $uid) {
                    $user_list = Db::name('admin')->where(" pid = $uid and username = '" . $username . "' ")->where($where_zx)->select();
                }else{
                    $user_list = Db::name('admin')->where(" id = $user_id and username = '" . $username . "' ")->where($where_zx)->select();
                }
            }else{
                $user_list = Db::name('admin')->where($where_)->where($where_zx)->select();
            }
        }
        $ids = [];
        if($user_list){
            foreach ($user_list as $key => $val){
                $ids[] = $val['id'];
            }
        }
        return $ids;
    }



    /**
     *查找日统计数据
     * @param
     * @author xiangjinkai
     * @date 2019.05.31
     * @return
     */
    public function get_today_user_statistics($user_auth, $type, $page = 1, $limit = 10,$user_id, $username, $usertype, $start_time = '', $end_time = ''){

        $uid = $user_auth['uid'];
        $role = $user_auth['role'];

        $ids = $this->get_user_ids($uid,$role,$user_id,$usertype, $username);

        $field = "cs.*";
        $datas = Db::name('consumption_statistics')
            ->field($field)
            ->alias('cs')
            ->where('cs.member_id','in',$ids);
        $count = Db::name('consumption_statistics')
            ->where('member_id','in',$ids);

        $datas = $datas->where('cs.type',$type);
        $count = $count->where('type',$type);
        if(!empty($start_time) && $type == 'day'){
            $start_time = strtotime($start_time);
            $datas = $datas->where('cs.date','>=',$start_time);
            $count = $count->where('date','>=',$start_time);
        }
        if(!empty($end_time) && $type == 'day'){
            $end_time = strtotime($end_time);
            $datas = $datas->where('cs.date','<=',$end_time);
            $count = $count->where('date','<=',$end_time);
        }
        $datas = $datas->page($page,$limit)
            ->order('cs.date', 'desc')
            ->select();

        $uids = implode(',',$ids);

        //$count = $count->count('1');
        //采用原生sql，比count统计快300倍
        /*if(!empty($uids)){
            $sql = "SELECT COUNT(id) as counts FROM `rk_consumption_statistics`
            WHERE `member_id` IN ($uids) AND `type` = 'day'  LIMIT 1";
            $count = Db::query($sql);
            $count = $count[0]['counts'];
        }else{
            $count = 0;5872,5873,5875,5879,5885
        }*/
        $count = $count->count(1);
        //查找admin
        $admin = Db::name('admin')->field('id,username as member_name,month_price')->select();
        $new_admin = [];
        if($admin){
            foreach ($admin as $key=>$val) {
                $new_admin[$val['id']] = $val;
            }
        }
        $admin_keys = array_keys($new_admin);
        if($datas){
            foreach ($datas as $key => $val){
                if(in_array($val['member_id'],$admin_keys)){
                    $datas[$key]['member_name'] = $new_admin[$val['member_id']]['member_name'];
                    $datas[$key]['month_price'] = $new_admin[$val['member_id']]['month_price'];
                }else{
                    $datas[$key]['member_name'] = '';
                    $datas[$key]['month_price'] = '';
                }
            }
        }
        unset($admin,$new_admin);
        foreach ($datas as $key => $value) {
            if(empty($value['date'])){
                $datas[$key]['date'] = "暂无记录";
            }else {
                if($type == 'day'){
                    $datas[$key]['date'] = date('Y-m-d', $value['date']);
                }else if($type == 'month'){
                    $datas[$key]['date'] = date('Y-m', $value['date']);
                }else{
                    $datas[$key]['date'] = date('Y', $value['date']);
                }
            }

            //序列号
            $datas[$key]['sequence'] = ($page-1)*$limit+($key+1);

        }
        //调用今日数据的方法
        //判断是否是商家,是的话需要加上销售id
				$role_name = Db::name('admin')
                      ->alias('a')
                      ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                      ->where('a.id', $user_id)
                      ->value('ar.name');
        if($role_name == '商家'){
            $shangjia['pid'] = ['in',$ids];
            $shangjia['role_id'] = 19;
						$shangjia['is_jizhang'] = 0;
            $list = Db::name('admin')->where($shangjia)->column('id');
            $ids = array_merge($ids,$list);
        }
        $today_data = $this->get_today_allconsumption($ids);


        //$today_data = [];
        $data = ['list'=>$datas,'count'=>$count,'type'=>$type,'today_data'=>$today_data];
        return $data;
    }
    /**
     * 查询管理员下面所有运营商
     */

	/**
	 * 	获取汇总日通话统计数据,获取月通话统计数据,获取年通话统计数据
	 *	@param int $uid 当前用户id
	 *	@param string $type 通话统计类型（day,month,year）
	 *  @param int $page 当前页码
	 *  @param int $limit 每页显示的数量
	 *  @param string $start_time 通话开始时间
	 *  @param string $end_time 通话结束时间
	 *	@return array
	*/
	public function get_all_statistics($uid, $type, $page = 1, $limit = 10, $start_time = '', $end_time = '', $user_type=''){
		//获取所有id
		$ids = $this->get_userall_id($uid,$user_type);
		$son_id = $this->get_find_member_ids($uid,$user_type);
		if($type == 'day'){
			//调用今日数据的方法
			$today_data = $this->get_today_allconsumption($ids);
		}else if($type == 'month'){
			//调用本月数据的方法
			$today_data = $this->accumulation_allmonth_statistics($ids);
		}else{
			//调用今年数据的方法
			$today_data = $this->accumulation_allyear_statistics($ids);
		}
		$datas = Db::name('consumption_statistics')
						->field('cs.*,a.username as member_name,a.month_price')
						->alias('cs')
						->join('admin a', 'a.id = cs.member_id', 'LEFT')
						->where('member_id', 'in', $son_id);
		$count = Db::name('consumption_statistics')
						->where('member_id', 'in', $son_id);
		// if($type == 'day'){
		// 	$datas = $datas->where('cs.call_count','neq','')
		// 					 ->where('cs.connect_count','neq','');
		// 	$count = $count->where('call_count','neq','')->where('connect_count','neq','');
		// }
		$datas = $datas->where('cs.type',$type);
		$count = $count->where('type',$type);

		if(!empty($start_time) && $type == 'day'){
			$start_time = strtotime($start_time);
			$datas = $datas->where('cs.date','>=',$start_time);
			$count = $count->where('date','>=',$start_time);
		}
		if(!empty($end_time) && $type == 'day'){
			$end_time = strtotime($end_time);
			$datas = $datas->where('cs.date','<=',$end_time);
			$count = $count->where('date','<=',$end_time);
		}
		$datas = $datas->page($page,$limit)
						 ->order('cs.date', 'desc')
						 ->select();
		$count = $count->count('id');

		foreach ($datas as $key => $value) {
			if(empty($value['date'])){
				$datas[$key]['date'] = "暂无记录";
			}else {
				if($type == 'day'){
					$datas[$key]['date'] = date('Y-m-d', $value['date']);
				}else if($type == 'month'){
					$datas[$key]['date'] = date('Y-m', $value['date']);
				}else{
					$datas[$key]['date'] = date('Y', $value['date']);
				}
			}

			//序列号
			$datas[$key]['sequence'] = ($page-1)*$limit+($key+1);

		}

		$data = ['list'=>$datas,'count'=>$count,'type'=>$type,'today_data'=>$today_data];
		return $data;
	}

	/**
	 * 用户本月消费统计累加
	 * @param int $member_id
	 * @param int $uid
	 * @return array
	 */
	public function accumulation_month_statistics($member_id, $uid){
		//获取查询的用户的用户角色
		$role_name = $this->get_ower_type($member_id);
		$son_id = [];
		$son_id[] = $member_id;
		//判断查询的用户是否是商家，并且是不是当前用户
		if($role_name == '商家' && $member_id != $uid){
			//获取查询用户以及查询用户下子账户的汇总信息
			$result = Db::name('admin')
							->field('id')
							->where('pid', $member_id)
							->select();

			foreach($result as $key => $value){
				$son_id[] = $value['id'];
			}
		}
		//获取本月第一天时间
		$start = date('Y-m-01',strtotime(date('Y-m-d')));
		//获取昨天时间
		$end = date("Y-m-d 23:59:59", strtotime('-1 day'));
		$start_time = strtotime($start);
		$end_time = strtotime($end);
		//今天的时间
		$now_time = date("Y-m-d");
		if(strtotime($start) == strtotime($now_time)){
			$before_statistics['call_count'] = 0;
			$before_statistics['connect_count'] = 0;
			$before_statistics['charging_duration'] = 0;
			$before_statistics['duration'] = 0;
			$before_statistics['connect_cost'] = 0;
			$before_statistics['asr_count'] = 0;
			$before_statistics['asr_cost'] = 0;
			$before_statistics['robot_cost'] = 0;
			$before_statistics['send_sms_count'] = 0;
			$before_statistics['sms_cost'] = 0;
			$before_statistics['technology_service_cost'] = 0;
			$before_statistics['total_cost'] = 0;
		}else{

            //本月第一天到昨天的消费统计汇总
            $RedisApiData = new RedisApiData();
            $before_statistics = $RedisApiData->get_user_oneday_to_yestoday_consumption_statistics($son_id,$start_time,$end_time);
		}

		\think\Log::record('用户本月消费统计累加');

		//今天的消费统计汇总
		$now_statistics = $this->get_usertoday_consumption($member_id,$uid);
		//本月消费统计数据
		$today_data = array();
		$today_data['member_name'] = Db::name('admin')->where('id',$member_id)->value('username');
		$today_data['type'] = 'month';
		$today_data['date'] = strtotime(date("Y-m"));
		$today_data['call_count'] = $before_statistics['call_count'] + $now_statistics['call_count'];
		$today_data['connect_count'] = $before_statistics['connect_count'] + $now_statistics['connect_count'];
		$today_data['charging_duration'] = $before_statistics['charging_duration'] + $now_statistics['charging_duration'];
		$today_data['duration'] = $before_statistics['duration'] + $now_statistics['duration'];
		$today_data['connect_cost'] = $before_statistics['connect_cost'] + $now_statistics['connect_cost'];
		$today_data['asr_count'] = $before_statistics['asr_count'] + $now_statistics['asr_count'];
		$today_data['asr_cost'] = $before_statistics['asr_cost'] + $now_statistics['asr_cost'];
		$today_data['robot_cost'] = $before_statistics['robot_cost'] + $now_statistics['robot_cost'];
		$today_data['send_sms_count'] = $before_statistics['send_sms_count'] + $now_statistics['send_sms_count'];
		$today_data['sms_cost'] = $before_statistics['sms_cost'] + $now_statistics['sms_cost'];
		$today_data['technology_service_cost'] = $before_statistics['technology_service_cost'] + $now_statistics['technology_service_cost'];
		$today_data['total_cost'] = $before_statistics['total_cost'] + $now_statistics['total_cost'];
		if(empty($today_data['call_count']) || empty($today_data['connect_count'])){
			$today_data['connect_rate'] = 0;
		}else{
			$today_data['connect_rate'] = round($today_data['connect_count']/$today_data['call_count'],2)*100;
		}
		if(empty($today_data['connect_count']) || empty($today_data['duration'])){
			$today_data['average_duration'] = 0;
		}else{
			$today_data['average_duration'] = ceil($today_data['duration']/$today_data['connect_count']);
		}
		return $today_data;
	}

	/**
	 * 汇总所有用户本月消费统计累加
	 * @param array $ids 子账户id
	 *
	 * @return array
	 */
	public function accumulation_allmonth_statistics($ids){
		//获取本月第一天时间
		$start = date('Y-m-01',strtotime(date('Y-m-d')));
		//获取昨天时间
		$end = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($start);
		$end_time = strtotime($end);
		//今天时间
		$now_time = date("Y-m-d");
		if(strtotime($start) == strtotime($now_time)){
			$before_statistics['call_count'] = 0;
			$before_statistics['connect_count'] = 0;
			$before_statistics['charging_duration'] = 0;
			$before_statistics['duration'] = 0;
			$before_statistics['connect_cost'] = 0;
			$before_statistics['asr_count'] = 0;
			$before_statistics['asr_cost'] = 0;
			$before_statistics['robot_cost'] = 0;
			$before_statistics['send_sms_count'] = 0;
			$before_statistics['sms_cost'] = 0;
			$before_statistics['technology_service_cost'] = 0;
			$before_statistics['total_cost'] = 0;
		}else{
			//本月第一天到昨天的消费统计汇总
			$before_statistics = Db::name('consumption_statistics')
												->field('sum(call_count) as call_count,sum(connect_count) as connect_count,sum(charging_duration) as charging_duration,sum(asr_count) as asr_count,sum(send_sms_count) as send_sms_count,sum(sms_cost) as sms_cost,sum(robot_cost) as robot_cost,sum(connect_cost) as connect_cost,sum(asr_cost) as asr_cost,sum(technology_service_cost) as technology_service_cost,sum(total_cost) as total_cost')
												->where('member_id','in',$ids)
												->where('date','between time',[$start_time,$end_time])
												->where('type','day')
												->find();
			$before_statistics['duration'] = Db::name('tel_order')->where('owner','in',$ids)->where('create_time','between time',[$start_time,$end_time])->sum('duration');

		}
		\think\Log::record('汇总用户本月至昨天消费统计累加');

		//今天的消费统计汇总
		$now_statistics = $this->get_today_allconsumption($ids);
		\think\Log::record('汇总用户今天消费统计累加');
		//本月消费统计数据
		$today_data['member_name'] = '所有';
		$today_data['type'] = 'month';
		$today_data['date'] = strtotime(date("Y-m"));
		$today_data['call_count'] = $before_statistics['call_count'] + $now_statistics['call_count'];
		$today_data['connect_count'] = $before_statistics['connect_count'] + $now_statistics['connect_count'];
		$today_data['charging_duration'] = $before_statistics['charging_duration'] + $now_statistics['charging_duration'];
		$today_data['duration'] = $before_statistics['duration'] + $now_statistics['duration'];
		$today_data['connect_cost'] = $before_statistics['connect_cost'] + $now_statistics['connect_cost'];
		$today_data['asr_count'] = $before_statistics['asr_count'] + $now_statistics['asr_count'];
		$today_data['asr_cost'] = $before_statistics['asr_cost'] + $now_statistics['asr_cost'];
		$today_data['robot_cost'] = $before_statistics['robot_cost'] + $now_statistics['robot_cost'];
		$today_data['send_sms_count'] = $before_statistics['send_sms_count'] + $now_statistics['send_sms_count'];
		$today_data['sms_cost'] = $before_statistics['sms_cost'] + $now_statistics['sms_cost'];
		$today_data['technology_service_cost'] = $before_statistics['technology_service_cost'] + $now_statistics['technology_service_cost'];
		$today_data['total_cost'] = $before_statistics['total_cost'] + $now_statistics['total_cost'];
		if(empty($today_data['call_count']) || empty($today_data['connect_count'])){
			$today_data['connect_rate'] = 0;
		}else{
			$today_data['connect_rate'] = round($today_data['connect_count']/$today_data['call_count'],2)*100;
		}
		if(empty($today_data['connect_count']) || empty($today_data['duration'])){
			$today_data['average_duration'] = 0;
		}else{
			$today_data['average_duration'] = ceil($today_data['duration']/$today_data['connect_count']);
		}
		\think\Log::record('汇总用户本月消费统计累加');
		return $today_data;
	}

	/**
	 * 用户今年消费统计累加
	 * @param int $member_id
	 * @param int $uid
	 * @return array
	 */
	public function accumulation_year_statistics($member_id, $uid){
		//获取查询的用户的用户角色
		$role_name = $this->get_ower_type($member_id);
		$son_id = [];
		$son_id[] = $member_id;
		//判断查询的用户是否是商家，并且是不是当前用户
		if($role_name == '商家' && $member_id != $uid){
			//获取查询用户以及查询用户下子账户的汇总信息
			$result = Db::name('admin')
							->field('id')
							->where('pid', $member_id)
							->select();

			foreach($result as $key => $value){
				$son_id[] = $value['id'];
			}
		}
		//获取今年第一天时间
		$start = date('Y-01');
		//获取上个月时间
		$end = date('Y-m-t 23:59:59',strtotime('-1 month'));
		$start_time = strtotime($start);
		$end_time = strtotime($end);
		//获取上个月月末的时间
		$last_time =strtotime(date('Y-m-t', strtotime('-1 month')));
		//获取本月的时间
		$now_time = date("Y-m");
		if(strtotime($start) == strtotime($now_time)){
			$before_statistics['call_count'] = 0;
			$before_statistics['connect_count'] = 0;
			$before_statistics['charging_duration'] = 0;
			$before_statistics['duration'] = 0;
			$before_statistics['connect_cost'] = 0;
			$before_statistics['asr_count'] = 0;
			$before_statistics['asr_cost'] = 0;
			$before_statistics['robot_cost'] = 0;
			$before_statistics['send_sms_count'] = 0;
			$before_statistics['sms_cost'] = 0;
			$before_statistics['technology_service_cost'] = 0;
			$before_statistics['total_cost'] = 0;
		}else{

			//今年第一月到上个月的消费统计汇总
            $RedisApiData = new RedisApiData();
            $before_statistics = $RedisApiData->get_user_oneday_to_yestoday_consumption_statistics($son_id,$start_time,$end_time);
		}

		\think\Log::record('用户今年初至上个月消费统计累加');

		//本月的消费统计汇总
		$now_statistics = $this->accumulation_month_statistics($member_id,$uid);
		//今年的消费统计数据
		$today_data = array();
		$today_data['member_name'] = Db::name('admin')->where('id',$member_id)->value('username');
		$today_data['type'] = 'year';
		$today_data['date'] = strtotime(date("Y"));
		$today_data['call_count'] = $before_statistics['call_count'] + $now_statistics['call_count'];
		$today_data['connect_count'] = $before_statistics['connect_count'] + $now_statistics['connect_count'];
		$today_data['charging_duration'] = $before_statistics['charging_duration'] + $now_statistics['charging_duration'];
		$today_data['duration'] = $before_statistics['duration'] + $now_statistics['duration'];
		$today_data['connect_cost'] = $before_statistics['connect_cost'] + $now_statistics['connect_cost'];
		$today_data['asr_count'] = $before_statistics['asr_count'] + $now_statistics['asr_count'];
		$today_data['asr_cost'] = $before_statistics['asr_cost'] + $now_statistics['asr_cost'];
		$today_data['robot_cost'] = $before_statistics['robot_cost'] + $now_statistics['robot_cost'];
		$today_data['send_sms_count'] = $before_statistics['send_sms_count'] + $now_statistics['send_sms_count'];
		$today_data['sms_cost'] = $before_statistics['sms_cost'] + $now_statistics['sms_cost'];
		$today_data['technology_service_cost'] = $before_statistics['technology_service_cost'] + $now_statistics['technology_service_cost'];
		$today_data['total_cost'] = $before_statistics['total_cost'] + $now_statistics['total_cost'];
		if(empty($today_data['call_count']) || empty($today_data['connect_count'])){
			$today_data['connect_rate'] = 0;
		}else{
			$today_data['connect_rate'] = round($today_data['connect_count']/$today_data['call_count'],2)*100;
		}
		if(empty($today_data['connect_count']) || empty($today_data['duration'])){
			$today_data['average_duration'] = 0;
		}else{
			$today_data['average_duration'] = ceil($today_data['duration']/$today_data['connect_count']);
		}
		return $today_data;
	}

	/**
	 * 汇总所有用户今年消费统计累加
	 * @param array $ids 子账户id
	 *
	 * @return array
	 */
	public function accumulation_allyear_statistics($ids){
		//获取今年第一天时间
		$start = date('Y-01');
		//获取上个月时间
		$end = date('Y-m',strtotime('-1 month'));
		$start_time = strtotime($start);
		$end_time = strtotime($end);
		//获取上个月月末的时间
		$last_time = strtotime(date('Y-m-d',strtotime(date('Y-m-01') . '-1 day')));
		//获取本月的时间
		$now_time = date("Y-m");
		if(strtotime($start) == strtotime($now_time)){
			$before_statistics['call_count'] = 0;
			$before_statistics['connect_count'] = 0;
			$before_statistics['charging_duration'] = 0;
			$before_statistics['duration'] = 0;
			$before_statistics['connect_cost'] = 0;
			$before_statistics['asr_count'] = 0;
			$before_statistics['asr_cost'] = 0;
			$before_statistics['robot_cost'] = 0;
			$before_statistics['send_sms_count'] = 0;
			$before_statistics['sms_cost'] = 0;
			$before_statistics['technology_service_cost'] = 0;
			$before_statistics['total_cost'] = 0;
		}else{
			//今年第一月到上个月的消费统计汇总
			$before_statistics = Db::name('consumption_statistics')
												->field('sum(call_count) as call_count,sum(connect_count) as connect_count,sum(charging_duration) as charging_duration,sum(asr_count) as asr_count,sum(send_sms_count) as send_sms_count,sum(sms_cost) as sms_cost,sum(robot_cost) as robot_cost,sum(connect_cost) as connect_cost,sum(asr_cost) as asr_cost,sum(technology_service_cost) as technology_service_cost,sum(total_cost) as total_cost')
												->where('member_id','in',$ids)
												->where('date','between time',[$start_time,$end_time])
												->where('type','month')
												->find();
			$before_statistics['duration'] = Db::name('tel_order')->where('owner','in',$ids)->where('create_time','between time',[$start,$last_time])->sum('duration');
		}
		\think\Log::record('汇总用户今年初至上个月消费统计累加');

		//本月的消费统计汇总
		$now_statistics = $this->accumulation_allmonth_statistics($ids);
		\think\Log::record('汇总用户本月消费统计累加');
		//本月消费统计数据
		$today_data['member_name'] = '所有';
		$today_data['type'] = 'month';
		$today_data['date'] = strtotime(date("Y"));
		$today_data['call_count'] = $before_statistics['call_count'] + $now_statistics['call_count'];
		$today_data['connect_count'] = $before_statistics['connect_count'] + $now_statistics['connect_count'];
		$today_data['charging_duration'] = $before_statistics['charging_duration'] + $now_statistics['charging_duration'];
		$today_data['duration'] = $before_statistics['duration'] + $now_statistics['duration'];
		$today_data['connect_cost'] = $before_statistics['connect_cost'] + $now_statistics['connect_cost'];
		$today_data['asr_count'] = $before_statistics['asr_count'] + $now_statistics['asr_count'];
		$today_data['asr_cost'] = $before_statistics['asr_cost'] + $now_statistics['asr_cost'];
		$today_data['robot_cost'] = $before_statistics['robot_cost'] + $now_statistics['robot_cost'];
		$today_data['send_sms_count'] = $before_statistics['send_sms_count'] + $now_statistics['send_sms_count'];
		$today_data['sms_cost'] = $before_statistics['sms_cost'] + $now_statistics['sms_cost'];
		$today_data['total_cost'] = $before_statistics['total_cost'] + $now_statistics['total_cost'];
		$today_data['technology_service_cost'] = $before_statistics['technology_service_cost'] + $now_statistics['technology_service_cost'];
		if(empty($today_data['call_count']) || empty($today_data['connect_count'])){
			$today_data['connect_rate'] = 0;
		}else{
			$today_data['connect_rate'] = round($today_data['connect_count']/$today_data['call_count'],2)*100;
		}
		if(empty($today_data['connect_count']) || empty($today_data['duration'])){
			$today_data['average_duration'] = 0;
		}else{
			$today_data['average_duration'] = ceil($today_data['duration']/$today_data['connect_count']);
		}
		\think\Log::record('汇总用户今年消费统计累加');
		return $today_data;
	}
    /**
     * 获取用户消费明细
     * @param int $uid
     * @param int $page
     * @param int $limit
     * @param string $username
     * @param string $linename
     * @param string $asrname
     * @param string $smsname
     * @param string $start_time 开始时间
     * @param string $end_tiem 结束时间
     * @param string $callNum 被叫号码
     * @return array
     */
    public function get_consumption_statistics($uid,$args)
    {
        foreach($args as $k => $v){
            if($k == 'page'){
                $page = $v;
            }
            else if($k == 'limit'){
                $limit = $v;
            }
            else if($k == 'usertype'){
                $usertype = $v;
            }
            else if($k == 'username'){
                $username = $v;
            }
            else if($k == 'linename'){
                $linename = $v;
            }
            else if($k == 'asrname'){
                $asrname = $v;
            }
            else if($k == 'smsname'){
                $smsname = $v;
            }
            else if($k == 'start_time'){
                $start_time = $v;
            }
            else if($k == 'end_time'){
                $end_time = $v;
            }
            else if($k == 'callNum'){
                $callNum = $v;
            }
            else if($k == 'select_type'){
                $select_type = $v;
            }

        }
        if(empty($username)){
            $id = $uid;
        }else{
            $id = Db::name('admin')->where('username',$username)->value('id');
        }

        $table_name = get_order_table_name($select_type);

        $datas = Db::name($table_name)->alias('o')->field('o.*,a.username')->join('admin a','o.owner = a.id','LEFT')->where('o.owner', $id);
        $count = Db::name($table_name)->alias('o')->where('o.owner', $id);

        if(!empty($linename)){
            $datas = $datas->where('o.call_phone_id','=',$linename);
            $count = $count->where('o.call_phone_id','=',$linename);
        }
        if(!empty($asrname)){
            $datas = $datas->where('o.asr_id','=',$asrname);
            $count = $count->where('o.asr_id','=',$asrname);
        }
        if(!empty($smsname)){
            $datas = $datas->where('o.sms_channel_id','=',$smsname);
            $count = $count->where('o.sms_channel_id','=',$smsname);

        }
        if(!empty($start_time)){
            $datas = $datas->where('o.create_time', '>=', strtotime($start_time));
            $count = $count->where('o.create_time', '>=', strtotime($start_time));
        }
        if(!empty($end_time)){
            //当天23:59:59
            $end_time =  date('Y-m-d 23:59:59', strtotime($end_time));
            $datas = $datas->where('o.create_time', '<=', strtotime($end_time));
            $count = $count->where('o.create_time', '<=', strtotime($end_time));
        }
        if(!empty($callNum)){
            $datas = $datas->where('o.mobile', 'like', '%'.$callNum.'%');
            $count = $count->where('o.mobile', 'like', '%'.$callNum.'%');
        }
        $datas = $datas->page($page,$limit)
            ->order('o.create_time', 'desc')
            ->select();
        $count = $count->count('1');
        //查找所有的tel_line
        $new_tel_line_table = Db::name('tel_line_group')->field('id,name')->select();
        if($new_tel_line_table)
        {
            $new_tel_line_table_ = [];
            foreach ($new_tel_line_table as $key=>$val){
                $new_tel_line_table_[$val['id']] = $val;
            }
            $tel_line_ids = array_keys($new_tel_line_table_);
        }

        //查找所有的tel_interface
        $new_tel_interface_table = Db::name('tel_interface')->field('id,name')->select();
        if($new_tel_interface_table)
        {
            $new_tel_interface_table_ = [];
            foreach ($new_tel_interface_table as $key=>$val){
                $new_tel_interface_table_[$val['id']] = $val;
            }
            $tel_interface_ids = array_keys($new_tel_interface_table_);
        }

        //查找所有的sms_channel
        $new_sms_channel_table = Db::name('sms_channel')->field('id,name')->select();
        if($new_sms_channel_table)
        {
            $new_sms_channel_table_ = [];
            foreach ($new_sms_channel_table as $key=>$val){
                $new_sms_channel_table_[$val['id']] = $val;
            }
            $sms_channel_ids = array_keys($new_sms_channel_table_);
        }else{
			$sms_channel_ids =[];
		}

        foreach ($datas as $key => $value) {
            //序号
            $datas[$key]['sequence'] = ($page-1)*$limit+($key+1);

            if(in_array($value['call_phone_id'],$tel_line_ids))
            {
                $datas[$key]['linename'] = $new_tel_line_table_[$value['call_phone_id']]['name'];
            }else{
                $datas[$key]['linename'] = "暂无";
            }

            if(in_array($value['asr_id'],$tel_interface_ids))
            {
                $datas[$key]['asrname'] = $new_tel_interface_table_[$value['asr_id']]['name'];
            }else{
                $datas[$key]['asrname'] = "暂无";
            }

            if(in_array($value['sms_channel_id'],$sms_channel_ids))
            {
                $datas[$key]['smsname'] = $new_sms_channel_table_[$value['sms_channel_id']]['name'];
            }else{
                $datas[$key]['smsname'] = "暂无";
            }


            if(empty($value['mobile'])){
                $datas[$key]['mobile'] = "暂无";
            }

            //时间
            if(empty($value['create_time'])){
                $datas[$key]['create_time'] = "暂无拨打时间";
            }else {
                $datas[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
            }

            $datas[$key]['mobile'] = hide_phone_middle($datas[$key]['mobile']);
        }

        unset($new_tel_line_table,$new_tel_line_table_,$tel_interface_ids);
        unset($new_tel_interface_table,$new_tel_interface_table_,$sms_channel_ids);
        unset($new_sms_channel_table,$new_sms_channel_table_,$sms_channel_ids);
        $list = ['list'=>$datas,'count'=>$count,'type'=>'details','page'=>$page];
        return $list;


    }


	/**
	 * 汇总用户消费明细
	 * @param int $uid
	 * @param int $page
	 * @param int $limit
	 * @param string $linename
	 * @param string $asrname
	 * @param string $smsname
	 * @param string $start_time 开始时间
	 * @param string $end_tiem 结束时间
	 * @param string $callNum 被叫号码
	 * @return Json
	*/
	public function get_allconsumption_statistics($uid,$args)
	{
		foreach($args as $k=>$v){
			if($k == 'page'){
				$page = $v;
			}
			else if($k == 'limit'){
				$limit = $v;
			}
			else if($k == 'usertype'){
				$usertype = $v;
			}
			else if($k == 'username'){
				$username = $v;
			}
			else if($k == 'linename'){
				$linename = $v;
			}
			else if($k == 'asrname'){
				$asrname = $v;
			}
			else if($k == 'smsname'){
				$smsname = $v;
			}
			else if($k == 'start_time'){
				$start_time = $v;
			}
			else if($k == 'end_time'){
				$end_time = $v;
			}
			else if($k == 'callNum'){
				$callNum = $v;
			}
			else if($k == 'select_type'){
				$select_type = $v;
			}
		}
		//获取所有id
		if($usertype == '全部类型'){
			$ids = $this->get_find_member_ids($uid);
		}else{
			$ids = $this->get_find_member_ids($uid,$usertype);
		}

        $table_name = get_order_table_name($select_type);

        $datas = Db::name($table_name)->alias('o')->force('index_owner_line_asr_sms_create_time_mobile')->field('o.*,a.username')->join('admin a','o.owner = a.id','LEFT')->where('o.owner','in', $ids);
        $count = Db::name($table_name)->alias('o')->where('o.owner','in', $ids);

        if(!empty($linename)){
            $datas = $datas->where('o.call_phone_id','=',$linename);
            $count = $count->where('o.call_phone_id','=',$linename);
        }
        if(!empty($asrname)){
            $datas = $datas->where('o.asr_id','=',$asrname);
            $count = $count->where('o.asr_id','=',$asrname);
        }
        if(!empty($smsname)){
            $datas = $datas->where('o.sms_channel_id','=',$smsname);
            $count = $count->where('o.sms_channel_id','=',$smsname);

        }
        if(!empty($start_time)){
            $datas = $datas->where('o.create_time', '>=', strtotime($start_time));
            $count = $count->where('o.create_time', '>=', strtotime($start_time));
        }
        if(!empty($end_time)){
            //当天23:59:59
            $end_time =  date('Y-m-d 23:59:59', strtotime($end_time));
            $datas = $datas->where('o.create_time', '<=', strtotime($end_time));
            $count = $count->where('o.create_time', '<=', strtotime($end_time));
        }
        if(!empty($callNum)){
            $datas = $datas->where('o.mobile', 'like', '%'.$callNum.'%');
            $count = $count->where('o.mobile', 'like', '%'.$callNum.'%');
        }


		$datas = $datas->page($page,$limit)
									 ->order('o.create_time', 'desc')
									 ->select();
		$count = $count->count(1);

        //查找所有的tel_line
        $new_tel_line_table = Db::name('tel_line_group')->field('id,name')->select();
        if($new_tel_line_table)
        {
            $new_tel_line_table_ = [];
            foreach ($new_tel_line_table as $key=>$val){
                $new_tel_line_table_[$val['id']] = $val;
            }
            $tel_line_ids = array_keys($new_tel_line_table_);
        }

        //查找所有的tel_interface
        $new_tel_interface_table = Db::name('tel_interface')->field('id,name')->select();
        if($new_tel_interface_table)
        {
            $new_tel_interface_table_ = [];
            foreach ($new_tel_interface_table as $key=>$val){
                $new_tel_interface_table_[$val['id']] = $val;
            }
            $tel_interface_ids = array_keys($new_tel_interface_table_);
        }

        //查找所有的sms_channel
        $new_sms_channel_table = Db::name('sms_channel')->field('id,name')->select();
        if($new_sms_channel_table)
        {
            $new_sms_channel_table_ = [];
            foreach ($new_sms_channel_table as $key=>$val){
                $new_sms_channel_table_[$val['id']] = $val;
            }
            $sms_channel_ids = array_keys($new_sms_channel_table_);
        }else{
			$sms_channel_ids =[];
		}


		foreach ($datas as $key => $value) {
			//序号
			$datas[$key]['sequence'] = ($page-1)*$limit+($key+1);
			if(empty($value['mobile'])){
				$datas[$key]['mobile'] = "暂无拨打";
			}

      if(in_array($value['call_phone_id'],$tel_line_ids)){
        $datas[$key]['linename'] = $new_tel_line_table_[$value['call_phone_id']]['name'];
      }else if(!empty($value['call_phone_id'])){
        $datas[$key]['linename'] = '线路已删除';
      }else{
        $datas[$key]['linename'] = "暂无";
      }

      if(in_array($value['asr_id'],$tel_interface_ids)){
        $datas[$key]['asrname'] = $new_tel_interface_table_[$value['asr_id']]['name'];
      }else if(!empty($value['asr_id'])){
        $datas[$key]['asrname'] = 'asr已删除';
      }else{
        $datas[$key]['asrname'] = "暂无";
      }

      if(in_array($value['sms_channel_id'],$sms_channel_ids)){
        $datas[$key]['smsname'] = $new_sms_channel_table_[$value['sms_channel_id']]['name'];
      }else if(!empty($value['sms_channel_id'])){
        $datas[$key]['smsname'] = '短信通道已删除';
      }else{
        $datas[$key]['smsname'] = "暂无";
      }
			//时间
			if(empty($value['create_time'])){
				$datas[$key]['create_time'] = "暂无拨打时间";
			}else {
				$datas[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
			}

            $datas[$key]['mobile'] = hide_phone_middle($datas[$key]['mobile']);

		}
		$list = ['list'=>$datas,'count'=>$count,'type'=>'details','page'=>$page];
		return $list;

	}


	/*
  * 过滤二维数组中重复的元素
  * @param array $array_data
  * @return array $temp
  */
  function arrunique($array_data){
    foreach ($array_data as $v){
      $v=join(',',$v); //降维,也可以用implode,将一维数组转换为用逗号连接的字符串
      $temp[]=$v;
     }
     $temp=array_unique($temp); //去掉重复的字符串,也就是重复的一维数组
     foreach ($temp as $k => $v){
      $temp[$k]=explode(',',$v); //再将拆开的数组重新组装
     }
     return $temp;
  }

  /**
	 * 汇总呼叫次数
	 *
	 * @param int $member_id
	 * @param string $type
	 * @param array $args
	 * @return int
	*/
	public function summary_call_count($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}

		$count = $count->sum('call_count');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

	/**
	 * 汇总接通次数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_connect_count($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('connect_count');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

	/**
	 * 汇总计费时长
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_duration_count($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('charging_duration');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

	/**
	 * 汇总通话时长
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_connect_duration($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$sum = Db::name('tel_order');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$sum = $sum->where('create_time', '>=', $value);
			}else if($key === 'end_time'){
				$sum = $sum->where('create_time', '<=', $value);
			}else if($key === 'member_id'){
				$sum = $sum->where('owner', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}
		$sum = $sum->sum('duration');

		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 汇总识别次数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function summary_asr_count($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('asr_count');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

	/**
	 * 汇总发送短信次数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_sms_count($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('send_sms_count');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

	/**
	 * 汇总短信费用
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_sms_cost($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('sms_cost');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

	/**
	 * 汇总机器人费用
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_robot_cost($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('robot_cost');
		if(empty($count)){
			return 0.000;
		}
		return $count;
	}

	/**
	 * 汇总接通费用
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_connect_cost($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('connect_cost');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

	/**
	 * 汇总识别费用
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_asr_cost($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$count = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$count = $count->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$count = $count->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$count = $count->where('member_id', $value);
			}else if($key === 'type'){
				$count = $count->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('asr_cost');
		if(empty($count)){
			return 0;
		}
		return $count;
	}

		/**
	 * 汇总消费金额
	 *
	 * @param int $member_id
	 * @param array $args 筛选参数
	 * @return float
	*/
	public function summary_consumption($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$money = Db::name('consumption_statistics');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$money = $money->where('date', '>=', $value);
			}else if($key === 'end_time'){
				$money = $money->where('date', '<=', $value);
			}else if($key === 'member_id'){
				$money = $money->where('member_id', '=', $value);
			}else if($key === 'type'){
				$money = $money->where('type', '=', $value);
			}
		}
		//获取所有id
		$ids = $this->get_userall_id($member_id,$type);

		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$money = $money->where('member_id', 'in', $ids);
		}
		$money = $money->sum('total_cost');
		if(empty($money)){
			return 0;
		}
		return $money;
	}



	public function ajaxsale_account_recharge(){
		$user_name = input('username','','trim,strip_tags');
		$start_date = input('start_date','','trim,strip_tags');
		$end_date = input('end_date','','trim,strip_tags');
		$user_auth = session('user_auth');
		$uid = $user_auth['uid'];
		$page = input('page','','trim,strip_tags');
		$page_size = input('page_size','','trim,strip_tags');
		$where = array();
		if($user_name){
			$where_a['username'] = array('like',"%".$sotitle."%");
		}
		$a_id = Db::name('admin')->where($where_a)->column('id');
		if($a_id){
			$where['recharge_member_id'] =array('in',$a_id);
		}
		if($start_date !='' && $end_date !=''){
			$where['create_time'] = array(array('ge',$start_date),array('le',$end_date),'and');
		}
		$list = Db::name('tel_deposit')->where($where)->page($page,$Page_size)->select();
		$count = count($list);
		$page_count = ceil($count/$Page_size);

		$data = array();
		$data['list'] = $list; //数据
		$data['total'] = $count; //总条数
		$data['page'] = $page_count; //总页数
		$data['Nowpage'] = $page; //当前页数
		return returnAjax(1,'获取数据成功',$data);
	}
	public function sale_record(){
		if(request()->isPost()){
			$data = [];
			$user_auth = session('user_auth');
			$uid = $user_auth['uid'];
			$user_id= input('user_id','','trim,strip_tags');//被充值ID；
			$data['recharge_member_id'] = $user_id;
			$data['owner'] = $uid; //充值ID
			$data['menoy'] = input('menoy','','trim,strip_tags'); //充值金额
			$defore_balance = Db::name('tel_deposit')->where('recharge_member_id',$user_id)->value('balance');//充值前金额
			$data['balance'] = $defore_balance + $data['menoy'];//充值后金额 = 充值金额 + 充值前金额（数据库充值后金额）
			$data['create_time'] = time(); //充值时间
			$data['remak'] = input('remak','','trim,strip_tags');//备注
			$res = Db::name('tel_deposit')->insert();
			if($res){
				return returnAjax(0,'充值成功');
			}
		}
	}

  /**
   * 获取账户概况的数据
   *
   * @param string $role_name 角色名称
   * @param int $user_id 查询的用户用户
   * @return array
  */
  public function get_user_survey_data($role_name, $username)
  {
    $result = [
      'username'  =>  '',
      'money' =>  0,
      'role_name' =>  '',
      'robot_cnt' =>  '',
      'end_time'  =>  '',
      'rate_info' =>  [
        'line_info'  =>  [],
        'asr_info' =>  [],
        'sms_info' =>  [],
        'robot_info'  =>  []
      ]
    ];
    if(empty($role_name)){
      return $result;
    }




    //获取当前用户
    $user_auth = session('user_auth');

    if($username != '全部类型'){
      $user_id = Db::name('admin')->where(['username'=> $username])->value('id');
    }

    //验证
    if(!empty($user_id) && $user_id != $user_auth['uid'] && $user_auth['role'] != '管理员'){
      $find_user_ids = Db::name('admin')->where('pid', $user_auth['uid'])->column('id');
      if(in_array($user_id, $find_user_ids) == false){
        return $result;
      }
    }


    //管理员和其他用户角色的查询的方式不一样
    if($user_auth['role'] == '管理员'){

      if(!empty($user_id)){
        //查个人

        $user_data = Db::name('admin')
                      ->alias('a')
                      ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                      ->where('a.id', $user_id)
                      ->field('a.username, ar.name as role_name, a.money, a.robot_cnt, a.month_price, a.type_price, a.robot_date')
                      ->find();
        if($user_data['role_name'] == '商家'){
          $user_data['money'] += Db::name('admin')->where('pid', $user_id)->sum('money');
        }
        $line_datas = Db::name('tel_line_group')->field('name,sales_price')->where('user_id', $user_id)->select();
        $asr_datas = Db::name('tel_interface')->field('name,sale_price')->where('owner', $user_id)->select();
        $sms_datas = Db::name('sms_channel')->field('name,price')->where('owner', $user_id)->select();



        $result['username'] = $user_data['username'];
        $result['money'] = $user_data['money'];
        $result['role_name'] = $user_data['role_name'];
        $result['rate_info']['line_info'] =  $line_datas;
        $result['rate_info']['asr_info']  =  $asr_datas;
        $result['rate_info']['sms_info']  =  $sms_datas;
        $result['robot_cnt'] =  $user_data['robot_cnt'];
        $result['end_time'] = date('Y-m-d', $user_data['robot_date']);


        $result['rate_info']['robot_info'][0] = $this->get_robot_rate($user_id);



      }else{

        //查一个层级
        $where = [
          'ar.name' =>  $role_name
        ];
        if($role_name == '商家'){
          $user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where($where)->column('a.id');
          $user_data = Db::name('admin')
                      ->alias('a')
                      ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                      ->where('a.id', 'in', $user_ids)
                      ->whereOr('a.pid', 'in', $user_ids)
                      ->field('sum(a.money) as money, sum(a.robot_cnt) as robot_cnt, sum(a.month_price) as month_price, a.type_price')
                      ->find();
        }else{
          $user_data = Db::name('admin')
                      ->alias('a')
                      ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                      ->where($where)
                      ->field('sum(a.money) as money, sum(a.robot_cnt) as robot_cnt, sum(a.month_price) as month_price, a.type_price')
                      ->find();
        }
        //获取当前用户在这个层级创建的所有用户ID
        $user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where($where)->column('a.id');
        $line_datas = Db::name('tel_line_group')->field('name,sales_price')->where('user_id', 'in', $user_ids)->select();
        $asr_datas = Db::name('tel_interface')->field('name,sale_price')->where('owner', 'in', $user_ids)->select();
        $sms_datas = Db::name('sms_channel')->field('name,price')->where('owner', 'in', $user_ids)->select();



        $result['username'] = $role_name;
        $result['money'] = $user_data['money'];
        $result['role_name'] = $role_name;
        $result['rate_info']['line_info'] =  $line_datas;
        $result['rate_info']['asr_info']  =  $asr_datas;
        $result['rate_info']['sms_info']  =  $sms_datas;
        $result['robot_cnt'] =  $user_data['robot_cnt'];
        $result['end_time'] = '';
        $result['rate_info']['robot_info'][0] = $this->get_robot_rate($user_ids);

      }

    }else{

      if(!empty($user_id)){
        //查个人

        $user_data = Db::name('admin')
                      ->alias('a')
                      ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                      ->where('a.id', $user_id)
                      ->field('a.username, ar.name as role_name, a.money, a.robot_cnt, a.month_price, a.type_price, a.robot_date')
                      ->find();

        if($user_data['role_name'] == '商家'){
          $user_data['business_money'] = Db::name('admin')
                                ->where('id', $user_id)
                                ->value('money');
          $find_user_money = Db::name('admin')->where('pid', $user_id)->sum('money');
          $user_data['money'] = $user_data['business_money'] + $find_user_money;
          
          $result['is_business'] = 1;
          $result['business_money'] = $user_data['business_money'];
        }

        $line_datas = Db::name('tel_line_group')->field('name,sales_price')->where('user_id', $user_id)->select();
        $asr_datas = Db::name('tel_interface')->field('name,sale_price')->where('owner', $user_id)->select();
        $sms_datas = Db::name('sms_channel')->field('name,price')->where('owner', $user_id)->select();


        
        $result['username'] = $user_data['username'];
        $result['money'] = $user_data['money'];
        $result['role_name'] = $user_data['role_name'];
        $result['rate_info']['line_info'] =  $line_datas;
        $result['rate_info']['asr_info']  =  $asr_datas;
        $result['rate_info']['sms_info']  =  $sms_datas;
        $result['robot_cnt'] =  $user_data['robot_cnt'];
        $result['end_time'] = date('Y-m-d', $user_data['robot_date']);
        $result['rate_info']['robot_info'][0] = $this->get_robot_rate($user_id);



      }else{
        //查一个层级
        $where = [
          'a.pid' =>  $user_auth['uid'],
          'ar.name' =>  $role_name
        ];
        if($user_auth['role'] == '商家'){
          $user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where($where)->column('a.id');
          $user_data = Db::name('admin')
                      ->alias('a')
                      ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                      ->where('a.id', 'in', $user_ids)
                      ->whereOr('a.pid', 'in', $user_ids)
                      ->field('sum(a.money) as money, sum(a.robot_cnt) as robot_cnt, sum(a.month_price) as month_price, a.type_price')
                      ->find();
        }else{
          $user_data = Db::name('admin')
                      ->alias('a')
                      ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                      ->where($where)
                      ->field('sum(a.money) as money, sum(a.robot_cnt) as robot_cnt, sum(a.month_price) as month_price, a.type_price')
                      ->find();
        }

        \think\Log::record('$user_data&&&&&'.json_encode($user_data));
        // getLastSql
        \think\Log::record('$user_data_sql&&&&&'.Db::name('admin')->getLastSql());
        //获取当前用户在这个层级创建的所有用户ID
        $user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where($where)->column('a.id');


        $line_datas = Db::name('tel_line_group')->field('name,sales_price')->where('user_id', 'in', $user_ids)->select();
        $asr_datas = Db::name('tel_interface')->field('name,sale_price')->where('owner', 'in', $user_ids)->select();
        $sms_datas = Db::name('sms_channel')->field('name,price')->where('owner', 'in', $user_ids)->select();

        $result['username'] = $role_name;
        $result['money'] = $user_data['money'];
        $result['role_name'] = $role_name;
        $result['rate_info']['line_info'] =  $line_datas;
        $result['rate_info']['asr_info']  =  $asr_datas;
        $result['rate_info']['sms_info']  =  $sms_datas;
        $result['robot_cnt'] =  $user_data['robot_cnt'];
        $result['end_time'] = '';
        $result['rate_info']['robot_info'][0] = $this->get_robot_rate($user_ids);
      }
    }
    $result['today'] = $this->get_day_consumption($role_name, $user_id, date('Y-m-d'));
    $result['yesterday'] = $this->get_day_consumption($role_name, $user_id, date('Y-m-d', strtotime('-1 day')));
    return $result;
  }

  /**
   * 获取财务概况日消费统计
   *
   * @param string $role_name 用户角色
   * @param int $user_id 用户ID
   * @param date 日期
   * @return array
  */
  public function get_day_consumption($role_name, $user_id, $date)
  {
    if(empty($role_name) || empty($date)){
      return [];
    }

    //获取当前用户的数据
    $user_auth = session('user_auth');
    if(empty($role_name) && $user_auth['role'] != '管理员'){

      $where = [
        'pid' =>  $user_auth['uid'],
        'id'  =>  $user_id
      ];
      $count = Db::name('admin')->where($where)->count('id');
      if(empty($count)){
        return [];
      }
    }

    //今日
    if($date == date('Y-m-d')){
      $time = strtotime($date);
      if(!empty($user_id)){

        //如果要查询的用户是商家的话 需要将商家所创建的销售人员(不包括记账的销售人员)一起带上
        if($role_name == '商家'){
          $where = [
            'a.pid' =>  $user_id,
            'ar.name' =>  '销售人员',
            'a.is_jizhang'  =>  0
          ];
          $find_user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where($where)->column('a.id');
          $where = [
            'a.pid' =>  $user_id,
            'ar.name' =>  '销售人员',
          ];
          $all_find_user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where($where)->column('a.id');
        }else{
          $find_user_ids = [];
          $all_find_user_ids = [];
        }
        $all_find_user_ids[] = $user_id;
        $user_ids = [$user_id];
        $user_ids = array_merge($user_ids, $find_user_ids);
      }else{

        if($role_name == '商家'){
          if($user_auth['role'] == '管理员'){
            $where = [
              'ar.name' => $role_name
            ];
          }else{
            $where = [
              'ar.name' => $role_name,
              'a.pid' => $user_auth['uid']
            ];
          }
          $user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')->where($where)->column('a.id');
          $find_user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where('a.pid', 'in', $user_ids)->where('is_jizhang', 0)->column('a.id');
          $all_find_user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where('a.pid', 'in', $user_ids)->column('a.id');
          $all_find_user_ids = array_merge($all_find_user_ids, $user_ids);
          $user_ids = array_merge($user_ids, $find_user_ids);
        }else{
          if($user_auth['role'] == '管理员'){
            $where = [
              'ar.name' =>  $role_name
            ];
          }else{
            $where = [
              'ar.name' =>  $role_name,
              'a.pid' =>  $user_auth['uid']
            ];
          }
          $user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')->where($where)->column('a.id');
          $all_find_user_ids = $user_ids;
        }
      }

      $redis = RedisConnect::get_redis_connect();
      $connected_numbers = $unconnected_numbers = $sum_charging_duration = $sum_duration = $sum_asr_cnt = $sum_sms_count = $sum_call_money = $sum_asr_money = $sum_sms_money = $sum_technology_service_cost = $sum_money = 0;
      //遍历收集到的这些用户id 并 将对应的数据累加在一起
      foreach($user_ids as $key=>$value){
        //key name
        $incr_key_charging_duration =  "incr_owner_".$value."_".$time."_charging_duration";
        $incr_key_duration = "incr_owner_".$value."_".$time."_duration";
        $incr_key_asr_cnt = "incr_owner_".$value."_".$time."_asr_cnt";
        $incr_key_sms_count = "incr_owner_".$value."_".$time."_sms_count";
        $incr_key_call_money = "incr_owner_".$value."_".$time."_call_money";
        $incr_key_asr_money = "incr_owner_".$value."_".$time."_asr_money";
        $incr_key_sms_money = "incr_owner_".$value."_".$time."_sms_money";
        $incr_key_technology_service_cost = "incr_owner_".$value."_".$time."_technology_service_cost";
        $incr_key_money = "incr_owner_".$value."_".$time."_money";
        $incr_key_connected_numbers= "incr_owner_".$value."_".$time."_charging_connected_numbers";
        $incr_key_unconnect_count = "incr_owner_".$value."_".$time."_charging_unconnect_count";

        $sum_charging_duration  += $redis->get($incr_key_charging_duration);
        $sum_duration  += $redis->get($incr_key_duration);
        $sum_asr_cnt   += $redis->get($incr_key_asr_cnt);
        $sum_sms_count += $redis->get($incr_key_sms_count);
        $sum_call_money += $redis->get($incr_key_call_money);
        $sum_asr_money += $redis->get($incr_key_asr_money);
        $sum_sms_money += $redis->get($incr_key_sms_money);
        $sum_technology_service_cost += $redis->get($incr_key_technology_service_cost);
        $sum_money += $redis->get($incr_key_money);
        //接通次数 未接通号码数
        $connected_numbers += $redis->get($incr_key_connected_numbers);
        $unconnected_numbers += $redis->get($incr_key_unconnect_count);
      }

      //整理数据
      $data = [];
      $data['call_count'] = ($connected_numbers + $unconnected_numbers);
      $data['connect_count'] = $connected_numbers;
      //接通率
      if(empty($data['call_count']) || empty($data['connect_count'])){
        $data['connect_rate'] = 0;
      }else{
        $data['connect_rate'] = round(($data['connect_count']/$data['call_count']),2)*100;
      }
      //平均通话时长
      if(empty($sum_duration) || empty($data['connect_count'])){
        $data['average_duration'] = 0;
      }else{
        $data['average_duration'] = ceil($sum_duration / $data['connect_count']);
      }
      $data['connect_cost'] = $sum_call_money;
      $data['asr_cost'] = $sum_asr_money;
      //机器人费用
      $where = [
        'date'  =>  $date,
        'member_id' =>  ['in', $all_find_user_ids]
      ];
      $data['robot_cost'] = Db::name('robot_cost_statistics')->where($where)->sum('cost');
      $data['sms_cost'] = $sum_sms_money;
      $data['technology_service_cost'] = $sum_technology_service_cost;
      $data['total_cost'] = $sum_money + $data['robot_cost'];
      $data['charging_duration'] = $sum_charging_duration;



    //历史
    }else{

      if(!empty($user_id)){
        $where = [
          'type'  =>  'day',
          'member_id' =>  $user_id,
          'date'  =>  strtotime($date)
        ];

        $data = Db::name('consumption_statistics')
                ->where($where)
                ->field('call_count, connect_count, connect_rate, average_duration, connect_cost, asr_cost, robot_cost, sms_cost, charging_duration, technology_service_cost, total_cost')
                ->find();
      }else{
        //查询指定层级的所有用户
        if($user_auth['role'] == '管理员'){
          $where = [
            'ar.name' =>  $role_name,
            'a.is_jizhang'  =>  0
          ];
        }else{
          $where = [
            'a.pid' =>  $user_auth['uid'],
            'ar.name' =>  $role_name,
            'a.is_jizhang'  =>  0
          ];
        }
        $find_user_ids = Db::name('admin')->alias('a')->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')->where($where)->column('a.id');


        //查询上面查询出来的用户对应的日消费 并 将这些用户的日消费进行合并
        $where = [
          'type'  =>  'day',
          'member_id' =>  ['in', $find_user_ids],
          'date'  =>  strtotime($date)
        ];
        $data = Db::name('consumption_statistics')
                ->where($where)
                ->field('
                  sum(call_count) as call_count,
                  sum(connect_count) as connect_count,
                  sum(connect_cost) as connect_cost,
                  sum(asr_cost) as asr_cost,
                  sum(sms_cost) as sms_cost,
                  sum(robot_cost) as robot_cost,
                  sum(technology_service_cost) as technology_service_cost,
                  sum(charging_duration) as charging_duration,
                  sum(total_cost) as total_cost,
                  sum(duration) as duration
                  ')
                ->find();
        if(empty($data['connect_count']) || empty($data['call_count'])){
          $data['connect_rate'] = 0;
        }else{
          $data['connect_rate'] = round(($data['connect_count']/$data['call_count']),2)*100;
        }

        if(empty($data['connect_count']) || empty($data['duration'])){
          $data['average_duration'] = 0;
        }else{
          $data['average_duration'] = ceil($data['duration'] / $data['connect_count']);
        }

      }

    }

    return $data;

  }





}
