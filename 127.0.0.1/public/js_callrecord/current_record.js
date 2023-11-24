/*
    Author:Li Ning
    作用：同步check input  调用只需要 赋值class_names


*/

/*
  输入 {class_names:['flexout_1','flexout_1p',......]}
  函数会根据输入的类名  对应起来 ， 会将页面中 对应的两个类下面  对应位置 的checkbox 进行 check绑定
*/

let checkO=function(o) {
    let cnames = o.class_names;
    for (let cnames_index in cnames) {

        $('.' + cnames[cnames_index]).find('input:checkbox').each(function (index) {
            //里面是对于 checkbox 进行控制和展示
            $(this).bind('click', function () {

                if ($(this).is(':checked')) {
                    //如果是勾上那么对于另外的其他类同样位置的 也勾上
                    for (let i in cnames) {
                        // debugger;
                        if (i == cnames_index) continue;
                        let thischeckbox=$('.' + cnames[i]).find('input:checkbox').eq(index);
                        thischeckbox.prop('checked', true);

                        //判断同级下后面是否有隐藏的div 进行显示
                        // let sblings=thischeckbox.parent().children();
                        // debugger;
                        // $(sblings[2]).removeClass('hidden');
                        // $(sblings[3]).removeClass('hidden');

                    }
                } else {
                    //如果是不勾上那么对于另外的其他类同样位置的 也不勾上
                    for (let i in cnames) {
                        // debugger;
                        if (i == cnames_index) continue;
                        let thischeckbox=$('.' + cnames[i]).find('input:checkbox').eq(index);
                        thischeckbox.prop('checked', false);

                        //判断同级下后面是否有隐藏的div 进行显示
                        // let sblings=thischeckbox.parent().children();
                        // $(sblings[2]).addClass('hidden');
                        // $(sblings[3]).addClass('hidden');
                    }
                }
            });

        });

    }

};
checkO({class_names:['flexout_1','flexout_1p']});
checkO({class_names:['flexout_2','flexout_2p']});
checkO({class_names:['flexout_3','flexout_3p']});
checkO({class_names:['flexout_4','flexout_4p']});
checkO({class_names:['flexout_5','flexout_5p']});


/*
    输入 包括select input
    {class_name:[['name1','name2'],[...],..]}
    数组内每组只能是两个值
    每组的两个类input当change时候 value会进行同步
*/

let inputO=function(o){
    let put_class=o.class_names;
    for(let i in put_class){

        //对每一对input进行同步处理
        for( let ii in put_class[i]){
           let elems=document.getElementsByClassName(put_class[i][ii]);
           //所获取的elems是唯一的
            if(typeof elems[0] != "undefined" ){
                elems[0].addEventListener("change",function(e){
                    if(ii==0){
                        // debugger;
                        let elem=document.getElementsByClassName(put_class[i][1]);
                        elem[0].value=this.value;
                    }else{
                        // debugger;
                        let elem=document.getElementsByClassName(put_class[i][0]);
                        elem[0].value=this.value;
                    }

                });
            }

        }
    }
}

inputO({class_names:[
        ['flexout_2_input_1','flexout_2p_input_1'],
        ['flexout_2_input_2','flexout_2p_input_2'],
        ['flexout_2_input_3','flexout_2p_input_3'],
        ['flexout_2_input_4','flexout_2p_input_4'],

        ['flexout_3_input_1','flexout_3p_input_1'],
        ['flexout_3_input_2','flexout_3p_input_2'],
        ['flexout_3_input_3','flexout_3p_input_3'],

        ['flexout_4_input_1','flexout_4p_input_1'],
        ['flexout_4_input_2','flexout_4p_input_2'],
        ['flexout_4_input_3','flexout_4p_input_3'],

        ['flexout_3_select_1','flexout_3p_select_1'],
        ['flexout_3_select_2','flexout_3p_select_2'],
        ['flexout_3_select_3','flexout_3p_select_3'],

        ['flexout_4_select_1','flexout_4p_select_1'],
        ['flexout_4_select_2','flexout_4p_select_2'],
        ['flexout_4_select_3','flexout_4p_select_3'],

        ['flexout_5_select_1','flexout_5p_select_1']


    ]});

/*
  点击或选择 所需条件时候，进行页面中对应的显示或隐藏的动作  如果所属Class下面的checkbox  全部未点击则隐藏，否则显示出来
[ ['class1','class2'],[...] ] class1 为悬浮窗， class2为 页面中的  页面中 的 flex 为important 故加了 hidden_force的css

0326增加 ：上述逻辑继续 施行，增加了 对于checkbox  如果未选取name将进行隐藏
*/

