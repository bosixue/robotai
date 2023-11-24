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


class OperationRecord extends Base{



  /**
   * 写入操作记录
   *
   * @param int $owner 操作人ID
   * @param int $user_id 被操作人ID
   * @param int $operation_type 操作类型
   * @param string $operation_fu 操作功能
   * @param string $record_content 操作内容
   * @param int $operation_date 操作时间
   * @param string $old_data 修改前数据
   * @param string $new_data 修改后数据
   * @param string $remark 备注
  */
  public function insert($owner, $user_id, $operation_type, $operation_fu, $record_content, $operation_date, $old_data = '', $new_data = '', $remark = '')
  {
    if(empty($owner) || empty($user_id) || empty($operation_type) || empty($operation_fu) || empty($record_content)){
      return false;
    }
    $insert_data = [
      'owner' =>  $owner,
      'user_id' =>  $user_id,
      'operation_type'  =>  $operation_type,
      'operation_fu'  =>  $operation_fu,
      'record_content'  =>  $record_content,
      'operation_date'  =>  $operation_date,
      'old_data'  =>  $old_data,
      'new_data'  =>  $new_data,
      'remark'  =>  $remark
    ];
    $result = Db::name('operation_record')->insert($insert_data);
    \think\Log::info('$insert_data-'.json_encode($insert_data));
    if($result){
      return true;
    }
    return false;
  }

  /**
   * 写入短信通道的操作记录
  */
  public function insert_sms_channel($owner, $user_id, $operation_fu, $record_content, $old_data, $new_data, $remark = '')
  {

    if(isset($old_data['password'])){
      unset($old_data['password']);
    }
    if(isset($new_data['password'])){
      unset($new_data['password']);
    }

    return $this->insert($owner, $user_id, 4, $operation_fu, $record_content, time(), $old_data, $new_data, $remark);

  }

  /**
   * 写入用户的操作记录
   *
   * @param string $type 类型
   * @param int $owner 操作人的用户ID
   * @param int $user_id 被操作人的用户ID
   * @param string $operation_fu 操作功能
   * @param string $record_content 操作内容
   * @param string $old_data 旧的数据
   * @param string $new_data 新的数据
   * @return bool
  */
  public function insert_user($type, $owner, $user_id, $operation_fu, $old_data, $new_data, $remark = '')
  {
    if(isset($old_data['password']) == true){
      unset($old_data['password']);
    }

    if(isset($new_data['password']) == true){
      unset($new_data['password']);
    }

    $record_content = $this->get_operation_content($type, $old_data, $new_data);

    return $this->insert($owner, $user_id, 5, $operation_fu, $record_content, time(), json_encode($old_data), json_encode($new_data), $remark);
  }

  /**
   * 获取操作内容
   *
   * @param string $type 类型
   * @param array $old_data 旧数据
   * @param array $new_data 新数据
   * @return string
  */
  public function get_operation_content($type, $old_data, $new_data)
  {
    if(empty($type)){
      return false;
    }
    $operation_content = '';
    switch ($type) {
      case 'update_sms_channel':
        $operation_content = $this->get_update_sms_channel_operation_content($old_data, $new_data);
        break;

      case 'add_sms_channel':
        $operation_content = $this->get_add_sms_channel_operation_content($new_data);
        break;

      case 'delete_sms_channel':
        $operation_content = $this->get_delete_sms_channel_operation_content($old_data);
        break;

      case 'distribution_sms_channel':
        $operation_content = $this->get_distribution_sms_channel_operation_content($new_data);
        break;

      case 'add_user':
        $operation_content = $this->add_user($new_data);
        break;

      case 'update_user':
        $operation_content = $this->update_users($old_data, $new_data);
        break;

      case 'update_user_status':
        $operation_content = $this->update_user_status($old_data, $new_data);
        break;

      case 'recovery_sms_channel':
        $operation_content = $this->recovery_sms_channel($old_data);
        break;

      case 'reset_user_password':
        $operation_content = $this->reset_user_password($old_data);
        break;
      default:
        // code...
        break;
    }
    return $operation_content;
  }

