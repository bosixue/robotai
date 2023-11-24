  var html ='';
      html  ="<style>.star{color:red;margin-right:3px;}.verification-code{position:relative;}.send-code{position: absolute;top: 17%;right: 18px;}</style>"
      +'<div class="modal fade in" id="export-validation" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="false" style="display: none;">'
      +'  <div class="modal-dialog modal-sm" style="width: 550px;">'
      +'		<div class="modal-content">'
      +'				<div class="modal-header">'
      +'						<button type="button" class="close" data-dismiss="modal" aria-label="Close">'
      +'							 <span aria-hidden="true">×</span>'
      +'						</button>'
      +'						<h4 class="modal-title" id="export-val"><span>导出验证</span></h4>'
      +'			 </div>'
      +'			 <div class="modal-body pagelists">'
      +'					<form id="distributionform" method="post" class="form-horizontal margintop" enctype="multipart/form-data">'
      +'           <div class="form-group  ">'
      +'           <label class="col-lg-4 control-label">  <span class="star">*</span>请输入电话号码：</label>'
      +'             <div class="col-lg-6 col-sm-6">'
      +'               <input type="text" class="form-control" name="" id="export-phone" value="" placeholder="请输入电话号码">'
      +'             </div>'
      +'           </div>'
      +'           <div class="form-group  ">'
      +'            <label class="col-lg-4 control-label"><span class="star">*</span>请输入验证码：</label>'
      +'            <div class="col-lg-6 col-sm-6 verification-code">'
      +'              <input type="text" class="form-control" name="" value="" id="export-verification-code" placeholder="请输入验证码">'
      +'              <a href="javascript:;" class="send-code" id="new_real_time">发送短信验证码</a>'
      +'            </div>'
      +'          </div>'
      +'          <div class="form-group  ">'
      +'           <label class="col-lg-4 control-label">备注：</label>'
      +'           <div class="col-lg-6 col-sm-6">'
      +'             <textarea name="name" rows="5" cols="80" id="export-remark" class="form-control" maxlength="30" placeholder="请输入备注信息，限定30个字以内"></textarea>'
      +'           </div>'
      +'         </div>'
      +'				 </form>'
      +'			 </div>'
      +'			 <div style="clear:both"></div>'
      +'			 <div class="modal-footer">'
      +'					 <button type="button" class="btn btn-default btncloseprojectile-frame" data-dismiss="modal">取消</button>'
      +'           <button class="btn btn-primary submit-btn btnokprojectile-frame" id="export_val" onclick="" type="button">确 定</button>'
      +'			</div>'
      +'	 </div>'
      +'  </div>'
      +' </div>'

// 验证手机号
function isPhoneNo(phone) {
  var pattern = /^1[345789]\d{9}$/;
  return pattern.test(phone);
}
function msm_send(){
  $('#new_real_time').click(function(){
  window.phone = '';
  var phone = $('#export-phone').val();
  window.phone = phone;
  if(phone){
    if(!isPhoneNo(phone)){
      alert('请输入正确的手机号')
      return false;
    }
  }else{
    alert('请输入手机号');
    return false;
  }
  var url = "/user/scenarios/sms_send_verification"
  $.ajax({
		type: 'POST',
		dataType: "json",
		data: {
			phone: phone,
		},
		url: url,
		success: function(result){
		  console.log(result);
		  if(result.code === 0 ){
		    var time = 60;
        var placement = 'new_real_time';
      	getRandomCode(time,placement);
		  }else{
		    alert('发送失败');
		  }
		},
		error: function() {
		  alert('发送失败');
		}
	});
})
}

 function attitude(placement){
    var time = $('#'+placement).html();
    if(time == '0秒'){
      $('#'+placement).html('发送短信验证码');
      $('#'+placement).css({"color":"#0e90fe","cursor":"pointer"});
      msm_send();
    }else{
      $('#'+placement).css({"color":"gray","cursor":"no-drop"});
      $('#'+placement).unbind("click" );
    }
 }
function getRandomCode(time,placement) {
 attitude(placement);
 if (time === 0) { 
     time = 60;
     return;
 } else { 
    time--;
    $('#'+placement).html(time+'秒');
 } 
 setTimeout(function() { 
     getRandomCode(time,placement);
 },1000);
}

function export_val(type){
  var data = {};
  data.phone = $('#export-phone').val();
  data.code = $('#export-verification-code').val();
  data.remark =$('#export-remark').val();
  if(type == 1){
    data.export = '话术配置';
    data.export_name = '备份话术';
  }else if(type == 2){
    data.export = '任务管理';
    data.export_name = '导出全部号码';
  }else if(type == 3){
    data.export = '任务管理';
    data.export_name = '导出未拨打号码';
  }else if(type == 4){
    data.export = '号码管理';
    data.export_name = '导出号码';
  }else if(type == 5){
    data.export = '号码管理';
    data.export_name = '导出号码';
  }else if(type == 6){
    data.export = '当天通话记录';
    data.export_name = '导出话单';
  }else if(type == 7){
    data.export = '当天通话记录';
    data.export_name = '导出号码';
  }else if(type == 8){
    data.export = '历史通话记录';
    data.export_name = '导出话单';
  }else if(type == 9){
    data.export = '历史通话记录';
    data.export_name = '导出号码';
  }else if(type == 10){
    data.export = '客户管理';
    data.export_name = '导出号码';
  }
  
  if(!data.phone){
    alert('请输入电话号码');
    return false;
  }
  if(!isPhoneNo(data.phone)){
    alert('请输入正确的手机号')
    return false;
  }
  if(!data.code){
    alert('请输入验证码');
    return false;
  }
  var is_int = /^[0-9]{6}$/;
  if(!is_int.test(data.code)){
    alert('请输入正确格式的验证码');
    return false;
  }
  if(window.phone != data.phone){
    alert('提交的号码与接收短信的手机号码不一致');
    return false;
  }
  if(!window.phone){
    alert('请获取正确验证码');
    return false;
  }
  console.log(data);
  var url = "/user/scenarios/join_verification"
  $.ajax({
		type: 'POST',
		dataType: "json",
		data: data,
		url: url,
		success: function(result){
		  console.log(result);
		  if(result.code == 0){
		    alert(result.msg);
		    $('#export-validation').modal('hide');
		    if(type == 1){
		      outexcel();
		    }else if(type == 2){
		      outexcel() 
		    }else if(type == 3){
		      outoutexcelNotCall() 
		    }else if(type == 4){
		      export_phone_group() 
		    }else if(type == 5){
		      export_phone_list() 
		    }else if(type == 6){
		      exportlistExcel() 
		    }else if(type == 7){
		      outexcel() 
		    }else if(type == 8){
		      exportlistExcel() 
		    }else if(type == 9){
		      outexcel() 
		    }else if(type == 10){
		      constomerExport() 
		    }
		  }else{
		    alert(result.msg);
		  }
		},
		error: function() {
		  alert('发送失败');
		}
	});
}
      
      
      
      
      
      
      
      
      
      
      
      
      
      
      
      
      
      
