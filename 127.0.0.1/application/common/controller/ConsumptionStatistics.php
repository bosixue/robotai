<?php
namespace app\common\controller;

use think\Db;
use app\common\controller\Log;
use app\common\controller\AdminData;
//财务管理数据处理类
use app\common\controller\EnterpriseData;

//消费统计处理类
class ConsumptionStatistics extends Base{

	// /**
	// * 写入
	// *
	// * @param int $member_id 用户
	// * @param int $call_count 呼叫次数
	// * @param int $charging_duration 计费时长(分钟)
	// * @param int $connect_duration 通话时长
	// * @param float $money 金额
	// * @param int $distinguish_count 识别次数
	// * @retrun bool
	// */
	// public function insert($member_id, $call_count, $charging_duration, $connect_count, $connect_duration, $money, $distinguish_count)
	// {
	// 	if(empty($member_id)){
	// 		return false;
	// 	}
	// 	if(empty($money)){
	// 		$money = 0;
	// 	}
	// 	if(empty($distinguish_count)){
	// 		$distinguish_count = 0;
	// 	}
	// 	if(empty($call_count)){
	// 		$call_count = 0;
	// 	}
	// 	if(empty($charging_duration)){
	// 		$charging_duration = 0;
	// 	}
	// 	if(empty($connect_duration)){
	// 		$connect_duration = 0;
	// 	}
	// 	if(empty($connect_count)){
	// 		$connect_count = 0;
	// 	}
	// 	$create_time = time();
	// 	$result = Db::name('consumption_statistics')
	// 						->insert([
	// 							'date'	=>	date("Y-m-d", strtotime('-1 day')),
	// 							'member_id'	=>	$member_id,
	// 							'call_count' => $call_count,
	// 							'charging_duration'	=>	$charging_duration,
	// 							'connect_count'	=>	$connect_count,
	// 							'connect_duration'	=>	$connect_duration,
	// 							'money'	=>	$money,
	// 							'distinguish_count' => $distinguish_count,
	// 							'create_time'	=>	$create_time
	// 						]);
	// 	if(!empty($result)){
	// 		return true;
	// 	}
	// 	return false;
	// }

