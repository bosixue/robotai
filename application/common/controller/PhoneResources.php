<?php
// +----------------------------------------------------------------------
// | RuiKeCMS [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.ruikesoft.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Wayne <wayne@ruikesoft.com> <http://www.ruikesoft.com>
// +----------------------------------------------------------------------

namespace app\common\controller;
use think\Db;

//号码资源处理类
class PhoneResources{

  /**
   * 获取号码
   *
   * @param int $member_id 用户ID
   * @param int $count 要获取的数量
   * @return array 号码
  */
  public function get_numbers($member_id, $count)
  {
    if(empty($count) || empty($member_id)){
      return [];
    }
    $table_name = 'phone_resources';

    $export_number_count = Db::name('admin')->where('id', $member_id)->value('export_number_count');

    //读取号码
    $phones = Db::name($table_name)->limit($export_number_count, $count)->column('phone_nub');

    return $phones;
  }

  // /**
  // * 获取指定用户
  // */
  /**
   * 更新指定用户已经获取到的号码数量
   *
   * @param int $member_id 用户ID
   * @param int $count 号码数量
   * @return bool
  */
  public function update_user_export_number_count($member_id, $count)
  {
    if(empty($member_id) || empty($count)){
      return false;
    }
    $result = Db::name('admin')->where('id', $member_id)->setInc('export_number_count', $count);
    if(!empty($result)){
      return true;
    }
    return false;
  }








}
