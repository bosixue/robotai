//添加、编辑线路显示
function addLine(num){
    if(num) {
        //编辑线路
        var url = "/user/Line/editLine_view"
        var data={
            'id':num,
            'group_id':group_id,
        };
        $.ajax({
            type:'POST',
            dataType:'json',
            url:url,
            data:data,
            success:function(result){

                if(result.code==0){
                    $("#e-linename").val(result.data.name);
                    $("#e-interface-IP").val(result.data.inter_ip);
                    $('#type_link').val(result.data.type_link);
                    $("#e-call-prefix").val(result.data.call_prefix);
                    $("#e-cost-price").val(result.data.sales_price);
                    $("#remarks").val(result.data.remark);
                    $("#line_id").val(result.data.id);

                }else{

                    alert(result.msg);
                    $("#line-add").modal('hide');

                }
            },
            error:function(){
                console.log('错误');
            },

        });
        $('#add_editLine').html('编辑线路');
        $('#line-add .submit-btn').html('保存');

    } else {
        //清空编辑的数据
        $("#e-linename").val('');
        $("#e-interface-IP").val('');
        $("#e-call-prefix").val('');
        $("#e-cost-price").val('');
        $("#remarks").val('');
        $("#line_id").val('');
        $("#type_link").val('');

        $('#add_editLine').html('添加线路');
        $('#line-add .submit-btn').html('确定');
    }
    $("#line-add").modal('show');
}

//添加和 编辑线路方法
function addLineInGroup(){
    var type=1;
    var name =$('#e-linename').val();
    var inter_ip =$("#e-interface-IP").val();
    var call_prefix =$("#e-call-prefix").val();
    var remark =$("#remarks").val();
    var type_link = $('#type_link').val();
    var line_id = $('#line_id').val();
    var url = '/user/Line/add_line';
    var data={
        'type':type,
        'id':line_id,
        'group_id':group_id,
        'name' :name,
        'inter_ip':inter_ip,
        'call_prefix':call_prefix,
        'remark':remark,
        'type_link': type_link
    };
    $.ajax({
        type:'POST',
        dataType:'json',
        url:url,
        data:data,
        success:function(result){
            if(result.code==0){
                $("#line-add").modal('hide');
                alert(result.msg);
                //读取当前页码 好刷新 免得从1页开始
                query_datas(window.page,window.limit);

            }else{
                $("#line-add").modal('hide');
                alert(result.msg);
            }
        }
    });

}

/*
条件查询 ---AJAX 返回
*/
function query_datas(page,limit){

    let url='/user/line/getLineInGroup';
    window.page=page;
    window.limit=limit;
    var line_name=$('#line_name').val();
    $.ajax({
        url:url,
        data:{group_id:group_id,line_name:line_name,page:page,pageLimit:limit},
        method:'post',
        success:function(msg){
            if(msg.data.data.length === 0){
                $('#line_in_group_list').empty();
                $('#consumeempty').show();
            }else{
                $('#consumeempty').hide();
                var count=msg.data.totalCount;
                var htmlAll='';
                var return_data=msg.data.data;
                var rownum=(page-1)*limit;
                for(var i in return_data){
                    let html=$('#line_detail_template').children().html();//tbody
                    html = html.replace('{%i%}', ++rownum);
                    html = html.replace('{%name%}', return_data[i].name);
                    html = html.replace('{%id%}', return_data[i].id);
                    html = html.replace('{%id1%}', return_data[i].id);
                    html = html.replace('{%group_name%}', return_data[i].group_name);
                    html = html.replace('{%create_time%}', return_data[i].create_time);
                    if(return_data[i].allow_operate==0){html = html.replace('{%hidden0%}', 'hidden');html = html.replace('{%hidden1%}', 'hidden');}
                    htmlAll+=html;
                }


                $('#line_in_group_list').empty().html(htmlAll);
                $('#countNum').text(count);
                Paging1.paging(page, count, limit);
            }
        },
        error:function(){

        }
    });

}

function delLine(id){

    var data ={id:id,gourp_id:group_id};
    var url  ='/user/line/delInGroup';
    $.ajax({
        type:'POST',
        data:data,
        url:url,
        success:function(msg){
            $("#tips_model").modal('hide');
            location.reload();
            alert(msg.msg);
        },
        error:function(){

        }
    });



}



/**
 * 配置分页
 *
 * @param int args.page 页码 页码参数统一"page"
 * @param int args.limit 每页显示的数量 参数统一"limit"
 * @param string args.paging_class 放置分页的class
 * @param function args.callback 回调函数
 */
window.page = Number($('#Nowpagehidden').val());
window.limit =Number($('#Nowlimithidden').val());
var count =Number($('#countNum').text());
var Paging1 = new Paging01();
Paging1.init_args({
    page: 1, //初始页码
    limit: 10, //初始每页显示的数据量
    paging_class: 'paging', //放置分页的class
    callback: query_datas, //回调函数 比如show_datas(页码, 显示条数)
    key:1,
});

query_datas( page ,limit);
$(document).ready(function(){



    $('.all_checked_this_page').click(function(){
        //当没有选中某个子复选框时，check-all取消选中
        if ($(this).is(':checked')) {
            $('input.check_id[type=\'checkbox\']').prop('checked',true);
        }else{
            $('input.check_id[type=\'checkbox\']').prop('checked',false);
        }

    });

    $('#line_in_group_list').delegate('.check_id','click',function(){
        //当没有选中某个子复选框时，check-all取消选中
        if (!$(this).is(':checked')) {
            $("input.all_checked_this_page").prop("checked", false);
            $("input.all_checked_all_page").prop("checked", false);
        }

    });

    $('.all_checked_all_page').click(function(){
        if($(this).is(':checked')){
            $('input.check_id[type=\'checkbox\']').prop('checked',true);
            $('#totalCount').removeClass('hidden');
        }else{
            $('#totalCount').addClass('hidden');
            $('input.check_id[type=\'checkbox\']').prop('checked',false);
        }
    });




    if( page > 1 && count <= (page-1)*limit ){
        var lessPage = location.search.replace(/page=([^&]*)&*?/,function(){return 'page='+(Number(arguments[1])-1) }  );
        location.href=location.origin+location.pathname+lessPage;

    }



});


