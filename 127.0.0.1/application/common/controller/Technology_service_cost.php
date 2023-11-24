<?php
namespace app\common\controller;

use think\Db;

//日志
use app\common\controller\Log;


//技术服务费用
class Technology_service_cost extends Base{
  
  //表名
  public $table_name = 'technology_service_charging_statistics';
  
  
  /**
   * 获取技术服务费用每日统计数据
   * 
   * @param int $page 页码
   * @param int $limit 条数
   * @param array $args 筛选参数
  */
  public function get_datas($page, $limit, $args = [])
  {
    if(empty($page)){
      $page = 1;
    }
    if(empty($limit)){
      $limit = 10;
    }
    
    $user_auth = session('user_auth');
    $where = [];
    $where['main.member_id'] = $user_auth['uid'];
    if(is_array($args) === true){
      foreach($args as $key=>$value){
        if($key == 'role_name'){
          $where['ar.name'] = ['like', '%'.$value.'%'];
        }else if($key == 'username'){
          $where['a.username'] = ['like', '%'.$value.'%'];
        }
      }
    }
    $datas = Db::name($this->table_name)
              ->alias('main')
              ->field('main.*, a.username, ar.name as role_name')
              ->join('admin a', 'a.id = main.find_member_id', 'LEFT')
              ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
              ->where($where)
              ->page($page, $limit)
              ->order('date', 'desc')
              ->select();
    $i = ($page - 1) * $limit + 1;
    foreach($datas as $key=>$value){
      $datas[$key]['key'] = $i;
      $i++;
    }
    $count = Db::name($this->table_name)
              ->alias('main')
              ->join('admin a', 'a.id = main.member_id', 'LEFT')
              ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
              ->where($where)
              ->count('main.id');
    $result = ['data' =>  $datas, 'count' =>  $count];
    return $result;
  }
  
  
  /**
   * 获取技术服务费用合计
   * 
  */
  public function get_total_data()
  {
    $user_auth = session('user_auth');
    $total_data = Db::name($this->table_name)
                  ->field('sum(duration) as duration, sum(cost_price_statistics) as cost_price_statistics, sum(sale_price_statistics) as sale_price_statistics, sum(profit) as profit')
                  ->where('member_id', $user_auth['uid'])
                  ->find();
		if(empty($total_data['duration'])){
      $total_data['duration'] = 0;
    }
    if(empty($total_data['cost_price_statistics'])){
      $total_data['cost_price_statistics'] = 0;
    }
    if(empty($total_data['sale_price_statistics'])){
      $total_data['sale_price_statistics'] = 0;
    }
    if(empty($total_data['profit'])){
      $total_data['profit'] = 0;
    }
    return $total_data;
  }
  
}