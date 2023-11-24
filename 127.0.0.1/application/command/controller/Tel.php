<?php
namespace app\command\controller;

use app\common\controller\Base;
use app\common\controller\RedisConnect;
use app\api\controller\Smartivr;
use app\common\controller\Log;
use think\Db;

class Tel extends Base
{
	/**
     * 消费挂断接口数据 - 命令行，防止超时
     * 每分钟启动一次，启动后会把队列所有的数据消费掉后再停止
     * 因为用了队列，不会造成重复消费的情况
     *
     * 命令行启动方法：
     * # crontab -e  执行命令，增加一行
     * # 星号/1 * * * *  nohup php /www/wwwroot/127.0.0.1/index.php command/tel/hangup  >> /www/wwwroot/127.0.0.1/data/log/find_lose_data.log 2>&1 &
     */
    public function hangup()
    {
        set_time_limit(0);
        $redis = RedisConnect::get_redis_queue();
		$Smartivr = new Smartivr();
        // $Smartivr->insert_data = '';
		$start_time = time();
		$i = 0;
		while ($call_data = $redis->rpop('call-detail')) {
            try {
                $call_data = json_decode($call_data, true);
                if (!$call_data) {
                    continue;
                }

                $_POST['type']           = $call_data['type'];
                $_POST['taskuuid']       = $call_data['taskuuid'];
                $_POST['callid']         = $call_data['callid'];
                $_POST['number']         = $call_data['number'];
                $_POST['numberid']       = $call_data['numberid'];
                $_POST['calldatetime']   = $call_data['calldatetime'];
                $_POST['cause']          = $call_data['cause'];
                $_POST['code']           = $call_data['code'];
                $_POST['bill']           = $call_data['bill'];
                $_POST['duration']       = $call_data['duration'];
                $_POST['da']             = $call_data['da'];
                $_POST['recordfile']     = $call_data['recordfile'];
                $_POST['calleridnumber'] = $call_data['calleridnumber'];
                $Smartivr->unusualNotify();
            } catch (\Exception $e) {
                $redis->lpush('call-detail', json_encode($call_data));
				// $this->insertToDb($Smartivr->insert_data);
                $log = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'msg'  => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
                Log::log2file('error.hangup', $log);
            }

			// $i ++;
			// if ($i = 1000) {
				// $i = 0;
				// $this->insertToDb($Smartivr->insert_data);
			// }

			$now = time();
			if (($now - $start_time) > 55) {
                // $this->insertToDb($Smartivr->insert_data);
				exit;
			}
		}
		// $this->insertToDb($Smartivr->insert_data);
	}

    /**
     * 批量插入数据库
     * @param $insert_data
     */
	public function insertToDb(& $insert_data)
    {
		try {
			foreach ($insert_data as $table_name => $data) {
				Db::name($table_name)->insertAll($data);

				//插入成功后删除，避免出现异常处理后将已经插入成功的数据写入日志
				unset($insert_data[$table_name]);
			}
			$insert_data = [];
		} catch (\Exception $e) {
			$log = [
				'file'  => $e->getFile(),
				'line'  => $e->getLine(),
				'msg'   => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'data'  => $insert_data,
			];
			Log::log2file('error.hangup.insertToDb', $log);
		}

    }
}
