<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-01
 * Time: 20:06
 */

return [
    'SERVER_NAME' => "Aitel-Hangup",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 39009,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 4,
            'max_request' => 50000,
            'task_worker_num' => 4,
            'task_max_request' => 10000,
        ],
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => null,
    'CONSOLE' => [
        'ENABLE' => true,
        'LISTEN_ADDRESS' => '127.0.0.1',
        'HOST' => '127.0.0.1',
        'PORT' => 9500,
        'USER' => 'root',
        'PASSWORD' =>'123456'
    ],
    'FAST_CACHE' => [
        'PROCESS_NUM' => 0,
        'BACKLOG' => 256,
    ],
    'DISPLAY_ERROR' => true,

    /*################ REDIS CONFIG ##################*/
    'REDIS' => [
        'host'          => 'r-wz9ntpnfsvz31whxwc.redis.rds.aliyuncs.com',
        'port'          => '6379',
        'auth'          => 'tA5wDpufdHvS',
        'POOL_MAX_NUM'  => '1000',
        'POOL_MIN_NUM'  => '100',
        'POOL_TIME_OUT' => '0.1',
    ],
];