let showO=function(o){
    let lines_class=o.class_names;
    for( let i in lines_class){
        if ( i>=lines_class.length) continue;
        let checkv=false;
        $('.'+lines_class[i][0]).find('input:checkbox').each(function(ii,v){

            if ($(this).is(':checked')){
                $('.'+lines_class[i][1]).find('input:checkbox').eq(ii).parent().removeClass('hidden');
                // $(this).parent().addClass('hidden');//针对的是 里面 的 checkbox 一项进行 展示或隐藏  （优化 部分）
                checkv=true;
            }else{

                $('.'+lines_class[i][1]).find('input:checkbox').eq(ii).parent().addClass('hidden');
            }

        });
        if (checkv){ $('.'+lines_class[i][1]).removeClass('hidden_force');}
        else{ $('.'+lines_class[i][1]).addClass('hidden_force');}
    }

};

showO({class_names:[
    ['flexout_1','flexout_1_total' ],
    ['flexout_2','flexout_2_total' ],
    ['flexout_3','flexout_3_total' ],
    ['flexout_4','flexout_4_total' ],
    ['flexout_5','flexout_5_total' ]

]});






function saveSearchCondition(){
    let phoneStateStr='';
    let talkTimeStr='';
    let frequencySpeakingStr='';
    let clientToneStr='';
    let reviewThingStr='';
    $("#phoneState").find('input:checkbox').each(function(i,v){
        if($(this).is(':checked'))
             phoneStateStr+=','+i;
    });
    phoneStateStr=phoneStateStr.substr(1);
   $("#talkTime").find('input:checkbox').each(function(i,v){
       if($(this).is(':checked'))
            talkTimeStr+=','+i;
    });
    talkTimeStr=talkTimeStr.substr(1);
   $("#frequencySpeaking").find('input:checkbox').each(function(i,v){
      if($(this).is(':checked'))
          frequencySpeakingStr+=','+i;
    });
    frequencySpeakingStr=frequencySpeakingStr.substr(1);
   $("#clientTone").find('input:checkbox').each(function(i,v){
     if($(this).is(':checked'))
         clientToneStr+=','+i;
    });
    clientToneStr=clientToneStr.substr(1);

    $("#reviewThing").find('input:checkbox').each(function(i,v){
     if($(this).is(':checked'))
         reviewThingStr+=','+i;
    });
    reviewThingStr=reviewThingStr.substr(1);



    data={'phoneStateStr':phoneStateStr,'talkTimeStr':talkTimeStr,'frequencySpeakingStr':frequencySpeakingStr,'clientToneStr':clientToneStr,'reviewThingStr':reviewThingStr};



    $.ajax({
        url:window.location.origin+'/user/callrecord/update_search_condition',
        method:'post',
        data:data,
        dataType:'json',
        success:function(data) {
            console.log('条件保存成功');
        },
        error:function(e){
            console.log(e);
        }
    });


}


function showCondition(){
    $.ajax({
        url:window.location.origin+'/user/callrecord/get_search_condition',
        method:'post',
        data:data,
        dataType:'json',
        success:function(msg) {
            //对于应点击或显示的数据进行展示
            
            let data=msg.data;
            if(data){
                let phoneStateStr=data.phoneStateStr;
                let talkTimeStr=data.talkTimeStr;
                let frequencySpeakingStr=data.frequencySpeakingStr;
                let clientToneStr=data.clientToneStr;
                let reviewThingStr=data.reviewThingStr;
                if(phoneStateStr&&phoneStateStr!=''){
                    let phoneStateStrElemArr=phoneStateStr.split(',');
                    for(let i in phoneStateStrElemArr){
                        if(!phoneStateStrElemArr[i])continue;
                        $(".flexout_1_total").find('input:checkbox').eq( phoneStateStrElemArr[i]  ).removeClass('hidden').parent().removeClass('hidden');
                        var phoneStateshow=true;
                    }
                    if(typeof phoneStateshow !="undefined")$('.flexout_1_total').removeClass('hidden_force');
                }

               if(talkTimeStr&&talkTimeStr!=''){
                    let talkTimeStrElemArr=talkTimeStr.split(',');
                    for(let i in talkTimeStrElemArr){
                        if(!talkTimeStrElemArr[i])continue;
                        $(".flexout_2_total").find('input:checkbox').eq( talkTimeStrElemArr[i]  ).parent().removeClass('hidden');
                        var talkTimeshow=true;
                    }

                   if(typeof talkTimeshow !="undefined")$('.flexout_2_total').removeClass('hidden_force');
                }
               if(frequencySpeakingStr&&frequencySpeakingStr!=''){
                    let frequencySpeakingStrElemArr=frequencySpeakingStr.split(',');
                    for(let i in frequencySpeakingStrElemArr){
                        if(!frequencySpeakingStrElemArr[i])continue;
                        $(".flexout_3_total").find('input:checkbox').eq( frequencySpeakingStrElemArr[i]  ).parent().removeClass('hidden');
                        var frequencySpeakingshow=true;
                    }
                   if(typeof frequencySpeakingshow !="undefined")$('.flexout_3_total').removeClass('hidden_force');
                }
               if(clientToneStr&&clientToneStr!=''){
                    let clientToneStrElemArr=clientToneStr.split(',');
                    for(let i in clientToneStrElemArr){
                        if(!clientToneStrElemArr[i])continue;
                        $(".flexout_4_total").find('input:checkbox').eq( clientToneStrElemArr[i]  ).parent().removeClass('hidden');
                        var clientToneshow=true;
                    }
                   if(typeof clientToneshow !="undefined")$('.flexout_4_total').removeClass('hidden_force');
                }
               if(reviewThingStr&&reviewThingStr!=''){
                    let reviewThingStrElemArr=reviewThingStr.split(',');
                    for(let i in reviewThingStrElemArr){
                        if(!reviewThingStrElemArr[i])continue;
                        $(".flexout_5_total").find('input:checkbox').eq( reviewThingStrElemArr[i]  ).parent().removeClass('hidden');
                        var reviewThingshow=true;
                    }
                   if(typeof reviewThingshow !="undefined")$('.flexout_5_total').removeClass('hidden_force');
                }


            }



        },
        error:function(e){
            console.log(e);
        }
    });

}

