<?php
// +----------------------------------------------------------------------
// | RuiKeCMS [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.ruikesoft.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Wayne <wayne@ruikesoft.com> <http://www.ruikesoft.com>
// +----------------------------------------------------------------------

namespace app\common\controller;
use app\common\model\AuthRule;
use app\common\model\AuthGroup;
//获取菜单数据
use app\common\controller\MenuData;

use app\common\controller\Log;

class User extends Base{

    public function _initialize(){
        parent::_initialize();

        // 是否是超级管理员

        if (!is_login() and !in_array($this->url,array('user/upload/upload','user/index/login', 'user/index/extension_login', 'user/index/logout', 'user/index/verify', 'user/index/verify_register'))) {
            $this->redirect('user/index/login');
        }
        if (!defined('IS_ROOT')) {
            define('IS_ROOT',   is_administrator());
        }
        //菜单设置

        $user = session('user_auth');
        if(empty($user)){
            return false;
        }
        if(isset($user['port']) == false || $user['port'] != $_SERVER['SERVER_PORT']){
            session('user_auth', null);
            $this->error('未授权访问!',url('user/index/login'));
        }
        $uid = $user['uid'];
        //判断是否超级管理员,是才可以修改用户资料
        $super = $user['super'];
        $this->assign('is_super', $super);
        $ulist = db('admin')
            ->field("role_id")
            ->where('id',$user["uid"])
            ->find();
        $rolelist = db('admin_role')
            ->field("rule_items")
            ->where([
                'id'	=>	$ulist["role_id"]
            ])
            ->find();
        $url = array();
        $menuId = array();


        $temp = explode(",",$rolelist["rule_items"]);


        $menulist = db('menu')
            ->field('id,title,pid,url,source_id')
            ->where([
                'id'	=>	['in', $temp],
                'menu_grouping'	=>	$user['menu_grouping']
            ])
            ->select();
        foreach ($menulist as $k=>$v){
            if($v["url"]){
                $action = explode('/', $v["url"]);
                $actString = $action[0]."/".$action[1];
                $url[$k] = strtolower($actString);
            }
            $menuId[$k] = $v["source_id"];
        }
        if ($this->request->isGet()){
            $thisurl = strtolower($this->request->module() . '/' . $this->request->controller());
            $allow = array("user/upload/delete","user/upload/upload",'user/index/login', 'user/index/extension_login', 'user/index/logout', 'user/index/verify', 'user/index/verify_register', 'user/index/index','user/upload/editor');
            if (!in_array($thisurl,$url) && !in_array($thisurl,$allow) && !in_array($this->url,$allow)) {
                //var_dump($thisurl,$url,!in_array($thisurl,$url),!in_array($thisurl,$allow));exit;
                // echo in_array($thisurl,$url) . ' = 1';
                // echo '<br />';
                // echo in_array($thisurl,$allow) . ' = 2';
                // echo '<br />';
                // echo in_array($this->url,$allow) . ' = 3';
                $this->error('未授权访问!',url('user/index/login'));
            }
        }
        //}
        //获取当前登录用户角色等级
        $u_role_id = db('admin')->where('id',$uid)->value('role_id');
        //判断是否是运营商以上
        if($u_role_id != 16 && $u_role_id != 12 && !empty($uid)){
            $p_user  = db('admin')
                ->alias('za')
                ->field('ca.id,ca.role_id')
                ->join('admin ca','za.pid = ca.id','LEFT')
                ->where('za.id',$uid)
                ->find();
            while($p_user['role_id'] != 16){
                $p_user  = db('admin')
                    ->alias('za')
                    ->field('ca.id,ca.role_id')
                    ->join('admin ca','za.pid = ca.id','LEFT')
                    ->where('za.id',$p_user['id'])
                    ->find();
            }
            $site_data = db('basics_config')
                ->where('member_id', $p_user['id'])
                ->find();
        }else{
            $site_data = db('basics_config')
                ->where('member_id', $uid)
                ->find();
        }
        //获取用户数据
        $datas = db('admin')->field('logo')->where('id', $uid)->find();
        $this->assign('site_data', $site_data);
        $this->assign('user_logo',$datas);

        //获取当前菜单数据
        $MenuData = new MenuData();

        // $menu_data = $MenuData->get_menu_data($this->url);
        $menu_data= $MenuData->get_menu_data($this->url);
        $this->assign('menu_data', $menu_data);
        $msgNum = db('message')->where(array('member_id'=>$uid,'status'=>0))->count('id');
        $this->assign('msgNum', $msgNum);
        $this->setMenu($menuId);
        $this->setMeta();

        //对于用户的访问进行记录

        $remote  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $method  = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
        $uri     = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $params  = json_encode(input('param.',''));

        $insertData=array('user_id'=>$uid,'url_path'=>$uri,'params'=>$params,'method'=>$method,'access_ip'=>$remote,'create_time'=>date("Y-m-d H:i:s"));
        \think\Db::name('system_operation_record')->insert($insertData);


    }

