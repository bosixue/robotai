/*
    效果：打开网页进行软电话连接， 监听断开，并3秒内重新连接。
    连接成功之后会与软电话对应的用户关联起来。
    执行拨打和挂断都是在关联中的用户名下执行

    window.中存储当前通话的UUID，当前使用的账户uniqueid,当前账户url  window.call_unique_id（账户也可称为分机）

    当前只能最多一通电话在打，
    当前最多一个关联的软电话用户
    0508/LiNing

*/


/**
 * 全局处理
 *
 */

//定时连接websocket
window.heartCheck = {

    wss:null,//ws对象
    timeout: 30000,
    timeoutObj: null,
    reset: function(){
        clearTimeout( this.timeoutObj );
        return this;
    },
    start: function(){
        this.timeoutObj = setTimeout(function(){
            loadWebSocket();
        }, this.timeout);
    }
}

//分机功能开启与否 打开的时候进行验证
window.onload = function(){

    $.ajax({
        url:location.origin+'/user/extension/getSwitchTag',
        method:'post',
        data:{'getsoftcall':1},
        success:function(tag){
            if (tag)
            {
                youCanCallNow();      //刷新之后 立即开通允许拨打
                initilizeCallList();  //初始化通话ID列表 以及 通话详情列表
                loadWebSocket();      //开启websocket
                // reviewCallWdget(); //开启全局 小窗口
            }
        },


    });
};


/**
 *当websocket 断开之后进行的操作
 */

function outServer(){

    // if(window.call_unique_id!='' && typeof window.call_unique_id !='undefined' )hangupf();   //对于主动断开连接的用户发送此刻的时间戳给后台进行记录

    //对于当前通话进行异常处理
    specialClose(window.call_unique_id);
    window.call_unique_id = '';



    window.callf=function() {
        alert('请打开sipphone，再尝试进行呼叫。');
    }
    window.hangupf=function() {
        alert('请打开sipphone，再尝试进行呼叫。');
    }

}
function output(msg){
    console.log(msg);
}
function getSearchString(key) {
    // 获取URL中?之后的字符
    var str = location.search;

    str = str.substring(1, str.length);

    // 以&分隔字符串，获得类似name=xiaoli这样的元素数组
    var arr = str.split("&");
    var obj = new Object();

    // 将每一个数组元素以=分隔并赋给obj对象
    for (var i = 0; i < arr.length; i++) {
        var tmp_arr = arr[i].split("=");
        obj[decodeURIComponent(tmp_arr[0])] = decodeURIComponent(tmp_arr[1]);

    }
    return obj[key];
}

function S4()
{
    return (((1+Math.random())*0xFFFFF)|0).toString(36);
}
function NewGuid()
{
    return Date.now().toString(36)+("-"+S4()+S4());
}



