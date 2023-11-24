<?php


namespace extend\PHPExcel;
namespace extend\PHPExcel\PHPExcel;
namespace app\user\controller;
use app\common\controller\User;
use think\Db;
use think\Session;
use Qiniu\json_decode;
use app\common\controller\Log;
use app\common\controller\AdminData;
use app\common\controller\LinesData;
use app\common\controller\AutoTaskTime;
use app\common\controller\AutoTaskDate;
use app\common\controller\TelCallRecord;
use app\common\controller\Request;
use app\common\controller\RedisApiData;
use app\common\controller\RedisConnect;
use app\common\controller\TaskData;
use app\common\controller\Audio;
use app\common\controller\PhoneResources;
use app\user\controller\Scenarios;
class Plan extends User{
private $connect;
public $call_pause_second = 0;
public $inArrears;
public $fs_num;
public function _initialize() {
    parent::_initialize(); // 初始化父类
    $request = request(); // 获取当前请求
    $action = $request->action(); // 获取当前请求的动作（方法名）
    $count = count(config('db_configs')); // 获取数据库配置的数量
    $config = Db::name('tel_config')->order('id desc')->find(); // 从数据库中检索最新的tel_config配置

    // 根据fs_num的值和数据库配置，选择一个数据库连接
    if($config['fs_num'] >= $count){
        if( config('db_configs')['fs1'] ){
            $this->connect = Db::connect('db_configs.fs1');
            $this->fs_num=1;
        } else {
            for( $i=1;$i<$count;$i++){
                if( config('db_configs')['fs'.($i+1)] ){
                    $this->connect = Db::connect('db_configs.fs'.($i+1));
                    $this->fs_num=$i+1;
                    break;
                }
            }
        }
    } else {
        if( config('db_configs')['fs'.($config['fs_num']+1)] ){
            $this->connect = Db::connect('db_configs.fs'.($config['fs_num']+1));
            $this->fs_num=$config['fs_num']+1;
        } else {
            for( $i=2;$i<$count;$i++){
                if($config['fs_num']+$i>$count){
                    break;
                }
                if( config('db_configs')['fs'.($config['fs_num']+$i)] ){
                    $this->connect = Db::connect('db_configs.fs'.($config['fs_num']+$i));
                    $this->fs_num=$config['fs_num']+$i;
                    break;
                }
            }
            if( empty($this->fs_num) ){
                for( $i=1;$i<=$count;$i++){
                    if( config('db_configs')['fs'.$i] ){
                        $this->connect = Db::connect('db_configs.fs'.$i);
                        $this->fs_num=$i;
                        break;
                    }
                }
            }
        }
    }

    $show_days = get_show_day(); // 获取一些配置或数据
    $this->assign('show_days',$show_days); // 将数据分配给视图
}

public function index(){
    $user_auth = session('user_auth'); // 从会话中获取用户认证信息
    $uid = $user_auth["uid"]; // 用户ID
    $super = $user_auth["super"]; // 用户是否为超级用户
    $where = array();
    if(!$super){
        $where['member_id'] = (int)$uid; // 如果不是超级用户，设置查询条件
    }

    $gatewayUser = config('notify_url');
    if ($gatewayUser){
        $where['call_notify_url'] = $gatewayUser; // 设置额外的查询条件
    }

    // 从autodialer_task表中查询数据，并进行分页处理
    $list = $this->connect->table('autodialer_task')->where($where)->order('uuid desc')->paginate(10,false,array('query'=>$this->param));
    $page = $list->render(); // 获取分页渲染结果
    $list = $list->toArray(); // 将列表转换为数组

    // 对查询结果进行处理
    foreach ($list['data'] as $key =>$value) {
        $config = Db::name('tel_config')->where("task_id",$value['uuid'])->find(); // 获取相关配置
        $scenarios = Db::name('tel_scenarios')->field('name')->where("id",$config['scenarios_id'])->find(); // 获取场景名称
        $list['data'][$key]['scenarios'] = $scenarios['name']; // 添加场景名称到列表项
        $list['data'][$key]['bridge'] = $config['bridge']; // 添加桥接信息到列表项

        $where = array();
        $where['phone'] = $config['phone']; // 设置查询条件
        // 根据call_type获取电话信息
        if ($config['call_type'] == 0){
            $sim = Db::name('tel_sim')->field('phone')->where($where)->find();
            $list['data'][$key]['phone'] = '网关'.$sim['phone']; // 网关电话
        }
        else{
            $sim = Db::name('tel_line')->field('phone')->where($where)->find();
            $list['data'][$key]['phone'] = '线路'.$sim['phone']; // 线路电话
        }
    }

    $this->assign('super',$super); // 分配超级用户标志给视图
    $this->assign('list',$list['data']); // 分配处理后的列表数据给视图
    $this->assign('page',$page); // 分配分页数据给视图
    return $this->fetch(); // 返回渲染后的视图
}

public function newindex(){
    $redis = RedisConnect::get_redis_connect(); // 获取Redis连接
    $user_auth = session('user_auth'); // 从会话中获取用户认证信息
    $uid = $user_auth["uid"]; // 用户ID
    $super = $user_auth["super"]; // 用户是否为超级用户
    $key_list = 'var_first_create_task_'.$uid; // 构造Redis键名
    $firest_create = $redis->get($key_list); // 从Redis获取数据

    // 根据Redis中的数据设置视图变量
    if(!empty($firest_create)){
        $this->assign('var_first_create', $firest_create);
        $redis->INCRBY($key_list, 1); // 增加Redis键的值
    } else {
        $this->assign('var_first_create', 0);
    }

    $is_verification = Db::name('admin')->where('id', $uid)->value('is_verification'); // 从数据库获取验证状态
    $this->assign('is_verification', $is_verification); // 分配验证状态给视图

    // 处理Cookie，用于跟踪是否弹出
    if(isset($_COOKIE["is_eject_".$uid])){
        $is_eject = $_COOKIE["is_eject_".$uid];
    } else {
        setcookie("is_eject_".$uid, 0, 0, '/');
        $is_eject = isset($_COOKIE["is_eject_".$uid]) ? 1 : 0;
    }
    $this->assign('is_eject', $is_eject); // 分配弹出状态给视图
    setcookie("is_eject_".$uid, 1, time() + 86400, '/'); // 更新Cookie

    // 构造查询条件
    $where = array();
    if(!$super){
        $where['c.member_id'] = (int)$uid;
    }

    // 查询电话线路组数据
    $line_datas = Db::name('tel_line_group')
        ->field('id, name')
        ->where(['user_id' => $uid, 'status' => 1])
        ->select();
    $this->assign('line_datas', $line_datas); // 分配线路组数据给视图

    // 获取默认线路ID
    $default_line_id = Db::name('admin')
        ->where('id', $uid)
        ->value('default_line_id');
    $this->assign('default_line_id', $default_line_id); // 分配默认线路ID给视图

    // 查询电话接口数据
    $asr_list = Db::name('tel_interface')
        ->field('id, name')
        ->where('owner', $uid)
        ->select();
    $this->assign('asr_list', $asr_list); // 分配电话接口数据给视图

    // 查询短信模板数据
    $sms_where = array();
    $sms_where['st.owner'] = $uid;
    $sms_where['st.status'] = 3;
    $sms_template = Db::name('sms_template')
        ->alias('st')
        ->join('sms_sign ss', 'st.sign_id = ss.id', 'LEFT')
        ->field('st.id, st.name, ss.name as sign_name, st.content')
        ->where($sms_where)
        ->select();
    $this->assign('sms_template', $sms_template); // 分配短信模板数据给视图

    // 查询微信推送用户数据
    $yunying_id = $this->get_operator_id($uid); // 获取运营ID
    $wx_config = Db::name('wx_config')->where(['member_id' => $yunying_id])->find();
    $wx_push_users = Db::name('wx_push_users')
        ->field('id, name')
        ->where([
            'member_id' => $uid,
            'wx_config_id' => $wx_config['id'],
        ])
        ->select();
    $this->assign('wx_push_users', $wx_push_users); // 分配微信推送用户数据给视图

    // 查询CRM推送用户数据
    $crm_push_users = Db::name('admin')
        ->field('id, username')
        ->where([
            'pid' => $uid,
            'role_id' => 20,
        ])
        ->select();
    $this->assign('crm_push_users', $crm_push_users); // 分配CRM推送用户数据给视图

    $this->assign('super', $super); // 分配超级用户标志给视图

    // 查询场景列表数据
    $where = array();
    if(!$super){
        $where['member_id'] = $uid;
    }
    $where['status'] = 1;
    $where['check_statu'] = ['<>', 1];
    $scenarioslist = Db::name('tel_scenarios')->where($where)->field('id, name')->order('id asc')->select();
    $this->assign('scenarioslist', $scenarioslist); // 分配场景列表数据给视图

    // 查询管理员列表数据
    $where = array();
    if(!$super){
        $where['pid'] = $uid;
    }
    $where['status'] = 1;
    $adminlist = Db::name('admin')
        ->field('id, username')
        ->where($where)
        ->order('id asc')
        ->select();
    $this->assign('adminlist', $adminlist); // 分配管理员列表数据给视图

    // 查询可用机器人数量
    $usable_robot_cnt = Db::name('admin')->where('id', $uid)->value('usable_robot_cnt');
    $sum = array();
    $sum['member_id'] = $uid;
    $sum['status'] = ['=', 1];
    $rnum = Db::name('tel_config')->where($sum)->sum('robot_cnt');
    $rnum = $usable_robot_cnt - $rnum;
    if($rnum < 0){
        $rnum = 0;
    }
    $task_temp = DB::name('tel_tasks_templates')->where('member_id', $uid)->order('id', 'desc')->column('template', 'id');
    $this->assign('task_temp', $task_temp); // 分配任务模板数据给视图
    $this->assign('rnum', $rnum); // 分配可用机器人数量给视图

    return $this->fetch("new_index"); // 返回渲染后的视图
}

public function taskDetail(){
    $id = input('taskId', '', 'trim,strip_tags'); // 获取输入的任务ID，并进行清理和标签剥离
    $cwhere = array();
    if($id){
        $cwhere["task"] = $id; // 如果ID存在，则设置查询条件
    }
    $cwhere["level"] = ['>', 0]; // 设置查询条件，只选择level大于0的记录

    // 执行原生SQL查询，获取每个level的用户数量
    $memberList = Db::query("SELECT `level`, COUNT(uid) AS tp_count FROM `rk_member` WHERE `task` = ? AND level > ? GROUP BY level", [$id, 0]);

    // 获取满足条件的总成员数
    $count = Db::name('member')->where($cwhere)->count(1);

    // 遍历每个level，计算其占总数的百分比
    foreach ($memberList as $keys => $values) {
        $percent = round(($values['tp_count'] / $count) * 100, 2); // 计算百分比
        $memberList[$keys]['percent'] = $percent; // 将百分比添加到数组中
    }

    // 根据是否有成员返回不同的结果
    if($count){
        return returnAjax(0, '成功了', $memberList); // 如果有成员，返回成功和成员列表
    } else {
        return returnAjax(1, '计算失败'); // 如果没有成员，返回失败信息
    }
}

public function talkTime(){
    $id = input('taskId', '', 'trim,strip_tags'); // 获取输入的任务ID，并进行清理和标签剥离
    $cwhere = array();
    if($id){
        $cwhere["task"] = $id; // 如果ID存在，则设置查询条件
    }

    // 执行原生SQL查询，根据通话时长分组统计数量
    // INTERVAL函数用于将duration分成不同的时间段
    $memberList = Db::query("select INTERVAL(duration, 10, 60, 120) as timegroup, count(1) AS tkcount from `rk_member` WHERE `task` = ? group by timegroup", [$id]);

    // 获取满足条件的总成员数
    $count = Db::name('member')->where($cwhere)->count(1);

    // 遍历每个时间组，计算其占总数的百分比
    foreach ($memberList as $keys => $values) {
        $percent = round(($values['tkcount'] / $count) * 100, 2); // 计算百分比
        $memberList[$keys]['percent'] = $percent; // 将百分比添加到数组中
    }

    // 根据是否有统计数据返回不同的结果
    if(count($memberList)){
        return returnAjax(0, '成功了', $memberList); // 如果有统计数据，返回成功和数据列表
    } else {
        return returnAjax(1, '计算失败'); // 如果没有统计数据，返回失败信息
    }
}

public function get_operator_id($uid){
    // 获取用户及其角色信息
    $admin = Db::name('admin')
        ->alias('a')
        ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
        ->field('a.id, a.pid, ar.name as role_name')
        ->where('a.id', $uid)
        ->find();

    $roleName = $admin['role_name'];

    // 如果用户是运营商，直接返回其ID
    if (!empty($uid) && $roleName == '运营商') {
        return $admin['id'];
    }

    // 如果用户不是运营商也不是管理员，查找其上级
    if (!empty($uid) && $roleName != '运营商' && $roleName != '管理员') {
        $admin_father = Db::name('admin')
            ->alias('a')
            ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
            ->field('a.id, a.pid, ar.name as role_name')
            ->where('a.id', $admin['pid'])
            ->find();

        $father_role_name = $admin_father['role_name'];

        // 如果上级是运营商，返回其ID
        if ($father_role_name == '运营商') {
            return $admin_father['id'];
        } else {
            // 否则，继续查找上级的上级
            $admin_granddad = Db::name('admin')
                ->alias('a')
                ->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')
                ->field('a.id, a.pid, ar.name as role_name')
                ->where('a.id', $admin_father['pid'])
                ->find();

            $granddad_role_name = $admin_granddad['role_name'];

            // 如果上级的上级是运营商，返回其ID
            if ($granddad_role_name == '运营商') {
                return $admin_granddad['id'];
            } else {
                // 否则，返回最上级的ID
                $admin_last = Db::name('admin')->field('id')->where(['id' => $admin_granddad['pid']])->find();
                return $admin_last['id'];
            }
        }
    }
}

public function talkRotation(){
    $id = input('taskId', '', 'trim,strip_tags'); // 获取输入的任务ID，并进行清理和标签剥离
    $cwhere = array();
    if ($id) {
        $cwhere["task"] = $id; // 如果ID存在，则设置查询条件
    }

    // 执行原生SQL查询，根据通话轮次分组统计数量
    // INTERVAL函数用于将call_rotation分成不同的时间段
    $memberList = Db::query("select INTERVAL(call_rotation, 3, 5, 7, 10) as rotationgroup, count(1) AS tkcount from `rk_member` WHERE `task` = ? group by rotationgroup", [$id]);

    // 获取满足条件的总成员数
    $count = Db::name('member')->where($cwhere)->count(1);

    // 遍历每个轮次组，计算其占总数的百分比
    foreach ($memberList as $keys => $values) {
        $percent = round(($values['tkcount'] / $count) * 100, 2); // 计算百分比
        $memberList[$keys]['percent'] = $percent; // 将百分比添加到数组中
    }

    // 根据是否有统计数据返回不同的结果
    if (count($memberList)) {
        return returnAjax(0, '成功了', $memberList); // 如果有统计数据，返回成功和数据列表
    } else {
        return returnAjax(1, '计算失败'); // 如果没有统计数据，返回失败信息
    }
}

public function lineup(){
    $Page_size = 10; // 设置每页显示的记录数
    $page = input('page', '1', 'trim,strip_tags'); // 获取当前页码
    $user_auth = session('user_auth'); // 获取当前用户的认证信息
    $uid = $user_auth["uid"]; // 获取用户ID
    $super = $user_auth["super"]; // 获取用户的权限等级

    $where = array();
    $where["task"] = input('taskId', '', 'trim,strip_tags'); // 获取任务ID
    $where["status"] = 1; // 设置查询条件：状态为1

    // 查询成员信息
    $list = Db::name('member')
        ->field('uid, mobile, nickname, status, duration, last_dial_time, originating_call')
        ->where($where)
        ->order('uid desc')
        ->page($page, $Page_size)
        ->select();

    $count = Db::name('member')->where($where)->count(1); // 获取总记录数
    $page_count = ceil($count / $Page_size); // 计算总页数

    $back = array();
    $back['total'] = $count; // 总记录数
    $back['Nowpage'] = $page; // 当前页码
    $back['list'] = $list; // 当前页的数据列表
    $back['page'] = $page_count; // 总页数

    return returnAjax(0, '获取数据成功', $back); // 返回数据
}

public function alreadyDialed(){
    $Page_size = 10; // 设置每页显示的记录数
    $page = input('page', '1', 'trim,strip_tags'); // 获取当前页码
    $user_auth = session('user_auth'); // 获取当前用户的认证信息
    $uid = $user_auth["uid"]; // 获取用户ID
    $super = $user_auth["super"]; // 获取用户的权限等级

    $where = array();
    $where["task"] = input('taskId', '', 'trim,strip_tags'); // 获取任务ID
    $where["status"] = ['>', 1]; // 设置查询条件：状态大于1

    // 获取并处理其他筛选条件
    $Lengthoftime = input('Lengthoftime', '', 'trim,strip_tags');
    $rotation = input('rotation', '', 'trim,strip_tags');
    $activeMode = input('activeMode', '', 'trim,strip_tags');
    $Levelofintention = input('Levelofintention', '', 'trim,strip_tags');

    // 根据筛选条件设置查询条件
    if ($Lengthoftime) {
        // ...
    }
    // 其他筛选条件的处理逻辑类似

    // 查询成员信息
    $list = Db::name('member')
        ->field('uid, mobile, nickname, real_name, status, duration, last_dial_time, originating_call, level')
        ->where($where)
        ->order('uid desc')
        ->page($page, $Page_size)
        ->select();

    // 格式化列表中的时间
    foreach ($list as &$item) {
        if ($item['last_dial_time'] > 0) {
            $item['last_dial_time'] = date('Y-m-d H:i:s', $item['last_dial_time']);
        } else {
            $item['last_dial_time'] = "";
        }
    }

    $count = Db::name('member')->where($where)->count(1); // 获取总记录数
    $page_count = ceil($count / $Page_size); // 计算总页数

    $back = array();
    $back['total'] = $count; // 总记录数
    $back['Nowpage'] = $page; // 当前页码
    $back['list'] = $list; // 当前页的数据列表
    $back['page'] = $page_count; // 总页数

    return returnAjax(0, '获取数据成功', $back); // 返回数据
}

public function cesday($day1, $day2){
    $day1 = strtotime($day1); // 将第一个日期字符串转换为Unix时间戳
    $day2 = strtotime($day2); // 将第二个日期字符串转换为Unix时间戳

    $distance = ($day2 - $day1) / 86400; // 计算两个日期之间的天数差
    $daylist = array(); // 初始化一个数组来存储每一天的日期

    for ($i = 0; $i <= $distance; $i++) {
        // 循环遍历从第一个日期到第二个日期之间的每一天
        $daylist[] = date('Y-m-d', $day1 + (86400 * $i)); // 将每一天的日期添加到数组中
    }

    $daylist = implode(',', $daylist); // 将日期数组转换为以逗号分隔的字符串

    return $daylist; // 返回日期字符串
}

public function verify_task_asr_start($user_id, $task_id) {
    // 检查用户ID和任务ID是否为空，如果任一为空，则返回false
    if (empty($user_id) || empty($task_id)) {
        return false;
    }

    // 从数据库中查询特定用户和任务的ASR（自动语音识别）接口信息
    $tel_interfaceArr = Db::name('tel_interface ti')
        ->join('tel_config tc', 'ti.id=tc.asr_id', 'left')
        ->where('ti.owner', $user_id)
        ->where('tc.task_id', $task_id)
        ->field('ti.asr_from, ti.asr_token')
        ->find();

    // 检查ASR来源是否为1且ASR令牌是否为空，如果是，则返回true
    if ($tel_interfaceArr['asr_from'] != 1 || empty($tel_interfaceArr['asr_token'])) {
        return true;
    }

    // 查询是否存在一个特定的ASR令牌，该令牌属于用户名为'admin'的用户且余额不足
    $tel_interfaceArr = Db::name('tel_interface ti')
        ->join('admin a', 'ti.owner=a.id', 'left')
        ->where('a.username', 'admin')
        ->where('ti.asr_token', $tel_interfaceArr['asr_token'])
        ->where('ti.money', '<=', 0)
        ->find();

    // 如果没有找到符合条件的记录，则返回true，否则返回false
    return empty($tel_interfaceArr);
}

public function testVerfy() {
    // 从POST请求中获取用户ID、用户名、任务ID和任务名称
    $uid = input('post.uid');
    $username = input('post.username');
    $task_id = input('post.task_id');
    $task_name = input('post.task_name');

    // 如果没有提供用户ID但提供了用户名，则尝试根据用户名查找用户
    if (!$uid && $username) {
        $p_user = Db::name('admin')
            ->where('username', 'like', '%' . $username . '%')
            ->select();

        // 如果没有找到用户，则打印消息并返回
        if (!$p_user) {
            print('No Users! like ' . $username);
            return;
        }

        // 打印用户信息集合
        print("用户信息集合：\n");
        print_r($p_user);

        // 从找到的用户中选择第一个用户的ID
        $uid = $p_user[0]['id'];

        // 打印使用的用户信息
        print("\n用户信息集合-------\n\n");
        print("使用的用户信息\n");
        print($uid);
        print("\n使用的用户信息-------\n\n");
    }

    // 如果没有提供任务ID但提供了任务名称，则尝试根据任务名称查找任务
    if (!$task_id && $task_name) {
        $p_task = Db::name('tel_config')
            ->where('task_name', $task_name)
            ->where('member_id', $uid)
            ->select();

        // 如果没有找到任务，则打印消息并返回
        if (!$p_task) {
            print('No Tasks! like ' . $task_name);
            return;
        }

        // 打印任务信息集合
        print("任务信息集合：\n");
        print_r($p_task);

        // 从找到的任务中选择第一个任务的ID
        $task_id = $p_task[0]['task_id'];

        // 打印使用的任务信息
        print("\n任务信息集合-------\n\n");
        print("使用的任务信息\n");
        print($task_id);
        print("\n使用的任务信息-------\n\n");
    }

    // 打印欠费标志位
    echo "欠费标志位";
    dump($this->verify_task_asr_start($uid, $task_id) ? '不欠费' : '欠费');

    // 打印验证结果
    var_dump($this->verify_task_asr_start($uid, $task_id));
}

public function get_scenarios_node_name($id) {
    // 检查是否提供了ID
    if (!empty($id)) {
        // 根据提供的ID从数据库中查询场景节点的名称
        $name = Db::name('tel_scenarios_node')->where(['id' => $id])->value('name');

        // 返回找到的场景节点名称
        return $name;
    }

    // 如果没有提供ID或找不到对应的场景节点，返回默认消息
    return '没有场景节点';
}

public  function get_flow_node_name_by_arr($arr){
if(!is_array($arr)){
return '';
}
if(empty($arr)){
return '';
}
$name_str='';
foreach($arr as $value){
$name = Db::name('tel_flow_node')->field('name')->where(['id'=>$value])->value('name');
$name_str .= $name.'-';
}
return trim($name_str,'-');
}
public function create_task()	{
$task_name = input('task_name','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$start_date = input('start_date/a','','trim,strip_tags');
$end_date = input('end_date/a','','trim,strip_tags');
$start_time = input('start_time/a','','trim,strip_tags');
$end_time = input('end_time/a','','trim,strip_tags');
$is_auto = input('is_auto','','trim,strip_tags');
$robot_count = input('robot_count','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
$asr_id = input('asr_id','','trim,strip_tags');
$is_default_line = input('is_default_line','','trim,strip_tags');
$task_abnormal_remind_phone = input('task_abnormal_remind_phone','','trim,strip_tags');
$is_again_call = input('is_again_call','','trim,strip_tags');
$again_call_status = input('again_call_status/a','','trim,strip_tags');
if(is_array($again_call_status) === true){
$again_call_status = implode(',',$again_call_status);
}else{
$again_call_status = '';
}
$again_call_count = input('again_call_count/d','','trim,strip_tags');
$send_sms_status = input('send_sms_status','','trim,strip_tags');
$send_sms_level = input('send_sms_level/a','','trim,strip_tags');
if(is_array($send_sms_level) &&count($send_sms_level) >0){
$send_sms_level = implode(',',$send_sms_level);
}else{
$send_sms_level = '';
}
$yunkong_push_status = input('yunkong_push_status',0,'trim,strip_tags');
$yunkong_push_username = input('yunkong_push_username','','trim,strip_tags');
$yunkong_push_level = input('yunkong_push_level/a','','trim,strip_tags');
if(is_array($yunkong_push_level) &&count($yunkong_push_level) >0){
$yunkong_push_level = implode(',',$yunkong_push_level);
}else{
$yunkong_push_level = '';
}
$sms_template_id = input('sms_template_id','','trim,strip_tags');
$is_add_crm = input('is_add_crm','','trim,strip_tags');
$add_crm_level = input('add_crm_level/a','','trim,strip_tags');
if(is_array($add_crm_level) &&count($add_crm_level)){
$add_crm_level = implode(',',$add_crm_level);
}else{
$add_crm_level = '';
}
$crm_push_user_id = input('crm_push_user_id','','trim,strip_tags');
$wx_push_status = input('wx_push_status','','trim,strip_tags');
$wx_push_level = input('wx_push_level/a','','trim,strip_tags');
if(is_array($wx_push_level) &&count($wx_push_level)){
$wx_push_level = implode(',',$wx_push_level);
}else{
$wx_push_level = '';
}
$wx_push_user_id = input('wx_push_user_id','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$call_type = 1;
$status = 0;
if(empty($task_name)){
return $this->Json(2,'任务名不能为空');
}
if(empty($scenarios_id)){
return $this->Json(2,'请选择话术');
}
if(empty($robot_count)){
return $this->Json(2,'机器人数量不能为空');
}
if(empty($line_id)){
return $this->Json(2,'请选择线路');
}
if(empty($asr_id)){
return $this->Json(2,'请选择ASR');
}
$regex = config('phone_regular');
if(preg_match($regex,$task_abnormal_remind_phone) === false){
return returnAjax(2,'任务异常短信提醒的手机号码格式错误');
}
if($is_again_call == 1){
if(empty($again_call_status)){
return returnAjax(2,'请选择需要进行重新呼叫的通话状态');
}
if(empty($again_call_count)){
return returnAjax(2,'请选择重新呼叫次数');
}
}
if($yunkong_push_status == 1){
if(empty($yunkong_push_username)){
return returnAjax(2,'推送微信云控的用户名不能为空');
}
if(empty($yunkong_push_level)){
return returnAjax(2,'请选择需要推送到微信云控的意向等级');
}
}
if($send_sms_status == 1){
if(empty($send_sms_level)){
return returnAjax(2,'没有选择触发发送短信的意向等级');
}
if(empty($sms_template_id)){
return returnAjax(2,'没有选中指定短信模版');
}
}
if($is_add_crm == 1){
if(empty($add_crm_level)){
return returnAjax(2,'请选择加入CRM的客户意向等级');
}
}
if($wx_push_status == 1){
if(empty($wx_push_level)){
return returnAjax(2,'请选择微信推送的客户意向等级');
}
if(empty($wx_push_user_id)){
return returnAjax(2,'请选择推动的人员');
}
}
$line_count = Db::name('tel_line_group')
->where(['id'=>$line_id,'status'=>1])
->count('id');
if(empty($line_count)){
return returnAjax(2,'线路组不存在');
}
$user_auth = session('user_auth');
$usable_robot_cnt = Db::name('admin')
->where("id",$user_auth['uid'])
->value('usable_robot_cnt');
$run_robot_count =  Db::name('tel_config')
->where('member_id',$user_auth['uid'])
->where('status','1')
->sum('robot_cnt');
$usable_robot_count = $usable_robot_cnt -$run_robot_count;
if($robot_count >$usable_robot_count){
return returnAjax(2,'机器人数量不足');
}
$scenarios_count = Db::name('tel_scenarios')
->where('id',$scenarios_id)
->count('id');
if(empty($scenarios_count)){
return returnAjax(2,'话术不存在');
}
$task_name_count = Db::name('tel_config')
->where([
'task_name'=>$task_name,
'member_id'=>$user_auth['uid'],
'status'=>['neq',-1],
])
->count('id');
if($task_name_count >0){
return returnAjax(2,'任务名已重复');
}
if($is_default_line == 1){
$update_line_id = Db::name('admin')
->where('id',$user_auth['uid'])
->update([
'default_line_id'=>$line_id
]);
}else if($is_default_line == 0){
Db::name('admin')->where('id',$user_auth['uid'])
->update(['default_line_id'=>0 ]);
}
$max_destination_extension = Db::name('tel_config')->field('destination_extension')->order('id desc')->find();
if ($max_destination_extension &&$max_destination_extension['destination_extension'] >0){
$destination_extension = ((int)$max_destination_extension['destination_extension'])+1;
}else{
$destination_extension =  config('destination_extension');
}
$line_data = Db::name('tel_line')
->field('dial_format,phone,originate_variables,call_prefix,type_link')
->where('id',$line_id)
->find();
$task_config = [];
$task_config['fs_num'] = 0;
$task_config['member_id'] = $user_auth['uid'];
$task_config["task_name"] = $task_name;
$task_config["scenarios_id"] = $scenarios_id;
$task_config["call_type"] = $call_type;
$task_config["status"] = $status;
$task_config["destination_extension"] = $destination_extension;
$task_config["phone"] = $line_data['phone']??'';
$task_config["call_prefix"] = $line_data['call_prefix']??'';
$task_config['remarks'] = $remark;
$task_config['default_line_id'] = $is_default_line;
$task_config['robot_cnt'] = $robot_count;
$task_config['create_time'] = time();
$task_config['is_auto'] = $is_auto;
$task_config['asr_id'] = $asr_id;
$task_config['call_phone_group_id'] = $line_id;
$task_config['send_sms_status'] = $send_sms_status;
$task_config['send_sms_level'] = $send_sms_level;
$task_config['sms_template_id'] = $sms_template_id;
$task_config['is_add_crm'] = $is_add_crm;
$task_config['add_crm_level'] = $add_crm_level;
$task_config['add_crm_zuoxi'] = $crm_push_user_id;
$task_config['wx_push_status'] = $wx_push_status;
$task_config['wx_push_level'] = $wx_push_level;
$task_config['wx_push_user_id'] = $wx_push_user_id;
$task_config['is_again_call'] = $is_again_call;
$task_config['again_call_status'] = $again_call_status;
$task_config['again_call_count'] = $again_call_count;
$task_config['already_again_call_count'] = 0;
$task_config['yunkong_push_status'] = $yunkong_push_status;
$task_config['yunkong_push_username'] = $yunkong_push_username;
$task_config['yunkong_push_level'] = $yunkong_push_level;
$task_config['task_abnormal_remind_phone'] = $task_abnormal_remind_phone;
Db::startTrans();
try{
$task_id = Db::name('tel_config')->insertGetId($task_config);
if(empty($task_id)){
\think\Log::record('创建WEB端的任务配置表失败');
}
Db::name('tel_config')->where(['id'=>$task_id])->update(['task_id'=>$task_id]);
if($start_date &&$end_date &&$start_time &&$end_time){
$AutoTaskDate = new AutoTaskDate();
$insert_result = $AutoTaskDate->insert($user_auth['uid'],$task_id,$start_date,$end_date);
if(empty($insert_result)){
\think\Log::record('创建指定日期失败');
}
$AutoTaskTime = new AutoTaskTime();
foreach($start_time as $key=>$value){
$start_time[$key] = $value .':00';
$end_time[$key] = $end_time[$key] .':00';
}
$insert_result = $AutoTaskTime->insert($user_auth['uid'],$task_id,$start_time,$end_time);
if(empty($insert_result)){
\think\Log::record('创建指定时间失败');
}
}
Db::commit();
$is_variable = Db::name('tel_scenarios')->where(['id'=>$scenarios_id])->value('is_variable');
if($is_variable==1){
$redis = RedisConnect::get_redis_connect();
$uid= $user_auth['uid'];
$key_list = 'var_first_create_task_'.$uid;
$redis->INCRBY($key_list,1);
}
\think\Log::record('新建任务成功');
return returnAjax(0,'新建任务成功');
}catch (\Exception $e) {
Db::rollback();
return returnAjax(1,'新建任务失败');
}
}
public function verify_yunkong_username()
{
$username = input('username','','trim,strip_tags');
if(empty($username)){
return returnAjax(1,'用户名不能为空');
}
$Request = new Request();
$url = 'http://yktest.tyyke.com/index.php/Admin/Contact/apiCheck';
$params = [
'account'=>$username
];
$data = $Request->get($url,$params);
if($data['code'] == 0){
return returnAjax(0,'存在');
}else{
return returnAjax(1,'不存在');
}
}
public function get_task_config_data_api()
{
$user_auth = session('user_auth');
$task_id = input('task_id','','trim,strip_tags');
$tel_config = Db::name('tel_config')
->where('task_id',$task_id)
->find();
$task_date = Db::name('auto_task_date')
->where('task_id',$task_id)
->select();
$task_time = Db::name('auto_task_time')
->where('task_id',$task_id)
->select();
$usable_robot_count = Db::name('admin')
->where('id',$user_auth['uid'])
->value('usable_robot_cnt');
$run_robot_count = Db::name('tel_config')
->where([
'member_id'=>$user_auth['uid'],
'status'=>1,
'task_id'=>['<>',$task_id]
])
->sum('robot_cnt');
$datas = [];
$datas = $tel_config;
$datas['usable_robot_count'] = ($usable_robot_count -$run_robot_count);
foreach($task_date as $key=>$value){
$task_date[$key]['start_date'] = date('Y-m-d',strtotime($value['start_date']));
$task_date[$key]['end_date'] = date('Y-m-d',strtotime($value['end_date']));
}
foreach($task_time as $key=>$value){
$task_time[$key]['start_time'] = date('H:i',strtotime($value['start_time']));
$task_time[$key]['end_time'] = date('H:i',strtotime($value['end_time']));
}
$datas['task_date'] = $task_date;
$datas['task_time'] = $task_time;
return returnAjax(0,'成功',$datas);
}
public function update_task_api()
{
$task_id = input('task_id','','trim,strip_tags');
$task_name = input('task_name','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$start_date = input('start_date/a','','trim,strip_tags');
$end_date = input('end_date/a','','trim,strip_tags');
$start_time = input('start_time/a','','trim,strip_tags');
$end_time = input('end_time/a','','trim,strip_tags');
$is_auto = input('is_auto','','trim,strip_tags');
$robot_count = input('robot_count','','trim,strip_tags');
$line_id = input('line_id/d','','trim,strip_tags');
$asr_id = input('asr_id/d','','trim,strip_tags');
$is_default_line = input('is_default_line','','trim,strip_tags');
$task_abnormal_remind_phone = input('task_abnormal_remind_phone','','trim,strip_tags');
$is_again_call = input('is_again_call','','trim,strip_tags');
$again_call_status = input('again_call_status/a','','trim,strip_tags');
if(is_array($again_call_status) === true){
$again_call_status = implode(',',$again_call_status);
}else{
$again_call_status = '';
}
$again_call_count = input('again_call_count/d','','trim,strip_tags');
$send_sms_status = input('send_sms_status/d','','trim,strip_tags');
$send_sms_level = input('send_sms_level/a','','trim,strip_tags');
if(is_array($send_sms_level) &&count($send_sms_level)){
$send_sms_level = implode(',',$send_sms_level);
}else{
$send_sms_level = '';
}
$yunkong_push_status = input('yunkong_push_status',0,'trim,strip_tags');
$yunkong_push_username = input('yunkong_push_username','','trim,strip_tags');
$yunkong_push_level = input('yunkong_push_level/a','','trim,strip_tags');
if(is_array($yunkong_push_level) &&count($yunkong_push_level) >0){
$yunkong_push_level = implode(',',$yunkong_push_level);
}else{
$yunkong_push_level = '';
}
$sms_template_id = input('sms_template_id/d','','trim,strip_tags');
$is_add_crm = input('is_add_crm','','trim,strip_tags');
$add_crm_level = input('add_crm_level/a','','trim,strip_tags');
if(is_array($add_crm_level) &&count($add_crm_level)){
$add_crm_level = implode(',',$add_crm_level);
}else{
$add_crm_level = '';
}
$crm_push_user_id = input('crm_push_user_id','','trim,strip_tags');
$wx_push_status = input('wx_push_status','','trim,strip_tags');
$wx_push_level = input('wx_push_level/a','','trim,strip_tags');
if(is_array($wx_push_level) &&count($wx_push_level)){
$wx_push_level = implode(',',$wx_push_level);
}else{
$wx_push_level = '';
}
$wx_push_user_id = input('wx_push_user_id','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$call_type = 1;
if(empty($task_name)){
return $this->Json(2,'任务名不能为空');
}
if(empty($scenarios_id)){
return $this->Json(2,'请选择话术');
}
if(empty($robot_count)){
return $this->Json(2,'机器人数量不能为空');
}
if(empty($line_id)){
return $this->Json(2,'请选择线路');
}
if(empty($asr_id)){
return $this->Json(2,'请选择ASR');
}
$regex = config('phone_regular');
if(preg_match($regex,$task_abnormal_remind_phone) === false){
return returnAjax(2,'任务异常短信提醒的手机号码格式错误');
}
if($is_again_call == 1){
if(empty($again_call_status)){
return returnAjax(2,'请选择需要进行重新呼叫的通话状态');
}
if(empty($again_call_count)){
return returnAjax(2,'请选择重新呼叫次数');
}
}
if($yunkong_push_status == 1){
if(empty($yunkong_push_username)){
return returnAjax(2,'推送微信云控的用户名不能为空');
}
if(empty($yunkong_push_level)){
return returnAjax(2,'请选择需要推送到微信云控的意向等级');
}
}
if($send_sms_status == 1){
if(empty($send_sms_level)){
return returnAjax(2,'没有选择触发发送短信的意向等级');
}
if(empty($sms_template_id)){
return returnAjax(2,'没有选中指定短信模版');
}
}
if($is_add_crm == 1){
if(empty($add_crm_level)){
return returnAjax(2,'请选择加入CRM的客户意向等级');
}
}
if($wx_push_status == 1){
if(empty($wx_push_level)){
return returnAjax(2,'请选择微信推送的客户意向等级');
}
if(empty($wx_push_user_id)){
return returnAjax(2,'请选择推动的人员');
}
}
$task_data = Db::name('tel_config')->where('task_id',$task_id)->field('status, fs_num')->find();
$run_robot_cont = 0;
if($task_data['status'] == 1){
$fs_num = $task_data['fs_num'];
$run_robot_cont = Db::connect(config('db_configs.fs'.$task_data['fs_num']))->table('autodialer_task')->where('start',1)->sum('maximumcall');
$max_workload = config('max_workload');
$surplus_robot_count = $max_workload -$run_robot_cont;
if(($surplus_robot_count -$robot_count) <0){
return returnAjax(2,' 当前线路繁忙，需要减少'.($robot_count -$surplus_robot_count).'个机器人数量，任务可以正常进行');
}
}
$line_count = Db::name('tel_line_group')
->where('id',$line_id)
->count('id');
if(empty($line_count)){
return returnAjax(2,'线路组不存在');
}
$user_auth = session('user_auth');
$asr_count = Db::name('tel_interface')
->where([
'id'=>$asr_id,
'owner'=>$user_auth['uid']
])
->count('id');
if(empty($asr_count)){
return returnAjax(2,'ASR不存在');
}
$usable_robot_cnt = Db::name('admin')
->where("id",$user_auth['uid'])
->value('usable_robot_cnt');
$run_robot_count =  Db::name('tel_config')
->where('member_id',$user_auth['uid'])
->where('status','1')
->where('task_id','<>',$task_id)
->sum('robot_cnt');
$usable_robot_count = $usable_robot_cnt -$run_robot_count;
if($robot_count >$usable_robot_count){
return returnAjax(2,'机器人数量不足');
}
$scenarios_count = Db::name('tel_scenarios')
->where('id',$scenarios_id)
->count('id');
if(empty($scenarios_count)){
return returnAjax(2,'话术不存在');
}
$task_name_count = Db::name('tel_config')
->where([
'task_name'=>$task_name,
'member_id'=>$user_auth['uid'],
'task_id'=>['<>',$task_id],
'status'=>['<>',-1]
])
->count('id');
if($task_name_count >0){
return returnAjax(2,'任务名已重复');
}
if($is_default_line == 1){
$update_line_id = Db::name('admin')
->where('id',$user_auth['uid'])
->update(['default_line_id'=>$line_id]);
}else{
$update_line_id = Db::name('admin')
->where('id',$user_auth['uid'])
->update(['default_line_id'=>0]);
}
$TaskData = new TaskData();
$line_id_min=$TaskData->getMinLineId($line_id);
$line_data = Db::name('tel_line')
->field('dial_format,phone,originate_variables,call_prefix,type_link')
->where('id',$line_id_min)
->find();
$task = [];
if(!empty($task_data['fs_num'])){
$task['name'] =  $task_name;
$task['remark'] = input('remark','','trim,strip_tags');
if (config('start_da2')){
if (isset($task['originate_variables']) &&$task['originate_variables']){
$task['originate_variables'] = $task['originate_variables'].','.config('start_da2');
}else{
$task['originate_variables']  = config('start_da2');
}
}
$task['destination_dialplan'] = "XML";
$task['destination_context'] = "default";
$task['maximumcall'] = $robot_count;
$task['alter_datetime']	=	date('Y-m-d H:i:s');
if($line_data['type_link'] == 2){
$task['call_pause_second'] = 10;
}else{
$task['call_pause_second'] = 0;
}
if($line_data['type_link'] == 2){
$task['call_per_second'] = 1;
}else{
$task['call_per_second'] = 100;
}
$task['dial_format'] = $line_data['dial_format']??'';
$task['_origination_caller_id_number'] = $line_data['phone']??'';
$task['originate_variables'] = $line_data['originate_variables']??'';
$fs_num = Db::name('tel_config')->where(['id'=>$task_id])->value('fs_num');
$update_result =  Db::connect('db_configs.fs'.$fs_num)->table('autodialer_task')->where('uuid',$task_id)->update($task);
if(empty($update_result)){
\think\Log::record('更新FS端任务表失败');
}
}
$task_config = [];
$task_config['member_id'] = $user_auth['uid'];
$task_config["task_name"] = $task_name;
$task_config["scenarios_id"] = $scenarios_id;
$task_config["call_type"] = $call_type;
$task_config["phone"] = $line_data['phone'];
$task_config["call_prefix"] = $line_data['call_prefix'];
$task_config['remarks'] = $remark;
$task_config['robot_cnt'] = $robot_count;
$task_config['default_line_id'] = $is_default_line;
$task_config['is_auto'] = $is_auto;
$task_config['asr_id'] = $asr_id;
$task_config['call_phone_group_id'] = $line_id;
$task_config['call_phone_id'] = $line_id_min;
$task_config['send_sms_status'] = $send_sms_status;
$task_config['send_sms_level'] = $send_sms_level;
$task_config['sms_template_id'] = $sms_template_id;
$task_config['is_add_crm'] = $is_add_crm;
$task_config['add_crm_level'] = $add_crm_level;
$task_config['add_crm_zuoxi'] = $crm_push_user_id;
$task_config['wx_push_status'] = $wx_push_status;
$task_config['wx_push_level'] = $wx_push_level;
$task_config['wx_push_user_id'] = $wx_push_user_id;
$task_config['is_again_call'] = $is_again_call;
$task_config['again_call_status'] = $again_call_status;
$task_config['again_call_count'] = $again_call_count;
$task_config['yunkong_push_status'] = $yunkong_push_status;
$task_config['yunkong_push_username'] = $yunkong_push_username;
$task_config['yunkong_push_level'] = $yunkong_push_level;
$task_config['task_abnormal_remind_phone'] = $task_abnormal_remind_phone;
$update_result = Db::name('tel_config')->where('task_id',$task_id)->update($task_config);
if(empty($update_result)){
\think\Log::record('更新WEB端的任务配置表失败');
}
$RedisApiData = new RedisApiData();
$RedisApiData->resetTaskMinSeatId($task_id);
if($start_date &&$end_date &&$start_time &&$end_time){
$AutoTaskDate = new AutoTaskDate();
$delete_result = $AutoTaskDate->delete($user_auth['uid'],$task_id);
$insert_result = $AutoTaskDate->insert($user_auth['uid'],$task_id,$start_date,$end_date);
if(empty($insert_result)){
\think\Log::record('创建新的指定日期失败');
}
$AutoTaskTime = new AutoTaskTime();
foreach($start_time as $key=>$value){
$start_time[$key] = $value .':00';
$end_time[$key] = $end_time[$key] .':00';
}
$delete_result = $AutoTaskTime->delete($user_auth['uid'],$task_id);
$insert_result = $AutoTaskTime->insert($user_auth['uid'],$task_id,$start_time,$end_time);
if(empty($insert_result)){
\think\Log::record('创建新的指定时间失败');
}
}else{
Db::name('auto_task_date')->where(['task_id'=>$task_id,'member_id'=>$user_auth['uid']])->delete();
Db::name('auto_task_time')->where(['task_id'=>$task_id,'member_id'=>$user_auth['uid']])->delete();
}
$redis_key = 'task_config_'.$task_id.'_find';
$redis = RedisConnect::get_redis_connect();
$redis->del($redis_key);
\think\Log::record('任务测试');
return returnAjax(0,'成功');
}
public function export_task_all_number(){
$task_id = input('task_id','','trim,strip_tags');
if(empty($task_id)){
return returnAjax(2,'参数错误');
}
$numbers = Db::name('member')
->field('mobile')
->where('task',$task_id)
->select();
$objPHPExcel = new \PHPExcel();
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A1','客户号码');
foreach ($list as $k =>$v) {
$num = $k +2;
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A'.$num,hide_phone_middle($v['mobile']));
}
$setTitle='Sheet1';
$fileName='所有号码';
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
return returnAjax(0,'导出成功',config('res_url').ltrim($execlpath,"."));
}
public function export_task_not_dialing_number(){
$task_id = input('task_id','','trim,strip_tags');
if(empty($task_id)){
return returnAjax(2,'参数错误');
}
$numbers = Db::name('member')
->field('mobile')
->where([
'task'=>$task_id,
'status'=>1
])
->select();
$objPHPExcel = new \PHPExcel();
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A1','客户号码');
foreach ($list as $k =>$v) {
$num = $k +2;
$objPHPExcel->setActiveSheetIndex(0)
->setCellValue('A'.$num,hide_phone_middle($v['mobile']) );
}
$setTitle='Sheet1';
$fileName='未拨打号码';
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
return returnAjax(0,'导出成功',config('res_url').ltrim($execlpath,"."));
}
public function impload_task_numbers_api(){
set_time_limit(60*60*16);
ini_set('memory_limit','256M');
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES ['excel'] ['tmp_name'];
}else{
return returnAjax(2,'上传失败,请下载模板，填好再选择文件上传。');
}
$file_types = explode(".",$_FILES['excel']['name']);
$file_type = $file_types [count ( $file_types ) -1];
$taskId = input('task_id','','trim,strip_tags');
$savePath = './uploads/Excel/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy ( $tmp_file,$savePath .$file_name ))
{
return returnAjax(1,'上传失败');
}
$foo = new \PHPExcel_Reader_Excel2007();
$extension = strtolower( pathinfo($file_name,PATHINFO_EXTENSION) );
if($extension == 'xlsx') {
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
else{
$inputFileType = 'Excel5';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$objPHPExcel = $objReader->load($savePath.$file_name,$encode = 'utf-8');
$excelArr = $objPHPExcel->getsheet(0)->toArray();
$count = count($excelArr) -1;
if (count($excelArr) <2){
return returnAjax(1,'导入的文件没有数据!');
}
$number = [];
$repeat_count = 0;
unset($excelArr[0]);
foreach($excelArr as $key=>$value){
if(isset($number[$value[1]]) === false){
$number[$value[1]] = 1;
}else{
$number[$value[1]]++;
}
if($number[$value[1]] >1){
unset($excelArr[$key]);
$repeat_count++;
}
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if ($taskId){
$telConfig = Db::name('tel_config')
->field('scenarios_id')
->where('task_id',$taskId)
->find();
}
$data = array();
$taskdata = array();
$totalCnt = 0;
$successCnt = 0;
$long = count($excelArr);
$numlist = array();
$success_count = 0;
$existence_number_rows = [];
$existence_number_count = 0;
foreach ( $excelArr as $k =>$v ){
$isMob="/^1[2345789]{1}\d{9}$/";
$user['mobile'] = trim(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$v[0]));
$totalCnt++;
if(preg_match($isMob,$user['mobile'])){
$success_count++;
$user['owner'] = $uid;
if($taskId){
$user['task'] = $taskId;
$user['status'] = 1;
if ($telConfig){
$user['scenarios_id'] = $telConfig['scenarios_id'];
}
}else{
$user['status'] = 0;
}
if(!empty($user['mobile'])){
$successCnt++;
array_push($data,$user);
$taskuser['number'] = $user['mobile'];
array_push($taskdata,$taskuser);
array_push($numlist,$user['mobile']);
}
}else{
$existence_number_count++;
}
if($successCnt == 100 ||$totalCnt == $long){
$where = array();
if($taskId){
$where['task'] = $taskId;
}else{
$where['task'] = "";
}
$where['owner'] = $uid;
$mlist = Db::name('member')->field('owner,mobile')
->where($where)
->where("mobile","in",$numlist)
->select();
if(!empty($mlist)){
foreach ($data as $dakey =>$davalue) {
foreach ($mlist as $key =>$value) {
if( $davalue['mobile'] == $value['mobile']){
if(isset($data[$dakey]) === true &&isset($taskdata[$dakey]) === true){
unset($data[$dakey]);
unset($taskdata[$dakey]);
$existence_number_count++;
$success_count--;
$existence_number_rows[] = ($dakey +1);
}
}
}
}
}
if($data){
$result = Db::name('member')
->insertAll($data);
array_splice($data,0,count($data));
}
if ($taskId &&$taskdata){
$ret = $this->connect->table('autodialer_number_'.$taskId)
->insertAll($taskdata);
array_splice($taskdata,0,count($taskdata));
}
$successCnt = 0;
array_splice($numlist,0,count($numlist));
}
}
ini_set('memory_limit','-1');
$data = [
'repeat_count'=>$repeat_count,
'success_count'=>$success_count,
'existence_number_count'=>$existence_number_count,
'existence_number_rows'=>implode(',',$existence_number_rows),
'count'=>$count
];
return returnAjax(0,'导入成功');
}
public function verify_task_start($user_id){
if(empty($user_id)){
return false;
}
$p_user = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->field('a.is_jizhang,a.id, a.username,(a.money + a.credit_line) as balance, a.pid, ar.name as role_name')
->where('a.id',$user_id)
->find();
$is_jizhang=$p_user['is_jizhang'];
$p_role_name=getRoleNameByUserId($p_user['pid']);
if($p_user['balance'] <= 0){
if($p_role_name=='商家'&&$p_user['role_name']=='销售人员'&&$p_user['is_jizhang']==1){
}else{
$this->inArrears=$p_user['username'];
return false;
}
}
while(!empty($p_user['pid'])){
$p_user = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->field('a.id, a.username,(a.money + a.credit_line) as balance, a.pid, ar.name as role_name')
->where('a.id',$p_user['pid'])
->find();
if( $p_user['balance'] <= 0 ){
if( ($is_jizhang==0 &&$p_user['role_name']=='商家') ||$p_user['role_name'] =='管理员'){
continue;
}
if($p_user['balance'] <= 0){
$this->inArrears=$p_user['username'];
return false;
}
}
}
return true;
}
public function update_status() //点击任何开始，第二次进入的方法
{
    // 获取当前用户的认证信息
    $user_auth = session('user_auth');
    $uid = $user_auth['uid'];

    // 从请求中获取任务ID和状态
    $task_id = input('task_id', '', 'trim,strip_tags');
    $status = input('status', '', 'trim,strip_tags');
    $username = $user_auth['username'];

    // 检查任务ID是否为空
    if (empty($task_id)) {
        return returnAjax(2, '请先选中指定任务');
    }

    switch ($status) {
        case 1:
            // 检查用户是否有足够的可用机器人数量来启动任务
            if ($this->verify_task_start($uid) == false) {
                // 更新任务状态为4（欠费）并返回错误信息
                $ret = Db::name('tel_config')->where('task_id', $task_id)->update(array('status' => 4, 'arrears_user' => $this->inArrears, 'update_time' => time()));
                return returnAjax(2, (($username == $this->inArrears) ? '当前' : '上级') . '用户(' . $this->inArrears . ')已欠费', ['arrears_user' => $this->inArrears]);
            }

            // 检查ASR是否欠费
            if ($this->verify_task_asr_start($uid, $task_id) == false) {
                // 更新任务状态为4（欠费）并返回错误信息
                $ret = Db::name('tel_config')->where('task_id', $task_id)->update(array('status' => 4, 'arrears_user' => 'ASR_ADMIN', 'update_time' => time()));
                return returnAjax(2, '您使用的ASR为系统自带现已经欠费，请通知admin充值或选择其他ASR。');
            }

            // 获取任务的话术ID
            $scenarios_id = Db::name('tel_config')->where('task_id', $task_id)->value('scenarios_id');

            // 创建Scenarios对象，检查话术是否正常
            $scenarios = new Scenarios();
            $arr_check = $scenarios->scenarios_check_by_xiafa($scenarios_id);

            if ($arr_check[0] == false) {
                return returnAjax(2, '当前话术存在异常，请修复后再重新启动任务');
            }

            // 获取当前日期和时间
            $current_date_time = date('Y-m-d');
            $current_time = date('H:i:s');
            $result = 0;

            // 检查任务的日期和时间是否匹配
            $is_exist = Db::name('auto_task_date')->where('task_id', $task_id)->count();

            if ($is_exist) {
                $count = Db::name('auto_task_date')
                    ->where([
                        'start_date' => ['<=', $current_date_time],
                        'end_date' => ['>=', $current_date_time],
                        'task_id' => $task_id
                    ])
                    ->count('id');

                if (!empty($count)) {
                    $count = Db::name('auto_task_time')
                        ->where([
                            'start_time' => ['<=', $current_time],
                            'end_time' => ['>=', $current_time],
                            'task_id' => $task_id
                        ])
                        ->count('id');

                    if (!empty($count)) {
                        $result = 1;
                    }
                }
            } else {
                $result = 1;
            }

            if ($result === 0) {
                return returnAjax(2, '当前时间不在任务时间内，请修改任务时间');
            } else {
                // 获取用户可用机器人数量、已运行机器人数量和任务机器人数量
                $robot_count = Db::name('admin')->where('id', $uid)->value('usable_robot_cnt');
                $run_robot_count = Db::name('tel_config')->where(['member_id' => $uid, 'status' => 1])->sum('robot_cnt');
                $task_robot_count = Db::name('tel_config')->where('task_id', $task_id)->value('robot_cnt');

                // 检查可用机器人数量是否足够启动任务
                if (($robot_count - $run_robot_count) < $task_robot_count) {
                    return returnAjax(2, '当前可用机器人数量不足以开启当前任务');
                }

                // 获取任务数据
                $task_data = Db::name('tel_config')->where(['id' => $task_id])->field('robot_cnt, fs_num, call_phone_group_id')->find();
                $fs_num = $task_data['fs_num'];
                $TaskData = new TaskData();

                // 获取最小的线路ID
                $line_id_min = $TaskData->getMinLineId($task_data['call_phone_group_id']);

                if (!$line_id_min) {
                    return returnAjax(2, '无法找到线路信息，请联系管理人员。');
                }

                // 连接FS数据库
                $fs_db = Db::connect(config('db_configs.fs' . $fs_num));

                // 查询正在运行的机器人数量
                $robot_cont = $fs_db->table('autodialer_task')->where('start', 1)->sum('maximumcall');
                $max_workload = config('max_workload');

                // 检查工作负载是否超出最大限制
                if ($max_workload < $robot_cont + $task_data['robot_cnt']) {
                    if ($max_workload - $robot_cont < 0) {
                        return returnAjax(2, '线路被占用,请稍后在开启任务，或联系管理员');
                    }

                    // 更新任务的机器人数量以适应最大工作负载
                    $is_Ok = Db::name('tel_config')->where('task_id', $task_id)->update([
                        'robot_cnt' => ($max_workload - $robot_cont),
                        'update_time' => time()
                    ]);

                    $fs_db->table('autodialer_task')->where('uuid', $task_id)->update([
                        'maximumcall' => ($max_workload - $robot_cont),
                        'alter_datetime' => date('Y-m-d H:i:s')
                    ]);

                    if (!$is_Ok) {
                        return returnAjax(2, '线路被占用,请稍后在开启任务，或联系管理员');
                    }
                }

                if (empty($result)) {
                    return returnAjax(2, '创建任务失败', 'insert_task_data_to_fs_server');
                }

                // 更新任务状态为1（已开启）
                $update_result = Db::name('tel_config')
                    ->where('task_id', $task_id)
                    ->update([
                        'update_time' => time(),
                        'status' => 1
                    ]);

                $fs_update_result = Db::connect('db_configs.fs' . $fs_num)
                    ->table('autodialer_task')
                    ->where('uuid', $task_id)
                    ->update([
                        'start' => 1,
                        'alter_datetime' => date('Y-m-d H:i:s'),
                    ]);

                if (!empty($update_result) && !empty($fs_update_result)) {
                    return returnAjax(0, '成功');
                } else {
                    return returnAjax(2, '任务已开启');
                }
            }
            break;


case 2:
    // 更新任务状态为2（已暂停）
    $update_result = Db::name('tel_config')
        ->where('task_id', $task_id)
        ->update([
            'update_time' => time(),
            'status' => 2,
            'is_auto' => 0
        ]);

    // 获取任务的FS编号
    $fs_num = Db::name('tel_config')->where(['id' => $task_id])->value('fs_num');

    // 更新FS中任务的状态为2（已暂停）
    if (!empty($fs_num)) {
        $fs_update_result = Db::connect('db_configs.fs' . $fs_num)
            ->table('autodialer_task')
            ->where('uuid', $task_id)
            ->update([
                'start' => 2,
                'alter_datetime' => date('Y-m-d H:i:s')
            ]);
    } else {
        $fs_update_result = 1;
    }

    // 删除任务的日期和时间设置
    Db::name('auto_task_date')->where('task_id', $task_id)->delete();
    Db::name('auto_task_time')->where('task_id', $task_id)->delete();

    // 检查更新结果并返回响应
    if (!empty($update_result) && !empty($fs_update_result)) {
        return returnAjax(0, '成功');
    } else {
        return returnAjax(2, '任务已暂停');
    }
    break;

case -1:
    // 获取当前任务的状态
    $current_status = Db::name('tel_config')->where('task_id', $task_id)->value('status');

    if ($current_status == 1) {
        // 如果任务正在进行中，无法被删除，返回错误信息
        return returnAjax(2, '任务正在进行中，无法被删除');
    } else {
        // 更新任务状态为-1（已删除）
        $update_result = Db::name('tel_config')
            ->where('task_id', $task_id)
            ->update([
                'update_time' => time(),
                'status' => -1
            ]);

        // 删除任务相关的统计数据
        $this->delIntentionalTaskCount($uid, $task_id);

        // 获取任务的FS编号
        $fs_num = Db::name('tel_config')->where(['id' => $task_id])->value('fs_num');

        // 更新FS中任务的状态为2（已暂停）
        if (!empty($fs_num) || $fs_num > 0) {
            $fs_update_result = Db::connect('db_configs.fs' . $fs_num)
                ->table('autodialer_task')
                ->where('uuid', $task_id)
                ->update([
                    'start' => 2,
                    'alter_datetime' => date('Y-m-d H:i:s')
                ]);
        }

        if (!empty($update_result) && !empty($fs_update_result)) {
            // 如果更新任务状态和FS状态成功，返回成功信息
            return returnAjax(0, '成功');
        } else {
            // 否则返回任务已删除的错误信息
            return returnAjax(2, '任务已删除');
        }
    }
    break;
}
}
public function delIntentionalTaskCount($uid,$task_id){
if($uid &&$task_id){
$now_time = strtotime(date('Ymd'));
$redis = RedisConnect::get_redis_connect();
$incr_key_task_intentional_customers = "incr_owner_".$uid."_".$task_id."_".$now_time."_task_intentional_customers";
$member_count = Db::name('member')->where(['task'=>$task_id])->count(1);
$task_intention_customers = $redis->get($incr_key_task_intentional_customers);
if(!$task_intention_customers ||$task_intention_customers <0){
$task_intention_customers = 0;
}
if($task_intention_customers >0){
$intentional_customers_redis_key = "incr_owner_".$uid."_".$now_time."_intentional_customers";
$redis->decrby($intentional_customers_redis_key,$task_intention_customers);
}
$create_time = Db::name('tel_config')->where(['task_id'=>$task_id])->value('create_time');
if($create_time &&$create_time >= strtotime(date('Ymd',time()))){
$incr_key_all_count = "incr_owner_".$uid."_".$now_time."_all_count";
$redis->decrby($incr_key_all_count,$member_count);
}
}
}
public function is_task_demand() //一旦启动，最先开始第一次进入
{
    // 从请求中获取任务ID和状态
    $task_id = input('task_id', '', 'trim,strip_tags');
    $status = input('status', '', 'trim,strip_tags');

    if ($status == 1) {
        // 查询数据库，获取任务配置信息
        $task = Db::name('tel_config')->where('task_id', $task_id)->find();
        
        // 获取Redis连接
        $redis = RedisConnect::get_redis_connect();
        $redis_key = 'task_id_fs_num_' . $task_id;
        $redis_fs_num = $redis->get($redis_key);

        // 如果任务配置中的fs_num为空，并且Redis中也没有值
        if (empty($task['fs_num']) && empty($redis_fs_num)) {
            // 创建一个TaskData对象
            $TaskData = new TaskData();

            // 获取最小运行机器人数量的FS服务器数据
            $fs_server_data = $TaskData->get_min_run_robot_count_fs_server($task_id);

            // 如果原始fs_num不为空，使用原始fs_num，否则使用fs_num和fs_db
            if (!empty($fs_server_data['original_fs_num'])) {
                $fs_num = $fs_server_data['original_fs_num'];
            } else {
                $fs_num = $fs_server_data['fs_num'];
                $fs_db = $fs_server_data['db'];

                // 将任务数据插入到FS服务器
                $result = $TaskData->insert_task_data_to_fs_server($task_id, $fs_server_data);
            }
        } elseif (!empty($redis_fs_num)) {
            $fs_num = $redis_fs_num;
        } else {
            $fs_num = $task['fs_num'];
        }

        // 根据fs_num获取FS数据库配置
        $fs_db_config = config('db_configs.fs' . $fs_num);

        // 连接FS数据库
        $fs_db = Db::connect($fs_db_config);

        // 查询Web平台的号码数量
        $web_number_count = Db::name('member')->where('task', $task_id)->count('uid');

        // 查询FS服务器的号码数量
        $fs_number_count = $fs_db->table('autodialer_number_' . $task_id)->count('1');

        // 如果Web平台的号码数量大于FS服务器的号码数量
        if ($web_number_count > $fs_number_count) {
            $fs_numbers = $fs_db->table('autodialer_number_' . $task_id)->column('number');
            $max_count = $web_number_count - $fs_number_count;
            $limit = 5000;
            $max_page = ceil($max_count / $limit);

            // 分批插入号码数据到FS服务器
            for ($i = 1; $i <= $max_page; $i++) {
                $web_numbers = Db::name('member')
                    ->where('task', $task_id)
                    ->where('mobile', 'not in', $fs_numbers)
                    ->page($i, $limit)
                    ->field('mobile as number')
                    ->select();

                $fs_db->table('autodialer_number_' . $task_id)->insertAll($web_numbers);
            }
        }

        // 创建RedisApiData对象
        $RedisApiData = new RedisApiData();

        // 执行任务预处理
        $RedisApiData->task_preprocessing($task_id);

        // 查询FS服务器中正在运行的机器人数量
        $run_robot_count = $fs_db->table('autodialer_task')->where('start', 1)->sum('maximumcall');

        // 获取最大工作负载配置
        $max_workload = config('max_workload');
        $res = [];

        // 如果可用工作负载大于等于任务中机器人数量和已运行机器人数量之差
        if ($max_workload - ($run_robot_count + $task['robot_cnt']) >= 0) {
            return returnAjax(0);
        } else {
            // 计算溢出数量和可用数量
            $overflow = 0 - ($max_workload - ($run_robot_count + $task['robot_cnt']));
            $data = [];
            $data['overflow'] = $overflow;
            $data['available'] = $max_workload - $run_robot_count;

            return returnAjax(1, '', $data);
        }
    } else {
        return returnAjax(0);
    }
}

public function addPlan(){
if (IS_POST) {
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$call_type = input('call_type','0','trim,strip_tags');
$robot_cnt = input('robot_cnt/d','','trim,strip_tags');
if($user_auth['role'] == '销售人员'){
$uid_where['member_id'] = array('eq',$uid);
$Db_tel_line = Db::name('tel_line');
$phoneId = $Db_tel_line
->where($uid_where)
->order('id','asc')
->value('id');
}else{
$phoneId = input('phone_id','','trim,strip_tags');
}
$memberInfo = Db::name('admin')
->field('usable_robot_cnt,asr_type')
->where("id",$uid)
->find();
$totalRobotCnt =  Db::name('tel_config')->where('member_id',$uid)->where('status','1')->sum('robot_cnt');
$totalRobotCnt = $robot_cnt+(int)$totalRobotCnt;
if ($totalRobotCnt >$memberInfo['usable_robot_cnt']){
return returnAjax(1,'超出购买机器人数量，请联系销售人员');
}
if ($memberInfo['asr_type'] >0){
$tilist = Db::name('tel_interface')->where('owner',$uid)->find();
if(!$tilist){
return returnAjax(1,'您没有接口，请到接口配置表里面添加数据。');
}
}
if ($call_type){
$sim = Db::name('tel_line')
->field('call_prefix,originate_variables,member_id,phone,inter_ip,dial_format')
->where("id",$phoneId)->find();
if (!$sim){
return returnAjax(1,'线路不存在！');
}
}else{
$sim = Db::name('tel_sim')
->field('call_prefix,member_id,phone,device_id')
->where("id",$phoneId)->find();
$gatewayInfo = Db::name('tel_device')->field('dial_format')->where('id',$sim['device_id'])->find();
if ($gatewayInfo &&$gatewayInfo['dial_format']){
$sim['dial_format'] = $gatewayInfo['dial_format'];
}
else{
return returnAjax(1,'网关账号不可为空');
}
}
$status = input('startup','0','trim,strip_tags');
if($status == 1){
$AdminData = new AdminData();
if($AdminData->verify_member_open_task_condition($uid) === false){
return returnAjax(1,'当前用户或上级余额不足');
}
}
$timegroup = array();
$timegroup['name'] = uniqid();
$timegroup['domain'] = uniqid();
$timegroup['member_id'] = $uid;
$tgresult = $this->connect->table('autodialer_timegroup')->insertGetId($timegroup);
$TimeRange = array();
$TimeRange['onetime'] = input('onetime','','trim,strip_tags');
$TimeRange['twotime'] = input('twotime','','trim,strip_tags');
$TimeRange['threetime'] = input('threetime','','trim,strip_tags');
$TimeRange['fourtime'] = input('fourtime','','trim,strip_tags');
$SaveRange = array();
foreach ($TimeRange as $tkey =>$tvalue) {
$temp = array();
$temp['group_uuid'] = $tgresult;
$temp['member_id'] = $uid;
if($tkey == 'onetime'){
$temp['begin_datetime'] = "00:00:00";
$temp['end_datetime'] = $tvalue;
array_push($SaveRange,$temp);
}
if($tkey == 'threetime'){
$temp['begin_datetime'] = $TimeRange['twotime'];
$temp['end_datetime'] = $tvalue;
array_push($SaveRange,$temp);
}
if($tkey == 'fourtime'){
$temp['begin_datetime'] = $tvalue;
$temp['end_datetime'] = "23:59:59";
array_push($SaveRange,$temp);
}
}
$TRresult = $this->connect->table('autodialer_timerange')->insertAll($SaveRange);
$task_name = input('task_name','','trim,strip_tags');
$task = array();
$task['name'] =  $task_name;
$task['create_datetime'] = date("Y-m-d H:i:s",time());
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$task['disable_dial_timegroup'] = $tgresult;
$task['member_id'] = $uid;
$task['remark'] = input('remark','','trim,strip_tags');
$task['call_pause_second'] = 0 ;
$task['call_pause_second'] = $task['call_pause_second'] +$this->call_pause_second;
$task['call_notify_url'] = config('notify_url');
$task['start'] = $status;
$task['call_notify_type'] = 2;
$task['cache_number_count'] = 0;
$max_destination_extension = Db::name('tel_config')->field('destination_extension')->order('id desc')->find();
if ($max_destination_extension &&$max_destination_extension['destination_extension'] >0){
$destination_extension = ((int)$max_destination_extension['destination_extension'])+1;
}else{
$destination_extension =  config('destination_extension');
}
$task['destination_extension'] = $destination_extension;
if ($call_type){
$task['dial_format'] = $sim['dial_format'];
$task['_origination_caller_id_number'] = $sim['phone'];
$task['originate_variables'] = $sim['originate_variables'];
}else{
$task['dial_format'] = $sim['dial_format'];
$task['_origination_caller_id_number'] = $sim['call_prefix'];
}
if (config('start_da2')){
if (isset($task['originate_variables']) &&$task['originate_variables']){
$task['originate_variables'] = $task['originate_variables'].','.config('start_da2');
}else{
$task['originate_variables']  = config('start_da2');
}
}
$task['destination_dialplan'] = "XML";
$task['destination_context'] = "default";
$task['maximumcall'] = $robot_cnt;
$taskresult = $this->connect->table('autodialer_task')->insertGetId($task);
$week = array();
$zhou = array();
$week['Monday'] = input('Monday','','trim,strip_tags');
$week['Tuesday'] = input('Tuesday','','trim,strip_tags');
$week['Wednesday'] = input('Wednesday','','trim,strip_tags');
$week['Thursday'] = input('Thursday','','trim,strip_tags');
$week['Friday'] = input('Friday','','trim,strip_tags');
$week['Saturday'] = input('Saturday','','trim,strip_tags');
$week['Sunday'] = input('Sunday','','trim,strip_tags');
foreach ($week as $key =>$value) {
if($value == 0){
array_push($zhou,$key);
}
}
$weeklist = implode(",",$zhou);
$timertask = array();
$timertask['group_id'] = $tgresult;
$day1 = input('startdata');
$day2 = input('enddata');
$timertask['date_list'] = $this->cesday($day1,$day2);
$timertask['week_list'] = '';
$timertask["task_id"] = $taskresult;
$timerresult = $this->connect->table('autodialer_timer_task')->insertGetId($timertask);
$cdata = array();
$cdata['member_id'] = $uid;
$levelArr = input('level/a','','trim,strip_tags');
if ($levelArr){
$cdata['level'] =  implode(",",$levelArr);
}
$sale_ids_arr = input('sale_ids/a','','trim,strip_tags');
if ($sale_ids_arr){
$cdata['sale_ids'] =  implode(",",$sale_ids_arr);
}
$cdata["task_id"] = $taskresult;
$cdata["task_name"] = $task_name;
$cdata["scenarios_id"] = input('scenarios_id','','trim,strip_tags');
$cdata["call_type"] = $call_type;
$cdata["status"] = $status;
$cdata["destination_extension"] = $destination_extension;
$cdata["phone"] = $sim['phone'];
$cdata["call_prefix"] = $sim['call_prefix'];
$cdata['remarks'] =input('remarks','','trim,strip_tags');
$cdata['robot_cnt'] = $robot_cnt;
$cdata['create_time'] = time();
$cdata['call_phone_id'] = $phoneId;
$cfresult = Db::name('tel_config')->insertGetId($cdata);
if ($taskresult){
$backdata = array();
$backdata['url'] = Url("User/Plan/index");
$import_number_args = input('import_number_args/a','','trim,strip_tags');
$member_ids = input('member_ids','','trim,strip_tags');
if(!empty($import_number_args)){
$member_where = [];
$member_where['owner'] = $uid;
if(!empty($import_number_args['level']) &&count($import_number_args['level']) >0){
$member_where['level'] = ['in',explode(',',$import_number_args['level'])];
}
if(isset($import_number_args['status']) === true &&!empty($import_number_args['status']) &&count($import_number_args['status']) >0){
$member_where['status'] = ['in',explode(',',$import_number_args['status'])];
}
if(!empty($import_number_args['startNum']) &&!empty($import_number_args['endNum'])){
$member_where['duration'] = ['between',[$import_number_args['startNum'],$import_number_args['endNum']]];
}else if(!empty($import_number_args['startNum'])){
$member_where['duration'] = ['>=',$import_number_args['startNum']];
}else if(!empty($import_number_args['endNum'])){
$member_where['duration'] = ['<=',$import_number_args['endNum']];
}
if(!empty($import_number_args['task_id'])){
$member_where['task'] = $import_number_args['task_id'];
}
if(!empty($import_number_args['scenarios_id'])){
$member_where['scenarios_id'] = $import_number_args['scenarios_id'];
}
if(!empty($import_number_args['startDate']) &&!empty($import_number_args['endTime'])){
$member_where['last_dial_time'] = ["between time",[$import_number_args['startDate'],$import_number_args['endTime']]];
}else if(!empty($import_number_args['startDate'])){
$member_where['last_dial_time'] = ['>=',strtotime($import_number_args['startDate'])];
}else if(!empty($import_number_args['endTime'])){
$member_where['last_dial_time'] = ['<=',strtotime($import_number_args['endTime'])];
}
if(!empty($import_number_args['semantic_labels'])){
$member_where['semantic_label'] = ['in',explode(',',$import_number_args['semantic_labels'])];
}
if(!empty($import_number_args['flow_labels'])){
$member_where['flow_label'] = ['in',explode(',',$import_number_args['flow_labels'])];
}
if(!empty($import_number_args['knowledge_labels'])){
$member_where['knowledge_label'] = ['in',explode(',',$import_number_args['knowledge_labels'])];
}
if(!empty($import_number_args['call_times'])){
$member_where['call_times'] = ['in',explode(',',$import_number_args['call_times'])];
}
if(!empty($import_number_args['affirm_times'])){
$member_where['affirm_times'] = ['in',explode(',',$import_number_args['affirm_times'])];
}
if(!empty($import_number_args['negative_times'])){
$member_where['negative_times'] = ['in',explode(',',$import_number_args['negative_times'])];
}
if(!empty($import_number_args['neutral_times'])){
$member_where['neutral_times'] = $import_number_args['negative_times'];
}
if(!empty($import_number_args['effective_times'])){
$member_where['effective_times'] = $import_number_args['effective_times'];
}
$data = Db::name('member')
->field('mobile')
->group('mobile')
->where($member_where)
->select();
}elseif(!empty($member_ids)){
$data = Db::name('member')
->field('mobile')
->where('uid','in',explode(',',$member_ids))
->select();
}
\think\Log::record('号码导入结果-2');
if(!empty($data) &&is_array($data) === true &&count($data) >0){
$members = [];
$request = request();
$ip = $request->ip(0,true);
$data_count = count($data);
Log::info($data_count);
foreach($data as $key=>$value){
$members[$key]['owner'] = $uid;
$members[$key]['reg_time'] = time();
$members[$key]['mobile'] = $value['mobile'];
$members[$key]['salt'] = '';
$members[$key]['password'] = '';
$members[$key]['reg_ip'] = $ip;
$members[$key]['is_new'] = 1;
$members[$key]['task'] = $cdata["task_id"];
$members[$key]['status'] = 1;
$members[$key]['scenarios_id'] = $cdata["scenarios_id"];
if(count($members) === 1000 ||$data_count == ($key+1)){
$input_result = Db::name('member')
->insertAll($members);
Log::info(json_encode($input_result));
unset($members);
$members = [];
if(empty($input_result)){
\think\Log::record('写入失败');
}else{
\think\Log::record('写入成功');
}
}
}
\think\Log::record('号码导入结果');
}
return returnAjax(0,'添加成功',$backdata);
}else{
$backdata = array();
$backdata['url'] = Url("User/Plan/addPlan");
return returnAjax(1,'添加任务失败',$backdata);
}
}else{
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('scenarioslist',$scenarioslist);
$where = array();
if(!$super){
$where['pid'] = $uid;
}
$where['status'] = 1;
$adminlist = Db::name('admin')
->field('id,username')
->where($where)
->order('id asc')
->select();
$this->assign('adminlist',$adminlist);
$where = array();
if(!$super){
$where['a.member_id'] = $uid;
}
$where['a.status'] = 1;
$where['c.id'] = array('EXP','IS NULL');
$tsrlist = Db::name('tel_tsr')
->alias('a')
->field('a.id,a.phone')
->join('tel_config c','a.phone = c.phone','LEFT')
->where($where)
->select();
$this->assign('tsrlist',$tsrlist);
$this->assign("current",'新增');
return $this->fetch("addplan");
}
}
public function editPlan(){
if (IS_POST) {
$taskId=input('taskId','','trim,strip_tags');
if(empty($taskId)){
return returnAjax(1,'请选择要编辑的任务');
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$callType = input('call_type','0','trim,strip_tags');
if($user_auth['role'] == '销售人员'){
$uid_where['member_id'] = $uid;
$Db_tel_line = Db::name('tel_line');
$phoneId = $Db_tel_line
->where($uid_where)
->order('id','asc')
->value('id');
}else{
$phoneId = input('phone_id','','trim,strip_tags');
}
$task_name = input('task_name','','trim,strip_tags');
$robot_cnt = input('robot_cnt','','trim,strip_tags');
$configId = input('configId','','trim,strip_tags');
$memberInfo = Db::name('admin')
->field('usable_robot_cnt,asr_type')
->where("id",$uid)->find();
$totalRobotCnt =  Db::name('tel_config')
->where('member_id',$uid)
->where('id','<>',$configId)
->where('status','1')->sum('robot_cnt');
$totalRobotCnt = $robot_cnt+(int)$totalRobotCnt;
if ($totalRobotCnt >$memberInfo['usable_robot_cnt']){
return returnAjax(1,'超出购买机器人数量，请联系销售人员');
}
if ($memberInfo['asr_type'] >0){
$tilist = Db::name('tel_interface')->where('owner',$uid)->find();
if(!$tilist){
return returnAjax(1,'您没有接口，请到接口配置表里面添加数据。');
}
}
if ($callType){
$sim = Db::name('tel_line')
->field('call_prefix,originate_variables,member_id,phone,inter_ip,dial_format')
->where("id",$phoneId)->find();
if (!$sim){
return returnAjax(1,'线路不存在！');
}
}else{
$sim = Db::name('tel_sim')
->field('call_prefix,member_id,phone,device_id')
->where("id",$phoneId)->find();
$gatewayInfo = Db::name('tel_device')->field('dial_format')->where('id',$sim['device_id'])->find();
if ($gatewayInfo &&$gatewayInfo['dial_format']){
$sim['dial_format'] = $gatewayInfo['dial_format'];
}
else{
return returnAjax(1,'网关账号不可为空');
}
}
$status = input('startup','0','trim,strip_tags');
if($status == 1){
$AdminData = new AdminData();
if($AdminData->verify_member_open_task_condition($uid) === false){
return returnAjax(1,'当前用户或上级余额不足');
}
}
$groupId = input('groupId','','trim,strip_tags');
$morning = input('morning','','trim,strip_tags');
$afternoon = input('afternoon','','trim,strip_tags');
$evening = input('evening','','trim,strip_tags');
$TimeRange = array();
$TimeRange['onetime'] = input('onetime','','trim,strip_tags');
$TimeRange['twotime'] = input('twotime','','trim,strip_tags');
$TimeRange['threetime'] = input('threetime','','trim,strip_tags');
$TimeRange['fourtime'] = input('fourtime','','trim,strip_tags');
$one = array();
$one['begin_datetime'] = "00:00:00";
$one['end_datetime'] =$TimeRange['onetime'];
$TRresult = $this->connect->table('autodialer_timerange')->where('uuid',$morning)->update($one);
$two = array();
$two['begin_datetime'] = $TimeRange['twotime'];
$two['end_datetime'] = $TimeRange['threetime'];
$TRresult = $this->connect->table('autodialer_timerange')->where('uuid',$afternoon)->update($two);
$three = array();
$three['begin_datetime'] = $TimeRange['fourtime'];
$three['end_datetime'] = "23:59:59";
$TRresult = $this->connect->table('autodialer_timerange')->where('uuid',$evening)->update($three);
$timertask = array();
$day1 = input('startdata');
$day2 = input('enddata');
$timertask['date_list'] = $this->cesday($day1,$day2);
$excludeId = input('exclude','','trim,strip_tags');
$timerresult = $this->connect->table('autodialer_timer_task')->where('id',$excludeId)->update($timertask);
$task = array();
$task['name'] = $task_name;
$task['remark'] = input('remark','','trim,strip_tags');
$task['call_pause_second'] = input('frequency','','trim,strip_tags');
$task['call_pause_second'] = (int)$task['call_pause_second'] +$this->call_pause_second;
$task['start'] = $status;
$call_type = input('call_type','0','trim,strip_tags');
if ($call_type){
$task['dial_format'] = $sim['dial_format'];
$task['_origination_caller_id_number'] = $sim['phone'];
$task['originate_variables'] = $sim['originate_variables'];
}else{
$task['dial_format'] = $sim['dial_format'];
$task['_origination_caller_id_number'] = $sim['call_prefix'];
}
if (config('start_da2')){
if (isset($task['originate_variables']) &&$task['originate_variables']){
$task['originate_variables'] = $task['originate_variables'].','.config('start_da2');
}
else{
$task['originate_variables']  = config('start_da2');
}
}
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$task['maximumcall'] = $robot_cnt;
$taskresult = $this->connect->table('autodialer_task')->where('uuid',$taskId)->update($task);
$cdata = array();
$cdata["task_name"] = $task_name;
$levelArr = input('level/a','','trim,strip_tags');
if ($levelArr){
$cdata['level'] =  implode(",",$levelArr);
}
$sale_ids_arr = input('sale_ids/a','','trim,strip_tags');
if ($sale_ids_arr){
$cdata['sale_ids'] =  implode(",",$sale_ids_arr);
}
$cdata["scenarios_id"] = input('scenarios_id','','trim,strip_tags');
$bridge = input('bridge','','trim,strip_tags');
$tsr = Db::name('tel_tsr')
->field('phone')
->where("id",$bridge)->find();
$cdata["bridge"] = $tsr['phone'];
$cdata["call_type"] = $callType;
$cdata["status"] = $status;
$cdata["phone"] = $sim['phone'];
$cdata['remarks']=input('remarks','','trim,strip_tags');
$cdata["call_prefix"] = $sim['call_prefix'];
$cdata['robot_cnt'] = input('robot_cnt','','trim,strip_tags');
$cdata['update_time'] = time();
$cdata['call_phone_id'] = $phoneId;
$cfresult = Db::name('tel_config')->where('task_id',$taskId)->update($cdata);
$RedisApiData = new RedisApiData();
$RedisApiData->resetTaskMinSeatId($taskId);
if($cfresult >= 0||$timerresult >= 0 ||$taskresult >= 0 ||$TRresult >= 0){
$backdata = array();
$backdata['url'] = Url("User/Plan/index");
return returnAjax(0,'编辑成功',$backdata);
}else{
$backdata = array();
$backdata['url'] = Url("User/Plan/editPlan",['id'=>$taskId]);
return returnAjax(1,'编辑失败',$backdata);
}
}
else {
$id = input('id','','trim,strip_tags');
$slist = $this->connect->table('autodialer_task')
->field('uuid,name,call_pause_second,disable_dial_timegroup,destination_extension,start,remark')
->where('uuid',$id)->find();
$slist['call_pause_second'] = $slist['call_pause_second'] -$this->call_pause_second;
$cfresult = Db::name('tel_config')
->field('id,level,sale_ids,scenarios_id,destination_extension,phone,call_type,bridge')
->where('task_id',$id)->find();
$cfresult['sale_ids']= explode(",",$cfresult['sale_ids']);
$cfresult['level']= explode(",",$cfresult['level']);
$slist['config'] = $cfresult;
$TRresult = $this->connect->table('autodialer_timerange')
->where('group_uuid',$slist['disable_dial_timegroup'])
->order('uuid asc')
->select();
$onestart = strtotime($TRresult[0]["end_datetime"]);
$onestart = date("H:i:s",($onestart+60));
$oneend = strtotime($TRresult[1]["begin_datetime"]);
$oneend = date("H:i:s",($oneend -60));
$towstart = strtotime($TRresult[1]["end_datetime"]);
$twostarts = date("H:i:s",($towstart +60));
$towend = strtotime($TRresult[2]["begin_datetime"]);
$twoend = date("H:i:s",($towend -60));
$TRresult[0]["begin_datetime"] = $onestart;
$TRresult[0]["end_datetime"] = $oneend;
$TRresult[1]["begin_datetime"] = $twostarts;
$TRresult[1]["end_datetime"] = $twoend;
$slist['timerange'] = $TRresult;
$timerresult = $this->connect->table('autodialer_timer_task')
->where('group_id',$slist['disable_dial_timegroup'])
->where('task_id',$id)
->find();
$timerresult['week_list'] = explode(",",$timerresult['week_list']);
$slist['timer'] = $timerresult;
$simwhere['phone'] = $cfresult['phone'];
$sim = Db::name('tel_sim')->field('id,phone')->where($simwhere)->find();
$slist['phone'] = $sim['id'];
$this->assign("list",$slist);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('scenarioslist',$scenarioslist);
$where = array();
if(!$super){
$where['pid'] = $uid;
}
$where['status'] = 1;
$adminlist = Db::name('admin')
->field('id,username')
->where($where)
->order('id asc')
->select();
$this->assign('adminlist',$adminlist);
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$where['status'] = 1;
$tsrlist = Db::name('tel_tsr')
->field('id,phone')
->where($where)
->select();
$this->assign('tsrlist',$tsrlist);
$this->assign("current",'编辑');
return $this->fetch('addplan');
}
}
public function newadd(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$where['status'] = 1;
$where['check_statu'] = ['<>',1];
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('scenarioslist',$scenarioslist);
$asr_list = Db::name('tel_interface')
->field('id,name')
->where('owner',$uid)
->select();
$this->assign('asr_list',$asr_list);
$line_datas = Db::name('tel_line_group')
->field('id,name')
->where(['user_id'=>$uid,'status'=>1])
->select();
$this->assign('line_datas',$line_datas);
$default_line_id = Db::name('admin')
->where('id',$uid)
->value('default_line_id');
$this->assign('default_line_id',$default_line_id);
$yunying_id = $this->get_operator_id($uid);
$wx_config =Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$wx_push_users = Db::name('wx_push_users')
->field('id,name')
->where([
'member_id'=>$uid,
'wx_config_id'=>$wx_config['id'],
])
->select();
$this->assign('wx_push_users',$wx_push_users);
$crm_push_users = Db::name('admin')
->field('id,username')
->where([
'pid'=>$uid,
'role_id'=>20,
])
->select();
$this->assign('crm_push_users',$crm_push_users);
$sms_where = array();
$sms_where['st.owner'] = $uid;
$sms_where['st.status'] = 3;
$sms_template = Db::name('sms_template')
->alias('st')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->field('st.id,st.name,ss.name as sign_name,st.content')
->where($sms_where)
->select();
$this->assign('sms_template',$sms_template);
$task_temp = DB::name('tel_tasks_templates')->where('member_id',$uid)->order('id','desc')->column('template','id');
$this->assign('task_temp',$task_temp);
return $this->fetch("newadd");
}
public function get_usable_robot_count_api(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$usable_robot_count = Db::name('admin')
->where('id',$uid)
->value('usable_robot_cnt');
$run_robot_count = Db::name('tel_config')
->where([
'member_id'=>$uid,
'status'=>1
])
->sum('robot_cnt');
$usable_robot_count = $usable_robot_count -$run_robot_count;
return returnAjax(0,'成功',$usable_robot_count);
}
public function bindCallNum(){
$type = input('type','0','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
if ($type == 0){
$where = array();
if(!$super){
$where['a.member_id'] = $uid;
}
$where['a.status'] = 1;
$simlist = Db::name('tel_sim')
->alias('a')
->field('a.id,a.phone')
->where($where)
->select();
}else{
$where = array();
$where['status'] = 1;
$where['user_id'] = [['=',0],['=',$uid],'OR'];
$simlist = Db::name('tel_line_group')
->field('id,name as phone, line_group_pid')
->where($where)
->select();
$LinesData = new LinesData();
foreach($simlist as $key=>$value){
$simlist[$key]['price'] = $LinesData->get_sales_price($value['id']);
}
}
return returnAjax(0,'',$simlist);
}
public function setstatus(){
$sId = input('pId','','trim,strip_tags');
if(empty($sId) ||$sId==0){
return returnAjax(1,'请选择任务再来开启');
}
$status = input('status','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$taskInfo = Db::name('tel_config')->where('task_id',$sId)->find();
if(empty($taskInfo)){
return returnAjax(1,'当前任务不存在');
}
if ($status == 1){
$AdminData = new AdminData();
if($AdminData->verify_member_open_task_condition($uid) === false){
return returnAjax(1,'当前用户或上级余额不足');
}
if ($taskInfo['status'] == 0 ||$taskInfo['status'] == 2){
$adlist = Db::name('admin')->field("robot_cnt")->where('id',$uid)->find();
$sum = array();
$sum['member_id'] = $uid;
$sum['status'] = ['=',1];
$rnum = Db::name('tel_config')->where($sum)->sum('robot_cnt');
$rnum = $adlist['robot_cnt'] -$rnum;
if($rnum <= 0){
return returnAjax(1,'可用机器人不足');
}
}else{
return returnAjax(1,'该任务不可开启请联系管理员');
}
}
$data = array();
$data['start'] = $status;
$data["alter_datetime"] = date("Y-m-d H:i:s",time());
Db::startTrans();
try{
$ret = $this->connect->table('autodialer_task')->where('uuid',$sId)->update($data);
$res = Db::name('tel_config')->where('task_id',$sId)->update(array('status'=>$status));
}catch(\Exception $e){
Db::rollback();
return returnAjax(1,'状态修改失败，请联系管理员');
}
if($ret &&$res){
Db::commit();
return returnAjax(0,'成功');
}else{
Db::rollback();
return returnAjax(1,'任务修改失败');
}
}
public function stopAll(){
$arr=[];
$idArr = input('idList/a','','trim,strip_tags');
foreach($idArr as $key=>$value){
$num = Db::name('tel_config')->where(['status'=>1,'task_id'=>$value])->count('*');
if($num>0){
$data=array();
$data['start'] = 2;
$data["alter_datetime"] = date("Y-m-d H:i:s",time());
$fs_num = Db::name('tel_config')->where(['status'=>1,'task_id'=>$value])->value('fs_num');
$ret = Db::connect('db_configs.fs'.$fs_num)->table('autodialer_task')->where(['uuid'=>$value,'start'=>1])->update($data);
if($ret){
$ret = Db::name('tel_config')->where(['status'=>1,'task_id'=>$value])->update(['status'=>2,'update_time'=>time()]);
$arr[]=$ret;
}
}
}
if(!empty($arr)){
return returnAjax(0,'成功');
}else{
return returnAjax(1,'任务都已经停止了！无需再暂停！',"失败");
}
}
public function project(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
if($super){
$adminlist = Db::name('admin')->field('id,username')->order('id desc')->select();
$this->assign('adminlist',$adminlist);
$this->assign('isAdmin',1);
}
$where = array();
if(!$super){
$where['member_id'] = (int)$uid;
}
$gatewayUser = config('gateway_user');
if ($gatewayUser){
$where['dial_format'] = $gatewayUser;
}
$tasklist = $this->connect->table('autodialer_task')->where($where)->field('uuid,name,disable_dial_timegroup')->order('uuid desc')->select();
foreach ($tasklist as $k=>$v){
if($v['disable_dial_timegroup']){
unset($tasklist[$k]);
}
}
$this->assign('tasklist',$tasklist);
$where = array();
if(!$super){
$where['member_id'] = (int)$uid;
}
$list = $this->connect->table('autodialer_timegroup')->order('uuid desc')->where($where)->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$taskname = $this->connect->table('autodialer_task')->field('uuid,name,start')->where('disable_dial_timegroup',$v['uuid'])->find();
$list['data'][$k]["taskname"] = $taskname["name"];
$admin = Db::name('admin')->field('username')->where('id',$v['member_id'])->find();
$list['data'][$k]["memberName"] = $admin["username"];
if(!$taskname['start']){
unset($list['data'][$k]);
}
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function addProject(){
$data = array();
$data['name'] = input('name','','trim,strip_tags');
$data['domain'] = input('domain','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
if(!$super){
$data['member_id'] = $uid;
}else{
$data['member_id'] = input('member_id','','trim,strip_tags');
}
$result = $this->connect->table('autodialer_timegroup')->insertGetId($data);
if($result){
$task["disable_dial_timegroup"] = $result;
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$taskId = input('task','','trim,strip_tags');
$return = $this->connect->table('autodialer_task')->where('uuid',$taskId)->update($task);
}
if($result){
return returnAjax(0,'成功',$result);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function getPlanInfo(){
$id = input('id','','trim,strip_tags');
$slist = $this->connect->table('autodialer_timegroup')->where('uuid',$id)->find();
$taskname = $this->connect
->table('autodialer_task')
->field('uuid,name')
->where('disable_dial_timegroup',$slist['uuid'])
->find();
$slist["taskId"] = $taskname["uuid"];
$slist["taskName"] = $taskname["name"];
echo json_encode($slist,true);
}
public function editProject(){
$data = array();
$data['name'] = input('name','','trim,strip_tags');
$data['domain'] = input('domain','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
if(!$super){
$data['member_id'] = $uid;
}else{
$data['member_id'] = input('member_id','','trim,strip_tags');
}
$planId = input('planId','','trim,strip_tags');
$result = $this->connect->table('autodialer_timegroup')->where('uuid',$planId)->update($data);
$task["disable_dial_timegroup"] = null;
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$return = $this->connect->table('autodialer_task')->where('disable_dial_timegroup',$planId)->update($task);
$newtask["disable_dial_timegroup"] = $planId;
$newtask["alter_datetime"] = date("Y-m-d H:i:s",time());
$taskId = input('task','','trim,strip_tags');
$return = $this->connect->table('autodialer_task')->where('uuid',$taskId)->update($newtask);
if($result){
return returnAjax(0,'成功',$result);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function delProject(){
$ids= input('id/a','','trim,strip_tags');
$list = $this->connect->table('autodialer_timegroup')->where('uuid','in',$ids)->delete();
foreach ($ids as $k=>$v){
$this->connect->table('autodialer_timerange')->where('group_uuid',$v)->delete();
$task["disable_dial_timegroup"] = null;
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$return = $this->connect->table('autodialer_task')->where('disable_dial_timegroup',$v)->update($task);
}
if(!$list){
echo "删除失败。";
}
}
public function projectdetail(){
$id = input('id','','trim,strip_tags');
$this->assign('groupId',$id);
$where = array();
$where['group_uuid'] = $id;
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
if(!$super){
$where['member_id'] = $uid;
}
if($super){
$adminlist = Db::name('admin')->field('id,username')->order('id desc')->select();
$this->assign('adminlist',$adminlist);
$this->assign('isAdmin',1);
}
$list = $this->connect->table('autodialer_timerange')->where($where)->order('uuid desc')->paginate(10,false,array('query'=>$this->param));
$page = $list->render();
$list = $list->toArray();
foreach ($list['data'] as $k=>$v){
$groupname = $this->connect->table('autodialer_timegroup')->field('uuid,name')->where('uuid',$v['group_uuid'])->find();
$list['data'][$k]["groupname"] = $groupname["name"];
}
$this->assign('list',$list['data']);
$this->assign('page',$page);
return $this->fetch();
}
public function addProjectTime(){
$data = array();
$data['begin_datetime'] = input('startDate','','trim,strip_tags');
$data['end_datetime'] = input('endTime','','trim,strip_tags');
$data['group_uuid'] = input('groupId','','trim,strip_tags');
$groupname = $this->connect->table('autodialer_timegroup')->field('member_id')->where('uuid',$data['group_uuid'])->find();
$data['member_id'] = $groupname["member_id"];
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$return = $this->connect->table('autodialer_task')->where('disable_dial_timegroup',$data['group_uuid'])->update($task);
$result = $this->connect->table('autodialer_timerange')->insertGetId($data);
if($result){
return returnAjax(0,'成功',$result);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function getTimeInfo(){
$id = input('id','','trim,strip_tags');
$slist = $this->connect->table('autodialer_timerange')->where('uuid',$id)->find();
echo json_encode($slist,true);
}
public function editProjectTime(){
$data = array();
$data['begin_datetime'] = input('startDate','','trim,strip_tags');
$data['end_datetime'] = input('endTime','','trim,strip_tags');
$data['group_uuid'] = input('groupId','','trim,strip_tags');
$groupname = $this->connect->table('autodialer_timegroup')->field('member_id')->where('uuid',$data['group_uuid'])->find();
$data['member_id'] = $groupname["member_id"];
$task["alter_datetime"] = date("Y-m-d H:i:s",time());
$return = $this->connect->table('autodialer_task')->where('disable_dial_timegroup',$data['group_uuid'])->update($task);
$planId = input('planId','','trim,strip_tags');
$result = $this->connect->table('autodialer_timerange')->where('uuid',$planId)->update($data);
if($result){
return returnAjax(0,'成功',$result);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function delTime(){
$ids= input('id/a','','trim,strip_tags');
$list = $this->connect->table('autodialer_timerange')->where('uuid','in',$ids)->delete();
if(!$list){
echo "删除失败。";
}
}
function outexcel_degree(){
$chaos_num = input('chaos_num','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$phone_count = $RedisConnect->get($key);
$complete_key = 'task_'.$chaos_num .'_complete_count';
$phone_complete = $RedisConnect->get($complete_key);
$data = [];
$data['count'] = $phone_count;
$data['complete'] = $phone_complete;
if($phone_count){
$Percentage = ($phone_complete/$phone_count) * 100;
if($Percentage >1){
$data['percentage'] = number_format(($Percentage -0.01),2) ;
}else{
$data['percentage'] = number_format($Percentage,2);
}
}else{
$data['percentage'] = 0;
}
return returnAjax(1,'',$data);
}
function exportExcel(){
$taskId = input('taskId','','trim,strip_tags');
if(empty($taskId)){
return returnAjax(1,'请选择一个任务再导出');
}
$daochu_type = input('daochu_type','','trim,strip_tags');
$cwhere = array();
$columName = ['主叫机号码','客户号码','客户姓名','呼叫结果','客户等级','通话时长','通话轮次','呼叫时间','分配状态','全程录音'];
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$ctype['owner'] = $uid;
$super = $user_auth["super"];
$cwhere["status"] = ['>',0];
if (!$super){
$cwhere["owner"] = $uid;
}
$id=input('post.id',0,'trim,strip_tags');
if( !empty($id) &&$id!=0 ){
$cwhere["uid"] = $id;
}
$data = input('data/a','','trim,strip_tags');
if(!empty($data)){
$cwhere["uid"] = ['in',$data];
}
if(!empty($taskId)){
$cwhere["task"] = $taskId;
}
$current_date = strtotime(date('Y-m-d'));
$mList = Db::name('member')
->field('originating_call,mobile,nickname,status,level,duration,call_rotation,last_dial_time,salesman,record_path')
->where($cwhere)
->order('uid asc')
->select();
foreach($mList as &$item){
if ($item['last_dial_time'] >0){
$item['last_dial_time'] = date('Y-m-d H:i:s',$item['last_dial_time']);
}
else{
$item['last_dial_time'] = "";
}
switch ($item['level']) {
case 6:
$item['level'] = 'A类(意向客户)';
break;
case 5:
$item['level'] = 'B类(一般意向)';
break;
case 4:
$item['level'] = 'C类(简单对话)';
break;
case 3:
$item['level'] = 'D类(无有效对话)';
break;
case 2:
$item['level'] = 'E类(有效未接通)';
break;
case 1:
$item['level'] = 'F类(无效号码)';
}
switch ($item['status']) {
case 3:
$item['status'] = '未接听挂断/关机/欠费';
break;
case 2:
$item['status'] = '已接通';
break;
default:
$item['status'] = '拨打排队中';
}
if($item['salesman'] >0){
$adminlist = Db::name('admin')->field('username,mobile')->where('id',$item['salesman'])->find();
if($adminlist['username']){
$item['salesman'] = $adminlist['username'];
}else{
$item['salesman'] = $adminlist['mobile'];
}
}else{
$item['salesman'] = "未分配";
}
if($item['last_dial_time'] >$current_date){
$item['record_path'] = config('record_path').$item['record_path'];
}else if($item['last_dial_time'] <$current_date){
$item['record_path'] = config('history_record_path').$item['record_path'];
}
$item['mobile'] = hide_phone_middle($item['mobile']);
}
$list = $mList;
$chaos_num = input('chaos_num','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$phone_count = count($list);
$RedisConnect->set($key,$phone_count);
$setTitle='Sheet1';
$fileName='文件名称';
if ( empty($columName) ||empty($list) ) {
return returnAjax(1,'内容不能为空!',"失败");
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(1,'列名跟数据的列不一致!',"失败");
}
if($daochu_type=='xlsx'){
$PHPExcel = new \PHPExcel();
$PHPSheet = $PHPExcel->getActiveSheet();
$PHPSheet->setTitle($setTitle);
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
for ($i=0;$i <count($list[0]);$i++) {
$PHPSheet->setCellValue("$letter[$i]1","$columName[$i]");
}
$complete_num = 0 ;
foreach ($list as $key =>$val) {
foreach (array_values($val) as $key2 =>$val2) {
$PHPSheet->setCellValue($letter[$key2].($key+2),$val2);
}
$complete_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$complete_num);
}
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xls';
$PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
if($list){
return returnAjax(0,'成功',config('res_url').ltrim($execlpath,"."));
}else{
return returnAjax(1,'失败!',"失败");
}
}else{
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$filename = rand_string(12,'',time()).'txtphone.txt';
$execlpath.=$filename;
$file = fopen($execlpath,"w");
for($i=0;$i<$phone_count;$i++){
fwrite($file,$list[$i]['mobile'] ."\r\n");
}
fclose($file);
if($list){
return returnAjax(0,'成功',config('res_url').'/api/file/download?file_path='.$execlpath);
}else{
return returnAjax(1,'失败!',"失败");
}
}
}
public  function exportExcelNotCall(){
$taskId = input('taskId','','trim,strip_tags');
$daochu_type = input('daochu_type','','trim,strip_tags');
if(empty($taskId)){
return returnAjax(1,'请选择一个任务再导出');
}
$cwhere = array();
$columName = ['客户姓名','客户号码'];
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$ctype['owner'] = $uid;
$super = $user_auth["super"];
$cwhere["status"] = 1;
if (!$super){
$cwhere["owner"] = $uid;
}
if(!empty($taskId)){
$cwhere["task"] = $taskId;
}
$mList = Db::name('member')
->field('nickname,mobile')
->where($cwhere)
->order('uid asc')
->select();
if(empty($mList)){
return returnAjax(1,'此任务没有未拨打的电话号码！',"失败");
}
$list = $mList;
$chaos_num = input('chaos_num','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$phone_count = count($list);
$RedisConnect->set($key,$phone_count);
$setTitle='Sheet1';
if ( empty($columName) ||empty($list) ) {
return returnAjax(1,'内容不能为空!',"失败");
}
if ( count($list[0]) != count($columName) ) {
return returnAjax(1,'列名跟数据的列不一致!',"失败");
}
if($daochu_type=='xlsx'){
$PHPExcel = new \PHPExcel();
$PHPSheet = $PHPExcel->getActiveSheet();
$PHPSheet->setTitle($setTitle);
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$xml_count =count($list[0]);
for ($i=0;$i <$xml_count ;$i++) {
$PHPSheet->setCellValue("$letter[$i]1","$columName[$i]");
}
$complete_num = 0 ;
foreach ($list as $key =>$val) {
foreach (array_values($val) as $key2 =>$val2) {
if($key2%1==0)$val2=hide_phone_middle($val2);
$PHPSheet->setCellValue($letter[$key2].($key+2),$val2);
}
$complete_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$complete_num);
}
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel,'Excel2007');
$PHPWriter->save($execlpath);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
if($list){
return returnAjax(0,'成功',config('res_url').ltrim($execlpath,"."));
}else{
return returnAjax(1,'失败!',"失败");
}
}else{
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$filename = rand_string(12,'',time()).'txtphone.txt';
$execlpath.=$filename;
$file = fopen($execlpath,"w");
for($i=0;$i<$phone_count;$i++){
fwrite($file,$list[$i]['mobile'] ."\r\n");
}
fclose($file);
if($list){
return returnAjax(0,'成功',config('res_url').'/api/file/download?file_path='.$execlpath);
}else{
return returnAjax(1,'失败!',"失败");
}
}
}
function taskgetConfig(){
$taskId = input('taskId','','trim,strip_tags');
if (!$taskId){
return returnAjax(0,'成功',array());
}
$where = array();
$where["task_id"] = $taskId;
$list = Db::name('tel_config')->where($where)->find();
$list['line_name'] = Db::name('tel_line')
->where('id',$list['call_phone_id'])
->value('name');
$scenarioslist = Db::name('tel_scenarios')->field("name")->where('id',$list["scenarios_id"])->find();
$list["scenarios"] = $scenarioslist['name'];
if (isset($list['level'])){
$leveltemp = explode(",",$list['level']);
foreach($leveltemp as &$item){
switch ($item) {
case 6:
$item = 'A类';
break;
case 5:
$item = 'B类';
break;
case 4:
$item = 'C类';
break;
case 3:
$item = 'D类';
break;
case 2:
$item = 'E类';
break;
default:
$item = 'F类';
}
}
}
else{
$leveltemp = array();
}
switch ($list['status']) {
case 0:
$list['statusstr'] = '暂停';
break;
case 1:
$list['statusstr'] = '开启';
break;
case 2:
$list['statusstr'] = '人工暂停';
break;
case 3:
$list['statusstr'] = '停止';
break;
case 4:
$list['statusstr'] = '线路欠费';
break;
default:
$list['statusstr'] = '软删除';
}
if($list['bridge'] == 0){
$list['bridgestr'] = '0';
}else{
$list['bridgestr'] = '1个';
}
$cwhere = array();
$cwhere["task"] = $taskId;
$cwhere["status"] = ['>',0];
$count = Db::name('member')->where($cwhere)->count(1);
$here = array();
$here["task"] =$taskId;
$here["status"] = ['>',1];
$Molecular = Db::name('member')->where($here)->count(1);
if($count >0 &&$Molecular >0){
$percent = round(($Molecular / $count) * 100,2);
}else{
$percent = 0;
}
$backdata = array();
$backdata["list"] = $list;
$backdata["result"] = $list["status"];
$backdata["level"] = implode(",",$leveltemp);
$backdata["Molecular"] = $Molecular;
$backdata["count"] = $count;
$backdata["percent"] = $percent;
if($list){
return returnAjax(0,'成功',$backdata);
}else{
return returnAjax(1,'error!',"失败");
}
}
public function editplanInfo(){
$id = input('id','','trim,strip_tags');
if(empty($id)){
return returnAjax(1,'请选择要编辑的任务');
}
$slist = $this->connect->table('autodialer_task')
->field('uuid,name,call_pause_second,disable_dial_timegroup,destination_extension,maximumcall,start,remark')
->where('uuid',$id)
->find();
$slist['call_pause_second'] = $slist['call_pause_second'] -$this->call_pause_second;
$cfresult = Db::name('tel_config')
->field('id,level,sale_ids,scenarios_id,destination_extension,phone,call_type,bridge,robot_cnt,call_phone_id,remarks')
->where('task_id',$id)->find();
$cfresult['sale_ids'] = explode(",",$cfresult['sale_ids']);
$cfresult['level'] = explode(",",$cfresult['level']);
$tsr = Db::name('tel_tsr')
->field('id')
->where("phone",$cfresult['bridge'])
->find();
$cfresult['bridge'] = $tsr['id'];
$slist['config'] = $cfresult;
$TRresult = $this->connect->table('autodialer_timerange')
->where('group_uuid',$slist['disable_dial_timegroup'])
->order('uuid asc')
->select();
if(count($TRresult)){
$onestart = strtotime($TRresult[0]["end_datetime"]);
$onestart = date("H:i:s",($onestart+60));
$oneend = strtotime($TRresult[1]["begin_datetime"]);
$oneend = date("H:i:s",($oneend -60));
$towstart = strtotime($TRresult[1]["end_datetime"]);
$twostarts = date("H:i:s",($towstart +60));
$towend = strtotime($TRresult[2]["begin_datetime"]);
$twoend = date("H:i:s",($towend -60));
$TRresult[0]["begin_datetime"] = $onestart;
$TRresult[0]["end_datetime"] = $oneend;
$TRresult[1]["begin_datetime"] = $twostarts;
$TRresult[1]["end_datetime"] = $twoend;
}
$slist['timerange'] = $TRresult;
$timerresult = $this->connect->table('autodialer_timer_task')
->where('group_id',$slist['disable_dial_timegroup'])
->where('task_id',$id)
->find();
$timerresult['date_list'] = explode(",",$timerresult['date_list']);
$key=count($timerresult['date_list'])-1;
$timerresult['begin']=$timerresult['date_list'][0];
$timerresult['end']=$timerresult['date_list'][$key];
$slist['timer'] = $timerresult;
$slist['phone'] = $cfresult['call_phone_id'];
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$where = array();
if(!$super){
$where['member_id'] = $uid;
}
$where['status'] = 1;
$scenarioslist = Db::name('tel_scenarios')
->where($where)
->field('id,name')
->order('id asc')
->select();
$where = array();
if(!$super){
$where['pid'] = $uid;
}
$where['status'] = 1;
$adminlist = Db::name('admin')
->field('id,username')
->where($where)
->order('id asc')
->select();
$backdata = array();
$backdata['list'] = $slist;
$usable_robot_cnt = Db::name('admin')
->where('id',$uid)
->value('usable_robot_cnt');
$run_robot_count = Db::name('tel_config')
->where([
'member_id'=>$uid,
'task_id'=>['<>',$id],
'status'=>1
])
->sum('robot_cnt');
$backdata['usable_robot_cnt'] = $usable_robot_cnt -$run_robot_count;
return returnAjax(0,'成功',$backdata);
}
public function task_statistics(){
return $this->fetch("task_statistics");
}
public function ajax_task_statistics(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$calltask = input('calltask','','trim,strip_tags');
$keyword = input('keyword','','trim,strip_tags');
$startDate = strtotime(input('startDate','','trim,strip_tags'));
$endTime = strtotime(input('endTime','','trim,strip_tags'));
$limit = input('limit','','trim,strip_tags');
if(!empty($limit)){
$Page_size = $limit;
}else{
$Page_size = 10;
}
$page = input('page','1','trim,strip_tags');
$where['status'] = array('neq',-1);
if(!empty($keyword)){
$where['task_name'] = array('like','%'.$keyword.'%');;
}
if($calltask !== ''){
$where['status'] = $calltask;
}
$where['member_id']=$uid;
$Db_tel_config = Db::name('tel_config');
$count =  $Db_tel_config->where($where)->count(1);
$page_count = ceil($count/$Page_size);
if($page >$page_count){
$page = $page_count -1;
}
$list = $Db_tel_config->where($where)->order('id desc')->page($page,$Page_size)->select();
$date = strtotime(date('Y-m-d'));
foreach($list as $key =>$vo){
if($vo['create_time'] <$date &&$vo['status'] == 3){
$list[$key]['url'] = url('user/callrecord/historical_records') .'?task_id='.$vo['task_id'];
}else{
$list[$key]['url'] = url('user/callrecord/current_record') .'?task_id='.$vo['task_id'];
}
$call_phone_group_name = Db::name('tel_line_group')->where('id',$vo['call_phone_group_id'])->value('name');
if(empty($call_phone_group_name)){
$call_phone_group_name='线路组被删除';
}
$list[$key]['call_phone_name']=$call_phone_group_name;
$list[$key]['break'] = getBreakByScenariosId($vo['scenarios_id']);
$list[$key]['sms'] = getSmsByStatus($vo['send_sms_status']);
$list[$key]['wechat'] = getSmsByStatus($vo['wx_push_status']);
$list[$key]['crm'] =  getSmsByStatus($vo['is_add_crm']);
$list[$key]['scenarios_name'] = getScenariosName($vo['scenarios_id']);
$list_ = $this->get_task_statistics($vo['task_id'],$vo['status']);
$list[$key]['number_total'] = $list_['number_total'];
$list[$key]['call_already'] = $list_['call_already'];
$list[$key]['call_okConnect'] = $list_['call_okConnect'];
$list[$key]['call_rate'] = $list_['call_rate'];
$list[$key]['call_duration'] = $list_['call_duration'];
$list[$key]['call_avg_duration'] = $list_['call_avg_duration'];
$list[$key]['last_dial_time'] = $list_['last_dial_time'];
$list[$key]['call_level'] = $list_['call_level'];
}
if(!empty($startDate) ||!empty($endTime)){
if(!empty($startDate) &&empty($endTime)){
$whereM['m.last_dial_time'] = ['>=',$startDate];
}
if(!empty($endTime) &&empty($startDate)){
$whereM['m.last_dial_time'] = ['<',$endTime];
}
if(!empty($startDate) &&!empty($endTime)){
$whereM['m.last_dial_time'] = [['>=',$startDate],['<',$endTime],'and'];
}
$whereM['c.status'] = array('neq',-1);
if(!empty($keyword)){
$whereM['c.task_name'] = array('like','%'.$keyword.'%');;
}
if($calltask !== ''){
$whereM['c.status'] = $calltask;
}
$whereM['c.member_id']=$uid;
$count  = Db::name('tel_config')
->alias('c')
->field('c.*,m.last_dial_time')
->join('member m','m.task = c.task_id')
->where($whereM)->group('id')->count('*');
$page_count = ceil($count/$Page_size);
if($page >$page_count){
$page = $page_count -1;
}
$listx = Db::name('tel_config')
->alias('c')
->field('c.*,m.last_dial_time')
->join('member m','m.task = c.task_id')
->where($whereM)->order('id desc')->group('id')->page($page,$Page_size)->select();
$date = strtotime(date('Y-m-d'));
$Db_member = Db::name('member');
foreach($listx as $key =>$vo){
if($vo['create_time'] <$date &&$vo['status'] == 3){
$listx[$key]['url'] = url('user/callrecord/historical_records') .'?task_id='.$vo['task_id'];
}else{
$listx[$key]['url'] = url('user/callrecord/current_record') .'?task_id='.$vo['task_id'];
}
$listx[$key]['call_phone_name']=getPhoneName($vo['call_phone_id']);
$listx[$key]['break']=getBreakByScenariosId($vo['scenarios_id']);
$listx[$key]['sms'] = getSmsByStatus($vo['send_sms_status']);
$listx[$key]['wechat'] = getSmsByStatus($vo['wx_push_status']);
$listx[$key]['crm'] =  getSmsByStatus($vo['is_add_crm']);
$listx[$key]['scenarios_name']=getScenariosName($vo['scenarios_id']);
$listx_ = $this->get_task_statistics($vo['task_id'],$vo['status']);
$listx[$key]['number_total'] = $listx_['number_total'];
$listx[$key]['call_already'] = $listx_['call_already'];
$listx[$key]['call_okConnect'] = $listx_['call_okConnect'];
$listx[$key]['call_rate'] = $listx_['call_rate'];
$listx[$key]['call_duration'] = $listx_['call_duration'];
$listx[$key]['call_avg_duration'] = $listx_['call_avg_duration'];
$listx[$key]['last_dial_time'] = $listx_['last_dial_time'];
$listx[$key]['call_level'] = $listx_['call_level'];
}
$datas = array();
$datas['total'] = $count;
$datas['Nowpage'] = $page;
$datas['list'] = $listx;
$datas['page'] = $page_count;
$datas['limit']=$Page_size;
return returnAjax(0,'获取数据成功',$datas);
}
\think\Log::record('任务统计');
$datas = array();
$datas['total'] = $count;
$datas['Nowpage'] = $page;
$datas['list'] = $list;
$datas['page'] = $page_count;
$datas['limit']=$Page_size;
return returnAjax(0,'获取数据成功',$datas);
}
public function get_task_statistics($task_id,$status){
if(!$task_id){
return false;
}
$Db_statistics = Db::name('task_statistics');
$Db_member = Db::name('member');
if($status == 3){
$task_statistics = $Db_statistics->where(['task_id'=>$task_id])->find();
if($task_statistics){
$list['number_total'] = $task_statistics['task_all_num'];
$list['call_already'] = $task_statistics['task_call_num'];
$list['call_okConnect'] = $task_statistics['task_connect_num'];
if($list['call_already'] == 0){
$list['call_rate'] = sprintf("%.2f",0);
}else{
$list['call_rate'] = sprintf("%.2f",$list['call_okConnect'] / $list['call_already']*100);
}
$list['call_duration'] = $task_statistics['task_call_duration'] ??0;
$list['call_avg_duration'] = $task_statistics['task_average_duration'] ??0;
$list['last_dial_time'] = $task_statistics['task_last_dial_time'];
$list['call_level'] = $task_statistics['task_level_a'] +$task_statistics['task_level_b'];
return $list;
}
}
$task_where['task'] = $task_id;
$list['number_total'] = $Db_member->where($task_where)->count();
$task_where2['task'] = $task_id;
$task_where2['status'] = array('egt',2);
$list['call_already']= $Db_member->where($task_where2)->count();
$task_where3['task'] = $task_id;
$task_where3['status'] = array('eq',2);
$list['call_okConnect'] = $Db_member->where($task_where3)->count();
if($list['call_already'] == 0){
$list['call_rate'] = sprintf("%.2f",0);
}else{
$list['call_rate'] = sprintf("%.2f",$list['call_okConnect'] / $list['call_already']*100);
}
$list['call_duration'] = $Db_member->where($task_where)->sum('ceil(duration/60)');
if(!$list['call_duration']){
$list['call_duration'] = 0;
}
$list['call_avg_duration'] = $Db_member->where($task_where)->sum('duration');
if(!$list['call_avg_duration'] ||!$list['call_okConnect']){
$list['call_avg_duration'] = 0;
}else{
$list['call_avg_duration'] = round($list['call_avg_duration'] / $list['call_okConnect'],2);
}
$last_dial_time = $Db_member->where($task_where)->field('last_dial_time')->order('last_dial_time desc')->limit(1)->find();
if(empty($last_dial_time['last_dial_time'])){
$list['last_dial_time'] = 0;
}else{
$list['last_dial_time'] = $last_dial_time['last_dial_time'];
}
$level_where['task'] = array('eq',$task_id);
$level_where['level'] = array('in',array(5,6));
$list['call_level']  = $Db_member->where( $level_where)->count(1);
return $list;
}
public function soft_delet(){
$id = input('id',0,'trim,strip_tags');
if( empty($id) ||$id==0){
return returnAjax(1,'id不能为空');
}
$Db_tel_config = Db::name('tel_config');
$where['id'] = $id;
$data['status'] = -1;
$status = $Db_tel_config->where($where)->value('status');
if($status==1){
return returnAjax(0,'进行中的任务不能删除');
}
$res =  $Db_tel_config ->where($where)->update($data);
if($res>0){
$Db_autodialer_task = $this->connect->table('autodialer_task');
$taskId = $Db_tel_config->where($where)->value('task_id');
$uuid_where['uuid'] = array('eq',$taskId);
$Db_autodialer_task->where($uuid_where)->update(['start'=>-1]);
return returnAjax(1,'删除成功',$res);
}else{
return returnAjax(0,'删除失败',$res);
}
}
public function task_batch_delet(){
$ids = input('vals','','trim,strip_tags');
$index = explode(',',$ids);
$Db_tel_config = Db::name('tel_config');
$where['id'] = ['in',$index];
$data['status'] = -1;
$configs = $Db_tel_config->where($where)->select();
$xr=[];
foreach($configs as $key=>$value){
if($value['status']!=1){
$res = $Db_tel_config->where('id',$value['id'])->update($data);
if($res){
$Db_autodialer_task = $this->connect->table('autodialer_task');
$uuid_where['uuid'] =$value['task_id'];
$Db_autodialer_task->where($uuid_where)->update(['start'=>-1]);
}else{
return returnAjax(0,'删除失败',$res);
}
}else{
$xrr[]=1;
}
}
if(!empty($xrr)){
return returnAjax(0,'删除的任务中有正在进行中的任务，无法删除掉正在进行中的任务！');
}
return returnAjax(1,'删除成功',$res);
}
public function del_all_state(){
$Db_tel_config = Db::name('tel_config');
$Db_member = Db::name('member');
$Db_autodialer_task =$this->connect->table('autodialer_task');
$where['status'] = array('eq',-1);
$taskId = $Db_tel_config->where($where)->column('task_id');
$task_where['task'] = ['in',$taskId];
$Db_member->where($task_where)->delete();
$uuid_where['uuid'] = ['in',$taskId];
$Db_autodialer_task->where($uuid_where)->delete();
$res = $Db_tel_config->where($where)->delete();
if($res){
return returnAjax(1,'删除成功',$res);
}else{
return returnAjax(0,'删除失败',$res);
}
}
public  function  delMember(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$id = input('post.id',0,'trim,strip_tags');
if(!empty($_POST['data'])){
$arr=$_POST['data'];
$where['owner']=$uid;
$where['uid']=['in',$arr];
$member = Db::name('member')->where($where)->delete();
if($member){
return  returnAjax(0,'删除成功');
}else{
return  returnAjax(1,'删除失败');
}
}
if(empty($id) ||$id==0){
return  returnAjax(1,'id不能为空');
}
$where['uid']=$id;
$where['owner']=$uid;
$member = Db::name('member')->where($where)->delete();
if($member){
return  returnAjax(0,'删除成功');
}else{
return  returnAjax(1,'删除失败');
}
}
public function blacklist(){
return $this->fetch("blacklist");
}
public function ajax_blacklist(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$name = input('username','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$unit = input('unit','','trim,strip_tags');
$duties = input('duties','','trim,strip_tags');
$Db_blacklist = Db::name('blacklist_phones');
$where['member_id'] =array('eq',$uid);
if(!empty($name)){
$where['name'] = array('like','%'.$name.'%');
}
if(!empty($phone)){
$where['phone'] = array('eq',$phone);
}
if(!empty($unit)){
$where['unit'] = array('like','%'.$unit.'%');
}
if(!empty($duties)){
$where['duties'] = array('like','%'.$duties.'%');
}
$list = $Db_blacklist->where($where)->page($page,$limit)
->order('update_time','desc')
->select();
foreach ($list as $key =>$value) {
$list[$key]['sequence'] = ($page-1)*10+($key+1);
if(empty($value['create_time'])){
$list[$key]['create_time'] = "暂无显示";
}else {
$list[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
}
if(empty($value['update_time'])){
$list[$key]['update_time'] = "暂无显示";
}else {
$list[$key]['update_time'] = date('Y-m-d H:i',$value['update_time']);
}
if(empty($value['note'])){
$list[$key]['note'] = "暂无备注";
}
}
$count = $Db_blacklist->where($where)->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function add_blackphone(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$id = input('id','','trim,strip_tags');
$name = input('username','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$unit = input('unit','','trim,strip_tags');
$duties = input('duties','','trim,strip_tags');
$note = input('note','','trim,strip_tags');
$data = [
'member_id'=>$uid,
'name'=>$name,
'phone'=>$phone,
'unit'=>$unit,
'duties'=>$duties
];
$Db_blacklist = Db::name('blacklist_phones');
if(empty($id)){
$data['update_time'] = time();
$data['note'] = $note;
$data['create_time'] = time();
$res = $Db_blacklist->insert($data);
}else{
$data['update_time'] = time();
$data['note'] = $note;
$res = $Db_blacklist->where('id',$id)->update($data);
}
if(empty($res)){
return returnAjax(1,'添加或更新失败',$data);
}else{
return returnAjax(0,'添加或更新成功',$data);
}
}
public function delelte_blacklist(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$type = input('type','','trim,strip_tags');
if($type == 1){
$where = [];
$where['member_id'] = array('eq',$uid);
$name = input('username','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$unit = input('unit','','trim,strip_tags');
$duties = input('duties','','trim,strip_tags');
if(!empty($name)){
$where['name'] = array('like','%'.$name.'%');
}
if(!empty($phone)){
$where['phone'] = array('eq',$phone);
}
if(!empty($unit)){
$where['unit'] = array('like','%'.$unit.'%');
}
if(!empty($duties)){
$where['duties'] = array('like','%'.$duties.'%');
}
$res = Db::name('blacklist_phones')->where($where)->delete();
}else{
$id = input('id','','trim,strip_tags');
if($id){
$where = [];
$where['id'] = array('eq',$id);
}else{
$where = [];
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
}
$where['member_id'] = array('eq',$uid);
$res = Db::name('blacklist_phones')->where($where)->delete();
}
if(!empty($res)){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
public function blackinfo(){
$name = input('username','','trim,strip_tags');
$phone = input('phone','','trim,strip_tags');
$unit = input('unit','','trim,strip_tags');
$duties = input('duties','','trim,strip_tags');
$Db_blacklist = Db::name('blacklist_phones')
->field('id');
if(!empty($name)){
$Db_blacklist = $Db_blacklist->where('name','like','%'.$name.'%');
}
if(!empty($phone)){
$Db_blacklist = $Db_blacklist->where('phone',$phone);
}
if(!empty($unit)){
$Db_blacklist = $Db_blacklist->where('unit','like','%'.$unit.'%');
}
if(!empty($duties)){
$Db_blacklist = $Db_blacklist->where('duties','like','%'.$duties.'%');
}
$info = $Db_blacklist->order('create_time','desc')
->select();
return returnAjax(0,'success',$info);
}
public function get_blackinfo(){
$id = input('id','','trim,strip_tags');
$black_info = Db::name('blacklist_phones')
->where('id',$id)
->find();
return returnAjax(0,'success',$black_info);
}
public function import_blacklist(){
set_time_limit(60*60*16);
ini_set('memory_limit','256M');
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES ['excel'] ['tmp_name'];
}else{
return returnAjax(1,'上传失败,请下载模板，填好再选择文件上传。');
}
$file_types = explode ( ".",$_FILES ['excel'] ['name'] );
$file_type = $file_types [count ( $file_types ) -1];
$savePath = './uploads/Excel/';
if (!is_dir($savePath)){
mkdir($savePath);
}
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy ( $tmp_file,$savePath .$file_name ))
{
return returnAjax(1,'上传失败');
}
$foo = new \PHPExcel();
$extension = strtolower( pathinfo($file_name,PATHINFO_EXTENSION) );
if($extension == 'xlsx') {
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
else{
$inputFileType = 'Excel5';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$objPHPExcel = $objReader->load($savePath.$file_name,$encode = 'utf-8');
$excelArr = $objPHPExcel->getsheet(0)->toArray();
$count = count($excelArr) -1;
if (count($excelArr) <2){
return returnAjax(1,'导入的文件没有数据!');
}
$black_info = [];
$repeat_count = 0;
unset($excelArr[0]);
foreach($excelArr as $key=>$value){
if(isset($black_info[$value[1]]) === false){
$black_info[$value[1]] = 1;
}else{
$black_info[$value[1]]++;
}
if($black_info[$value[1]] >1){
unset($excelArr[$key]);
$repeat_count++;
}
}
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$insert_info = [];
$successCnt = 0;
$failCnt = 0;
$existenceCnt = 0;
$not_numbleCnt = 0;
foreach ($excelArr as $k =>$v) {
$user['mobile'] = trim(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$v[1]));
$isMob="/^1[2345789]{1}\d{9}$/";
if(preg_match($isMob,$user['mobile'])){
$black_mobile = Db::name('blacklist_phones')
->field('phone')
->where('phone',$user['mobile'])
->find();
if(empty($black_mobile)){
$creat_time = time();
$updata_time = $creat_time;
$insert_info[$k] = [
'member_id'=>$uid,
'phone'=>$v[1],
'create_time'=>$creat_time
];
$insert_result[$k] = Db::name('blacklist_phones')->insert($insert_info[$k]);
if(empty($insert_result[$k])){
$failCnt = $failCnt +1;
}else {
$successCnt = $successCnt +1;
}
}else {
$existenceCnt = $existenceCnt +1;
}
}else {
$not_numbleCnt = $not_numbleCnt +1;
}
}
$info['excel_info'] = $excelArr;
$info['insert_info'] = $insert_info;
$info['success_count'] = $successCnt;
$info['existenceCnt'] = $existenceCnt;
$info['not_numbleCnt'] = $not_numbleCnt;
if(count($excelArr) == ($successCnt+$failCnt+$existenceCnt+$not_numbleCnt)){
return returnAjax(0,'成功',$info);
}else {
return returnAjax('1','导入数据库失败',$info);
}
}
public function rule_setting(){
return $this->fetch("rule_setting");
}
public function get_rule_data(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$Db_blackrule = Db::name('blacklist_rules')
->where('member_id',$uid)
->page($page,$limit)
->order('id','desc')
->select();
$count = Db::name('blacklist_rules')->where('member_id',$uid)->count();
$ids =  Db::name('blacklist_rules')->field('id')->where('member_id',$uid)->select();
$list = $Db_blackrule;
foreach ($list as $key =>$value) {
$list[$key]['sequence'] = ($page-1)*10+($key+1);
if(empty($value['note'])){
$list[$key]['note'] = "暂无备注";
}
}
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取成功',$data);
}
public function delete_rule(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$type = input('type','','trim,strip_tags');
if($type == 1){
$res = Db::name('blacklist_rules')->where('member_id',$uid)->delete();
}else{
$id = input('id','','trim,strip_tags');
if($id){
$where = [];
$where['id'] = array('eq',$id);
}else{
$where = [];
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
}
$where['member_id'] = array('eq',$uid);
$res = Db::name('blacklist_rules')->where($where)->delete();
}
if(!empty($res)){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}
public function add_rule(){
$user_auth = session('user_auth');
$uid = $user_auth['uid'];
$id = input('id','','trim,strip_tags');
$name = input('name','','trim,strip_tags');
$rule = input('rule','','trim,strip_tags');
$note = input('note','','trim,strip_tags');
$data = [
'member_id'=>$uid,
'name'=>$name,
'rule'=>$rule,
'note'=>$note
];
$Db_blackrule = Db::name('blacklist_rules');
if(empty($id)){
$res = $Db_blackrule->insert($data);
}else{
$res = $Db_blackrule->where('id',$id)->update($data);
}
if(empty($res)){
return returnAjax(1,'添加或更新失败',$data);
}else{
return returnAjax(0,'添加或更新成功',$data);
}
}
public function get_ruleinfo(){
$id = input('id','','trim,strip_tags');
$rule_info = Db::name('blacklist_rules')
->where('id',$id)
->find();
return returnAjax(0,'success',$rule_info);
}
public  function backdetail(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$super = $user_auth["super"];
$mobile = input('mobile','','trim,strip_tags');
$taskId = input('taskId','','trim,strip_tags');
$recordId = input('recordId','','trim,strip_tags');
$froms = input('froms','','trim,strip_tags');
$where['task']=$taskId;
$where['mobile']=$mobile;
$member = Db::name('member')->where($where)->find();
if($member['sex']==0){
$member['sex']="未知";
}elseif($member['sex']==1){
$member['sex']="男";
}elseif($member['sex']==2){
$member['sex']="女";
}
if(empty($member->originating_call)){
$member['originating_call']="无";
}
$list=[];
$list['memberInfo']=$member;
$where1['phone']=$mobile;
$where1['call_id']=$member['uid'];
$where1['status']=['>',-1];
$bills= Db::name('tel_bills')->where($where1)->order('id asc')->select();
$list['bills']=$bills;
$num=0;
foreach($bills as $k=>$v){
if(!empty($v['hit_keyword']) &&$v['hit_keyword']!=null){
$num=$num+1;
}
}
$list['num']=$num;
if(!empty($member)){
return returnAjax(0,'成功了',$list);
}else{
return returnAjax(1,'用户信息为空');
}
}
public function  changeLevel(){
$uid   = input('post.uid','','trim,strip_tags');
$level = input('post.level','','trim,strip_tags');
if(empty($uid) ||empty($level)){
return returnAjax(1,'用户或者等级不能为空');
}
$where['uid']=$uid;
$data['level']=$level;
$res =  Db::name('member')->where($where)->update($data);
if($res){
return returnAjax(0,'修改等级成功');
}else if($res===0){
return returnAjax(0,'没有修改等级');
}else{
return returnAjax(1,'修改等级出错');
}
}
public function task_template() {
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if(request()->isPost()){
$page = input('page','','strip_tags');
$limit = input('limit','','strip_tags');
$templateName = input('templateName','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$limit){
$limit = 10;
}
$where = [];
if($templateName){
$where['template'] = array('like',"%".$templateName."%");
}
$where['member_id'] = array('eq',$uid);
$list = Db::name('tel_tasks_templates')->where($where)->page($page,$limit)->order('id','desc')->select();
$count = Db::name('tel_tasks_templates')->where($where)->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
$super = $user_auth["super"];
$where = array();
if (!$super) {
$where['member_id'] = $uid;
}
$where['status'] = 1;
$where['check_statu'] = ['<>',1];
$scenarioslist = Db::name('tel_scenarios')->where($where)->field('id,name')->order('id asc')->select();
$this->assign('scenarioslist',$scenarioslist);
$asr_list = Db::name('tel_interface')
->field('id,name')
->where('owner',$uid)
->select();
$this->assign('asr_list',$asr_list);
$line_datas = Db::name('tel_line_group')
->field('id,name')
->where(['user_id'=>$uid,'status'=>1])
->select();
$this->assign('line_datas',$line_datas);
$default_line_id = Db::name('admin')
->where('id',$uid)
->value('default_line_id');
$this->assign('default_line_id',$default_line_id);
$yunying_id = $this->get_operator_id($uid);
$wx_config =Db::name('wx_config')->where(['member_id'=>$yunying_id])->find();
$wx_push_users = Db::name('wx_push_users')
->field('id,name')
->where([
'member_id'=>$uid,
'wx_config_id'=>$wx_config['id'],
])
->select();
$this->assign('wx_push_users',$wx_push_users);
$crm_push_users = Db::name('admin')
->field('id,username')
->where([
'pid'=>$uid,
'role_id'=>20,
])
->select();
$this->assign('crm_push_users',$crm_push_users);
$sms_where = array();
$sms_where['st.owner'] = $uid;
$sms_where['st.status'] = 3;
$sms_template = Db::name('sms_template')
->alias('st')
->join('sms_sign ss','st.sign_id = ss.id','LEFT')
->field('st.id,st.name,ss.name as sign_name,st.content')
->where($sms_where)
->select();
$this->assign('sms_template',$sms_template);
return $this->fetch('task_template');
}
public function get_tasks_template(){
$id = input('id','','trim,strip_tags');
$info = Db::name('tel_tasks_templates')->where('id',$id)->find();
$info['date_team'] = unserialize($info['date_team']);
$info['time_team'] = unserialize($info['time_team']);
if($info){
return returnAjax(0,'获取数据成功',$info);
}else{
return returnAjax(1,'获取数据成功',$info);
}
}
public function add_edit_template() {
$task_template_name = input('task_template_name','','trim,strip_tags');
$scenarios_id = input('scenarios_id','','trim,strip_tags');
$date = input('date/a','','trim,strip_tags');
$time = input('times/a','','trim,strip_tags');
$is_auto = input('is_auto','','trim,strip_tags');
$robot_count = input('robot_count','','trim,strip_tags');
$line_id = input('line_id','','trim,strip_tags');
$asr_id = input('asr_id','','trim,strip_tags');
$is_default_line = input('is_default_line','','trim,strip_tags');
$id = input('id','','trim,strip_tags');
$task_abnormal_remind_phone = input('task_abnormal_remind_phone','','trim,strip_tags');
$is_again_call = input('is_again_call','','trim,strip_tags');
$again_call_status = input('again_call_status/a','','trim,strip_tags');
if (is_array($again_call_status) === true) {
$again_call_status = implode(',',$again_call_status);
}else {
$again_call_status = '';
}
$again_call_count = input('again_call_count/d','','trim,strip_tags');
$send_sms_status = input('send_sms_status','','trim,strip_tags');
$send_sms_level = input('send_sms_level/a','','trim,strip_tags');
if (is_array($send_sms_level) &&count($send_sms_level) >0) {
$send_sms_level = implode(',',$send_sms_level);
}else {
$send_sms_level = '';
}
$yunkong_push_status = input('yunkong_push_status',0,'trim,strip_tags');
$yunkong_push_username = input('yunkong_push_username','','trim,strip_tags');
$yunkong_push_level = input('yunkong_push_level/a','','trim,strip_tags');
if (is_array($yunkong_push_level) &&count($yunkong_push_level) >0) {
$yunkong_push_level = implode(',',$yunkong_push_level);
}else {
$yunkong_push_level = '';
}
$sms_template_id = input('sms_template_id','','trim,strip_tags');
$is_add_crm = input('is_add_crm','','trim,strip_tags');
$add_crm_level = input('add_crm_level/a','','trim,strip_tags');
if (is_array($add_crm_level) &&count($add_crm_level)) {
$add_crm_level = implode(',',$add_crm_level);
}else {
$add_crm_level = '';
}
$crm_push_user_id = input('crm_push_user_id','','trim,strip_tags');
$wx_push_status = input('wx_push_status','','trim,strip_tags');
$wx_push_level = input('wx_push_level/a','','trim,strip_tags');
if (is_array($wx_push_level) &&count($wx_push_level)) {
$wx_push_level = implode(',',$wx_push_level);
}else {
$wx_push_level = '';
}
$wx_push_user_id = input('wx_push_user_id','','trim,strip_tags');
$remark = input('remark','','trim,strip_tags');
$call_type = 1;
$status = 0;
if (empty($scenarios_id)) {
return $this->Json(2,'请选择话术');
}
if (empty($robot_count)) {
return $this->Json(2,'机器人数量不能为空');
}
if (empty($line_id)) {
return $this->Json(2,'请选择线路');
}
if (empty($asr_id)) {
return $this->Json(2,'请选择ASR');
}
$regex = config('phone_regular');
if (preg_match($regex,$task_abnormal_remind_phone) === false) {
return returnAjax(2,'任务异常短信提醒的手机号码格式错误');
}
if ($is_again_call == 1) {
if (empty($again_call_status)) {
return returnAjax(2,'请选择需要进行重新呼叫的通话状态');
}
if (empty($again_call_count)) {
return returnAjax(2,'请选择重新呼叫次数');
}
}
if ($yunkong_push_status == 1) {
if (empty($yunkong_push_username)) {
return returnAjax(2,'推送微信云控的用户名不能为空');
}
if (empty($yunkong_push_level)) {
return returnAjax(2,'请选择需要推送到微信云控的意向等级');
}
}
if ($send_sms_status == 1) {
if (empty($send_sms_level)) {
return returnAjax(2,'没有选择触发发送短信的意向等级');
}
if (empty($sms_template_id)) {
return returnAjax(2,'没有选中指定短信模版');
}
}
if ($is_add_crm == 1) {
if (empty($add_crm_level)) {
return returnAjax(2,'请选择加入CRM的客户意向等级');
}
}
if ($wx_push_status == 1) {
if (empty($wx_push_level)) {
return returnAjax(2,'请选择微信推送的客户意向等级');
}
if (empty($wx_push_user_id)) {
return returnAjax(2,'请选择推动的人员');
}
}
$line_count = Db::name('tel_line_group')
->where(['id'=>$line_id,'status'=>1])
->count('id');
if (empty($line_count)) {
return returnAjax(2,'线路不存在');
}
$user_auth = session('user_auth');
$usable_robot_cnt = Db::name('admin')
->where("id",$user_auth['uid'])
->value('usable_robot_cnt');
$run_robot_count = Db::name('tel_config')
->where(['member_id'=>$user_auth['uid'],'status'=>1])
->sum('robot_cnt');
$usable_robot_count = $usable_robot_cnt -$run_robot_count;
if ($robot_count >$usable_robot_count) {
return returnAjax(2,'机器人数量不足',$usable_robot_count);
}
$scenarios_count = Db::name('tel_scenarios')
->where('id',$scenarios_id)
->count('id');
if (empty($scenarios_count)) {
return returnAjax(2,'话术不存在');
}
if($id){
$task_name_count = Db::name('tel_tasks_templates')
->where([
'template'=>$task_template_name,
'member_id'=>$user_auth['uid'],
'id'=>['<>',$id]
])
->count('id');
if ($task_name_count >=1) {
return returnAjax(2,'任务模板名已重复');
}
}else{
$task_name_count = Db::name('tel_tasks_templates')
->where([
'template'=>$task_template_name,
'member_id'=>$user_auth['uid'],
])
->count('id');
if ($task_name_count) {
return returnAjax(2,'任务模板名已重复');
}
}
if ($is_default_line == 1) {
$update_line_id = Db::name('admin')
->where('id',$user_auth['uid'])
->update([
'default_line_id'=>$line_id
]);
if (empty($update_line_id)) {
\think\Log::record('更新默认线路ID');
}
}
$line_data = Db::name('tel_line')
->field('dial_format,phone,originate_variables,call_prefix')
->where('id',$line_id)
->find();
$task_config = [];
$task_config['member_id'] = $user_auth['uid'];
$task_config["template"] = $task_template_name;
$task_config["scenarios_id"] = $scenarios_id;
$task_config["call_type"] = $call_type;
$task_config["phone"] = $line_data['phone']??'';
$task_config["call_prefix"] = $line_data['call_prefix']??'';
$task_config['remarks'] = $remark;
$task_config['default_line_id'] = $is_default_line;
$task_config['robot_cnt'] = $robot_count;
$task_config['create_time'] = time();
$task_config['is_auto'] = $is_auto;
if($date &&$time){
$task_config['date_team'] = serialize($date);
$task_config['time_team'] = serialize($time);
}else{
$task_config['date_team'] = '';
$task_config['time_team'] = '';
}
$task_config['asr_id'] = $asr_id;
$task_config['call_phone_id'] = $line_id;
$task_config['send_sms_status'] = $send_sms_status;
$task_config['send_sms_level'] = $send_sms_level;
$task_config['sms_template_id'] = $sms_template_id;
$task_config['is_add_crm'] = $is_add_crm;
$task_config['add_crm_level'] = $add_crm_level;
$task_config['add_crm_zuoxi'] = $crm_push_user_id;
$task_config['wx_push_status'] = $wx_push_status;
$task_config['wx_push_level'] = $wx_push_level;
$task_config['wx_push_user_id'] = $wx_push_user_id;
$task_config['is_again_call'] = $is_again_call;
$task_config['again_call_status'] = $again_call_status;
$task_config['again_call_count'] = $again_call_count;
$task_config['yunkong_push_status'] = $yunkong_push_status;
$task_config['yunkong_push_username'] = $yunkong_push_username;
$task_config['yunkong_push_level'] = $yunkong_push_level;
$task_config['task_abnormal_remind_phone'] = $task_abnormal_remind_phone;
if($id){
$task_id = Db::name('tel_tasks_templates')->where('id',$id)->update($task_config);
if ($task_id) {
return returnAjax(0,'修改任务模板成功');
}else {
return returnAjax(1,'修改任务模板失败');
}
}else{
$task_id = Db::name('tel_tasks_templates')->insert($task_config);
if ($task_id) {
return returnAjax(0,'新建任务模板成功');
}else {
return returnAjax(1,'新建任务模板失败');
}
}
}
public function del_task_template(){
$id = input('id','','trim,strip_tags');
if($id){
$where['id'] = array('eq',$id);
$res = Db::name('tel_tasks_templates')->where($where)->delete();
if($res){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}else{
$type = input('type','','trim,strip_tags');
$templateName = input('templateName','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['member_id'] = array('eq',$uid);
if($templateName){
$where['template'] = array('eq',$templateName);
}
if($type == 1){
$res = Db::name('tel_tasks_templates')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}else if($type == 0){
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
$res = Db::name('tel_tasks_templates')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}
}
}
public function add_edit_phone_box(){
$id = input('id','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = [];
$data['box_name'] = input('name','','trim,strip_tags');
$data['remarks'] = input('remarks','','trim,strip_tags');
if($id){
$count = Db::name('tel_phone_box')->where(['box_name'=>$data['box_name'],'member_id'=>$uid ,'id'=>['neq',$id]])->count();
if($count >= 1 ){
return returnAjax(1,'号码组名是唯一的');
}else{
$res = Db::name('tel_phone_box')->where('id',$id)->update($data);
if($res){
return returnAjax(0,'修改成功');
}else{
return returnAjax(1,'修改失败');
}
}
}else{
$data['member_id'] = $uid;
$data['establish_time'] = time();
$count = Db::name('tel_phone_box')->where(['box_name'=>$data['box_name'],'member_id'=>$uid])->count();
if($count >0 ){
return returnAjax(1,'号码组名是唯一的');
}else{
$res = DB::name('tel_phone_box')->insert($data);
if($res){
return returnAjax(0,'添加成功');
}else{
return returnAjax(1,'添加失败');
}
}
}
}
public function phone_manage() {
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$this->assign('role_name',$user_auth['role']);
$is_verification = Db::name('admin')->where('id',$uid)->value('is_verification');
$this->assign('is_verification',$is_verification);
if(request()->isPost()){
$type = input('type','','trim,strip_tags');
if($type == 1){
$page = input('page','','strip_tags');
$limit = input('limit','','strip_tags');
$box_name = input('box_name','','trim,strip_tags');
$startTime = input('startTime','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$limit){
$limit = 10;
}
$where = [];
if($box_name){
$where['box_name'] = array('like',"%".$box_name."%");
}
if($startTime ||$endTime){
$endTime = date("Y-m-d",strtotime("+1 day",strtotime($endTime)));
$where['establish_time'] =  array('between time',array($startTime,$endTime));
}
$where['member_id'] = array('eq',$uid);
$count = Db::name('tel_phone_box')->where($where)->count();
$page_count = ceil($count/$limit);
if($page >$page_count){
$page = $page_count -1;
}
$list = Db::name('tel_phone_box')->where($where)->page($page,$limit)->order('id','desc')->select();
foreach($list as $key =>$vo){
$list[$key]['establish_time'] = date("Y-m-d H:i:s",$vo['establish_time']);
$list[$key]['count'] = DB::name('tel_phone_data')->where('pid',$vo['id'])->count();
}
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}else{
$page = input('page','','strip_tags');
$limit = input('limit','','strip_tags');
$groupname = input('list_groupName','','trim,strip_tags');
$phoneNumber = input('list_phoneNumber','','trim,strip_tags');
if(!$page){
$page = 1;
}
if(!$limit){
$limit = 10;
}
$where = [];
if($groupname){
$where['box.box_name'] = array('like',"%".$groupname."%");
}
if($phoneNumber){
$where['data.queue'] = array('like',"%".$phoneNumber."%");
}
$where['data.member_id'] = array('eq',$uid);
$count = Db::name('tel_phone_data')
->alias('data')
->field('data.*,box.box_name')
->join('tel_phone_box box','box.id = data.pid','LEFT')
->where($where)
->count();
$page_count = ceil($count/$limit);
if($page >$page_count){
$page = $page_count -1;
}
$list = Db::name('tel_phone_data')
->alias('data')
->field('data.*,box.box_name')
->join('tel_phone_box box','box.id = data.pid','LEFT')
->where($where)
->page($page,$limit)
->order('id','desc')
->select();
foreach($list as $key =>$vo){
$list[$key]['establish_time'] = date("Y-m-d H:i:s",$vo['establish_time']);
if(!$vo['nickname']){
$list[$key]['nickname'] = '暂无名称';
}
}
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
}
return $this->fetch('phone_manage');
}
public function get_editPhone(){
$id = input('id','','trim,strip_tags');
$info = Db::name('tel_phone_box')->where('id',$id)->find();
$info['count'] = Db::name('tel_phone_data')->where('pid',$info['id'])->count();
if($info){
return returnAjax(0,'获取成功',$info);
}else{
return returnAjax(1,'获取失败');
}
}
public function edit_add_singlePhone(){
$id = input('id','','trim,strip_tags');
$data = [];
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data['queue'] = input('phone','','trim,strip_tags');
$data['nickname'] = input('nickname','','trim,strip_tags');
$data['pid'] = input('pid','','trim,strip_tags');
if($id){
$count = Db::name('tel_phone_data')->where(['member_id'=>$uid,'queue'=>$data['queue'],'pid'=>$data['pid']])->count();
if($count >1){
return returnAjax(1,'号码组中的号码是惟一的');
}else{
$res = Db::name('tel_phone_data')->where('id',$id)->update($data);
if($res){
return returnAjax(0,'修改成功');
}else{
return returnAjax(1,'修改失败');
}
}
}else{
$data['establish_time'] = time();
$data['member_id'] = $uid;
$count = Db::name('tel_phone_data')->where(['member_id'=>$uid,'queue'=>$data['queue'],'pid'=>$data['pid']])->count();
if($count >0){
return returnAjax(1,'号码组中的号码是惟一的');
}else{
$res = Db::name('tel_phone_data')->insert($data);
if($res){
return returnAjax(0,'添加成功');
}else{
return returnAjax(1,'添加失败');
}
}
}
}
public function list_phone_box(){
$task_id = input('task_id','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$scenarios_id = Db::name('tel_config')->where('task_id',$task_id)->value('scenarios_id');
$is_variable = Db::name('tel_scenarios')->where('id',$scenarios_id)->value('is_variable');
$list = Db::name('tel_phone_box')->where('member_id',$uid)->column('box_name','id');
return returnAjax(0,'获取成功',['list'=>$list,'is_variable'=>$is_variable,'url'=>url('scenarios/variabl_template1',['id'=>$scenarios_id])]);
}
public function get_phone_data(){
$id = input('id','','trim,strip_tags');
$info = Db::name('tel_phone_data')->where('id',$id)->find();
if($info){
return returnAjax(0,'获取成功',$info);
}else{
return returnAjax(1,'获取失败');
}
}
public function phone_detail() {
return $this->fetch();
}
public function import_phoneFiles(){
set_time_limit(60*60*16);
ini_set('memory_limit','256M');
if(!empty($_FILES['excel']['tmp_name'])){
$tmp_file = $_FILES ['excel']['tmp_name'];
}else{
return returnAjax(1,'上传失败,请下载模板，填好再选择文件上传。');
}
$file_types = explode(".",$_FILES['excel']['name']);
$file_type = $file_types [count ( $file_types ) -1];
$savePath = './uploads/Excel/';
if (!is_dir($savePath)){
mkdir($savePath,0777,true);
}
$chaos_num = input('chaos_num','','trim,strip_tags');
$pid = input('pid','','trim,strip_tags');
$str = date ( 'Ymdhis');
$file_name = $str .".".$file_type;
if (!copy($tmp_file,$savePath .$file_name)){
return returnAjax(1,'上传失败');
}
$foo = new \PHPExcel_Reader_Excel2007();
$extension = strtolower( pathinfo($file_name,PATHINFO_EXTENSION) );
if($extension == 'xlsx') {
$inputFileType = 'Excel2007';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}else{
$inputFileType = 'Excel5';
$objReader = \PHPExcel_IOFactory::createReader($inputFileType);
}
$objPHPExcel = $objReader->load($savePath.$file_name,$encode = 'utf-8');
$excelArr = $objPHPExcel->getsheet(0)->toArray();
if (count($excelArr) <2){
return returnAjax(1,'导入的文件没有数据!');
}
unset($excelArr[0]);
foreach($excelArr as $key=>$value){
if(empty($value[1])){
unset($excelArr[$key]);
}
}
$countSum = count($excelArr);
$number_count = $countSum;
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_number_count';
$total_key = 'task_'.$chaos_num .'_total_number_count';
$RedisConnect->set($key,$number_count);
$RedisConnect->set($total_key,$number_count);
$number = [];
foreach($excelArr as $key=>$value){
if(!isset($number[$value[1]])  &&!empty($value[1])){
$number[$value[1]] = 1;
}elseif(!empty($value[1])){
unset($excelArr[$key]);
}
}
$excelArr = $this->blacklistRule($excelArr);
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$data = array();
$taskdata = array();
$totalCnt = 0;
$successCnt = 0;
$count = count($excelArr);
$numlist = array();
$success_count = 0;
$existence_number_rows = [];
$existence_number_count = 0;
foreach($excelArr as $k =>$v){
$isMob="/^(13[0-9]|14[579]|15[0-3,5-9]|16[6]|17[0135678]|18[0-9]|19[289])\d{8}$/";
$isTel="/^([0-9]{3,4}-)?[0-9]{7,8}$/";
$user['queue'] = trim(preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/","",$v[1]));
$totalCnt++;
if(!$v[0] ||$v[0] == null){
$v[0] = "";
}
if(preg_match($isTel,$user['queue'])){
$success_count++;
$user['member_id'] = $uid;
$user['pid'] = $pid;
$user['establish_time'] = time();
$user['nickname'] = $v[0];
if(!empty($user['queue'])){
$successCnt++;
array_push($data,$user);
$taskuser['number'] = $user['queue'];
array_push($taskdata,$taskuser);
array_push($numlist,$user['queue']);
}
}elseif(preg_match($isMob,$user['queue'])){
$success_count++;
$user['member_id'] = $uid;
$user['pid'] = $pid;
$user['establish_time'] = time();
$user['nickname'] = $v[0];
if(!empty($user['queue'])){
$successCnt++;
array_push($data,$user);
$taskuser['number'] = $user['queue'];
array_push($taskdata,$taskuser);
array_push($numlist,$user['queue']);
}
}else{
$number_count--;
}
if($successCnt == 1000 ||$totalCnt == $count){
$where = array();
$where['member_id'] = $uid;
$where['queue']=['in',$numlist];
$where['pid'] = ['eq',$pid];
$mlist = Db::name('tel_phone_data')->field('member_id,queue')->where($where)->select();
if(!empty($mlist)){
foreach ($data as $dakey =>$davalue) {
foreach ($mlist as $key =>$value) {
if( $davalue['queue'] == $value['queue']){
if(isset($data[$dakey]) === true &&isset($taskdata[$dakey]) === true){
unset($data[$dakey]);
unset($taskdata[$dakey]);
$existence_number_count++;
$success_count--;
$number_count--;
}
}
}
}
}
if ($data){
$result = Db::name('tel_phone_data')->insertAll($data);
$number_count = $number_count -$result;
$number_count_key = 'task_'.$chaos_num .'_number_count';
$RedisConnect->set($number_count_key,$number_count);
array_splice($data,0,count($data));
}
$successCnt = 0;
array_splice($numlist,0,count($numlist));
}
}
ini_set('memory_limit','-1');
return returnAjax(0,'总共导入'.$countSum.'条，成功导入'.$success_count.'条信息,');
}
public function blacklistRule($phone){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$black_array = DB::name('blacklist_phones')->where('member_id',$uid)->column('phone');
$rule_array =  Db::name('blacklist_rules')->where('member_id',$uid)->column('rule');
if($rule_array){
$rule_array = implode("|",$rule_array);
$pattern = "/".$rule_array."/";
}
foreach ($phone as $key =>$value) {
if(in_array($value[1],$black_array) == true){
unset($phone[$key]);
continue;
}
if(isset($pattern)){
if(preg_match($pattern,$value[1]) == true ){
unset($phone[$key]);
continue;
}
}
}
return $phone;
}
public function del_phone_box(){
$id = input('id','','trim,strip_tags');
$count = Db::name('tel_phone_data')->where('pid',$id)->count();
if($count >0){
return returnAjax(1,'号码组删除失败,号码组中存在号码');
}else{
$res = Db::name('tel_phone_box')->where('id',$id)->delete();
if($res){
return returnAjax(0,'号码组删除成功');
}else{
return returnAjax(1,'号码组删除失败');
}
}
}
public function del_phone_data(){
$id = input('id','','trim,strip_tags');
if($id){
$where['id'] = array('eq',$id);
$res = Db::name('tel_phone_data')->where($where)->delete();
if($res){
return returnAjax(0,'删除成功');
}else{
return returnAjax(1,'删除失败');
}
}else{
$type = input('type','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['member_id'] = array('eq',$uid);
if($type == 1){
$groupname = input('list_groupName','','trim,strip_tags');
$phoneNumber = input('list_phoneNumber','','trim,strip_tags');
$where2 = [];
if($groupname){
$where2['box_name'] = array('like',"%".$groupname."%");
$box_nameids = Db::name('tel_phone_box')->where($where2)->column('id');
if($box_nameids){
$where['pid'] = array('in',$box_nameids);
}
}
if($phoneNumber){
$where['queue'] = array('like',"%".$phoneNumber."%");
}
$res = Db::name('tel_phone_data')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}else if($type == 0){
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
$res = Db::name('tel_phone_data')->where($where)->delete();
if($res){
return returnAjax(0,'批量删除成功');
}else{
return returnAjax(1,'批量删除失败');
}
}
}
}
public function phone_data_export(){
$type = input('type','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['data.member_id'] = array('eq',$uid);
if($type == 1){
$groupname = input('list_groupName','','trim,strip_tags');
$phoneNumber = input('list_phoneNumber','','trim,strip_tags');
if($groupname){
$where['box.box_name'] = array('like',"%".$groupname."%");
}
if($phoneNumber){
$where['data.queue'] = array('like',"%".$phoneNumber."%");
}
$list = Db::name('tel_phone_data')
->alias('data')
->field('data.nickname,data.queue,box.box_name')
->join('tel_phone_box box','box.id = data.pid','LEFT')
->where($where)
->select();
}else if($type == 0){
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['data.id'] = array('in',$ids);
$list = Db::name('tel_phone_data')
->alias('data')
->field('data.nickname,data.queue,box.box_name')
->join('tel_phone_box box','box.id = data.pid','LEFT')
->where($where)
->select();
}
$chaos_num = input('chaos_num','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$phone_count = count($list);
$RedisConnect->set($key,$phone_count);
$columName = ['姓名','电话','分组'];
$setTitle = 'Sheet1';
$fileName = '文件名称';
if (empty($columName) ||empty($list)) {
return returnAjax(1,'内容不能为空!',"失败");
}
if (count($list[0]) != count($columName)) {
return returnAjax(1,'列名跟数据的列不一致!',"失败");
}
$PHPExcel = new \PHPExcel();
$PHPSheet = $PHPExcel->getActiveSheet();
$PHPSheet->setTitle($setTitle);
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$i = 0;
foreach($columName as $key =>$vo){
$PHPSheet->setCellValue($letter[$i]."1",$vo);
$i = $i+1;
}
$complete_num = 0;
foreach ($list as $key =>$val) {
foreach (array_values($val) as $key2 =>$val2) {
$PHPSheet->setCellValue($letter[$key2].($key+2),$val2);
}
$complete_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$complete_num);
}
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)) {
mkdir($execlpath,0777,true);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
if ($list) {
return returnAjax(0,'成功',config('res_url').ltrim($execlpath,"."));
}else {
return returnAjax(1,'失败!',"失败");
}
}
public function phone_box_export(){
$type = input('type','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['member_id'] = array('eq',$uid);
if($type == 1){
$box_name = input('box_name','','trim,strip_tags');
$startTime = input('startTime','','trim,strip_tags');
$endTime = input('endTime','','trim,strip_tags');
if($box_name){
$where['box_name'] = array('like',"%".$box_name."%");
}
if($startTime ||$endTime){
$where['establish_time'] =  array('between time',array($startTime,$endTime));
}
$list_box= DB::name('tel_phone_box')->where($where)->order('id','desc')->column('box_name','id');
}else if($type == 0){
$idArr = input('vals','','trim,strip_tags');
$ids = explode (',',$idArr);
$where['id'] = array('in',$ids);
$list_box= DB::name('tel_phone_box')->where($where)->order('id','desc')->column('box_name','id');
}
$columName = ['姓名','电话'];
$fileName = '文件名称';
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$index = 0 ;
$sum = count($list_box);
$list_box_array = [];
foreach($list_box as $j=>$i){
$list_box_array[] = $j;
}
$phone_count = DB::name('tel_phone_data')->where(['pid'=>['in',$list_box_array]])->count();
$chaos_num = input('chaos_num','','trim,strip_tags');
$RedisConnect = RedisConnect::get_redis_connect();
$key = 'task_'.$chaos_num .'_count';
$RedisConnect->set($key,$phone_count);
$PHPExcel = new \PHPExcel();
foreach($list_box as $key =>$vo){
$setTitle = $vo;
$list = Db::name('tel_phone_data')->field('nickname,queue')->where('pid',$key)->select();
$PHPExcel->createSheet();
$PHPExcel->setactivesheetindex($index);
$PHPSheet = $PHPExcel->getActiveSheet();
$PHPSheet->setTitle($setTitle);
$i = 0;
foreach($columName as $k =>$v){
$PHPSheet->setCellValue($letter[$i]."1",$v);
$i = $i+1;
}
$complete_num = 0;
foreach ($list as $key =>$val) {
foreach (array_values($val) as $key2 =>$val2) {
$PHPSheet->setCellValue($letter[$key2].($key+2),$val2);
}
$complete_num ++;
$complete_key = 'task_'.$chaos_num .'_complete_count';
$RedisConnect->set($complete_key,$complete_num);
}
$index++;
}
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)) {
mkdir($execlpath,0777,true);
}
$execlpath .= rand_string(12,'',time()).'excel.xlsx';
$PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel,'Excel2007');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename='.$fileName.'.xlsx');
header('Cache-Control: max-age=0');
$PHPWriter->save($execlpath);
$RedisConnect->del($complete_key);
$RedisConnect->del($key);
if ($list) {
return returnAjax(0,'成功',config('res_url').ltrim($execlpath,"."));
}else {
return returnAjax(1,'失败!',"失败");
}
}
public function ceshi(){
if(request()->isPost()){
return returnAjax(1,'adfasdfa');
}
return $this->fetch();
}
public function user_list($user_id)
{
$user_list = [];
$users = Db::name('admin')->alias('a')->join('admin_role ar','a.role_id = ar.id','LEFT')->where('a.pid',$user_id)->field('a.id, a.username, a.pid, ar.name as role_name')->select();
foreach($users as $key=>$value){
if(!empty($value)){
$user_list[$value['id']] = $value;
$user_list[$value['id']]['find'] = $this->user_list($value['id']);
}
}
return $user_list;
}
public function indest(){
$user_id = 5555;
$user_info = Db::name('admin')
->alias('a')
->join('admin_role ar','a.role_id = ar.id','LEFT')
->where('a.id',$user_id)
->field('a.id, a.username, ar.name as role_name')
->find();
$list = $this->user_list($user_id);
$setTitle = $user_info['role_name'].'：'.$user_info['username'];
$fileName = $user_info['username'];
$PHPExcel = new \PHPExcel();
$PHPSheet = $PHPExcel->getActiveSheet();
$PHPSheet->setTitle($setTitle);
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$line_number = 1;
$c = 0;
$i = 0;
$this->set_cell_values($PHPSheet,$list,$line_number,$i,$c);
$execlpath = './uploads/exportExcel/';
if (!file_exists($execlpath)){
mkdir($execlpath);
}
$execlpath .= rand_string(12,'',time()).'excel.xls';
$PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel,'Excel2007');
$PHPWriter->save($execlpath);
header('location:'.config('res_url').ltrim($execlpath,"."));
}
public function set_cell_values(&$PHPSheet,$values,&$line_number,&$i = 0,&$c = 0)
{
$letter = [
'A','B','C','D','E','F','G','H','I','J','K','L','M',
'N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
];
$count = count($values);
foreach($values as $key=>$value){
$count--;
$PHPSheet->setCellValue($letter[$i] .$line_number,$value['role_name'] .':'.$value['username']);
if($count == 0){
$c--;
}
$i++;
$line_number++;
if(!empty($value['find']) &&count($value['find'])){
$c++;
$this->set_cell_values($PHPSheet,$value['find'],$line_number,$i,$c);
}else{
$i = $c;
}
}
}
public function getMinLineDialFormat($task_id){
$line_group_id=Db::name('tel_config')->where('status',1)->where('id',$task_id)->value('call_phone_id');
$min_line_id=Db::name('tel_config')->where(['status'=>1,'call_phone_id'=>$line_group_id])->field('sum(robot_cnt) s,call_phone_id ')
->group('call_phone_id')->order('s desc')->find()['call_phone_id'];
if(!empty($call_phone_id))
return Db::name('rk_tel_line')->where('id',$min_line_id)->value('dial_format')['dial_format'];
return false;
}
public function sendout_phone(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['a.pid'] = array('eq',$uid);
$where['a.role_id'] = array('neq',20);
$where['a.status'] = array('eq',1);
$role_list = Db::name('admin')
->alias('a')
->join('admin_role r','a.role_id = r.id','LEFT')
->where($where)
->group('a.role_id')
->column('r.name','role_id');
$this->assign('role_list',$role_list);
return $this->fetch();
}
public function ajax_phone_manage(){
$page = input('page','','trim,strip_tags');
$limit = input('limit','','trim,strip_tags');
$box_name = input('box_name','','trim,strip_tags');
$role_id = input('role_id','','trim,strip_tags');
$userName = input('userName','','trim,strip_tags');
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
if(!$page){
$page = 1;
}
if(!$limit){
$limit = 10;
}
$where = [];
if($box_name){
$where['bo.box_name'] = array('like','%'.$box_name.'%');
}
if($role_id){
$where['se.role_id'] = array('eq',$role_id);
}
if($userName){
$where['ad.username'] = array('like','%'.$userName.'%');
}
$where['se.owner'] =array('eq',$uid);
$list = DB::name('sendout_phone')
->alias('se')
->join('tel_phone_box bo','se.box_name = bo.id','LEFT')
->join('admin ad','ad.id = se.username','LEFT')
->join('admin_role ro','ro.id = se.role_id','LEFT')
->where($where)
->field('se.*,bo.box_name as boxname,ad.username,ro.name')
->order('id','desc')
->page($page,$limit)
->select();
foreach($list as $key =>$v){
$list[$key]['create_time'] = date('Y-m-d H:i',$v['create_time']);
if(!$v['boxname']){
$list[$key]['boxname'] = '号码组已删除';
}
if(!$v['username']){
$list[$key]['username'] = '用户已删除';
}
}
$count = DB::name('sendout_phone')
->alias('se')
->join('tel_phone_box bo','se.box_name = bo.id','LEFT')
->join('admin ad','ad.id = se.username','LEFT')
->join('admin_role ro','ro.id = se.role_id','LEFT')
->where($where)
->field('se.*,bo.box_name as boxname,ad.username,ro.name')
->count();
$page_count = ceil($count/$limit);
$data = array();
$data['list'] = $list;
$data['total'] = $count;
$data['page'] = $page_count;
$data['Nowpage'] = $page;
return returnAjax(0,'获取数据成功',$data);
}
public function get_sendoutphone(){
$id = input('id','','trim,strip_tags');
$box_info = Db::name('tel_phone_box')->where('id',$id)->find();
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$where = [];
$where['a.pid'] = array('eq',$uid);
$where['a.role_id'] = array('neq',20);
$where['a.status'] = array('eq',1);
$role_id = input('role_id','','trim,strip_tags');
if($role_id){
$where['a.role_id'] = array('eq',$role_id);
}
$role_list = Db::name('admin')
->alias('a')
->join('admin_role r','a.role_id = r.id','LEFT')
->where($where)
->field('a.id,a.username,a.role_id,r.name as role_name')
->select();
$role_name = $this->assoc_unique($role_list,'role_id');
$data = [];
$data['box_name'] = $box_info['box_name'];
$data['role_list'] = $role_list;
$data['role_name'] = $role_name;
return returnAjax(1,'',$data);
}
function assoc_unique($arr,$key) {
$tmp_arr = array();
foreach ($arr as $k =>$v) {
if (in_array($v[$key],$tmp_arr)) {
unset($arr[$k]);
}else {
$tmp_arr[] = $v[$key];
}
}
array_multisort(array_column($arr,$key),SORT_ASC,$arr);
return $arr;
}
public function give_subordinate(){
$user_auth = session('user_auth');
$uid = $user_auth["uid"];
$box_name = input('box_name','','trim,strip_tags');
$role_name = input('role_name','','trim,strip_tags');
$username = input('username','','trim,strip_tags');
$sendout_remark = input('sendout_remark','','trim,strip_tags');
$sendout_num = Db::name('tel_phone_data')->where('pid',$box_name)->count();
if(!$sendout_num){
return returnAjax(1,'下发号码数量为空');
}
$box_info = Db::name('tel_phone_box')->where('id',$box_name)->find();
$get_box_name = DB::name('tel_phone_box')->where(['box_name'=>$box_info['box_name'],'member_id'=>$username])->count();
if($get_box_name == 0){
$new_boxname = $box_info['box_name'];
}else{
$i = 1;
while($get_box_name >0 ){
$new_boxname = $box_info['box_name'].'_('.$i.')';
$get_box_name = DB::name('tel_phone_box')->where(['box_name'=>$new_boxname,'member_id'=>$username])->count();
$i++;
};
}
$new_box_id = DB::name('tel_phone_box')->insertGetId(['member_id'=>$username,'box_name'=>$new_boxname,'establish_time'=>time(),'remarks'=>'上级分配']);
$sql = "INSERT INTO rk_tel_phone_data(member_id,pid,queue,nickname,establish_time)
              SELECT ifnull(null, ".$username.") as member_id, ifnull(null,".$new_box_id.") as pid , queue ,nickname, ifnull(null,".time().") as establish_time
              FROM rk_tel_phone_data where pid = ".$box_name."";
$res = Db::execute($sql);
if($res){
$data = [];
$data['owner'] = $uid;
$data['box_name'] = $box_name;
$data['username'] = $username;
$data['role_id'] = $role_name;
$data['sendout_num'] = $sendout_num ;
$data['create_time'] = time();
$data['remark'] = $sendout_remark;
$is_int = Db::name('sendout_phone')->insert($data);
if($is_int){
return returnAjax(1,'下发成功');
}else{
return returnAjax(0,'下发失败');
}
}else{
return returnAjax(0,'下发失败');
}
}
public function weix_push(){
return $this->fetch();
}
public function get_number_resources()
{
if(config('phone_resources_status') == false){
return returnAjax(2,'无效接口');
}
$number_group_name = input('group_name','','trim,strip_tags');
$number_count = input('number_count/d','','trim,strip_tags');
$note = input('note','','trim,strip_tags');
if(empty($number_group_name)){
return returnAjax(2,'请输入号码组名称');
}
if($number_count <1){
return returnAjax(2,'号码数量不能小于1个');
}
if($number_count >10000){
return returnAjax(2,'号码数量不能大于10000个');
}
$user_auth = session('user_auth');
$phone_resources_count = config('phone_resources_count');
$export_number_count = Db::name('admin')->where('id',$user_auth['uid'])->value('export_number_count');
if(($export_number_count +$number_count) >$phone_resources_count){
return returnAjax(2,'剩余号码数量为'.($phone_resources_count -$export_number_count) .'个');
}
$current_time = time();
$insert_data = [
'member_id'=>$user_auth['uid'],
'box_name'=>$number_group_name,
'establish_time'=>$current_time,
'remarks'=>$note
];
$group_id = Db::name('tel_phone_box')->insertGetId($insert_data);
$PhoneResources = new PhoneResources();
$numbers = $PhoneResources->get_numbers($user_auth['uid'],$number_count);
$insert_datas = [];
foreach($numbers as $key=>$value){
$insert_datas[$key] = [
'member_id'=>$user_auth['uid'],
'pid'=>$group_id,
'queue'=>$value,
'nickname'=>'',
'establish_time'=>$current_time
];
}
$result = Db::name('tel_phone_data')->insertAll($insert_datas);
if(!empty($result)){
$PhoneResources->update_user_export_number_count($user_auth['uid'],$number_count);
return returnAjax(0,'成功');
}
return returnAjax(2,'失败');
}
}
