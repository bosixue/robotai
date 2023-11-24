
$(function(){
   $(".selectpicker").selectpicker({
		     'deselectAllText':'取消',
         'selectAllText': '全选',
		  })
})

//我的话术
//给我的话术下内容添加边框
function getscenarios(obj){
	$(obj).addClass('active').siblings().removeClass('active');
}
//我的话术


//流程页面
 $('.talk-list-item').click(function(){
	 $(this).addClass('active').siblings().removeClass('active');

 });


//导入话术
function loadexcel(){
	$('#exampleModal').modal('show');
}


function sendScene_effectTmp(){
  console.log(window.chaos_num);
  var url = "/user/scenarios/scenarios_effectTmp"
  $.ajax({
		type: 'POST',
		dataType: "json",
		data: {
			chaos_num: window.chaos_numsend
		},
		url: url,
		success: function(result) {
		  console.log(result);
		  if(result.code == 1){
		    $("#send_effectTmp .progress-bar-data").width((result.data.num_count) + '%');
        $("#send_effectTmp .Progress_value").html((result.data.num_count)+ '%');
		  }
		},
		error: function() {
		}
	});
}

//话术下发弹框显示
function scene_send(){
  
  $("#sceneSend").modal('show');
  var id =$("#nowsceneID").val();
  $("#scenarios_name").data('id',id);
  $.post("/user/scenarios/get_sendout_staff", {
		'id': id
	}, function(data) {
	  console.log(data);
		if(data.code == 1){
		  $('#scenarios_name').html(data.data.scenarios_name);
		  var option = '<option value="">请选择下发用户类型</option>';
		  $.each(data.data.role_name,function(index,value){
		    option += '<option value="'+value.role_id+'">'+value.role_name+'</option>'
		  });
		  $('#role_name').html(option);
		  var option2 = '<option value="">请选择下发用户</option>';
//		  $.each(data.data.role_list,function(index,value){
//		    option2 += '<option value="'+value.id+'">'+value.username+'</option>'
//		  });
		  $('#username').html(option2);
		  
		  $('.filter-option').text('请选择下发用户');
		  $('.dropdown-menu.open').hide();
		}
	});

}
function get_username(id){
  $('.dropdown-menu.open').show();
  $.post("/user/scenarios/get_sendout_staff", {
		'role_id': id
	}, function(data) {
	  console.log(data);
	  if(data.code == 1){
	    var option2 = '';
		  $.each(data.data.role_list,function(index,value){
		    option2 += '<option value="'+value.id+'">'+value.username+'</option>'
		  });
		  $('#username').html(option2);
		
		  
      //刷新容器
      $("#username").selectpicker('refresh');
      
	  }
	});
}
function sendComfire(){
  var data = {};
  data.scenarios_id = $("#scenarios_name").data('id');
  data.role_id = $('#role_name').val();
  data.username = $('#username').val();
  data.scene_remarks = $('#scene_remarks').val();
  if(!data.role_id){
    alert('请选择下发用户类型');
    return false;
  }
  if(!data.username){
    alert('请选择下发用户');
    return false;
  }
  window.chaos_numsend = (new Date()).valueOf(); 
  $('#sceneSend').modal('hide');
  $("#send_effectTmp .progress-bar-data").width(0 + '%');
  $("#send_effectTmp .Progress_value").html('0.00' + '%');
  $('#send_effectTmp .finish').addClass('hidden');
  $('#send_effectTmp .import').removeClass('hidden');
  $('#send_effectTmp').modal('show');
  window.import_dingshisend = window.setInterval(sendScene_effectTmp, 1000);
  data.chaos_num = window.chaos_numsend;
  var url = "/user/scenarios/give_subordinate"
	$.ajax({
		type: 'POST',
		dataType: "json",
		data: data,
		url: url,
		success: function(data) {
  		console.log(data);
  		window.clearInterval(window.import_dingshisend);
 			if(data.code == 0){
 				  $('#send_effectTmp .import').addClass('hidden');
          $('#send_effectTmp .finish').removeClass('hidden');
          $('#effect-tips-content_send').html(data.msg);
 			}else{
 			   $("#send_effectTmp .progress-bar-data").width(100.00 + '%');
         $("#send_effectTmp .Progress_value").html('100.00' + '%');
          if($('#send_effectTmp .Progress_value').html() == '100.00%'){
            //延迟1秒在执行 加强体验度
            setTimeout(function(){
              $('#send_effectTmp .import').addClass('hidden');
              $('#send_effectTmp .finish').removeClass('hidden');
              $('#effect-tips-content_send').html('话术下发成功！');
            },1000)
            $('#upload_ok_send').click(function(){
              $('#send_effectTmp').modal('hide');
            })
          }
 			}
 		},
		error: function() {
		}
	});
}