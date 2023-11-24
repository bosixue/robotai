function sendSms(){
    $("#sendSms").modal("show");
    var url = "/user/sms/get_msm_path"
    $.ajax({
			type: "POST",
			dataType: 'json',
			url: url,
			success: function(data) {
			  console.log(data);
			  $('#data_template_option').html("<option value=''>请选择模板</option>")
			  $('#data_channel_option').html("<option value=''>请选择通道</option>")
			  var html_channel = "";
			  var html_template = "";
			  if (data.code == 1 ) {
			    $.each(data.data.channel,function(index,obj){
			       html_channel = "<option value='"+obj.id+"'>"+obj.name+"</option>";
			       $('#data_channel_option').append(html_channel);
			    })
			    $.each(data.data.template,function(index,obj){
			       html_template += "<option value='"+obj.id+"'>"+obj.name+"</option>";
			       $('#data_template_option').append(html_template);
			    })
			  } else {
			    alert('数据获取失败');
			  }
			},
			error: function(data) {
			}
		})
}
function effectTmp(){
  var url = "/user/plan/outexcel_degree"
  $.ajax({
		type: 'POST',
		dataType: "json",
		data: {
			chaos_num: window.chaos_num
		},
		url: url,
		success: function(result) {
		  console.log(result);
		  if(result.code == 1){
		    $(".progress-bar-data").width((result.data.percentage) + '%');
        $(".Progress_value").html((result.data.percentage)+ '%');
		  }
		},
		error: function() {
		}
	});
}
function click_submit(){
  var excel = document.getElementById("excelId").files[0];
  var formFile = new FormData();
  var data_channel = $('#data_channel_option').val();
  var data_template = $('#data_template_option').val();
  if(!data_channel){
    alert('请选择短信通道')
    return false;
  }
  if(!data_template){
    alert('请选择短信模板')
    return false;
  }
  if(!excel){
    alert('请选择导入文件')
    return false;
  }
  $('#effectTmp').modal('show');
  $("#sendSms").modal("hide");
  window.chaos_num = (new Date()).valueOf();
	$(".progress-bar-data").width(0 + '%');
  $(".Progress_value").html('0.00' + '%');
  $('.finish').addClass('hidden');
  $('.import').removeClass('hidden');
  window.import_dingshi = window.setInterval(effectTmp, 1000);
  formFile.append("excel",excel);
  formFile.append("channel",data_channel);
  formFile.append("template",data_template);
  formFile.append("chaos_num",window.chaos_num);
  var url = "/user/sms/sending_msm"
  $.ajax({
    type: 'post',
    data: formFile,
    dataType: 'json',
    url: url,
    cache: false,
    contentType: false,    //不可缺
    processData: false,    //不可缺
    success: function (data) {
      window.clearInterval(window.import_dingshi);
      console.log(data);
      if(data.code == 1){
        $(".progress-bar-data").width(100.00 + '%');
				$(".Progress_value").html('100.00' + '%');
				if($('.Progress_value').html() == '100.00%'){
					//延迟1秒在执行 加强体验度
					setTimeout(function(){
						$('.import').addClass('hidden');
						$('.finish').removeClass('hidden');
						$('#effect-tips-content').html(data.msg);
					},1000);
					$('#upload_ok').unbind('click');
					$('#upload_ok').click(function(){
					  show_data();
					  $('#effectTmp').modal('hide');
					})
				}
      }else{
        setTimeout(function(){
  				$('.import').addClass('hidden');
  				$('.finish').removeClass('hidden');
  				$('#effect-tips-content').html(data.msg);
  			},1000);
  			$('#upload_ok').unbind('click');
  			$('#upload_ok').click(function(){
  			  show_data();
  			  $('#effectTmp').modal('hide');
  			})
      }
    },
    error: function (e) {
      window.clearInterval(window.import_dingshi);
    }
  })
}

