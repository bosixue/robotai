function isPhoneNo(phone) {
    var pattern = /^1[3456789]\d{9}$/;
    return pattern.test(phone);
}

//添加号码组

function addPhone() {
    $('#semantics-branchName').val('');
    $('#remarks_box').val('');
    $('#only_box_id').val('');
    $("#addGroup").modal('show');
}

function add_edit_phone_box() {
    var data = {};
    data.id = $('#only_box_id').val();
    if (data.id) {
        data.name = $('#zbranchName_box').val();
        data.remarks = $('#zremarks_box').val();
    } else {
        data.name = $('#semantics-branchName').val();
        data.remarks = $('#remarks_box').val();
    }
    if (data.name == '') {
        alert('请填写组名');
        return false;
    }
    var href = "/user/plan/add_edit_phone_box";
    $.ajax({
        type: "POST",
        dataType: 'json',
        url: href,
        data: data,
        success: function(result) {
            if (result.code == 0) {
                alert(result.msg);
                show_data_box(1,10);
            } else {
                alert(result.msg);
            }
            $('#addGroup').modal('hide');
            $('#editGroup').modal('hide');
        },
        error: function(data) {
            alert("提交失败");
        }
    })

}

//编辑号码组

function editPhone(id) {
    $("#editGroup").modal('show');
    var href = "/user/plan/get_editPhone";
    $('#only_box_id').val('');
    $.ajax({
        type: "POST",
        dataType: 'json',
        url: href,
        cache: false,
        data: {
            id: id
        },
        success: function(result) {
            console.log(result);
            var info = result.data;
            $('#zbranchName_box').val(info.box_name);
            $('#count_phone').text(info.count);
            $('#zremarks_box').val(info.remarks);
            $('#only_box_id').val(info.id);
        },
        error: function(data) {
            alert("提交失败");
        }
    })
}

//添加/编辑单个号码组

function edit_add_singlePhone(type, id) {
    $('#phone').val('');
    $('#nickname').val('');
    $('#phone_box_select').val(0);
    var href = "/user/plan/list_phone_box";
    $.ajax({
        type: "POST",
        dataType: 'json',
        url: href,
        success: function(result) {
            console.log(result);
            if (result.code == 0) {
                var option = '';
                option += "<option value='0'> 请选择</option>";
                $.each(result.data.list, function(i, val) {
                    option += "<option value='" + i + "'>" + val + "</option>";
                })
                $('#phone_box_select').html(option);
            }
            if (type == 0) {
                $('#only_phone_id').val('');
                $("#singleTel span").text("编辑单个号码");
                var url = "/user/plan/get_phone_data";
                console.log(id);
                $.ajax({
                    type: "POST",
                    dataType: 'json',
                    data: {
                        id: id
                    },
                    url: url,
                    success: function(result) {
                        console.log(result);
                        var data = result.data;
                        $('#phone').val(data.queue);
                        $('#nickname').val(data.nickname);
                        $("#phone_box_select").val(data.pid);
                        $('#only_phone_id').val(data.id);
                    },
                    error: function(data) {
                        alert("提交失败");
                    }
                })
            } else {
                $("#singleTel span").text("添加单个号码");
                $('#only_phone_id').val('');
            }
            $("#singlePhone").modal('show');
        },
        error: function(data) {
            alert("提交失败");
        }
    })
}

function save_phone() {
    var data = {};
    data.id = $('#only_phone_id').val();
    data.phone = $('#phone').val();
    if (data.phone) {
        if (!isPhoneNo(data.phone)) {
            alert('电话号码格式不正确')
            return false;
        }
    } else {
        alert('请输入电话号码')
        return false;
    }
    data.nickname = $('#nickname').val();
    data.pid = $('#phone_box_select').val();
    if (data.pid == 0) {
        alert('请选择分组');
        return false;
    }
    var href = "/user/plan/edit_add_singlePhone";
    $.ajax({
        type: "POST",
        dataType: 'json',
        url: href,
        data: data,
        success: function(result) {
            console.log(result);
            if (result === 0) {
                alert(result.msg);
            } else {
                alert(result.msg);
            }
            show_data_phone();
            show_data_box();
            $("#singlePhone").modal('hide');
        },
        error: function(data) {
            alert("提交失败");
        }
    })
}

