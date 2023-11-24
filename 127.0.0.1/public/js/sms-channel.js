var find_users_screens = {},
    find_users_i = 0;
/**
 * 短信通道
*/
//获取数据
function get_datas(page, limit)
{
  var data = {};
  data.page = page?page:1;
  data.limit = limit?limit:10;
  window.page = data.page;
  window.limit = data.limit;
  data.keyword = $('#channel-name').val();
  var url = "/user/sms/get_channels";
  $.ajax({
    type:'POST',
    dataTye:'json',
    data:data,
    url:url,
    success:function(result){
      if(result.code == 0){
        var htmls = '';
        $.each(result.data.data, function(index, object){
          var html = $('#list_option').html();
              html = html.replace('{%id%}', object.id);
              html = html.replace('{%name%}', object.name);
              html = html.replace('{%price%}', object.price);
              // html = html.replace('{%count%}', object.count);
              html = html.replace('{%note%}', object.remarks);
              html = html.replace('{%source%}', object.source);
              var operation = '';
              if(object.pid == 0){
              	operation += '<a href="javascript:void(0);" data-id="'+object.id+'" class="edit_sms_channel">编辑&nbsp;&nbsp;&nbsp;</a>';
	        			operation += '<a href="javascript:void(0);" data-title="删除单个线路" data-id="'+object.id+'" class="delete_sms_channel">删除</a>';
              }else{
	        			operation += '<a href="javascript:void(0);" style="color:silver;" data-title="删除单个线路">删除</a>';
              }
              html = html.replace('{%operation%}', operation);
              html = html.replace('{%key%}', object.key);
          htmls += html;
        });
        if(htmls == ''){
          $("#consumeempty").show();
          $(".foots").hide();

        }else{
          $("#consumeempty").hide();
          $(".foots").show();

        }
        $('#recharge-recored-list').html(htmls);
        $('#sms_channel_count').text(result.data.count);
        //显示分页(当前页码, 总数, 每页显示的条数, 自定义参数)
        Paging.paging(page, result.data.count, limit);
        edit_sms_channel();
        delete_sms_channel();
      }
    },
    error:function(){
      console.log('错误');
    }
  })
}

//点击重置通道列表
function reset_channel_list()
{
	$('#channel-name').val('');
	get_datas(1, 10);
}

//点击重置短信通道分配
function reset(){
	$('#find_users-select_user_name').val('');
	$('#find_users-input_user_name').val('');

	get_find_users();
	$('#find_users-search').click();
}

//点击重置短信计费统计
function reset_sms_statistical(){
	$('#username_details').val('');
	$('#selectSmsName').val(0);
	get_sms_statistical();
}
//点击编辑短信通道 显示当前短信通道的数据
// function


var Paging = new Paging01();
// this.url =  args.url;
// this.page = args.page;
// this.limit = args.limit;
// this.count = args.count;
// this.paging_class = args.paging_class;
// this.callback = args.callback;
// this.data = '';
Paging.init_args({
  page:1,
  limit:10,
  paging_class:'channel_paging',
  callback:get_datas
});

//添加私有通道
function add_channel()
{
	//点击添加按钮显示添加界面
	$('.add_channel').unbind('click');
	$('.add_channel').click(function(){
		$('#channel_action').val('add');
		$('#addprivateChannel > span').text('添加私有通道');
		$('#add_channel').text('保存');

		$('#sms-channel-name').val('');
		$('select[name="type"]').val('');
		$('#sms-channel-url').val('');
		$('#sms-channel-userid').val('');
		$('#sms-channel-username').val('');
		$('#sms-channel-password').val('');
		$('#sms-channel-price').val('');
		$('#sms-channel-note').val('');

		$('#add_privateChannelDialog').modal('show');
	});
	//提交
  $('#add_channel').unbind('click');
  $('#add_channel').click(function(){
  	submit_channel();
  })
}

