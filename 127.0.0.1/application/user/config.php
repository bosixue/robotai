<?php
// +----------------------------------------------------------------------
// | RuiKeCMS [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.ruikesoft.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Wayne <wayne@ruikesoft.com> <http://www.ruikesoft.com>
// +----------------------------------------------------------------------

return array(

	'user_administrator'  => 1,
	'title'=>'微信平台',
	//'url_common_param'=>true,
   'url_html_suffix'   => '',

	'template' => array(
	),

	'view_replace_str'       => array(
		'__ADDONS__' => BASE_PATH . '/addons',
		'__PUBLIC__' => BASE_PATH . '/public',
		'__STATIC__' => BASE_PATH . '/application/user/static',
		'__IMG__'    => BASE_PATH . '/application/user/static/images',
		'__CSS__'    => BASE_PATH . '/application/user/static/css',
		'__JS__'     => BASE_PATH . '/application/user/static/js',
	),

	'session'  => array(
		'prefix'         => 'user',
		'type'           => 'redis',
		'auto_start'     => true,
	),

	'session_option' => array(
		'expire' => 3600*5,
	)

);
