<?php
namespace app\common\controller;
use \think\Cache;
use \think\Db;
/*
 * 统一使用表名进行  缓存
*/


class CacheO extends \think\Controller {





    /*
         * $cacheName  缓存名称
         * $dbName  缓存的数据库名称
    */
    public static function Cache_Get($cacheName='',$dbName='')
    {
        empty($dbName)&&$dbName=$cacheName;
        if(  false=== $cacheValue= unserialize(  Cache::get($cacheName)   )  ) {

            $cacheValue = Db::name($dbName)->select();
    //            print_r(serialize($cacheValue));
    //            echo 'aaaa';
            Cache::set($cacheName,  serialize($cacheValue)   );
    //            echo 'bbbb';

        }

        return $cacheValue;

    }


    /*
         * $cacheName  缓存名称
         * $dbName  缓存的数据库名称
    */
    public static function Cache_Set($cacheName='',$dbName='')
    {
        empty($dbName)&&$dbName=$cacheName;
        $cacheValue = Db::name($dbName)->select();

        return   Cache::set($cacheName,  serialize($cacheValue)   );


    }








    //Function name passed as string
    public static function Cache_Filter( $value,$key,$condition = '' )
    {
        switch ($condition) {
            case '=':
                return $value == $key;
                break;
            case '>':
                return $value > $key;
                break;
            case '<':
                return $value < $key;
                break;
            case '>=':
                return $value >= $key;
                break;
            case '<=':
                return $value <= $key;
                break;
            default:
                return $value == $key;
                break;

        }

    }

    /*
     * 结果集为 二维数组， 比如 array( 0=>array()  1=>array() )
     * $fields  是个数组  ['field1','field2|$alname']  $alname 为别称
     * $whereArr 是个数组  ['key'=>value]  或者  ['key'=>['>','value']] 的形式  暂时不支持其他
     * $orderArr  形如['id'=>'asc'] ['id'=>'asc','uid'=>'desc']
     *
    */

    public static function Cache_Get_With_Condition( $cacheValue, $fields=[], $whereArr=[],$orderArr=[])
    {

        //先进行条件判断
        if (!empty($fields) && is_array($fields)) {

                //  print_r($cacheValue);
                //对于结果集进行过滤
                $returnArr=[];
               foreach($cacheValue as $key=>$value){
                    foreach($fields as $k=>$v){
                        if(strpos($v,'|') ) {
                            $vArr=explode('|',$v);
                            $name1=trim($vArr[0]);
                            $name2=trim($vArr[1]);
//                            echo $name1 ,$name2;

//                            if($name1=='owner'||$name1=='sale_price')print_r($value);
                            $returnArr[$key][$name2]=$value[$name1]??'';
                        }else{
                            $v=trim($v);
                            $returnArr[$key][$v]=$value[$v]??'';
                        }

                    }
               }
                 $cacheValue= $returnArr;unset($returnArr);
            }




//        print_r($cacheValue);
        //后进行
        if (!empty($whereArr) && is_array($whereArr)) {

            foreach ($whereArr as $k => $v) {
                //判断的关键字
                $key = $k;
                if (is_array($v)) {
                    //关系符号
                    $condition = $v[0];
                    //比较数据
                    $value = $v[1];
                } else {
                    //关系符号
                    $condition = '=';
                    //比较数据
                    $value = $v;
                }
//                echo $key,$value, $condition;
                //对于结果集进行过滤
                $cacheValue = array_filter( $cacheValue, function($subArr,$subKey )use($key,$value, $condition){

                    return self::Cache_Filter($subArr[$key] ,$value, $condition )  ;

                } ,1 );

            }


        }
        //order  排序

        if (!empty($orderArr) && is_array($orderArr)) {

            $orderArr= array_reverse($orderArr);

            foreach($orderArr as $orderKey=>$orderEx){

                if(empty($orderEx) )continue ;
                //降序

                if($orderEx=='desc'){
                    $ssortArr = array();
                    foreach ($cacheValue as $key => $row) {
                        $ssortArr[$key] = $row[ $orderKey ];
                    }

                    array_multisort($ssortArr, SORT_DESC, $cacheValue);
                }else{
                    $ssortArr = array();
                    foreach ($cacheValue as $key => $row) {
                        $ssortArr[$key] = $row[$orderKey];
                    }
                    array_multisort($ssortArr, SORT_ASC, $cacheValue);
                }
            }
        }

//        print_r($orderArr);

        return $cacheValue;


    }





    /*
     * $left  左数组
     * $right 右数组
     * $left_join_on 左字段
     *
    */

    public static  function Cache_Get_With_Join_Condition($left, $right, $left_join_on, $right_join_on = NULL){
            $final= array();
            if(empty($right_join_on))
                $right_join_on = $left_join_on;


        foreach($left AS $k => $v){
            $final[$k] = $v;
            foreach($right AS $kk => $vv){
                if(empty($vv[$right_join_on]))continue;
                if($v[$left_join_on] === $vv[$right_join_on]){
                    $final[$k]+=$right[$kk];
                }
            }
        }
        return $final;




    }
    /* 为减少循环次数  专门针对left join 进行设计
     * $left  左数组
     * $right 右数组
     * $left_join_on 左字段
     *
    */

    public static  function Cache_Get_With_Left_Join_Condition($left, $right, $left_join_on, $right_join_on = NULL){
        //初始化
        $final= array();
        $rArr=array();
        if(empty($right_join_on))
            $right_join_on = $left_join_on;

        //循环处数据进行处理  将右侧数组 一句 右侧关键字的值 进行 存储
        foreach($right as $k => $v){
            $rArr[ $v[ $right_join_on ]] = $v;
        }
        //
        foreach($left as $k => $v){

           if(!empty( $rArr [ $v[$left_join_on] ] )) {
               $final[$k] = $v + $rArr [ $v[$left_join_on] ] ;}
            else{
                $final[$k] = $v;
            }
        }



        return $final;




    }








}

?>