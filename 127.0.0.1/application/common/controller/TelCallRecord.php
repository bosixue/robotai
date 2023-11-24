<?php
namespace app\common\controller;

use think\Db;

//日志
use app\common\controller\Log;


//通话记录处理类
class TelCallRecord extends Base{

    /**
     * 创建表
     *
     * @param int $task_id
     * @return bool
     */
    public function create_table($task_id)
    {
        $task_name = 'rk_tel_call_record_' . $task_id;
        $result = Db::execute("CREATE TABLE `".$task_name."` (
									  `id` int(11) NOT NULL AUTO_INCREMENT,
									  `owner` int(11) DEFAULT NULL,
									  `mobile` varchar(32) DEFAULT NULL,
									  `task_id` int(11) NOT NULL COMMENT '任务Id',
									  `scenarios_id` int(11) NOT NULL COMMENT '话术Id',
									  `call_id` varchar(100) DEFAULT NULL,
									  `review` tinyint(1) DEFAULT '0' COMMENT '是否已经查看',
									  `record_path` varchar(255) DEFAULT NULL,
									  `originating_call` varchar(32) DEFAULT NULL COMMENT '主叫号码 ',
									  `affirm_times` tinyint(2) DEFAULT '0' COMMENT '肯定次数',
									  `negative_times` tinyint(2) DEFAULT '0' COMMENT '否定次数',
									  `neutral_times` tinyint(2) DEFAULT '0' COMMENT '中性次数',
									  `effective_times` tinyint(2) DEFAULT '0' COMMENT '有效次数',
									  `hit_times` tinyint(2) DEFAULT '0' COMMENT '命中次数',
									  `flow_label` varchar(255) DEFAULT NULL COMMENT '流程标签',
									  `call_times` tinyint(2) DEFAULT '0' COMMENT '客户说话次数',
									  `knowledge_label` varchar(255) DEFAULT NULL COMMENT '问答标签',
									  `semantic_label` varchar(255) DEFAULT NULL COMMENT '语义标签',
									  `status` tinyint(2) DEFAULT NULL COMMENT '会员状态，0未拨打 1拨打排队中  2已接通  3未接听挂断/关机/欠费',
									  `level` tinyint(2) DEFAULT '0' COMMENT '等级',
									  `last_dial_time` int(11) DEFAULT NULL,
									  `duration` tinyint(4) DEFAULT '0' COMMENT '时长',
									  `invitation` tinyint(1) DEFAULT '0' COMMENT '邀约状态',
									  PRIMARY KEY (`id`) USING BTREE
									) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
									");
        if(!empty($result)){
            return true;
        }
        return false;
    }

    /**
     * 获取数据
     *
     * @param array $args
     * @param int $page default 1
     * @param int $limit default 10
     */
    public function get($args, $page, $limit)
    {
        if(empty($page)){
            $page = 1;
        }
        if(empty($limit)){
            $limit = 10;
        }
        $user_auth = session('user_auth');
        $result = $this->screent($args, $page, $limit);
        $table = $result['table'];//数据
        $count = $result['count'];//总数
        $data = [];
        $data['list'] = $table;
        $data['count'] = $count;
        $data['total'] = ceil($data['count']/$limit);
        $data['Nowpage'] = $page;
        $data['limit'] = $limit;
        $data['args'] = $args;
        $data['sql'] = $result['sql'];
        \think\Log::record('呼叫管理查询');
        return $data;
    }