	/**
	 * 统计指定日期内所有用户的消费
	 *
	 * @param time $date 日期
	 * @return bool
	*/
	public function statistics($date)
	{
	  if(empty($date)){
	    return false;
	  }

	  //1.获取所有用户的数据
	  $user_data = Db::name('admin')
	                ->alias('a')
	                ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
									->field('a.id,a.money,ar.name as role_name')
									->select();


		//2.获取当前要统计的起始时间和结束时间 比如要统计的是2019-06-06那么起始时间和结束时间分别是: 2019-06-06 - 2019-06-07
		$time = strtotime($date);
		$start_time = $time;
		$end_time = $time + 24 * 3600;
		$start_date = date('Y-m-d', $start_time);
		$end_date = date('Y-m-d', $end_time);


		//3.选择表名
		$table_name = 'tel_order_'. date('Ymd', $time);
    if(date('Y-m-d') == $date){
      $admin_table_name = 'admin';
    }else{
      $admin_table_name = 'admin_'.date('Ymd', strtotime($date));
    }

		//4.遍历所有用户进行逐个统计
		foreach ($user_data as $key => $value) {


		  //判断是否已生成
		  $sum = Db::name('consumption_statistics')->where(['type'=>'day','date'=>$start_time,'member_id'=>$value['id']])->count('*');
		  if($sum > 0){
		     continue;
		  }


		  //由于商家和其他角色统计方式不一样 所以在这里区分一下


		  //商家设置筛选条件 => 自己的 和 自己创建的销售人员(不包含状态为"记账"的销售人员)   |  其他角色设置筛选条件 => 自己的
		  if($value['role_name'] == '商家'){



		    //4.0.获取该商家的子账户(销售人员)
		    $find_user_ids = Db::name('admin')->where('pid', $value['id'])->column('id');

		    //4.1.拨打次数
		    $where = [ //自己的
		      'owner' =>  ['=', $value['id']],
		      'create_time' =>  ['between', [$start_time, $end_time]],
					'type'	=>	['<>', 2]
		    ];
		    $whereOr = [ //自己创建的销售人员(不包含状态为"记账"的销售人员)
		      'owner' =>  ['in', $find_user_ids],
		      'is_jizhang'  =>  ['=', 0],
		      'member_id' =>  $value['id'],
		      'create_time' =>  ['between', [$start_time, $end_time]],
					'type'	=>	['<>', 2]
		    ];
		    //'id',['>',0],['<>',10],'or'
		    $call_count = Db::name($table_name)->where($where)->whereOr(function($query) use($whereOr){
		      $query->where($whereOr);
		    })->count('id');

		    //4.2.接通次数
		    $where = [
		      'owner' =>  ['=', $value['id']],
		      'duration'  =>  ['>', 0],
		      'create_time' =>  ['between', [$start_time, $end_time]]
		    ];
		    $whereOr = [
		      'owner' =>  ['in', $find_user_ids],
		      'is_jizhang'  =>  ['=', 0],
		      'duration'  =>  ['>', 0],
		      'create_time' =>  ['between', [$start_time, $end_time]]
		    ];
		    $connect_count = Db::name($table_name)->where($where)->whereOr(function($query) use($whereOr){
		      $query->where($whereOr);
		    })->count('id');

		    //4.3.asr识别次数,asr识别费用,短信发送次数,短信费用,通话费用,技术服务费,总费用,计费时长和通话时长
		    $where = [ //自己的
		      'owner' =>  ['=', $value['id']],
		      'create_time' =>  ['between', [$start_time, $end_time]]
		    ];
		    $whereOr = [ //自己创建的销售人员(不包含状态为"记账"的销售人员)
		      'owner' =>  ['in', $find_user_ids],
		      'is_jizhang'  =>  ['=', 0],
		      'create_time' =>  ['between', [$start_time, $end_time]]
		    ];
		    $data = Db::name($table_name)->where($where)->whereOr(function($query) use($whereOr){
		      $query->where($whereOr);
		    })->field('sum(asr_cnt) as asr_cnt, sum(asr_money) as asr_money, sum(sms_count) as sms_count, sum(sms_money) as sms_money, sum(call_money) as call_money, sum(technology_service_cost) as technology_service_cost, sum(money) as money, sum(ceil(duration / 60)) as charging_duration, sum(duration) as duration')->find();

		    //4.4.获取机器人费用
	      $where = [
	        'date'  =>  $date,
	        'member_id' =>  $value['id'],
	      ];
	      $whereOr = [
	        'date'  =>  ['=', $date],
	        'member_id' =>  ['in', $find_user_ids]
	      ];
	      $robot_cost = Db::name('robot_cost_statistics')->where($where)->whereOr(function($query) use($whereOr){
		      $query->where($whereOr);
		    })->sum('cost');

		    //4.5.获取余额
		    $where = [
		      'id'  =>  $value['id']
		    ];
		    $whereOr = [
		      'id'  =>  ['in', $find_user_ids]
		    ];

		    $money = Db::name($admin_table_name)->where($where)->whereOr(function($query) use($whereOr){
		      $query->where($whereOr);
		    })->sum('money');
        if(empty($money)){
          $money = 0;
        }

		  }else{




		    //4.1.拨打次数
		    $where = [ //自己的
		      'owner' =>  ['=', $value['id']],
		      'create_time' =>  ['between', [$start_time, $end_time]],
					'type'	=>	['<>', 2]
		    ];
		    $call_count = Db::name($table_name)->where($where)->count('id');

		    //4.2.接通次数
		    $where = [
		      'owner' =>  ['=', $value['id']],
		      'duration'  =>  ['>', 0],
		      'create_time' =>  ['between', [$start_time, $end_time]],
		    ];
		    $connect_count = Db::name($table_name)->where($where)->count('id');

		    //4.3.asr识别次数,asr识别费用,短信发送次数,短信费用,通话费用,技术服务费,总费用,计费时长和通话时长
		    $where = [ //自己的
		      'owner' =>  ['=', $value['id']],
		      'create_time' =>  ['between', [$start_time, $end_time]]
		    ];

		    $data = Db::name($table_name)->where($where)->field('sum(asr_cnt) as asr_cnt, sum(asr_money) as asr_money, sum(sms_count) as sms_count, sum(sms_money) as sms_money, sum(call_money) as call_money, sum(technology_service_cost) as technology_service_cost, sum(money) as money, sum(ceil(duration / 60)) as charging_duration, sum(duration) as duration')->find();

		    //4.4.获取机器人费用
	      $where = [
	        'date'  =>  $date,
	        'member_id' =>  $value['id'],
	      ];
	      $robot_cost = Db::name('robot_cost_statistics')->where($where)->sum('cost');

	      //4.5.获取余额
		    $where = [
		      'id'  =>  $value['id']
		    ];
		    $money = Db::name($admin_table_name)->where($where)->value('money');
        if(empty($money)){
          $money = 0;
        }

		  }



		  //4.6.计算平均通话时长
		  if(empty($data['duration']) || empty($connect_count)){
		    $average_duration = 0;
		  }else{
	      $average_duration = ceil($data['duration'] / $connect_count);
		  }


	    //4.7.计算接通率
	    if(empty($connect_count) || empty($call_count)){
	      $connect_rate = 0;
	    }else{
	      $connect_rate = round(($connect_count/$call_count),2)*100;
	    }

	    //4.8.整合数据
	    $insert_data = [
	      'type'  =>  'day',
	      'member_id' =>  $value['id'],
	      'date'  =>  $start_time,
	      'call_count'  =>  !empty($call_count)?$call_count:0,
	      'connect_count' =>  !empty($connect_count)?$connect_count:0,
	      'charging_duration' =>  !empty($data['charging_duration'])?$data['charging_duration']:0,
	      'asr_count' =>  !empty($data['asr_cnt'])?$data['asr_cnt']:0,
	      'send_sms_count'  =>  !empty($data['sms_count'])?$data['sms_count']:0,
	      'sms_cost'  =>  !empty($data['sms_money'])?$data['sms_money']:0.000,
	      'robot_cost'  =>  !empty($robot_cost)?$robot_cost:0.000,
	      'connect_cost'  =>  !empty($data['call_money'])?$data['call_money']:0.000,
	      'asr_cost'  =>  !empty($data['asr_money'])?$data['asr_money']:0.0000,
	      'total_cost'  =>  ($data['money'] + $robot_cost),
	      'average_duration'  =>  !empty($average_duration)?$average_duration:0,
	      'connect_rate'  =>  !empty($connect_rate)?$connect_rate:0,
	      'money' =>  $money,
	      'technology_service_cost' =>  !empty($data['technology_service_cost'])?$data['technology_service_cost']:0.00000,
	      'duration'  =>  !empty($data['duration'])?$data['duration']:0
	    ];


		  //4.8.写入统计出来的数据
		  $result = $this->insert($insert_data);

		}
	  return true;
	}

