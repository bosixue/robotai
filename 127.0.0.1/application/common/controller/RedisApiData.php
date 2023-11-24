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
//数据库
use think\Db;
//Redis
use app\common\controller\RedisConnect;
use Overtrue\Pinyin\Pinyin;

class RedisApiData extends Base{

    public $cache_time = 300;
    public $redisConn;

    public function __construct()
    {
        $this->redisConn = RedisConnect::get_redis_connect();
    }

    //查询任务的rk_member表中的数据
    public function get_task_members($task_id)
    {
      if(empty($task_id)){
        return false;
      }
      $redis_key = 'task_'.$task_id.'_members';

      $datas = $this->redisConn->get($redis_key);
      if(!empty($datas)){
        $datas = json_decode($datas, true);
      }else{
        $datas = Db::name('member')->where('task', $task_id)->column('uid', 'mobile');
        $this->redisConn->set($redis_key, json_encode($datas));
      }
      return $datas;
    }

    /**
     * 通过任务ID和电话号码进行查询rk_member中的uid
     *
     * @param int $task_id 任务ID
     * @param int $phone 电话号码
     * @param int rk_member表中的uid
    */
    public function get_member_uid($task_id, $phone)
    {
      if(empty($task_id) || empty($phone)){
        return 0;
      }
      $members = $this->get_task_members($task_id);
      if(isset($members[$phone]) == true){
        $uid = $members[$phone];
      }else{
        $uid = Db::name('member')->where([
          'task'  =>  $task_id,
          'mobile'  =>  $phone
        ])->value('uid');
      }
      return $uid;
    }


