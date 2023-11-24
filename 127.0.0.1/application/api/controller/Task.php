<?php

namespace app\api\controller;

use think\Db;
use Overtrue\Pinyin\Pinyin;
use app\common\controller\Log;
//消费统计
use app\common\controller\ConsumptionStatistics;
//用户数据
use app\common\controller\AdminData;

use app\user\controller\Manager;

use app\common\controller\LinesData;
//Redis
use app\common\controller\RedisConnect;

use app\api\controller\Smartivr;
use app\common\controller\TaskData;
class Task
{
    public $inArrears='';//用来记录欠费用户名
	//每天凌晨删除 redis 所储存的 是否查看通话记录的键下的所有值
    public function delete_review_call(){
      return true;
      $day_numer = 5;
      $redis = RedisConnect::get_redis_connect();
      for($i = 0; $i <= $day_number; $i++){
        if($i == 0){
          $redis_key = 'tel_call_record_review';
        }else{
          $redis_key = 'tel_call_record_'.date('Ymd', strtotime("-{$i} day")) . '_review';
          //tel_call_record_20190604_review
        }
        $redis->del($redis_key);
      }

    }
    public function verify_task_start($user_id){
        if(empty($user_id)){
            return false;
        }
        //获取当前用户
        $p_user = Db::name('admin')
            ->alias('a')
            ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
            ->field('a.is_jizhang,a.id, a.username,(a.money + a.credit_line) as balance, a.pid, ar.name as role_name')
            ->where('a.id', $user_id)
            ->find();
        $is_jizhang=$p_user['is_jizhang'];
        $p_role_name=getRoleNameByUserId($p_user['pid']); //上一级用户名
        if($p_user['balance'] <= 0){
            if($p_role_name=='商家'&&$p_user['role_name']=='销售人员' && $p_user['is_jizhang']==1){

            }else{
                $this->inArrears=$p_user['username'];
                return false;
            }
        }


        //寻找父类  查看欠费
        while(!empty($p_user['pid'])){

            $p_user = Db::name('admin')
                ->alias('a')
                ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                ->field('a.id, a.username,(a.money + a.credit_line) as balance, a.pid, ar.name as role_name')
                ->where('a.id', $p_user['pid'])
                ->find();


            //当此账户欠费
            if( $p_user['balance'] <= 0 ){

                if( ($is_jizhang==0 && $p_user['role_name']=='商家') || $p_user['role_name'] =='管理员'){
                    continue;   //当最开始为销售的时候就跳过对上级商家的判断。
                }
                if($p_user['balance'] <= 0){
                    $this->inArrears=$p_user['username'];

                    return false;
                }

            }


        }
        return true;
    }

    /**
     * 验证是否能够开启任务(asr余额是否充足)
     *
     */
    public function verify_task_asr_start($user_id)
    {
        if (empty($user_id) || empty($task_id)) {
            return false;
        }

        //获取当前用户ASR
        $tel_interfaceArr = Db::name('tel_interface ti')
            ->join('tel_config tc', 'ti.id=tc.asr_id', 'left')
            ->where('ti.owner', $user_id)
            ->where('tc.task_id', $task_id)
            ->field('ti.asr_from,ti.asr_token')
            ->find();
        //如果ASR不是使用的我们分配给admin的默认ASR 或者 分配的ASR token字段为空 则不做欠费判断直接返回 不欠费
        if ($tel_interfaceArr['asr_from'] != 1 || empty($tel_interfaceArr['asr_token'])) return true;


        //获取admin的 ASR
        $tel_interfaceArr = Db::name('tel_interface ti')
            ->join('admin a', ' ti.owner=a.id', 'left')
            ->where('a.username', 'admin')
            ->where('ti.asr_token', $tel_interfaceArr['asr_token'])
            ->where('ti.money', '<=', 0)
            ->find();

        return empty($tel_interfaceArr);

    }

    /**
     * 定时开启任务 - 定时脚本
     *
     */
    public function auto_start_task_api()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        echo '开始时间：' . date('Y-m-d H:i:s') . "\n";

