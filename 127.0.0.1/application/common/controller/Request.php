<?php
namespace app\common\controller;

use think\Db;
use app\common\controller\Log;

class Request extends Base{
  public function get($url, $params = [])
  {
    if(empty($url)){
      return false;
    }
    $ch = curl_init();

    curl_setopt ($ch, CURLOPT_URL, $url);
  
    curl_setopt ($ch, CURLOPT_POST, 0);
  
    if($params != ''){
  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
  
    }
  
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
  
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);
  
    curl_setopt($ch, CURLOPT_HEADER, false);
  
    $file_contents = curl_exec($ch);
  
    curl_close($ch);
    //返回
    $file_contents = json_decode($file_contents, true);
    return $file_contents;
  }
  
  public function post($url, $params)
  {
    if(empty($url)){
      return false;
    }
    $ch = curl_init();

    curl_setopt ($ch, CURLOPT_URL, $url);
  
    curl_setopt ($ch, CURLOPT_POST, 1);
  
    if($params != ''){
  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
  
    }
  
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
  
    curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);
  
    curl_setopt($ch, CURLOPT_HEADER, false);
  
    $file_contents = curl_exec($ch);
  
    curl_close($ch);
    //返回
    $file_contents = json_decode($file_contents, true);
    return $file_contents;
  }
}