    /**
     * 权限检测
     * @param string  $rule    检测的规则
     * @param string  $mode    check模式
     * @return boolean
     * @author 朱亚杰  <xcoolcc@gmail.com>
     */
    final protected function checkRule($rule, $type=AuthRule::rule_url, $mode='url'){
        static $Auth    =   null;
        if (!$Auth) {
            $Auth       =   new \com\Auth();
        }
        if(!$Auth->check($rule,session('user_auth.uid'),$type,$mode)){
            return false;
        }
        return true;
    }

    /**
     * 检测是否是需要动态判断的权限
     * @return boolean|null
     *      返回true则表示当前访问有权限
     *      返回false则表示当前访问无权限
     *      返回null，则表示权限不明
     *
     * @author 朱亚杰  <xcoolcc@gmail.com>
     */
    protected function checkDynamic(){
        if(IS_ROOT){
            return true;//管理员允许访问任何页面
        }
        return null;//不明,需checkRule
    }

    /**
     * action访问控制,在 **登陆成功** 后执行的第一项权限检测任务
     *
     * @return boolean|null  返回值必须使用 `===` 进行判断
     *
     *   返回 **false**, 不允许任何人访问(超管除外)
     *   返回 **true**, 允许任何管理员访问,无需执行节点权限检测
     *   返回 **null**, 需要继续执行节点权限检测决定是否允许访问
     * @author 朱亚杰  <xcoolcc@gmail.com>
     */
    final protected function accessControl(){
        $allow = \think\Config::get('allow_visit');
        $deny  = \think\Config::get('deny_visit');
        $check = strtolower($this->request->controller().'/'.$this->request->action());
        if ( !empty($deny)  && in_array_case($check,$deny) ) {
            return false;//非超管禁止访问deny中的方法
        }
        if ( !empty($allow) && in_array_case($check,$allow) ) {
            return true;
        }
        return null;//需要检测节点权限
    }


