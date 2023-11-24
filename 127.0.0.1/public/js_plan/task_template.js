// 查询

function search() {

}
// 重置

function no_query() {
    $("keyword").val('');
    search();
}
function del_task_template(id){
	var data = {};
	data.type = 0 ;
	var arr = new Array();
	var checkedsub = $("input[name='checkids'][type='checkbox']:checked").length; //获取选中的checkids的个数
	if(checkedsub > 0 || id !=''){
		if($('.all_checked_count').is(':checked')){
			data.type = 1 ;
		}
		if($("input[name='checkids'][type='checkbox']").is(':checked')){
    	$("input[name='checkids'][type='checkbox']").each(function(i){
				if($(this).context.checked == true){
						arr[i] = $(this).val();
				}
			});
			data.vals = arr.join();
	}
	if(id){
		data.id = id; 
	}
	data.templateName = $('#keyword').val();
	console.log(data);
	var url = "/user/plan/del_task_template";
	$.ajax({
  		type:'POST',
  		data: data,
  		dataType:'json',
  		url:url,
  		success:function(data){
  			console.log(data);
  			if(data.code == 0){
  				alert(data.msg);
  				$('#tips_model').modal('hide');
  				show_data();
  			}else{
  				alert(data.msg);
  				$('#tips_model').modal('hide');
  			}
  		},
  		error:function(e){
  			alert('数据提交失败！');
  		}
  	});
	}else{
		alert('请选择批量删除的数据');
	}
}

// 添加模板