    public function screent($args, $page, $limit){
        $user_auth = session('user_auth');
        $table = Db::name('tel_call_record')
            ->field('tcr.id,tcr.mobile,tcr.duration,tcr.status,tcr.level,tcr.last_dial_time,tcr.task_id,state_crm,tcr.scenarios_id,tcr.review,tcr.record_path')
            ->alias('tcr');
        //->join('tel_scenarios ts', 'tcr.scenarios_id = ts.id', 'LEFT')
        //->join('tel_config tc', 'tc.task_id = tcr.task_id', 'LEFT');
        $count = Db::name('tel_call_record')
            ->alias('tcr');
        //->join('tel_scenarios ts', 'tcr.scenarios_id = ts.id', 'LEFT')
        //->join('tel_config tc', 'tc.task_id = tcr.task_id', 'LEFT');
        //今天凌晨时间戳
        $where = [];
        $connect_where = [];
        $start = strtotime(date('Y-m-d',time()));
        //明天凌晨时间戳
        //$end = strtotime(date('Y-m-d',strtotime('+1 day')));
        // $where['tc.create_time'] = [['>=',$start],['<',$end],'and'];

        $connect_where['tcr.owner'] = ['=', $user_auth['uid']];
        //筛选任务
        if(isset($args['task_id']) === true && !empty($args['task_id'])){
            $connect_where['tcr.task_id'] = ['=', $args['task_id']];
        }
        //筛选话术
        if(isset($args['scenarios_id']) === true && !empty($args['scenarios_id'])){
            $connect_where['tcr.scenarios_id'] = ['=', $args['scenarios_id']];
        }
        //只显示当天时间的记录
        // $connect_where['tcr.last_dial_time'] = ['>',$start];
        //筛选开始拨打时间 和 结束拨打时间
        if(isset($args['start_call_time']) === true && !empty($args['start_call_time']) && isset($args['end_call_time']) === true && !empty($args['end_call_time'])){
            $args['start_call_time'] = strtotime($args['start_call_time']);
            $args['end_call_time'] = strtotime($args['end_call_time']);
            $connect_where['tcr.last_dial_time'] = [['>=', $args['start_call_time']], ['<=', $args['end_call_time']]];
        }else{
            if(isset($args['start_call_time']) === true && !empty($args['start_call_time'])){
                $args['start_call_time'] = strtotime($args['start_call_time']);
                $connect_where['tcr.last_dial_time'] = ['>=', $args['start_call_time']];
            }elseif(isset($args['end_call_time']) === true && !empty($args['end_call_time'])){
                $args['end_call_time'] = strtotime($args['end_call_time']);
                $connect_where['tcr.last_dial_time'] = ['<=', $args['end_call_time']];
            }
        }
        //电话号码
        if(isset($args['phone']) === true && !empty($args['phone'])){
            $connect_where['tcr.mobile'] = ['like', '%'.$args['phone'].'%'];
        }
        //是否已查看
        if(isset($args['review']) === true ){
            $connect_where['tcr.review'] = ['=', $args['review']];
        }
        //意向标签   修改为多选
        if(isset($args['level']) === true && !empty($args['level'])){
            $where['tcr.level'] = ['in', $args['level']];
            //$whereOrlevel['tcr.level'] = ['in', $args['level']];
        }



        //通话状态
        if(isset($args['status']) === true && is_array($args['status']) && count($args['status']) > 0){
            $where['tcr.status'] = ['in', $args['status']];
        }
        //最小通话时长 和 最大通话时长
        if(isset($args['min_duration']) === true && $args['min_duration'] != '' && isset($args['max_duration']) && $args['max_duration'] != ''){
            $where['tcr.duration'] = [['>=', $args['min_duration']], ['<=', $args['max_duration']]];
        }else{
            if(isset($args['min_duration']) === true && $args['min_duration'] != ''){
                $where['tcr.duration'] = ['>=', $args["min_duration"]];
            }elseif(isset($args['max_duration']) === true && $args['max_duration'] != ''){
                $where['tcr.duration'] = ['<=', $args['max_duration']];
            }
        }
        $whereTalkTime=[];
        //说话次数
        if(isset($args['call_times']) === true && $args['call_times'] != '' ){
            $whereTalkTime[]=  ['tcr.call_times'=>[$args['call_times_sel'], $args['call_times']]];
        }
        //有效对话次数
        if(isset($args['effective_times']) === true && $args['effective_times'] != ''){
            $whereTalkTime[]= ['tcr.effective_times'=>[$args['effective_times_sel'], $args['effective_times']]];
        }
        //触发问题次数
        if(isset($args['hit_times']) === true && $args['hit_times'] != ''){
            $whereTalkTime[]=   ['tcr.hit_times'=>[$args['hit_times_sel'], $args['hit_times']] ];
        }

        $whereAttitude=[];
        //肯定次数
        if(isset($args['affirm_times']) === true && $args['affirm_times'] != ''){
            $whereAttitude[]= [ 'tcr.affirm_times'=>[ $args['affirm_times_sel'], $args['affirm_times']  ]];
        }
        //中性次数
        if(isset($args['neutral_times']) === true && $args['neutral_times'] != ''){
            $whereAttitude[]= [ 'tcr.neutral_times'=>[ $args['neutral_times_sel'], $args['neutral_times']  ]];
        }
        //否定次数
        if(isset($args['negative_times']) === true && $args['negative_times'] != ''){
            $whereAttitude[]= [ 'tcr.negative_times'=>[ $args['negative_times_sel'], $args['negative_times'] ] ];
        }
        //是否邀约成功
        if(isset($args['invitation']) == true && $args['invitation'] != ''){
            $whereAttitude[]=['tcr.invitations' => [ '>=',$args['invitation'] ] ]; //待处理
        }
        $subWhere = [];
        //流程标签
        if(isset($args['flow_label']) === true && is_array($args['flow_label']) && count($args['flow_label'])){
            $flow_label_str = ',('.implode('|', $args['flow_label']).'),';
            $subWhere[] = "concat(',',tcr.flow_label,',') regexp '".$flow_label_str."'";
        }
        //语义标签
        if (isset($args['semantic_label']) === true && is_array($args['semantic_label']) && count($args['semantic_label'])){
            $semantic_label_str = ',('.implode('|', $args['semantic_label']).'),';
            $subWhere[] = "concat(',',tcr.semantic_label,',') regexp '".$semantic_label_str."'";
        }
        //问答标签
        if (isset($args['knowledge_label']) === true && is_array($args['knowledge_label']) && count($args['knowledge_label'])){
            $knowledge_label_str = ',('.implode('|', $args['knowledge_label']).'),';
            $subWhere[] = "concat(',',tcr.knowledge_label,',') regexp '".$knowledge_label_str."'";
        }

        $subWhereStr = '';
        // foreach($subWhere as $key=>$value){
        // 	if($key != 0){
        // 		$subWhereStr .= ' or ';
        // 	}
        // 	$subWhereStr .= $value;
        // }

        if(count($connect_where) > 0){
            $table = $table->where($connect_where);
            $count = $count->where($connect_where);
        }
        //排重(四个字段可以排除重复)
        //$condition = "tcr.id = (select min(`A`.id) from rk_tel_call_record as A where  `tcr`.mobile=`A`.mobile and `tcr`.task_id = `A`.task_id and `tcr`.scenarios_id = `A`.scenarios_id and `tcr`.last_dial_time = `A`.last_dial_time)";
        //$table = $table->where($condition);
        if(count($where) > 0){
            $table = $table->where($where);
            $count = $count->where($where);
        }
        //对于同一类型的数据内部是 Or 的关系
        if(count($whereTalkTime)>0 ){


            $table = $table->where(function($query)use($whereTalkTime){
                foreach($whereTalkTime as $v){
                    $query->whereOr($v);
                }
            });
            $count = $count->where(function($query)use($whereTalkTime){
                foreach($whereTalkTime as $v){
                    $query->whereOr($v);
                }
            });

        }

        if(count($whereAttitude)>0 ){
            $table = $table->where(function($query)use($whereAttitude){
                foreach($whereAttitude as $v){
                    $query->whereOr($v);
                }
            });
            $count = $count->where(function($query)use($whereAttitude){
                foreach($whereAttitude as $v){
                    $query->whereOr($v);
                }
            });

        }


        if(count($subWhere) > 0){
            foreach($subWhere as $key=>$value) {
                $table = $table->where($value);
                $count = $count->where($value);
            }
            /*//所有的数据 检索 改为 且的关系  0328  章俊
             * foreach($subWhere as $key=>$value){
                $whereOr = [
                    $value,
                    $connect_where
                ];
                $table = $table->whereOr(function($query) use($value, $connect_where){
                    $query->where($value)->where($connect_where);
                });
                $count = $count->whereOr(function($query) use($value, $connect_where){
                    $query->where($value)->where($connect_where);
                });
            }*/
        }



        /*
        if(isset($args['order']) === true && is_array($args['order']) === true && count($args['order']) == 2){
            $orderby = $args['order']['orderby'];
            $order = $args['order']['order'];
            if($orderby == 'duration'){
                $orderby = 'duration';
            }else if($orderby == 'call_time'){
                $orderby = 'last_dial_time';
            }else{
                $orderby = 'last_dial_time';
            }
            if($order != 'desc' && $order != 'asc'){
                $order = 'desc';
            }
            $table = $table->order($orderby.' '.$order);
        }else{
            $table = $table->order('last_dial_time desc');
        }
		*/



        if($page != 0){
            //筛选排序类型 type  duration通话时长  or call_time 最后拨打时间
            if(isset($args['type']) === true && !empty($args['type'])){
                //筛选排序 方式 desc or asc
                if(isset($args['order']) === true && !empty($args['order'])){
                    if($args['type'] == "duration"){
                        $table_ = $table->page($page, $limit)->order(['duration'=>$args['order'],'id'=>$args['order']])->select();
                    }elseif($args['type']=="call_time"){
                        $table_ = $table->page($page, $limit)->order(['last_dial_time'=>$args['order'],'id'=>$args['order']])->select();
                    }
                }else{
                    //第一次进来按照最后拨打时间排序
                    $table_ = $table->page($page, $limit)->order(['last_dial_time'=>'desc','id'=>'desc'])->select();
                }
            }else{
                //第一次进来按照最后拨打时间排序
                $table_ = $table->page($page, $limit)->order(['last_dial_time'=>'desc','id'=>'desc'])->select();
            }
        }else{
            //筛选排序类型 type  duration通话时长  or call_time 最后拨打时间
            if(isset($args['type']) === true && !empty($args['type'])){
                //筛选排序 方式 desc or asc
                if(isset($args['order']) === true && !empty($args['order'])){
                    if($args['type']=="duration"){
                        $table_ = $table->order(['duration'=>$args['order'],'id'=>$args['order']])->select();
                    }elseif($args['type']=="call_time"){
                        $table_ = $table->order(['last_dial_time'=>$args['order'],'id'=>$args['order']])->select();
                    }
                }else{
                    //第一次进来按照最后拨打时间排序
                    $table_ = $table->order(['last_dial_time'=>'desc','id'=>'desc'])->select();
                }
            }else{
                //第一次进来按照最后拨打时间排序
                $table_ = $table->order(['last_dial_time'=>'desc','id'=>'desc'])->select();
            }
        }


        $sql = $table->getLastSql();
        $count = $count->count(1);
        //查询任务表
        $tel_config_table = Db::name('tel_config tc')->field('tc.task_id,tc.create_time,tc.task_name')->select();

        $new_tel_config_table = [];
        if($tel_config_table){
            foreach ($tel_config_table as $key=>$val){
                $new_tel_config_table[$val['task_id']] = $val;
            }
        }
        //查询话术表
        $tel_scenarios_table = Db::name('tel_scenarios ts')->field('ts.id,ts.name scenarios_name')->select();
        $new_tel_scenarios_table = [];
        if($tel_scenarios_table){
            foreach ($tel_scenarios_table as $key=>$val){
                $new_tel_scenarios_table[$val['id']] = $val;
            }
        }
        $c_keys = array_keys($new_tel_config_table);
        $s_keys = array_keys($new_tel_scenarios_table);

        foreach ($table_ as $key => $value) {
            $keys_c  = $value['task_id'];
            $keys_s  = $value['scenarios_id'];
            $table_[$key]['last_dial_time'] = date('Y-m-d H:i:s', $value['last_dial_time']);
            if(in_array($keys_c,$c_keys)) {
                $table_[$key]['task_name'] = $new_tel_config_table[$keys_c]['task_name'];
                $table_[$key]['create_time'] = $new_tel_config_table[$keys_c]['create_time'];
            }else{
                $table_[$key]['task_name'] = '';
                $table_[$key]['create_time'] = '';
            }
            if(in_array($keys_s,$s_keys)){
                $table_[$key]['scenarios_name'] = $new_tel_scenarios_table[$keys_s]['scenarios_name'];
            }else{
                $table_[$key]['scenarios_name'] = '';
            }
        }
        //释放内存
        unset($tel_config_table,$new_tel_config_table,$tel_scenarios_table,$new_tel_scenarios_table);
        return [
            'table'	=>	$table_,
            'count'	=>	$count,
            'sql'=>$sql
        ];
    }

    /**
     * 获取通话记录ID
     *
     * @param int $mobile
     * @param int $task_id
     * @return int
     */
    public function get_id($mobile, $task_id)
    {
        if(empty($mobile) || empty($task_id)){
            return false;
        }
        $id = Db::name('tel_call_record')
            ->where([
                'mobile'	=>	$mobile,
                'task_id'	=>	$task_id
            ])
            ->value('id');
        return $id;
    }



}
