<?php
namespace App\HttpController;


use EasySwoole\Http\AbstractInterface\Controller;
use App\Utility\Pool\MysqlPool;
use EasySwoole\Component\Pool\PoolManager;

class Engage extends Controller
{

    function index()
    {
        $table_name = 'rk_member_test';
        $owner = rand(10000, 99999);
        $data = [
            'uid' => null,
            'owner' => $owner,
            'level' => '2',
            'last_dial_time' => '1552460021',
            'task' => '7000001',
        ];
        //$insertSql = "INSERT INTO `rk_member_test` (`uid`, `owner`, `mobile`,`level`,`last_dial_time`,`task`)
        //              VALUES (NULL, '{$owner}', '15989355455',2, '1552460021', '7000001');";



        $db = PoolManager::getInstance()->getPool(MysqlPool::class)->getObj();
        $insert_id = $db->insert($table_name, $data);
        $result = $db->where('uid', $insert_id)->update($table_name, ['status'=>'2']);
        //使用完毕需要回收
        PoolManager::getInstance()->getPool(MysqlPool::class)->recycleObj($db);
        $this->response()->write(json_encode([$insert_id, $result]));
    }
}