function add_edit_temp(id) {
    Reset_checkform();
    if(id != 0){
        $('.modal-title').text('编辑模板');
        $('#only_id_task_template').val(id);
        var  href = "/user/plan/get_tasks_template";
        // debugger;
        $.ajax({
            type: "GET",
            dataType: 'json',
            url: href,
            data: {id:id},
            success: function(result) {
                console.log(result);
                var info = result.data;
                $('input[name="task_template_name"]').val(info.template); //任务名称
                $('select[name="scenarios_id"]').val(info.scenarios_id); //话术ID
                $('input[name="robot_count"]').val(info.robot_cnt); //机器人数量
                $(":radio[name='is_auto'][value='" + info.is_auto + "']").prop("checked", "checked");//任务是否自动启动
                if(info.is_auto == 1){
								   $(".tips-auto").removeClass('hidden');
                   $(".tips-manualoperation").addClass('hidden');
								}else{
								   $(".tips-manualoperation").removeClass('hidden');
                   $(".tips-auto").addClass('hidden');
								}
                $('select[name="line_id"]').val(info.call_phone_id); //线路
                $('select[name="asr_id"]').val(info.asr_id); //ASR
                $.each(info.date_team, function(index, object){
    				if(index == 0){
    					$('#start_date').val(object[0]);
    					$('#end_date').val(object[1]);
    				}else if(index != 0){
    				 	var gongzuoriqi_html = '<div class="col-lg-11 col-sm-11 pz_zhuijiadata" style="margin-top: 10px">';
							gongzuoriqi_html += '<div class="col-lg-3 col-sm-3 n_inputwidth">';
							gongzuoriqi_html += '<input type="text" class="form-control textTimeWidth c_datebgimg" placeholder="请选择开始日期" class="form-control" id="start_date'+index+'" name="start_date" value="'+object[0]+'" readonly="" />';
							gongzuoriqi_html += '</div><div style="text-align:center; line-height: 30px;width: 25px;" class="pull-left"> 至 </div>';
							gongzuoriqi_html += '<div class="col-lg-3 col-sm-3 n_inputwidth">';
							gongzuoriqi_html += '<input type="text" class="form-control textTimeWidth c_datebgimg" placeholder="请选择结束日期" id="end_date'+index+'" name="end_date" value="'+object[1]+'" readonly="" />';
							gongzuoriqi_html += '</div>';
							gongzuoriqi_html += '<div class="col-lg-1 col-sm-1 pz_delTimes"><img style="margin-right:8px;vertical-align: sub;" src="/public/img/shanchu.png"/>';
							gongzuoriqi_html += '</div>';
    				 $('#date_list_temp').append(gongzuoriqi_html);
    				 delTimes();
    				 //时间
    				 $('#start_date'+index).fdatepicker({
    					 format: 'yyyy-mm-dd',
    				 });
    				 $('#end_date'+index).fdatepicker({
    					 format: 'yyyy-mm-dd',
    				 });
    				}
		    	});
                $.each(info.time_team, function(index, object){
					if(index == 0){
						$('#start_time').val(object[0]);
						$('#end_time').val(object[1]);

					}else if(index != 0){
							var gongzuoshijian_html = '<div class="col-lg-11 col-sm-11 pz_zhuijiadata" style="margin-top: 10px;">';
								gongzuoshijian_html += '<input type="hidden" name="morning" id="" value="" />';
								gongzuoshijian_html += '<div class="col-lg-3 col-sm-3 n_inputwidth">';
								gongzuoshijian_html += '<input type="text" class="form-control textTimeWidth c_timebgimg" placeholder="请输入开始时间" name="start_time" value="'+object[0]+'"  id="start_time'+index+'"/>';
								gongzuoshijian_html += '</div>';
								gongzuoshijian_html += '<div style="text-align:center; line-height: 30px;width: 25px;" class="pull-left"> 至 </div>';
								gongzuoshijian_html += '<div class="col-lg-3 col-sm-3 n_inputwidth">';
								gongzuoshijian_html += '<input type="text" class="form-control textTimeWidth c_timebgimg"  placeholder="请输入结束时间" name="end_time" value="'+object[1]+'"  id="end_time'+index+'"/>';
								gongzuoshijian_html += '</div>';
								gongzuoshijian_html += '<div class="col-lg-1 col-sm-1 pz_delTimes">';
								gongzuoshijian_html += '<img style="margin-right:8px;vertical-align: sub;" src="/public/img/shanchu.png"/>';
								gongzuoshijian_html += '</div>';
								gongzuoshijian_html += '</div>';
							 $('#time_list_temp').append(gongzuoshijian_html);
							 delTimes();
							 //时间
							 $('#start_time'+index).timepicker({
								 	format: 'hh:ii:ss',
								 	// pickTime: true,
								 	defaultTime:'8:00',
								 	showMeridian:false,
							 });
							$('#end_time'+index).timepicker({
								format: 'hh:ii:ss',
								// pickTime: true,
								defaultTime:'21:30',
								showMeridian:false,
							});
						}
				});
                $('input[name="task_abnormal_remind_phone"]').val(info.task_abnormal_remind_phone); //任务异常短信提醒的手机号码
                if(info.again_call_status){
                    $(":radio[name='is_again_call'][value='是']").prop("checked", "checked");//任务是否自动启动
                    $('.pz_again_call').removeClass('hide');
                    $('#again_call_count_label').removeClass('hide');
                    $('#again_call_count').val(info.again_call_count); 
                    var again_call_status = info.again_call_status.split(',');
                    $.each(again_call_status,function(index, object){
                        $('input[name="again_call_status"][value='+object+']').prop('checked', true);
                    });
                }else{
                    $(":radio[name='is_again_call'][value='否']").prop("checked", "checked");//任务是否自动启动
                    $('.pz_again_call').addClass('hide');
                    $('#again_call_count_label').addClass('hide');
                }
                if(info.add_crm_level){
                    $(":radio[name='joinCRM'][value='是']").prop("checked", "checked");
                    $(".pz_CRM_dengji").removeClass("hide");
                    var add_crm_level = info.add_crm_level.split(',');
                    $.each(add_crm_level,function(index, object){
                        $('input[name="crm-Yixiangdengji"][value='+object+']').prop('checked', true);
                    });

                    var add_crm_zuoxi = info.add_crm_zuoxi.split(',');
                    $.each(add_crm_zuoxi,function(index, object){
                        $('input[name="crm-push-users"][value='+object+']').prop('checked', true);
                    });


                }else{
                    $(":radio[name='joinCRM'][value='否']").prop("checked", "checked");
                    $(".pz_CRM_dengji").addClass("hide");
                }
                if(info.send_sms_status == 1){
                    $(":radio[name='Duanxinfasong'][value='是']").prop("checked", "checked");
                    $(".pz_duanxing_fasong").removeClass("hide");
                    var send_sms_level = info.send_sms_level.split(',');
                    $('#sms-template').val(info.sms_template_id);
                    $.each(send_sms_level,function(index, object){
                        $('input[name="Duanxinfasong-dengji"][value='+object+']').prop('checked', true);
                    });
                }else{
                    $(":radio[name='Duanxinfasong'][value='否']").prop("checked", "checked");
                    $(".pz_duanxing_fasong").addClass("hide");
                }
                //是否将意向客户推送给云控
                if(info.yunkong_push_status == 1){
                    $(":radio[name='yunkong'][value='是']").prop("checked", "checked");
                    $('.pz_yunkong_dengji').removeClass('hide');
                     $('.pz_yunkong').removeClass('hide');
                    var yunkong_push_level = info.yunkong_push_level.split(',');
                    $('#yunkong_username').val(info.yunkong_push_username);
                    $.each(yunkong_push_level,function(index, object){
                        $('input[name="yunkong-Yixiangdengji"][value='+object+']').prop('checked', true);
                    });
                }else{
                    $(":radio[name='yunkong'][value='否']").prop("checked", "checked");
                    $('.pz_yunkong_dengji').addClass('hide');
                    $('.pz_yunkong').addClass('hide')
                }
                
                //是否启用微信公众号推送
                if(info.wx_push_status == 1){
                    $(":radio[name='Tuisong'][value='是']").prop("checked", "checked");
                    $(".pz_tuisong_dengji").removeClass("hide");
                    var wx_push_level = info.wx_push_level.split(',');
                    $.each(send_sms_level,function(index, object){
                        $('input[name="wx_push-Yixiangdengji"][value='+object+']').prop('checked', true);
                    });
                    
                    var wx_push_user_id = info.wx_push_user_id.split(',');
                    $.each(wx_push_user_id,function(index, object){
                        $('input[name="wx-push-users"][value='+object+']').prop('checked', true);
                    });
                    
                }else{
                    $(":radio[name='Tuisong'][value='否']").prop("checked", "checked");
                    $(".pz_tuisong_dengji").addClass("hide");
                }
                
                $('#remark').val(info.remarks); //备注
                if (info.default_line_id == 1) {
                    $('.tishi').addClass('default-line');
                    $('#defaultlines').attr("disabled", false);
                    $('#defaultlines').prop("checked", "checked");
                } else if(info.default_line_id == 0 ) {
                  if(info.call_phone_id){
                      $('.tishi').removeClass('default-line');
                      $('#defaultlines').attr("disabled", false);
                  }else{
                      $('.tishi').removeClass('default-line');
                      $('#defaultlines').attr("disabled", true);
                      $('#defaultlines').attr("checked", false);
                  }
                } 
            },
            error: function(data) {
            }
        })
    }else{
        $('.modal-title').text('添加模板');
        $('#only_id_task_template').val('');
        Reset_checkform();
    }
     $("#task-template").modal("show");   
}