//导入

function importNumber(id) {
    $("#importNumber").modal('show');
    $("#only_phone_pid").val(id);
}

function import_phoneFiles() {
    window.chaos_num = (new Date()).valueOf();
  	$("#importNumber").modal('hide');
  	$(".progress-bar-data").width(0 + '%');
  	$(".Progress_value").html('0.00' + '%');
  	$('.finish').addClass('hidden');
  	$('.import').removeClass('hidden');
  	$('#effectTmp').modal('show');
  	
    var pid = $('#only_phone_pid').val();
    var excel = document.getElementById('phone_excelId');
    if (excel.files[0] == undefined) {
        alert('未上传文件！');
        return false;
    }
    var filevalue = excel.value;
    var index = filevalue.lastIndexOf('.');
    var ename = filevalue.substring(index);
    if (ename != ".xlsx") {
        if (ename != ".xls") {
            alert('文件格式错误。"xlsx"或者"xls"，请用下载的模板改。');
            return false;
        }
    }
    var url = "/user/plan/import_phoneFiles";
    var formFile = new FormData();
    formFile.append("excel", excel.files[0]);
    formFile.append("pid", pid);
    formFile.append("chaos_num", window.chaos_num);
    window.import_dingshi = window.setInterval(effectTmp, 1000);
    window.tow_ok = false;
    $.ajax({
        type: 'post',
        data: formFile,
        dataType: 'json',
        url: url,
        cache: false,
        contentType: false, //不可缺
        processData: false, //不可缺
        success: function(data) {
            window.tow_ok = true;
            if (data.code == 0) {
					 	  //客户导入成功刷新 ajax_member(1)
    					$(".progress-bar-data").width(100.00 + '%');
    					$(".Progress_value").html('100.00' + '%');
    					if($('.Progress_value').html() == '100.00%'){
    						//延迟1秒在执行 加强体验度
    						setTimeout(function(){
    							$('.import').addClass('hidden');
    							$('.finish').removeClass('hidden');
    							var tmp = data.msg.split(',');
    							temp = '';
    							$.each(tmp,function(i,value){
    								temp += '<p>' + value + '</p>';
    							});
    							$('#effect-tips-content').html(temp);
    						},1000)
    						$('#upload_ok').click(function(){
    						  show_data_box(window.box_page,window.box_limit);
    						  show_data_phone(window.phone_page,window.phone_limit);
                })
    					}
            }
            window.clearInterval(window.import_dingshi);
        },
        error: function(e) {
            	$('.import').addClass('hidden');
							$('.finish').removeClass('hidden');
							var tmp = responseText.msg.split(',');
							temp = '';
							$.each(tmp,function(i,value){
								temp += '<p>' + value + '</p>';
							});
							$('#effect-tips-content').html('导入失败');
            window.clearInterval(window.import_dingshi);
        }
    })
}

var Paginga = new Paging01();
Paginga.init_args({
    // url:
    page: 1, //初始页码
    limit: 10, //初始每页显示的数据量
    paging_class: 'pagingA', //放置分页的class
    callback: show_data_box, //回调函数 比如show_datas(页码, 显示条数)
    key:'a'
});
show_data_box();

