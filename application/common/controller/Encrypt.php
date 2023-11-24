<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/9 0009
 * Time: 下午 17:45
 */
namespace app\common\controller;
Class Encrypt{

    private $key='LiNing0509';


    public function __construct()
    {

    }

    /**
     *
     * 加密解密函数，
     * D 解密 E 加密
     *
    */
    public function encrypt($string,$operation){

        $key=$this->key;
        $key=md5($key);
        $key_length=strlen($key);
        $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string;
        $string_length=strlen($string);
        $rndkey=$box=array();
        $result='';
        for($i=0;$i<=255;$i++){
            $rndkey[$i]=ord($key[$i%$key_length]);
            $box[$i]=$i;
        }
        for($j=$i=0;$i<256;$i++){
            $j=($j+$box[$i]+$rndkey[$i])%256;
            $tmp=$box[$i];
            $box[$i]=$box[$j];
            $box[$j]=$tmp;
        }
        for($a=$j=$i=0;$i<$string_length;$i++){
            $a=($a+1)%256;
            $j=($j+$box[$a])%256;
            $tmp=$box[$a];
            $box[$a]=$box[$j];
            $box[$j]=$tmp;
            $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256]));
        }

        if($operation=='D'){
            if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8)){
                return substr($result,8);
            }else{
                return'';
            }
        }else{
            return str_replace('=','',base64_encode($result));
        }
    }
}

//
//$t=new Encrypt();
//echo  $t->encrypt('Dragon123^!!','E');
//
//echo $t->encrypt( $t->encrypt('Dragon123^!!','E'),'D' );
//


$a=['a'=>
    ['namea'=>'nameaaa','namea1'=>'nameaaa1','namea2'=>'nameaaa2',  ],
    'b'=>
    ['namea'=>'nameaaa','namea1'=>'nameaaa1','namea2'=>'nameaaa2',  ]

];
$b=['a'=>
    ['nameb'=>'nameaaa','nameb1'=>'nameaaa1','nameb2'=>'nameaaa2',  ],
    'b'=>
    ['nameb'=>'nameaaa','nameb1'=>'nameaaa1','namea2'=>'nameaaa2!!!!!',  ]

];


print_r( array_merge_recursive($a,$b) );