// 删除单个模板

function del_single() {
   
}

// 删除多个模板

function del_multiple() {
    $('#temp-delete').modal('show');
}

function Reset_checkform() {
    $('input[name="task_template_name"]').val(""); //任务名称
    $('select[name="scenarios_id"]').val(""); //话术
    //工作日期
    // $('#start_date').val(""); //指定开始日期
    // $('#end_date').val(""); //指定结束日期
    //工作时间
    // $('#start_time').timepicker({format: 'hh:ii:ss',defaultTime:'8:00',showMeridian:false});//指定开始时间
    // $('#end_time').timepicker({format: 'hh:ii:ss',defaultTime:'21:30',showMeridian:false});//指定结束时间
    $('.form-group.pz_formgroup_moban .pz_zhuijiadata').remove();

    //任务是否自动启动
    $(".radio-radioed[type='radio'][title='是']").removeAttr("checked");
    $(".radio-radioed[type='radio'][title='否']").click();
    $('input[name="again_call_status"]').prop('checked', false);
    $('#again_call_count').val('0'); 

    $('.tips-manualoperation').removeClass('hidden');
    $('.tips-auto').addClass('hidden');
    //ASR
    $("select[name='asr_id'] option:eq(0)").prop("selected", 'selected');
    $('input[name="robot_count"]').val(""); //机器人数量
    $('select[name="line_id"] option:eq(0)').prop("selected", 'selected'); //线路
    //是否开启人工转接
    $(".pz_rengongzuoxi").addClass("hide");
    $("#phone_id").val(""); //请选择人工座席

    //是否将意向客户加入CRM
    $(".pz_CRM_dengji").addClass("hide");
    $(".pz_Yixiangdengji .field-status input[name='Yixiangdengji']").prop("checked", false); //选择加入CRM的客户意向等级(可多选)
    $('input[name="crm-Yixiangdengji"]').prop('checked', false);
    $('input[name="crm-push-users"]').prop('checked', false);

    //是否启用微信公众号推送
    $(".pz_tuisong_dengji").addClass("hide");
    $(".pz_Yixiangdengji .field-status input[name='Yixiangdengji']").prop("checked", false); //选择要推送的客户意向等级(可多选)

    //是否触发短信发送
    $(".pz_duanxing_fasong").addClass("hide");
    $(".pz_Duanxinfasong .field-status input[name='Duanxinfasong']").prop("checked", false); //选择要发送的客户意向等级(可多选)
    $("#sms-template").val("");
    $('input[name="Duanxinfasong-dengji"]').prop('checked', false);
    
    $('input[name="yunkong-Yixiangdengji"]').prop('checked', false);
    $('input[name="wx_push-Yixiangdengji"]').prop('checked', false);
    $('#task_abnormal_remind_phone').val('');
    
    $('.tishi').removeClass('default-line');
    $('#defaultlines').attr("disabled",true);
	$('#defaultlines').attr("checked",false);
    //备注
    $('#remark').val("");


}