    //查询ASR数据
    public function get_asr_find($asr_id)
    {
        if(empty($asr_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'asr_' . $asr_id . '_find';
        $asr_data = $redis->get($key);
        if(!empty($asr_data)){
            $asr_data = json_decode($asr_data, true);
        }else{
            $asr_data = Db::name('tel_interface')
                ->alias('ti')
                ->join('admin a', 'a.id = ti.owner', 'LEFT')
                ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                ->field('ti.id,ti.pid,ti.owner as member_id,ti.sale_price as sales_price,ar.name as role_name, ti.path, ti.asr_from, ti.asr_token')
                ->where('ti.id', $asr_id)
                ->find();
            // $asr_data = Db::name('tel_interface')->where('id', $asr_id)->find();
            $redis->setex($key, $this->cache_time, json_encode($asr_data));
        }
        return $asr_data;
    }

    //查询短信通道数据
    public function get_sms_channel_find($sms_id)
    {
        if(empty($sms_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'sms_channel_' . $sms_id . '_find';
        $sms_channel_data = $redis->get($key);
        if(!empty($sms_data)){
            $sms_channel_data = json_decode($sms_channel_data, true);
        }else{
            $sms_channel_data = Db::name('sms_channel')
                ->alias('sc')
                ->join('admin a', 'a.id = sc.owner', 'LEFT')
                ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                ->field('sc.id,sc.pid,sc.price as sales_price,sc.owner as member_id,ar.name as role_name')
                ->where('sc.id', $sms_id)
                ->find();
            $redis->setex($key, $this->cache_time, json_encode($sms_channel_data));
        }
        return $sms_channel_data;
    }

    //查询短信模板的数据
    public function get_sms_template_find($sms_template_id)
    {
        if(empty($sms_template_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'sms_template_' . $sms_template_id . '_find';
        $sms_template_data = $redis->get($key);
        if(!empty($sms_template_data)){
            $sms_template_data = json_decode($sms_template_data, true);
        }else{
            $sms_template_data = Db::name('sms_template')->where('id', $sms_template_id)->find();
            $redis->setex($key, $this->cache_time, json_encode($sms_template_data));
        }
        return $sms_template_data;
    }


    //查询用户数据
    public function get_user_find($user_id)
    {
        if(empty($user_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'admin_' . $user_id . '_find';
        $user_data = $redis->get($key);
        if(!empty($user_data)){
            $user_data = json_decode($user_data, true);
        }else{
            $user_data = Db::name('admin')
                ->alias('a')
                ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                ->field('a.id, a.pid,a.is_jizhang, a.username, ar.name as role_name, a.technology_service_price,a.money ,a.role_id')
                ->where('a.id', $user_id)
                ->find();
            $redis->setex($key, $this->cache_time, json_encode($user_data));
        }
        return $user_data;
    }

    //查询线路数据
    public function get_line_find($line_id)
    {
        if(empty($line_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'line_' . $line_id . '_find';
        $line_data = $redis->get($key);
        if(!empty($line_data)){
            $line_data = json_decode($line_data, true);
        }else{
            $line_data = Db::name('tel_line')
                ->alias('tl')
                ->join('admin a', 'a.id = tl.member_id', 'LEFT')
                ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                ->field('tl.*,ar.name as role_name')
                ->where('tl.id', $line_id)
                ->find();
            $redis->setex($key, $this->cache_time, json_encode($line_data));
        }
        return $line_data;
    }

    //查询线路数据
    public function get_line_group_find($line_group_id)
    {
      /*
      id	int(11) 自动增量
      user_id	int(11) NULL	所属用户的ID
      line_group_pid	int(11) NULL	上级线路组pid
      name	varchar(100) NULL	线路组名
      sales_price	decimal(13,5) NULL	线路价格
      status	tinyint(1) NULL [1]	1 激活  0、未激活
      remark	varchar(255) NULL	备注
      create_time	int(11) NULL	船舰事件
      */
        if(empty($line_group_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'line_group_' . $line_group_id . '_find';
        $line_data = $redis->get($key);
        if(!empty($line_data)){
            $line_data = json_decode($line_data, true);
        }else{
            $line_data = Db::name('tel_line_group')
                ->alias('tl')
                ->join('admin a', 'a.id = tl.user_id', 'LEFT')
                ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                ->field('tl.id, tl.user_id as member_id, tl.line_group_pid as pid, tl.name, tl.sales_price, tl.status, tl.remark, tl.create_time, ar.name as role_name')
                ->where('tl.id', $line_group_id)
                ->find();
            $redis->setex($key, $this->cache_time, json_encode($line_data));
        }
        return $line_data;
    }

    //查询任务数据
// 	public function get_task_find($task_id)
// 	{
// 	  if(empty($task_id)){
// 	    return false;
// 	  }
// 	  $redis = RedisConnect::get_redis_connect();
// 	  $key = 'task_' . $task_id . '_find';
// 	  $task_data = $redis->get($key);
// 	  if(!empty($task_data)){
// 	    $task_data = json_decode($task_data, true);
// 	  }else{
// 	    $task_data = Db::name('tel_config')->where('task_id', $task_id)->find();
// 	    $redis->setex($key, json_encode($task_data));
// 	  }
// 	  return $task_data;
// 	}

    //查询话术基础数据
    public function get_scenarios_find($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'scenarios_' . $scenarios_id . '_find';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name('tel_scenarios')->where(['id' => $scenarios_id, 'status' => 1])->find();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }
    //查询话术基础数据
    public function get_extension_find($extension_id)
    {
        if(empty($extension_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'extension_id_' . $extension_id . '_find';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name('tel_extension')->where(['id' => $extension_id, 'status' => 1])->find();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //获取话术中默认的用户未回复类型节点
    public function get_knowledge_8_default_find($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = $scenarios_id . '_knowledge_default_8_find';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name('tel_knowledge')
                ->where([
                    'scenarios_id'  =>  $scenarios_id,
                    'type'  =>  8,
                    'is_default'  =>  0
                ])
                ->find();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询话术配置数据
    public function get_scenarios_config_find($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'scenarios_config_' . $scenarios_id . '_find';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name('tel_scenarios_config')->where('scenarios_id', $scenarios_id)->find();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询话术第一个场景节点
    public function get_scenarios_node_0_find($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'scenarios_node_' . $scenarios_id . '_0_find';
        //->order('scen_node_id asc,pid asc')
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $where['scenarios_id'] = $scenarios_id;
            $where['type'] = 0;
            $data = Db::name("tel_scenarios_node")->where($where)->order('id asc ,sort desc')->find();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询话术场景节点
    public function get_scenarios_node_find($scenarios_id, $scenarios_node_id)
    {
      if(empty($scenarios_id) || empty($scenarios_node_id)){
        return false;
      }
      $redis = RedisConnect::get_redis_connect();
      $key = 'scenarios_node_'.$scenarios_node_id.'_find';
      $data = $redis->get($key);
      if(!empty($data)){
        $data = json_decode($data, true);
      }else{
        $where = [
          'scenarios_id'  =>  $scenarios_id,
          'id'  =>  $scenarios_node_id
        ];
        $data = Db::name('tel_scenarios_node')->where($where)->find();
        $redis->setex($key, $this->cache_time, json_encode($data));
      }
      return $data;
    }

    //查询指定话术场景节点的第一个流程节点
    public function get_flow_node_0_find($scenarios_node_id)
    {
        if(empty($scenarios_node_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'flow_node_' . $scenarios_node_id . '_0_find';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $where = [];
            $where['scen_node_id'] = $scenarios_node_id;
            $where['pid'] = 0;   //用开场白场景节点 的id 寻找开场白流程节点 pid=0为开场白流程
            $data = Db::name("tel_flow_node")->where($where)->find();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询节点中的语料
    public function get_tel_corpus_select($flow_id, $type = 0)
    {
        if(empty($flow_id)){
            return false;
        }
        if($type === ''){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'tel_corpus_' . $flow_id . '_' . $type . '_select';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name("tel_corpus")->where(array('src_id'=>$flow_id,'src_type'=>$type, 'audio'  =>  ['<>', 'undefined']))->select();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询语义标签
    public function get_semantics_label_select($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'semantics_label_' . $scenarios_id . '_select';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name('tel_label')->where(['scenarios_id' => $scenarios_id, 'type' => 0, 'label_status'  =>  1])->order('query_order', 'desc')->select();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询话术意向等级规则
    public function get_tel_intention_rule_select($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'tel_intention_rule_' . $scenarios_id . '_select';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name('tel_intention_rule_template')
                ->alias('tirt')
                ->join('tel_intention_rule tir', 'tirt.id = tir.template_id', 'LEFT')
                ->field('tir.type,tir.level,tir.rule')
                ->where([
                    'tirt.scenarios_id'  =>  $scenarios_id,
                    'tirt.status' =>  1
                ])
                ->order('tir.level desc')
                ->select();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
        // tel_intention_rule_[话术ID]_select
    }

    //查询指定节点下的所有分支
    public function get_flow_node_flow_branch_select($flow_node_id)
    {
        if(empty($flow_node_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'tel_flow_branch_' . $flow_node_id . '_select';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $where = array();
            $where['flow_id'] = $flow_node_id;
            $where['is_select'] = 1;
            $data = Db::name('tel_flow_branch')->where($where)->order('order_by asc')->select();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询指定话术的知识库节点
    public function get_knowledge_select($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'tel_knowledge_'. $scenarios_id .'_select';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $where = [];
            $where['scenarios_id'] = $scenarios_id;
            $data = Db::name('tel_knowledge')->where($where)->order('order_by desc')->select();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询指定流程节点
    public function get_flow_node_find($flow_id)
    {
        if(empty($flow_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'flow_node_' . $flow_id . '_find';
        $data = $redis->get($key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }else{
            $data = Db::name("tel_flow_node")->where('id', $flow_id)->find();
            $redis->setex($key, $this->cache_time, json_encode($data));
        }
        return $data;
    }

    //查询话术所有场景节点
    public function get_scenarios_node_select($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'scenarios_node_' . $scenarios_id . '_select';
        $scenarios_node_datas = $redis->get($key);
        if(!empty($scenarios_node_datas)){
            $scenarios_node_datas = json_decode($scenarios_node_datas, true);
        }else{
            $scenarios_node_datas = Db::name('tel_scenarios_node')->where('scenarios_id', $scenarios_id)->order('id asc ,sort desc')->select();
            $redis->setex($key, $this->cache_time, json_encode($scenarios_node_datas));
            // $one_key = 'scenarios_node_' . $scenarios_id . '_0_find';
            // $scenarios_node_0_find = $redis->get($one_key);
            // if(!empty($scenarios_node_datas) && count($scenarios_node_datas) > 0){
            //   $redis->setex($one_key, json_encode($scenarios_node_datas[0]));
            // }
        }
        return $scenarios_node_datas;
    }
    //获取任务配置数据
    public function get_task_find($task_id)
    {
        $redis = RedisConnect::get_redis_connect();
        $key = 'task_config_' . $task_id . '_find';
        $task_data = $redis->get($key);
        if(!empty($task_data)){
            $task_data = json_decode($task_data, true);
        }else{
            $task_data = Db::name('tel_config')->where('id', $task_id)->find();
            $redis->setex($key, $this->cache_time, json_encode($task_data));
        }
        return $task_data;
    }

    //查询当前用户的所有坐席用户
    public function get_seat_select($user_id)
    {
        if(empty($user_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'admin_'.$user_id.'_seat_select';
        $seats = $redis->get($key);
        if(!empty($seats)){
            $seats = json_decode($seats, true);
        }else{
            $seats = Db::name('admin')->where([
                'pid' =>  $user_id,
                'role_id' =>  20, //坐席角色ID = 20
            ])->select();
            $redis->setex($key, $this->cache_time, json_encode($seats));
        }
        return $seats;
    }

    //查询指定坐席用户的号码
    public function get_seat_number_select($user_id)
    {
        if(empty($user_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'saet_'.$user_id.'_number_select';
        $numbers = $redis->get($key);
        if(!empty($numbers)){
            $numbers = json_decode($numbers, true);
        }else{
            $numbers = Db::name('seat_transfer_numbers')
                ->field('number')
                ->where('member_id', $user_id)
                ->select();
            $redis->setex($key, $this->cache_time, json_encode($numbers));
        }
        return $numbers;
    }

    //获取通话过程中的一些数据
    public function get_flow_data($call_id)
    {
        if(empty($call_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'flow_data_' . $call_id;
        $flow_data = $redis->get($key);
        if(!empty($flow_data)){
            $flow_data = json_decode($flow_data, true);
        }
        return $flow_data;
    }
    //缓存通话过程中的一些数据
    public function set_flow_data($call_id, $flow_data)
    {
        if(empty($call_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'flow_data_' . $call_id;
        $redis->set($key, $flow_data);
        return true;
    }
    //删除通话过程中的缓存数据
    public function delete_flow_data($call_id)
    {
        if(empty($call_id)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $key = 'flow_data_' . $call_id;
        $redis->del($key);
        return true;
    }

    //预处理

    /**
     * @param $task_id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function task_preprocessing($task_id)
    {
        if(empty($task_id)){
            return false;
        }
        //加载任务配置数据
        $redis = RedisConnect::get_redis_connect();

        $task_config = Db::name('tel_config')->where('task_id', $task_id)->find();
        if(!empty($task_config)){
            $task_key = 'task_config_' . $task_id . '_find';
            $redis->setex($task_key, $this->cache_time, json_encode($task_config));
        }
        //缓存用户余额
        // $key = '';
        $scenarios_id = $task_config['scenarios_id'];
        //查询话术基础数据
        $scenarios_data = Db::name('tel_scenarios')->where('id', $scenarios_id)->find();
        if(!empty($scenarios_data)){
            $scenarios_key = 'scenarios_' . $scenarios_id . '_find';
            $redis->setex($scenarios_key, $this->cache_time, json_encode($scenarios_data));
        }

        //查询话术配置数据
        $scenarios_config_data = Db::name('tel_scenarios_config')->where('scenarios_id', $scenarios_id)->find();
        if(!empty($scenarios_config_data)){
            $scenarios_config_key = 'scenarios_config_' . $scenarios_id . '_find';
            $redis->setex($scenarios_config_key, $this->cache_time, json_encode($scenarios_config_data));
        }

        //查询话术的所有场景节点
        $scenarios_node_datas = Db::name('tel_scenarios_node')->where('scenarios_id', $scenarios_id)->order('id asc ,sort desc')->select();
        if(!empty($scenarios_node_datas) && count($scenarios_node_datas) > 0){
            // $one_key = 'scenarios_node_' . $scenarios_id . '_0_find';
            $key = 'scenarios_node_' . $scenarios_id . '_select';
            // $redis->setex($one_key, $this->cache_time, json_encode($scenarios_node_datas[0]));
            $redis->setex($key, $this->cache_time, json_encode($scenarios_node_datas));
        }

        //存储当个场景节点
        foreach($scenarios_node_datas as $sn_key=>$sn_value){
          $redis_key = 'scenarios_node_'.$sn_value['id'].'_find';
          $redis->setex($redis_key, $this->cache_time, json_encode($sn_value));
        }

        //查询话术的所有流程节点
        $flow_nodes = Db::name('tel_flow_node')->where('scenarios_id', $scenarios_id)->order('scen_node_id asc,pid asc')->select();
        $scenarios_node_flow_node = [];
        foreach($flow_nodes as $key=>$value){
            $scenarios_node_flow_node[$value['scen_node_id']][] = $value;
        }
        /*
        $where['scen_node_id'] = $scenariosNode['id'];
            $where['pid'] = 0;   //用开场白场景节点 的id 寻找开场白流程节点 pid=0为开场白流程
            $flowNode = Db::name("tel_flow_node")->where($where)->find();
        */
        $flow_ids = [];
        if(!empty($flow_nodes) && count($flow_nodes) > 0){
            foreach($flow_nodes as $key=>$value){
                $flow_ids[] = $value['id'];
                $key = 'flow_node_' . $value['id'] . '_find';
                $redis->setex($key, $this->cache_time, json_encode($value));
            }
            foreach($scenarios_node_flow_node as $key=>$value){
                $one_key = 'flow_node_'.$key.'_0_find';
                $redis->setex($one_key, $this->cache_time, json_encode($value[0]));
            }
        }

        //查询话术中所有知识库
        $key = 'tel_knowledge_' . $scenarios_id . '_select';
        $tel_knowledge = Db::name('tel_knowledge')->where('scenarios_id', $scenarios_id)->order('order_by desc')->select();
        $redis->setex($key, $this->cache_time, json_encode($tel_knowledge));

        //保存用户不说话处理
        foreach($tel_knowledge as $key=>$value){
            if($value['type'] == 8 && $value['is_default'] == 0){
                $redis_key = $scenarios_id . '_knowledge_default_8_find';
                $redis->setex($redis_key, $this->cache_time, json_encode($value));
                break;
            }
        }


        //查询话术中所有的语料
        $tel_corpus = Db::name('tel_corpus')->where('scenarios_id', $scenarios_id)->select();
        $tel_corpus_datas = [];
        foreach($tel_corpus as $key=>$value){
            $tel_corpus_datas['tel_corpus_' . $value['src_type'] . '_' . $value['src_id'] . '_select'][] = $value;
        }

        foreach($tel_corpus_datas as $key=>$value){
            $redis->setex($key, $this->cache_time, json_encode($value));
        }

        //读取rk_member表的uid和mobile
        $members = Db::name('member')->where('task', $task_id)->column('uid', 'mobile');
        $redis_key = 'task_'.$task_id.'_members';
        $redis->set($redis_key, json_encode($members));


        /*
        $where = array();
            $where['flow_id'] = $flowData['currFlowId'];
            $where['is_select'] = 1;
            $flowBranchs = Db::name('tel_flow_branch')->where($where)->order('type desc')->select();
        */
        //流程节点对应的分支节点
        $tel_flow_branches = Db::name('tel_flow_branch')->where(['flow_id' => ['in', $flow_ids], 'is_select'  =>  1])->order('order_by asc')->select();
        $tel_flow_branche_datas = [];
        foreach($tel_flow_branches as $key=>$value){
            $tel_flow_branche_datas['tel_flow_branch_' . $value['flow_id'] . '_select'][] = $value;
        }
        foreach($tel_flow_branche_datas as $key=>$value){
            $redis->setex($key, $this->cache_time, json_encode($value));
        }

        //话术中的意向等级
        $intention_rule = Db::name('tel_intention_rule_template')
            ->alias('tirt')
            ->join('tel_intention_rule tir', 'tirt.id = tir.template_id', 'LEFT')
            ->field('tir.type,tir.level,tir.rule')
            ->where([
                'tirt.scenarios_id'  =>  $scenarios_id,
                'tirt.status' =>  1
            ])
            ->order('tir.level desc')
            ->select();
        $key = 'tel_intention_rule_' . $scenarios_id . '_select';
        $redis->setex($key, $this->cache_time, json_encode($intention_rule));


        //查询话术中语义标签
        $semantics_labels = Db::name('tel_label')->where(['scenarios_id' => $scenarios_id, 'type' => 0, 'label_status'  =>  1])->order('query_order desc')->select();
        $key = 'semantics_label_' . $scenarios_id . '_select';
        $redis->setex($key, $this->cache_time, json_encode($semantics_labels));


        //查询线路数据
        $line_id = $task_config['call_phone_id'];
        $line_data = Db::name('tel_line')
            ->alias('tl')
            ->join('admin a', 'a.id = tl.member_id', 'LEFT')
            ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
            ->field('tl.*,ar.name as role_name')
            ->where('tl.id', $line_id)
            ->find();
        $key = 'line_' . $line_id . '_find';
        $redis->setex($key, $this->cache_time, json_encode($line_data));
        while(!empty($line_data['pid']))
        {
            $line_data = Db::name('tel_line')
                ->alias('tl')
                ->join('admin a', 'a.id = tl.member_id', 'LEFT')
                ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                ->field('tl.*,ar.name as role_name')
                ->where('tl.id', $line_data['pid'])
                ->find();
            $key = 'line_' . $line_data['id'] . '_find';
            $redis->setex($key, $this->cache_time, json_encode($line_data));
        }

        //查询ASR数据
        $asr_id = $task_config['asr_id'];
        $asr_data = Db::name('tel_interface')
            ->alias('ti')
            ->join('admin a', 'a.id = ti.owner', 'LEFT')
            ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
            ->field('ti.id,ti.pid,ti.owner as member_id,ti.sale_price as sales_price,ar.name as role_name, ti.asr_from, ti.path, ti.asr_from, ti.asr_token')
            ->where('ti.id', $asr_id)
            ->find();
        $key = 'asr_' . $asr_id . '_find';
        $redis->setex($key, $this->cache_time, json_encode($asr_data));
        while(!empty($asr_data['pid']))
        {
            $asr_data = Db::name('tel_interface')
                ->alias('ti')
                ->join('admin a', 'a.id = ti.owner', 'LEFT')
                ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                ->field('ti.id,ti.pid,ti.owner as member_id,ti.sale_price as sales_price,ar.name as role_name, ti.asr_from, ti.path, ti.asr_from, ti.asr_token')
                ->where('ti.id', $asr_data['pid'])
                ->find();
            $key = 'asr_' . $asr_data['id'] . '_find';
            $redis->setex($key, $this->cache_time, json_encode($asr_data));
        }

        //查询是否开启短信
        if(!empty($task_config['send_sms_status'])){
            // $sms_id = $task_config['sms_id'];
            //查询短信模板
            $sms_template_data = Db::name('sms_template')->where('id', $task_config['sms_template_id'])->find();

            $key = 'sms_template_' . $task_config['sms_template_id'] . '_find';
            // print_r($sms_template_data);
            // echo $key;
            // echo '<hr />';
            $redis->setex($key, $this->cache_time, json_encode($sms_template_data));

            //查询短信通道
            $sms_channel_id = $sms_template_data['channel_id'];
            $key = 'sms_channel_' . $sms_channel_id . '_find';
            $sms_channel_data = Db::name('sms_channel')->where('id', $sms_channel_id)->find();
            $redis->setex($key, $this->cache_time, json_encode($sms_channel_data));

            while(!empty($sms_channel_data['pid']))
            {
                $sms_channel_data =  Db::name('sms_channel')->where('id', $sms_channel_data['pid'])->find();
                $key = 'sms_channel_' . $sms_channel_data['id'] . '_find';
                $redis->setex($key, $this->cache_time, json_encode($sms_channel_data));
            }
        }

        //查询技术服务费
        $user_id = $task_config['member_id'];
        $user_data = Db::name('admin')
            ->alias('a')
            ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
            ->field('a.id, a.pid, a.username, ar.name as role_name, a.technology_service_price')
            ->where('a.id', $user_id)
            ->find();
        $key = 'user_technology_service_price_' . $user_id . '_find';
        $redis->setex($key, $this->cache_time, json_encode($user_data));

        while(!empty($user_data['pid']))
        {
            $user_data = Db::name('admin')
                ->alias('a')
                ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                ->field('a.id, a.pid, a.username, ar.name as role_name, a.technology_service_price')
                ->where('a.id', $user_data['pid'])
                ->find();
            $key = 'user_technology_service_price_' . $user_data['id'] . '_find';
            $redis->setex($key, $this->cache_time, json_encode($user_data));
        }
        return true;
    }

    public function resetTaskMinSeatId($task_id){

        $seatIds=Db::name('tel_config')->where(['task_id'=>$task_id])->value('add_crm_zuoxi');
        $name='Seats_ID_'.$task_id;
        if(!empty($seatIds) ){
            $seatIdsArr=explode(',',$seatIds);
            foreach ($seatIdsArr as $v){
                //分配坐席对应的 Hash值  初始化为0 意思为分配到意向客户数为0
                $this->redisConn->hSet( $name, (string)$v ,0);
            }
        }


    }




    //获取当前任务下面保存的分配坐席ID 分配的最少的那个坐席ID
    public function getTaskMinSeatId($task_id){
        if(empty($task_id)){
            return false;
        }

        $name='Seats_ID_'.$task_id;
        if(!$this->redisConn->hGetAll($name)){
            $this->resetTaskMinSeatId($task_id);
        }

        $seatValsArr=$this->redisConn->hGetAll($name);
        if(!$seatValsArr || count($seatValsArr) == 0 ){
            return false;
        }
        //获取值最小的KEY  并返回它
        return array_search( min($seatValsArr),$seatValsArr );

    }
    //对于坐席获取数自增一
    public function setTaskMinSeatId($task_id,$seat_id){

        $name='Seats_ID_'.$task_id;
        if(!$this->redisConn->hGetAll($name)){
            return false;
        }
        //设置该值加一
        return $this->redisConn->hIncrBy($name,$seat_id,1);
    }
    //修复话术数据
    public function repair_scenarios($scenarios_id)
    {
        if(empty($scenarios_id)){
            return false;
        }
        $pinyin = new Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
        //流程分支关键词中存在"null"
        $where = [
            'node.scenarios_id' =>  $scenarios_id,
            'branch.keyword' => 'null'
        ];
        $null_flow_branch = Db::name('tel_flow_branch')
            ->alias('branch')
            ->join('tel_flow_node node', 'node.id = branch.flow_id', 'LEFT')
            ->where($where)
            ->select();
        foreach($null_flow_branch as $key=>$value){
            Db::name('tel_flow_branch')->where('id', $value['id'])->update(['keyword'=>'']);
        }
        $where = [
            'node.scenarios_id' =>  $scenarios_id
        ];
        $flow_branchs = Db::name('tel_flow_branch')
            ->alias('branch')
            ->join('tel_flow_node node', 'node.id = branch.flow_id', 'LEFT')
            ->field('branch.id, branch.keyword')
            ->where($where)
            ->select();
        foreach($flow_branchs as $key=>$value){
            $keywords = explode(',', $value['keyword']);
            $new_keywords = [];
            foreach($keywords as $find_key=>$find_value){
                if(!empty($find_value)){
                    $new_keywords[] = $find_value;
                }
            }
            $new_keywords = implode(',', $new_keywords);
            $new_keywords_py = $pinyin->sentence($new_keywords);
            $update_data = [
                'keyword' =>  $new_keywords,
                'keyword_py'  =>  $new_keywords_py
            ];
            Db::name('tel_flow_branch')->where('id', $value['id'])->update($update_data);
        }

        //将知识库的type=0的数据修改成type=1
        $where = [
            'scenarios_id'  =>  $scenarios_id,
            'type'  =>  0
        ];
        Db::name('tel_knowledge')->where($where)->update(['type'=> 1]);
        //修复"用户不回答处理"节点
        $where = [
            'scenarios_id'  =>  $scenarios_id,
            'name'  =>  '用户不回答处理'
        ];
        Db::name('tel_knowledge')->where($where)->update([
            'type'  =>  8,
            'is_default'  =>  0
        ]);

        //修复"无法回答用户问题"节点
        $where = [
            'scenarios_id'  =>  $scenarios_id,
            'name'  =>  '无法回答用户问题'
        ];
        Db::name('tel_knowledge')->where($where)->update([
            'type'  =>  9,
            'is_default'  =>  0
        ]);

        //修复"连续3次无法回答用户问题"节点
        $where = [
            'scenarios_id'  =>  $scenarios_id,
            'name'  =>  '连续3次无法回答用户问题'
        ];
        Db::name('tel_knowledge')->where($where)->update([
            'type'  =>  10,
            'is_default'  =>  0
        ]);

        //修改"未听清楚"节点
        $where = [
            'scenarios_id'  =>  $scenarios_id,
            'name'  =>  '未听清楚'
        ];
        Db::name('tel_knowledge')->where($where)->update([
            'type'  =>  7,
            'is_default'  =>  0
        ]);
		//修复 跳转节点 pid=0的bug
        $node_tiaos = Db::name('tel_flow_node')->where(['scenarios_id'=>$scenarios_id,'type'=>1])->select();
        foreach($node_tiaos as $key=>$value){
          $count = Db::name('tel_flow_node')->where('scen_node_id', $value['scen_node_id'])->count('1');
          if($value['pid']==0 && $count > 1){
             Db::name('tel_flow_node')->where('id',$value['id'])->update(['pid'=>1]);
          }
        }
        //判断关键词如果为空时 给关键词加入默认的关键词
        // $where = [
        //   'type'  =>  ['in', [2, 3, 4, 5, 6]],
        //   'scenarios_id'  =>  0
        // ];
        // $defualt_keywords = Db::name('tel_knowledge')->where($where)->select();
        //


        return true;
    }

    /**
     * 用户某天到某天的消费统计汇总
     * @param str $son_id
     * @param int $start_time
     * @param int $end_time
     * return array
     */
    public function get_user_oneday_to_yestoday_consumption_statistics($son_id,$start_time,$end_time){
        if(empty($son_id) || empty($start_time) || empty($end_time)){
            return false;
        }
        $redis = RedisConnect::get_redis_connect();
        $ids = implode('',$son_id);
        $key = 'con_statistics_' . $ids .$start_time.$end_time.'_select';
        $data = $redis->get($key);
        if(!empty($data)){
            $before_statistics = json_decode($data, true);
        }else{
            $before_statistics = Db::name('consumption_statistics')
                ->field('sum(call_count) as call_count,sum(connect_count) as connect_count,sum(charging_duration) as charging_duration,sum(asr_count) as asr_count,sum(send_sms_count) as send_sms_count,sum(sms_cost) as sms_cost,sum(robot_cost) as robot_cost,sum(connect_cost) as connect_cost,sum(asr_cost) as asr_cost,sum(technology_service_cost) as technology_service_cost,sum(total_cost) as total_cost,sum(duration) as duration')
                ->where('member_id','in',$son_id)
                ->where('date','between time',[$start_time,$end_time])
                ->where('type','day')
                ->find();
            //$before_statistics['duration'] = Db::name('tel_order')->force('index_owner_create_time_duration')->where('owner','in',$son_id)->where('create_time','between time',[$start_time,$end_time])->sum('duration');

            $redis->setex($key, '86400', json_encode($before_statistics));
        }
        return $before_statistics;
    }
}
