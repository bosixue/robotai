<?php
namespace app\common\controller;

use think\Db;
//日志
use app\common\controller\Log;


//会员用户数据处理类
class AsrData extends Base{

	//表名
	public $table_name = 'tel_interface';

	/**
   * 给指定的用户分配指定ASR
   *
   * @param int $member_id 用户ID
   * @param int $asr_id ASRID
   * @param flota $sale_price 销售价格
   * @return bool
  */
  public function create_distribution_asr($member_id, $asr_id, $sale_price)
  {
  	\think\Log::record('ASR分配');
  	\think\Log::record($member_id . '-' . $asr_id . '-' . $sale_price);
  	if(empty($member_id) || empty($asr_id)){
  		return false;
  	}

  	//获取ASR数据
  	$asr_data = Db::name($this->table_name)
  							->where('id', $asr_id)
  							->find();
  	unset($asr_data['id']);
  	$asr_data['note'] = '账号添加';
  	$asr_data['sale_price'] = $sale_price;
  	$asr_data['owner'] = $member_id;
  	$asr_data['pid'] = $asr_id;
  	$asr_data['status'] = 1;
  	$asr_data['create_time'] = time();
  	$insert_result = Db::name($this->table_name)
  										->insert($asr_data);
  	\think\Log::record('给指定的用户分配指定ASR');
  	if(!empty($insert_result)){
  		$this->update_asr_config_file([$member_id]);
  		return true;
  	}
  	return false;
  }
  /**
   * 更新asr配置
   *
   * @param array $ids ASR的ID集合
   * @return bool
  */
  public function update_asr_config_file($ids)
  {
    //获取所有asr
    $asrs = Db::name($this->table_name)
            ->where('id', 'in', $ids)
            ->order('id desc')
            ->select();
    $asrs_data = [];
    foreach($asrs as $key=>$value){
      $asrs_data[$value['owner']][] = $value;
    }
    foreach($asrs_data as $key=>$value){
      //遍历所有asr 进行拼接
      foreach($value as $find_key=>$find_value){
      	$asr_conf = $this->get_asr_config($find_value['type'], $find_value['project_key'], $find_value['app_key'], $find_value['app_secret']);
	      $vrs = config('view_replace_str');
	      //获取默认asr配置文件
	      $path = ".".$vrs["__STATIC__"]. '/smartivr.json';
	      $json_string = file_get_contents($path);
	      $savedata = json_decode($json_string, true);

	      $savedata['asr'] = $asr_conf;
	      /*设置保存路径*/
	      $fs_path = '/asrapi/';
	      $savePath = './uploads/asrapi/';
	      $is_file = './uploads/';
	      // 如果不存在则创建文件夹
	      if (!is_dir($savePath)){
	      		mkdir($savePath, 0775, true);
	      }
	      $suffix = md5(time());
	      $update_file_name = $key . '_' . $find_value['id'] . '_smartivr_' . $suffix . '.json';
	      $itempath = $savePath.$update_file_name;
	      $update_result = Db::name($this->table_name)
	                        ->where('id', $find_value['id'])
	                        ->update([
	                          'path'  =>  $fs_path . $update_file_name
	                         ]);
	      if(!empty($update_result)){
	        if(is_file($is_file . $find_value['path']) === true){
	          rename($is_file.$find_value['path'], $savePath.$update_file_name);
	          $savejson = json_encode($savedata, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    	      file_put_contents($itempath,$savejson);
	        }else{
	          $savejson = json_encode($savedata, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    	      file_put_contents($itempath,$savejson);
	        }
					$redis = RedisConnect::get_redis_connect();
					$redis_key = 'asr_'.$find_value['id'].'_find';
					$redis->del($redis_key);
	      }else{

	      }
	      \think\Log::record('更新ASR配置文件');
      }
    }
  }

  public function get_asr_config($type, $project_key, $app_key, $app_secret)
  {
    if(empty($app_key) || empty($app_secret)){
      return false;
    }
    if(empty($type)){
      $type = 'aliyun';
    }
    if($type == 'aliyun'){
  		$type_name = 'aliyunv2';
  	}else if($type == 'xfyun'){
  		$type_name = 'xfyun';
  	}else if($type == 'baidu'){
  		$type_name = 'baidu';
  	}else if($type == 'aiuiv2'){
  		$type_name = 'aiuiv2';
  	}else{
  		$type_name = 'aliyunv2';
  	}
  	$enable = [];
  	$asr_conf = [];
    switch ($type) {
      case 'aliyun':
        $asr_conf[$type_name] = [
          "mode"  => 1,
          "connecttimeout" => 1000,
          "responsetimeout" => 2000,
          "appkey" => $project_key,
          "enable_punctuation_prediction" => false,
          "enable_inverse_text_normalization" =>  false,
          "enable_voice_detection" => false,
          "keylist" =>  [
            [
              "id"  =>  $app_key,
              "secret"  =>  $app_secret
            ]
          ]
        ];
        $enable[] = $type_name;
        $asr_conf['enable'] = $enable;
        $asr_conf['mode'] = 1;
        break;
      case 'xfyun':
        $asr_conf[$type_name] = [
          "aiui"  => false,
          "engine" => "sms8k",
          "keylist" =>  [
            [
              "id"  =>  $app_key,
              "secret"  =>  $app_secret
            ]
          ]
        ];
        $enable[] = $type_name;
        $asr_conf['enable'] = $enable;
        $asr_conf['mode'] = 1;
        break;
      default:
        $asr_conf[$type_name] = [
          "mode"  => 1,
          "connecttimeout" => 1000,
          "responsetimeout" => 2000,
          "appkey" => $project_key,
          "enable_punctuation_prediction" => false,
          "enable_inverse_text_normalization" =>  false,
          "enable_voice_detection" => false,
          "keylist" =>  [
            [
              "id"  =>  $app_key,
              "secret"  =>  $app_secret
            ]
          ]
        ];
        $enable[] = $type_name;
        $asr_conf['enable'] = $enable;
        $asr_conf['mode'] = 1;
        break;
    }
    return $asr_conf;
  }
}
