<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------
    //切片的录音服务器
    'cut_audio_server_url'	 => '/uploads/asrdir',
    //历史切片录音服务器
    'history_cut_audio_server_url'   =>  '/uploads/asrdir',
    //手机号码正则
    'phone_regular'					 =>	'/^1(?:3\d|4[4-9]|5[0-35-9]|6[67]|7[013-8]|8\d|9\d)\d{8}$/',
    // 应用命名空间
    'app_namespace'          => 'app',
    // 应用调试模式
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => false,
    // 应用模式状态
    'app_status'             => '',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,
    // 注册的根命名空间
    'root_namespace'         => [],
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT],
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 是否开启多语言
    'lang_switch_on'         => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => '',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 应用类库后缀
    'class_suffix'           => false,
    // 控制器类后缀
    'controller_suffix'      => false,
    //OSS地址
    //今日的
    'record_path'    				 =>	'/uploads/recordings/',
    //历史的
    'history_record_path'		 =>	'/uploads/recordings/',
    'weixin_push_ip'=>'127.0.0.1',
    'domain_name'  => 'http://B3021901003.tyyke.com',
    'version'                => '7.0.4',
    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module'         => 'user',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,
    //静音录音文件
    'mute_wav'               => '/var/smartivr/sounddir/mute.wav',

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,

    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => true,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => true,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',

    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
    'redis' =>  [
        'host'  =>  '127.0.0.1',
        'port'  =>  6379,
        'auth'  =>  '',
        'select'  =>  0
    ],
    //消息队列redis
    'redis_queue' =>  [
        'host'  =>  '127.0.0.1',
        'port'  =>  6379,
        'auth'  =>  '',
        'select'  =>  0
    ],
    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
    ],

    // 视图输出字符串内容替换
    'view_replace_str'       => array(
        '__ADDONS__' => BASE_PATH . '/addons',
        '__PUBLIC__' => BASE_PATH . '/public',
        '__STATIC__' => BASE_PATH . '/application/user/static',
        '__IMG__'    => BASE_PATH . '/application/user/static/images',
        '__CSS__'    => BASE_PATH . '/application/user/static/css',
        '__JS__'     => BASE_PATH . '/application/user/static/js',
    ),
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'          => '系统维护中！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------

    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],

    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],

    'attachment_upload' => [
        // 允许上传的文件MiMe类型
        'mimes'    => ['mp3','mp4','zip','xls'],
        // 上传的文件大小限制 (0-不做限制)
        'maxSize'  => 0,
        // 允许上传的文件后缀
        'exts'     => [],
        // 子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
        'subName'  => ['date', 'Ymd'],
        //保存根路径
        'rootPath' => './uploads/attachment',
        // 保存路径
        'savePath' => '',
        // 上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组
        'saveName' => ['uniqid', ''],
        // 文件上传驱动e,
        'driver'   => 'Local',
    ],

    'editor_upload'     => [
        // 允许上传的文件MiMe类型
        'mimes'    => [],
        // 上传的文件大小限制 (0-不做限制)
        'maxSize'  => 0,
        // 允许上传的文件后缀
        'exts'     => [],
        // 子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
        'subName'  => ['date', 'Ymd'],
        //保存根路径
        'rootPath' => './uploads/editor',
        // 保存路径
        'savePath' => '',
        // 上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组
        'saveName' => ['uniqid', ''],
        // 文件上传驱动e,
        'driver'   => 'Local',
    ],

    'picture_upload'    => [
        // 允许上传的文件MiMe类型
        'mimes'    => ['jpg','png'],
        // 上传的文件大小限制 (0-不做限制)
        'maxSize'  => 0,
        // 允许上传的文件后缀
        'exts'     => [],
        // 子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
        'subName'  => ['date', 'Ymd'],
        //保存根路径
        'rootPath' => './uploads/picture',
        // 保存路径
        'savePath' => '',
        // 上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组
        'saveName' => ['uniqid', ''],
        // 文件上传驱动e,
        'driver'   => 'Local',
    ],
    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------

    'cache'                  => [
        // 驱动方式
        'type'   => 'File',
        // 缓存保存目录
        'path'   => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
    ],

    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'user',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
        //设置过期时间
        'expire'         => 3600*5,
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],

    // 'redis' =>  [
    //   'host'  =>  '',
    //   'select'  =>  '',
    //   'auth'  =>  'Wyyx520520',
    // ],

    //分页配置 
    'paginate'               => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],
    'sign_key'=>'fasfdasfasdfasf',
    'appVer' => '2.1',
    'res_url'=>'http://127.0.0.1/',

    'gateway_user'=>'user/fwgoip',
    'destination_extension'=>'710000000',
    //'notify_url'=>'http://192.168.1.170:5858/api/smartivr/unusualNotify',
    'notify_url'=>'http://127.0.0.1:39009/hangup',
    'smartivr_api_path'=>'',
    'ignore_fs' => [],
    'db_configs'	=>	[
        'fs1'=>	[
            // 数据库类型
            'type'        => 'mysql',
            'debug'          => true,
            // 服务器地址 47.106.166.156
            'hostname'    => '127.0.0.1',
            // 数据库名
            'database'    => 'autodialer',
            // 数据库用户名
            'username'    => 'autodialer',
            // 数据库密码
            'password'    => 'sj28kHPEXYffXRsa',
            // 数据库编码默认采用utf8
            'charset'     => 'utf8',
            // 数据库表前缀
            'prefix'      => '',
        ],
    ],
    /*'db_config1' => [
            // 数据库类型
            'type'        => 'mysql',
            'debug'       => true,
            // 服务器地址
            'hostname'    => '127.0.0.1',
            // 数据库名
            'database'    => 'autodialer',
            // 数据库用户名
            'username'    => 'root',
            // 数据库密码
            'password'    => '3a06e7d66eb9ac1e',
            // 数据库编码默认采用utf8
            'charset'     => 'utf8',
            // 数据库表前缀
            'prefix'      => '',
    ],*/
    'start_da2'=>'{execute_on_media=start_da2}',
    'WeChat'=>[
        'appid'=>'',
        'AppSecret'=>'',
    ],
    // 新增加的导出号码进行掩藏的配置
    'hide_phone_middle'=>0,
    'charge_server' => 'http://127.0.0.1',
    'push_mobile' => [
        '6444' => [
            'url'=>'http://127.0.0.1/zeng.php',
            'param' => 'sjh',
            'type' => 'post',
            'level' => array(6)
        ],
        '7121' => [
            'url'=>'http://127.0.0.1/zeng.php',
            'param' => 'sjh',
            'type' => 'post',
            'level' => array(6)
        ],
        '7248' => [
            'url'=>'http://127.0.0.1/zeng.php',
            'param' => 'sjh',
            'type' => 'post',
            'level' => array(6)
        ],
    ],
    'max_workload'  =>  1950,
    'call_table_days'=> 100,
    'order_table_days'=> 100,
    'support_asr_type'  =>  [
        'aliyun'  =>  '阿里云',
        'xfyun'   =>  '科大讯飞',
        'aiuiv2'  =>  '科大讯飞v2'
    ],
    'master_db'=>	[
        'type'           => 'mysql',
        'hostname'       => '127.0.0.1',
        'database'       => 'robot',
        'username'       => 'robot',
        'password'       => '',
        'hostport'       => '3306',
        // 连接dsn
        'dsn'            => '',
        // 数据库连接参数
        'params'         => [],
        // 数据库编码默认采用utf8
        'charset'        => 'utf8',
        // 数据库表前缀
        'prefix'         => 'rk_',
        // 数据库调试模式
        'debug'          => true,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'         => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'    => false,
        // 读写分离后 主服务器数量
        'master_num'     => 1,
        // 指定从服务器序号
        'slave_no'       => '',
        // 是否严格检查字段是否存在
        'fields_strict'  => false,
        // 数据集返回类型 array 数组 collection Collection对象
        'resultset_type' => 'array',
        // 是否自动写入时间戳字段
        'auto_timestamp' => true,
        // 是否需要进行SQL性能分析
        'sql_explain'    => false,
    ],
    'yypt_config'=>[
        'push_admin_url'=>'http://127.0.0.1/api/receive/get_admin',
        'push_robot_url'=>'http://127.0.0.1/api/receive/get_robot_use',
        'push_statistics_url'=>'http://127.0.0.1/api/receive/get_system_statistics'
    ],
    'phone_resources_count' =>  0,
    'phone_resources_status' => false,
    'export_phone_status'  =>  true,
];
