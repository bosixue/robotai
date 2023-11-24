<?php
namespace app\common\controller;

use think\Db;
use app\common\controller\Log;


/**
 * 依赖:
 *     1.在服务器上安装sox
 *       1).查看sox是否安装
 *           sox --version
 *       2).如果没有安装 运行以下命令 已安装 请忽略
 *           yum install sox
 *     2.将exec函數从禁用名单中去除
 *       1).宝塔面板中可以设置
*/
class Audio extends Base{
  /**
   * 判断录音格式是否正常
   *
   * @param string $audio_path
   * @retrun bool
  */
  public function audio_whether_correct($audio_path)
  {
    if(empty($audio_path)){
      return false;
    }
    // /uploads/audio/20190422/20181219091556.wav
    if(file_exists($_SERVER['DOCUMENT_ROOT'] . $audio_path) == false){
      return false;
    }
    $command = 'soxi '.$_SERVER['DOCUMENT_ROOT'] . $audio_path.'  2>&1';
    exec($command, $audio_info);
    /*
    Array ( [0] => [1] => Input File : '/www/wwwroot/127.0.0.1/uploads/20181130030349.wav' [2] => Channels : 1 [3] => Sample Rate : 8000 [4] => Precision : 16-bit [5] => Duration : 00:00:00.37 = 2937 samples ~ 27.5344 CDDA sectors [6] => File Size : 5.94k [7] => Bit Rate : 129k [8] => Sample Encoding: 16-bit Signed Integer PCM [9] => )
    */
    foreach($audio_info as $key=>$value){
      switch($key){
        case 2:
          // if(strpos($value, 'Channels') === false){
          //   echo $value;
          //   return false;
          // }
          $new_value = str_replace('Channels', '', $value);
          $new_value = str_replace(':', '', $new_value);
          if($new_value != '1'){
            return false;
          }
          break;
        case 3:
          $new_value = str_replace('Sample Rate', '', $value);
          $new_value = str_replace(':', '', $new_value);
          if($new_value != 8000){
            return false;
          }
          break;
        case 4:
          // $new_value = str_replace('Precision', '', $value);
          // $new_value = str_replace(':', '', $new_value);
          // if($new_value != '16-bit'){
          //   echo $new_value;
          //   return false;
          // }
          break;
        case 6:
        //   $new_value = str_replace('File Size', '', $value);
        //   $new_value = str_replace(':', '', $new_value);
        //   if($new_value < 0){
        //     echo $new_value;
        //     return false;
        //   }
        break;
        case 8:
          // $new_value = str_replace('Sample Encoding', '', $value);
          // $new_value = str_replace(':', '', $new_value);
          // if($new_value != '16-bit Signed Integer PCM'){
          //   return false;
          // }
          break;
      }
    }
    return true;
  }
}