//提交
function submit_channel()
{
	var action = $('#channel_action').val();
	if(action == 'add'){
		var url = '/user/sms/add_channel';
    var data = {};
    data.name = $('#sms-channel-name').val();
    data.userid = $('#sms-channel-userid').val();
    data.type = $('select[name="type"]').val();
    data.url = $('#sms-channel-url').val();
    data.username = $('#sms-channel-username').val();
    data.password = $('#sms-channel-password').val();
    data.price = $('#sms-channel-price').val();
    data.note = $('#sms-channel-note').val();
    if(data.name == ''){
    	alert('通道名称不能为空');
    	return false;
    }
    if(data.type == ''){
    	alert('通道类型不能为空');
    	return false;
    }
    if(data.type == '云片网'){
      if(data.username == ''){
      	alert('短信账号不能为空');
      	return false;
      }
    }else{
      if(data.username == ''){
      	alert('短信账号不能为空');
      	return false;
      }
      if(data.password == ''){
      	alert('短信密码不能为空');
      	return false;
      }
    }
    if(data.url == ''){
    	alert('接口地址不能为空');
    	return false;
    }
    $.ajax({
      type:'POST',
      data:data,
      dataTye:'json',
      url:url,
      success:function(result){
        if(result.code == 0){
          alert('添加成功');
          $('#add_privateChannelDialog').modal('hide');
          get_datas(1, 10);
        }else{
          alert('添加失败');
        }
      },
      error:function(){
        console.log('错误');
      }
    });
	}else if(action == 'edit'){
		var id = $('#channel_id').val();
		var url = '/user/sms/update_channel';
    var data = {};
    data.id = id;
    data.name = $('#sms-channel-name').val();
    data.userid = $('#sms-channel-userid').val();
    data.type = $('select[name="type"]').val();
    data.url = $('#sms-channel-url').val();
    data.username = $('#sms-channel-username').val();
    data.password = $('#sms-channel-password').val();
    data.price = $('#sms-channel-price').val();
    data.note = $('#sms-channel-note').val();
    if(data.name == ''){
    	alert('通道名称不能为空');
    	return false;
    }
    if(data.type == ''){
    	alert('通道类型不能为空');
    	return false;
    }
    if(data.type == '云片网'){
      if(data.username == ''){
      	alert('短信账号不能为空');
      	return false;
      }
    }else{
      if(data.url == ''){
      	alert('接口地址不能为空');
      	return false;
      }
      if(data.username == ''){
      	alert('短信账号不能为空');
      	return false;
      }
      if(data.password == ''){
      	alert('短信密码不能为空');
      	return false;
      }
    }
    $.ajax({
      type:'POST',
      data:data,
      dataTye:'json',
      url:url,
      success:function(result){
        if(result.code == 0){
          alert('更新成功');
          $('#add_privateChannelDialog').modal('hide');
          get_datas(window.page, window.limit);
        }else if(result.code == 3){
        	alert(result.msg);
        }else{
          alert('更新失败');
        }
      },
      error:function(){
      	alert('更新失败');
        console.log('错误');
      }
    });
	}
}

//编辑短信通道
function edit_sms_channel()
{
	//点击编辑显示回调数据
	$('.edit_sms_channel').click(function(){
		var id = $(this).data('id');
		var url = '/user/sms/get_sms_channel';
		$.ajax({
			type:"POST",
			data:{
				id:id,
			},
			dataTye:"json",
			url:url,
			success:function(result){
				if(result.code == 0){
					$('#channel_action').val('edit');
					$('#channel_id').val(id);
					$('#sms-channel-name').val(result.data.name);
					$('#sms-channel-userid').val(result.data.enterprise_id);
			    $('select[name="type"]').val(result.data.type);
			    $('#sms-channel-url').val(result.data.url);
			    $('#sms-channel-username').val(result.data.user_id);
			    $('#sms-channel-password').val(result.data.password);
			    $('#sms-channel-price').val(result.data.price);
			    $('#sms-channel-note').val(result.data.remarks);
			    $('#addprivateChannel>span').text('编辑私有通道');
					$('#add_channel').text('保存');
			    $('#add_privateChannelDialog').modal('show');
				}
			},
			error:function(){
				console.log('错误');
			}
		});
	})
	//点击保存 提交编辑后的数据
	$('#add_channel').unbind('click');
	$('#add_channel').click(function(){
		submit_channel();
	});
}



