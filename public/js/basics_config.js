//提交数据
$('#submit').click(function(){
  var site_name = $('#site_name').val();
  if(site_url == ''){
    alert('请输入网站名称');
    return false;
  }
  var site_url = $('#site_url').val();
  if(site_url == ''){
    alert('请输入官网网址');
    return false;
  }
  var imageObject = document.getElementById("login_file").files[0]
  var address = $('#address').val();
  var description = $('#description').val();
  var site_record_number = $('#site_record_number').val();
  var site_contact_number = $('#site_contact_number').val();
  var formFile = new FormData();
  formFile.append("logo", imageObject); //加入文件对象
  formFile.append('site_name', site_name);
  formFile.append('site_url', site_url);
  formFile.append('address', address);
  formFile.append('description', description);
  formFile.append('site_record_number', site_record_number);
  formFile.append('site_contact_number', site_contact_number);
  
  $.ajax({
    data: formFile,
  	url: "/user/system/update_basics_config",
  	type: "POST",
  	dataType: "json",
  	cache: false,			//上传文件无需缓存
  	processData: false,		//用于对data参数进行序列化处理 这里必须false
  	contentType: false, 	//必须
  	success: function(result){
  	  if(result.code == 0){
  	    alert('更新成功');
  	    window.location.href = '';
  	  }else{
  	    alert('更新失败');
  	  }
  	},
  	error: function(result){
  		alert('更新失败');
  	}
  });
  
});

//点击上传LOGO
$('#click_upload_login_file').click(function(){
  $('#login_file').click();
});

//选中LOGO图片后
$('#login_file').change(function(){
  // var file = $(this).files[0];
  // form_soundv($(this));
  var imageObject = document.getElementById("login_file").files[0];
  var file_url = getObjectURL(imageObject);
  $('#show_logo_file').attr('src', file_url);
  $('#show_logo_file').show();
});

// 把文件转换成可读URL
function getObjectURL(file) {
    var url = null;
    if (window.createObjectURL != undefined) { // basic
        url = window.createObjectURL(file);
    } else if (window.URL != undefined) { // mozilla(firefox)
        url = window.URL.createObjectURL(file);
    } else if (window.webkitURL != undefined) { // webkit or chrome
        url = window.webkitURL.createObjectURL(file);
    }
    return url;
}