function addConfigSubmit(){
    //进行逻辑判断  ，对应的 行显示的 显示 隐藏的隐藏
    showO({class_names:[
            ['flexout_1','flexout_1_total' ],
            ['flexout_2','flexout_2_total' ],
            ['flexout_3','flexout_3_total' ],
            ['flexout_4','flexout_4_total' ],
            ['flexout_5','flexout_5_total' ]

        ]});
    //进行搜索数据保存
    saveSearchCondition();
return  false;
}


/*
dev5 原有 JS
*/




function moreCondition(){
    $("#add_config").modal('show');
}


//添加配置中点击切换
$(".left-content .btn").click(function(){
    $(this).addClass('btn-active').siblings().removeClass('btn-active');
    type = $(this).data("type");
    $("#" + type).removeClass("hidden").siblings().addClass('hidden');
})

//复选框选中后显示后面的输入框
$(".optins input:checkbox").change(function(){
    type = $(this).data("type");
    if($(this).is(":checked")){
        $("#"+ type).removeClass("hidden");
    }else{
        $("#"+ type).addClass("hidden");
    }
});


//点击收起
$(".shrink").click(function(){
    $(".right-condition").toggleClass('displayNO');
    if($(".right-condition").hasClass('displayNO')){
        $(".left-condition").css('width','100%');
        $(".shrinkIcon").css('border-left','1px solid #fff');
        $(".shrinkIcon").css('border-bottom','1px solid #fff');
        $(".shrinkIcon").css('border-right','0');
        $(".shrinkIcon").css('border-top','0');
        $("#shrink span").text("展开");
        $(".shrinkIcon").css('margin-left',"3px");
        $(".left-condition").css("margin-right","0%");
        $(".shrink").css("left","99.7%");
    }else{
        $(".left-condition").css('width','84%');
        $(".shrinkIcon").css('border-right','1px solid #fff');
        $(".shrinkIcon").css('border-top','1px solid #fff');
        $(".shrinkIcon").css('border-left','0');
        $(".shrinkIcon").css('border-bottom','0');
        $("#shrink span").text("收起");
        $(".shrinkIcon").css('margin-left',"-3px");
        $(".left-condition").css("margin-right","0.8%");
        $(".shrink").css("left","99%");
    }
})


function display_intensiondiv(){
    $(".right-condition").removeClass('displayNO');
    $(".left-condition").css('width','84%');
    $(".shrinkIcon").css('border-right','1px solid #fff');
    $(".shrinkIcon").css('border-top','1px solid #fff');
    $(".shrinkIcon").css('border-left','0');
    $(".shrinkIcon").css('border-bottom','0');
    $("#shrink span").text("收起");
    $(".shrinkIcon").css('margin-left',"-3px");
    $(".left-condition").css("margin-right","0.8%");
    $(".shrink").css("left","99%");
}

 //新建拨打任务
    // function addtask(obj){
    //     if($('.all_checked_count').is(':checked')){
    //         showadd(obj);
    //     }
    //     var checkedsub = $("input[name='checkids'][type='checkbox']:checked").length; //获取选中的checkids的个数
    //     if(checkedsub > 0){
    //         showadd(obj);
    //     }else{
    //         alert('请选择呼叫电话');
    //     }
    // }


/*
dev5 上面的原有 JS -------------------END

下面是元current_html 内部的JS
*/
