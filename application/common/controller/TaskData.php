<?php
namespace app\common\controller;

use think\Db;
//Redis
use app\common\controller\RedisConnect;

class TaskData{
    /**
     * 返回fs服务器正在运行机器人数量最小的那个
     *
     * @return array[db => database, fs_num => int]
     */
    public function get_min_run_robot_count_fs_server($task_id)
    {

        $fs_configs = config('db_configs');      //所有FS配置
        $ignore_fs = config('ignore_fs') ?? [];  //忽略的FS号码
        $fs_servers = [];
        $fs_servers_datas = [];
        foreach($fs_configs as $key=>$value){
            if(empty($value)){
                unset($fs_configs[$key]);
                continue;
            }

            $fs_num = str_replace('fs', '', $key);
            if (!in_array($fs_num, $ignore_fs)) {
                $fs_servers[$key] = Db::connect($value);
            }

        }

        foreach($fs_servers as $key=>$value){
            try{
                $fs_servers_datas[$key] = $value->table('autodialer_task')->where('start', 1)->sum('maximumcall');
            }catch (\Exception $e) {
                continue;
            }
        }

        $fs_num = Db::name('tel_config')->where('task_id', $task_id)->value('fs_num');
        $redis_fs_num = 0;
        if(!empty($fs_num) || !empty($redis_fs_num)){
          return ['original_fs_num' => $fs_num];
        }

        if(!empty($fs_servers_datas)){
            $min_key = array_search(min($fs_servers_datas), $fs_servers_datas);
            $fs_num = str_replace('fs', '', $min_key);
            $result = ['db' => $fs_servers[$min_key], 'fs_num' => $fs_num];
        }else{
            $result=[];
        }

        return $result;

    }
    /**
     * 将web这边的任务数据写入到fs服务器的数据库上
     */
    public function insert_task_data_to_fs_server($task_id, $fs_server)
    {
        if(empty($task_id) || empty($fs_server)){
            return false;
        }
        //获取任务数据
        $task_data = Db::name('tel_config')->where('task_id', $task_id)->find();

        //获取线路ID
        $line_id=$this->getMinLineId($task_data['call_phone_group_id']);

        //获取线路数据
        $line_data = Db::name('tel_line')->where('id', $line_id)->find();

        $redis = RedisConnect::get_redis_connect();

        Db::name('tel_config')->where('task_id', $task_id)->update(['fs_num' => $fs_server['fs_num'],'call_phone_id'=>$line_id, 'call_prefix' =>  $line_data['call_prefix']]);
        $redis_key = 'task_id_fs_num_'.$task_id;
        $redis->setex($redis_key, (6 * 3600), $fs_server['fs_num']);


        //3.创建时间分组
        $timegroup = array();
        $timegroup['name'] = uniqid();
        $timegroup['domain'] = uniqid();
        $timegroup['member_id'] = $task_data['member_id'];
        $fs_server_db = $fs_server['db'];
        $timegroup_id = $fs_server_db->table('autodialer_timegroup')->insertGetId($timegroup);
        //4.创建排除时间
        $TimeRange = array();
        //8:00-22:00
        $SaveRange = [
            [
                'group_uuid'	=>	$timegroup_id,
                'member_id' => $task_data['member_id'],
                'begin_datetime'	=>	'00:00:00',
                'end_datetime'	=>	'07:59:59',
            ],
            [
                'group_uuid'	=>	$timegroup_id,
                'member_id'	=>	$task_data['member_id'],
                'begin_datetime'	=>	'22:01:00',
                'end_datetime'	=>	'23:59:59'
            ]
        ];
        $TRresult = $fs_server_db->table('autodialer_timerange')->insertAll($SaveRange);
        //5.创建FS端任务表
        // 任务表的
        $task = [];
        $task['uuid'] = $task_id;
        $task['name'] = $task_data['task_name'];
        $task['create_datetime'] = date("Y-m-d H:i:s",time());
        $task["alter_datetime"] = date("Y-m-d H:i:s",time());
        $task['disable_dial_timegroup'] = $timegroup_id;
        $task['member_id'] = $task_data['member_id'];
        $task['remark'] = '';
        //间隔时间
        if($line_data['type_link'] == 2){
            $task['call_pause_second'] = 10;
        }else{
            $task['call_pause_second'] = 0;
        }
        //呼叫结束后调用的接口
        $task['call_notify_url'] = config('notify_url');
        $task['start'] = 0;
        $task['call_notify_type'] = 2;
        $task['cache_number_count'] = 0;
        //每秒并发数
        if($line_data['type_link'] == 2){
            $task['call_per_second'] = 1;
        }else{
            $task['call_per_second'] = 80;
        }


        $task['destination_extension'] = $task_data['destination_extension'];


        $task['dial_format'] = $line_data['dial_format']??'';
        $task['_origination_caller_id_number'] = $line_data['phone']??'';
        $task['originate_variables'] = $line_data['originate_variables']??'';
        //空号检测的
        if (config('start_da2')){
            if (isset($task['originate_variables']) && $task['originate_variables']){
                $task['originate_variables'] = $task['originate_variables'].','.config('start_da2');
            }else{
                $task['originate_variables']  = config('start_da2');
            }
        }
        $task['destination_dialplan'] = "XML";
        $task['destination_context'] = "default";
        $task['maximumcall'] = $task_data['robot_cnt'];

        $count = $fs_server_db->table('autodialer_task')->insertGetId($task);
        if(empty($count)){
            \think\Log::record('创建FS端的任务失败');
        }
        //6.创建FS端的号码表
        $exc_result = $fs_server_db->execute("CREATE TABLE `autodialer_number_".$task_id."` (
 					`id` int(11) NOT NULL AUTO_INCREMENT,
 					`number` VARCHAR(20) NOT NULL,
 					`state` INT(11) NULL DEFAULT NULL,
 					`description` VARCHAR(255) NULL DEFAULT NULL,
 					`recycle` INT(11) NULL DEFAULT NULL,
 					`callid` VARCHAR(255) NULL DEFAULT NULL,
 					`calldate` DATETIME NULL DEFAULT NULL,
 					`calleridnumber` VARCHAR(50) NULL DEFAULT NULL,
 					`answerdate` DATETIME NULL DEFAULT NULL,
 					`hangupdate` DATETIME NULL DEFAULT NULL,
 					`bill` INT(11) NULL DEFAULT NULL,
 					`duration` INT(11) NULL DEFAULT NULL,
 					`hangupcause` VARCHAR(255) NULL DEFAULT NULL,
 					`bridge_callid` VARCHAR(255) NULL DEFAULT NULL,
 					`bridge_number` VARCHAR(20) NULL DEFAULT NULL,
 					`bridge_calldate` DATETIME NULL DEFAULT NULL,
 					`bridge_answerdate` DATETIME NULL DEFAULT NULL,
 					`recordfile` VARCHAR(255) NULL DEFAULT NULL,
 					`status` VARCHAR(100) NULL DEFAULT NULL,
 					 PRIMARY KEY (`id`)
 				)ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=2000");
 		\think\Log::record('kevin[TaskData182]'.json_encode($exc_result));
        //将号码写入到fs服务器中 - 分次导入
        $limit = 5000;
        $count = Db::name('member')->where('task', $task_id)->count('uid');
        $max_page = ceil($count / $limit);
        for($i = 1; $i <= $max_page; $i++){
            $numbers = Db::name('member')->where('task', $task_id)->field('mobile')->page($i, $limit)->order('uid asc')->select();
            $new_numbers = [];
            foreach($numbers as $key=>$value){
                $new_numbers[$key]['number'] = $value['mobile'];
            }
            $fs_server_db->table('autodialer_number_' . $task_id)->insertAll($new_numbers);
        }
        return true;
    }
    /**
     * 验证是否能够开启任务(余额是否充足)
     * 2019.5.25 鲁健修改
     *
     *
     */
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
     * 开启任务
     *
     * @param int $task_id 任务ID
     * @return bool
     */
    public function start_task($task_id)
    {
        if(empty($task_id)){
            return false;
        }
        $status = 1;
        $task = Db::name('tel_config')->where('task_id', $task_id)->find();
        $redis = RedisConnect::get_redis_connect();
        $redis_key = 'task_id_fs_num_'.$task_id;
        $redis_fs_num = $redis->get($redis_key);
        if(empty($task['fs_num']) && empty($redis_fs_num)){
            //选择fs目前运行机器人数量最小的服务器 并 将当前任务的数据写入进去
            $fs_server_data = $this->get_min_run_robot_count_fs_server($task_id);
            if(!empty($fs_server_data['original_fs_num'])){
              $fs_num = $fs_server_data['original_fs_num'];
            }else{
              $fs_num = $fs_server_data['fs_num'];
              $fs_db =  $fs_server_data['db'];
              $result = $this->insert_task_data_to_fs_server($task_id, $fs_server_data);
              if($result == true){
                  $redis->setex($redis_key, (6 * 3600), $fs_num);
              }
            }
        }else if(!empty($redis_fs_num)){
            $fs_num = $redis_fs_num;
        }else{
            $fs_num = $task['fs_num'];
        }

        $fs_db_config = config('db_configs.fs'.$fs_num);
        $fs_db = Db::connect($fs_db_config);

        //----------------------------------------------------------------------------------------------------------------
        //如果web这边的号码数量 > fs那边的号码数量
        $web_number_count = Db::name('member')->where('task', $task_id)->count('uid');
        $fs_number_count = $fs_db->table('autodialer_number_'.$task_id)->count('1');
        if($web_number_count > $fs_number_count){
            $fs_numbers = $fs_db->table('autodialer_number_'.$task_id)->column('number');
            $max_count = $web_number_count - $fs_number_count;
            $limit = 5000;
            $max_page = ceil($max_count / $limit);
            for($i = 1; $i <= $max_page; $i++){
                //查询web这边没有写入到fs的号码数量
                $web_numbers = Db::name('member')->where('task', $task_id)->where('mobile', 'not in', $fs_numbers)->page($i, $limit)->field('mobile as number')->order('uid asc')->select();
                //写入到fs
                $fs_db->table('autodialer_number_'.$task_id)->insertAll($web_numbers);
            }
        }
        //----------------------------------------------------------------------------------------------------------------


        //当前服务器正在运行的机器人
        $run_robot_count = $fs_db->table('autodialer_task')->where('start', 1)->sum('maximumcall');
        $max_workload = config('max_workload');
        $res = []; //返回结果
        if($max_workload - ($run_robot_count + $task['robot_cnt']) >= 0){
            // 没有超过峰值
            $fs_db->table('autodialer_task')->where('uuid', $task_id)->update([
                'start' =>  1,
                'alter_datetime'  =>  date('Y-m-d H:i:s', time() + 1)
            ]);
            return true;
        }
        return false;
    }


