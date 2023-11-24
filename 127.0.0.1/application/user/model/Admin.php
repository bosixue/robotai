<?php
namespace app\user\model;
use think\Model;
	
class Admin extends Model
{
	public function get_user_data()
	{
		$data = $this->where('id', 5555)->find();
		return $data;
	}
}