  /**
   * 资费管理
   *
   * @param int $owner 操作人的用户ID
   * @param array $operation_data 操作的数据
   * @return bool
  */
  public function insert_fee_management($owner, $operation_data)
  {
	  
    if(empty($owner) || empty($operation_data)){
      return false;
    }

    $record_content = [];
    foreach($operation_data as $key=>$user){
      $user_info = Db::name('admin')->where('id', $key)->field('username, type_price, pid')->find();
      $record_content[$user_info['pid']][$key][] = '调整用户:"'.$user_info['username'].'"的资费管理';
      foreach($user as $find_key=>$modular){
        switch($find_key){
          case 'robot':
            if(isset($modular['new_data']['month_price']) == true && $modular['new_data']['month_price'] != $modular['old_data']['month_price']){
              //判断机器人的计费类型 计费类型 1=天  2 = 月
              if($user_info['type_price'] == 1){
                $record_content[$modular['old_data']['param_user_id']][$key][] = '机器人日租费率从原先的'.$modular['old_data']['month_price'].'元修改为'.$modular['new_data']['month_price'].'元';
              }else{
                $record_content[$modular['old_data']['param_user_id']][$key][] = '机器人月租费率从原先的'.$modular['old_data']['month_price'].'元修改为'.$modular['new_data']['month_price'].'元';
              }
            }
            break;
          case 'line_group':
            foreach($modular as $find_find_key=>$line_group){
              if(isset($line_group['new_data']['sales_price']) == true && $line_group['old_data']['sales_price'] != $line_group['new_data']['sales_price']){
                $record_content[$line_group['old_data']['param_user_id']][$key][] = '线路组:"'.$line_group['old_data']['name'].'"的费率从原先的'.$line_group['old_data']['sales_price'].'元修改为'.$line_group['new_data']['sales_price'].'元';
              }
            }
            break;
          case 'sms':
            if(is_array($modular) == true ){
              foreach($modular as $find_find_key=>$sms){
                if(isset($sms['new_data']['price']) == true && $sms['old_data']['price'] != $sms['new_data']['price']){
                  $record_content[$sms['old_data']['param_user_id']][$key][] = '短信通道:"'.$sms['old_data']['name'].'"的费率从原先的'.$sms['old_data']['price'].'元修改为'.$sms['new_data']['price'].'元';
                }
              }
            }
            break;
          case 'asr':
            if(is_array($modular) == true){
              foreach($modular as $find_find_key=>$asr){
                if(isset($asr['new_data']['sale_price']) == true && $asr['old_data']['sale_price'] != $asr['new_data']['sale_price']){
                  $record_content[$asr['old_data']['param_user_id']][$key][] = 'ASR:"'.$asr['old_data']['name'].'"的费率从原先的'.$asr['old_data']['sale_price'].'元修改为'.$asr['new_data']['sale_price'].'元';
                }
              }
            }
            break;
          case 'technology_service_price':
            if(isset($modular['new_data']['technology_service_price']) == true && $modular['new_data']['technology_service_price'] != $modular['old_data']['technology_service_price']){
              $record_content[$modular['old_data']['param_user_id']][$key][] = '技术服务费费率从原先的'.$modular['old_data']['technology_service_price'].'元修改为'.$modular['new_data']['technology_service_price'].'元';
            }
            break;
        }
      }
    }


    foreach($record_content as $key=>$value){
      foreach($value as $find_key=>$find_value){
        $record_content = implode('；', $find_value);
        $this->insert($key, $find_key, 7, '编辑资费管理', $record_content, time(), json_encode([]), json_encode([]));
      }
    }
    return true;

  
  }

  /**
   * 获取添加用户的操作内容
   *
   * @param array $new_data 新增用户的数据
   * @return string
  */
  public function add_user($new_data)
  {
    if(empty($new_data)){
      return false;
    }

    $record_content = [];

    //用户名
    if(!empty($new_data['username'])){
      $record_content[] = '添加账户'.$new_data['username'];
    }
    //用户类型
    if(!empty($new_data['role_id'])){
      $role_name = Db::name('admin_role')->where('id', $new_data['role_id'])->value('name');
      $record_content[] = '用户角色为'.$role_name;
    }
    //手机号码
    if(!empty($new_data['mobile'])){
      $record_content[] = '手机号码为'.$new_data['mobile'];
    }
    //备用手机号码
    if(!empty($new_data['spare_mobile'])){
      $record_content[] = '备用手机号码为'.$new_data['spare_mobile'];
    }
    //账户充值
    if(!empty($new_data['money'])){
      $record_content[] = '充值金额为'.$new_data['money'].'元';
    }
    //机器人个数
    if(!empty($new_data['robot_cnt'])){
      $record_content[] = '分配机器人数'.$new_data['robot_cnt'];
    }
    //机器人到期时间
    if(!empty($new_data['robot_date'])){
      $record_content[] = '机器人到期时间'.date('Y-m-d', $new_data['robot_date']);
    }
    //备注
    if(!empty($new_data['remark'])){
      $record_content[] = '备注为'.$new_data['remark'];
    }
    $record_content = implode('，', $record_content);

    return $record_content;
  }