function show_data_box(page,limit) {
    if (!page) {
        page = 1;
    }
    if (!limit) {
        limit = 10;
    }
    window.box_page = page;
    window.box_limit = limit;
    var data = {};
    var url = "/user/plan/phone_manage"
    data.box_name = $('#groupName').val();
    data.startTime = $('#createTimeStart').val();
    data.endTime = $('#createTimeEnd').val();
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
                        var html = $('#data_phone_box').html();
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%sequence%}', i);
                        html = html.replace('{%name%}', object.box_name);
                        html = html.replace('{%count%}', object.count);
                        html = html.replace('{%date_time%}', object.establish_time);
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%remarks%}', object.remarks);
                        htmls += html;
                        i++;
                    });
                    $('#totalData').text(result.data.total);
                    //Nowpage  当前页
                    //count    数据总条数
                    //total    总共页数
                    //limit    分页数量
                    //Paging.paging(当前页码, 总数量, 每页显示的条数)
                    Paginga.paging(Nowpage, count, limit);
                }
                $('#number-list_box').html(htmls);
                election();
            }
        },
        error: function(error) {
            console.log(error);
            alert('数据获取失败！');
        }
    });
}

var Pagingb = new Paging01();
Pagingb.init_args({
    // url:
    page: 1, //初始页码
    limit: 10, //初始每页显示的数据量
    paging_class: 'pagingB', //放置分页的class
    callback: show_data_phone, //回调函数 比如show_datas(页码, 显示条数)
    key:'b'
});
show_data_phone();

function show_data_phone(page, limit) {
    if (!page) {
        page = 1;
    }
    if (!limit) {
        limit = 10;
    }
    window.phone_page = page;
    window.phone_limit = limit;
    var data = {};
    var url = "/user/plan/phone_manage"
    data.list_groupName = $('#list_groupName').val();
    data.list_phoneNumber = $('#list_phoneNumber').val();
    data.type = 2;
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
                    $('#consumeempty2').show();
                    window.count2 = 0;
                    $('.footerB').hide();
                } else {
                    $('#consumeempty2').hide();
                    $('.footerB').show();
                    var total = result.data.page; //总页数
                    var Nowpage = result.data.Nowpage; //当前页码
                    var count = result.data.total; //总条数
                    window.count2 = count;
                    var Nowpage = parseInt(Nowpage);
                    var i = (Nowpage - 1) * limit + 1;
                    $.each(result.data.list, function(index, object) {
                        data.i = i;
                        var html = $('#data_phone_value').html();
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%sequence%}', i);
                        html = html.replace('{%box_name%}', object.box_name);
                        html = html.replace('{%phone%}', object.queue);
                        html = html.replace('{%nickname%}', object.nickname);
                        html = html.replace('{%date_time%}', object.establish_time);
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%id%}', object.id);
                        htmls += html;
                        i++;
                    });
                    $('#totalData').text(result.data.total);
                    //Nowpage  当前页
                    //count    数据总条数
                    //total    总共页数
                    //limit    分页数量
                    //Paging.paging(当前页码, 总数量, 每页显示的条数)
                    Pagingb.paging(Nowpage, count, limit);
                }
                $('#number-list_phone').html(htmls);
                election2();
            }
        },
        error: function(error) {
            console.log(error);
            alert('数据获取失败！');
        }
    });
}

function del_phone_group(id) {
    console.log(id)
    var data = {};
    data.id = id;
    var url = "/user/plan/del_phone_box";
    $.ajax({
        type: 'POST',
        data: data,
        dataType: 'json',
        url: url,
        success: function(data) {
            console.log(data);
            if (data.code == 0) {
                alert(data.msg);
                $('#tips_model').modal('hide');
                show_data_box(window.box_page,window.box_limit);
            } else {
                alert(data.msg);
                $('#tips_model').modal('hide');
            }
        },
        error: function(e) {
            alert('数据提交失败！');
        }
    });
}