    /**
     * 获取当前线路组中最少的那个线路
     * 入参 线路组ID
     *
     * 返回获取的最小使用量线路的呼叫格式
     *
     * ----0621 修改为要按照轮询的方法来进行分配
     */
    public function getMinLineId($line_group_id){


        $line_group_info=$this->getLineGroupId($line_group_id);

        //按照一定顺序的线路排序 数组
        $sub_line_arr=Db::name('tel_line')->where(['group_id'=>$line_group_info['id'],'member_id'=>$line_group_info['user_id'] ])->field('id')->order('create_time desc')->select();
        $line_arr_length=count($sub_line_arr);

        //组合为ID
        $sub_line_arr=array_column($sub_line_arr,'id');//获取ID列
        $sub_line_ids_str=implode(',',$sub_line_arr);


        //获取使用量最小的那个线路
        $min=9999;$line_id='';
        if($line_arr_length<=0)return '';

//      foreach($sub_line_arr as $v){
//
//
//          $tmp=Db::name('tel_config')->where(['status'=>1,'call_phone_id'=>$v['id']])->field('sum(robot_cnt) s,call_phone_id')->find();
//
//          if(!$tmp || $min>$tmp['s']){$line_id=$v['id'];$min=$tmp['s']??0;}
//
//      }


        //获取使用本线路组 的最近 的那个任务数据
        $line_id_used=Db::name('tel_config')->where('call_phone_id','in',$sub_line_ids_str)->order('update_time desc')->limit(1)->value('call_phone_id');



        //轮询获取线路组内的线路
        $sequence=array_search( $line_id_used,$sub_line_arr);


        $line_id=$sub_line_arr [ ($sequence+1) % $line_arr_length ]??'';


        return  $line_id;

    }

    //返回的是跟线路组ID
    public function getLineGroupId($line_group_id){
        $group_info1 = $group_info=Db::name('tel_line_group')->where('id',$line_group_id)->find();
        while( !empty($group_info1) && $group_info1['line_group_pid']!=0){
            $group_info1 = Db::name('tel_line_group')->where('id',$group_info1['line_group_pid'])->find();
        }

        return $group_info1??[];
    }
}