//每次进入的时候都要进行app端连接。
function loadWebSocket() {


    var baseUrl = getSearchString("wsurl");
    if(!baseUrl)
        baseUrl="ws://127.0.0.1:9988";

    output("Connecting to WebSocket server at " + baseUrl + ".");
    var socket = new WebSocket(baseUrl);


    socket.onclose = function () {
        console.error("web channel closed");
        outServer();
        heartCheck.start();
    };
    socket.onerror = function (error) {
        outServer();
        console.error("web channel error: " + error);
    };
    socket.onopen = function () {
        heartCheck.reset();
        new QWebChannel(socket, function (channel) {
            // make dialog object accessible globally
            window.gui = channel.objects.gui;
            window.phone = channel.objects.phone;


            //呼出  连接上sipphone 之后才会进入到此流程中
            window.callf=function(id,name) {

                //开始拨打
                if(!checkSipPhone()){alert('确认在sipPhone进行登录注册。');return false;}
                if(!checkCallTag()){alert('稍等，上一通电话马上处理完。');return false;}
                window.call_unique_id =NewGuid();//唯一的callid,挂断等操作需要使用。
                call_list_push( window.call_unique_id );//将uniqueid推到数组中
                startCallCheck(
                    function(){
                        //获取当前用户默认的线路
                        getDefaultLine(function(lineInfo){

                            if (!lineInfo)return;
                            var call_prefix=lineInfo.call_prefix;
                            var inter_ip=lineInfo.inter_ip;

                            getPhone(id,'',name,function(phoneNumber){


                                if (!phoneNumber) return;
                                let fullCallNumber=call_prefix+phoneNumber+'-'+inter_ip;
                                window.destination_number=phoneNumber;

                                callWidge();//展示拨打框框
                                youCanNotCallNow();
                                var acc_unique = window.uniqueAccount;

                                var callParam = {
                                    "called":fullCallNumber, //被叫号码
                                    "acc_unique":acc_unique, //使用哪个账户呼叫
                                    "call_unique":call_unique_id
                                };
                                phone.makeCall(callParam, function (errmsg) {
                                    if (errmsg){
                                        alert('请确认您的音频设置正确。');
                                        console.error(errmsg);
                                        closeCallWidge();
                                    }
                                });

                            });



                        }) ;

                    }

                );



            }





            //主动挂断
            window.hangupf= function () {
                var callList=call_unique_id_list;

                for(var x in callList){
                    var call_unique = callList[x];
                    var hangupParam = {
                        "call_unique":call_unique
                    };
                    phone.hangup(hangupParam, function (errmsg) {

                        call_list_pop( call_unique );
                        if (errmsg)
                            console.log(errmsg);
                    });
                }



            }

            //通话状态监听

            window.callstatuschange=function(){

                for(unique in phone.callList){

                    var call = phone.callList[unique];
                    //role: 0 去电， 1 来电
                    //state:
                    //1 呼出
                    //2 呼入
                    //3 回铃
                    //4 接通中
                    //5 接通成功
                    //6 挂断
                    let localUrl=call.localUri.uri;
                    let state=call.state;
                    let stateText=call.stateText;
                    let callTime=call.callTime;
                    let connectDuration=call.connectDuration;
                    let totalDuration=call.totalDuration;
                    let destination_number=window.destination_number;
                    let call_unique_id=call.unique;
                    let lastReason=call.lastReason;


                    console.log('status:'+state);
                    console.log('lastReason:'+lastReason);
                    windgetContorl(state);
                    var data={
                        'destination_number':destination_number,
                        'total_duration':totalDuration,
                        'duration':connectDuration,
                        'call_unique_id':call_unique_id,
                        'callTime':callTime   //以服务器为准
                    };

                    var call_info_detail={
                        'call_unique_id':call_unique_id,
                        'data':data
                    };


                    if(state==5){
                        callWidge();
                        goStart();//开始展示时间
                        callInfoListpush(call_info_detail);
                    }else if(state==6){

                        console.log('call_unique_id:'+call_unique_id+'||'+window.call_unique_id);

                        if(call_unique_id === window.call_unique_id){

                            // console.log('REPEAT:call_unique_id:'+call_unique_id+'||'+window.call_unique_id);
                            chargeExtension(data);//进行计费处理 //以服务端的数据为准。

                            call_list_pop( call_unique_id );

                            callInfoListpop( call_unique_id );

                            setTimeout(youCanCallNow,3000);

                            changeCallWidget(2);//挂机显示，并关闭

                        }

                    }else if(state==3){

                        callWidge();
                    }



                }

            }

            function listusers(){
                window.uniqueAccount =  '';
                window.urlAccount =  '';
                for(let i in window.phone.accountList){

                    var account = phone.accountList[i];
                    window.uniqueAccount = account.unique || '';
                    window.urlAccount = account.uri.uri || '';

                }
            }

            function registerUser(data,callback){
                //添加账号


                var accountParam={
                    "unique": NewGuid(),//唯一ID，注册多个账号的时候不能重复
                    "iduri":"sip:"+data.extension_account+"@"+data.extension_ip, //格式 sip:用户名@服务器IP:端口 或者 "\"Display Name\" <sip:account@provider>
                    "reguri":"sip:"+data.extension_ip,	//格式 sip:服务器IP:端口
                    "username":data.extension_account,
                    "password":data.extension_pass,
                    "registerOnAdd":true, //添加后是否自动注册
                    "timeout":60  //注册间隔，单位秒
                };
                phone.addAccount(accountParam, function (errmsg) {
                    if (errmsg){callback();}
                    else{return false;}
                });

            }

            listusers();
            phone.accountChanged.connect(listusers);  //更新用户状态  listusers可以为自定义的其他状态信息处理函数



            //呼叫状态改变
            phone.callChanged.connect(callstatuschange);


            //注册检验
            if(!checkSipPhone())getDefaultExtension(function(data){
                registerUser(data,function(){
                    console.error('注册失败，检查分机账号，密码，以及对应主机IP和端口是否正确');
                });
            });

//-------------------------channel-----------------------------------

        });





    };
}