  /**
   * 回收短信通道
   *
   * @param array $old_data
   * @param string
  */
  public function recovery_sms_channel($old_data)
  {
    if(empty($old_data)){
      return false;
    }

    $operation_content = '回收短信通道，短信通道名称为'.$old_data['name'].';';
    return $operation_content;
  }

  /**
   * 获取更新用户的操作内容
   *
   * @param array $old_data
   * @param array $new_data
   * @return string
  */
  public function update_users($old_data, $new_data)
  {
      if(empty($old_data) || empty($new_data)){
          return false;
      }

      $record_content = [];
      $record_content[] = '编辑账户'.$old_data['username'];

      //手机号码
      if(isset($new_data['mobile']) == true && $new_data['mobile'] != $old_data['mobile']){
        $record_content[] = '手机号码从'.$old_data['mobile'].'修改为'.$new_data['mobile'];
      }
      //备用手机号码
      if(isset($new_data['spare_mobile']) == true && $new_data['spare_mobile'] != $old_data['spare_mobile']){
        $record_content[] = '修改备用手机号码，从'.$old_data['spare_mobile'].'修改为'.$new_data['spare_mobile'];
      }
      $type_prices = [
        1   =>  '按天计费',
        2   =>  '按月计费'
      ];
      //机器人租金类型
      if(isset($new_data['type_price']) == true && $new_data['type_price'] != $old_data['type_price']){
        $record_content[] = '修改机器人租金计费类型，从'.$type_prices[$old_data['type_price']].'修改为'.$type_prices[$new_data['type_price']];
      }
      //机器人租金
      if(isset($new_data['month_price']) == true && $new_data['month_price'] != $old_data['month_price']){
        $record_content[] = '修改机器人租金，从'.$old_data['month_price'].'修改为'.$new_data['month_price'];
      }
      $is_scenarios = [
        1 => '隐藏',
        2 => '显示'
      ];
      //是否隐藏话术模块
      if(isset($new_data['is_scenarios']) == true && $new_data['is_scenarios'] != $old_data['is_scenarios']){
        $record_content[] = '修改是否开启话术模块的状态，从'.$is_scenarios[$old_data['is_scenarios']].'修改为'.$is_scenarios[$new_data['is_scenarios']];
      }
      $is_verification = [
        1 =>  '开启',
        2 =>  '关闭'
      ];
      //是否开启短信验证功能
      if(isset($new_data['is_verification']) == true && $new_data['is_verification'] != $old_data['is_verification']){
        $record_content[] = '修改是否开启短信验证的状态，从'.$is_verification[$old_data['is_verification']].'修改为'.$is_verification[$new_data['is_verification']];
      }
      //备注
      if(isset($new_data['remark']) && $new_data['remark'] != $old_data['remark']){
        $record_content[] = '修改备注，从'.$old_data['remark'].'修改为'.$new_data['remark'];
      }

      if(count($record_content) > 1){
        return implode('；', $record_content);
      }
      return '';
  }

  /**
   * 获取开启或锁定账号的操作内容
   *
   * @param array $new_data 新的数据(修改后的数据)
   * @param string
  */
  public function update_user_status($old_data, $new_data)
  {
    if(empty($new_data) || empty($old_data)){
      return false;
    }
    $status = [
      '锁定账号',
      '开启账号'
    ];

    $record_content = $status[$new_data['status']].'：'.$old_data['username'];
    return $record_content;
  }

  /**
   * 获取添加短信通道的操作内容
   *
   * @param array $sms_channel_data 短信通道的数据
   * @return string 操作内容
  */
  public function get_add_sms_channel_operation_content($sms_channel_data)
  {
    if(empty($sms_channel_data)){
      return false;
    }

    $record_content = [];
    $record_content[] = '添加短信通道';
    $record_content[] = '通道名称:'.$sms_channel_data['name'];
    $record_content[] = '单价为'.$sms_channel_data['price'].'元/分钟';
    $record_content[] = '短信运营商为'.$sms_channel_data['type'];
    $record_content[] = '接口地址为'.$sms_channel_data['url'];
    $record_content[] = '短信ID为'.$sms_channel_data['enterprise_id'];
    $record_content[] = '短信账号为'.$sms_channel_data['user_id'];
    // $record_content[] = '短信密码为'.$sms_channel_data['pass'];
    $record_content[] = '备注为'.$sms_channel_data['remarks'];
    $record_content = implode('，', $record_content);
    return $record_content;
  }


