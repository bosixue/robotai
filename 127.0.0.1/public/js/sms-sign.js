/**
 * 短信签名
*/
var Original = {
	sign_name:''
};

//点击提交或更新
function click_submit_sign()
{
  $('#click_submit').unbind('click');
  $('#click_submit').click(function(){
  	var action = $('#action').val();
  	if(action == 'add'){
  		var name = $('#add-sign-name').val();
	    var url = '/user/sms/add_sign';
	    $.ajax({
	      type:"POST",
	      dataType:'json',
	      data:{
	        name:name
	      },
	      url:url,
	      success:function(result){
	        console.log(result);
	        if(result.code == 0){
	          alert('添加成功');
	          $('#newModal').modal('hide');
	          $('#click_query_sign').click();
	        }else if(result.code == 3){
	        	alert(result.msg);
	        }else{
	          alert('添加失败');
	        }
	      },
	      error:function(){
	        alert('添加失败');
	        console.log('错误');
	      }
	    });
  	}else if(action == 'edit'){
  		var url = '/user/sms/update_sign';
  		var id = $('#sign-id').val();
  		var sign_name = $('#add-sign-name').val();
  		if(id == ''){
  			alert('提交失败');
  			return false;
  		}
  		if(sign_name == ''){
  			alert('短信签名不能为空');
  			return false;
  		}
  		if(Original.sign_name == sign_name){
  			alert('提交成功');
  			get_sign(1, 10);
  			$('#newModal').modal('hide');
  			return false;
  		}
  		var data = {
  			id:id,
  			sign_name:sign_name
  		};
  		$.ajax({
  			type:'POST',
  			data:data,
  			url:url,
  			dataType:'json',
  			success:function(result){
  				console.log(result);
  				if(result.code == 0){
  					alert('提交成功');
  					$('#newModal').modal('hide');
  					get_sign(1, 10);
  				}else if(result.code == 3){
  					alert(result.msg);
  				}else{
  					alert('提交失败');
  				}
  			},
  			error:function(){
  				alert('提交失败');
  			}
  		})
  	}
    
  });
}

/**
 * 点击显示编辑界面
 * 
*/
function click_show_edit_interface()
{
	$('.edit_sign').click(function(){
		var id = $(this).data('id');
		$('#pzModalLabel').text('编辑短信签名');
		$('#click_submit').text('保存');
		$('#action').val('edit');
		$('#sign-id').val(id);
		//获取回填的数据
		var url = '/user/sms/get_sign';
		$.ajax({
			type:'POST',
			dataType:"json",
			data:{
				id:id,
			},
			url:url,
			success:function(result){
				if(result.code === 0){
					$('#add-sign-name').val(result.data);
					Original.sign_name = result.data;
					$('#newModal').modal('show');
				}
			}
		});
		
	});
}

/**
 * 点击显示添加界面
*/
function click_show_add_interface()
{
	$('.add_sign').click(function(){
		$('#pzModalLabel').text('添加短信签名');
		$('#click_submit').text('确认');
		$('#action').val('add');
		$('#newModal').modal('show');
	});
}

//点击提交审核
function sumbit_auditing()
{
  $('.auditing_sign').unbind('clicl');
  $('.auditing_sign').click(function(){
    var sign_id = $(this).data('id');
    var url = '/user/sms/auditing_sign';
    $.ajax({
      type:"POST",
      data:{
        sign_id:sign_id,
      },
      dataType:'json',
      url:url,
      success:function(result){
        console.log(result);
        if(result.code == 0){
          alert('提交成功');
          get_sign(1, 10);
        }else{
          alert('提交失败');
        }
      },
      error:function(){
        // console.log()
        alert('提交失败');
      }
    });
  })
}

//获取短信签名数据
function show_sign()
{
  $('#click_query_sign').unbind('click');
  $('#click_query_sign').click(function(){
    get_sign(1, 10);
  });
}
/**
 * 点击删除显示确认弹窗
 * 
 * 
*/
function click_show_delete_interface()
{
	$('.delete_sign').click(function(){
		var id = $(this).data('id');
		$('#delete_sms_sign_tips-id').val(id);
		$('#delete_sms_sign_tips').modal('show');
	});
}

/**
 * 点击确认删除
 * 
*/
function click_delete_sign()
{
	$('#delete_sms_sign').click(function(){
		var id = $('#delete_sms_sign_tips-id').val();
		var url = '/user/sms/delete_sign';
		$.ajax({
			type:"POST",
			data:{
				id:id
			},
			url:url,
			dataType:'json',
			success:function(result){
				console.log(result);
				if(result.code == 0){
					alert('删除成功');
					$('#delete_sms_sign_tips').modal('hide');
					get_sign(1, 10);
				}else{
					alert('删除失败');
				}
			},
			error:function(){
				console.log('错误');
				alert('删除失败');
			}
		});
	});
}

//获取数据
function get_sign(page, limit)
{
  var url = '/user/sms/get_signs';
  var data = {};
  data.start_create_time = $('#start_create_time').val();
  data.end_create_time = $('#end_create_time').val();
  data.status = $('#status').val();
  data.keyword = $('#keyword').val();
  data.page = page;
  data.limit = limit;
  $.ajax({
    type:"POST",
    data:data,
    dataType:'json',
    url:url,
    success:function(result){
      console.log(result);
      // sign_template
      if(result.code == 0){
      	if(result.data.data.length == 0){
      		$('#show_datas').html('');
	      	$('#consumeempty').show();
	        Paging.paging(1, 0, 10);
					$('.main-box-footer').hide();
      	}else{
      		$('#consumeempty').hide();
      		$('.main-box-footer').show();
      		var htmls = '';
	        $.each(result.data.data, function(index, object){
	          var html = $('#sign_template').html();
	              html = html.replace(/{%sign_id%}/g, object.id);
	              html = html.replace('{%sign_name%}', object.name);
	              html = html.replace('{%username%}', object.username);
	              html = html.replace('{%key%}', object.key);
	              html = html.replace('{%status%}', object.status_name);
	              html = html.replace('{%create_time%}', object.create_time);
	              var operation = '';
	              //审核状态 0 未提交审核 1 审核中 2 审核未通过 3 审核通过 4 管理员审核未通过
	              if(object.status == 0){
	                // operation += '<a href="javascript:void(0);" data-id="'+object.id+'" class="auditing_sign">提交审核</a>';
	              }
	              if(object.status != 3 && object.status != 1){
	                operation += '<a href="javascript:void(0);" data-id="'+object.id+'" class="edit_sign">编辑</a>';
	              }
	              operation += '<a href="javascript:void(0);" data-id="'+object.id+'" class="operation delete_sign">删除</a>';
	              html = html.replace('{%operation%}', operation);
	          htmls += html;
	        });
      	}
    	  $('#show_datas').html(htmls);
        window.count = result.data.count;
        Paging.paging(page, result.data.count, limit);
        sumbit_auditing();
        click_show_edit_interface();
        click_show_delete_interface();
        click_delete_sign();
      }else{
      	$('#show_datas').html('');
      	$('#consumeempty').show();
        Paging.paging(1, 0, 10);
        window.count = 0;
      }
      election();
    },
    error:function(){
      Paging.paging(1, 0, 10);
      console.log('错误');
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
  callback: get_sign //回调函数 比如show_datas(页码, 显示条数)
});





$(function(){
  show_sign();
  click_submit_sign();
  click_show_add_interface();
  $('#click_query_sign').click();
})




































//---
