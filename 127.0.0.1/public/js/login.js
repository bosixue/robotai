var send_sms_verify_code_status = true,
		register_submit_status = true,
		sTips;
//点击发送短信验证码
function click_send_sms_verify_code()
{
	$('#send_sms_verify_code').unbind('click');
	$('#send_sms_verify_code').click(function(){
		if(send_sms_verify_code_status == true){
			send_sms_verify_code_status = false;
			
			
			var data = {};
			//手机号码
			data.phone = $('#register-phone').val();
			//验证码
			data.verify_code = $('#verify_code').val();
			if(data.phone == ''){
				alert('请输入手机号码');
				send_sms_verify_code_status = true;
				return false;
			}
			if(data.phone.length != 11 || is_phone(data.phone) === false){
				alert('手机号码格式错误');
				send_sms_verify_code_status = true;
				return false;
			}
			if(data.verify_code == ''){
				alert('请输入验证码');
				return false;
			}
			if(data.verify_code.length != 4){
				alert('验证码错误');
				send_sms_verify_code_status = true;
				return false;
			}
			var url = '/api/users/send_sms_verify_code';
			$.ajax({
				type:'POST',
				dataType:'json',
				data:data,
				url:url,
				success:function(result){
					if(result.code == 0){
						// alert('发送成功');
						waitFor60s($('#send_sms_verify_code'));
					}else if(result.code == 3){
						send_sms_verify_code_status = true;
						alert(result.msg);
					}else{
						send_sms_verify_code_status = true;
						alert('发送失败');
					}
				},
				error:function(){
					send_sms_verify_code_status = true;
					alert('提交失败');
				}
			});
		}
	})
}
function click_verify_code()
{
	$('#regist-verifyimg').unbind('click');
	$("#regist-verifyimg").click(function(){
		var verifyimg = $(this).attr('src');
		if(verifyimg.indexOf('?')>0){
			$(this).attr("src", verifyimg+'&random='+Math.random());
		}else{
			$(this).attr("src", verifyimg.replace(/\?.*$/,'')+'?'+Math.random());
		}
	});
}
function waitFor60s(Object)
{
  var s = 60;
  Object.text(s + 's');
  sTips = setInterval(function () {
    if(s === 0){
      //停止
      clearInterval(sTips);
      Object.text('重新发送');
      send_sms_verify_code_status = true;
    }else{
      s--;
      Object.text(s + 's');
    }
  },1000);
}
function is_phone(phone)
{
    //手机号正则
	var phoneReg = /^1(?:3\d|4[4-9]|5[0-35-9]|6[67]|7[013-8]|8\d|9\d)\d{8}$/;
	//电话
	if (!phoneReg.test(phone)) {
		return false;
	}else{
	  return true;
	}
}
function reset_register()
{
	$('#register-phone').val('');
	$('#verify_code').val('');
	$('#sms_verify_code').val('');
	$('#register-password').val('')
	$('#register-confirm-password').val('')
}
//点击注册
function click_register()
{
	$('#register').unbind('click');
	$('#register').click(function(){
		if(register_submit_status == true){
			register_submit_status = false;
			var data = {};
			//手机号码
			data.phone = $('#register-phone').val();
			if(data.phone == ''){
				alert('请输入手机号码');
				register_submit_status = true;
				return false;
			}
			if(data.phone.length != 11 || is_phone(data.phone) === false){
				alert('手机号码格式错误');
				register_submit_status = true;
				return false;
			}
			//验证码
			data.verify_code = $('#verify_code').val();
			if(data.verify_code == ''){
				alert('请输入验证码');
				register_submit_status = true;
				return false;
			}
			if(data.verify_code.length != 4){
				alert('验证码错误');
				register_submit_status = true;
				return false;
			}
			//短信验证码
			data.sms_verify_code = $('#sms_verify_code').val();
			if(data.sms_verify_code == ''){
				alert('请输入短信验证码');
				register_submit_status = true;
				return false;
			}
			//密码
			data.password = $('#register-password').val();
			if(data.password == ''){
				alert('请输入密码');
				register_submit_status = true;
				return false;
			}
			if(data.password != $('#register-confirm-password').val()){
				alert('输入的密码不一致');
				register_submit_status = true;
				return false;
			}
			//url
			var url = '/api/users/create_user_api';
			$.ajax({
				type:'POST',
				dataType:'json',
				data:data,
				url:url,
				success:function(result){
					console.log(result);
					if(result.code == 0){
						alert('注册成功');
						reset_register();
						$('#show_login').click();
					}else if(result.code == 3){
						alert(result.msg);
					}else{
						alert('注册失败');
					}
					register_submit_status = true;
				},
				error:function(){
					alert('提交失败');
					register_submit_status = true;
				}
			});
		}
	});
}


//页面加载好后
$(function(){
	click_send_sms_verify_code();
	click_verify_code();
	click_register();
})

















// ---