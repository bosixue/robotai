<?php
// +----------------------------------------------------------------------
// | RuiKeCMS [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.ruikesoft.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Wayne <wayne@ruikesoft.com> <http://www.ruikesoft.com>
// +----------------------------------------------------------------------

/**
 * select返回的数组进行整数映射转换
 *
 * @param array $map  映射关系二维数组  array(
 *                                          '字段名1'=>array(映射关系数组),
 *                                          '字段名2'=>array(映射关系数组),
 *                                           ......
 *                                       )
 * @author 朱亚杰 <zhuyajie@topthink.net>
 * @return array
 *
 *  array(
 *      array('id'=>1,'title'=>'标题','status'=>'1','status_text'=>'正常')
 *      ....
 *  )
 *
 */
if(function_exists('int_to_string') == false){
	function int_to_string(&$data, $map = array('status' => array(1 => '正常', -1 => '删除', 0 => '禁用', 2 => '未审核', 3 => '草稿'))) {
		if ($data === false || $data === null) {
			return $data;
		}
		$data = (array) $data;
		foreach ($data as $key => $row) {
			foreach ($map as $col => $pair) {
				if (isset($row[$col]) && isset($pair[$row[$col]])) {
					$data[$key][$col . '_text'] = $pair[$row[$col]];
				}
			}
		}
		return $data;
	}
}


/**
 * 获取对应状态的文字信息
 * @param int $status
 * @return string 状态文字 ，false 未获取到
 * @author huajie <banhuajie@163.com>
 */
function get_status_title($status = null) {
	if (!isset($status)) {
		return false;
	}
	switch ($status) {
	case -1:return '已删除';
		break;
	case 0:return '禁用';
		break;
	case 1:return '正常';
		break;
	case 2:return '待审核';
		break;
	default:return false;
		break;
	}
}

// 获取数据的状态操作
function show_status_op($status) {
	switch ($status) {
	case 0:return '启用';
		break;
	case 1:return '禁用';
		break;
	case 2:return '审核';
		break;
	default:return false;
		break;
	}
}

/**
 * 获取行为类型
 * @param intger $type 类型
 * @param bool $all 是否返回全部类型
 * @author huajie <banhuajie@163.com>
 */
function get_action_type($type, $all = false) {
	$list = array(
		1 => '系统',
		2 => '用户',
	);
	if ($all) {
		return $list;
	}
	return $list[$type];
}

/**
 * 获取行为数据
 * @param string $id 行为id
 * @param string $field 需要获取的字段
 * @author huajie <banhuajie@163.com>
 */
function get_action($id = null, $field = null) {
	if (empty($id) && !is_numeric($id)) {
		return false;
	}
	$list = cache('action_list');
	if (empty($list[$id])) {
		$map       = array('status' => array('gt', -1), 'id' => $id);
		$list[$id] = db('Action')->where($map)->field(true)->find();
	}
	return empty($field) ? $list[$id] : $list[$id][$field];
}

/**
 * 根据条件字段获取数据
 * @param mixed $value 条件，可用常量或者数组
 * @param string $condition 条件字段
 * @param string $field 需要返回的字段，不传则返回整个数据
 * @author huajie <banhuajie@163.com>
 */
function get_document_field($value = null, $condition = 'id', $field = null) {
	if (empty($value)) {
		return false;
	}

	//拼接参数
	$map[$condition] = $value;
	$info            = db('Model')->where($map);
	if (empty($field)) {
		$info = $info->field(true)->find();
	} else {
		$info = $info->value($field);
	}
	return $info;
}
/**
 * 根据条件获取表名
 * @param int $select_type
 * @author  xiangjinkai
 * @date 20190521
 */
function get_table_name($select_type){
    //选择表
    if(!$select_type){
        $select_type = 1;
    }
    //查询配置表（多少天）
    $config_table_days = config('call_table_days');
    if(!isset($config_table_days) || !is_numeric($config_table_days)){
        $config_table_days = 5;//默认5天
    }
    for($i = 1;$i <= $config_table_days;$i++){
        if($select_type == $i){
            $day = date('Ymd',time()-$i*24*60*60);
            $tablename = $tablename = 'tel_call_record_'.$day;
        }
    }
    return $tablename;
}
/**
 * 根据时间获取表名
 * @param int $select_time
 * @author  xiangjinkai
 * @date 20190521
 */
