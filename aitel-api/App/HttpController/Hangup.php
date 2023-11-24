<?php
namespace App\HttpController;

use EasySwoole\Http\AbstractInterface\Controller;

use App\Utility\Pool\RedisPool;
use App\Utility\Pool\RedisObject;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Component\Pool\PoolManager;

class Hangup extends Controller
{
    public $topic_name = 'call-detail';
    function index()
    {
        $this->setDataToRedis();
    }

    /**
     * 如果出现了错误，重试一次
     * @param \Throwable $throwable
     */
    function onException(\Throwable $throwable): void
    {
        $this->setDataToRedis();
    }

    function setDataToRedis()
    {
        $request = $this->request();
        $data = $request->getRequestParam(
            'type',
            'taskuuid',
            'callid',
            'number',
            'numberid',
            'calldatetime',
            'cause',
            'code',
            'bill',
            'duration',
            'da',
            'recordfile',
            'calleridnumber'
        );
        $task_id = $data['taskuuid'];
        $bill    = $data['bill'];
        $data    = json_encode($data);
        $redis   = PoolManager::getInstance()->getPool(RedisPool::class)->getObj();

        //检查连接是否可用:set('i', 1)只是随意设置一个值进行测试
        //如果不可用，创建一个新的连接
        /*
        if (1 !== $redis->set('i', 1)) {
            $redis = new RedisObject();
            $conf = Config::getInstance()->getConf('REDIS');
            if( $redis->connect($conf['host'],$conf['port'])){
                if(!empty($conf['auth'])){
                    $redis->auth($conf['auth']);
                }
            }else{
                $log_data = [
                    'time' => date('Y-m-d H:i:s'),
                    'data' => $data,
                ];
                file_put_contents(Config::getInstance()->getConf('ERROR_LOG_PATH'), var_export($log_data, true), FILE_APPEND);
            }
        }
        */

        $today = date('Ymd');
        $expire_time = 30 * 24 * 3600;  //过期时间一个月

        if ($bill > 0) {
            $redis->rpush($this->topic_name, $data);  //如果通话产生了费用，放到队列前面优先处理

            $redis->incrBy("call-answer-{$today}", 1);  //接通次数
            $redis->expire("call-answer-{$today}", $expire_time);

            //通话时长（秒）
            $second = ceil($bill/1000);
            $redis->incrBy("call-duration-sec-{$today}", $second);
            $redis->expire("call-duration-sec-{$today}", $expire_time);

            //计费时长（分钟）
            $redis->incrBy("call-cost-min-{$today}", ceil($second/60));
            $redis->expire("call-cost-min-{$today}", $expire_time);
        } else {
            $redis->lpush($this->topic_name, $data);
        }

        //呼叫次数，包括重呼次数，如果需要去掉重呼次数，需要减去call-set-again-{{today}}的次数
        $redis->incrBy("call-total-{$today}", 1);
        $redis->expire("call-total-{$today}", $expire_time);


        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($redis);

        $this->response()->write('ok');
    }
}