    protected function setMenu($menuId){
        $hover_url = $this->request->controller();
        $controller = $this->url;
        $menu = array();
        $uid = session('user_auth.uid');
        $user_auth_sign = session('user_auth_sign');
        // echo $uid;
        $role_id = db('admin')
            ->where('id', $uid)
            ->value('role_id');
        $where['hide']  =   0;
        $where['type']  =   'user';
        $where['menu_grouping'] = session('user_auth.menu_grouping');
        $menuList = db('menu')->field('id,title,url,icon,pid')->where($where)->where('id','in',$menuId)->order('sort asc')->select();
        //获取菜单排序规则
        $order_rule = db('admin_role')
            ->where('id', $role_id)
            ->value('order_rule');
        $order_rule = unserialize($order_rule);
        $nowPathInfo = "";

        $is_scenarios = db('admin')->where('id',$uid)->value('is_scenarios');
        $this->assign('is_scenarios',$is_scenarios);

        if(isset($_SERVER['PATH_INFO'])){
            $ca = explode('/', $_SERVER['PATH_INFO']);

            if($_SERVER['PATH_INFO']){
                if (count($ca) >2){
                    $nowPathInfo = $ca[2];
                    if (isset($ca[3])){
                        $nowPathInfo .='/'.$ca[3];
                    }
                }
                else{
                    $nowPathInfo = $ca[1];
                }
            }
        }



        $flag = 0;
        foreach ($menuList as $key => $value) {
            $expand = 0;//默认不展开子菜单
            if ($value['pid'] == 0){
                //获取排序值
                $order_id = $order_rule[$value['id']];
                $menu[$order_id][$value['id']]['title'] = $value['title'];
                $menu[$order_id][$value['id']]['expand'] = 0;
                $menu[$order_id][$value['id']]['icon'] = $value['icon'];
                $menu[$order_id][$value['id']]['url'] = $value['url'];
                // print_r($menuList);
                foreach ($menuList as $sKey => $sValue) {
                    if ($sValue['pid'] > 0 && $value['id'] == $sValue['pid']){
                        //获取排序值
                        // print_r($order_rule);
                        $sorder_id = $order_rule[$sValue['id']];
                        if ($controller == strtolower($sValue['url'])) {
                            $sValue['style'] = "active";
                            $expand = 1;
                        }
                        $menu[$order_id][$value['id']]['child'][$sorder_id][$sValue['id']] = $sValue;
                        // 添加三级菜单
                        foreach ($menuList as $zKey => $zValue) {
                            if ($zValue['pid'] > 0 && $sValue['id'] == $zValue['pid']){
                                $zorder_id = $order_rule[$zValue['id']];
                                $menu[$order_id][$value['id']]['child'][$sorder_id][$sValue['id']]['child'][$zorder_id][$zValue['id']] = $zValue;
                                if ($controller == strtolower($zValue['url'])) {
                                    $zValue['style'] = "active";
                                    $expand = 1;
                                    $menu[$order_id][$value['id']]['child'][$sorder_id][$sValue['id']]['expand'] = 1;
                                    $menu[$order_id][$value['id']]['child'][$sorder_id][$sValue['id']]['child'][$zorder_id][$zValue['id']]['expand'] = 1;
                                }
                            }
                        }
                    }
                }
            }
            //展开子菜单
            if ($expand){
                $menu[$order_id][$value['id']]['expand'] = 1;
                $flag = 1;
            }
            if($value['pid'] == 0){
                if($controller == strtolower($menu[$order_id][$value['id']]['url'])){
                    $menu[$value['id']]['expand'] = 1;
                    $flag = 1;
                }
            }

        }

        if(!$flag){
            foreach ($menu as $mKey => $mValue) {
                foreach($mValue as $find_mKey=>$find_mValue){
                    if(isset($mValue["child"])){
                        foreach ($mValue["child"] as $ck => $cv) {
                            foreach($cv as $find_ck=>$find_cv){


                                $val = explode('/', $find_cv["url"]);
                                if (!$val[0]){
                                    continue;
                                }
                                $isvl = $val[0]."/".$val[1];



                                if (strtolower($nowPathInfo) == strtolower($isvl)) {

                                    //$one = explode('=', $val[2]);
                                    $menu[$mKey][$find_mKey]["expand"] = 1;
                                    // dump(strtolower($nowPathInfo));
                                    //   dump(strtolower($isvl));
                                    //							if(isset($one[1])){
                                    //
                                    //								if($one[1] == input('flag','','trim,strip_tags')){
                                    //									$menu[$mKey]["expand"] = 1;
                                    //
                                    //									$menu[$mKey]["child"][$ck]['style'] = "active";
                                    //								}
                                    //
                                    //							}

                                }

                                /*
                                if(isset($_SERVER["HTTP_REFERER"])){
                                    if (strpos(strtolower($_SERVER["HTTP_REFERER"]),strtolower($cv['url']))) {
                                        $menu[$mKey]["child"][$ck]['style'] = "active";
                                    }
                                }
                                */
                            }
                        }
                    }
                }
            }
        }
        //排序
        $this->menu_sort($menu);
        foreach($menu as $key=>$value){
            foreach($value as $find_key=>$find_value){
                if(isset($find_value['child']) == true && is_array($find_value['child']) == true){
                    $menu[$key][$find_key]['child'] = $this->menu_sort($find_value['child']);
                    foreach($find_value['child'] as $find_2_key=>$find_2_value){
                        foreach($find_2_value as $find_3_key=>$find_3_value){
                            if(isset($find_3_value['child']) == true && is_array($find_3_value['child'])){
                                $menu[$key][$find_key]['child'][$find_2_key][$find_3_key]['child'] = $this->menu_sort($find_3_value['child']);
                            }
                        }
                    }
                }
            }
        }
        //获取用户的基本信息
        $user_auth = session('user_auth');
        // 设置全体 title 和 logo的
        $res = db('config')
            ->where([
                'group'	=> 36,
                'member_id'	=>	$user_auth['uid']
            ])
            ->find();

        $res['val'] = unserialize($res['value']);


        $cover = array();
        // 	if($res['val']["cover"]){

        // 		//$plist = db('picture')->field('path')->where('id', $res['val']["cover"])->find();
        // 		// $cover['cover'] = $plist["path"];

        // 	}else{
        // 		$cover['cover'] = "";
        // 	}

        // 	$res['val']["logo"] = $cover['cover'];
        // 	$this->assign('allcinfig', $res['val']);


        $this->assign('__menu__',$menu);
        //增加获取菜单
        if(cookie('skb_token')){
            //换取菜单
            // dump(session('user_auth.uid'));
            $curlRes2 = json_decode($this->curl_getlist(session('user_auth.uid')),TRUE);
            // dump($curlRes2);die();
            if($curlRes2['success']){
                foreach ($curlRes2['data']['menu'] as $k1 => &$v1) {
                    $v1['url'] = urlencode(str_replace("{access_token}",cookie('skb_token'), $v1['url']));
                }
            }
            $this->assign('skb_menu',$curlRes2['data']['menu']);
        }
    }