/**
 * 删除短信通道
 *
 * @param int $sms_channel_id 短信通道ID
*/
var delete_action = 'delete';
function delete_sms_channel()
{
  //点击删除显示提示框
  $('.delete_sms_channel').unbind('click');
  $('.delete_sms_channel').click(function(){
    // tips_model
    delete_action = 'delete';
    var sms_channel_id = $(this).data('id');
    $('#sms_channel_id').val(sms_channel_id);
    $('#tips_model').modal('show');
  });
  //在提示框中选择确认删除
  $('#delete_sms_channel').unbind('click');
  $('#delete_sms_channel').click(function(){
  	var sms_channel_id = $('#sms_channel_id').val();
    var url = '/user/sms/delete_sms_channel_api';
    $.ajax({
      type:"POST",
      data:{
        sms_channel_id:sms_channel_id,
      },
      url:url,
      dataType:'json',
      success:function(result){
        if(result.code == 0){
          alert('成功');
          if(delete_action == 'delete'){
          	get_datas(window.page, window.limit);
          }else if(delete_action == 'delete_find'){
          	$('.l-account-active').click();
          }
        }else{
          alert('失败');
        }
        $('#tips_model').modal('hide');
      },
      error:function(){
        console.log('错误');
      }
    })
  })

  $('.delete_find_sms_channel').unbind('click');
  $('.delete_find_sms_channel').click(function(){
  	delete_action = 'delete_find';
    $('#tips_model').modal('show');
    $('#sms_channel_id').val($(this).data('id'));
  });
}








/**
 * 分配短信通道
*/
/**
 * 获取子账户
 *
 * @param string role_name 角色名称
 * @param string user_name 用户名称搜索
 * @param int count 目前显示的用户数量
*/
function get_find_users()
{
	$('#find_users-search').unbind('click');
	$('#find_users-search').click(function(){
		var url = "/user/sms/get_find_users";
		var data = {};
		// role_name, user_name, page, limit
		data.user_id = $('#find_users-select_user_name').val();
		find_users_screens.user_id = data.user_id;
		if(data.user_id == ''){
			data.username = $('#find_users-input_user_name').val();
			find_users_screens.username = data.username;
		}
		data.role_name = $('#find_users-role_name').val();
		find_users_screens.role_name = data.role_name;
		data.count = 0;
		$.ajax({
			type:"POST",
			dataType:'json',
			data:data,
			url:url,
			success:function(result){
				if(result.code == 0){
					if(result.data.length > 0){
						find_users_i++;
					}
					var htmls = '';
					$.each(result.data, function(index, object){
						var html = '';
								html += '<div class="l-account " data-name="'+object.username+'" data-role_name="'+object.role_name+'" data-id="'+object.id+'">';
									html += '<span>'+object.username+'</span>';
								html += '</div>';
						htmls += html;
					})
					$('.operator').html(htmls);
					click_find_user();
					if(find_users_i >= 1){
						$('.l-account').eq(0).click();
					}

				}
			},
			error:function(){
				console.log('错误');
			}
		});
	});
	$('.pz_innerbox').unbind('scroll');
	$('.pz_innerbox').scroll(function(){
	    var $this = $(this),
	    viewH = $(this).height(),//可见高度
	    contentH = $(this).get(0).scrollHeight,//内容高度
	    scrollTop = $(this).scrollTop();//滚动高度
	    //if(contentH - viewH - scrollTop <= 100) { //到达底部100px时,加载新内容
	    if(scrollTop - (contentH - viewH - 15) === 0){ //到达底部100px时,加载新内容
	    	window.page++;
	    	var url = "/user/asr/get_find_users";
	    	find_users_screens.count = $('.l-account').length;
	    	$.ajax({
					type:"POST",
					dataType:'json',
					data:find_users_screens,
					url:url,
					success:function(result){
						if(result.code == 0){
							var htmls = '';
							$.each(result.data, function(index, object){
								var html = '';
										html += '<div class="l-account " data-name="'+object.username+'" data-role_name="'+object.role_name+'" data-id="'+object.id+'">';
											html += '<span>'+object.username+'</span>';
										html += '</div>';
								htmls += html;
							})
							$('.operator').append(htmls);
							click_find_user();
						}
					},
					error:function(){
						console.log('错误');
					}
				});
	    	// show_task_list('append');
	    // 这里加载数据..
	    }
	  });

	//默认参数
	/*
	<div class="l-account l-account-active" data-name="pz" data-role_name="商家" data-id="5556">
		<span>pz</span>
	</div>
	*/

	// $.ajax()
}

