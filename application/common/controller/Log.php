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

class Log extends Base{
	
	
	// public function __construct()
	// {
		
	// }
	public static function info($content)
	{
		// $url = '/www/wwwroot/120.79.142.199/public/thinkphp.log';
		// //创建日志文件
  //   $logFile = fopen($url , "a+");
  //   // echo $this->logFileUrl;
  //   $date = date("Y-m-d H:i:s");
  //   $txt = $date . " - " . $content . "\n";
  //   fwrite($logFile , $txt);
	}
	// public static function error($content)
	// {
	// 	$url = '/www/wwwroot/120.79.142.199/public/error.log';
	// 	//创建日志文件
 //   $logFile = fopen($url , "a+");
 //   // echo $this->logFileUrl;
 //   $date = date("Y-m-d H:i:s");
 //   $txt = $date . " - " . $content . "\n";
 //   fwrite($logFile , $txt);
	// }




    /**
     * 获取路径，当路径不存在时，尝试创建路径
     *
     * @param string $path
     * @return string
     */
    public static function path( $path = '' ){
        if( empty( $path ) )
            return false;

        $parentDir = dirname( $path );
        if( !is_dir( $parentDir ) && !self::path( $parentDir ) ){
            return false;
        }

        $path = trim( $path );
        if( is_dir( $path ) )
            return $path;

        if( @mkdir( $path ) )
            return $path;

        return false;
    }


    /**
     * 创建文件夹
     *
     * @param string $path 路径
     *
     * @return
     */
    public static function makeDir($path)
    {
        if (!file_exists($path)){
            self::makeDir(dirname($path));
            mkdir($path, 0777);
            chmod($path, 0777);
        }
    }

    /**
     * 记录一个日志文件，如果有就追加
     *
     * @param string $type       日志类型
     * @param mixed $data        日志数据内容
     * @param string $logPath    日志输出的目录
     *
     * @return int 返回写入的字节数
     */
    public static function log2file( $type = '', $data = array(), $logPath = '' ){
        if (empty($logPath)){
            if (DIRECTORY_SEPARATOR === '/'){
                $logPath = '/www/wwwroot/127.0.0.1/data/log/';
            }
            else {
                $logPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR;
            }
        }

        if( empty( $type ) || !is_string( $type ) )
            return false;

        $typePath = explode( ".", $type );
        $logOnly = strtolower( $typePath[0] ) == 'log-only' ? true : false;
        if( $logOnly )
            unset( $typePath[0] );

        $http = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $domain = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';

        $filepath = $logPath . date("Y_m_d") . "/" . $domain . "/" . implode( "/", $typePath ) . "/" . date("H") . ".log";
        if( defined( "APP_PLATFORM" ) ){
            $filepath = $logPath . date("Y_m_d") . "/" . APP_PLATFORM . "/" . implode( "/", $typePath ) . "/" . date("H") . ".log";
        }

        $dir = dirname( $filepath );
        self::makeDir( $dir );

        if( !$logOnly ){
            $logData = array(
                "time" => date( "Y-m-d H:i:s" ),
                "request-url" => isset($_SERVER['HTTP_HOST']) ? $http . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] : '',
                //"header" => getallheaders(),
                "post" => $_POST,
                "files" => $_FILES,
                "json|xml" => file_get_contents("php://input"),
                "logs" => $data
            );
        }
        else{
            $logData = array(
                "time" => date( "Y-m-d H:i:s" ),
                "logs" => $data
            );
        }

        return file_put_contents( $filepath, var_export( $logData, true ) . "\n\n==============================\n\n", FILE_APPEND );
    }
}