    //菜单排序
    public function menu_sort($menu){
        //获取最大的key
        $max = 0;
        //获取最小的key
        $min = 999999999999999999999999999999;
        foreach($menu as $key=>$value){
            if($key < $min){
                $min = $key;
            }
            if($key > $max){
                $max = $key;
            }
        }
        if(isset($_GET['action']) && $_GET['action'] == 'test'){
            echo $max . ';;;;';
        }
        $new_menu = [];
        for($min; $min <= $max; $min++){
            if(isset($menu[$min]) === true){
                $new_menu[$min] = $menu[$min];
            }
        }
        return $new_menu;
    }
    /*
       protected function setMenu(){
           $hover_url = $this->request->controller();
           $controller = $this->url;
           $menu = array();

           $where['hide']  =   0;
           $where['type']  =   'user';

           $menuList = db('menu')->field('id,title,url,icon,pid')->where($where)->order('sort asc')->select();
           $nowPathInfo = "";
           $ca = explode('/', $_SERVER['PATH_INFO']);
           if($_SERVER['PATH_INFO']){
               if (count($ca) >2){
                   $nowPathInfo = $ca[1].'/'.$ca[2];
               }
               else{
                   $nowPathInfo = $ca[1];
               }
           }

           $flag = 0;

           foreach ($menuList as $key => $value) {
             if ($value['pid'] == 0){
               $menu[$value['id']]['title'] = $value['title'];
               $menu[$value['id']]['expand'] = 0;
               $menu[$value['id']]['icon'] = $value['icon'];
             }


             $expand = 0;//默认不展开子菜单
               foreach ($menuList as $sKey => $sValue) {

                   //此处用来做权限判断
                      if (!IS_ROOT && !$this->checkRule($sValue['url'],2,null) ) {
                         continue;
                     }

                  if ($sValue['pid'] > 0 && $value['id'] == $sValue['pid']){

                       if ($controller == strtolower($sValue['url'])) {
                           $sValue['style'] = "active";
                           $expand = 1;
                       }
                       $menu[$value['id']]['child'][] = $sValue;

                  }
             }
             //展开子菜单
             if ($expand){
                 $menu[$value['id']]['expand'] = 1;
                 $flag = 1;
               }

           }

           if(!$flag){
               foreach ($menu as $mKey => $mValue) {
                   if(isset($mValue["child"])){
                       foreach ($mValue["child"] as $ck => $cv) {

                           $val = explode('/', $cv["url"]);
                           $isvl = $val[0]."/".$val[1];

                           if (strtolower($nowPathInfo) == strtolower($isvl)) {
                               $menu[$mKey]["expand"] = 1;
                           }

                           if(isset($_SERVER["HTTP_REFERER"])){
                               if (strpos(strtolower($_SERVER["HTTP_REFERER"]),strtolower($cv['url']))) {
                                   $menu[$mKey]["child"][$ck]['style'] = "active";
                               }
                           }

                       }
                   }
               }
           }


           $this->assign('__menu__',$menu);
       }
    */
    protected function getContentMenu(){
        $model = \think\Loader::model('Model');
        $list = array();
        $map = array(
            'status' => array('gt',0),
            'extend' => array('gt',0),
        );
        $list = $model::where($map)->field("name,id,title,icon,'' as 'style'")->select();

        $menu = array();
        $expand = 0;
        //判断是否有模型权限
        $models = AuthGroup::getAuthModels(session('user_auth.uid'));
        foreach ($list as $key => $value) {
            if (IS_ROOT || in_array($value['id'], $models)) {
                $item['style'] = "no";
                if ('user/content/index' == $this->url && input('model_id/d') == $value['id']) {
                    $item['style'] = "active";
                    $expand = 1;

                }


                $item['url'] = "user/content/index?model_id=".$value['id'];
                $item['title'] = $value['title']."管理";
                $item['icon'] = $value['icon'] ? $value['icon'] : 'file';
                $menu['child'][] = $item;
            }
        }

        $item['url'] = 'user/category/index';
        $item['title'] = '栏目管理';
        $item['icon'] = 'fa fa-list-ol';
        $menu['child'][] = $item;

        $menu['title'] = '内容管理';
        $menu['expand'] = $expand;
        //dump($menu);
        if (!empty($menu)) {
            $this->assign('extend_menu',$menu);
        }
        //增加获取菜单
        if(cookie('skb_token')){
            //换取菜单
            // dump(session('user_auth.uid'));
            $curlRes2 = json_decode($this->curl_getlist(session('user_auth.uid')),TRUE);
            // dump($curlRes2);die();
            if($curlRes2['success']){
                foreach ($curlRes2['data']['menu'] as $k1 => &$v1) {
                    $v1['url'] = urlencode(str_replace("{access_token}",cookie('skb_token'), $v1['url']));
                }
            }
            $this->assign('skb_menu',$curlRes2['data']['menu']);
        }
    }