/**
 * 根据短信通道显示对应的成本价格
 *
*/
function show_sms_channel_cost()
{
	$('#sms_channel_options').unbind('change');
	$('#sms_channel_options').change(function(){
		var tag = $(this).val();
		if(tag !=''){
			var cost = $(this).find('option:selected').data('cost');
			var note = $(this).find('option:selected').data('note');
			var sale_price = $(this).find('option:selected').data('sale_price');
			if(note == ''){
				note = '';
			}
			if(cost == undefined || cost == null){
				cost = '0.000';
				$('#show_cost').text(cost + '元/条');
			}else{
				$('#show_cost').text(cost + '元/条');
			}
			$('#price').val(sale_price);
			$('#notes').val(note);
			$('.cost-price').removeClass('hidden');
		}else{
			$('.cost-price').addClass('hidden');
		}

	});
}

/**
 * 重置短信分配表单
*/
function reset_sms_distribution()
{
	$('#sms_channel_options').val('');
	$('#sms_channel_options').trigger('change');
	$('#price').val('');
	$('#notes').val('');
}

/**
 * 点击提交分配
 *
*/
function click_distribution_submit()
{
	$('#sms_channel_distribution_submit').click(function(){
		var url = '/user/sms/distribution_sms_channel_api';
		/*
		* @param int $asr_id ASR的ID
	   * @param int $member_id 分配指定的用户ID
	   * @param float $sale_price 销售价格
		*/
		var data = {};
		data.sms_channel_id = $('#sms_channel_options').val();
		data.find_user_id = $('#find_user_id').val();
		data.price = $('#price').val();
		data.note = $('#notes').val();
		if(data.sms_channel_id == ''){
			alert('请选择短信通道');
			return false;
		}
		if(data.price == ''){
			alert('请输入通道销售价格');
			return false;
		}
    // data.count = $('#count').val();
		$.ajax({
			type:"POST",
			data:data,
			dataType:"json",
			url:url,
			success:function(result){
				if(result.code == 0){
					alert('分配成功');
					$('#alloction-passageway').modal('hide');
					$('.l-account-active').click();
					reset_sms_distribution();
				}else if(result.code == 3){
          alert(result.msg);
        }else{
					alert('分配失败');
				}
			},
			error:function(){
				alert('分配失败');
			}
		});
	});
}


/**
 * 选择指定用户进行分配
 *
*/
function click_find_user()
{
	$('.l-account').unbind('click');
	$('.l-account').click(function(){
		var username = $(this).data('name');
		var role_name = $(this).data('role_name');
    $.each($('.show_username'), function(index, object){
      $(object).text(username);
    })
		$('#current_username').html(username+'<span>('+role_name+')</span>');
		$(this).addClass('l-account-active').siblings().removeClass('l-account-active');
		var id = $(this).data('id');
		$('#find_user_id').val(id);
    //显示可分配通道
    show_optional_sms_channel();
    //显示给指定用户分配的
    show_find_user_sms_channel();
	});
}

//显示给指定用户分配了哪些短信通道
function show_find_user_sms_channel()
{
  var url = '/user/sms/get_find_distribution_sms_channel_api';
  var find_user_id = $('#find_user_id').val();
  $.ajax({
    type:"POST",
    data:{
      find_user_id:find_user_id
    },
    url:url,
    dataTye:'json',
    success:function(result){
      if(result.code == 0){
        var htmls = '';
        $.each(result.data, function(index, object){
          var html = $('#distribution_sms_channel_option').html();
              html = html.replace('{%id%}', object.id);
              html = html.replace('{%name%}', object.name);
              html = html.replace('{%cost%}', object.cost);
              html = html.replace('{%price%}', object.price);
              html = html.replace('{%create_time%}', object.create_time);
              html = html.replace('{%note%}', object.remarks);
          htmls += html;
        })
        $('#distribution-details').html(htmls);
        delete_sms_channel();
      }
    },
    error:function(){
      console.log('错误');
    }
  })
}