function save_template() {

    //任务名称
    var data = {};
    data.task_template_name = $('input[name="task_template_name"]').val();
    if (data.task_template_name == '') {
        alert('任务模板名称不能为空');
        return false;
    }
    //话术
    data.scenarios_id = $('select[name="scenarios_id"]').val();
    if(data.scenarios_id == ''){
        alert('请选择话术');
        return false;
    }
    //机器人数量
    data.robot_count = $('input[name="robot_count"]').val();
    if(data.robot_count == ''){
        alert('请输入机器人数量');
        return false;
    }
    //线路
    data.line_id = $('select[name="line_id"]').val();
    if(data.line_id == 0){
        alert('请选择线路');
        return false;
    }
    //ASR
    data.asr_id = $('select[name="asr_id"]').val();
    if(data.asr_id == 0){
        alert('请选择ASR');
        return false;
    }
    //验证是否开启重新呼叫功能
    data.is_again_call = $('input[name="is_again_call"]:checked').val();
    if (data.is_again_call == '是') {
        data.is_again_call = 1;
        //验证是否有选择需要重新呼叫的通话状态
        var again_call_status_length = $('input[name="again_call_status"]:checked').length;
        if (again_call_status_length == 0) {
            alert('至少需要选中一项重新呼叫的通话状态');
            return false;
        }
        data.again_call_status = [];
        $.each($('input[name="again_call_status"]:checked'), function(index, object) {
            data.again_call_status.push($(object).val());
        });
        //验证重新呼叫次数
        data.again_call_count = $('#again_call_count').val();
        if (data.again_call_count == 0) {
            alert('请选择重新呼叫次数');
            return false;
        }
    }
    //任务异常短信提醒的手机号码
    data.task_abnormal_remind_phone = $('#task_abnormal_remind_phone').val();
    if (data.task_abnormal_remind_phone != '') {
        // task_abnormal_remind_phone
        if (is_phone(data.task_abnormal_remind_phone) == false) {
            alert('任务异常短信提醒的手机号码格式错误');
            return false;
        }
    }
    //验证是否开启微信云控推送
    var yunkong_push_status = $("input[name='yunkong']:checked").val();
    if (yunkong_push_status == '是') {
        data.yunkong_push_status = 1;
        if (query_yunkong_username_status == 0) {
            alert('请先检索推送给微信云控的用户是否存在');
            return false;
        }
        if (query_yunkong_username_status == 2) {
            alert('该微信云控推送用户不存在');
            return false;
        }
        data.yunkong_push_username = $('#yunkong_username').val();
        data.yunkong_push_level = [];
        $.each($('input[name="yunkong-Yixiangdengji"]:checked'), function(index, object) {
            data.yunkong_push_level.push($(object).val());
        });
        if (data.yunkong_push_level.length == 0) {
            alert('请选择需要推送到微信云控的意向等级');
            return false;
        }
    }
    //验证是否发送短信
    var send_sms_status = $('input[name="Duanxinfasong"]:checked').val();
    if (send_sms_status == '是') {
        data.send_sms_status = 1;
        //验证是否有选择意向等级
        var level_length = $('input[name="Duanxinfasong-dengji"]:checked').length;
        if (level_length == 0) {
            alert('至少需要选中一项触发发送短信的意向等级');
            return false;
        }
        data.send_sms_level = [];
        $.each($('input[name="Duanxinfasong-dengji"]:checked'), function(index, object) {
            data.send_sms_level.push($(object).val());
        });
        data.sms_template_id = $('#sms-template').val();
    } else {
        data.send_sms_status = 0;
    }
    var is_add_crm = $('input[name="joinCRM"]:checked').val();
    if (is_add_crm == '是') {
        data.is_add_crm = 1;
        data.add_crm_level = [];
        if ($('input[name="crm-Yixiangdengji"]:checked').length == 0) {
            alert('请选择加入CRM的客户意向等级');
            return false;
        }
        $.each($('input[name="crm-Yixiangdengji"]:checked'), function(index, object) {
            data.add_crm_level.push($(object).val());
        });
        data.crm_push_user_id = $("input:checkbox[name='crm-push-users']:checked").map(function(index, elem) {
            return $(elem).val();
        }).get().join(','); //多推送 把多个用户的id 用逗号分隔  1,3,5


    } else {
        data.is_add_crm = 0;
        data.add_crm_level = [];
        data.crm_push_user_id = '';
    }
    var wx_push_status = $('input[name="Tuisong"]:checked').val();
    if (wx_push_status == '是') {
        data.wx_push_status = 1;
        data.wx_push_level = [];
        if ($('input[name="wx_push-Yixiangdengji"]:checked').length == 0) {
            alert('请选择微信推送的客户意向等级');
            return false;
        }
        $.each($('input[name="wx_push-Yixiangdengji"]:checked'), function(index, object) {
            data.wx_push_level.push($(object).val());
        });

        data.wx_push_user_id = $("input:checkbox[name='wx-push-users']:checked").map(function(index, elem) {
            return $(elem).val();
        }).get().join(','); //多推送 把多个用户的id 用逗号分隔  1,3,5

        //data.wx_push_user_id = $('#wx-push-users').val();  //鲁健2019-2-16 注释
        if (data.wx_push_user_id == '') {
            alert('请选择推送的人员');
            return false;
        }
    } else {
        data.wx_push_status = 0;
        data.wx_push_level = [];
        data.wx_push_user_id = '';
    }
    
    //是否自动开启
    data.is_auto = $('input[name="is_auto"]:checked').val();
    var is_ok = true;
    if(data.is_auto == 1){
      //指定日期
      var start_date_objects = $('input[name="start_date"]');
      var end_date_objects = $('input[name="end_date"]');
      data.date = [];
    	var curDate = getFormatDate(10);
			var curDateNum = parseInt(curDate.replace(/-/g,''),10);
      $.each(start_date_objects, function(index, object) {
           var  time = [];
           if($(object).val() && $(end_date_objects[index]).val()){
      			if($.myTime.DateToUnix($(object).val()) > $.myTime.DateToUnix(end_date_objects.eq(index).val())){
      				alert('开始日期不能大于结束日期');
      				is_ok = false;
      				return false;
      			}else{
      			    time.push($(object).val());
                time.push($(end_date_objects[index]).val()); 
                data.date.push(time);
      			}	
           }else{
              alert('工作日期开始于结束不一致');
              is_ok = false;
              return false;
           }
      });
      
      if(!is_ok){
        return false;
      }
      //判断结束时间是否大于当前时间。
		  var end_date_array = new Array();
		  $.each(end_date_objects,function(index,object){
		    end_date_array[index] = parseInt($(object).val().replace(/-/g,''),10);
		  })
		  var max_end_date = Math.max.apply(null, end_date_array)
		  var curDate = getFormatDate(10);
			var curDateNum = parseInt(curDate.replace(/-/g,''),10);
		  if(max_end_date < curDateNum){
		    alert('结束时间不能小于当前日期');
		     is_ok = false;
		     return false;
		  }
		  
      //指定时间
      var start_time_objects = $('input[name="start_time"]');
      var end_time_objects = $('input[name="end_time"]');
      data.times = [];
      $.each(start_time_objects, function(index, object) {
          var  time = [];
          if($(object).val() && $(end_time_objects[index]).val()){
              if(!judge($(object).val() , end_time_objects.eq(index).val())){
                  alert('开始时间不能大于结束时间');
                  is_ok = false;
                  return false;
              }else
              if(judge($(object).val(),'8:00')){
  				alert('开始时间不能小于8:00');
  				is_ok = false;
  				return false;
  			}else
  			if(!judge(end_time_objects.eq(index).val(),'21:31')){
  				alert('开始时间不能大于21:30');
  				is_ok = false;
  				return false;
  			}else{
  			    time.push($(object).val());
                  time.push($(end_time_objects[index]).val()); 
                  data.times.push(time);
  			}
           }else{
              alert('工作日期开始于结束不一致');
              is_ok = false;
              return false;
           }
      });
    }else{
      data.date = [];
      data.times = [];
    }
    if(!is_ok){
      return false;
    }
    
    //是否设置默认线路
    // data.is_default_line = $('input[name="is_default_line"]').val();
    if ($("input[type='checkbox'][name='is_default_line']").is(':checked')) {
        data.is_default_line = 1;
    } else {
        data.is_default_line = 0;
    }
    //备注
    data.remark = $('textarea[name="remark"]').val();
    data.id = $('#only_id_task_template').val();
  // debugger;
    var href = "/user/plan/add_edit_template";
    console.log(data);
    $.ajax({
        type: "POST",
        dataType: 'json',
        url: href,
        data: data,
        success: function(result) {
            alert(result.msg);
            $('#task-template').modal('hide');
            show_data();
        },
        error: function(data) {
            alert("提交失败");
        }
    })
}
var Paging = new Paging01();
		Paging.init_args({
		// url:
		page: 1, //初始页码
		limit: 10, //初始每页显示的数据量
		paging_class: 'paging', //放置分页的class
		callback: show_data //回调函数 比如show_datas(页码, 显示条数)
	});
 show_data();
 function show_data(page,limit) {
    if (!page){
		  page = 1;
  	}
  	if(!limit){
  		limit = 10;
  	}
  	var data = {};
  	var url = "/user/plan/task_template"
  	data.templateName = $('#keyword').val();
  	data.page = page;
	  data.limit = limit;
	  $.ajax({
  		url:url,
  		dataType:'json',
  		type:'post',
  		data:data,
  		success:function(result){
  			console.log(result);
  			if(result.code === 0){
  				var htmls = '';
  				if(result.data.list.length == 0){
  					$('#consumeempty').show();
  					Paging.paging(1, 0, 10);
  					$('.footerB').hide();
  				}else{
  					$('#consumeempty').hide();
  					$('.footerB').show();
  					var total = result.data.page;   //总页数
  					var Nowpage = result.data.Nowpage;  //当前页码
  					var count  = result.data.total;  //总条数
  					window.count = count;
  					var Nowpage = parseInt(Nowpage);
  					var i = (Nowpage - 1) * limit + 1;
  					$.each(result.data.list,function(index,object){
  						data.i = i;
  						var html = $('#data_task_template').html();
  						html = html.replace('{%id%}',object.id);
  						html = html.replace('{%sequence%}',i);
  						html = html.replace('{%templateName%}',object.template);
  						html = html.replace('{%id%}',object.id);
  						html = html.replace('{%id%}',object.id);
  					    html = html.replace('{%remarks%}',object.remarks);
  						htmls += html;
  						i++;
  					});
  					$('#totalData').text(result.data.total);
  					//Nowpage  当前页
  					//count    数据总条数
  					//total    总共页数
  					//limit    分页数量
  					//Paging.paging(当前页码, 总数量, 每页显示的条数)
       			Paging.paging(Nowpage, count, limit);
  				}
  				$('#taskTemplateList').html(htmls);
  				election();
  			}
  		},
  		error: function(error) {
  			console.log(error);
  			alert('数据获取失败！');
  		}
  	});
  }
 
function no_query(){
	$('#keyword').val('');
	show_data();
}