//首先获取用户电话 然后进行拨打
function getPhone(id,type,name,callback){
    var url="/user/extension/getPhone";
    var data={id:id,type:type,name:name};

    $.post(url,data,function(msg){
        if(msg.code!=0){
            console.log(msg.msg);
            return false;
        }else{

            callback(msg.data.phoneNumber);
        }

    });




}


//首先获取用户默认线路 然后再进行电话确认
function getDefaultLine(callback){
    var url="/user/extension/getDefaultLine";

    $.post(url,{},function(msg){
        if(msg.code!=0){
            console.log(msg.msg);
            return false;
        }else{
            callback(msg.data);
        }

    });




}

//首先获取用户默认线路 然后再进行电话确认
function getDefaultExtension(callback){
    var url="/user/extension/getDefaultExtension";

    $.post(url,{},function(msg){
        if(!msg){
            console.log('获取默认分机失败！');
            return false;
        }else{
            callback(msg);
        }

    });




}



/*

  控制通话小弹窗的展示    对于此类展示的功能，尽量不要带有后台逻辑。 纯粹点

*/
function windgetContorl(status){


    switch(status){

        case 1://呼出
            changeCallWidget(0);
            break;
        case 4://呼叫中
            break;
        case 5://通话中
            changeCallWidget(1);
            break;
        case 6://挂断
            changeCallWidget(2);
            break;


    }


}



/**
 * 关于通话窗口显示方面的操作
 *
 *
 */

//点击拨打是后进行展示
function callWidge(){

    $("#tips_model").modal('hide');
    $("#main-call").removeClass("hidden");

}
// 正在呼叫
function smallWidge(){

    $("#online-call").addClass("hidden");
    $("#online-call-small").removeClass("hidden");

}

// 正在呼叫
function recoverWidge(){

    $("#online-call").removeClass("hidden");
    $("#online-call-small").addClass("hidden");

}

//关闭小窗口
function closeCallWidge(){

    setTimeout( function(){$("#main-call").addClass("hidden");reSet();changeCallWidget(3)} ,1600);

}

function reviewCallWdget(){

    if(getStorage()){
        callWidge();//展示通话窗口
    }

}

//对应不同呼叫状态的文字和图片展示  单纯前端展示
function changeCallWidget(i){
    i=i||0;
    var textObj=[
        { 'tel_text1':'正在呼叫...','tel_text2':'正在呼叫...','tel_img1':'hujiao01.png','tel_img2':'hujiao02.png',},
        { 'tel_text1':'通话中...','tel_text2':'通话中...','tel_img1':'yuyin01.png','tel_img2':'yuyin02.png',},
        { 'tel_text1':'正在挂断...','tel_text2':'正在挂断...','tel_img1':'guaduan.png', 'tel_img2':'hujiao02.png',},
        { 'tel_text1':'...', 'tel_text2':'...','tel_img1':'guaduan.png', 'tel_img2':'hujiao02.png', }];
    $("#main-call .tel_text1").text(textObj[i].tel_text1);
    $("#main-call .tel_text2").text(textObj[i].tel_text2);
    $("#main-call .tel_img1").attr('src' , $("#main-call .tel_img1").attr('src').slice(0,$("#main-call .tel_img1").attr('src').lastIndexOf('/')+1 ) +  textObj[i].tel_img1);
    $("#main-call .tel_img2").attr('src' , $("#main-call .tel_img2").attr('src').slice(0,$("#main-call .tel_img2").attr('src').lastIndexOf('/')+1 ) +  textObj[i].tel_img2);
    if(i==2){closeCallWidge();}//当要结束的时候 另外 关闭


}



/*
    电话操作相关
*/

//挂断电话并且进行关窗操作
function hangupAndclose(){

    changeCallWidget(2);
    hangupf();

}




//异常挂断
function specialClose(call_unique_id){

    let call_detail=callInfoListpop(call_unique_id);
    //获取当前通话面板上面的时间数据
    if(call_detail){
        //通话时长获取
        call_detail.data.duration= call_duration;
        //计费相关
        chargeExtension(call_detail.data);
        hangupAndclose();
    }


}

//浏览器关闭
function closeExplorer(){

    let call_detail=callInfoListpop(call_unique_id);
    //获取当前通话面板上面的时间数据
    if(call_detail){
        //通话时长获取
        call_detail.data.duration= call_duration;
        //计费相关
        chargeExtensionSyn(call_detail.data);

    }
    hangupf();


}




/**
 *控制打电话Tag ,允许打电话与否
 */