	/**
	 * 写入
	 *
	 * @param int $member_id 用户
	 * @param int $call_count 呼叫次数
	 * @param int $charging_duration 计费时长(分钟)
	 * @param int $connect_duration 通话时长
	 * @param float $money 金额
	 * @param int $distinguish_count 识别次数
	 * @retrun bool
	*/
	public function insert($data)
	{
		if(empty($data['member_id'])){
			return false;
		}
		if(empty($data['date'])){
			return false;
		}
		if(empty($data['type'])){
			$data['type'] = 'day';
		}
		// if(empty($data['call_count'])){
		// 	$data['call_count'] = 0;
		// }
		// if(empty($data['connect_count'])){
		// 	$data['connect_count'] = 0;
		// 	$data['average_duration'] = 0;
		// 	$data['connect_rate'] = 0;
		// }
		// if(empty($data['charging_duration'])){
		// 	$data['charging_duration'] = 0;
		// }
		// if(empty($data['asr_count'])){
		// 	$data['asr_count'] = 0;
		// }
		// if(empty($data['send_sms_count'])){
		// 	$data['send_sms_count'] = 0;
		// }
		if(empty($data['sms_cost'])){
			$data['sms_cost'] = 0.000;
		}
		if(empty($data['robot_cost'])){
			$data['robot_cost'] = 0.000;
		}
		if(empty($data['connect_cost'])){
			$data['connect_cost'] = 0.000;
		}
		if(empty($data['asr_cost'])){
			$data['asr_cost'] = 0.000;
		}
		if(empty($data['total_cost'])){
			$data['total_cost'] = 0.000;
		}

		$result = Db::name('consumption_statistics')
							->insert([
								'type' => $data['type'],
								'member_id'	=>	$data['member_id'],
								'date'	=>	$data['date'],
								'call_count' => $data['call_count'],
								'connect_count'	=>	$data['connect_count'],
								'charging_duration'	=>	$data['charging_duration'],
								'asr_count' => $data['asr_count'],
								'send_sms_count'	=>	$data['send_sms_count'],
								'sms_cost'	=>	$data['sms_cost'],
								'robot_cost' => $data['robot_cost'],
								'connect_cost' => $data['connect_cost'],
								'asr_cost' => $data['asr_cost'],
								'total_cost' => $data['total_cost'],
								'average_duration' => $data['average_duration'],
								'connect_rate'	=>	$data['connect_rate'],
								'money'	=>	$data['money'],
								'technology_service_cost'=>$data['technology_service_cost'],
								'duration'=>$data['duration']
							]);
		if(!empty($result)){
			return true;
		}
		return false;
	}