  /**
   * 获取编辑短信通道的操作内容
   *
   * @param array $old_data 旧数据
   * @param array $new_data 新数据
   * @return string
  */
  public function get_update_sms_channel_operation_content($old_data, $new_data)
  {
    if(empty($old_data) || empty($new_data)){
      return false;
    }

    $record_content = [];
    // $find_record_content = [];
    $record_content[] = '编辑短信通道'.$old_data['name'];
    // $find_record_content[] = '编辑短信通道'.$old_sms_channel_data['name'];
    if(isset($new_data['name']) == true && $new_data['name'] != $old_data['name']){
      $record_content[] = '将'.$old_data['name'].'的名称修改为:'.$new_data['name'];
      // $find_record_content[] = '将'.$old_sms_channel_data['name'].'的名称修改为:'.$new_sms_channel_data['name'];
    }
    if(isset($new_data['price']) == true && $new_data['price'] != $old_data['price']){
      $record_content[] = '单价从'.$old_data['price'].'元/分钟修改为'.$new_data['price'].'元/分钟';
    }
    if(isset($new_data['type']) == true && $new_data['type'] != $old_data['type']){
      $record_content[] = '短信运营商从'.$old_data['type'] . '修改为' . $new_data['type'];
      // $find_record_content[] = '短信运营商从'.$old_sms_channel_data['type'] . '修改为' . $new_sms_channel_data['type'];
    }
    if(isset($new_data['url']) == true && $new_data['url'] != $old_data['url']){
      $record_content[] = '接口地址从'.$old_data['url'] . '修改为' . $new_data['url'];
      // $find_record_content[] = '接口地址从'.$old_sms_channel_data['url'] . '修改为' . $new_sms_channel_data['url'];
    }
    if(isset($new_data['enterprise_id']) == true && $new_data['enterprise_id'] != $old_data['enterprise_id']){
      $record_content[] = '短信ID从'.$old_data['enterprise_id'] . '修改为' . $new_data['enterprise_id'];
      // $find_record_content[] = '短信ID从'.$old_sms_channel_data['enterprise_id'] . '修改为' . $new_sms_channel_data['enterprise_id'];
    }
    if(isset($new_data['user_id']) == true && $new_data['user_id'] != $old_data['user_id']){
      $record_content[] = '短信账号从' . $old_data['user_id'] . '修改为' . $new_data['user_id'];
      // $find_record_content[] = '短信账号从' . $old_sms_channel_data['user_id'] . '修改为' . $new_sms_channel_data['user_id'];
    }
    // $record_content[] = '短信密码为'.$sms_password;
    if(isset($new_data['remarks']) == true && $new_data['remarks'] != $old_data['remarks']){
      $record_content[] = '备注从'. $old_data['remarks'] . '修改为' . $new_data['remarks'];
      // $find_record_content[] = '备注从'. $old_sms_channel_data['remarks'] . '修改为' . $new_sms_channel_data['remarks'];
    }
    if(count($record_content) > 1){
      $record_content = implode('，', $record_content);
      // $OperationRecord->insert($user_auth['uid'], $user_auth['uid'], 4, '编辑短信通道', $record_content, time(), [], json_encode($insert_data), '');
    }else{
      $record_content = '';
    }
    return $record_content;
  }

  /**
   * 获取删除短信通道的操作内容
   *
   * @param string $sms_channel_data 短信通道数据
   * @return string
  */
  public function get_delete_sms_channel_operation_content($sms_channel_data)
  {
    if(empty($sms_channel_data)){
      return false;
    }

    $operation_content = '删除短信通道，短信通道名称为'.$sms_channel_data['name'].';';

    return $operation_content;
  }

  /**
   * 获取分配短信通道的操作内容
   *
   * @param array $sms_channel_data 短信通道数据
   * @param string $username 分配给指定用户的用户名
   * @return string
  */
  public function get_distribution_sms_channel_operation_content($sms_channel_data)
  {
    if(empty($sms_channel_data)){
      return false;
    }
    $username = Db::name('admin')->where('id', $sms_channel_data['owner'])->value('username');
    //分配短信通道，给用户xs分配通道td1,td1销售价为：0.7元/条；

    $operation_content = '分配短信通道，给用户'.$username.'分配通道'.$sms_channel_data['name'].','.$sms_channel_data['name'].'销售价为：'.$sms_channel_data['price'].'元/条；';


    return $operation_content;
  }

  /**
   * 获取重置密码的操作内容
   *
   * @param int $old_data
   * @return string
  */
  public function reset_user_password($old_data)
  {
    if(empty($old_data)){
      return false;
    }

    $operation_content = '用户名为:"'.$old_data['username'].'"进行修改密码';
    return $operation_content;
  }

  /**
   * 获取编辑资费管理的操作记录
   *
   * @param array $old_data 旧的数据
   * @param array $new_data 新的数据
   * @return string
  */
  // public function get_

}