function get_table_name_by_time($select_time){
    //选择表
    if(!$select_time){
        $select_time = time();
    }
    $today = strtotime(date('Y-m-d',time()));
    //查询配置表（多少天）
    $config_table_days = config('call_table_days');
    if(!isset($config_table_days) || !is_numeric($config_table_days)){
        $config_table_days = 5;//默认5天
    }
    //转化成当天凌晨时间
    $select_day = date('Ymd',$select_time);
    for($i = 1;$i <= $config_table_days;$i++){
        $day = date('Ymd',$today-$i*24*60*60);
        if($day == $select_day){
            $tablename = $tablename = 'tel_call_record_'.$day;
        }
    }
    return $tablename;
}


/**
 * 根据条件获消费明细表
 * @param int $select_type
 * @author  xiangjinkai
 * @date 20190521
 */
function get_order_table_name($select_type){
    //选择表
    if(!$select_type){
        $select_type = 1;
    }
    //查询配置表（多少天）
    $select_type = $select_type -1;
    $config_table_days = config('order_table_days');
    if(!isset($config_table_days) || !is_numeric($config_table_days)){
        $config_table_days = 5;//默认5天
    }
    $tablename = 'tel_order';
    for($i = 0;$i <= $config_table_days;$i++){
        if($select_type == $i){
            if($select_type == 0){
                $tablename = 'tel_order';
            }else {
                $day = date('Ymd', time() - $i * 24 * 60 * 60);
                $tablename = $tablename = 'tel_order_' . $day;
            }
        }
    }
    return $tablename;
}


function get_date_name($type){
    if($type == 'record'){

        //查询配置表（多少天）
        $config_table_days = config('call_table_days');
        if(!isset($config_table_days) || !is_numeric($config_table_days)){
            $config_table_days = 5;//默认5天
        }
        for($i=1;$i<=$config_table_days;$i++){
            $month[$i.'month'] = date('n', time() - $i * 24 * 60 * 60) . '月';
            $data[$i.'days'] = $month[$i.'month'].date('j', time() - $i * 24 * 60 * 60) . '日';
        }

    }elseif($type == 'order') {
        //查询配置表（多少天）
        $config_table_days = config('order_table_days');
        if(!isset($config_table_days) || !is_numeric($config_table_days)){
            $config_table_days = 5;//默认5天
        }
        for($i=0;$i<=$config_table_days;$i++){
            if($i == 0){
                $data[$i . 'days'] = '当天';
            }else {
                $month[$i . 'month'] = date('n', time() - $i * 24 * 60 * 60) . '月';
                $data[$i . 'days'] = $month[$i . 'month'] . date('j', time() - $i * 24 * 60 * 60) . '日';
            }
        }
    }
    return $data;
}

//根据最后拨打时间获取表
function show_call_date($last_dial_time){
    $today = strtotime(date('Y-m-d',time()));

    if($last_dial_time){
        //查询配置表（多少天）
        $config_table_days = config('call_table_days');
        if(!$config_table_days || empty($config_table_days)){
            $config_table_days = 5;//默认5天
        }
        //转成凌晨时间
        $last_dial_date = date('Ymd',$last_dial_time);
        for($i=1;$i<=$config_table_days;$i++){
            $day = $today - $i * 24 * 60 * 60;
            if($day == $last_dial_date){
                $show_date = $i;
            }else{
                $show_date = 1;
            }
        }
    }else{
        $show_date = 1;
    }
    return $show_date;
}
//保留天数,以通话记录的天数为主
function get_show_day(){
    //查询配置表（多少天）
    $config_call_table_days = config('call_table_days');
    if(!isset($config_call_table_days) || !is_numeric($config_call_table_days)){
        $config_call_table_days = 5;//默认5天
    }

    $config_order_table_days = config('order_table_days');
    if(!isset($config_order_table_days) || !is_numeric($config_order_table_days)){
        $config_order_table_days = 5;//默认5天
    }

    return $config_call_table_days > $config_order_table_days ? $config_order_table_days : $config_call_table_days;
}