	/**
	 * 获取数据
	 *
	 * @param int $member_id
	 * @param array $args
	 * @param int $page
	 * @return array
	*/
	public function gets($member_id, $args = [], $page = 1)
	{
		$datas = Db::name('consumption_statistics')
							->field('cs.*,a.username as member_name')
							->alias('cs')
							->join('admin a', 'cs.member_id = a.id', 'LEFT');
		foreach($args as $key=>$value){
			if($key === 'start_time'){
				$datas = $datas->where('cs.date', '>=', $value);
			}else if($key === 'end_time'){
				$datas = $datas->where('cs.date', '<=', $value);
			}else if($key === 'member_name'){
				$datas = $datas->where('a.username', 'like', '%'.$value.'%');
			}
		}
		$AdminData = new AdminData();
		$ids = [];
		$ids[] = $member_id;
		$find_ids = $AdminData->get_find_member_ids($member_id);
		$ids = array_merge($ids, $find_ids);
		$args['member_id'] = ['in', $ids];
		$datas = $datas->where('cs.member_id', 'in', $ids);
		$users = [];
		foreach($ids as $key=>$value){
			$users[$value] = Db::name('admin')
												->alias('a')
												->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
												->field('a.money,a.robot_cnt,ar.name as role_name')
												->where('a.id', $value)
												->find();
		}
		$datas = $datas->page($page.',10')
							->order('cs.create_time', 'desc')
							->select();
		foreach($datas as $key=>$value){
			//角色名称
			$datas[$key]['role_name'] = $users[$value['member_id']]['role_name'];
			//余额
			$datas[$key]['user_money'] = $users[$value['member_id']]['money'];
			//机器人数量
			$datas[$key]['robot_cnt'] = $users[$value['member_id']]['robot_cnt'];
		}
		return $datas;
	}

  /**
   * 获取昨天asr费用
   *
   * @param int $user_id 用户ID
   * @return float
   *
  */
  public function get_yesterday_asr_money($user_id)
  {
    if(empty($user_id)){
      return false;
    }
    $start_time = strtotime(date('Y-m-d', strtotime('-1 day')));
    $end_time = strtotime(date('Y-m-d'));
    $money = Db::name('tel_order')->where(['create_time' => ['between', [$start_time, $end_time]], 'owner' => $user_id])->sum('asr_money');
    return $money;
  }

  /**
   * 获取昨天通话费用
   *
   * @param int $user_id
   * @return float
  */
  public function get_yesterday_call_money($user_id)
  {
    if(empty($user_id)){
      return false;
    }
    $start_time = strtotime(date('Y-m-d', strtotime('-1 day')));
    $end_time = strtotime(date('Y-m-d'));
    $money = Db::name('tel_order')->where(['create_time' => ['between', [$start_time, $end_time]], 'owner' => $user_id])->sum('call_money');
    return $money;
  }

