<?php

/**
 * 此文件做测试使用
 * 方法1 添加 修改账号-> edit_accounts();
 * 方法2 销售账号管理列表展示-> ajax_sale_account();
 * 方法3 账号开启与锁定-> open_close_state();
 * 方法4 充值列表展示-> ajax_sale_account_recharge();
 * 方法5 子集账户充值 -> sale_record();
 * 方法6 机器人管理列表-> ajax_robot_management();
 * 方法7 分配机器人-> distribution_robot();
 * 方法8 强制回收机器人-> force_recovery();
 **/

namespace app\common\controller;
use \think\Db;
use \think\Controller;
//ASR
use app\common\controller\AsrData;

//操作记录
use app\common\controller\OperationRecord;

class ManagerMethod extends Controller{
    public function index(){

    }
    //添加账号
    //添加 修改账号前置数据回填
    public function add_account(){
        $user_auth = session('user_auth');
        $uid = $user_auth["uid"];
        //选择用户类型
        $info = Db::name('admin')->where('id',$uid)->find();
        $role_id = $info['role_id'];
        $level = Db::name('admin_role')->where('id',$role_id)->value('level');
        $where_role['level'] = [array('gt',$level),array('neq',5)];
        $where_role['status'] = array('eq',1);
        $role_list = Db::name('admin_role')->where($where_role)->select();
        $this->assign('role_list',$role_list);
        $this->assign('role_id',$role_id);
        $this->assign('role_name', $user_auth['role']);
        //选择线路--线路组
        $where_line['user_id'] = array('eq',$uid);
        $where_line['status'] = array('eq',1);
        $line_list = Db::name('tel_line_group')->where($where_line)->select();
        $this->assign('line_list',$line_list);
        //选择ASR
        $where_asr['owner'] = array('eq',$uid);
        $where_asr['status'] = array('eq',1);
        $asr_list = Db::name('tel_interface')->where($where_asr)->select();
        $this->assign('asr_list',$asr_list);
        //选择短信
        $where_sms['owner'] = array('eq',$uid);
        $where_sms['status'] = array('eq',1);
        $sms_list = Db::name('sms_channel')->where($where_sms)->select();
        $this->assign('sms_list',$sms_list);
        $info['role_name'] = $user_auth['role'];
		//求出此账户 正在运行的机器人数量
        $yunxing = Db::name('tel_config')->where(['status'=>1,'member_id'=>$uid])->sum('robot_cnt');
        $info['usable_robot_cnt'] = $info['usable_robot_cnt'] - $yunxing;  //可用分配的机器人数量 = admin表中剩余机器人的数量-正在运行任务的机器人数量
        $this->assign('info',$info);
        // var_dump($info);
        if(request()->isGet()){
            $id = input('id','','trim,strip_tags');
            $info = Db::name('admin')->where('id',$id)->find();
            $p_info = Db::name('admin')->where('id',$uid)->find();
            $info['role_name'] = Db::name('admin_role')->where('source_id',$info['role_id'])->value('name');
            $info['pid_role_id'] = $role_id;
            $info['pid_role_name'] = $user_auth['role'];
            $info['pid_robot_date'] = $p_info['robot_date'];
            $info['pid_robot_price'] = $p_info['month_price'];

            $line_where = [];
            $line_where['user_id'] = array('eq',$id);
            $line_where['status'] = array('eq',1);
            $line_where['line_group_pid'] = array('neq',0);
            $line_list = Db::name('tel_line_group')->where($line_where)->select();
            if($line_list){
                foreach($line_list as $key => $value1 ){
                    $line_list[$key]['pid_sale_price'] = Db::name('tel_line_group')->where('id',$value1['line_group_pid'])->value('sales_price');
                }
            }
            $info['line_list'] = $line_list;

            $asr_where = [];
            $asr_where['owner'] = array('eq',$id);
            $asr_where['status'] = array('eq',1);
            $asr_where['pid'] = array('neq',0);
            $asr_list = Db::name('tel_interface')->where($asr_where)->select();
            if($asr_list){
                foreach($asr_list as $key => $value){
                    $asr_list[$key]['pid_sale_price'] = Db::name('tel_interface')->where('id',$value['pid'])->value('sale_price');
                }
            }
            $info['asr_list'] = $asr_list;
            //短信
            $sms_where = [];
            $sms_where['owner'] = array('eq',$id);
            $sms_where['status'] = array('eq',1);
            $sms_where['pid'] = array('neq',0);
            $sms_list = Db::name('sms_channel')->where($sms_where)->select();
            if($sms_list){
                foreach($sms_list as $key => $value2 ){
                    $sms_list[$key]['pid_sale_price'] = Db::name('sms_channel')->where('id',$value2['pid'])->value('price');
                }
            }
            $info['sms_list'] = $sms_list;
            return returnAjax(1,'获取数据成功',$info);
        }
        if(request()->isPost()){
            $data = [];
            $id = input('id','','trim,strip_tags');//修改ID
            if($id){
                $data['username'] = input('username','','trim,strip_tags');//子用户名称
                $data['mobile'] = input('mobile','','trim,strip_tags');//手机号
                $data['spare_mobile'] = input('spare_mobile','','trim,strip_tags');//备用手机
                $password = input('password','','trim,strip_tags');//密码
                if(!empty($password)){
                    $data['salt'] = rand_string(6);
                    $data['password'] = md5($password.$data['salt']);
                }
                $robot_date = strtotime(input('robot_date','','trim,strip_tags')); //到期日期
                if(!empty($robot_date)){
                    $data['robot_date'] = $robot_date;
                }
                $type_price = input('type_price','','trim,strip_tags');
                if(!empty($type_price)){
                    $data['type_price'] = $type_price;
                }
                $month_price = input('month_price','','trim,strip_tags');//机器人租金(只有商家 和销售 有值)
                if($month_price != ''){
                    $data['month_price'] = $month_price;
                }
                $data['remark'] = input('remark','','trim,strip_tags');//备注
            }else{
                $data['username'] = input('username','','trim,strip_tags');//子用户名称
                $data['role_id'] = input('role_id','','trim,strip_tags');//当前角色下的任意角色
                $data['mobile'] = input('mobile','','trim,strip_tags');//手机号
                $data['spare_mobile'] = input('spare_mobile','','trim,strip_tags');//备用手机
                $data['password'] = input('password','','trim,strip_tags');//密码
                $data['salt'] = rand_string(6);
                $data['password'] = md5($data['password'].$data['salt']);
                $data['money'] = input('money','','trim,strip_tags');//账户充值金额
                $data['is_jizhang']=input('jizhang','','trim,strip_tags'); //是否记账
                if(!$data['money']){
                    $data['money'] =0.00;
                }
                $data['robot_cnt'] = input('robot_cnt',0,'trim,strip_tags');//机器人数量
                $data['usable_robot_cnt'] = input('robot_cnt',0,'trim,strip_tags');//机器人数量
                if($data['money']<0){
                    //充值金额不能为负数
                    return returnAjax(1,'添加账号失败，充值金额不能为负数');
                }
                if($data['robot_cnt']<0){
                    //机器人数量不能为负数
                    return returnAjax(1,'添加账号失败，机器人数量不能为负数');
                }
                if(!$data['robot_cnt']){
                    $data['robot_cnt'] = 0;
                    $data['usable_robot_cnt'] = 0;
                }
                $data['robot_date'] = strtotime(input('robot_date','0','trim,strip_tags')); //到期日期
                $data['type_price'] = input('price_type','1','trim,strip_tags');
                $data['month_price'] = input('month_price','','trim,strip_tags');//机器人租金(只有商家 和销售 有值)
                $data['technology_service_price'] = input('service_price','','trim,strip_tags');//技术服务费

                $line_id = input('line','','trim,strip_tags');//线路id
                $line_name = Db::name('tel_line_group')->where('id',$line_id)->value('name');//线路价格
                $line_price = input('line_price','','trim,strip_tags');//线路价格
                if($user_auth['role'] == '商家'){
                  $line_price = Db::name('tel_line_group')->where('id', $line_id)->value('sales_price');
                }
                $line_data=array(
                    'name'=>$line_name,
                    'sales_price'=>$line_price,
                    'line_group_pid'=>$line_id,
                    'status'=>1,
                    'remark'=>'账号添加',
                    'create_time'=>time()

                );
                $asr_id = input('asr','','trim,strip_tags'); //ASR

                $message_id = input('message','','trim,strip_tags'); //短信
                if($message_id){
                    $message_data = Db::name('sms_channel')
                        ->field('name,type,url,user_id,access_secret,count,password,is_default,pid,relation_member_id,enterprise_id')
                        ->where('id',$message_id)
                        ->find();
                    if($message_data['pid'] == 0){
                        $message_data['relation_member_id'] = $uid;
                    }
                    $sms_price = input('sms_price','','trim,strip_tags'); //短信价格
                    if($user_auth['role'] == '商家'){
                        $sms_price = Db::name('sms_channel')
                            ->where('id', $message_id)
                            ->value('price');
                    }
                    $message_data['pid'] = $message_id ;//短信父ID
                    $message_data['price'] = $sms_price;
                    $message_data['status'] = 1 ;
                    $message_data['remarks'] = '账号添加';
                    $message_data['create_time'] = time();
                }


                $data['credit_line'] = input('credit_line','','trim,trim,strip_tags');
                if($data['credit_line'] == ''){
                    $data['credit_line'] = 0 ;
                }
                $data['remark'] = input('remark','','trim,strip_tags');//备注
            }
            $data['is_scenarios'] = input('is_scenarios','','trim,strip_tags');
            $data['is_verification'] =input('is_verification','','trim,strip_tags');
            $data['is_backup'] =input('is_backup','','trim,strip_tags');
            //前置定义
            $data['pid'] = $uid;
            $data['create_time']  = time();
            //生成记录
            $object['owner'] = $uid;
            $object['operation_type'] = 5 ;
            $object['operation_date'] = time();
            $object['remark'] = $data['remark'];
            if($id){
                $object['operation_fu'] = '编辑账号';
                $where['id'] = array('eq',$id);
                $object['user_id'] = $id;
                $object['record_content'] = '修改账号'.$data['username'];
                $ins= Db::name('admin')->where('username',$data['username'])->count();
                if($ins > 1){
                    return returnAjax(1,'修改账号失败,用户名是唯一的');
                }else{
                    Db::startTrans();
                    try {
                        $OperationRecord = new OperationRecord();
                        $up_admin_info = Db::name('admin')->where('id',$id)->find();
                        if($up_admin_info['role_id'] == 18){
                            $new_data = [];
                            if($data['is_scenarios'] && $data['is_verification'] && $data['is_backup'] ){
                                $new_data = [
                                  'is_verification' =>  $data['is_verification'],
                                  'is_scenarios'  =>  $data['is_scenarios'],
                                  'is_backup'=>$data['is_backup']
                                ];
                            }
                            if($data['is_scenarios']){
                              $new_data = ['is_scenarios'=>$data['is_scenarios']];
                            }
                            if($data['is_verification']){
                                $new_data = ['is_verification'=>$data['is_verification']];
                            }
                            if($data['is_backup']){
                                $new_data = ['is_backup'=>$data['is_backup']];
                            }
                            if(count($new_data) > 0){
                              //操作记录 -------------------------------------------------------------------------------------------------------
                              $find_users = Db::name('admin')->where('pid', $id)->field('id, username, is_verification, is_scenarios,is_backup')->select();

                              Db::name('admin')->where('pid', $id)->update($new_data);


                              foreach($find_users as $key=>$value){
                                // $record_content = $OperationRecord->get_operation_content('update_user', $value, $new_data);
                                $OperationRecord->insert_user('update_user', $user_auth['uid'], $value['id'], '编辑账号', $value, $new_data);
                              }
                              //操作记录 -------------------------------------------------------------------------------------------------------
                            }
                        }
                        //操作记录 -------------------------------------------------------------------------------------------------------
                        $old_data = Db::name('admin')->where('id', $id)->find();

                        Db::name('admin')->where($where)->update($data);
                        if(isset($data['password']) == true){
                          $OperationRecord->insert_user('reset_user_password', $user_auth['uid'], $id, '重置用户密码', $old_data, []);
                        }


                        // $record_content = $OperationRecord->get_operation_content('update_user', $old_data, $data);

                        $OperationRecord->insert_user('update_user', $user_auth['uid'], $id, '编辑账号', $old_data, $data);
                        //操作记录 -------------------------------------------------------------------------------------------------------
                        // Db::name('operation_record')->insert($object);
                        // 提交事务
                        Db::commit();
                        return returnAjax(0,'修改账号成功');
                    }
                    catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        return returnAjax(1,'修改账号失败');
                    }
                }
            }else{
                $object['operation_fu'] = '添加账号';
                $object['record_content'] = '添加账号：'.$data['username'].'。充值金额：'.$data['money'];
                $ins= Db::name('admin')->where('username',$data['username'])->count();
                if($ins > 0){
                    return returnAjax(1,'添加账号失败,用户名是唯一的');
                }else{
                    $recharge['recharge_member_id'] = $uid;
                    $recharge['menoy'] = $data['money'];
                    $recharge['status'] = 1;
                    $recharge['create_time'] = time();
                    $recharge['defore_balance'] = 0;
                    $recharge['balance'] = intval($data['money']) + 0;
                    $recharge['remak'] = '账户添加初始记录';
                    Db::startTrans();
                    try {
                        $admin_is_info  = Db::name('admin')->where('id',$uid)->find();
                        if($admin_is_info['is_scenarios'] == 1){
                            $data['is_scenarios'] = 1;
                        }
                        if($admin_is_info['is_verification'] == 1){
                            $data['is_verification'] = 1;
                        }
                        if($admin_is_info['is_backup'] == 1){
                            $data['is_backup'] = 1;
                        }
                        $get_id = Db::name('admin')->insertGetId($data);

                        //操作记录 -----------------------------------------------------------
                        $OperationRecord = new OperationRecord();
                        //获取操作内容
                        // $record_content = $OperationRecord->get_operation_content('add_user', [], $data);
                        //写入操作记录
                        $OperationRecord->insert_user('add_user', $user_auth['uid'], $user_auth['uid'], '添加账号', [], $data);
                        //操作记录 -----------------------------------------------------------
                        $object['user_id'] = $get_id;
                        $recharge['owner'] = $get_id;
                        $line_data['user_id'] = $get_id;
                        $asr_data['owner'] = $get_id;
                        $message_data['owner'] = $get_id;
                        // Db::name('operation_record')->insert($object);//操作记录
                        Db::name('tel_deposit')->insert($recharge);////充值操作记录
                        if($line_id){Db::name('tel_line_group')->insert($line_data);}//线路
                        if($asr_id){
                            if($user_auth['role'] == '商家'){
                                $asr_sale_price = Db::name('tel_interface')->where('id', $asr_id)->value('sale_price'); //ASR价格
                            }else{
                                $asr_sale_price = input('asr_price','','trim,strip_tags'); //ASR价格
                            }
                            $AsrData = new AsrData();
                            $AsrData->create_distribution_asr($get_id, $asr_id, $asr_sale_price);
                            // Db::name('tel_interface')->insert($asr_data);
                        }//ASR
                        if($message_id){Db::name('sms_channel')->insert($message_data);}//短信
                        Db::name('recharge')->insert($recharge);//充值
                        //扣除当前用户机器人数量
                        $pid_userinfo = Db::name('admin')->where('id',$uid)->find();//查询当前登录用户
                        if($role_id != 12){
                            //usable_robot_cnt
                            $usable_robot_cnt  = $pid_userinfo['usable_robot_cnt'] - $yunxing;
                            $w_rot_rnumber = $usable_robot_cnt - $data['robot_cnt']; //当前可用机器人数量 = 历史可用数量-分配数量;
                            if($data['robot_cnt'] > $usable_robot_cnt){
                                return returnAjax(1,'分配机器人数量不能大于您当前可用数量');
                            }else{
                                Db::name('admin')->where('id',$uid)->update(['usable_robot_cnt' =>$w_rot_rnumber]);//修改当前用户可用数量
                            }
                        }
                        //扣除充值人金额
                        if($role_id == 18){
                            $pid_money = $pid_userinfo['money'] - $data['money'] ;
                            Db::name('admin')->where('id',$uid)->update(['money' => $pid_money]);javascript:;
                        }
                        // 提交事务
                        Db::commit();
                        if(session('check_type') == '管理员' && input('yon') == 1){
                            //如果添加成功,调用搜客宝创建帐号curl
                            $tcName = input('tc_name','','trim,strip_tags');
                            if(empty($tcName)){
                                $tcName = '测试套餐';
                            }
                            file_put_contents("signxml.txt", print_r($get_id,true)."\r\n",8);
                            $curlArr = [
                                'uid' => 'AISKB'.$get_id,
                                // 'parent_user' => '',
                                'username' => $data['username'],
                                'role' => 'mainaccount',
                                'company_name' => 'AI智能语音公司',
                                'email' => '',
                                'mobile' => $data['mobile'],
                                'expiry_time' => $data['robot_date']*1000,
                                'notifyCallback' => 'http:xxxx',
                                'integrationCallback' => 'http:xxxx',
                                'meals' => [
                                    ['type' => $tcName],
                                ],
                                'extra' => [
                                    'enableCall' => true,
                                    'extraSubCount' => 0,
                                    'extraViewCount' => 0,
                                    'enableTask' => true,
                                    'taskQuota' => 100,
                                ],
                                "integrationCallback"=> "http:xxxx",
                            ];
                            $jsonArr = json_encode($curlArr);
                            $curlRes = json_decode($this->curl_openAccount($jsonArr));
                            // dump($res);dump($res->success);
                            if($curlRes->success){
                                Db::name('admin')->where('id',$get_id)->update(['skb_status'=>1]);
                                return returnAjax(0,'添加账号成功');
                            }else{
                                return returnAjax(1,$curlRes->message);
                            }
                        }else{
                            return returnAjax(0,'添加账号成功');
                        }
                    }
                    catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        return returnAjax(1,'添加账号失败');
                    }
                }
            }
        }
    }
    
    //创建帐号时调用搜客宝curl
    public function curl_openAccount($arrJson){
        $timestamp = substr(array_sum(explode(' ', microtime()))*1000,0,13);
        $key = config("skb_key");
        $secret = config("skb_secret");
        $pin = createSign($timestamp,$secret);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://sk.yunxiongnet.com/services/v3/rest/user/openAccount",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            // CURLOPT_POSTFIELDS =>"{\r\n\t\"uid\": \"2\",\r\n\t\"username\": \"用户1\",\r\n\t\"role\": \"mainaccount\",\r\n\t\"company_name\": \"xx公司\",\r\n\t\"email\": \"55664@qq.com\",\r\n\t\"mobile\": \"18811223345\",\r\n\t\"expiry_time\": 1655497185391,\r\n\t\"notifyCallback\": \"http:xxxx\",\r\n\t\"integrationCallback\": \"http:xxxx\",\r\n\t\"meals\": [\r\n\t\t{\r\n\t\t\"type\": \"测试套餐\"\r\n\t\t}\r\n\t],\r\n\t\"extra\": {\r\n\t\t\"enableCall\": true,\r\n\t\t\"extraSubCount\": 0,\r\n\t\t\"extraViewCount\": 0,\r\n\t\t\"enableTask\": true,\r\n\t\t\"taskQuota\": 100\r\n\t},\r\n\t\"plugin\": [\"pluginid_xxx\"],\r\n\t\"integrationCallback\": \"http:xxxx\"\r\n}",
            CURLOPT_POSTFIELDS => $arrJson,
            CURLOPT_HTTPHEADER => array(
                "X-AK-KEY: {$key}",
                "X-AK-PIN: {$pin}",
                "X-AK-TS: {$timestamp}",
                "Content-Type: application/json"
            ),
        ));
        // dump($curl);
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        // dump($res);
        return $response;
    }

    //销售账号管理
    //销售账号管理列表展示
    public function ajax_sale_account(){
        $user_auth = session('user_auth');
        $uid = $user_auth['uid']; //当前用户ID
        $info = Db::name('admin')->where('id',$uid)->find();
        $this->assign('info',$info);
        $role_id = $info['role_id'];
        $level = Db::name('admin_role')->where('id',$role_id)->value('level');
        $where_role['level'] = [array('>',$level),array('neq',5)];
        $where_role['status'] = array('eq',1);
        // if($role_id == 12 ){
        // 	$where_role['id'] = array('eq',16);
        // }
        $role_list = Db::name('admin_role')->where($where_role)->select();
        $this->assign('role_list',$role_list);
        $this->assign('role_id',$role_id);
        if(request()->isPost()){
            $where = [];
            $sale_name = input('name','','trim,strip_tags');
            if($sale_name){
                //eq = 等于
                $where['username'] = array('like', "%".$sale_name."%");
            }
            $role_type = input('role_type','','trim,strip_tags');
            if($role_type){
                $where['role_id'] = array(array('eq',$role_type),array('neq',20));
            }else{
                $where['role_id'] = array('neq',20);
            }
            $page_size = input('page_size','','trim,strip_tags');
            $page = input('page','','trim,strip_tags');
            if(!$page_size)
                $page_size = 10 ;
            if(!$page)
                $page = 1;
            $where['pid'] = array('eq',$uid);
            $where['status'] = array('neq',-1);

            $count = Db::name('admin')->where($where)->count();
            $page_count = ceil($count/$page_size);

            if($page > $page_count){
              $page = $page_count - 1;
            }

            $list = Db::name('admin')->where($where)->page($page,$page_size)->order('id','desc')->select();
            foreach($list as $key => $value){
                $list[$key]['role_name'] = Db::name('admin_role')->where('id',$value['role_id'])->value('name');
                if($value['status'] == 1){
                    $list[$key]['status'] = '开启';
                }else if($value['status'] == 0){
                    $list[$key]['status'] = '关闭';
                }
                $list[$key]['asr_name'] = Db::name('tel_interface')->limit(1)->where(['owner' => $value['id'], 'pid' => ['<>', 0]])->order('create_time','desc')->value('name');
                if(!$list[$key]['asr_name']){
                    $list[$key]['asr_name'] = "<a style='cursor:pointer' href='/user/asr/list?action=distribution&user_id=$value[id]'>去分配</a>";
                }
                $list[$key]['line_name'] = Db::name('tel_line_group')->limit(1)->where(['user_id' => $value['id'], 'line_group_pid' => ['<>', 0]])->order('create_time','desc')->value('name');
                if(!$list[$key]['line_name']){
                    $list[$key]['line_name'] = "<a style='cursor:pointer' href='/user/manager/line_management?action=distribution&user_id=$value[id] '>去分配</a>";
                }
                $list[$key]['sms_name'] = Db::name('sms_channel')->limit(1)->where(['owner' => $value['id'], 'pid' => ['<>', 0]])->order('create_time','desc')->value('name');
                if(!$list[$key]['sms_name']){
                    $list[$key]['sms_name'] = "<a style='cursor:pointer' href='/user/sms/channel?action=distribution&user_id=$value[id] '>去分配</a>";
                }
            }

            $data = array();
            $data['list'] = $list; //数据
            $data['total'] = $count; //总条数
            $data['page'] = $page_count; //总页数
            $data['Nowpage'] = $page; //当前页数
            return returnAjax(0,'获取数据成功',$data);
        }
    }

    //账号软删除
    public function soft_deletion(){
        if(request()->isPost()){
            $user_auth = session('user_auth');
            $uid = $user_auth['uid']; //当前用户ID
            $id =input('id','','trim,strip_tags');
            $pid_roleid = input('pid_role_id','','trim,strip_tags');
            $info = Db::name('admin')->where('id',$id)->find();
            $robot_cnt = $info['robot_cnt'];  //销售购买机器人数量
            $money = $info['money']; //销售余额
            $data = [];
            $pid_info = Db::name('admin')->where('id',$uid)->find();
            $data['robot_cnt'] = $pid_info['robot_cnt'] + $robot_cnt;
            $data['usable_robot_cnt'] = $pid_info['usable_robot_cnt'] + $robot_cnt;
            if($pid_roleid == 18){
                $data['money'] = $pid_info['money'] + $money;
            }

            //充值数据
            $r_data['owner'] = $id;
            $r_data['recharge_member_id'] = $uid;
            $r_data['menoy'] = 0 - $money;
            $r_data['status'] = 1;
            $r_data['create_time'] = time();
            $r_data['deposit_type'] = 0;
            $r_data['defore_balance'] = $money;
            $r_data['balance'] = 0;
            $r_data['remak'] = '管理员PS：该账号已删除';
            //操作记录数据
            $o_data = [];
            $o_data['owner']= $uid;
            $o_data['user_id'] = $id;
            $o_data['operation_type'] = 5 ;
            $o_data['operation_fu'] =  '删除账号';
            $o_data['record_content'] = '回收机器人：'.$robot_cnt.',回收金额：'.$money;
            $o_data['operation_date'] = time();
            $o_data['remark'] = '删除账号';
            // 启动事务
            Db::startTrans();
            try {
                $res  = Db::name('operation_record')->insert($o_data);
                if($res){
                    Db::name('admin')->where('id',$id)->update(['robot_cnt'=> 0 ,'money'=> 0,'status'=> -1 ,'usable_robot_cnt' => 0]);
                    Db::name('admin')->where('id',$uid)->update($data);
                    Db::name('tel_deposit')->insert($r_data);
                }
                // 提交事务
                Db::commit();
                return returnAjax(0,'删除成功');
            }
            catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return returnAjax(1,'删除失败');
            }
        }
    }
    //账号开启与锁定
    public function open_close_state(){
        $type = input('type','','trim,strip_tags'); //全选状态
        $alt = input('alt','','trim,strip_tags'); //0开启 1关闭
        $arr = input('vals','','trim,strip_tags'); //复选值
        $arr = explode(',',$arr);
        $user_name = input('keyword','','trim,strip_tags');//筛选条件
        $role_type = input('role_type','','trim,strip_tags');
        $open['status'] = 1;
        $close['status'] = 0;
        $user_auth = session('user_auth');
        $uid = $user_auth["uid"];
        $object = [];
        $where['pid'] = array('eq',$uid);//查询当前角色下的子角色
        if($user_name){
            $where['username'] = array('like', "%".$user_name."%");
        }
        $role_type = input('role_type','','trim,strip_tags');
        if($role_type){
            $where['role_id'] = array('eq',$role_type);
        }
        $OperationRecord = new OperationRecord();

        $object['owner'] = $uid;
        $object['operation_type'] = 9;
        if($alt == 0){//开启账号
            $object['operation_fu'] = '开启账号';
            $object['operation_date'] = time();
            $object['remark'] = '成功开启用户，包括该用户的所有子用户';
            $arr_ids = [];
            if($type == 1){
                $list_ids = Db::name('admin')->where($where)->column('id');
                $arr_ids = $list_ids;
            }else{
                $arr_ids = array_filter($arr);
            }
            Db::startTrans();
            try {
                foreach($arr_ids as $key => $vo){
                    $object['user_id'] = $vo;
                    // $admin_name = Db::name('admin')->where('id',$vo)->value('username');
                    $old_data = Db::name('admin')->where('id', $vo)->field('id, username, status')->find();
                    // $object['record_content'] = '开启账号：'.$admin_name;

                    $OperationRecord->insert_user('update_user_status', $user_auth['uid'], $vo, '开启/锁定账号', $old_data, $open);
                    // DB::name('operation_record')->insert($object);
                }
                $array_ids = $this->get_open_ids($arr_ids);
                Db::name('admin')->where('id','in',$array_ids)->update($open);
                // 提交事务
                Db::commit();
                return returnAjax(0,'开启账号成功');
            }
            catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return returnAjax(1,'开启账号失败');
            }
        }else if($alt == 1){
            $object['operation_fu'] = '锁定账号';
            $object['operation_date'] = time();
            $object['remark'] = '成功锁定用户，包括该用户的所有子用户';
            $arr_ids = [];
            if($type == 1){
                $list_ids = Db::name('admin')->where($where)->column('id');
                $arr_ids = $list_ids;
            }else{
                $arr_ids = array_filter($arr);
            }
            Db::startTrans();
            try {
                foreach($arr_ids as $key => $vo){
                    $object['user_id'] = $vo;
                    // $admin_name = Db::name('admin')->where('id',$vo)->value('username');
                    // $object['record_content'] = '锁定账号：'.$admin_name;
                    $old_data = Db::name('admin')->where('id', $vo)->field('id, username, status')->find();

                    $OperationRecord->insert_user('update_user_status', $user_auth['uid'], $vo, '开启/锁定账号', $old_data, $close);
                    // DB::name('operation_record')->insert($object);
                }
                $array_ids = $this->get_open_ids($arr_ids);
                Db::name('admin')->where('id','in',$array_ids)->update($close);
                // 提交事务
                Db::commit();
                return returnAjax(0,'锁定账号成功');
            }
            catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return returnAjax(1,'锁定账号失败');
            }
        }
    }

    private function get_open_ids($arr_ids){
        $array_ids =[];
        $where['id']  = array('in',$arr_ids);
        $admin_array = Db::name('admin')->where($where)->column('id');
        do{
            $ids = [];
            foreach($admin_array as $k => $v ){
                $ids[] = $v;
            }
            $array_ids = array_merge($array_ids,$ids);
            $where_k['pid'] = array('in',$ids);
            $admin_array = DB::name('admin')->where($where_k)->column('id');
        }
        while(count($admin_array) > 0);
        return $array_ids;
    }

    //充值管理
    //充值列表展示
    public function ajax_sale_account_recharge(){
        $user_auth = session('user_auth');
        $uid = $user_auth['uid']; //当前用户ID
        $role_id = Db::name('admin')->where('id',$uid)->value('role_id');
        $level = Db::name('admin_role')->where('id',$role_id)->value('level');
        $where_role['level'] = [array('>=',$level),array('neq',5)];
        $where_role['status'] = array('eq',1);
        if($role_id == 12 ){
            $where_role['id'] = array('eq',16);
        }
        $role_list = Db::name('admin_role')->where($where_role)->order('id','desc')->select();
        foreach($role_list as $key=>$vo){
            if($key == 0){
                $role_chek = $vo['source_id'];
                $role_list[$key]['chek'] = 1;
            }else{
                $role_list[$key]['chek'] = 0;
            }
        }

        $this->assign('role_lsit',$role_list);//标题列表展示
        //-------------------

        if(request()->isPost()){
            $user_name = input('username','','trim,strip_tags');//条件  名称
            $start_date = input('startshow','','trim,strip_tags');//条件 开始时间
            $end_date = input('endshow','','trim,strip_tags');//条件 结束时间
            $end_date = input('endshow','','trim,strip_tags');//条件 结束时间
            $page = input('page','','trim,strip_tags');
            $page_size = input('limit','','trim,strip_tags');

            if(!$page_size){
                $page_size = 10;
            }
            if(!$page){
                $page = 1;
            }
            $role_type = input('role_type','','trim,trim,strip_tags');
            if(!$role_type){
                $role_type = $role_chek;
            }

            $where = array();
            $where_a = array();
            $where_z = array();

            if($user_name){
                $where['ad.username'] = array('like',"%".$user_name."%");
            }
            // 	$a_id =  Db::name('admin')->where($where_a)->column('id');
            // 	if($a_id){
            // 		$where['td.owner'] =array('in',$a_id);
            // 	}
            $where_a['role_id'] = $role_type;
            if($start_date !='' && $end_date !=''){
                // $where['td.create_time'] = array(array('>=',strtotime($start_date)),array('<=',strtotime($end_date)),'and');
                $where['td.create_time'] = [
                    [['>=', strtotime($start_date)], ['<=' , strtotime($end_date)]], 'and'
                ];
            }


            $where['ad.role_id'] = array('=',$role_type);
            if($role_type == $role_id){
                $where['td.owner'] = array('=',$uid);
            }else{
                $where['td.recharge_member_id'] = array('=',$uid);
                //获取当前用户的子ID
                $find_ids = Db::name('admin')->where('pid', $uid)->field('id')->select();
                $new_find_ids = [];
                foreach($find_ids as $find_key => $find_value){
                    $new_find_ids[] = $find_value['id'];
                }
                $where['td.owner'] = ['in', $new_find_ids];
            }
            $where['ad.status'] = ['<>', -1];
            $where_sql = [];
            foreach($where as $key=>$value){
                if(is_array($value[0]) == true){
                    $find_where_sql = '';
                    foreach($value[0] as $find_key=>$find_value){
                        if($find_key != 0){
                            $find_where_sql .= ' '.$value[1].' ';
                        }
                        $find_where_sql .= $key . ' ' . $find_value[0] . ' ' . $find_value[1];
                    }
                    $where_sql[] = $find_where_sql;
                }else{
                    if($value[0] == 'in'){
                        $where_sql[] = $key . ' in(' . implode(',', $value[1]) . ')';
                    }else{
                        $where_sql[] = $key . ' ' . $value[0] . ' "' . $value[1] . '"';
                    }
                }
            }
            $where_sql = implode(' and ', $where_sql);
            if(!empty($where_sql)){
                $where_sql = ' WHERE ' . $where_sql;
            }
            $start = ($page - 1) * $page_size;
            // 	owner
            $sql = '
			  SELECT
			    a.*
			  FROM
			    (
			      SELECT
			        td.*,ad.username,ad.role_id,td.owner as member_id
			      FROM
			        rk_tel_deposit as td
			      LEFT JOIN
			        rk_admin as ad
			      ON
			        ad.id = td.owner
			      '.$where_sql.'
			      ORDER BY td.create_time desc
			    )
			    a
			   LIMIT '.$start.','.$page_size.'
			';
            /*$sql = '
			  SELECT
			    a.*
			  FROM
			    (
			      SELECT
			        td.*,ad.username,ad.role_id,td.owner as member_id
			      FROM
			        rk_tel_deposit as td
			      LEFT JOIN
			        rk_admin as ad
			      ON
			        ad.id = td.owner
			      '.$where_sql.'
			      ORDER BY td.create_time desc
			    )
			    a
			   group by a.owner
			   LIMIT '.$start.','.$page_size.'
			';*/
            $count_sql = '
			 SELECT
			    count(a.owner) as count
			  FROM
			    (
			      SELECT
			        td.owner
			      FROM
  			      rk_admin as ad

			      LEFT JOIN
			        rk_tel_deposit as td
			      ON
			        ad.id = td.owner
			      '.$where_sql.'
			      group by td.owner
			    )
			    a
			';
            if($role_type == $role_id){
                $list = Db::connect(config('master_db'))->table('rk_tel_deposit')
                    ->alias('td')
                    ->join('admin ad','ad.id = td.owner','LEFT')
                    ->field('td.*,ad.username,ad.role_id,td.owner as member_id')
                    ->where($where)
                    ->page($page,$page_size)
                    ->order('id','desc')
                    ->select();
                $count = Db::connect(config('master_db'))->table('rk_tel_deposit')
                    ->where('owner',$uid)
                    ->count();
            }else{
                $list = Db::query($sql);
                $count = Db::query($count_sql);
                $count = $count[0]['count'];
            }
            foreach($list as $key => $vo){
                $list[$key]['recharge_member_id']= Db::name('admin')->where('id',$vo['recharge_member_id'])->value('username');
            }

            $page_count = ceil($count/$page_size);
            $data = array();
            $data['count'] = $count;
            $data['list'] = $list; //数据
            $data['total'] = $count; //总条数
            $data['page'] = $page_count; //总页数
            $data['Nowpage'] = $page; //当前页数
            $data['role_type'] = $role_type;
            $data['role_id'] = $role_id;
            return returnAjax(1,'获取数据成功',$data);
        }
    }
    //子集账户充值
    public function sale_record(){

        if(request()->isGet()){
            $user_auth = session('user_auth');
            $uid = $user_auth['uid'];//当前用户ID
            $user_info = Db::name('admin')->where('id',$uid)->find();
            $role_id = $user_info['role_id'];
            $id = input('id','','trim,strip_tags');
            // 	$info = Db::name('recharge')->where('id', $uid)->find();
            $admin = Db::name('admin')->where('id',$id)->find();
            $admin_role = Db::name('admin_role')->where('source_id',$admin['role_id'])->find();
            $info['name'] = $admin['username'];
            $info['role_name'] = $admin_role['name'];
            $info['pid_role_id'] = $role_id;
            $info['balance'] =$admin['money'];
            $info['owner'] = $id;
            //上级可充值金额
            if($role_id != 18){
                $info['pid_money'] = '-1';
            }else{
                $info['pid_money'] = Db::name('admin')->where('id',$uid)->value('money');
            }
            return returnAjax(1,'获取数据成功',$info);
        }else
            if(request()->isPost()){
                $data = [];
                $user_auth = session('user_auth');
                $uid = $user_auth['uid'];//当前用户ID
                $pid_userinfo = Db::name('admin')->where('id',$uid)->find();
                $pid_roleid = $pid_userinfo['role_id'];  //父级角色ID
                $user_id= input('user_id','','trim,strip_tags');//充值ID；
                $find_ids = Db::name('admin')->where('pid', $uid)->field('id')->select();
                $new_find_ids = [];
                foreach($find_ids as $key=>$value){
                    $new_find_ids[] = $value['id'];
                }
                if(in_array($user_id, $new_find_ids) == false){
                    return returnAjax(1, '充值失败，该用户不属于当前用户的子账户');
                }


                // $data['owner'] = $user_id;
                // $data['recharge_member_id'] = $uid; //充值ID
                $data['menoy'] = floatval(input('menoy','','trim,strip_tags')); //充值金额
                $current_money = $data['menoy'];
                $data['defore_balance'] = Db::name('admin')->where('id',$user_id)->value('money');//充值前金额
                $data['balance'] = $data['defore_balance'] + $data['menoy'];//充值后金额 = 充值金额 + 充值前金额（数据库充值后金额）
                $data['create_time'] = time(); //充值时间
                $data['remak'] = input('remak','','trim,strip_tags');//备注
                //获取当前用户的余额
                $current_user_money = Db::name('admin')->where('id', $uid)->value('money');
                //生成充值记录

                if((0 - $data['menoy']) > 0 ){
                    $rut['operation_fu'] = '扣除金额'; //大于等于0  扣除
                }else{
                    $rut['operation_fu'] = '充值金额';
                }
                if($pid_roleid == 18 && ($current_user_money - $data['menoy']) < 0) {
                    return returnAjax(1,'余额不足，充值失败');//商户进行充值余额控制
                }



                $s_menoy = 0 ;
                if($pid_roleid != 12){
                    $s_menoy = $current_user_money - $data['menoy'];
                }
                $z_menoy = $data['defore_balance'] + $data['menoy'];
                $rut['owner'] = $uid ;
                $rut['user_id'] = $user_id;
                $rut['operation_type'] = 1 ;
                if($data['menoy']>0){
                  $rut['record_content'] ='充值金额:'. $data['menoy'].'元';
                }else{
                  $rut['record_content'] ='扣除金额:'. (0-$data['menoy']).'元';
                }
                $rut['operation_date'] = time();
                if($data['remak']){
                    $rut['remark'] = $data['remak'];
                }
                // 启动事务
                Db::startTrans();
                try {
                    $res = Db::name('operation_record')->insert($rut);//生成记录
                    if($res> 0 ){
                        Db::name('recharge')->where('owner',$user_id)->update($data);//修改信息
                        $data['owner'] = $user_id;
                        $data['recharge_member_id'] = $uid;
                        $data['type'] = 1;
                        $data['status'] = 1;
                        Db::name('tel_deposit')->insert($data);//历史记录保存
                        //如果操作人员是商家的话
                        if($pid_roleid == 18){
                            //充值
                            if($current_money > 0){
                                //要扣除当前用户余额 整数
                                Db::name('admin')->where('id',$uid)->setDec('money', $current_money);//扣除金额
                                //扣除
                            }else{
                                //要增加当前用户余额 负数
                                Db::name('admin')->where('id',$uid)->setInc('money', (0 - $current_money));//充值金额
                            }
                        }
                        //充值
                        if($current_money > 0){
                            //增加被充值人员的余额
                            Db::name('admin')->where('id',$user_id)->setInc('money', $current_money);//充值用户充值金额
                            //扣除
                        }else{
                            //扣除被充值人员的余额
                            Db::name('admin')->where('id',$user_id)->setDec('money', (0 - $current_money));//扣除金额
                        }
                    }
                    // 提交事务
                    Db::commit();
                    return returnAjax(0,'充值成功');
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return returnAjax(1,'充值失败');
                }
            }

    }
    //机器人管理
    //机器人管理列表
    public function ajax_robot_management(){
        $user_auth = session('user_auth');
        $uid = $user_auth['uid']; //当前用户ID
        $info = Db::name('admin')->where('id',$uid)->find();
        $role_id = $info['role_id'];
        $level = Db::name('admin_role')->where('id',$role_id)->value('level');
        $where_role['level'] = [array('>',$level),array('neq',5)];
        $where_role['status'] = array('eq',1);
        if($role_id == 12 ){
            $where_role['id'] = array('eq',16);
        }
        $role_list = Db::name('admin_role')->where($where_role)->select();
        $this->assign('role_list',$role_list);
        $this->assign('role_id',$role_id);

        $user_name = input('username','','trim,strip_tags');
        if($user_name){
            $where['username'] = array('like',"%".$user_name."%");
        }
        $role_name = input('role_name','','trim,strip_tags');
        if($role_name){
            $where['role_id'] =array(array('eq',$role_name),array('neq',20));
        }else{
            $where['role_id'] = array('neq',20);
        }
        $where['pid'] = array('eq',$uid);
        $page = input('page','','trim,strip_tags');
        $page_size = input('limit','','trim,strip_tags');
        $where['status'] = array('neq',-1);
        if(!$page){
            $page = 1;
        }
        if(!$page_size){
            $page_size = 10;
        }
        $list = Db::name('admin')->where($where)->page($page,$page_size)->order('create_time','desc')->select();
        foreach ($list as $key => $value) {
            $list[$key]['pid_money'] = Db::name('admin')->where('id',$uid)->value('month_price');
            $where_a['user_id'] = array('eq',$value['id']);
            $where_a['operation_type'] = array('eq',2);
            $list[$key]['cinert'] = Db::name('operation_record')->limit(1)->where($where_a)->order('operation_date','desc')->column('remark');
            if(!$list[$key]['cinert']){
                $where_a['operation_type'] = array('eq',5);
                $list[$key]['cinert'] = Db::name('operation_record')->limit(1)->where($where_a)->order('operation_date','desc')->column('remark');
            }
            $list[$key]['role_name'] = Db::name('admin_role')->where('source_id',$value['role_id'])->value('name');
        }
        $count = Db::name('admin')->where($where)->count();
        $page_count = ceil($count/$page_size);

        $data = array();
        $data['list'] = $list; //数据
        $data['total'] = $count; //总条数
        $data['page'] = $page_count; //总页数
        $data['Nowpage'] = $page; //当前页数
        return returnAjax(1,'获取数据成功',$data);
    }
    //分配机器人
    public function distribution_robot(){

		$user_auth = session('user_auth');
        $uid = $user_auth["uid"];
		//求出此账户 正在运行的机器人数量
        $yunxing = Db::name('tel_config')->where(['status'=>1,'member_id'=>$uid])->sum('robot_cnt');
        //编辑回填
        if(request()->isGet()){
            $id = input('id','','trim,strip_tags');
            //
            $info = Db::name('admin')->where('id',$id)->find();
            $info['role_name'] = Db::name('admin_role')->where('source_id',$info['role_id'])->value('name');
            //排除管理员 获取当前用户机器人数量
            $user_auth = session('user_auth');
            $uid = $user_auth['uid']; //当前用户ID
            $user_info = Db::name('admin')->where('id',$uid)->find();
            $role_id = $user_info['role_id'];//父级角色ID
            if($role_id != 12){
                $info['p_robot_num'] = $user_info['usable_robot_cnt'] - $yunxing;
                $info['p_robot_date'] = date("Y-m-d", $user_info['robot_date']);
            }else{
                $info['p_robot_num'] = -1;
                $info['p_robot_date'] = -1;
            }
            if($role_id == 17){
                $info['robot_cost'] = $user_info['month_price'];
            }
            if($info['robot_date'] == 0 || $info['robot_date'] < 50000){
                $info['robot_date'] = 0;
            }else{
                $info['robot_date'] = date("Y-m-d",$info['robot_date']);
            }
            $info['pid_role_id'] = $role_id;//当前登录角色等级ID
            $info['pid_role_name'] = Db::name('admin_role')->where('id',$user_info['role_id'])->value('name');//当前登录角色名称
            return returnAjax(1,'数据获取成功',$info);
        }
        //修改
        if(request()->isPost()){
            $data = [];
            $id = input('id','','trim,strip_tags');
            $user_auth = session('user_auth');
            $uid = $user_auth["uid"];
            $pid_userinfo= Db::name('admin')->where('id',$uid)->find();
            $info = DB::name('admin')->where('id',$id)->find();
            $pid_roleid = $pid_userinfo['role_id'];
            $role_id = $info['role_id'];
            $robot_num = floatval(input('robot_num','','trim,strip_tags'));
            $remark = input('remark','','trim,strip_tags');
            $type_price = input('type_price','','trim,strip_tags');
            $month_price =input('month_price','','trim,strip_tags');
            $robot_date = strtotime(input('robot_date','','trim,strip_tags'));
            //之前的到期时间
            $ago_robot_date= Db::name('admin')->where('id',$id)->value('robot_date');

            //之前的类型 1 日租 2月租
            $ago_type_price= Db::name('admin')->where('id',$id)->value('type_price');
            //之前的价格
			      $befor_month_price= Db::name('admin')->where('id',$id)->value('month_price');
            //生成分配记录
            if( (0 - $robot_num) > 0 ){
                $data['operation_fu'] = '回收机器人'; //大于等于0  扣除
            }else if((0 - $robot_num) <= 0) {
                $data['operation_fu'] = '分配机器人'; //小于等于0  充值
            }
            $data['owner'] = $uid;
            $data['user_id'] = $id;
            $data['operation_type'] = 2;
            $str='';
            if( $robot_num > 0 ){
               $str = '分配机器人数量:'.$robot_num.'个' ;
            }else{
               $str = '回收机器人数量:'.(0-$robot_num).'个' ;
            }
            if($robot_date!=$ago_robot_date){
               $str.='。修改到期时间：'.date('Y-m-d',$robot_date);
            }
            if($type_price!=$ago_type_price){
               $day_name1 = $type_price==1?'按天计费':'按月计费';
               $str.='。修改计费类型：'.$day_name1;
               $day_name= $type_price==1?'天':'月';
               if($month_price!=$befor_month_price){
                   $str.='。修改计费价格：'.$month_price.'元/'.$day_name.'/个';
                }
            }else{
               $day_name2= $ago_type_price==1?'天':'月';
               if($month_price!=$befor_month_price){
                   $str.='。修改计费价格：'.$month_price.'元/'.$day_name2.'/个';
                }
            }
            $str = trim($str,'。');
            $data['record_content'] =$str;
            $data['operation_date'] = time();
            $data['remark'] = $remark;
            //启动事务
            Db::startTrans();
            try{
                if(!empty($str)){
                   $res = Db::name('operation_record')->insert($data);//添加记录
                }
                    if($pid_roleid != 12){
                        $where['id'] = array('eq',$uid);
                        $usable_robot_cnt = Db::name('admin')->where($where)->value('usable_robot_cnt');
						$usable_robot_cnt  =  $usable_robot_cnt  -$yunxing;
						//查询当前登录用户
                        $w_rot_rnumber = $usable_robot_cnt - $robot_num; //当前可用机器人数量 = 历史可用数量-分配数量;
                        Db::name('admin')->where($where)->update(['usable_robot_cnt' =>$w_rot_rnumber]);//修改当前用户可用数量
                    }
                    //如果成功
                    //分配人机器人  总数数量+  可用数量+
                    $num['robot_cnt'] = $info['robot_cnt'] + $robot_num;
                    $num['usable_robot_cnt'] = $info['usable_robot_cnt'] + $robot_num;
                    $num['type_price'] = $type_price;
                    if($month_price != ''){
                        $num['month_price'] = $month_price;
                    }
                    $num['robot_date'] = $robot_date;
                    //增加理由 ，BUG反馈 在商家修改销售机器人数量的时候回出错
                    if(empty($num['type_price']))unset($num['type_price']);
                    if(empty($num['month_price']))unset($num['month_price']);
                    if($robot_date < $ago_robot_date){
                        $find_users = Db::name('admin')->where('pid', $id)->select();
                        while(count($find_users) > 0){ //循环
                            //清空
                            $ids = [];
                            foreach($find_users as $key=>$value){
                                $ids[] = $value['id'];
                                if($value['robot_date'] > $robot_date){
                                    Db::name('admin')->where('id',$value['id'])->update(['robot_date' => $robot_date]);
                                }
                            }
                            //在获取子用户
                            $find_users = Db::name('admin')->where('pid', 'in', $ids)->select();
                        }
                    }
                    if($role_id == 17){ //如果当前角色代理商 编辑商家机器人时间时  同时也编辑当前商家的的自角色时间
                        $find_users = Db::name('admin')->where('pid', $id)->select();
                        while(count($find_users) > 0){ //循环
                            $ids = [];
                            foreach($find_users as $key=>$value){
                                $ids[] = $value['id'];
                                $data = [
                                    'robot_date' => $robot_date,
                                    'type_price' => $type_price
                                ];
                                if($month_price != ''){
                                    $data['month_price'] = $month_price;
                                }
                                Db::name('admin')->where('id',$value['id'])->update($data);
                            }
                            //在获取子用户
                            $find_users = Db::name('admin')->where('pid', 'in', $ids)->select();
                        }
                    }
                    Db::name('admin')->where('id',$id)->update($num);
                    //获取被操作人的用户角色
                    $role_name = Db::name('admin')
                        ->alias('a')
                        ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                        ->where('a.id', $id)
                        ->value('ar.name');
                    if($role_name == '商家'){
                        $update_data = [
                            'month_price' => $month_price,
                            'robot_date'	=>	$robot_date,
                            'type_price'	=>	$type_price
                        ];
                        Db::name('admin')->where('pid', $id)->update($update_data);
                    }

                Db::commit();
                return returnAjax(0,'分配成功');
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return returnAjax(1,'分配失败');
            }
        }

    }
    //强制回收机器人
    public function force_recovery(){
        $id = input('id','','trim,strip_tags');
        $num['robot_cnt'] = 0;
        $num['usable_robot_cnt'] = 0;
        $find_users = Db::name('admin')->where('pid', $id)->select();
        // 启动事务
        Db::startTrans();
        try {
            while(count($find_users) > 0){ //循环清空机器人数量
                //清空
                $ids = [];
                foreach($find_users as $key=>$value){
                    $ids[] = $value['id'];
                    Db::name('admin')->where('id',$value['id'])->update($num);
                }
                //在获取子用户
                $find_users = Db::name('admin')->where('pid', 'in', $ids)->select();
            }
            $robot_cnt = Db::name('admin')->where('id',$id)->value('robot_cnt');
            $user_auth = session('user_auth');
            $uid = $user_auth['uid']; //当前用户ID
            $user_info = Db::name('admin')->where('id',$uid)->find();
            $role_id = $user_info['role_id'];
            if($role_id == 12){
                Db::name('admin')->where('id',$id)->update($num);//清空完成
            }else{
                Db::name('admin')->where('id',$id)->update($num);//清空完成
                Db::name('admin')->where('id',$uid)->setInc('usable_robot_cnt',$robot_cnt);//填补可用数量;
            }
            //生成强制回收记录
            $user_auth = session('user_auth');
            $uid = $user_auth["uid"];
            $data['owner'] = $uid;
            $data['user_id'] = $id;
            $data['operation_type'] = 6;
            $data['operation_fu'] = '回收机器人';
            $data['record_content'] = '强制回收机器人';
            $data['operation_date'] = time();
            $data['remark'] = '一键回收当前用户的下所有机器人。';
            Db::name('operation_record')->insert($data);
            // 提交事务
            Db::commit();
            return returnAjax(0,'强制回收成功');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return returnAjax(1,'强制回收失败');
        }
    }
    //资费管理
    //资费管理列表
    public function ajax_tariff_management(){
        $user_auth = session('user_auth');
        $uid = $user_auth['uid']; //当前用户ID
        $info = Db::name('admin')->where('id',$uid)->find();
        $role_id = $info['role_id'];
        $this->assign('role_id',$role_id);
        if(request()->isPost()){
            $user_name = input('username','','trim,strip_tags');
            if($user_name){
                $where['username'] = array('like','%'.$user_name.'%');
            }
            $page = input('page','','trim,strip_tags');
            if(!$page)
                $page = 1;
            $page_size = input('page_size','','trim,strip_tags');
            if(!$page_size)
                $page_size = 10;
            $user_auth = session('user_auth');
            $uid = $user_auth["uid"];
            $where['pid'] = array('eq',$uid);
            $where['role_id'] = array('neq',20);
            $where['status'] = array('neq',-1);
            $list = Db::name('admin')->where($where)->page($page,$page_size)->order('create_time','desc')->select();
            foreach ($list as $key => $value) {
                $where_a['user_id'] = array('eq',$value['id']);
                $where_a['operation_type'] = array('eq',7);
                $list[$key]['cinert'] = Db::name('operation_record')->limit(1)->where($where_a)->order('operation_date','desc')->column('remark');
                if(!$list[$key]['cinert']){
                    $where_a['operation_type'] = array('eq',5);
                    $list[$key]['cinert'] = Db::name('operation_record')->limit(1)->where($where_a)->order('operation_date','desc')->column('remark');
                };
                $list[$key]['count_line'] = Db::name('tel_line_group')->where('user_id',$value['id'])->count();
                $list[$key]['message_count'] = Db::name('sms_channel')->where('owner',$value['id'])->count();
                $list[$key]['asr_count'] = Db::name('tel_interface')->where('owner',$value['id'])->count();
                $list[$key]['role_name'] = Db::name('admin_role')->where('source_id',$value['role_id'])->value('name');
            }
            $count = Db::name('admin')->where($where)->count();
            $page_count = ceil($count/$page_size);

            $data = array();
            $data['list'] = $list; //数据
            $data['total'] = $count; //总条数
            $data['page'] = $page_count; //总页数
            $data['Nowpage'] = $page; //当前页数s
            return returnAjax(1,'获取数据成功',$data);
        }
    }
    //资费列表详情编辑
    public function edit_tariff_management(){
		
        $user_auth = session('user_auth');
        $uid = $user_auth["uid"];
        $p_info = Db::name('admin')->where('id',$uid)->find();
        $role_id = $p_info['role_id'];

        $operation_record = [];
        if(request()->isGet()){
            $id = input('id','','trim,strip_tags');
            $info = [];
            //机器人价格 + 服务费
            $robot_info['u'] = Db::name('admin')->where('id',$id)->field('type_price,month_price,technology_service_price')->find();//column('type_price','month_price');
            $robot_info['p'] = Db::name('admin')->where('id',$uid)->field('type_price,month_price,technology_service_price')->find();
            $info['robot'] = $robot_info;
            //ASR价格
            $asr_list = Db::name('tel_interface')->where('owner',$id)->select();
            if($asr_list){
                foreach($asr_list as $key => $value){
                    $asr_list[$key]['pid_sale_price'] = Db::name('tel_interface')->where('id',$value['pid'])->value('sale_price');
                }
            }
            $info['asr'] = $asr_list;
            //线路
            $line_list = Db::name('tel_line_group')->where('user_id',$id)->select();
            if($line_list){
                foreach($line_list as $key => $value1 ){
                    $line_list[$key]['pid_sale_price'] = Db::name('tel_line_group')->where('id',$value1['line_group_pid'])->value('sales_price');
                }
            }
            $info['line'] = $line_list;
            //短信
            $sms_list = Db::name('sms_channel')->where('owner',$id)->select();
            if($sms_list){
                foreach($sms_list as $key => $value2 ){
                    $sms_list[$key]['pid_sale_price'] = Db::name('sms_channel')->where('id',$value2['pid'])->value('price');
                }
            }
            $info['sms'] = $sms_list;
            $info['id'] = $id;
            $info['pid_role_id'] = $role_id;
            $info['role_id'] = Db::name('admin')->where('id',$id)->value('role_id');
            return returnAjax(1,'获取数据成功',$info);
        }
        if(request()->isPost()){
            $id = input('id','','trim,strip_tags');
            $robot_price = input('robot_price',0,'trim,strip_tags');
            $vals_asr_id = 	explode(',',input('vals_asr_id','','trim,strip_tags'));
            $vals_asr_price = explode(',',input('vals_asr_price','','trim,strip_tags'));
            $vals_line_id = explode(',',input('vals_line_id','','trim,strip_tags'));
            $vals_line_price = explode(',',input('vals_line_price','','trim,strip_tags'));
            $vals_msm_id = explode(',',input('vals_msm_id','','trim,strip_tags'));
            $vals_msm_price = explode(',',input('vals_msm_price','','trim,strip_tags'));
            $note = input('note','','trim,strip_tags');
            $role_id = input('role_id','','trim,strip_tags');
            $service_price = input('service_price','','trim,strip_tags');
            if(!$note){
                $note = '修改资费金额';
            }
            //操作记录
            // $data['owner'] = $uid;
            // $data['user_id'] = $id;
            // $data['operation_type'] = 7;
            // $data['operation_fu'] = '资费管理';
            // $data['record_content'] = '资费编辑';
            // $data['operation_date'] = time();
            // $data['remark'] = $note;
            // //资费修改记录 postage_edit_record
            $record['owner'] = $uid ;
            $record['member_id'] = $id ;
            $record['update_time'] = time();

            // 启动事务
            Db::startTrans();
            try {
                // $res = Db::name('operation_record')->insert($data);//生成记录
                // if($res > 0 ){}

                $record['update_front'] = Db::name('admin')->where('id',$id)->value('month_price');
                if($robot_price != $record['update_front']){
                    $res_1 = Db::name('admin')->where('id',$id)->update(['month_price'=>$robot_price]);
                    $operation_record[$id]['robot']['old_data']['param_user_id'] = $user_auth['uid'];
                    $operation_record[$id]['robot']['old_data']['month_price'] = $record['update_front'];
                    $operation_record[$id]['robot']['new_data']['month_price'] = $robot_price;


                    if($role_id == 18){ //如果当前角色代理商 编辑商家机器人时间时  同时也编辑当前商家的的自角色时间

                        $find_users = Db::name('admin')->where('pid', $id)->field('id, username')->select();
                        while(count($find_users) > 0){ //循环
                            $ids = [];
                            foreach($find_users as $key=>$value){
                                $ids[] = $value['id'];

                                $operation_record[$value['id']]['robot']['old_data']['param_user_id'] = $id;
                                $operation_record[$value['id']]['robot']['old_data']['month_price'] = $record['update_front'];
                                $operation_record[$value['id']]['robot']['new_data']['month_price'] = $robot_price;

                                Db::name('admin')->where('id',$value['id'])->update(['month_price' => $robot_price]);
                            }
                            //在获取子用户
                            $find_users = Db::name('admin')->where('pid', 'in', $ids)->select();
                        }
                    }
                    $record['modular_name'] = '机器人月租' ;
                    $record['update_after'] = $robot_price ;
                    $record['update_front'] = $record['update_front'] ;
                    Db::name('postage_edit_record')->insert($record);
                }


                $record['update_front'] = Db::name('admin')->where('id',$id)->value('technology_service_price');
                // $res_1 =
                if($record['update_front'] != $service_price){
                    Db::name('admin')->where('id',$id)->update(['technology_service_price'=>$service_price]);
                    $operation_record[$id]['technology_service_price']['old_data']['param_user_id'] = $user_auth['uid'];
                    $operation_record[$id]['technology_service_price']['old_data']['technology_service_price'] = $record['update_front'];
                    $operation_record[$id]['technology_service_price']['new_data']['technology_service_price'] = $service_price;
                    if($role_id == 18){ //如果当前角色代理商 编辑商家机器人时间时  同时也编辑当前商家的的自角色时间
                        $find_users = Db::name('admin')->where('pid', $id)->field('id')->select();
                        while(count($find_users) > 0){ //循环
                            $ids = [];
                            foreach($find_users as $key=>$value){
                                $ids[] = $value['id'];

                                $operation_record[$value['id']]['technology_service_price']['old_data']['param_user_id'] = $id;
                                $operation_record[$value['id']]['technology_service_price']['old_data']['technology_service_price'] = $record['update_front'];
                                $operation_record[$value['id']]['technology_service_price']['new_data']['technology_service_price'] = $service_price;

                                Db::name('admin')->where('id',$value['id'])->update(['technology_service_price' => $service_price]);
                            }
                            //在获取子用户
                            $find_users = Db::name('admin')->where('pid', 'in', $ids)->select();
                        }
                    }
                    $record['modular_name'] = '服务费';
                    $record['update_after'] = $service_price ;
                    $record['update_front'] =$record['update_front'];
                    Db::name('postage_edit_record')->insert($record);
                }

                //asr
                if($vals_asr_id){
                    foreach($vals_asr_id as $key => $vo){
                        $price_asr_ladder[$key]['id'] = $vals_asr_id[$key];
                        $price_asr_ladder[$key]['price'] = $vals_asr_price[$key];
                    }
                    foreach($price_asr_ladder as $key=>$vo){
                        $info_asr =  Db::name('tel_interface')
                            ->alias('ti')
                            ->join('admin a', 'a.id = ti.owner', 'LEFT')
                            ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                            ->where('ti.id',$vo['id'])
                            ->field('ti.owner, ti.name, ti.sale_price, ar.name as role_name')
                            ->find();
                        $record['update_front'] = $info_asr['sale_price'];
                        if($info_asr['sale_price'] != $vo['price']){
                          $res_2 = Db::name('tel_interface')->where('id',$vo['id'])->update(['sale_price'=>$vo['price']]);
                          $operation_record[$info_asr['owner']]['asr'][$vo['id']]['old_data']['param_user_id'] = $user_auth['uid'];
                          $operation_record[$info_asr['owner']]['asr'][$vo['id']]['old_data']['sale_price'] = $info_asr['sale_price'];
                          $operation_record[$info_asr['owner']]['asr'][$vo['id']]['old_data']['name'] = $info_asr['name'];
                          $operation_record[$info_asr['owner']]['asr'][$vo['id']]['new_data']['sale_price'] = $vo['price'];
                        }

                        //如果当前被修改的ASR的所属用户是"商家"
                        if($info_asr['role_name'] == '商家'){
                            //因为商家能够分配的只能是销售了 所以直接更新分配出去的线路就好
                            Db::name('tel_interface')->where('pid',$vo['id'])->update(['sale_price'=>$vo['price']]);
                            //获取这个商家的所有销售人员
                            $find_users = Db::name('tel_interface')->where('pid', $vo['id'])->field('id, owner, name')->select();
                            foreach($find_users as $find_key=>$find_value){

                              $operation_record[$find_value['owner']]['asr'][$find_value['id']]['old_data']['param_user_id'] = $info_asr['owner'];
                              $operation_record[$find_value['owner']]['asr'][$find_value['id']]['old_data']['sale_price'] = $record['update_front'];
                              $operation_record[$find_value['owner']]['asr'][$find_value['id']]['old_data']['name'] = $info_asr['name'];
                              $operation_record[$find_value['owner']]['asr'][$find_value['id']]['new_data']['sale_price'] = $vo['price'];

                            }
                        }
                        $record['modular_name'] = "ASR费率(".$info_asr['name'].")" ;
                        $record['update_after'] = $vo['price'] ;
                        if($record['update_front']!=$vo['price']){
                          Db::name('postage_edit_record')->insert($record);
                        }
                    }
                }
                //线路
                if($vals_line_id){
                    foreach($vals_line_id as $key => $vo){
                        $price_line_ladder[$key]['id'] = $vals_line_id[$key];
                        $price_line_ladder[$key]['price'] = $vals_line_price[$key];
                    }
                    foreach($price_line_ladder as $key=>$vo){
                        $info_line =  Db::name('tel_line_group')
                            ->alias('tl')
                            ->join('admin a', 'a.id = tl.user_id', 'LEFT')
                            ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                            ->where('tl.id',$vo['id'])
                            ->field('tl.user_id, tl.sales_price, tl.name, ar.name as role_name')
                            ->find();
                        $record['update_front'] = $info_line['sales_price'];
                        $res_3 = Db::name('tel_line_group')->where('id',$vo['id'])->update(['sales_price'=>$vo['price']]);
                        $operation_record[$info_line['user_id']]['line_group'][$vo['id']]['old_data']['param_user_id'] = $user_auth['uid'];
                        $operation_record[$info_line['user_id']]['line_group'][$vo['id']]['old_data']['sales_price'] = $info_line['sales_price'];
                        $operation_record[$info_line['user_id']]['line_group'][$vo['id']]['old_data']['name'] = $info_line['name'];
                        $operation_record[$info_line['user_id']]['line_group'][$vo['id']]['new_data']['sales_price'] = $vo['price'];

                        //如果当前被修改的ASR的所属用户是"商家"
                        if($info_line['role_name'] == '商家'){
                            //因为商家能够分配的只能是销售了 所以直接更新分配出去的线路就好
                            Db::name('tel_line_group')->where('line_group_pid', $vo['id'])->update(['sales_price'=>$vo['price']]);
                            //查询这个商家所有分配出去的线路组
                            $find_line_group = Db::name('tel_line_group')->where('line_group_pid', $vo['id'])->field('id, name, user_id')->select();
                            //给这个线路组对应产生一条操作记录
                            foreach($find_line_group as $key=>$value){

                              $operation_record[$value['user_id']]['line_group'][$value['id']]['old_data']['param_user_id'] = $info_line['user_id'];
                              $operation_record[$value['user_id']]['line_group'][$value['id']]['old_data']['sales_price'] = $info_line['sales_price'];
                              $operation_record[$value['user_id']]['line_group'][$value['id']]['old_data']['name'] = $value['name'];
                              $operation_record[$value['user_id']]['line_group'][$value['id']]['new_data']['sales_price'] = $vo['price'];

                            }
                        }
                        $record['modular_name'] = "线路费率(".$info_line['name'].")" ;
                        $record['update_after'] = $vo['price'] ;
                        if($res_3 > 0 ){
                            Db::name('postage_edit_record')->insert($record);
                        }
                    }
                }
                //短信
                if($vals_msm_id){
                    foreach($vals_msm_id as $key => $vo){
                        $price_msm_ladder[$key]['id'] = $vals_msm_id[$key];
                        $price_msm_ladder[$key]['price'] = $vals_msm_price[$key];
                    }
                    foreach($price_msm_ladder as $key=>$vo){
                        $info_msm =  Db::name('sms_channel')
                            ->alias('sc')
                            ->join('admin a', 'a.id = sc.owner', 'LEFT')
                            ->join('admin_role ar', 'ar.id = a.role_id', 'LEFT')
                            ->where('sc.id',$vo['id'])
                            ->field('sc.owner, sc.price, sc.name, ar.name as role_name')
                            ->where('sc.id',$vo['id'])
                            ->find();
                        //member_id
                        $record['update_front'] = $info_msm['price'];

                        $operation_record[$info_msm['owner']]['sms'][$vo['id']]['old_data']['param_user_id'] = $user_auth['uid'];
                        $operation_record[$info_msm['owner']]['sms'][$vo['id']]['old_data']['price'] = $info_msm['price'];
                        $operation_record[$info_msm['owner']]['sms'][$vo['id']]['old_data']['name'] = $info_msm['name'];
                        $operation_record[$info_msm['owner']]['sms'][$vo['id']]['new_data']['price'] = $vo['price'];

                        $res_4 = Db::name('sms_channel')->where('id',$vo['id'])->update(['price'=>$vo['price']]);
                        //如果当前被修改的ASR的所属用户是"商家"
                        if($info_msm['role_name'] == '商家'){
                            //因为商家能够分配的只能是销售了 所以直接更新分配出去的线路就好
                            Db::name('sms_channel')->where('pid', $vo['id'])->update(['price'=>$vo['price']]);

                            //查詢這個商家所有分配出去的短信通道
                            $find_sms_channels = Db::name('sms_channel')->where('pid', $vo['id'])->field('id, name, owner')->select();

                            foreach($find_sms_channels as $key=>$value){

                              $operation_record[$value['owner']]['sms'][$value['id']]['old_data']['param_user_id'] = $info_msm['owner'];
                              $operation_record[$value['owner']]['sms'][$value['id']]['old_data']['price'] = $info_msm['price'];
                              $operation_record[$value['owner']]['sms'][$value['id']]['old_data']['name'] = $value['name'];
                              $operation_record[$value['owner']]['sms'][$value['id']]['new_data']['price'] = $vo['price'];

                            }
                        }
                        $record['modular_name'] = "短信费率(".$info_msm['name'].")" ;
                        $record['update_after'] = $vo['price'] ;
                        if($res_4 > 0 ){
                            Db::name('postage_edit_record')->insert($record);
                        }
                    }
                }
                // 提交事务
                Db::commit();
                $OperationRecord = new OperationRecord();
                \think\Log::info('$operation_record-'.json_encode($operation_record));
                $s = $OperationRecord->insert_fee_management($user_auth['uid'], $operation_record);
                return returnAjax(0,'编辑成功', $s);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return returnAjax(1,'编辑失败', ['message'  =>  $e->getMessage(), 'line'  =>  $e->getLine()]);
            }
        }
    
    }
    public function management_record(){
        if(request()->isGet()){
            $id = input('id','','trim,strip_tags');
            $info = [];
            $user = Db::name('admin')->where('id',$id)->find();
            $info['username'] = $user['username'] ;
            $info['role_name'] = Db::name('admin_role')->where('source_id',$user['role_id'])->value('name');
            $info['list'] = Db::name('postage_edit_record')->where('member_id',$id)->order('update_time','desc')->select();
            return returnAjax(1,'获取数据成功',$info);
        }
    }

    //操作记录
    public function ajax_operation_record(){
        $user_auth = session('user_auth');
        $uid = $user_auth["uid"];
        $name = input('name','','trim,strip_tags');
        $type = input('type','','trim,strip_tags');
        $where['owner'] = array('eq',$uid);
        if($name){
            $where['ad.username'] = array('like', "%".$name."%");
        }
        if($type){
            $where['op.operation_type'] = array('eq',$type);
        }
        $page_size = input('limit','','trim,strip_tags');
        $page = input('page','','trim,strip_tags');
        if(!$page_size)
            $page_size = 10 ;
        if(!$page)
            $page = 1;
        $list = Db::name('operation_record')
            ->alias('op')
            ->field('op.*,ad.username,ro.name')
            ->join("admin ad",'ad.id = op.user_id','LEFT')
            ->join("admin_role ro",'ad.role_id = ro.id','LEFT')
            ->where($where)
            ->page($page,$page_size)
            ->order('operation_date','desc')
            ->select();
        $count = Db::name('operation_record')
            ->alias('op')
            ->field('op.*,ad.username,ro.name')
            ->join("admin ad",'ad.id = op.user_id','LEFT')
            ->join("admin_role ro",'ad.role_id = ro.id','LEFT')
            ->where($where)
            ->count('op.id');
        $page_count = ceil($count/$page_size);
        foreach($list as $key=>$vo){
            $list[$key]['operation_date'] = date("Y-m-d H:i",$vo['operation_date']);
        }

        $data = array();
        $data['list'] = $list; //数据
        $data['total'] = $count; //总条数
        $data['page'] = $page_count; //总页数
        $data['Nowpage'] = $page; //当前页数
        return returnAjax(0,'获取数据成功',$data);
    }

    public function ajax_service_cost(){
        $user_auth = session('user_auth');
        $uid = $user_auth["uid"];
        //选择用户类型
        $info = Db::name('admin')->where('id',$uid)->find();
        $role_id = $info['role_id'];
        $this->assign('role_id',$role_id);

        if(request()->isPost()){
            $page = input('page','','strip_tags');
            $limit = input('limit','','strip_tags');
            $userName = input('userName','','strip_tags');
            if(!$limit)
                $limit = 10 ;
            if(!$page)
                $page = 1;
            $where = [];
            $where['pid'] = array('eq',$uid);
            $where['status'] = array('eq',1);
            if($userName){
                $where['username'] = ['like', '%'.$userName.'%'];
            }
            $list = Db::name('admin')->where($where)->page($page,$limit)->select();
            $pid_service_price = Db::name('admin')->where('id',$uid)->value('technology_service_price');
            foreach($list as $key => $vo){
                $list[$key]['role_name'] = Db::name('admin_role')->where('id',$vo['role_id'])->value('name');
                $list[$key]['pid_service_price'] = $pid_service_price;
                $where_a['user_id'] = array('eq',$vo['id']);
                $where_a['operation_type'] = array('eq',7);
                $list[$key]['cinert'] = Db::name('operation_record')->limit(1)->where($where_a)->order('operation_date','desc')->value('remark');
                if(!$list[$key]['cinert']){
                    $where_a['operation_type'] = array('eq',5);
                    $list[$key]['cinert'] = Db::name('operation_record')->limit(1)->where($where_a)->order('operation_date','desc')->value('remark');
                    if(!$list[$key]['cinert']){
                        $list[$key]['cinert'] = '暂无数据';
                    }
                }
            }
            $count = Db::name('admin')->where($where)->count();
            $page_count = ceil($count/$limit);

            $data = array();
            $data['list'] = $list; //数据
            $data['total'] = $count; //总条数
            $data['page'] = $page_count; //总页数
            $data['Nowpage'] = $page; //当前页数
            return returnAjax(0,'获取数据成功',$data);
        }
    }
    public function edit_service_cost(){
        $user_auth = session('user_auth');
        $uid = $user_auth["uid"];
        if(request()->isGet()){
            $id = input('id','','trim,strip_tags');
            $info = Db::name('admin')
                ->alias('a')
                ->join('admin p_a', 'p_a.id = a.pid', 'LEFT')
                ->field('a.id,a.username,a.technology_service_price,a.remark,p_a.technology_service_price as technology_service_cost')
                ->where('a.id',$id)
                ->find();
            return returnAjax(0,'数据获取成功',$info);
        }
        if(request()->isPost()){
            $id = input('id','','trim,strip_tags');
            $price = input('price','','trim,strip_tags');
            $remark = input('remark','','trim,strip_tags');


            $user_info = Db::name('admin')->alias('a')->join('admin_role ar', 'a.role_id = ar.id', 'LEFT')->where('a.id', $id)->field('a.username, ar.name as role_name, a.technology_service_price')->find();

            $data = [];
            $data['owner'] = $uid;
            $data['user_id'] = $id;
            $data['operation_type'] = 7;
            $data['operation_fu'] = '编辑技术服务费管理';
            $data['record_content'] = '编辑用户名:"'.$user_info['username'].'"的技术服务费，从原先的'.$user_info['technology_service_price'].'元修改为'.$price.'元';
            $data['operation_date'] = time();
            $data['remark'] = $remark;
            Db::startTrans();
            try {
                Db::name('operation_record')->insert($data);//生成记录
                Db::name('admin')->where('id',$id)->update(['technology_service_price'=>$price]);

                if($user_info['role_name'] == '商家'){
                  // Db::name('admin')->where('');
                  //查询这个商家的所有销售人员
                  $where = [
                    'role_id'  =>  19,
                    'pid'  =>  $id
                  ];
                  $find_users = Db::name('admin')->where($where)->field('id, username, technology_service_price')->select();
                  foreach($find_users as $key=>$value){
                    $data = [];
                    $data['owner'] = $uid;
                    $data['user_id'] = $value['id'];
                    $data['operation_type'] = 7;
                    $data['operation_fu'] = '编辑技术服务费管理';
                    $data['record_content'] = '编辑用户名:"'.$value['username'].'"的技术服务费，从原先的'.$value['technology_service_price'].'元修改为'.$price.'元';
                    $data['operation_date'] = time();
                    $data['remark'] = $remark;
                    Db::name('operation_record')->insert($data);//生成记录
                  }
                }
                // 提交事务
                Db::commit();
                return returnAjax(0,'修改成功');
            }catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return returnAjax(1,'修改失败');
            }
        }
    }
}


//toFixed_num
