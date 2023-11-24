<?php
namespace app\command\controller;

use app\common\controller\Base;
use app\common\model\CountsRobot;

class Counts extends Base
{
	public function robot()
    {
        $countsRobot = new CountsRobot();
        $countsRobot->doCount();
    }
}