function del_phone_list(id) {
    console.log(id)
    var data = {};
    data.type = 0;
    var arr = new Array();
    var checkedsub = $("input[name='checkids2'][type='checkbox']:checked").length; //获取选中的checkids的个数
    if (checkedsub > 0 || id != '') {
        if (id) {
            data.id = id;
        } else {
            if ($('.all_checked2_count2').is(':checked')) {
                data.type = 1;
                data.list_groupName = $('#list_groupName').val();
                data.list_phoneNumber = $('#list_phoneNumber').val();
            }
            if ($("input[name='checkids2'][type='checkbox']").is(':checked')) {
                $("input[name='checkids2'][type='checkbox']").each(function(i) {
                    if ($(this).context.checked == true) {
                        arr[i] = $(this).val();
                    }
                });
                data.vals = arr.join();
            }
        }
        console.log(data);
        var url = "/user/plan/del_phone_data";
        $.ajax({
            type: 'POST',
            data: data,
            dataType: 'json',
            url: url,
            success: function(data) {
                console.log(data);
                if (data.code == 0) {
                    alert(data.msg);
                    $('#tips_model').modal('hide');
                    show_data_phone(window.phone_page,window.phone_limit);
                    show_data_box(window.box_page,window.box_limit);
                } else {
                    alert(data.msg);
                    $('#tips_model').modal('hide');
                }
            },
            error: function(e) {
                alert('数据提交失败！');
            }
        });
    } else {
        alert('请选择批量删除的数据');
    }
}
//号码导出
function export_phone_list() {
    var data = {};
    data.type = 0;
    var arr = new Array();
    if ($('.all_checked2_count2').is(':checked')) {
        data.type = 1;
        data.list_groupName = $('#list_groupName').val();
        data.list_phoneNumber = $('#list_phoneNumber').val();
    }
    if ($("input[name='checkids2'][type='checkbox']").is(':checked')) {
        $("input[name='checkids2'][type='checkbox']").each(function(i) {
            if ($(this).context.checked == true) {
                arr[i] = $(this).val();
            }
        });
        data.vals = arr.join();
    }
    console.log(data);
    $(".progress-bar-data").width(0 + '%');
  	$(".Progress_value").html('0.00' + '%');
  	$('.finish').addClass('hidden');
  	$('.import').removeClass('hidden');
  	$('#outexcel_degree').modal('show');
  	window.chaos_num_outeo = (new Date()).valueOf();
  	window.import_dingshio = window.setInterval(out_degree2, 1000);
  	window.one_ok = false;
  	data.chaos_num = window.chaos_num_outeo;
    var url = "/user/plan/phone_data_export";
    $.ajax({
        type: 'POST',
        data: data,
        dataType: 'json',
        url: url,
        success: function(data) {
        window.clearInterval(window.import_dingshio);
        window.one1_ok = true;
  			console.log(data);
  			if(data.code == 0){
    				$(".progress-bar-data").width(100.00 + '%');
    				$(".Progress_value").html('100.00' + '%');
    				if($('.Progress_value').html() == '100.00%'){
    					//延迟1秒在执行 加强体验度
    					setTimeout(function(){
    						$('.import').addClass('hidden');
    						$('.finish').removeClass('hidden');
    						$('#effect-tips-content_outexcel').html('导出成功');
    					},1000);
    					$('#upload_oks').unbind('click');
    					$('#upload_oks').click(function(){
    						  window.location.href = data.data;
    					})
    				}
    			}else{
    			  $('#upload_oks').unbind('click');
    				$('.import').addClass('hidden');
    				$('.finish').removeClass('hidden');
    				$('#effect-tips-content_outexcel').html('导出失败');
    			}
        },
        error: function(e) {
          alert('数据提交失败！');
          window.clearInterval(window.import_dingshio);
        }
    });
}
function export_phone_group(){
  var data = {};
  data.type = 0;
  var arr = new Array();
  if ($('.all_checked_count').is(':checked')) {
      data.type = 1;
  }
  if ($("input[name='checkids'][type='checkbox']").is(':checked')) {
      $("input[name='checkids'][type='checkbox']").each(function(i) {
          if ($(this).context.checked == true) {
              arr[i] = $(this).val();
          }
      });
      data.vals = arr.join();
  }
  console.log(data);
	$(".progress-bar-data").width(0 + '%');
	$(".Progress_value").html('0.00' + '%');
	$('.finish').addClass('hidden');
	$('.import').removeClass('hidden');
	$('#outexcel_degree').modal('show');
	window.chaos_num_oute = (new Date()).valueOf();
	window.import_dingshiz = window.setInterval(out_degree, 1000);
	window.one_ok = false;
	data.chaos_num = window.chaos_num_oute;
  var url = "/user/plan/phone_box_export";
  $.ajax({
      type: 'POST',
      data: data,
      dataType: 'json',
      url: url,
      success: function(data) {
        window.clearInterval(window.import_dingshiz);
        window.one_ok = true;
  			console.log(data);
  			if(data.code == 0){
  				$(".progress-bar-data").width(100.00 + '%');
  				$(".Progress_value").html('100.00' + '%');
  				if($('.Progress_value').html() == '100.00%'){
  					//延迟1秒在执行 加强体验度
  					setTimeout(function(){
  						$('.import').addClass('hidden');
  						$('.finish').removeClass('hidden');
  						$('#effect-tips-content_outexcel').html('导出成功');
  					},1000);
  					$('#upload_oks').unbind('click');
  					$('#upload_oks').click(function(){
  						  window.location.href = data.data;
  					})
  				}
  			}else{
  			  $('#upload_oks').unbind('click');
  				$('.import').addClass('hidden');
  				$('.finish').removeClass('hidden');
  				$('#effect-tips-content_outexcel').html('导出失败');
  			}
      },
      error: function(e) {
          alert('数据提交失败！');
          window.clearInterval(window.import_dingshiz);
      }
  });
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

function election2() {
    console.log('引用成功')
    console.log(window.count)
    if ($('.all_checked2_count2').is(":checked")) {
        $("input[name='checkids2'][type='checkbox']").prop("checked", true);
        $("input[name='all_checked2'][type='checkbox']").prop("checked", true);
        $('#check_count2').text(window.count);
        $('#user_count2').text(window.count);
    } else {
        $('#user_count2').text(0);
        $('#check_count2').text(0);
        $("input[name='all_checked2'][type='checkbox']").prop("checked", false);
    }
    $("input[name='all_checked2'][type='checkbox']").click(function() {
        if ($("input[name='all_checked2'][type='checkbox']").is(":checked")) {
            $("input[name='checkids2'][type='checkbox']").prop("checked", true);
            $(".all_checked2_count2").prop("checked", false);
        } else {
            $("input[name='checkids2'][type='checkbox']").prop("checked", false);
            $(".all_checked2_count2").prop("checked", false);
        }
        $('#user_count2').text($("input[name='checkids2'][type='checkbox']:checked").length);
        $('#check_count2').text($("input[name='checkids2'][type='checkbox']:checked").length);
    });
    //子复选框的事件
    $('input[type="checkbox"][name="checkids2"]').click(function() {
        //当没有选中某个子复选框时，check-all取消选中
        if (!$('input[type="checkbox"][name="checkids2"]').checked) {
            $("input[name='all_checked2'][type='checkbox']").prop("checked", false);
            $(".all_checked2_count2").prop("checked", false);
        }
        var chsub = $("input[name='checkids2'][type='checkbox']").length; //获取checkids2的个数
        var checkedsub = $("input[name='checkids2'][type='checkbox']:checked").length; //获取选中的checkids2的个数
        if (checkedsub == chsub) {
            $("input[name='all_checked2'][type='checkbox']").prop("checked", true);
            $(".all_checked2_count2").prop("checked", false);
        }
        $('#user_count2').text(checkedsub);
        $('#check_count2').text(checkedsub);
    });
    $('.all_checked2_count2').click(function() {
        if ($(this).prop('checked') === true) {
            $.each($('.all_checked2_count2'), function(index, obj) {
                $(obj).prop("checked", true);
            });
            $("input[name='checkids2'][type='checkbox']").prop("checked", true);
            $("input[name='all_checked2'][type='checkbox']").prop("checked", true);
            $('#check_count2').text(window.count2);
            $('#user_count2').text(window.count2);
        } else {
            $.each($('.all_checked2_count2'), function(index, obj) {
                $(obj).prop("checked", false);
            });
            $("input[name='checkids2'][type='checkbox']").prop("checked", false);
            $("input[name='all_checked2'][type='checkbox']").prop("checked", false);
            $('#check_count2').text(0);
            $('#user_count2').text(0);
        }
    });
}

function effectTmp(){
	var url = "/user/member/effectTmp2"
	$.ajax({
		type: 'POST',
		dataType: "json",
		data: {
			chaos_num:window.chaos_num
		},
		url: url,
		success: function(result) {
		  if(window.tow_ok != true){
		    $(".progress-bar-data").width((result.data.baifenbi) + '%');
			  $(".Progress_value").html((result.data.baifenbi.toFixed(2)) + '%');
		  }
		},
		error: function() {
		}
	});
}

function out_degree(){
	$.post("/user/plan/outexcel_degree", {
		'chaos_num': window.chaos_num_oute
	}, function(data) {
		if(data.code == 1){
		  if(window.one_ok != true){
  			$(".progress-bar-data").width((data.data.percentage) + '%');
  			$(".Progress_value").html((data.data.percentage)+ '%');
		  }
		}
	});
}

function out_degree2(){
	$.post("/user/plan/outexcel_degree", {
		'chaos_num': window.chaos_num_outeo
	}, function(data) {
		if(data.code == 1){
		  if(window.one1_ok != true){
  			$(".progress-bar-data").width((data.data.percentage) + '%');
  			$(".Progress_value").html((data.data.percentage)+ '%');
		  }
		}
	});
}


function send_out(id){
  $('#box_name').data('id',id);
  $('#send_out').modal('show');
  $.post("/user/plan/get_sendoutphone", {
		'id': id
	}, function(data) {
	  console.log(data);
		if(data.code == 1){
		  $('#box_name').html(data.data.box_name);
		  var option = '<option value="">请选择下发用户类型</option>';
		  $.each(data.data.role_name,function(index,value){
		    option += '<option value="'+value.role_id+'">'+value.role_name+'</option>'
		  });
		  $('#role_name').html(option);
		  var option2 = '<option value="">请选择下发用户</option>';
		  // $.each(data.data.role_list,function(index,value){
		  //   option2 += '<option value="'+value.id+'">'+value.username+'</option>'
		  // });
		  $('#username').html(option2);
		}
	});
}
function get_username(id){
  $.post("/user/plan/get_sendoutphone", {
		'role_id': id
	}, function(data) {
	  console.log(data);
	  if(data.code == 1){
	    var option2 = '<option value="">请选择下发用户</option>';
		  $.each(data.data.role_list,function(index,value){
		    option2 += '<option value="'+value.id+'">'+value.username+'</option>'
		  });
		  $('#username').html(option2);
	  }
	});
}
function sendout_phone(){
  var data = {};
  data.box_name = $('#box_name').data('id');
  data.role_name = $('#role_name').val();
  data.username = $('#username').val();
  data.sendout_remark = $('#sendout_remark').val();
  if(!data.username){
    alert('请选择下发用户');
    return false;
  }
	var url = "/user/plan/give_subordinate"
	$.ajax({
		type: 'POST',
		dataType: "json",
		data: data,
		url: url,
		success: function(result) {
		  console.log(result);
		  if(result.code == 1){
		    alert(result.msg);
		    $('#send_out').modal('hide');
		  }
		},
		error: function() {
		}
	});
}
