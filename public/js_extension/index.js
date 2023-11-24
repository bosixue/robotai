/*
增加分机弹框
*/
function addExtention() {
    $("#extensionAdd").modal('show');
}


/*
编辑分机弹框
*/
function editExtention(id) {
    
    get_one_data(id,function(msg){
      //对于数据进行展示
       msg=msg.data;//单条记录 直接去data
       var html = $('#extensionEdit').html();
       for(var i in msg){
         
          html = html.replace('{%id%}', msg[i].id);
          html = html.replace('{%extension_account%}', msg[i].extension_account);
          html = html.replace('{%extension_pass%}', msg[i].extension_pass);
          html = html.replace('{%extension_ip%}', msg[i].extension_ip);
         
       }
      
       $('#extensionEdit-1').html('').html(html);
       $('#extensionEdit-1').find('select').children('option[value='+msg[i].tel_line_id+']').prop('selected',true);

    });

    $("#extensionEdit-1").modal('show');
}


/*
增加分机---AJAX
*/
function extensionAdd(){
  
  let data=$('#formScenariosform1').serialize();
  let url='add';
   $.ajax({
            type:'POST',
            data:data,
            url:url,
            success:function(msg){
              $("#extensionAdd").modal('hide');
              location.reload();
              alert(msg.msg);
            },
            error:function(){

            }
        });
  
}

function extensionEdits(){

    let data=$('#formScenariosform2').serialize();
    let url='add';
    $.ajax({
        type:'POST',
        data:data,
        url:url,
        success:function(msg){
            $("#extensionEdit").modal('hide');
            location.reload();
            alert(msg.msg);
        },
        error:function(){

        }
    });

}



function del(){
  
  //全删
  if($('[name=DataCheck_all]').is(':checked')){
    var extension_id=$('#query_extension_id').val();
    var ids=['all'];
  }else{
    
    var ids=$('#datalist [name=checkid]:checked').map(function(){return $(this).val();}).get();
    
  }
  
  var data ={ids:ids,extension_id:extension_id};
  var url  ='del';  
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



/*
条件查询 ---AJAX 返回
*/
function query_datas(page,limit){
  let extension_account=$('#query_extension_account').val();
  let url='ajaxExtension';
  
  $('#Nowpagehidden').val(page);
  $('#Nowlimithidden').val(limit);
  
  let data={extension_account:extension_account,page:page,pageLimit:limit,};
  
  $.ajax({
    url:url,
    data:data,
    method:'post',
    success:function(msg){
      
        var count=msg.data.totalCount;  
        var htmlAll='';
        var return_data=msg.data.data;
        var rownum=(page-1)*limit;
        for(var i in return_data){
          
          let html=$('#listTemp1').children().html();//tbody
          html = html.replace('{%i%}', ++rownum);
          html = html.replace('{%extension_account%}', return_data[i].extension_account);
          html = html.replace('{%extension_pass%}', return_data[i].extension_pass);
          html = html.replace('{%extension_ip%}', return_data[i].extension_ip);
          html = html.replace('{%line_name%}', return_data[i].tel_line_name);
          html = html.replace('{%create_time%}', return_data[i].create_time);
          if(return_data[i].default == 1){
            html =  html.replace('{%open%}','hidden');
          }else{
            html = html.replace('{%openalready%}', 'hidden');
          }
          
          htmlAll+=html;
       }
       
        $('#datalist').empty().html(htmlAll);
        $('#countNum').text(count);
        Paging.paging(page, count, limit);  
      },
    error:function(){

    }
  });
  
}

//展示数据
function get_one_data(id,callback){
  let data={'id':id};
  let url='ajaxExtension';
 $.ajax({
            type:'POST',
            data:data,
            url:url,
            success:function(msg){
                 callback(msg.data) ;
            },
            error:function(){

            }
        });
}

function enableExtension(id){
  let data={'id':id};
  let url='setDefault';
  $.ajax({
            type:'POST',
            data:data,
            url:url,
            success:function(msg){
              $("#tips_model").modal('hide');
               alert(msg.msg) ;
               location.reload();
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

var Paging = new Paging01();
Paging.init_args({
    page: 1, //初始页码
    limit: 10, //初始每页显示的数据量
    paging_class: 'paging', //放置分页的class
    callback: query_datas //回调函数 比如show_datas(页码, 显示条数)
});


 //check事件
$(document).ready(function(){
  
    $('.all_checked_this_page').click(function(){
      //当没有选中某个子复选框时，check-all取消选中
      if ($(this).is(':checked')) {
          $('[name=checkid]').prop('checked',true);
      }else{
          $('[name=checkid]').prop('checked',false);
      }
     
  });
  
   $('[name="checkid"]').click(function(){
      //当没有选中某个子复选框时，check-all取消选中
      if (!$(this).is(':checked')) {
          $("input.all_checked_this_page[type='checkbox']").prop("checked", false);
          $(".all_checked_all_page").prop("checked", false);
      }
     
  });
  
  $('.all_checked_all_page').click(function(){
      if($(this).is(':checked')){
        $('[name=checkid]').prop('checked',true);
        $('#totalCount').removeClass('hidden');
      }else{
         $('#totalCount').addClass('hidden');
          $('[name=checkid]').prop('checked',false);
      }
  });


var page = Number($('#Nowpagehidden').val());
var limit =Number($('#Nowlimithidden').val());
var count =Number($('#countNum').text());

  if( page > 1 && count <= (page-1)*limit ){
    var lessPage = location.search.replace(/page=([^&]*)&*?/,function(){return 'page='+(Number(arguments[1])-1) }  );
    location.href=location.origin+location.pathname+lessPage;
    
  }


}); 
