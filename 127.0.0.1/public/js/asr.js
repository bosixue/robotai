var asr_data = {};
var find_users_load_type = 'html';
var find_users_screens = {};
var find_users_i = 0;
var distribution_data = {};

/**
 * 添加ASR
 *
*/
function add_asr()
{
	$('#add-tips').modal('hide');
	var data = {};
	//ASR名称
	data.name = $('#e-asrname').val();
	//项目密钥
	data.project_key = $("#e-secret").val();
	//App Key
	data.app_key = $('#e-appkey').val();
	//App Secret
	data.app_secret = $('#e-appsecret').val();
	//成本价格
	data.sale_price = $('#e-asrprice').val();
	//ASR类型
	data.type = $('#e-type').val();
	//备注
	data.note = $('#asrRemarks').val();
	if(!data.name){
		alert('请输入ASR名称');
		return false;
	}
	if(data.type == ''){
	  alert('请选择ASR类型');
	  return false;
	}
	if(data.type != 'xfyun' && !data.project_key){
		alert('请输入项目密钥');
		return false;
	}
	if(!data.app_key){
		alert('请输入App Key');
		return false;
	}
	if(!data.app_secret){
		alert('请输入App Secret');
		return false;
	}
	if(!data.sale_price){
		alert('请输入销售价格');
		return false;
	}
	var url = '/user/asr/add_asr';
	$.ajax({
		type:'POST',
		dataTye:'json',
		data:data,
		url:url,
		success:function(result){
			if(result.code == 0){
				get_asrs();
				reset();
				$("#asr-add").modal('hide');
				alert("ASR添加成功！");
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}
//点击显示分配弹窗
function show_distribution_popup()
{
	var user = $('.l-account.l-account-active').data('name');
	var user_id = $('.l-account.l-account-active').data('id');
	$('#show_cost').text('');
	$('#asr-saleprice').val('');
	$('#username_ASR').text(user); 
	
	get_distributable_asrs(user_id);
	$('#alloction-ASR').modal('show');
}
//显示可分配ASR
function get_distributable_asrs(user_id)
{
	if(user_id == ''){
		return false;
	}
	var url = '/user/asr/get_distributable_asrs';
	$.ajax({
		type:"POST",
		data:{
			member_id:user_id
		},
		dataTye:"json",
		url:url,
		success:function(result){
			console.log(result);
			if(result.code == 0){
				var options = '<option value="">请选择ASR</option>';
				$.each(result.data, function(index, object){
					var option = '<option value="'+object.id+'" data-cost="'+object.cost_price+'" data-sale="'+object.sale_price+'">'+object.name+'</option>';
					options += option;
				});
				$('#asr_list').html(options);
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}
/**
 * 点击提交
 *
*/
function click_submit()
{
	$('.submit-asr').unbind('click');
	$('.submit-asr').click(function(){
		var type = $(this).attr('data-type');
		console.log(type);
		if(type == 'add'){
			$('#asr-add').modal('hide')
			$('#add-tips').modal('show');
			// $('#updateoperator').attr('data-type', 'add');
			// add_asr();
		}else if(type == 'edit'){
			$('#asr-add').modal('hide')
			$('#update-tips').modal('show');
			// $('#updateoperator').attr('data-type', 'edit');
			// update_asr();
		}
	});
}



//清空添加私有ASR弹框的数据
function clear_ASRDialog(){
	$('#e-asrname').val('');
	$('#e-secret').val('');
	$('#e-appkey').val('');
	$('#e-appsecret').val('');
	$('#e-asrprice').val('');
	$('#asrRemarks').val('');
}

//清空分配ASR弹框的数据
function clear_ASRDistributionDialog(){
	$('#asr-saleprice').val('');
	$('#asrnotes').val('');
	$('.cost-price').addClass('hidden');
	$('#asr_list').val('');

}


/**
 * 重置
*/
function reset()
{
	$('#asr_id').val('');
	//ASR名称
	$('#e-asrname').val('');
	//项目密钥
	$("#e-secret").val('');
	//App Key
	$('#e-appkey').val('');
	//App Secret
	$('#e-appsecret').val('');
	//成本价格
	$('#e-asrprice').val('');
	//备注
	$('#asrRemarks').val('');
}
/**
 * 更新ASR
 *
*/
function update_asr()
{
	$("#update-tips").modal('hide');
	var data = {};
	data.asr_id = $('#asr_id').val();
	//ASR名称
	data.name = $('#e-asrname').val();
	//项目密钥
	data.project_key = $("#e-secret").val();
	//App Key
	data.app_key = $('#e-appkey').val();
	//App Secret
	data.app_secret = $('#e-appsecret').val();
	//成本价格
	data.sale_price = $('#e-asrprice').val();
	//ASR类型
	data.type = $('#e-type').val();
	//备注
	data.note = $('#asrRemarks').val();
	if(!data.name){
		alert('请输入ASR名称');
		return false;
	}
	if(data.type == ''){
	  alert('请选择ASR类型');
	  return false;
	}
	if(data.type != 'xfyun' && !data.project_key){
		alert('请输入项目密钥');
		return false;
	}
	if(!data.app_key){
		alert('请输入App Key');
		return false;
	}
	if(!data.app_secret){
		alert('请输入App Secret');
		return false;
	}
	if(!data.sale_price){
		alert('请输入销售价格');
		return false;
	}
	$.ajax({
		type:'POST',
		data:data,
		dataTye:'json',
		url:'/user/asr/update_asr',
		success:function(result){
			console.log(result);
			if(result.code == 0){
				$("#update-tips").modal('hide');
				alert('更新成功');
				reset();
				get_asrs();
			}else if(result.code == 3){
			  alert(result.msg);
			}else{
				alert('更新失败');
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}

/**
 * 点击编辑显示弹窗
 *
 *
*/
function edit_asr()
{
	$('.edit_asr').click(function(){
		$('#add_editASR').html('编辑私有ASR');
		$('#asr-add .submit-btn').html('保存');
		var id = $(this).data('id');
		$('#asr_id').val(id);
		$('.submit-asr').attr('data-type', 'edit');
		/**
		 * 回填
		*/
		$.ajax({
			type:'POST',
			dataTye:'json',
			data:{
				id: id
			},
			url:'/user/asr/get_asr',
			success:function(result){
				if(result.code == 0){
					//ASR名称
					$('#e-asrname').val(result.data.name);
					asr_data.name = result.data.name;
					//ASR类型
					$('#e-type').val(result.data.type);
					//项目密钥
					$("#e-secret").val(result.data.project_key);
					asr_data.project_key = result.data.project_key;
					//App Key
					$('#e-appkey').val(result.data.app_key);
					asr_data.app_key = result.data.app_key;
					//App Secret
					$('#e-appsecret').val(result.data.app_secret);
					asr_data.app_secret = result.data.app_secret;
					//成本价格
					$('#e-asrprice').val(result.data.sale_price);
					asr_data.sale_price = result.data.sale_price;
					//备注
					$('#asrRemarks').val(result.data.note);
					asr_data.note = result.data.note;
					$("#asr-add").modal('show');
					click_submit();
				}
			},
			error:function(){
				console.log('错误');
			}
		});
	});
}
/**
 * 点击显示添加asr的弹窗
*/
function click_add_asr()
{
	$('.add_asr').click(function(){
		$('#add_editASR').html('添加私有ASR');
			clear_ASRDialog();
		$('#asr-add .submit-btn').html('确定');
		$('.submit-asr').attr('data-type', 'add');
		$("#asr-add").modal('show');
		click_submit();
	});
}

/**
 * 获取ASR数据
*/
function get_asrs(page, limit)
{
	var keyword = $('#username_list').val();
	var url = "/user/asr/get_asrs";
	$.ajax({
		type:'POST',
		dataType:'json',
		data:{
			keyword:keyword,
			page:page,
			limit:limit
		},
		url:url,
		success:function(result){
                    console.log(result);
                    if(result.code == 0){
                      if(result.data.data.length == 0){
                        $('#consumeempty').show();
                      }else{
                        $('#consumeempty').hide();
                        var htmls = '';
                            var hide_ids = [];
                            $.each(result.data.data, function(index, object){
                                    var html = $('#asr_template').html();
                                                    html = html.replace('{%key%}', object.key);
                                                    html = html.replace(/{%id%}/g, object.id);
                                                    html = html.replace('{%name%}', object.name);
                                                    html = html.replace('{%note%}', object.note);
                                                    html = html.replace('{%sale_price%}', object.sale_price);
                                                    html = html.replace('{%p_name%}', object.p_name);
                                    htmls += html;
                                    if (1 === object.asr_from) {
                                            hide_ids[hide_ids.length] = object.id;
                                    }
                            });
                            
                            //显示数据
                            $('#recharge-recored-list').html(htmls);
                            hide_ids.forEach(function(v){
                                    $('#edit-asr-' + v).remove();
                                    $('#del-asr-' + v).remove();
                            });
                            $('#count').text(result.data.count);
                            $("#asr-add").modal('hide');
                            Paging.paging(page, result.data.count, limit);
                            //编辑
                            edit_asr();
                            
                            for(var i=0;i<result.data.data.length;i++){
                               if(result.data.data[i]['pid']!=0){
                                  var id = result.data.data[i]['id'];
                                  $('#del-asr-'+id).hide();
                               }  
                            }
                      }
                      
                      
                    }
		},
		error:function(){
			console.log('错误');
		}
	});
}

/**
 * asr统计计费 数据
*/
function get_asrs_statistical(page, limit)
{
	if(page == "" || page == null){
		page = 1;
	}
	if(limit == "" || limit == null){
		limit = 10;
	}
	var asrname = $('#selectASRName').val();
	var username = $('#username_details').val();
	var data = {};
	data.page = page;
	data.limit = limit;
	data.asrname = asrname;
	data.username = username;

	var url = "/user/asr/get_asr_statistical_data";
	$.ajax({
		type:'POST',
		dataType:'json',
		data:data,
		url:url,
		success:function(result){
			console.log(result);
			if(result.code == 0){
				var htmls = '';
				if(result.data.count > 0){
                                    $('#consumeemptys').hide();
                                    $.each(result.data.list, function(index, object){
                                            var html = $('#asr_recored_template').html();
                                                            html = html.replace('{%key%}', object.key);
                                                            html = html.replace('{%asr_name%}', object.asrname);
                                                            html = html.replace('{%asr_username%}', object.username);
                                                            html = html.replace('{%username_type%}', object.usertype);
                                                            html = html.replace('{%voice_frequency%}', object.asr_cnt);
                                                            html = html.replace('{%cost_price%}', object.cost_price);
                                                            html = html.replace('{%total_cost%}', object.cost_price_statistics);
                                                            html = html.replace('{%selling_price%}', object.sale_price);
                                                            html = html.replace('{%total_selling%}', object.sale_price_statistics);
                                                            html = html.replace('{%profit%}', object.profit);
                                                            html = html.replace('{%charging_time%}', object.date);
                                                            html = html.replace('{%asr_source%}', object.source_name);
                                            htmls += html;
                                    });
				}else{
                                    $('#consumeemptys').show();
                                }
				//显示数据
				$('#asr_recored_list').html(htmls);
				$('#jifei_count').text(result.data.count);
				$('#zong_count').text(result.data.sum_info.sum_asr_cnt);//总使用条数
				$('#zonge_cost').text(result.data.sum_info.sum_cost_price_statistics);//成本总额
				$('#zonge_Sale').text(result.data.sum_info.sum_sale_price_statistics);//销售总额
				$('#zong_profit').text(result.data.sum_info.sum_profit);//总利润

				asr_Paging.paging(page, result.data.count, limit);
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}


/**
 * 删除asr
 *
 * @param int asr_id
*/
function delete_asr(object)
{
	var asr_id = $(object).data('id');
	console.log(asr_id);
	if(!asr_id){
		return false;
	}
	var url = '/user/asr/delete_asr';
	var id = [];
	id.push(asr_id);
	$.ajax({
		type:'POST',
		data:{
			id:id,
		},
		dataType:'json',
		url:url,
		success:function(result){
			console.log(result);
			if(result.code == 0){
				$('#tips_model').modal('hide');
				get_asrs();
				alert('删除成功');
			}else if(result.code == 2){
				alert(result.msg);
			}else{
				alert('删除失败');
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}

function delete_find_asr(object)
{
	var asr_id = $(object).data('id');
	if(!asr_id){
		return false;
	}
	var url = '/user/asr/delete_find_asr';
	$.ajax({
		type:'POST',
		data:{
			id:asr_id,
		},
		dataType:'json',
		url:url,
		success:function(result){
			console.log(result);
			if(result.code == 0){
				$('#tips_model').modal('hide');
				$('.l-account-active').click();
				alert('删除成功');
			}else{
				alert('删除失败');
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}

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
		var url = "/user/asr/get_find_users";
		var data = {};
		// role_name, user_name, page, limit
		data.user_id = $('#find_users-select_user_name').val();
		console.log(data);
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
	    console.log(contentH - viewH);
	    console.log(scrollTop);
	    if(scrollTop - (contentH - viewH - 15) === 0){ //到达底部100px时,加载新内容
	    	window.page++;
	    	console.log('到底了');
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
 * 根据asr显示对应的成本价格
 *
*/
function show_asr_cost()
{
	$('#asr_list').unbind('change');
	$('#asr_list').change(function(){
		var tag = $(this).val();
		if(tag !=''){
			var cost = $(this).find('option:selected').data('cost');
			var sales_price = $(this).find('option:selected').data('sale');
			$('#show_cost').text(cost+'元/次');
			$('#asr-saleprice').val(sales_price);
			$('.cost-price').removeClass('hidden');
		}else{
			$('.cost-price').addClass('hidden');
		}

	});
}

/**
 * 点击提交分配
 *
*/
function click_distribution_submit()
{
	$('#asr_distribution_submit').click(function(){
		console.log('点击了');
		var url = '/user/asr/distribution_asr_api';
		/*
		* @param int $asr_id ASR的ID
	   * @param int $member_id 分配指定的用户ID
	   * @param float $sale_price 销售价格
		*/
		var data = {};
		data.asr_id = $('#asr_list').val();
		data.member_id = $('.l-account-active').data('id');
		data.sale_price = $('#asr-saleprice').val();
		data.note = $('#asrnotes').val();
		console.log(data);
		$.ajax({
			type:"POST",
			data:data,
			dataType:"json",
			url:url,
			success:function(result){
				console.log(result);
				if(result.code == 0){
					alert('分配成功');
					$('#alloction-ASR').modal('hide');
					$('.l-account-active').click();
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
		$('#current_username').html(username+'<span>('+role_name+')</span>');
		$(this).addClass('l-account-active').siblings().removeClass('l-account-active');
		var id = $(this).data('id');
		$('#find_user_id').val(id);
		var url = "/user/asr/get_user_lines";
		$.ajax({
			type:"POST",
			data:{
				member_id:id,
			},
			url:url,
			success:function(result){
				console.log(result);
				if(result.code == 0){
					var htmls = '';
					$.each(result.data, function(index, object){
						var html = $('#asr_distribution_template').html();
								html = html.replace('{%asr_name%}', object.name);
								html = html.replace('{%id%}', object.id);
								html = html.replace('{%cost%}', object.cost);
								html = html.replace('{%sales_price%}', object.sale_price);
								html = html.replace('{%note%}', object.note);
								html = html.replace('{%create_time%}', object.create_time);
						htmls += html;
					});
					$('#distribution-details').html(htmls);
				}
			},
			error:function(){
				console.log('错误');
			}
		})

	});
}

/**
 * 删除ASR
 *
 * @
*/


/**
 * 重置ASR列表
*/
function reset()
{
	$('#username_list').val('');
	get_asrs();
}
/**
 * 重置ASR分配界面
*/
function reset_asr_distribution()
{
	$('#find_users-select_user_name').val('');
	$('#find_users-input_user_name').val('');
	$('#find_users-role_name').val('');
	get_find_users();
	$('#find_users-search').click();
}

// /**
// * 重置ASR统计计费
// */
// function orderChongzhi(){
// 	$('#startDate').val("");
// 	$('#endTime').val("");
// }

//重置ASR统计计费
function ASR_list_reset(){
	$('#username_details').val('');
	$('#selectASRName').val(0);
	get_asrs_statistical();
}

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
  paging_class:'paging',
  callback:get_asrs
});

var asr_Paging = new Paging01();
asr_Paging.init_args({
  page:1,
  limit:10,
  paging_class:'asrpaging',
  callback:get_asrs_statistical
});

$(function(){
	get_asrs();
	click_add_asr();
	click_submit();
	show_asr_cost();
	click_distribution_submit();
	get_find_users();
	get_asrs_statistical();
	$('#find_users-search').click();

})