//显示分配通道弹窗
function show_distribution_sms_channel()
{
  $('#alloction-passageway').modal('show');
}

/**
 * 输出可选通道
 *
*/
function show_optional_sms_channel()
{
  var url = '/user/sms/show_optional_sms_channel';
  $.ajax({
    type:"POST",
    data:{
      find_user_id:$('#find_user_id').val(),
    },
    dataTye:'json',
    url:url,
    success:function(result){
      if(result.code == 0){
        var htmls = '';
        htmls += '<option value="">请选择通道</option>';
        $.each(result.data, function(index, object){
          htmls += '<option value="'+object.id+'" data-note="'+object.find_note+'" data-cost="'+object.price+'" data-sale_price="'+object.find_price+'">'+object.name+'</option>';
        });
        $('#sms_channel_options').html(htmls);
        show_sms_channel_cost();
      }
    },
    error:function(){
      console.log('错误');
    }
  })
}

/**
 * 短信计费统计 数据
*/
function get_sms_statistical(page, limit)
{
	if(page == "" || page == null){
		page = 1;
	}
	if(limit == "" || limit == null){
		limit = 10;
	}
	var data = {};
	data.page = page;
	data.limit = limit;
	data.smsname = $('#selectSmsName').val();
	data.username = $('#username_details').val();
	var url = "/user/sms/get_sms_statistical_data";
	$.ajax({
		type:'POST',
		dataType:'json',
		data:data,
		url:url,
		success:function(result){
			if(result.code == 0){
				var htmls = '';
				if(result.data.count > 0){
					$.each(result.data.list, function(index, object){
						var html = $('#sms_recored_template').html();
								html = html.replace('{%key%}', object.key);
								html = html.replace('{%sms_name%}', object.smsname);
								html = html.replace('{%username%}', object.username);
								html = html.replace('{%usertype%}', object.usertype);
								html = html.replace('{%sms_cnt%}', object.sms_cnt);
								html = html.replace('{%cost_price%}', object.cost_price);
								html = html.replace('{%total_cost%}', object.cost_price_statistics);
								html = html.replace('{%sale_price%}', object.sale_price);
								html = html.replace('{%total_sale%}', object.sale_price_statistics);
								html = html.replace('{%profit%}', object.profit);
								html = html.replace('{%charging_time%}', object.date);
								html = html.replace('{%sms_source%}', object.source_name);
						htmls += html;
					});
				}
        if(htmls == ''){
          $("#consumeempty2").show();
          $(".footss").hide();
        }else{
          $("#consumeempty2").hide();
          $(".footss").show();
        }
				//显示数据
				$('#sms_recored_list').html(htmls);
				$('#jifei_count').text(result.data.sum_info.total);
				$('#zong_count').text(result.data.sum_info.sum_sms_cnt);//总使用条数
				$('#zonge_cost').text(result.data.sum_info.sum_cost_price_statistics);//成本总额
				$('#zonge_Sale').text(result.data.sum_info.sum_sale_price_statistics);//销售总额
				$('#zong_profit').text(result.data.sum_info.sum_profit);//总利润

				sms_Paging.paging(page, result.data.count, limit);
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}




var sms_Paging = new Paging01();
sms_Paging.init_args({
  page:1,
  limit:10,
  paging_class:'smspaging',
  callback:get_sms_statistical
});

$(function(){
  get_datas(1, 10);
  $('#sms-channel-query').click(function(){
    get_datas(1, 10);
  })
  add_channel();
  //获取短信通道分配模块的用户选项数据
  get_find_users();
  get_sms_statistical();
  $('#find_users-search').click();
  click_distribution_submit();
});
