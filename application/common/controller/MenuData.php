<?php
namespace app\common\controller;

use think\Db;

//菜单数据处理类
class MenuData extends Base{
  
  /**
   * 获取当前模块的信息
   * 
   * @return array
  */
  public function get_current_modular()
  {
    return MODULE_NAME;
  }
  
  /**
   * 获取菜单数据
   * 
   * @param int $url
   * @return array
  */
  public function get_menu_data($url)
  {
  	if(empty($url)){
  		return false;
  	}
  	$user_auth = session('user_auth');
    $rule_items = Db::name('admin_role')->where('name', $user_auth['role'])->value('rule_items');
    $rule_items = explode(',', $rule_items);
  	$menu_data = Db::name('menu')
  								->field('id,title,pid,url')
  								->where([
  									'url'	=> $url,
  									'id'  => ['in', $rule_items]
  								])
  								->find();
  	// $menu_data['pid_name'] = $this->get_parent_menu_name($menu_data['pid']);
  	$data_type = $this->get_parent_menu_name($menu_data['pid']);
  	if($data_type['type'] == 0){
  		if(empty($menu_data['url'])){
	  		$menu_data['url'] = '';
	  	}
	  	if(empty($menu_data['title'])){
	  		$menu_data['title'] = '';
	  	}
	  	if(empty($menu_data['pid_name'])){
	  		$menu_data['pid_name'] = '';


	  	}
	  	$menu_data['pid_pid_name'] = $data_type['menu2'];
  	}else{
  		$menu_data['pid_pid_name'] = $data_type['menu1'];
  		$menu_data['pid_name'] = $data_type['menu2'];
  		
  		if(empty($menu_data['url'])){
	  		$menu_data['url'] = '';
	  	}
	  	if(empty($menu_data['title'])){
	  		$menu_data['title'] = '';
	  	}
  	}
  
  	return $menu_data;
  	// return $data;
  }
  /**
   * 获取上级菜单名称
   * 
   * @param int $id
   * @return string
  */
  public function get_parent_menu_name($id)
  {
  	if(empty($id)){
  		return false;
  	}
  	$menu_1 = Db::name('menu')
  								->where('id', $id)
  								->find();
  								
		if($menu_1['pid'] == 0){
				$data['type'] = 0 ;
				$data['menu2'] = $menu_1['title'];
				return $data;
		}else{
			$menu_2 = Db::name('menu')
  								->where('id', $menu_1['pid'])
  								->find();
  			$data['menu2'] = $menu_1['title'];
  			$data['menu1'] = $menu_2['title'];
  			$data['type'] = 1 ;
  			return $data;
		}
  }
  
}