function checkSipPhone(){
    if( ( typeof  uniqueAccount !='undefined' &&uniqueAccount == '' ) ||( typeof  urlAccount !='undefined' && urlAccount ==   '') ){
        return false;
    }
    return true;
}

function checkCallTag(){

    // return window.call_unique_id_list.length<1;//通过idlist进行判断
    return window.youcancall; //通过youcancall判断

}

//可以呼叫
function youCanCallNow(){
    if(window.call_unique_id!=''  &&  typeof window.call_unique_id!=='undefined' )    {
        setTimeout(function(){ window.youcancall=1;},3600);
    }else{
        window.youcancall=1;  //对于刚进入的用户 或者进行刷新之后  直接开通
    }


}

//暂停呼叫
function youCanNotCallNow(){

    window.youcancall=0;

}


/**
 * 初始化全局存储，包括通话ID 通话详情等等 以及对应的push 和Pop 操作
 *
 */

//初始化call_unique_id_list
function initilizeCallList(){
    if(typeof window.call_unique_id_list =='undefined')
        window.call_unique_id_list=[];
    if(typeof window.telInfoByUnique =='undefined')
        window.telInfoByUnique=[];

}


function call_list_pop(call_unique_id){

    var index = window.call_unique_id_list.indexOf(call_unique_id);
    if (index > -1) {
        setTimeout(function(){ window.call_unique_id_list.splice(index, 1);},1500);

    }



}

function call_list_push(call_unique_id){

    window.call_unique_id_list.push(call_unique_id);

}


//将通话详情弹出数组
function callInfoListpop(call_unique_id){

    var telInfoByUnique=window.telInfoByUnique;
    for(var i in telInfoByUnique){
        if(telInfoByUnique[i].call_unique_id==call_unique_id){
            //unset
            // setTimeout(function(){ telInfoByUnique.splice(i, 1);},1000);
            return telInfoByUnique.splice(i, 1)[0];
        }

    }

}

//将通话详情压入数组
function callInfoListpush(call_info_data){

    window.telInfoByUnique.push(call_info_data);

}






/**
 * 通话前置条件检查
 */

function startCallCheck(callback){

    var url="/user/extension/startCalling";
    if(window.call_unique_id =='' )return false;
    var data={'call_unique_id': window.call_unique_id}

    $.post(url,data,function(msg){
        if(msg.code==0){
            callback();
        }else{
            console.log(msg.msg);
        }

    });



}

/**
 * 收费
 * @param data
 */
function chargeExtension(data){

    var url='/user/extension/extensionCharge';

    $.post(url,data,function(msg){
        if(msg.code!=0){
            // console.log(msg.msg);
            return false;
        }else{
            return true;
        }

    });


}



/**
 * 同步收费
 * @param data
 */
function chargeExtensionSyn(data){
    var url='/user/extension/extensionCharge';

    $.ajax({
            url: url,
            data : data,
            async: false,
            success :function(msg){
                if(msg.code!=0){
                    // console.log(msg.msg);
                    return false;
                }else{
                    return true;
                }

            },
            fail:function(){

            }
        }
    );


}





/**
 * 定时时间器的生成和操作
 */
var timer;
//开始计时
function goStart() {
    var start=Date.now();
    timer=setInterval(step,1000);
    function step(){



        let msecond=(Date.now()-start);
        let s=parseInt(msecond/1000);
        let t=toDub(Math.floor( s/3600 ))+":"+toDub(Math.floor( (s/60) )%60)+":"+toDub(s%60) ;
        // console.log(toDub(Math.floor( s/3600 ))+"："+toDub(Math.floor( s/60 ))+"："+toDub(s) );
        $('#main-call .tel_timer').text(t);
        window.call_duration=msecond;  //对于当前持续了多少好眠的存储
    }
};
//重置
function reSet() {
    clearInterval(timer);
    $('#main-call .tel_timer').text('');
}
//补零
function toDub(n){
    return n<10?"0"+n:""+n;
}


/**
 * 对于 call_unique_id的存储
 */

function storage(){
    if (window.localStorage) {
        if(window.call_unique_id!=''&& typeof window.call_unique_id !=='undefined'){
            localStorage.setItem("call_unique_id",call_unique_id);
        }
    }
}
function clearStorage(){
    if (window.localStorage) {
        localStorage.removeItem("call_unique_id");
    }
}

function getStorage(){
    if(window.call_unique_id!=''&& typeof window.call_unique_id !=='undefined'){
        return true;
    }

}