    //登录帐号时调用搜客宝curl获取菜单
    public function curl_getlist($uid){
        $timestamp = substr(array_sum(explode(' ', microtime()))*1000,0,13);
        $key = config("skb_key");
        $secret = config("skb_secret");
        $pin = createSign($timestamp,$secret);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "http://sk.yunxiongnet.com/services/v2/rest/user/menu?uid=".'AISKB'.$uid,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "X-AK-KEY: {$key}",
                "X-AK-PIN: {$pin}",
                "X-AK-TS: {$timestamp}",
                "Content-Type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response);
        return $response;
    }


    protected function getAddonsMenu(){
        $model = db('Addons');
        $list = array();
        $map = array(
            'status' => array('gt',0),
        );
        $list = $model->field("name,id,title,'' as 'style'")->where($map)->select();

        $menu = array();
        foreach ($list as $key => $value) {
            $class = "\\addons\\".strtolower($value['name'])."\\controller\\Admin";
            if (is_file(ROOT_PATH . $class.".php")) {
                $action = get_class_methods($class);
                $value['url'] = "admin/addons/execute?mc=".strtolower($value['name'])."&ac=".$action[0];
                $menu[$key] = $value;
            }
        }
        if (!empty($menu)) {
            $this->assign('extend_menu',array('管理插件'=>$menu));
        }
        //增加获取菜单
        if(cookie('skb_token')){
            //换取菜单
            // dump(session('user_auth.uid'));
            $curlRes2 = json_decode($this->curl_getlist(session('user_auth.uid')),TRUE);
            // dump($curlRes2);
            if($curlRes2['success']){
                foreach ($curlRes2['data']['menu'] as $k1 => &$v1) {
                    $v1['url'] = urlencode(str_replace("{access_token}",cookie('skb_token'), $v1['url']));
                }
            }
            $this->assign('skb_menu',$curlRes2['data']['menu']);
        }
    }

    protected function setMeta($title = ''){
        $this->assign('meta_title',$title);
    }



}