function formSoundv(Object){
	if(Object.files.length > 0 ){
		var excel = document.getElementById("excelId").files[0];
		var filePath = $('#excelId').val().toLowerCase().split(".");
		var fileType =  filePath[filePath.length - 1]; //获得文件结尾的类型如 zip rar 这种写法确保是最后的
		var file = $("#excelId");
		if(!(fileType == "xlsx"|| fileType == "xls")){
    		alert('文件格式不符合要求！');
				file.after(file.clone().val(""));      
				file.remove();  
    		return 
    }else if(excel.size>1048576){
        alert('错误！请上传不超过1M的文件');
				file.after(file.clone().val(""));      
				file.remove();  
        return
    }
	}
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
  if (!page) {
      page = 1;
  }
  if (!limit) {
      limit = 10;
  }
  var data = {};
  var url = "/user/sms/ajax_smssend_list"
  data.startTime = $('#start_create_time').val();
  data.endTime = $('#end_create_time').val();
  data.type = 1;
  data.page = page;
  data.limit = limit;
  $.ajax({
      url: url,
      dataType: 'json',
      type: 'post',
      data: data,
      success: function(result) {
          console.log(result);
          if (result.code === 0) {
              var htmls = '';
              if (result.data.list.length == 0) {
                  $('#consumeempty').show();
                  $('.footerA').hide();
                  window.count = 0;
              } else {
                  $('#consumeempty').hide();
                  $('.footerA').show();
                  var total = result.data.page; //总页数
                  var Nowpage = result.data.Nowpage; //当前页码
                  var count = result.data.total; //总条数
                  window.count = count;
                  var Nowpage = parseInt(Nowpage);
                  var i = (Nowpage - 1) * limit + 1;
                  $.each(result.data.list, function(index, object) {
                      data.i = i;
                      var html = $('#sign_template').html();
                      html = html.replace('{%id%}', object.id);
                      html = html.replace('{%sequence%}', i);
                      html = html.replace('{%channel_name%}', object.channel_name);
                      html = html.replace('{%template_name%}', object.template_name);
                      html = html.replace('{%import_num%}', object.phone_count);
                      html = html.replace('{%is_state%}', object.is_state);
                      html = html.replace('{%create_time%}', object.create_time);
                      html = html.replace('{%finish_time%}', object.ok_time);
                      html = html.replace('{%operation%}', object.id);
                      htmls += html;
                      i++;
                  });
                  //Nowpage  当前页
                  //count    数据总条数
                  //total    总共页数
                  //limit    分页数量
                  //Paging.paging(当前页码, 总数量, 每页显示的条数)
                  Paging.paging(Nowpage, count, limit);
              }
              $('#show_datas').html(htmls);
              election();
          }
      },
      error: function(error) {
          console.log(error);
          alert('数据获取失败！');
      }
  });
}

function reset_click(){
  $('#start_create_time').val('');
  $('#end_create_time').val('');
  show_data();
}

function look_smsdetail(id){
  window.location.href = "/user/sms/smsdetail?id="+id+"";
}


function election() {
    console.log('引用成功')
    console.log(window.count)
    if ($('.all_checked_count').is(":checked")) {
        $("input[name='checkids'][type='checkbox']").prop("checked", true);
        $("input[name='all_checked'][type='checkbox']").prop("checked", true);
        $('#check_count').text(window.count);
        $('#user_count').text(window.count);
    } else {
        $('#user_count').text(0);
        $('#check_count').text(0);
        $("input[name='all_checked'][type='checkbox']").prop("checked", false);
    }
    $("input[name='all_checked'][type='checkbox']").click(function() {
        if ($("input[name='all_checked'][type='checkbox']").is(":checked")) {
            $("input[name='checkids'][type='checkbox']").prop("checked", true);
            $(".all_checked_count").prop("checked", false);
        } else {
            $("input[name='checkids'][type='checkbox']").prop("checked", false);
            $(".all_checked_count").prop("checked", false);
        }
        $('#user_count').text($("input[name='checkids'][type='checkbox']:checked").length);
        $('#check_count').text($("input[name='checkids'][type='checkbox']:checked").length);
    });
    //子复选框的事件
    $('input[type="checkbox"][name="checkids"]').click(function() {
        //当没有选中某个子复选框时，check-all取消选中
        if (!$('input[type="checkbox"][name="checkids"]').checked) {
            $("input[name='all_checked'][type='checkbox']").prop("checked", false);
            $(".all_checked_count").prop("checked", false);
        }
        var chsub = $("input[name='checkids'][type='checkbox']").length; //获取checkids的个数
        var checkedsub = $("input[name='checkids'][type='checkbox']:checked").length; //获取选中的checkids的个数
        if (checkedsub == chsub) {
            $("input[name='all_checked'][type='checkbox']").prop("checked", true);
            $(".all_checked_count").prop("checked", false);
        }
        $('#user_count').text(checkedsub);
        $('#check_count').text(checkedsub);
    });
    $('.all_checked_count').click(function() {
        if ($(this).prop('checked') === true) {
            $.each($('.all_checked_count'), function(index, obj) {
                $(obj).prop("checked", true);
            });
            $("input[name='checkids'][type='checkbox']").prop("checked", true);
            $("input[name='all_checked'][type='checkbox']").prop("checked", true);
            $('#check_count').text(window.count);
            $('#user_count').text(window.count);
        } else {
            $.each($('.all_checked_count'), function(index, obj) {
                $(obj).prop("checked", false);
            });
            $("input[name='checkids'][type='checkbox']").prop("checked", false);
            $("input[name='all_checked'][type='checkbox']").prop("checked", false);
            $('#check_count').text(0);
            $('#user_count').text(0);
        }
    });
}