  /**
   * 获取昨日机器人费用
   *
   * @param int $user_id
   * @return float
  */
  public function get_yesterday_robot_money($user_id)
  {
    if(empty($user_id)){
      return false;
    }
    $date = date('Y-m-d', strtotime('-1 day'));
    // rk_robot_cost_statistics
    $cost = Db::name('robot_cost_statistics')->where(['member_id'=>$user_id, 'date'=>$date])->value('cost');
    return $cost;
  }

	/**
	 * 获取总行数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function get_count($member_id, $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$ids = [];
		$ids[] = $member_id;
		$AdminData = new AdminData();
		$find_ids = $AdminData->get_find_member_ids($member_id);
		$ids = array_merge($ids, $find_ids);
		$args['member_id'] = ['in', $ids];
		$count = Db::name('consumption_statistics')
							->where($args)
							->count('id');
		return $count;
	}

	/**
	 * 汇总呼叫次数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function summary_call_count($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_connect_count($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_duration_count($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_connect_duration($member_id, $args = []){
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_asr_count($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_sms_count($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_sms_cost($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_robot_cost($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('robot_cost');
		if(empty($count)){
			return 0;
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
	public function summary_connect_cost($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_asr_cost($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
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
	public function summary_consumption($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
			$args['member_id'] = ['in', $ids];
			$money = $money->where('member_id', 'in', $ids);
		}
		$money = $money->sum('total_cost');
		if(empty($money)){
			return 0;
		}
		return $money;
	}
	/**
	 * 汇总消费总金额
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function total_consumption_amount($member_id, $args = [])
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
		$AdminData = new AdminData();
		if(isset($args['member_id']) === false){
			$ids = [];
			$find_ids = $AdminData->get_find_member_ids($member_id);
			$role_name = $AdminData->get_role_name($member_id);
			if($role_name == '管理员'){
		  	$ids = $find_ids;
		  }else{
		  	$ids[] = $member_id;
				$ids = array_merge($ids, $find_ids);
		  }
			$args['member_id'] = ['in', $ids];
			$count = $count->where('member_id', 'in', $ids);
		}
		$count = $count->sum('total_cost');
		if(empty($count)){
			return 0;
		}
		return $count;
	}



	/**
	 * 计算用户昨天的总消费金额
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_yesterday_cost($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $start_time)
						->where('create_time', '<=', $end_time)
						->sum('money');
		$robot_cost = Db::name('robot_cost_statistics')
								->where('member_id',$member_id)
								->where('date','=',$yesterday)
								->value('cost');
		$allsum = $robot_cost + $sum;
		if(empty($allsum)){
			return 0;
		}
		return $allsum;
	}

	/**
	 * 计算商家用户昨天的总消费金额
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_business_yesterday_cost($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sum = Db::name('tel_order')
						->field('(sum(ceil(duration/60) * time_price) + sum(asr_cnt * asr_price) + sum(sms_price * sms_count)) as money')
						->where('owner', $member_id)
						->where('create_time', '>=', $start_time)
						->where('create_time', '<=', $end_time)
						->find();
		$robot_cost = Db::name('robot_cost_statistics')
								->where('member_id',$member_id)
								->where('date','=',$yesterday)
								->value('cost');
		$allsum = $robot_cost + $sum['money'];
		if(empty($allsum)){
			return 0;
		}
		return $allsum;
	}

	/**
	 * 计算用户今天的消费金额
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_today_cost($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')
						->where([
							'owner'	=>	$member_id,
							'create_time'	=>	['>=', $start_time],
						])
						->sum('money');
		$robot_cost = Db::name('robot_cost_statistics')
								->where('member_id',$member_id)
								->where('date','=',$today)
								->value('cost');
		$allsum = $robot_cost + $sum;
		if(empty($allsum)){
			return 0;
		}
		return $allsum;
	}
	/**
	 * 计算子账户今天的消费金额
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_allsontoday_cost($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$robot_cost = Db::name('robot_cost_statistics')->where('date','=',$today);
		$money = Db::name('tel_order')
										->field('sum(ceil(duration/60)*time_price) as connect_cost,sum(asr_cnt*asr_price) as asr_cost,sum(sms_count*sms_price) as sms_cost');
		$AdminData = new AdminData();
		foreach($args as $key=>$value){
			if($key === 'member_id'){
				//获取子账户id
				$son_id = $AdminData->get_find_member_ids($value);
				$money = $money->where('owner', 'in', $son_id);
				$robot_cost = $robot_cost->where('member_id', 'in' ,$son_id);
			}
		}

		$EnterpriseData = new EnterpriseData();
		//获取所有id
		$ids = $EnterpriseData->get_userall_id($member_id,$type);

		\think\Log::record('计算子账户今天的消费金额获取所有id');
		if(isset($args['member_id']) === false){
			//获取所有id下的子账户id
			$son_id = $EnterpriseData->get_findson_member_ids($ids);
			$args['member_id'] = ['in', $son_id];
			$money = $money->where('owner', 'in', $son_id);
			$robot_cost = $robot_cost->where('member_id', 'in' ,$son_id);
		}

		$money = $money->find();
		$robot_cost = $robot_cost->sum('cost');
		$allmoney = $robot_cost + $money['connect_cost'] + $money['asr_cost'] + $money['sms_cost'];
		\think\Log::record('计算子账户今天的消费金额');
		if(empty($allmoney)){
			return 0;
		}
		return $allmoney;
	}

	/**
	 * 计算当前用户及子账户今天的消费金额
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_alltoday_cost($member_id, $type = '', $args = [])
	{
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$robot_cost = Db::name('robot_cost_statistics')->where('date','=',$today);
		$money = Db::name('tel_order')->where('create_time','>=',$start_time);
		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$robot_cost = $robot_cost->where('member_id',$value);
				$money = $money->where('owner', '=', $value);
			}
		}

		//获取所有id
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id,$type);
			$args['member_id'] = ['in', $ids];
			$robot_cost = $robot_cost->where('member_id', 'in', $ids);
			$money = $money->where('owner', 'in', $ids);
		}
		$robot_cost = $robot_cost->sum('cost');
		$money = $money->sum('money');

		$allmoney = $robot_cost + $money;
		\think\Log::record('计算当前用户及子账户今天的消费金额');
		if(empty($allmoney)){
			return 0;
		}
		return $allmoney;
	}


	/**
	 * 计算用户昨天的总通话费用
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_yesterday_connect_cost($member_id){
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $start_time)
						->where('create_time', '<=', $end_time)
						->sum('call_money');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户今天的总通话费用
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_today_connect_cost($member_id){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')
						->where([
							'owner'	=>	$member_id,
							'create_time'	=>	['>=', $start_time],
						])
						->sum('call_money');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算当前用户及子账户今天的总通话费用
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_alltoday_connect_cost($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')->where('create_time','>=',$start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		//获取所有id
		$EnterpriseData = new EnterpriseData();
		$ids = $EnterpriseData->get_userall_id($member_id,$type);
		if(isset($args['member_id']) === false){
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}

		$sum = $sum->sum('call_money');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户昨天的总识别费用
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_yesterday_asr_cost($member_id){
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $start_time)
						->where('create_time', '<=', $end_time)
						->sum('asr_money');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户今天的总识别费用
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_today_asr_cost($member_id){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')
						->where([
							'owner'	=>	$member_id,
							'create_time'	=>	['>=', $start_time],
						])
						->sum('asr_money');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算当前用户及子账户今天的总识别费用
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_alltoday_asr_cost($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')->where('create_time','>=',$start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		//获取所有id
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id,$type);
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}
		$sum = $sum->sum('asr_money');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 获取用户机器人月租费用(每天扣的)
	 *
	 * @param int $member_id
	 *
	 * #return float
	 **/
	 public function get_robot_daycost($member_id){
	 	if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));

		$robot_cost = Db::name('robot_cost_statistics')
								->where('member_id',$member_id)
								->where('date','=',$yesterday)
								->value('cost');

	 	if(empty($robot_cost)){
	 		$robot_cost = 0.000;
	 	}
	 	return $robot_cost;
	 }

	 /**
	 * 	计算当前用户及子账户今天的总的机器人月租费用
	 *
	 *  @param int $member_id
	 *  @param string $username
	 *  @param string $usertype
	 *  @param string $day_type
	 *  @return float
	*/
	public function get_allrobot_money($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$robot_cost = Db::name('robot_cost_statistics')
								->where('date','=',$today);
		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$robot_cost = $robot_cost->where('member_id',$value);
			}
		}

		//获取所有id
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id,$type);
			$args['member_id'] = ['in', $ids];
			$robot_cost = $robot_cost->where('member_id', 'in', $ids);
		}
		$robot_cost = $robot_cost->sum('cost');
		\think\Log::record('计算当前用户及子账户今天的总的机器人月租费用');
		if(empty($robot_cost)){
			$robot_cost = 0.000;
		}
		return $robot_cost;
	}

	/**
	 * 计算用户昨天总的识别次数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_yesterday_distinguish_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $start_time)
						->where('create_time', '<=', $end_time)
						->sum('asr_cnt');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户今天总的识别次数
	 *
	 * @param int $member_id
	 * @return float
	*/
	public function get_today_distinguish_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')
						->where([
							'owner'	=>	$member_id,
							'create_time'	=>	['>=', $start_time],
						])
						->sum('asr_cnt');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算当前用户及子账户今天总的识别次数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_alltoday_distinguish_count($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')->where('create_time','>=',$start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id,$type);
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}
		$sum = $sum->sum('asr_cnt');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户昨天的呼叫总数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_yesterday_call_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$count = Db::name('member')
							->where('owner', $member_id)
							->where('status', '>=', 2)
							->where('last_dial_time', '>=', $start_time)
							->where('last_dial_time', '<=', $end_time)
							->count('1');
		return $count;
	}

	/**
	 * 计算用户今天的呼叫总数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_call_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$time = strtotime(date('Y-m-d'));
		$count = Db::name('member')
							->where('owner', $member_id)
							->where('status', '>=', 2)
							->where('last_dial_time', '>=', $time)
							->count('1');
		return $count;
	}

	/**
	 * 计算当前用户及子账户今天呼叫总数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_allcall_count($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('member')->where('status', '>=', 2)->where('last_dial_time', '>=', $start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id,$type);
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}
		$sum = $sum->count('1');
		\think\Log::record('计算当前用户及子账户今天呼叫总数');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户昨天计费时长(分钟)
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_yesterday_charging_duration($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sql = '
			SELECT
				sum(ceil(duration/60)) as charging_duration
			FROM
				rk_tel_order
			WHERE
				owner = :member_id
				and
				create_time >= :start_time
				and
				create_time <= :end_time
		';
		$charging_duration = Db::query($sql, [
			'member_id'	=>	$member_id,
			'start_time'	=>	$start_time,
			'end_time'	=>	$end_time,
		]);
		if(empty($charging_duration[0]['charging_duration'])){
			return 0;
		}
		return $charging_duration[0]['charging_duration'];
	}

	/**
	 * 获取用户今天的计算时长(分钟)
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_charging_duration($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$time = strtotime(date('Y-m-d'));
		$sql = '
			SELECT
				sum(ceil(duration/60)) as charging_duration
			FROM
				rk_tel_order
			WHERE
				owner = :member_id
				and
				create_time >= :time
		';
		$charging_duration = Db::query($sql, [
			'member_id'	=>	$member_id,
			'time'	=>	$time
		]);
		if(empty($charging_duration[0]['charging_duration'])){
			return 0;
		}
		return $charging_duration[0]['charging_duration'];
	}

	/**
	 * 计算当前用户及子账户今天计算时长(分钟)
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return int
	*/
	public function get_allcharging_duration($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$charging_duration = Db::name('tel_order')->field('sum(ceil(duration/60)) as charging_duration')->where('create_time', '>=', $start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$charging_duration = $charging_duration->where('owner', '=', $value);
			}
		}
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id,$type);
			$args['member_id'] = ['in', $ids];
			$charging_duration = $charging_duration->where('owner', 'in', $ids);
		}
		$charging_duration = $charging_duration->find();
		if(empty($charging_duration['charging_duration'])){
			return 0;
		}
		return $charging_duration['charging_duration'];
	}

	/**
	 * 计算用户昨天的通话总时长
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_yesterday_connect_duration($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$connect_duration = Db::name('tel_order')
												->where('owner', $member_id)
												->where('create_time', '>=', $start_time)
												->where('create_time', '<=', $end_time)
												->sum('duration');
		return $connect_duration;
	}

	/**
	 * 获取用户今天的通话总时长
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_today_connect_duration($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$time = strtotime(date('Y-m-d'));
		$connect_duration = Db::name('tel_order')
												->where('owner', $member_id)
												->where('create_time', '>=', $time)
												->sum('duration');
		return $connect_duration;
	}

	/**
	 * 计算当前用户及子账户今天通话总时长
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_alltoday_connect_duration($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')->where('create_time', '>=', $start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id,$type);
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
	 * 计算用户昨天的接通次数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_yesterday_connect_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$connect_count = Db::name('member')
												->where('owner', $member_id)
												->where('status', 2)
												->where('last_dial_time', '>=', $start_time)
												->where('last_dial_time', '<=', $end_time)
												->count('1');
		return $connect_count;
	}

	/**
	 * 获取今天的接通次数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_connect_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$time = strtotime(date('Y-m-d'));
		$connect_count = Db::name('member')
												->where('owner', $member_id)
												->where('status', 2)
												->where('last_dial_time', '>=', $time)
												->count('1');
		return $connect_count;
	}

	/**
	 * 计算当前用户及子账户今天接通次数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_allconnect_count($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('member')->where('status', 2)->where('last_dial_time', '>=', $start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id, $type);
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}
		$sum = $sum->count('1');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户昨天的短信计费条数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_yesterday_sms_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $start_time)
						->where('create_time', '<=', $end_time)
						->sum('sms_count');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 获取今天的短信计费条数
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_sms_count($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$time = strtotime(date('Y-m-d'));
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $time)
						->sum('sms_count');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算当前用户及子账户今天短信计费条数
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_allsms_count($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')->where('create_time', '>=', $start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id, $type);
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}
		$sum = $sum->sum('sms_count');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}

	/**
	 * 计算用户昨天的短信费用
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_yesterday_sms_cost($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$yesterday = date("Y-m-d", strtotime('-1 day'));
		$start_time = strtotime($yesterday);
		$end_time = $start_time + (24 * 3600);
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $start_time)
						->where('create_time', '<=', $end_time)
						->sum('sms_money');
		return $sum;
	}

	/**
	 * 获取今天的短信费用
	 *
	 * @param int $member_id
	 * @return int
	*/
	public function get_sms_cost($member_id)
	{
		if(empty($member_id)){
			return false;
		}
		$time = strtotime(date('Y-m-d'));
		$sum = Db::name('tel_order')
						->where('owner', $member_id)
						->where('create_time', '>=', $time)
						->sum('sms_money');
		return $sum;
	}

	/**
	 * 计算当前用户及子账户今天短信费用
	 *
	 * @param int $member_id
	 * @param array $args
	 * @return float
	*/
	public function get_allsms_cost($member_id, $type = '', $args = []){
		if(empty($member_id)){
			return false;
		}
		$today = date("Y-m-d");
		$start_time = strtotime($today);
		$sum = Db::name('tel_order')->where('create_time', '>=', $start_time);

		foreach($args as $key=>$value){
			if($key === 'member_id'){
				$sum = $sum->where('owner', '=', $value);
			}
		}
		$EnterpriseData = new EnterpriseData();
		if(isset($args['member_id']) === false){
			$ids = $EnterpriseData->get_userall_id($member_id, $type);
			$args['member_id'] = ['in', $ids];
			$sum = $sum->where('owner', 'in', $ids);
		}
		$sum = $sum->sum('sms_money');
		if(empty($sum)){
			return 0;
		}
		return $sum;
	}
}
