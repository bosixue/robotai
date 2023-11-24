<?php

namespace app\common\model;

use think\Db;

class CountsRobot extends Base
{


    /**
     * 统计机器人使用情况
     * 单台FS的机器人使用情况和总机器人使用情况
     * @throws \think\Exception
     */
    public function doCount()
    {
        $fs_count = count(config('db_configs'));
        $total = 0;
        $fs_data = [];
        for ($i = 1; $i <= $fs_count; $i++) {
            $fs_config = config('db_configs')['fs' . $i];
            if ($fs_config) {
                $use_robot = Db::connect($fs_config)
                    ->table('autodialer_task')
                    ->where('start', 1)
                    ->sum('maximumcall');
                $total += $use_robot;
                $fs_data[] = [
                    'customer_id' => '1',
                    'fs_id' => $i,
                    'use_robot' => $use_robot,
                    'create_time' => time(),
                ];
            }
        }
        Db::name('counts_fs_robot')->insertAll($fs_data);
        $data = [
            'customer_id' => '1',
            'use_robot' => $total,
            'create_time' => time(),
        ];
        $this->insert($data);
    }


    /**
     * 获取总机器人使用情况
     *
     * @throws \think\Exception
     */
    public static function getCount($last_min)
    {
        $now = time();
        $search_time = $now - ($last_min * 60);
        $today = strtotime(date('Y-m-d'));
        if ($search_time < $today) {
            $search_time = $today;
        }
        $robots = Db::name('counts_robot')
            ->field('use_robot,FROM_UNIXTIME(`create_time`) as count_time')
            ->where('create_time', '>=', $search_time)
            ->select();
        $fs = Db::name('counts_fs_robot')
            ->field('fs_id, use_robot,FROM_UNIXTIME(`create_time`) as count_time')
            ->where('create_time', '>=', $search_time)
            ->select();
        $fs_time = [];
        foreach ($fs as $key => $value) {
            $minutes = substr($value['count_time'], 0, 16);
            $fs_id  = 'FS-' . $value['fs_id'];
            $fs_time[$minutes][] = [
                'fs_id'     => $fs_id,
                'use_robot' => $value['use_robot'],
            ];
        }
        foreach ($robots as &$robot) {
            $minutes = substr($robot['count_time'], 0, 16);
            $robot['fs'] = @ $fs_time[$minutes];
        }
        return $robots;
    }

}