        //查询任务状态为：未启动、人工暂停和欠费暂停 且 允许自动开启任务 的 所有任务
        $tel_configs = Db::name('tel_config')
            ->field('task_id,is_auto,fs_num,robot_cnt')
            ->where([
                'status' => ['in', [0, 2, 4, 5]],
                'is_auto' => 1
            ])
            ->select();
        $current_date_time = date('Y-m-d');
        $current_time = date('H:i:s');
        foreach ($tel_configs as $key => $value) {

            if ($value['is_auto']) {
                //查询当前任务的指定日期是否符合当前日期
                $count = Db::name('auto_task_date')
                    ->where([
                        'start_date' => ['<=', $current_date_time],
                        'end_date' => ['>=', $current_date_time],
                        'task_id' => $value['task_id']
                    ])
                    ->count('id');
                if ($count > 0) {
                    //查询当前任务的指定时间是否符合当前时间
                    $count = Db::name('auto_task_time')
                        ->where([
                            'start_time' => ['<=', $current_time],
                            'end_time' => ['>=', $current_time],
                            'task_id' => $value['task_id']
                        ])
                        ->count('id');
                    if ($count > 0) {
                        //获取当前任务的机器人数量和用户
                        $task_data = Db::name('tel_config')
                            ->field('member_id,robot_cnt,fs_num')
                            ->where('task_id', $value['task_id'])
                            ->find();
                        $redis = RedisConnect::get_redis_connect();
                        $redis_key = 'task_id_fs_num_'.$value['task_id'];
                        $redis_fs_num = $redis->get($redis_key);
                        if(empty($task_data['fs_num']) && empty($redis_fs_num)){
							try{
								$TaskData = new TaskData();
								$fs_server_data = $TaskData->get_min_run_robot_count_fs_server($value['task_id']);
								$fs_num = $fs_server_data['fs_num'];
								$fs_db = $fs_server_data['db'];
								$result = $TaskData->insert_task_data_to_fs_server($value['task_id'], $fs_server_data);
								if($result == true){
									$redis->setex($redis_key, 6 * 3600, $fs_num);
								}
							}catch (\Exception $e) {
                              continue;
                            }
                        }else if(!empty($redis_fs_num)){
                            $fs_num = $redis_fs_num;
                            $fs_db = Db::connect(config('db_configs.fs' . $fs_num));
                        }else{
                            $fs_num = $task_data['fs_num'];
                            $fs_db = Db::connect(config('db_configs.fs' . $fs_num));
                        }
                        //获取当前可用机器人数量
                        $current_robot_count = Db::name('admin')
                            ->where('id', $task_data['member_id'])
                            ->value('usable_robot_cnt');
                        //获取当前运行的机器人数量
                        $current_run_robot_count = Db::name('tel_config')
                            ->where([
                                'status'	=>	1,
                                'member_id'	=>	$task_data['member_id']
                            ])
                            ->sum('robot_cnt');
                        // $run_robot_count = Db::connect(config('db_configs.fs' . $value['fs_num']))->table('autodialer_task')->where('start', 1)->sum('maximumcall');
                        //判断当前用户是否有足够的机器人用来开启该任务
                        if ($task_data['robot_cnt'] <= ($current_robot_count - $current_run_robot_count)) {
                            //是否欠费 否
                            if ($this->verify_task_start($task_data['member_id']) == true) {
                                //检查fs服务器的正在运行的机器人数
                                // $run_robot_count = Db::name('tel_config')->where([
                                //     'fs_num' => $value['fs_num'],
                                //     'status' => 1
                                // ])
                                //     ->sum('robot_cnt');
                                $run_robot_count = $fs_db->table('autodialer_task')->where('start', 1)->sum('maximumcall');
                                if ((config('max_workload') - $run_robot_count) > $value['robot_cnt']) {
                                    $fs_start_task = true;
                                } else {
                                    $fs_start_task = false;
                                }
                                if ($fs_start_task == false) {
                                    Db::name('tel_config')->where('task_id', $value['task_id'])->update(['tips_info' => '由于当前定时时间段线路比较繁忙，任务无法自动开启，需要手动启动任务。']);
                                    continue;
                                }
                                //开启任务
                                $this->update_task_status($value['task_id'], 1, $fs_num);
                                //是
                            } else {
								$ret = Db::name('tel_config')->where('task_id',$value['task_id'])->update(array('arrears_user'=>$this->inArrears ));
                                //欠费暂停任务
                                $this->update_task_status($value['task_id'], 4, $fs_num);
                            }
                        }
                    }
                }
            }
        }
        //\think\Log::record('定时开启任务');
        //return 'ok';
        echo '结束时间：' . date('Y-m-d H:i:s') . "\n";
    }

    /**
     * 检查任务在当前时间里是否应该暂停 - 定时脚本
     */
    public function task_whether_pause()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        echo '开始时间：' . date('Y-m-d H:i:s') . "\n";

        $current_date_time = date('Y-m-d 00:00:00'); //年月日
        $current_time = date('H:i:s'); //时分秒
        //获取状态为"1"[进行中]的所有任务
        $where = [
            'status' => 1,
            'is_auto' => 1
        ];
        $task_datas = Db::name('tel_config')
            ->field('task_id,fs_num')
            ->where($where)
            ->select();
        foreach ($task_datas as $key => $value) {
            //查询当前任务的指定日期是否符合当前日期
            $count = Db::name('auto_task_date')
                ->where([
                    //开始日期
                    'start_date' => ['<=', $current_date_time],
                    //结束日期
                    'end_date' => ['>=', $current_date_time],
                    'task_id' => $value['task_id']
                ])
                ->count('id');
            if (empty($count)) {
                $i = 1;
            } else {
                //查询当前任务的指定时间是否符合当前时间
                $count = Db::name('auto_task_time')
                    ->where([
                        //开始时间
                        'start_time' => ['<=', $current_time],
                        //结束时间
                        'end_time' => ['>=', $current_time],
                        'task_id' => $value['task_id']
                    ])
                    ->count('id');
                if (empty($count)) {
                    $i = 1;
                } else {
                    $i = 0;
                }
            }
            if ($i == 1) {
                //暂停任务
                $this->update_task_status($value['task_id'], 5, $value['fs_num']);
            }
        }
        echo '结束时间：' . date('Y-m-d H:i:s') . "\n";
    }


    /**
     * 获取任务拨打信息
     *
     * @param int $task_id 任务ID
     * @param int $
     * @return array
     */
    private function get_task_call_info($task_id, $fs_num)
    {
        if (empty($task_id)) {
            return false;
        }
        $result = [];
        //否则fs号码表存在的话
        //统计fs号码表中的全部电话数字
        //全部号码的数量
        $result['count_sum'] = Db::connect('db_configs.fs' . $fs_num)
            ->table('autodialer_number_' . $task_id)
            ->count('id');

        //去fs数据库中统计 因为任务开启时候 fs的号码表state 都变成null  所以不能用小于10来读取未完成的状态了，所以只能读取已经完成的数字
        $result['count_finish'] = Db::connect('db_configs.fs' . $fs_num)
            ->table('autodialer_number_' . $task_id)
            ->where('state', '>=', 10)
            ->count('id');
        //求出号码表中 最后拨打的时间
        $last_call_time = Db::connect('db_configs.fs' . $fs_num)
            ->table('autodialer_number_' . $task_id)
            ->order('calldate desc')
            ->value('calldate');
        $result['last_call_time'] = strtotime($last_call_time);
        //查询未拨打的号码数量
        $result['no_call_count'] = Db::connect('db_configs.fs' . $fs_num)
            ->table('autodialer_number_' . $task_id)
            ->where('calldate', null)
            ->count('id');
        //然后再求出 calldate 不为空的总数
        $result['count_call_time'] = Db::connect('db_configs.fs' . $fs_num)
                                      ->table('autodialer_number_' . $task_id)
                                      ->where('calldate', 'not null')
                                      ->count('*');
            
        $result['state_2_count'] = Db::connect('db_configs.fs' . $fs_num)
            ->table('autodialer_number_' . $task_id)
            ->where('state', '>=', 2)
            ->count('*');
            
        $result['last_call_time'] = Db::connect('db_configs.fs' . $fs_num)->table('autodialer_number_' . $task_id)->max('calldate');
        $result['last_call_time'] = strtotime($result['last_call_time']);
        return $result;
    }

    /**
     * 更新任务的定时检查时间
     *
     * @param int|array $task_id 任务ID
     * @return bool
     */
    private function update_last_inspect_time($task_id)
    {
        if (empty($task_id) || (is_array($task_id) && count($task_id) == 0)) {
            return false;
        }
        $where = [];
        if (is_array($task_id)) {
            $where['task_id'] = ['in', $task_id];
        } else {
            $where['task_id'] = $task_id;
        }
        //更新
        $result = Db::name('tel_config')
            ->where($where)
            ->update([
                'last_inspect_time' => time()
            ]);
        if (!empty($result)) {
            return true;
        }
        return false;
    }

    /**
     * 将指定任务更新为已完成
     *
     * @param int $task_id 任务ID
     * @param int $status 任务状态
     * @param int $fs_num fs的下标
     * @return bool
     */
    private function update_task_status($task_id, $status, $fs_num = 1, $tips_info = '')
    {
		
        if($status==3){
           $config = Db::name('tel_config')->where('task_id',$task_id)->find();
           //如果任务是开启的话 就推送 否则不推送了
           if($config['status']==1){
               $wx_push_status = Db::name('tel_config')->where('task_id',$task_id)->value('wx_push_status');
               if($wx_push_status==1){
                  //如果开启了微信推送 则 推送给 每个用户任务结束的模板信息
                  
                  $arr = explode(',',$config['wx_push_user_id']); 
                  foreach($arr as $key=>$value){
                     $url=config('weixin_push_ip')."/api/wechat/send_massage_to_user_one_by_end?uid=".$config['member_id']."&wx_push_user_id=".$value."&taskId=".$task_id;
                     $result = json_decode($this->http_curl_wx($url));
                     if($result[0]==false){
                        \think\Log::record('微信完成信息的推送，推送失败！');
                    }
                  }
               }
           }
        }
        
        if (empty($task_id) || empty($status)) {
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        if($status == 3 || $status == 7 || $status ==-1){
          $redis->del('task_'.$task_id.'_members');
        }
        $result = Db::name('tel_config')
            ->where('task_id', $task_id)
            ->update([
                'tips_info' => '',
                'status' => $status,
                'update_time' => time()
            ]);
        $fs_result = Db::connect('db_configs.fs' . $fs_num)
            ->table('autodialer_task')
            ->where('uuid', $task_id)
            ->update([
                'start' => $status,
                'alter_datetime' => date('Y-m-d H:i:s'),
            ]);
         //如果$status=3 代表任务完成 判断 任务是否开启微信推送
        
        if (!empty($result) && !empty($fs_result)) {
            return true;
        }
        return false;
    
    
    }

    /**
     * 对非今天拨打的号码进行重新呼叫的处理函数
     *
     * @param int $task_id 任务ID
     * @param array $again_call_status 需要重新呼叫的通话状态
     * @param int $fs_num fs服务器的下标
     * @param int $member_id 用户ID
    */
    public function history_again_call_number($task_id, $again_call_status, $fs_num = 1, $member_id)
    {
      if (empty($task_id) || is_array($again_call_status) == false || count($again_call_status) == 0 || empty($member_id)) {
          return false;
      }

      //1.获取重新呼叫的号码总数
      //2.判断号码总数是否大于0 如果大于0 继续往下走 反之停止
      //3.对需要重新呼叫的号码进行分页
      //4.获取每页的号码和Call_id
      //5.将号码的通话状态改为待拨打
      //6.增加号码总数的数量
      //7.修改fs服务器中的号码表的状态 实现重新呼叫

      $redis = RedisConnect::get_redis_connect();
      //获取今天凌晨的时间戳
      $now_time = strtotime(date("Y-m-d"));
      //获取指定通话状态的号码数量
      $where = [];
      $where['status'] = ['in', $again_call_status];
      $where['task'] = $task_id;
      //今天之前拨打过的号码
      $where['last_dial_time'] = ['<', $now_time];
      $max_limit = 500;
      //1.获取重新呼叫的号码总数
      $total_count = Db::connect(config('master_db'))->table('rk_member')->where($where)->count('uid');



      \think\Log::record('history_again_call_number - $total_count - ' . $total_count);


      //2.判断号码总数是否大于0 如果大于0 继续往下走 反之停止
      if($total_count > 0){

        //3.对需要重新呼叫的号码进行分页
        $max_page = ceil($total_count / $max_limit);
        $call_count_key = "incr_owner_".$member_id."_".$now_time."_all_count";

        for ($i = 1; $i <= $max_page; $i++) {
            $where = [];
            $where['status'] = ['in', $again_call_status];
            $where['task'] = $task_id;
            //今天之前拨打过的号码
            $where['last_dial_time'] = ['<', strtotime(date("Y-m-d"))];
            //4.获取每页的号码和Call_id
            $number_datas = Db::connect(config('master_db'))->table('rk_member')
                ->where($where)
                ->page(1, $max_limit)
                ->field('mobile, call_id')
                ->select();
            $numbers = [];
            $call_ids = [];
            foreach($number_datas as $key=>$value){
              $numbers[] = $value['mobile'];
              $call_ids[] = $value['call_id'];
            }

            $counts = count($numbers);
            //更新web的member表
            $where1 = [];
            $where1['mobile'] = ['in', $numbers];
            $where1['task'] = $task_id;
            //5.将号码的通话状态改为待拨打
            $result = Db::connect(config('master_db'))->table('rk_member')
                ->where($where1)
                ->update([
                    'status' => 1
                ]);
            if (empty($result)) {
                \think\Log::record('error - 更新web的member表失败');
            }else{
                //6.增加号码总数的数量
                $redis->incrby($call_count_key, $counts);
            }




            //7.修改fs服务器中的号码表的状态 实现重新呼叫
            $result = Db::connect('db_configs.fs' . $fs_num)
                ->table('autodialer_number_' . $task_id)
                ->where('number', 'in', $numbers)
                ->update([
                    'state' => 0,
                    'calldate' => null
                ]);

            if (empty($result)) {
                \think\Log::record('error - 更新freeswitch的(autodialer_number_?)号码表失败');
            }

            $now_time = strtotime(date('Ymd',time()));
            if($counts > 0){
                $today = date('Ymd');
                $expire_time = 30 * 24 * 3600;  //过期时间一个月

                //已经设置的重呼次数
                $redis->incrBy("call-set-again-{$today}", $counts);
                $redis->expire("call-set-again-{$today}", $expire_time);


            }
            if (empty($result)) {
                \think\Log::record('error - 删除之前生成的通话记录失败');
            }
        }
      }
      return true;
    }
    /**
     * 对今天拨打的号码进行重新呼叫的处理函数
     *
     * @param int $task_id 任务ID
     * @param array $again_call_status 需要重新呼叫的通话状态
     * @param int $fs_num fs服务器的下标
     * @param int $member_id 用户ID
    */
    public function today_again_call_number($task_id, $again_call_status, $fs_num = 1, $member_id)
    {
        if (empty($task_id) || is_array($again_call_status) == false || count($again_call_status) == 0 || empty($member_id)) {
            return false;
        }

        //1.获取重新呼叫的号码总数
        //2.判断号码总数是否大于0 如果大于0 继续往下走 反之停止
        //3.对需要重新呼叫的号码进行分页
        //4.获取每页的号码和Call_id
        //5.删除消费明显
        //6.删除通话记录
        //7.将号码的通话状态改为待拨打
        //8.修改fs服务器中的号码表的状态 实现重新呼叫
        //9.减少未接通次数

        $redis = RedisConnect::get_redis_connect();
        //获取今天凌晨的时间戳
        $now_time = strtotime(date("Y-m-d"));
        //获取指定通话状态的号码数量
        $where = [];
        $where['status'] = ['in', $again_call_status];
        $where['task'] = $task_id;
        //今天拨打过的号码
        $where['last_dial_time'] = ['>=', $now_time];
        $max_limit = 500;
        //1.获取重新呼叫的号码总数
        $total_count = Db::connect(config('master_db'))->table('rk_member')->where($where)->count('uid');



        \think\Log::record('history_again_call_number - $total_count - ' . $total_count);
        //2.判断号码总数是否大于0 如果大于0 继续往下走 反之停止
        if($total_count > 0){
            //3.对需要重新呼叫的号码进行分页
            $max_page = ceil($total_count / $max_limit);
            for ($i = 1; $i <= $max_page; $i++) {
                //4.获取每页的号码和Call_id
                $where = [];
                $where['status'] = ['in', $again_call_status];
                $where['task'] = $task_id;
                //今天拨打过的号码
                $where['last_dial_time'] = ['>=', $now_time];
                $number_datas = Db::connect(config('master_db'))->table('rk_member')
                    ->where($where)
                    ->page(1, $max_limit)
                    ->field('mobile, call_id')
                    ->select();
                $numbers = [];
                $call_ids = [];
                foreach($number_datas as $key=>$value){
                  $numbers[] = $value['mobile'];
                  $call_ids[] = $value['call_id'];
                }

                //5.删除消费明显
                $where = [
                  'mobile'  =>  ['in', $numbers],
                  'call_id' =>  ['in', $call_ids]
                ];
                if(count($numbers) && count($call_ids)){
                  echo "删除消费明细" . Db::name('tel_order')->where($where)->delete() . "条\n";
                }

                //6.删除通话记录
                $result = Db::connect(config('master_db'))->table('rk_tel_call_record')
                    ->where([
                        'task_id' => $task_id,
                        'mobile' => ['in', $numbers]
                    ])
                    ->delete();
                //7.将号码的通话状态改为待拨打
                $where1 = [];
                $where1['mobile'] = ['in', $numbers];
                $where1['task'] = $task_id;
                $result = Db::connect(config('master_db'))->table('rk_member')
                    ->where($where1)
                    ->update([
                        'status' => 1
                    ]);
                if (empty($result)) {
                    \think\Log::record('error - 更新web的member表失败');
                }
                //8.修改fs服务器中的号码表的状态 实现重新呼叫
                $result = Db::connect('db_configs.fs' . $fs_num)
                    ->table('autodialer_number_' . $task_id)
                    ->where('number', 'in', $numbers)
                    ->update([
                        'state' => 0,
                        'calldate' => null
                    ]);

                if (empty($result)) {
                    \think\Log::record('error - 更新freeswitch的(autodialer_number_?)号码表失败');
                }


                //9.减少未接通次数
                $redis = RedisConnect::get_redis_connect();
                $counts = count($numbers);
                $now_time = strtotime(date('Ymd',time()));
                $incr_key_all_unconnect_count = "incr_owner_".$member_id."_".$now_time."_all_unconnect_count";
                // /_charging_unconnect_count
                $incr_key_today_unconnect_count = "incr_" . $now_time . "_today_unconnect_count";
                //判断当前用户是否为不记账销售
                $where = [
                  'role_id' =>  19,
                  'is_jizhang'  =>  0,
                  'id'  =>  $member_id
                ];
                $is_not_jizhang_xiaoshou = Db::name('admin')->where($where)->count(1);

                if($counts > 0){
                    $today = date('Ymd');
                    $expire_time = 30 * 24 * 3600;  //过期时间一个月

                    //已经设置的重呼次数
                    $redis->incrBy("call-set-again-{$today}", $counts);
                    $redis->expire("call-set-again-{$today}", $expire_time);

                    $unconnect_count = $redis->get($incr_key_all_unconnect_count);
                    if($unconnect_count >= $counts){
                        $redis->decrby($incr_key_all_unconnect_count,$counts);
                    }

                    $today_unconnect_count = $redis->get($incr_key_today_unconnect_count);
                    if($today_unconnect_count >= $counts){
                        $redis->decrby($incr_key_today_unconnect_count,$counts);
                    }


                    //call_phone_group_id
                    $line_id = Db::name('tel_config')->where('task_id', $task_id)->value('call_phone_group_id');
                    $line_data = Db::name('tel_line_group')->where('id', $line_id)->field('id, user_id, line_group_pid')->find();
                    //减少拨打次数
                    while(!empty($line_data['id'])){
                        //关联线路的拨打次数
                        $line_unconnect_count_key = "incr_owner_" . $line_data['user_id'] . "_" . $line_data['id'] . "_" . $now_time . "_line_unconnect_count";
                        $redis->decrby($line_unconnect_count_key, $counts);

                        $line_data = Db::name('tel_line_group')->where('id', $line_data['line_group_pid'])->field('id, user_id, line_group_pid')->find();
                    }
                    $user_data = Db::name('admin')->where('id', $member_id)->field('id, role_id, pid')->find();
                    while(!empty($user_data['id'])){
                      //关联用户的拨打次数
                      $unconnect_count_key = "incr_owner_".$user_data['id']."_".$now_time."_charging_unconnect_count";

                      if($user_data['role_id'] != 18 || $is_not_jizhang_xiaoshou != 1){
                        $redis->decrby($unconnect_count_key, $counts);
                      }

                      $user_data = Db::name('admin')->where('id', $user_data['pid'])->field('id, role_id, pid')->find();
                    }
                }
            }
        }
        return true;
    }

    /**
     * 重呼指定任务中指定通话状态的号码
     *
     * @param int $task_id 任务ID
     * @param array $again_call_status 指定需要重呼的通话状态的号码
     * @return bool
     */
    private function again_call($task_id, $again_call_status, $fs_num = 1, $member_id)
    {
        if (empty($task_id) || is_array($again_call_status) == false || count($again_call_status) == 0) {
            return false;
        }

        //更新任务配置表中的重新拨打次数
        $result = Db::connect(config('master_db'))->table('rk_tel_config')
            ->where('task_id', $task_id)
            ->setInc('already_again_call_count');
        //对不是今天打的号码进行重新呼叫的处理
        $this->history_again_call_number($task_id, $again_call_status, $fs_num, $member_id);
        //对今天打的号码进行重新呼叫的处理
        $this->today_again_call_number($task_id, $again_call_status, $fs_num, $member_id);
        return true;
    }

    /**
     * 删除该条线路上级所有人的未接通统计
     * @param: $call_phone_id int 线路id
     * @param: $counts int 删除的数量
     * @author: xiangjinkai
     * @date: 2019/6/12
     * @return
     */
    function del_parent_unconnect_count($call_phone_id,$counts){
        //删除未接通数redis数(该条线路上级。包括自己所有人都需删除)
        $parent_line_ids = $this->get_parent_ids($call_phone_id);
        $line_ids = explode(',',$parent_line_ids);
        $line_list = Db::name('tel_line')->where('id','in',$line_ids)->field('member_id')->select();
        foreach ($line_list as $key => $val){
            $member_ids[] = $val['member_id'];
        }
        $redis = RedisConnect::get_redis_connect();
        $now_time = strtotime(date('Ymd',time()));
        foreach ($member_ids as $key => $val){
            $incr_key_all_unconnect_count = "incr_owner_".$val."_".$now_time."_all_unconnect_count";
            if($counts > 0){
                $unconnect_count = $redis->get($incr_key_all_unconnect_count);
                if($unconnect_count >= $counts){
                    $redis->decrby($incr_key_all_unconnect_count,$counts);
                }
            }
        }
    }

    /**
     * 获取上一级父id，直至pid为0
     * @param: $id
     * @author: xiangjinkai
     * @date: 2019/6/12
     * @return
     */
    function get_parent_ids($id){
        return $this->__get_ids($id,'','pid');
    }
    /**
     * 递归取id，直至pid为0
     * @param: $id
     * @author: xiangjinkai
     * @date: 2019/6/12
     * @return
     */
    function __get_ids($pid,$childids,$find_column = 'pid'){
        if(!$pid || $pid<=0 || strlen($pid)<=0 || !in_array($find_column,array('id','pid'))) return 0;
        if(!$childids || strlen($childids)<=0) $childids = $pid;
        $column = ($find_column =='id'? "pid":"id");//id跟pid为互斥
        $ids = Db::name('tel_line')->where("$column in($pid)")->value($find_column);

        //未找到,返回已经找到的
        if($ids<=0) return $childids;
        //添加到集合中
        $childids .= ','.$ids;
        //递归查找
        return $this->__get_ids($ids,$childids,$find_column);
    }


    /**
     * 重写任务是否完成或者重呼
     * 1、如果web任务状态为已完成，执行下一步：状态改为完成或者重呼（）
     * 2、如果web任务进行中+FS状态>=2,如果时间超过5分钟，触发找回逻辑
     * 3、累加次数redis key为：task-inspect-{{task_id}}
     * @author 梁里坚
     * @date 2019-06-25
     */
    public function inspect_task_whether_complete_new()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        echo '开始时间' . date('Y-m-d H:i:s') . "\n";

        $redis = RedisConnect::get_redis_connect();

        $tasks = Db::connect(config('master_db'))->table('rk_tel_config')
            ->field('member_id, task_id, is_again_call, again_call_status, already_again_call_count, again_call_count, fs_num')
            ->where('status', 1)
            ->order('last_inspect_time asc')
            ->page(1, 100)
            ->select();
        $task_ids = [];
        foreach ($tasks as $key => $value) {
            $task_ids[] = $value['task_id'];
        }
        $this->update_last_inspect_time($task_ids);

        foreach ($tasks as $task) {
            $redis_task_inspect_time = "task-inspect-{$task['task_id']}";
            //FS配置是否存在
            if (!isset($task['fs_num']) || empty($task['fs_num'])) {
                continue;
            }

            //先判断 fs的号码表是否存在 如果不存在 web 表更新为完成
            try {
                $res = Db::connect('db_configs.fs' . $task['fs_num'])
                    ->table('autodialer_number_' . $task['task_id'])
                    ->value('number');
            } catch (\Exception $e) {
                //如果异常 就跳出本次循环 并且暂停该任务
                $this->update_task_status($task['task_id'], 7, $task['fs_num']);
                continue;
            }

            //如果fs号码表为空 代表不存在 更新web中的状态为异常暂停
            if (isset($res) && empty($res)) {
                $this->update_task_status($task['task_id'], 7, $task['fs_num']);
                continue;
            }

            //号码的总数量
            $web_total_number_count = Db::connect(config('master_db'))
                ->table('rk_member')
                ->where('task', $task['task_id'])
                ->count('uid');
            //号码等于0时不再处理
            if (0 == $web_total_number_count) {
                continue;
            }

            //已拨打的号码数量
            $web_call_number_count = Db::connect(config('master_db'))
                ->table('rk_member')
                ->where(['task' => $task['task_id'], 'status' => ['>=', 2]])
                ->count('uid');
            //根据web这边的数据判断任务是否完成
            if ($web_call_number_count >= $web_total_number_count) {
                //是否需要重新呼叫
                if ($task['is_again_call'] == 1 && $task['again_call_count'] > $task['already_again_call_count']) {
                    $again_call_status = explode(',', $task['again_call_status']);
                    $again_call_number_count_where = [
                        'task' => $task['task_id'],
                        'status' => ['in', $again_call_status]
                    ];
                    $again_call_number_count = Db::connect(config('master_db'))
                        ->table('rk_member')
                        ->where($again_call_number_count_where)
                        ->count('uid');
                    if ($again_call_number_count > 0) {
                        $this->again_call($task['task_id'], $again_call_status, $task['fs_num'], $task['member_id']);
                    } else {
                        $this->update_task_status($task['task_id'], 3, $task['fs_num']);
                    }
                } else {
                    $this->update_task_status($task['task_id'], 3, $task['fs_num']);
                }
                //$redis->del($redis_push_time_key);
            } else {
                //获取任务拨打信息
                $task_call_info = $this->get_task_call_info($task['task_id'], $task['fs_num']);
                //如果要拨打的号码大于0 且 都 >= 2时
                if ($task_call_info['count_sum'] > 0 && $task_call_info['count_finish'] >= $task_call_info['count_sum']) {
                    $redis_key = 'task-inspect-'.$task['task_id'] . '-time';
                    $inspect_time = $redis->get($redis_key);
                    if(empty($inspect_time)){
                      $inspect_time = time();
                      $redis->setex($redis_key, 600, $inspect_time);
                    }
                    // $inspect_time = $redis->incr($redis_task_inspect_time);
                    // $redis->expire($redis_task_inspect_time, 600);

                    if (time() - $inspect_time > 300) {
                        $redis->lpush('lose-tasks', $task['task_id']);
                        $input_key = "lose-input-time-{$task['task_id']}";
                        $redis->set($input_key, time() - 180);  //这里已经3分钟后再触发找回，找回函数也判断了3分钟，所以这里要-180
                    }
                }
            }
        }

        echo '结束时间' . date('Y-m-d H:i:s') . "\n";
    }

    /**
     * 定时检查任务是否完成 鲁健新写的 - 定时脚本
     */
    public function inspect_task_whether_complete()
    {
        
        //读主库 modify by 2019.06.04
        echo '开始时间' . date('Y-m-d H:i:s') . "\n";

        $takes = Db::connect(config('master_db'))->table('rk_tel_config')
            ->field('member_id, task_id, is_again_call, again_call_status, already_again_call_count, again_call_count, fs_num')
            ->where('status', 1)
            ->order('last_inspect_time asc')
            ->page(1, 100)
            ->select();
        $task_ids = [];
        foreach ($takes as $key => $value) {
            $task_ids[] = $value['task_id'];
        }
        $redis = RedisConnect::get_redis_connect();
        //更新定时检查时间
        $this->update_last_inspect_time($task_ids);
        $redis_key = 'inspect_task_whether_complete';
        //验证每个任务是否已经完成
        foreach ($takes as $key => $value) {

            $redis_push_time_key = $redis_key . '_' .$value['task_id'];
            $redis_push_time = $redis->get($redis_push_time_key);
            
            //abnormal
            $task_abnormal_key = 'task_abnormal_' . $value['task_id'];
            //任务中必须存在fs
            if (isset($value['fs_num']) && !empty($value['fs_num'])) {
                //先判断 fs的号码表是否存在 如果不存在 web 表更新为完成
                try {
                    $res = Db::connect('db_configs.fs' . $value['fs_num'])
                        ->table('autodialer_number_' . $value['task_id'])
                        ->value('number');
                } catch (\Exception $e) {
                    $redis->setInc($task_abnormal_key);
                    $task_abnormal_value = $redis->get($task_abnormal_key);
                    if($task_abnormal_value >= 3){
                      $redis->del($task_abnormal_key);
                      //如果异常 就跳出本次循环 并且暂停该任务
                      $this->update_task_status($value['task_id'], 3, $value['fs_num']);
                    }
                    continue;
                }
                $redis->del($task_abnormal_key);
                //如果fs号码表为空 代表不存在 更新web中的状态为已完成
                if (isset($res) && empty($res)) {
                    $this->update_task_status($value['task_id'], 3, $value['fs_num']);
                } else {
                    
                    //已呼叫的号码数量
                    $web_call_number_count_member = Db::connect(config('master_db'))
                                                        ->table('rk_member')
                                                        ->where(['task' => $value['task_id'], 'status' => ['>=', 2]])
                                                        ->count('uid');
                    //号码的总数量
                    $web_totel_number_count = Db::connect(config('master_db'))
                                                        ->table('rk_member')
                                                        ->where(['task' => $value['task_id']])
                                                        ->count('uid');
                    
                    
                    //根据web这边的数据判断任务是否完成  当号码总数量大于0 并且 已经拨打的数量大于等于号码总数的话。。。因为已拨打有时候不太准确
                    if($web_totel_number_count > 0 && $web_totel_number_count <= $web_call_number_count_member){
                        //是否需要重新呼叫
                        if($value['is_again_call'] == 1 && $value['again_call_count'] > $value['already_again_call_count']){
                            $again_call_status = explode(',', $value['again_call_status']);
                            $again_call_number_count_where = [
                                'task' =>  $value['task_id'],
                                'status'  =>  ['in', $again_call_status]
                            ];
                            $again_call_number_count = Db::connect(config('master_db'))
                                ->table('rk_member')
                                ->where($again_call_number_count_where)
                                ->count('uid');
                            if($again_call_number_count > 0){
                                $this->again_call($value['task_id'], $again_call_status, $value['fs_num'],$value['member_id']);
                            }else{
                                \think\Log::record('lujian#####1-----任务id为:' .$value['task_id']. '已经完成了，总共号码:' .$web_totel_number_count. '已经拨打的号码' .$web_call_number_count_member);
                                $this->update_task_status($value['task_id'], 3, $value['fs_num']);
                            }
                        }else{
                            \think\Log::record('lujian#####2-----任务id为:' .$value['task_id']. '已经完成了，总共号码:' . $web_totel_number_count. '已经拨打的号码' .$web_call_number_count_member);
                            $this->update_task_status($value['task_id'], 3, $value['fs_num']);
                        }
                        $redis->del($redis_push_time_key);
                    }else{
                        //获取任务拨打信息
                        $task_call_info = $this->get_task_call_info($value['task_id'], $value['fs_num']);
                        
                        \think\Log::record('新找回数据；$task_call_info'.json_encode($task_call_info));
                        //如果要拨打的号码大于0 且 fs中已经完成的号码数量 大于等于 总号码数量  且 member表中 已经拨打的号码数量 小于总拨打的数量
                        if( 
                          ($task_call_info['count_sum'] > 0 && $task_call_info['count_finish'] >= $task_call_info['count_sum'] && $web_call_number_count_member < $task_call_info['count_sum'] && (time() - $task_call_info['last_call_time']) > 180) 
                          ||
                          ($task_call_info['count_sum'] == $task_call_info['state_2_count'] && (time() - $task_call_info['last_call_time']) > 300)
                        ){
                            \think\Log::record('lujian#####3-----任务id为:' .$value['task_id']. '，总共号码:' .$web_totel_number_count. '已经拨打的号码' .$web_call_number_count_member. ',fs中总号码为' .$task_call_info['count_sum']. ',fs中已经拨打的号码数量为:' .$task_call_info['count_finish']. 'member表中已经完成的数量为' .$web_call_number_count_member);
                            //触发找回数据机制
                             $redis = RedisConnect::get_redis_connect();
                             //判断此任务的id 是不是已经在找回队列里面了  如果在 不再推送到队列了 如果不再 推送到队列中
                             $array_find = $redis->LRANGE('lose-tasks',0,-1);
                             $find_task_key = 'lose-tasks-'.$value['task_id'].'-'.$value['already_again_call_count'];
                             $find_task_value = $redis->get($find_task_key);
                             if(!in_array($value['task_id'],$array_find) && empty($find_task_value)){
                                  $redis->lpush('lose-tasks', $value['task_id']);
                                  $redis->setex($find_task_key, 1800, 1);
                                  $input_key = "lose-input-time-{$value['task_id']}";
                                  $redis->set($input_key, time() - 180);  //这里已经3分钟后再触发找回，找回函数也判断了3分钟，所以这里要-180
                             }
                            
                        }
                    }
                }

            }
        }
        echo '结束时间' . date('Y-m-d H:i:s') . "\n";
    
    }
    /**
     * 检查延迟处理的任务是否已完成
     *
     *
     */
    public function inspect_delay_task_whether_complete()
    {
        $redis = RedisConnect::get_redis_connect();
        $redis_key = 'inspect_task_whether_complete';

        $start_time = time();
        while ($task_data = $redis->rpop($redis_key)) {

            $task_data = json_decode($task_data, true);

            $redis_push_time_key = 'inspect_task_whether_complete_' . $task_data['task_id'];
            //号码的总数量
            $web_totel_number_count = Db::connect(config('master_db'))
                ->table('rk_member')
                ->where('task', $task_data['task_id'])
                ->count('uid');
            //已拨打的号码数量
            $web_call_number_count = Db::connect(config('master_db'))
                ->table('rk_member')
                ->where(['task' => $task_data['task_id'], 'status' => ['>=', 2]])
                ->count('uid');
            //检查web进度条是否走完
            if ($web_totel_number_count == $web_call_number_count) {

                //是否需要重新呼叫 是
                if ($task_data['is_again_call'] == 1 && $task_data['again_call_count'] > $task_data['already_again_call_count']) {
                    $again_call_status = explode(',', $task_data['again_call_status']);
                    $again_call_number_count_where = [
                        'task' => $task_data['task_id'],
                        'status' => ['in', $again_call_status]
                    ];
                    $again_call_number_count = Db::connect(config('master_db'))
                        ->table('rk_member')
                        ->where($again_call_number_count_where)
                        ->count('uid');
                    if ($again_call_number_count > 0) {
                        $this->again_call($task_data['task_id'], $again_call_status, $task_data['fs_num'], $task_data['member_id']);
                    } else {
                        $this->update_task_status($task_data['task_id'], 3, $task_data['fs_num']);
                    }
                    //否
                } else {
                    $this->update_task_status($task_data['task_id'], 3, $task_data['fs_num']);
                }
                $redis->del($redis_push_time_key);
            } else {
                $redis_push_time = $redis->get($redis_push_time_key);
                //判断是否已经3分钟 是
                if (time() >= ($redis_push_time + 180)) {
                    //触发找回数据机制
                    $redis = RedisConnect::get_redis_connect();
                    $redis->lpush('lose-tasks', $task_data['task_id']);
                    $input_key = "lose-input-time-{$task_data['task_id']}";
                    $redis->set($input_key, time() - 180);  //这里已经3分钟后再触发找回，找回函数也判断了3分钟，所以这里要-180
                }else{
                    $redis->lpush($redis_key, json_encode($task_data));
                }
            }

            if (time() - $start_time > 55) {
                exit;
            }

            //sleep(1);
        }
        exit;
    }
    /*
       * 包装curl函数
       *
       * $url  curl要抓取数据地址
       * $type curl抓取数据的类型 是post类型还是get类型
       * $res  抓取完成后，返回的数据类型 默认为json
       * $arr  如果是post类型 需要给post类型传递数据,数据是json形式的
       */
    public function http_curl($url, $type = 'get', $res = 'json', $arr = '')
    {
        //初始化curl
        $ch = curl_init();
        //设置curl的参数
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //判断传递数据是什么类型 get还是post
        if ($type == 'post') {
            //是否开启post传递
            curl_setopt($ch, CURLOPT_POST, 1);
            //传递数据 $arr是在post方式中要传递的数据 是json形式的
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
        }
        //超时设置
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        //采集数据 因为get根本不需要上面 那2行代码 所以 type=get的话直接就可以采集了
        $output = curl_exec($ch);
        //关闭curl
        curl_close($ch);
        //判断返回信息的类型 默认为json
        if ($res == 'json') {
            //请求成功
            return json_decode($output, true);//curl抓取的返回值 然后返回数组形式
        }

    }

    public function test()
    {
        $uid = 5993;
        $line_id = 287;
        // $data = Db::name('tel_order')
        // 				->field('id,call_money,money')
        // 				->where([
        // 					'owner'	=>	['=', $uid],
        // 					'member_id'	=>	['<>', $uid],
        // 					'call_phone_id'	=>	['=', $line_id],
        // 				])
        // 				->sum('technology_service_cost');
        // print_r($data);
        // foreach($data as $key=>$value){
        // 	// $tel_order = $value;
        // 	$tel_order['money'] = $value['money'] - $value['call_money'];
        // 	$tel_order['call_money'] = 0;
        // 	$result = Db::name('tel_order')
        // 						->where('id', $value['id'])
        // 						->update($tel_order);
        // 	if(empty($result)){
        // 		echo $value['id'] . '<br />';
        // 	}
        // }
        // print_r($data);
        Db::name('admin')
            ->where('id', $uid)
            ->setInc('money', 930.15);
        Db::name('admin')
            ->where('id', $uid)
            ->setDec('money', 310.05);
        // 930.15
        // print_r($data);
    }

    //检查用户余额是否充足
    public function inspect_user_balance()
    {
        \think\Log::record('inspect_user_balance_GG');
        // $task_ids =
        $redis = RedisConnect::get_redis_connect();
        $a = $redis->incrbyfloat('balance_' . 5555, 0.1);

        print_r($a);
        $b = $redis->get('balance_' . 5555);
        echo $b;
        // $redis->set('balance_' . 5555, 0);

    }

    public function get()
    {
        $redis = RedisConnect::get_redis_connect();
        $a = $redis->get('balance_' . 5555);
        echo $a;
    }

    /**
     * 找回丢失的数据
     * @param int $task_id 任务ID
     * @return bool
     */
    public function find_lose_datas()
    {
        header('Access-Control-Allow-Origin: *');
        //任务ID
        $task_id = input('task_id', '', 'trim,strip_tags');
        //计算总数
        $count = Db::name('member')->where(['task' => $task_id, 'status' => 1])->count('*');
        $fs_num = Db::name('tel_config')->where('task_id', $task_id)->value('fs_num');
        $where_in = [];
        //使用分页 因为如果大量丢失 where in 数组 会报错
        //一页的数量
        $pagesize = 5000;
        //计算总页数
        $sum_page = ceil($count / $pagesize);
        $result = [];
        //然后分页循环
        for ($i = 1; $i <= $sum_page; $i++) {
            $numbers = Db::name('member')->field('mobile')->where(['task' => $task_id, 'status' => 1])->page($i, $pagesize)->select();
            foreach ($numbers as $key => $value) {
                $where_in[] = $value['mobile'];
            }
            $numbers_data = Db::connect('db_configs.fs' . $fs_num)
                ->table('autodialer_number_' . $task_id)
                ->where('number', 'in', $where_in)
                ->select();
            /*
            id	int(11) 自动增量
            number	varchar(20)
            state	int(11) NULL
            description	varchar(255) NULL
            recycle	int(11) NULL
            callid	varchar(255) NULL
            calldate	datetime NULL
            calleridnumber	varchar(50) NULL
            answerdate	datetime NULL
            hangupdate	datetime NULL
            bill	int(11) NULL
            duration	int(11) NULL
            hangupcause	varchar(255) NULL
            bridge_callid	varchar(255) NULL
            bridge_number	varchar(20) NULL
            bridge_calldate	datetime NULL
            bridge_answerdate	datetime NULL
            recordfile	varchar(255) NULL
            status	varchar(100) NULL
            */
            foreach ($numbers_data as $key => $value) {
                //type=hangup&taskuuid=281&callid=dfbe52cd-febd-45f1-a79d-704670b1a2c6&number=18565636908&numberid=5012&calldatetime=2019-04-04 16:34:46&cause=NORMAL_TEMPORARY_FAILURE&code=200&bill=0&duration=20&da=&recordfile=&calldatetime=0000000000
                $result[] = 'type=' . $value['description'] . '&taskuuid=' . $task_id . '&callid=' . $value['callid'] . '&number=' . $value['number'] . '&numberid=' . $value['id'] . '&calldatetime=' . $value['calldate'] . '&cause=' . $value['hangupcause'] . '&bill=' . $value['bill'] . '&duration=' . $value['duration'] . '&da=' . $value['status'] . '&recordfile=' . $value['recordfile'] . '&calldatetime=0000000000';
            }
        }
        return $result;
    }

    /**
     * 找回丢失的数据 - 命令行，防止超时
     * 每分钟启动一次，启动后会把队列所有的数据消费掉后再停止
     * 因为用了队列，不会造成重复消费的情况
     *
     * 命令行启动方法：
     * # crontab -e  执行命令，增加一行
     * # 星号/1 * * * *  nohup php /www/wwwroot/127.0.0.1/index.php api/task/findLoseDataCommand  >> /www/wwwroot/127.0.0.1/data/log/find_lose_data.log 2>&1 &
     */
    public function findLoseDataCommand()
    {
          set_time_limit(0);
          ini_set('memory_limit', '1024M');
          $redis = RedisConnect::get_redis_connect();
          $task_id = $redis->rpop('lose-tasks');
          if ($task_id) {
              $input_key = "lose-input-time-{$task_id}";
              $input_time = $redis->get($input_key);
              if (empty($input_time)) {  //如果没有设置时间，重新推送回队列并设置时间
                  $redis->lpush('lose-tasks', $task_id);
                  $redis->set($input_key, time());
                  //echo $task_id . ":没有初始时间\n";
                  return;
              }
  
              //180秒后再找回数据，防止正在通话中的时候去找回数据导致的通话时长为0
              // $now = time();
              // if (($now - 180) < $input_time) {
              //     $redis->lpush('lose-tasks', $task_id);
              //     //echo $task_id . ":时间未到" . date('Y-m-d H:i:s') . "\n";
              //     return;
              // }
          } else {
              return;
          }
  
          $redis->del($input_key);  //正式开始找回前，删除时间的key
          //member表中已拨打的号码数量
          $web_call_number_count_member = Db::connect(config('master_db'))
                                        ->table('rk_member')
                                        ->where(['task' => $task_id, 'status' => ['>=', 2]])
                                        ->count('uid');
          $web_total_number_count_member = Db::connect(config('master_db'))
                                            ->table('rk_member')
                                            ->where(['task' => $task_id])
                                            ->count('uid');
          $fs_num = Db::name('tel_config')->where('task_id', $task_id)->value('fs_num');
          //获取任务拨打信息
          $task_call_info = $this->get_task_call_info($task_id, $fs_num);
          //fs的总号码数量 <= fs已接通的号码数量 且 web的总数量 > web已拨打的号码数量
          if($task_call_info['count_sum'] <= $task_call_info['state_2_count'] && $web_call_number_count_member < $web_total_number_count_member){
            
          }else{
            return false;
          }
          echo '[' . $task_id . ']开始找回 - ' . date('Y-m-d H:i:s') . "\n";
          //计算总数
          $where = [
              'task' => $task_id,
              'status' => 1
          ];
          $count = Db::name('member')->where($where)->count('uid');
          echo '[' . $task_id . ']总共丢失 - ' . $count . "\n";
          //FS下标
          $fs_num = Db::name('tel_config')->where('task_id', $task_id)->value('fs_num');
          //
          $where_in = [];
          //每页的条数
          $page_size = 1000;
          //计算总页数
          $sum_page = ceil($count / $page_size);
  
          //分批遍历丢失的数据
          for ($i = 1; $i <= $sum_page; $i++) {
              $where = [
                  'task' => $task_id,
                  'status' => 1
              ];
              $numbers = Db::name('member')->field('mobile')->where($where)->page($i, $page_size)->select();
              if (!$numbers) {
                  break;
              }
              $where_in = [];
              foreach ($numbers as $find_key => $find_value) {
                  $where_in[] = $find_value['mobile'];
              }
              $numbers_data = Db::connect('db_configs.fs' . $fs_num)
                  ->table('autodialer_number_' . $task_id)
                  ->where('number', 'in', $where_in)
                  ->select();
              //遍历需要找回的数据
              foreach ($numbers_data as $find_key => $find_value) {
                  //找回数据，不再直接写数据库，改为推入Redis，然后由消费服务器进行消费
                  $data = [
                      'type' => $find_value['description'],
                      'taskuuid' => $task_id,
                      'callid' => $find_value['callid'],
                      'number' => $find_value['number'],
                      'numberid' => $find_value['id'],
                      'calldatetime' => $find_value['calldate'],
                      'cause' => $find_value['hangupcause'],
                      'code' => 200,
                      'bill' => $find_value['bill'],
                      'duration' => $find_value['duration'],
                      'da' => $find_value['status'],
                      'recordfile' => $find_value['recordfile'],
                      'calleridnumber' => ''
                  ];
                  $data    = json_encode($data);
                  $redis->lpush('call-detail', $data);
                  /*
                  $_POST['type'] = $find_value['description'];
                  $_POST['taskuuid'] = $task_id;
                  $_POST['callid'] = $find_value['callid'];
                  $_POST['number'] = $find_value['number'];
                  $_POST['numberid'] = $find_value['id'];
                  $_POST['calldatetime'] = $find_value['calldate'];
                  $_POST['cause'] = $find_value['hangupcause'];
                  $_POST['code'] = 200;
                  $_POST['bill'] = $find_value['bill'];
                  $_POST['duration'] = $find_value['duration'];
                  $_POST['da'] = $find_value['status'];
                  $_POST['recordfile'] = $find_value['recordfile'];
                  $Smartivr = new Smartivr();
                  $a = $Smartivr->unusualNotify();
                  */
              }
          }
          //unset($Smartivr);
          echo '[' . $task_id . ']找回成功 - ' . date('Y-m-d H:i:s') . "\n";
  
          exit;
        }
     public function find()
    {
        $redis = RedisConnect::get_redis_connect();
        $task_id = input('task_id', '', 'trim,strip_tags');
        echo '[' . $task_id . ']开始找回 - ' . date('Y-m-d H:i:s') . "\n";
        //计算总数
        $where = [
            'task' => $task_id,
            'status' => 1
        ];
        $count = Db::name('member')->where($where)->count('uid');
        echo '[' . $task_id . ']总共丢失 - ' . $count . "\n";
        //FS下标
        $fs_num = Db::name('tel_config')->where('task_id', $task_id)->value('fs_num');
        //
        $where_in = [];
        //每页的条数
        $page_size = 1000;
        //计算总页数
        $sum_page = ceil($count / $page_size);

        //分批遍历丢失的数据
        for ($i = 1; $i <= $sum_page; $i++) {
            $where = [
                'task' => $task_id,
                'status' => 1
            ];
            $numbers = Db::name('member')->field('mobile')->where($where)->page($i, $page_size)->select();
            if (!$numbers) {
                break;
            }
            $where_in = [];
            foreach ($numbers as $find_key => $find_value) {
                $where_in[] = $find_value['mobile'];
            }
            $numbers_data = Db::connect('db_configs.fs' . $fs_num)
                ->table('autodialer_number_' . $task_id)
                ->where('number', 'in', $where_in)
                ->select();
                print_r($numbers_data);
            //遍历需要找回的数据
            foreach ($numbers_data as $find_key => $find_value) {
                //找回数据，不再直接写数据库，改为推入Redis，然后由消费服务器进行消费
                $data = [
                    'type' => $find_value['description'],
                    'taskuuid' => $task_id,
                    'callid' => $find_value['callid'],
                    'number' => $find_value['number'],
                    'numberid' => $find_value['id'],
                    'calldatetime' => $find_value['calldate'],
                    'cause' => $find_value['hangupcause'],
                    'code' => 200,
                    'bill' => $find_value['bill'],
                    'duration' => $find_value['duration'],
                    'da' => $find_value['status'],
                    'recordfile' => $find_value['recordfile'],
                    'calleridnumber' => ''
                ];
                $data    = json_encode($data);
                $redis->lpush('call-detail', $data);
                /*
                $_POST['type'] = $find_value['description'];
                $_POST['taskuuid'] = $task_id;
                $_POST['callid'] = $find_value['callid'];
                $_POST['number'] = $find_value['number'];
                $_POST['numberid'] = $find_value['id'];
                $_POST['calldatetime'] = $find_value['calldate'];
                $_POST['cause'] = $find_value['hangupcause'];
                $_POST['code'] = 200;
                $_POST['bill'] = $find_value['bill'];
                $_POST['duration'] = $find_value['duration'];
                $_POST['da'] = $find_value['status'];
                $_POST['recordfile'] = $find_value['recordfile'];
                $Smartivr = new Smartivr();
                $a = $Smartivr->unusualNotify();
                */
            }
        }
        //unset($Smartivr);
        echo '[' . $task_id . ']找回成功 - ' . date('Y-m-d H:i:s') . "\n";

        exit;
    }


    /**
     * 找回丢失的数据 - 命令行，防止超时
     * 每分钟启动一次，启动后会把队列所有的数据消费掉后再停止
     * 因为用了队列，不会造成重复消费的情况
     *
     * 命令行启动方法：
     * # crontab -e  执行命令，增加一行
     * # 星号/1 * * * *  nohup php /www/wwwroot/127.0.0.1/index.php api/task/findLoseDataCommand  >> /www/wwwroot/127.0.0.1/data/log/find_lose_data.log 2>&1 &
     */
    public function findLoseDataCommandById()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $redis = RedisConnect::get_redis_connect();
        $task_id = input('task_id','','trim,strip_tags');

        echo '[' . $task_id . ']开始找回 - ' . date('Y-m-d H:i:s') . "\n";
        //计算总数
        $where = [
            'task' => $task_id,
            'status' => 1
        ];
        $count = Db::name('member')->where($where)->count('uid');
        //FS下标
        $fs_num = Db::name('tel_config')->where('task_id', $task_id)->value('fs_num');
        //
        $where_in = [];
        //每页的条数
        $page_size = 100;
        //计算总页数
        $sum_page = ceil($count / $page_size);

        //分批遍历丢失的数据
        for ($i = 1; $i <= $sum_page; $i++) {
            $where = [
                'task' => $task_id,
                'status' => 1
            ];
            $numbers = Db::name('member')->field('mobile')->where($where)->page(1, $page_size)->select();
            $where_in = [];
            foreach ($numbers as $find_key => $find_value) {
                $where_in[] = $find_value['mobile'];
            }
            $numbers_data = Db::connect('db_configs.fs' . $fs_num)
                ->table('autodialer_number_' . $task_id)
                ->where('number', 'in', $where_in)
                ->select();
            //遍历需要找回的数据
            foreach ($numbers_data as $find_key => $find_value) {
                //找回数据
                $_POST['type'] = $find_value['description'];
                $_POST['taskuuid'] = $task_id;
                $_POST['callid'] = $find_value['callid'];
                $_POST['number'] = $find_value['number'];
                $_POST['numberid'] = $find_value['id'];
                $_POST['calldatetime'] = $find_value['calldate'];
                $_POST['cause'] = $find_value['hangupcause'];
                $_POST['code'] = 200;
                $_POST['bill'] = $find_value['bill'];
                $_POST['duration'] = $find_value['duration'];
                $_POST['da'] = $find_value['status'];
                $_POST['recordfile'] = $find_value['recordfile'];
                $Smartivr = new Smartivr();
                $a = $Smartivr->unusualNotify();
            }
        }
        unset($Smartivr);
        echo '[' . $task_id . ']找回成功 - ' . date('Y-m-d H:i:s') . "\n";

        exit;
    }

    /**
     * 找回丢失的数据
     */
    public function new_find_lose_datas()
    {

        $task_id = input('task_id', '', 'trim,strip_tags');
        //计算总数
        $where = [
            'task' => $task_id,
            'status' => 1
        ];
        $count = Db::name('member')->where($where)->count('uid');
        //FS下标
        $fs_num = Db::name('tel_config')->where('task_id', $task_id)->value('fs_num');
        //
        $where_in = [];
        //每页的条数
        $page_size = 5000;
        //计算总页数
        $sum_page = ceil($count / $page_size);
        //结果的容器
        $result = [];

        //分批遍历丢失的数据
        for ($i = 1; $i <= $sum_page; $i++) {
            $where = [
                'task' => $task_id,
                'status' => 1
            ];
            $numbers = Db::name('member')->field('mobile')->where($where)->page($i, $page_size)->select();
            foreach ($numbers as $find_key => $find_value) {
                $where_in[] = $find_value['mobile'];
            }
            $numbers_data = Db::connect('db_configs.fs' . $fs_num)
                ->table('autodialer_number_' . $task_id)
                ->where('number', 'in', $where_in)
                ->select();
            //遍历需要找回的数据
            foreach ($numbers_data as $find_key => $find_value) {
                //找回数据
                $_POST['type'] = $find_value['description'];
                $_POST['taskuuid'] = $task_id;
                $_POST['callid'] = $find_value['callid'];
                $_POST['number'] = $find_value['number'];
                $_POST['numberid'] = $find_value['id'];
                $_POST['calldatetime'] = $find_value['calldate'];
                $_POST['cause'] = $find_value['hangupcause'];
                $_POST['code'] = 200;
                $_POST['bill'] = $find_value['bill'];
                $_POST['duration'] = $find_value['duration'];
                $_POST['da'] = $find_value['status'];
                $_POST['recordfile'] = $find_value['recordfile'];
                $Smartivr = new Smartivr();
                $a = $Smartivr->unusualNotify();
            }
        }
        return true;
    }

    /**
     * 9点30之后释放
     */
    public function release_all_robot()
    {
        //将所有开启的任务更改为定时暂停
        $update_time = time();
        $update_date = date('Y-m-d H:i:s');
        $task_datas = Db::name('tel_config')->where('status', 1)->update([
            'status' => 5,
            'update_time' => $update_time
        ]);
        //获取所有fs服务器
        $freeswitchs = config('db_configs');
        if (empty($freeswitchs) || !is_array($freeswitchs)) {
            die('没有配置FS');
        }
        foreach ($freeswitchs as $key => $value) {
            if (empty($value)) {
                continue;
            }
            $result = Db::connect($value)->table('autodialer_task')
                ->where('start', 1)
                ->update([
                    'start' => 2,
                    'alter_datetime' => $update_date
                ]);
            if ($result) {
                echo "{$key}:success\n";
            } else {
                echo "{$key}:fail\n";
            }
        }

    }

    /**
     * 找回通话记录写入失败的数据 找到之后写入到Redis中
     */
    public function find_task_data_insert_tel_call_record()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $start_time = time();
        $task_id = 5003476;
        $sql = '
        SELECT
          *
        FROM
          `rk_member`
        WHERE
          mobile not in (
            SELECT mobile FROM `rk_tel_call_record` WHERE task_id = '.$task_id.'
          )
          and
          task = '.$task_id.'
      ';
        $datas = Db::query($sql);
        $redis = RedisConnect::get_redis_connect();
        foreach($datas as $key=>$value){
            $data = array();
            $data['status'] = $value['status'];
            $data['record_path'] = $value['record_path'];
            $data['level'] = $value['level'];
            $data['call_id'] = $value['call_id'];
            $data['originating_call'] = $value['originating_call'];
            $data['call_times'] = $value['call_times'];
            $data['affirm_times'] = $value['affirm_times'];
            $data['negative_times'] = $value['negative_times'];
            $data['neutral_times'] = $value['neutral_times'];
            $data['effective_times'] = $value['effective_times'];
            $data['hit_times'] = $value['hit_times'];
            $data['flow_label'] = $value['flow_label'];
            $data['knowledge_label'] = $value['knowledge_label'];
            $data['semantic_label'] = $value['semantic_label'];
            $data['duration'] = $value['duration'];
            $data['scenarios_id'] = $value['scenarios_id'];
            $data['last_dial_time'] = $value['last_dial_time'];
            $data['owner'] = $value['owner'];
            $data['mobile'] = $value['mobile'];
            $data['task_id'] = $value['task'];
            $data['invite_succ'] = 0;
            //判定通话记录是否生成
            $call_record_count = Db::name('tel_call_record')
                ->where([
                    'call_id'  =>  $value['call_id'],
                    'owner'  =>  $value['owner'],
                    'mobile'  =>  $value['mobile']
                ])
                ->count('id');
            if(empty($call_record_count)){
                // $record_id = Db::name('tel_call_record')->insertGetId($data);
                $redis->lpush('lose_tel_call_record', json_encode($data));
            }
        }
        echo '运行时间:' . (time() - $start_time) . '秒';
    }
    /**
     * 消费Redis的lose_tel_call_record队列中的数据
     */
    public function insert_call_record_data()
    {
        $start_time = time();
        $redis = RedisConnect::get_redis_connect();
        $redis_key = 'lose_tel_call_record';
        while($redis_data = $redis->rpop($redis_key)){
            $redis_data = json_decode($redis_data, true);
            $result = Db::name('tel_call_record')->insert($redis_data);
            if(empty($result)){
                $json_redis_data = json_encode($redis_data);
                echo '写入失败 - ' . $json_redis_data;
                $redis->lpush($redis_key, $json_redis_data);
            }
            if(time() - $start_time > 60){
                exit;
            }
        }
        if(time() - $start_time > 60){
            exit;
        }
    }
	  //包装curl函数
	/*
	 * $url  curl要抓取数据地址
	 * $type curl抓取数据的类型 是post类型还是get类型
	 * $res  抓取完成后，返回的数据类型 默认为json
	 * $arr  如果是post类型 需要给post类型传递数据,数据是json形式的
	*/
	public  function http_curl_wx($url,$type='get',$res='json',$arr=''){
		//初始化curl
		$ch=curl_init();
		//设置curl的参数
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		//判断传递数据是什么类型 get还是post
		if($type=='post'){
			//是否开启post传递
			curl_setopt($ch,CURLOPT_POST,1);
			//传递数据 $arr是在post方式中要传递的数据 是json形式的
			curl_setopt($ch,CURLOPT_POSTFIELDS,$arr);
		}
	  //采集数据 因为get根本不需要上面 那2行代码 所以 type=get的话直接就可以采集了
		$output=curl_exec($ch);
	  //判断返回信息的类型 默认为json
	  if($res=='json'){
		  if( curl_errno($ch) ){
			  //请求失败 返回错误信息
			  return  curl_error($ch);
		  }else{
			  //请求成功
			  return json_decode($output,true);//curl抓取的返回值 然后返回数组形式
		  }
	  }
	   //关闭curl
	  curl_close($ch);
	}

}
