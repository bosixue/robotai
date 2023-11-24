var status = true;


function get_datas(page, limit)
{
	console.log(status);
	if(status == 'true'){
		console.log(status);
		status = false;
	  var data = {};
	  data.page = page?page:1;
	  data.limit = limit?limit:10;
	  data.username = $('#username').val();
	  data.start_create_time = $('#startDate').val();
	  data.end_create_time = $('#endTime').val();
	  data.status = $('#shenghe').val();
	  data.keyword = $('#keyword').val();
	  var url = '/user/sms/get_audit_record';
	  $.ajax({
	    type:'POST',
	    data:data,
	    url:url,
	    dataType:"json",
	    success:function(result){
	    	status = true;
	      console.log(result);
	      // <a href="javascript:;" onclick="audit();">审核</a>&nbsp;&nbsp;&nbsp;
	      // <a href="javascript:;" onclick="audit_record();">审核记录</a>
	      if(result.code == 0){
	        var htmls = '';
	        $.each(result.data.datas, function(index, object){
	          var html = $('#template').html();
	              html = html.replace('{%key%}', object.key);
	              html = html.replace('{%sign_name%}', object.sign_name);
	              html = html.replace('{%template_content%}', object.content);
	              html = html.replace('{%template_id%}', object.template_id);
	              html = html.replace('{%create_time%}', object.create_time);
	              html = html.replace('{%status_name%}', object.key);
	              html = html.replace('{%note%}', object.note?object.note:'');
	              html = html.replace('{%username%}', object.username);
	              html = html.replace('{%auditing_username%}', object.auditing_username);
	              html = html.replace('{%status%}', object.status_name);
	              html = html.replace('{%channel_name%}', object.channel_name);
	              ////0 未提交审核 1 审核中 2 审核未通过 3 审核通过 4 管理员审核未通过
	              var operation = '';
	              // if(object.status == 1){
	                // operation
	                operation += '<a href="javascript:;" data-id="'+object.template_id+'" class="audit">审核</a>&nbsp;&nbsp;&nbsp;';
	              // }
	              // audit_record
	              operation += '<a href="javascript:;" class="shwo_auditing_record" data-id="'+object.template_id+'">审核记录</a>';
	              html = html.replace('{%operation%}', operation);
	              htmls += html;
	        });

					if(htmls ==''){
						$("#consumeempty").show();
					}else{
						$("#consumeempty").hide();
					}


	        $('#show_datas').html(htmls);
	        $('#check_count').text(result.data.count);
	        Paging.paging(page, result.data.count, limit);
	        $('.audit').click(function(){
	          // get_sms_auditing_info
	          var url = "/user/sms/get_sms_auditing_info";
	          var id = $(this).data('id');
	          $.ajax({
	            type:"POST",
	            data:{
	              template_id: id,
	            },
	            dataType:'json',
	            url:url,
	            success:function(result){
	              console.log(result);
	              if(result.code == 0){
	              	$('#audit-username').val(result.data.username);
	              	$('#audit-sms-passageway').val(result.data.channel_name);
	              	$('#audit-sms-sign').val(result.data.sign_name);
	              	$('#audit-template-content').val(result.data.content);
	              	$('#audit-status').val(result.data.status);
	              	$('#sms-template-audit-id').val(result.data.id);
	              }
	            },
	            error:function(){
	              console.log('错误');
	            }
	          });
	          $('#template-audit').modal('show');
	        });
	        $('.shwo_auditing_record').click(function(){
	          var id = $(this).data('id');
	          var url = '/user/sms/get_sms_auditing_record';
	          $.ajax({
	            type:'POST',
	            data:{
	              template_id: id,
	            },
	            url:url,
	            dataType:'json',
	            success:function(result){
	              console.log(result);
	              if(result.code == 0){
	                var htmls = '';
	                $.each(result.data, function(index, object){
	                  var html = $('#auditing_record').html();
	                      html = html.replace("{%username%}", object.username);
	                      html = html.replace("{%create_time%}", object.create_time);
	                      html = html.replace("{%status_name%}", object.status);
	                      html = html.replace("{%note%}", object.note);
	                  htmls += html;
	                });
	                $('#show_auditing_record').html(htmls);
	              }
	            },
	            error:function(){
	              console.log('错误');
	            }
	          });
	          $('#audit-record').modal('show');
	        });
	        $('#submit-audit').unbind('click');
	        $('#submit-audit').click(function(){
	        	var data = {};
	        	data.id = $('#sms-template-audit-id').val();
	        	data.status = $('#audit-status').val();
	        	data.note = $('#audit-note').val();
	        	var url = '/user/sms/submit_auditing_result';
	        	$.ajax({
	        		type:"POST",
	        		dataType:'json',
	        		data:data,
	        		url:url,
	        		success:function(result){
	        			if(result.code == 0){
	        				$('#template-audit').modal('hide');
	        				get_datas(1, 10);
	        				alert('提交成功');
	        			}else{
	        				alert('提交失败');
	        			}
	        		},
	        		error:function(){
	        			console.log('错误');
	        			alert('提交失败');
	        		}
	        	});
	        });

	        console.log(htmls);
	      }else{
	      	Paging.paging(1, 0, 10);
	      }
	    },
	    error:function(){
	    	status = true;
	      console.log('错误');
	    }
	  });
	}
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
  callback: get_datas //回调函数 比如show_datas(页码, 显示条数)
});


$(function(){
	get_datas(1, 10);
	$('#data-query').click(function(){
  	get_datas(1, 10);
  });
});
