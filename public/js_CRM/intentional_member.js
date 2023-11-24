function isPhoneNo(phone) {
    var pattern = /^(\d{7,})$/;
    return pattern.test(phone);
}
//客户详情弹框显示

function constomerDetail(id) {
    $("#constomerdetail").modal('show');
    $.ajax({
        type: 'POST',
        url: '/user/Member/get_constomerDetail',
        data: {id:id},
        success: function(data) {
            var info = data.data;
            $('#e_name').val(info.name);
            if(info.sex == '男'){
                info.sex = 0;
            }else if(info.sex == '女'){
                info.sex = 1;
            } if(info.sex == '未知'){
                info.sex = 2;
            }
            $('#e_tradeType').val(info.sex);
            $('#e_phone').val(info.phone);
            $('#e_company').val(info.compay_name);
            $('#e_only_id').val(info.id);
        }
    })
}
function edit_constomerDetail(){
    var data = {};
    data.id =  $('#e_only_id').val();
    data.name = $('#e_name').val();
    data.sex = $('#e_tradeType option:selected').text();
    data.phone = $('#e_phone').val();
    data.compay_name = $('#e_company').val();
    if(!data.name){
        alert('客户名称不能为空')
       return false;
    }
    if(data.sex == '请选择性别'){
       alert('客户性别不能为空')
       return false;
    }
    if(!data.phone){
       alert('联系电话不能为空')
       return false;
    }else{
       if(!isPhoneNo(data.phone)){
          alert('电话号码格式不正确')
          return false;
       }
    }
    var url = '/user/member/add_intentional_member';
    $.ajax({
        type: 'POST',
        url: url,
        data: data,
        success: function(data) {
            alert(data.msg);
            $('#constomerdetail').modal('hide');
            ajax_member();
        }
    })
}

//添加客户
function constomerAdd() {
    //添加之前 全部清空
    $('#customer-name').val('');
    $("#sex option:first").prop("selected", 'selected');
    $('#phone').val('');
    $('#corporate-name').val('');
    $("#crm_cate option:first").prop("selected", 'selected');
    $('#blacklist-remark1').val('');
    $('#customer-add').modal('show');
}
//确定按钮提交 先验证 后显示弹窗提示

function checkform() {
    var name = $("#customer-name").val(); //客户名称
    var phone = $("#phone").val(); //电话
    if (!name) {
        alert("客户名称不能为空");
        return false;
    } else if (!phone) {
        alert("电话不能为空");
        return false;
    } else if (!isPhoneNo(phone)) {
        alert("电话不正确");
        return false;
    } else {
        $('#l-delnotex').modal('show');
    }
}
//新增意向客户

function add_intentional_member() {
    $('#customer-add').modal('hide');
    $('#l-delnotex').modal('hide');
    var url = '/user/Member/add_intentional_member';
    var name = $("#customer-name").val(); //客户姓名
    var sex = $("#sex").val(); //客户性别
    var phone = $("#phone").val(); //客户性别
    var compay_name = $("#corporate-name").val(); //公司名称   crm_cate
    var crm_cate = $("#crm_cate").val(); //客户分类
    var note = $("#blacklist-remark1").val(); //备注
    $.post(url, {
        'name': name,
        'sex': sex,
        'phone': phone,
        'compay_name': compay_name,
        'crm_cate': crm_cate,
        'note': note,
    }, function(data) {
        //0是失败
        if (data.code == 0) {
            alert(data.msg);
        } else {
            ajax_member(1);
            alert(data.msg);
        }
    });
}
//导入

function constomerImport() {
    $("#import-data").modal('show');
}
//导入保存页面

function Savechange(){
  var excel = document.getElementById('excelId');
  if(excel.files[0] == undefined){
    alert('未上传文件！');
    return false;
  }
  var filevalue = excel.value;
  var index = filevalue.lastIndexOf('.');
  var ename = filevalue.substring(index);
  if(ename != ".xlsx"){
    if(ename != ".xls"){
      alert('文件格式错误。"xlsx"或者"xls"，请用下载的模板改。');
      return false;
    }
  }
  window.chaos_num = (new Date()).valueOf();
  $('#chaos_num').val(window.chaos_num);
  $("#fileform").submit();
  $("#import-data").modal('hide');
  $(".progress-bar-data").width(0 + '%');
  $(".Progress_value").html('0.00' + '%');
  $('.finish').addClass('hidden');
  $('.import').removeClass('hidden');
  $('#effectTmp').modal('show');
}

//导出
//导出数据

function validation10() {
  $("#export-data").modal('show');
  $('#sel_excel').on('click', function() {
    $('#sel_excel').unbind('click');
    var typez = 10;
    var arr = [];
    $("input[name='checkids'][type='checkbox']:checked").each(function(i) {
        arr[i] = $(this).val();
    });
    if (arr.length <= 0) {
      alert('请选择导出的数据');
      return false;
    } else {
      $('#export-data').modal('hide');
      $('#show_validation1').html(html);
      $('#export_val').attr('onclick','export_val('+typez+')')
      $('#export-validation').modal('show');
      msm_send();
    }
  })
}
function constomerExport(){
   var data  = {};
   data.type = 0;
  if ($("input[name='DataCheck_all'][type='checkbox']").is(':checked')) {
    data.type = 1;
  }
  var xrr = [];
  $("input[name='checkids'][type='checkbox']:checked").each(function(i) {
      xrr[i] = $(this).val();
  });
  data.xrr=xrr

  var arr=[];  //A-6 B-5 C-4
    if( $("input[name='grade_all']").is(':checked') ){
      data.arr=arr //如果全选的话 arr是空
    }else{
      //否则不是全选
      $("input[name='grade']:checked").each(function(index, element){
          arr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.arr=arr;
    }
    var brr=[]; //已查看-1 未查看-0
    //通话查看 全选的话
    if ($("input[name='call_all']").is(':checked')){
        data.brr= brr//如果全选的话 brr是空
    }else{
       //否则不是全选
      $("input[name='call']:checked").each(function(index, element){
          brr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.brr=brr;
    }
    //客户分配 全选的话
     var crr=[];//已分配-1 未分配-0
    if( $("input[name='distribution_all']").is(':checked') ){
       data.crr=crr//如果全选的话 crr是空
    }else{
        //否则不是全选

      $("input[name='distribution']:checked").each(function(index, element){
          crr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.crr=crr;
    }
    //客户意愿 全选的话
     var drr=[];  //客户分类  0：未分类  1：意向客户   2：沟通中   3：试用中   4：已成交
    if( $("input[name='desire_all']").is(':checked') ){
         data.drr=drr //如果全选的话 drr是空
    }else{
      //否则不是全选

      $("input[name='desire']:checked").each(function(index, element){
          drr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.drr=drr;
    }

  window.chaos_num_out = (new Date()).valueOf();
  window.import_dingshiz = window.setInterval(out_effectTmp, 1000);
  $("#export-data").modal('hide');
  $(".progress-bar-data").width(0 + '%');
  $(".Progress_value").html('0.00' + '%');
  $('.finish').addClass('hidden');
  $('.import').removeClass('hidden');
  $('#out_effectTmp').modal('show');

  //电话
    data.phone = $('#phone_query').val();
    data.name = $('#name_query').val();
    data.sitchair = $("#sitchair").val(); //坐席
    data.startCreateDate = $('#startCreateDate').val();
    data.endCreateDate = $('#endCreateDate').val();
    //拨打次数
    data.min_call_count = $('#min_call_count').val();
    data.max_call_count = $('#max_call_count').val();
    data.chaos_num = window.chaos_num_out

  $.ajax({
    type: 'POST',
    url: '/user/Member/import_data',
    data: data,
    success: function(data) {
      window.clearInterval(window.import_dingshiz);
      if(data.code == 0){
        $("#out_effectTmp .progress-bar-data").width(100.00 + '%');
        $("#out_effectTmp .Progress_value").html('100.00' + '%');
        if($('#out_effectTmp .Progress_value').html() == '100.00%'){
          //延迟1秒在执行 加强体验度
          setTimeout(function(){
            $('#out_effectTmp .import').addClass('hidden');
            $('#out_effectTmp .finish').removeClass('hidden');
            $('#effect-tips-content_outexcel').html('导出成功');
          },1000)
          $('#upload_oks').click(function(){
            if(data.data){
                window.location.href = data.data;
            }
          })
        }
      }
    }
  })
}

//分配坐席

function allocationOfSeats() {
    $('#distribution_seat_id').val('');
    $("#allocationOfSeats").modal('show');
}

function allocation_seats() {
    $('.allocation_seats').unbind('click');
    $('.allocation_seats').click(function() {
        var type = $(this).data('type');
        var crm_id = $(this).data('id');
        $('#crm_id').val(crm_id);
        $('#distribution_type').val(type);

        $('#allocationOfSeats').modal('show');
    });
}
/**
 * 分配意向客户
 *
 * @param int type 分配类型 0 单选 1 多选
 * @param int id 指定ID
 */
function distribution_crm() {
    //获取当前指定分配的形式
    var url = "distribution_crm_api";


    // var distribution_seat_id = $('#distribution_seat_id').val();
    var distribution_seat_ids = $('#distribution_seat_id').find('input[type=checkbox]:checked').map(function(){return $(this).val();}).get();;

    //指定的座席
    // var seat_id = $('')
    var data = {};
   //判断是否全选 全选
    if ($('.all_checked_count').prop('checked') === false) {
        data.type = 1;
        data.ids = [];
        $.each($('input[name="checkids"]:checked'), function(index, object) {
            data.ids.push($(object).val());
        });
        //多选
    } else {
        data.type = 2;
         // 意向等级 全选的话
        var arr=[];  //A-6 B-5 C-4
        if( $("input[name='grade_all']").is(':checked') ){
          data.arr=arr //如果全选的话 arr是空
        }else{
          //否则不是全选
          $("input[name='grade']:checked").each(function(index, element){
              arr[index] = $(element).val(); //意向等级 搜索条件的获取
          });
          data.arr=arr;
        }
        var brr=[]; //已查看-1 未查看-0
        //通话查看 全选的话
        if ($("input[name='call_all']").is(':checked')){
            data.brr= brr//如果全选的话 brr是空
        }else{
           //否则不是全选
          $("input[name='call']:checked").each(function(index, element){
              brr[index] = $(element).val(); //意向等级 搜索条件的获取
          });
          data.brr=brr;
        }
        //客户分配 全选的话
         var crr=[];//已分配-1 未分配-0
        if( $("input[name='distribution_all']").is(':checked') ){
           data.crr=crr//如果全选的话 crr是空
        }else{
            //否则不是全选
    
          $("input[name='distribution']:checked").each(function(index, element){
              crr[index] = $(element).val(); //意向等级 搜索条件的获取
          });
          data.crr=crr;
        }
        //客户意愿 全选的话
         var drr=[];  //客户分类  0：未分类  1：意向客户   2：沟通中   3：试用中   4：已成交
        if( $("input[name='desire_all']").is(':checked') ){
             data.drr=drr //如果全选的话 drr是空
        }else{
          //否则不是全选
          $("input[name='desire']:checked").each(function(index, element){
              drr[index] = $(element).val(); //意向等级 搜索条件的获取
          });
          data.drr=drr;
        }
    	data.name = $('#name_query').val();
        data.sitchair = $("#sitchair").val(); //坐席
        data.config_id = $("#config_id").val(); //任务
        data.startCreateDate = $('#startCreateDate').val();
        data.endCreateDate = $('#endCreateDate').val();
    }
    data.distribution_seat_ids = distribution_seat_ids;

    if(!data.distribution_seat_ids){
       alert('请选择坐席');
       return false;
    }
    if(data.type == 1){
        if(!data.ids.length){
            alert('请选择要分配的客户');
            return false;
        }
    }
    $.ajax({
        type: 'POST',
        dataType: 'json',
        url: url,
        data: data,
        success: function(result) {
            if (result.code != 0) {
                alert('提交失败');
            } else {
                alert('成功');
                var page = $('#Nowpagehidden').val();
                var limit = $('#Nowlimithidden').val();
                ajax_member(page, limit);
            }
            $('#allocationOfSeats').modal('hide');
        },
        error: function() {
            alert('提交失败');
        }
    });
}
//通话记录
function callDetails(id,phone,object) {
    $(object).attr('style','color:#5b5d5f');
    $('#callDetails_id').val(id);
    $('#callDetails_phone').val(phone);
    ajax_call_list(1);
    $('#callrecord').modal('show');
}

function ajax_call_list(page){
    var data = {};
    data.crm_id = $('#callDetails_id').val();
    data.id_phone = $('#callDetails_phone').val();
    if(!page){
       page = 1;
    }
    data.page = page;
    $.ajax({
        type: 'POST',
        url: '/user/Member/ajax_call_list',
        data: data,
        success: function (data) {
            $('#tablepagelist').html('');
            if(data.code == 1){
                if(data.data.list.length == 0){
                    $('#consumeempty1').show();
                }else{
                    $('#consumeempty1').hide();
                    var total = data.data.total; //总条数
                    var Nowpage = data.data.Nowpage; //当前页码
                    var page = data.data.page; //总页数
                    window.totalPage = page; //总页数给全局
                    var dataList = data.data.list; //分页后的数据
                    var limit = data.data.limit; //每页的分页条数
                    var Sring = '';
                    var len = dataList.length;
                    //循环数据
                    for (var i =0; i < len; i++) {
                        var rs = dataList[i];
                        //<td class="text-center"><input type="checkbox" class="member_check rolecheck" value="' +
                            // rs.id + '" name="checkids" /> </td>
                        Sring += ' <tr>'
                            + '<td class="text-center">' + ((Nowpage-1)*limit+(i+1)) + '</td>'
                            +'<td class="text-center">' + rs.mobile_j + '</td>'
                            +'<td class="text-center">' + getStatusName(rs.status) + '</td>'
                            +'<td class="text-center">' + rs.level +'</td>'
                            +'<td class="text-center">' + rs.duration + '</td>'
                            +'<td class="text-center">' + rs.last_dial_time+ '</td>'
                            +'<td class="text-center">'
                            +'<a href="javascript:;" class="is_see_call'+rs.is_see_call+'"  onclick="gotoDetail('+rs.mobile+','+rs.task_id+','+rs.id+',\''+rs.source+'\','+rs.old_last_dial_time+',this)">通话详情</a>&nbsp;'
                            +'</td>'
                            +'</tr>';
                    }
                    //渲染数据
                    $('#tablepagelist').html(Sring);
                    //Nowpage  当前页
                    //total    数据总条数
                    //page    总共页数
                    //limit    分页数量
                    paging(Nowpage, total, page, limit);
                }
            }
        },
        error: function (e) {}
    });
}
//去掉关键词括号和括号里面的内容
function dele_brackets(str){
    var reg=/\(.*?\)/g;
    str=str.replace(reg,"");
    return str;
}
function gotoDetail(mobile, taskId, id, froms,time,object) {
      $(object).attr('style','color:#5b5d5f')
      if (froms == '') {
        froms = 'record';
      }

      var see_button = $('.show_record[data-id="' + id + '"]').eq(0);
      var data = {
          'mobile': mobile,
          'taskId': taskId,
          'recordId': id,
          'froms': 'record',
          'type':froms,
          'select_time':time
        };
      $.post("/user/member/backdetail", data, function(data) {
        if (data) {
          if (data.code == 0) {
            var memberInfo = data.data.memberInfo;
            //通话记录
            var bills = data.data.bills;
            if(memberInfo.length == 0 && bills.length == 0){
            //  console.log("暂无数据");
            }
            $('#taskname').text(memberInfo.task_name);  //任务名称
            $('#speechname').text(memberInfo.speechname);//话术名称

            $('#customer_talk').text(memberInfo.call_times);//客户说话次数

            $('#trigger_pro').text(memberInfo.hit_times); //触发问题
            $("#duration").text(memberInfo.duration + '秒');   //通话时长
            $('#effective_talk').text(memberInfo.effective_times);//有效对话
            $('#sure_talk').text(memberInfo.affirm_times);//肯定次数
            $('#neutral_talk').text(memberInfo.neutral_times); //中性次数
            $('#negative_talk').text(memberInfo.negative_times); //否定次数
            $('#c_invitation').text(memberInfo.successyaoyue);//是否邀约成功
            $("#last_dial_time").text(memberInfo.last_dial_time); //最后拨打时间

            $('#semantic_tag').text(memberInfo.semantic_label);//语义标签
            $('#process_tag').text(memberInfo.flow_label);//流程标签
            $('#answer_tag').text(memberInfo.knowledge_label);//问答标签

            $("#nickname").text(memberInfo.nickname);
            $("#sex").text(memberInfo.sex);
            $("#mobile").text(memberInfo.mobile);

            $("#call_time").text(memberInfo.call_times + '轮');
            $("#originatingCall").text(memberInfo.originating_call);
            $("#keyNum").text(data.data.num);//命中关键字
            $("#keyNum").parent().attr("title",data.data.num);//命中关键字
            var strstatus = "未拨打";
            switch (memberInfo.status) {
              case 2:
                strstatus = "已接通";
                break;
              case 3:
                strstatus = "无人接听";
                break;
              case 4:
                strstatus = "停机";
                break;
              case 5:
                strstatus = "空号";
                break;
              case 6:
                strstatus = "正在通话中";
                break;
              case 7:
                strstatus = "关机";
                break;
              case 8:
                strstatus = "用户拒接";
                break;
              case 9:
                strstatus = "网络忙";
                break;
              case 10:
                strstatus = "来电提醒";
                break;
              case 11:
                strstatus = "呼叫转移失败";
                break;
              default:
                strstatus = "--";
                break;
            }
            $("#statusinfo").text(strstatus); //拨打状态
            $(".greenactive").removeClass("greenactive");

            if (memberInfo.level == 1) {
              $("#level1").addClass("greenactive");
            } else if (memberInfo.level == 2) {
              $("#level2").addClass("greenactive");
            } else if (memberInfo.level == 3) {
              $("#level3").addClass("greenactive");
            } else if (memberInfo.level == 4) {
              $("#level4").addClass("greenactive");
            } else if (memberInfo.level == 5) {
              $("#level5").addClass("greenactive");
            }else if (memberInfo.level == 6) {
              $("#level6").addClass("greenactive");
            }

            $("#record_path").attr('src', memberInfo.record_path);
            if(memberInfo.record_path != ''){
              $('#download_record_path').attr('href', memberInfo.record_path);
            }
            $("#msglist").empty();

            for (var i = 0; i < bills.length; i++) {

              var tempbills = bills[i];

              if (tempbills.role == 0) {
                var tempstr = '<div class="jimi_lists clearfix">' +
                  '<div class="header_img  icon iconfont icon-zuoxi1"></div>' +
                  '<table class="msg" cellspacing="0" cellpadding="0">' +
                  '<tbody>' +
                  '<tr>' +
                  '<td></td>' +
                  '<td></td>' +
                  '</tr>' +
                  '<tr>' +
                  '<td class="lt"></td>' +
                  '<td class="tt"></td>' +
                  '<td class="rt"></td>' +
                  '</tr>' +
                  '<tr>' +
                  '<td class="lm"><span></span></td>' +
                  '<td class="mm">' +
                  '<span class="wel"><span class="visitor"><p>' +
                  '' + tempbills.message + '</p></span></span>' +
                  '</td>' +
                  '<td class="rm">' +
                  '</td>' +
                  '</tr>' +
                  '<tr>' +
                  '<td class="lb"></td>' +
                  '<td class="bm"></td>' +
                  '<td class="rb"></td>' +
                  '</tr>' +
                  '<tr><td></td></tr>' +
                  '</tbody>' +
                  '</table>' +
                  '</div>';

              } else {
                var tempstr = '<div class="customer_lists clearfix">' +
                  '<div class="header_img jimi3 icon iconfont icon-gerenkehuguanli">' +
                  '</div>' +
                  '<table class="msg" cellspacing="0" cellpadding="0">' +
                  '<tbody>' +
                  '<tr>' +
                  '<td></td>' +
                  '<td></td>' +
                  '</tr>' +
                  '<tr>' +
                  '<td class="lt"></td>' +
                  '<td class="tt"></td>' +
                  '<td class="rt"></td>' +
                  '</tr>'
                  +
                  '<tr>' +
                  '<td class="lm"></td>' +
                  '<td class="mm">' + tempbills.message + '</td>' +
                  '<td class="rm"><span></span></td>' +
                  '</tr>' +
                  '<tr>' +
                  '<td class="lb"></td>' +
                  '<td class="bm"></td>' +
                  '<td class="rb"></td>' +
                  '</tr>'
                  +
                  '</tbody>' +
                  '</table>' +
                  '</div>';

                if ((tempbills.status == 1) && tempbills.hit_keyword) {
                  tempstr += '<div class="customer_lists clearfix">' +
                    '<div class="session-item-left">' +
                    '<div class="ant-popover-placement ant-popover-placement-left">' +
                    '<div class="popover-content">【' + dele_brackets(tempbills.hit_keyword) + '】<br></div>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
                }
                tempstr += '<div class="session-item-left">'
                        +'<div class="ant-popover-placement ant-popover-placement-left">'
                        +'<div class="popover-content audio-content"><audio src="'+tempbills.path+'" class="palay-audio" preload="preload" controls="controls"></audio>'
                        +'<br></div></div></div>';

              }
              $("#msglist").append(tempstr);
            }

            $('#call-detail').modal('show');

            update_level_click();

          } else {
            alert(data.msg);
          }

        } else {
        }
      });

      $("#thisId").val(id);
    }

function update_level_click(){
    $('.item').unbind('click');
     $('.item').click(function() {
      if(!confirm("确定修改意向等级?")){
    　    　//点击确定后操作
    　　      return false;
      }
      var level = $(this).attr('data-v');
      var uid = $("#thisId").val();
      $.post("/user/member/changelevel/id/" + uid, {
        'level': level
      }, function(res) {
         if (res.code == 0) {

        }
         alert(res.msg);
      });
      $(".greenactive").removeClass("greenactive");
       $(this).addClass("greenactive");
    });
}



function paging(page, count, show_count, limit) {
    page = parseInt(page);

    limit = parseInt(limit);
    var page_count = Math.ceil(count / limit);
    // debugger;
    var html = '';
    //偶数
    if (show_count % 2) {
        // cha =
        //奇数
    } else {

    }
    window.limit = limit;
    var start = page - 2;
    var end = page + 2;
    if (start < 1) {
        end = end - start;
        start = 1;

    }
    if (end > page_count) {
        end = page_count;
    }
    html += '<div>';
    html += '<ul class="pagination">';
    html += '<div style="font-size: 12px;margin: 0px;margin-left: 10px; display: inline-block;">跳至';
    html +=
        '<input class="Nowpage" type="number" style="width: 50px;height:32px; margin: 1px 8px;border:1px solid #ddd;border-radius: 5px;text-align: center;" value="' +
        page + '" max="' + page_count + '" min="1">页</div>';
    html += '<button class="btn btn-primary go_up" type="button" data-toggle="modal">确定</button>';
    if (page === 1) {
        html += '<li id="prevbtn" class="disabled"><span>«</span>';
    } else {
        html += '<li id="prevbtn" class=""><a href="javascript:void(0);" onclick="ajax_call_list(' + (page - 1) +
            ');"><span>«</span></a>';
    }
    html += '</li>';
    for (start; start <= end; start++) {
        if (start == page) {
            html += '<li class="active"><a href="javascript:void(0);" onclick="ajax_call_list(' + start + ')">' + start +
                ' </a>';
            html += '</li>';
        } else {
            html += '<li class=""><a href="javascript:void(0);" onclick="ajax_call_list(' + start + ')">' + start +
                ' </a>';
            html += '</li>';
        }

    }
    if (page == page_count) {
        html += '<li id="prevbtn" class="disabled"><span>»</span>';
    } else {
        html += '<li ><a href="javascript:void(0);" onclick="ajax_call_list(' + (page + 1) + ');"><span>»</span></a>';
    }

    html += '</li>';
    html += '</ul>';
    html += '<div style="font-size: 12px;float: right;margin: 14px 9px 0px 0px;display: inline-block;">';
    html += '<span style="font-size: 12px;">总页数：<span id="all_page">' + show_count + '页</span></span>';
    html += '</div>';
    html += '</div>';

    $('.paginga').html(html);
    $('.go_up').click(function () {
        var index = $(this).index();
        var limit = $(this).siblings('div').find('.limit').val();
        var page = $(this).siblings('div').find('.Nowpage').val();
        index = index;
        go_up(page, limit);
    })
}
function go_up(page, limit) {
    paging(page, window.total, window.totalPage, limit);
    ajax_call_list(page);
}
function limitSet(obj) {
    //页码类型换了 之后 自动跳转第一页  因为预防页码过多  比如一共300条数据 10条每页的时候翻页到30页 我弄30条每页 如果还处于30页 会出现bug 跳转第一页 可以避免这样的情况
    paging(1, window.total, window.totalPage, $(obj).val());
    ajax_call_list(1);
}
function getStatusName(status) {
    var statusName = '';
    switch (status) {
    case 0:
        statusName = "未分配";
        break;
    case 1:
        statusName = "已分配";
        break;
    case 2:
        statusName = "已接通";
        break;
    case 3:
        statusName = "无人接听";
        break;
    case 4:
        statusName = "停机";
        break;
    case 5:
        statusName = "空号";
        break;
    case 6:
        statusName = "正在通话中";
        break;
    case 7:
        statusName = "关机";
        break;
    case 8:
        statusName = "用户拒接";
        break;
    case 9:
        statusName = "网络忙";
        break;
    case 10:
        statusName = "来电提醒";
        break;
    case 11:
        statusName = "呼叫转移失败";
        break;
    }
    return statusName;
}

//新增记录


function followUpRecord(id) {
    $("#followUpRecord").modal('show');
    //var
    var $id = id;
    $('#only_record_id').val($id);
    //渲染数据集合dom
    var list = $("#recordshow");
    list.html('');
    //加载更多dom
    var loadingBtn = $("#loading");
    //是否需要加载
    var isLoad = true;
    //当前查询第几页
    var currentPage = 1;
    var listcunt = 0;
    //没有更多数据
    var nomore_Text = '<div class="recordshow" style="border-left: 1px solid #e0e0e0;min-height: calc(15vh);"><i class="circle"></i><p class="recordinfo">没有更多数据</p></div>';

    loadData();
    function loadData() {
        var url = '/user/Member/ajax_uplist';
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                'currentPage': currentPage,
                'id': $id
            },
            success: sucessCallback,
            error: function (e) {}
        });
    }
    function sucessCallback(data) {
        var pageCount = data.data.pageCount; //分页总数
        var pageNo = data.data.pageNo; //分页个数
        var data = data.data.list;

        if(data.length <= 0){
          loadingBtn.html(nomore_Text);
        } else {
        //   loadingBtn.html('');
          //
          loadingBtn.html('');
          var html = '',
              result = data,
              len = result.length,
              i = 0;
          //循环数据
          for (; i < len; i++) {
            var rs = result[i];
            var time = rs.create_time; //客户跟进的时间
            var id = rs.id; //客户跟进的id  crm表中的id
            var note = rs.note; //客户跟进的内容
            var crm_cate = rs.crm_cate; //客户意愿

            html += '<div class="recordshow"> '
                +'<i class="circle"></i>'
                +'<p id="status" class="lstatus"> <span>客户意愿：</span>'
                +'<span>' + crm_cate +'</span>'
                +'<span>'+timestampToTime(time)+'</span>'
                +'</p>'
                +'<p class="recordinfo">' + note + '</p>'
                +'</div>'
            listcunt = listcunt + 1
          }
          //渲染数据
          if(currentPage == 1){
              list.html(html);
          }else{
              list.append(html);
              loadingBtn.html(nomore_Text);
          }

          //接口是否查询完毕
            if(currentPage < pageCount || listcunt < data.count){
              isLoad = true;
            }else if(currentPage >= pageCount || listcunt == data.count){
              isLoad = false;
            }
          //当前页自增
          currentPage++;
        }
    }

    /*
    滚动事件监听
     */
    $(document).ready(function() {
        $('#recordtimeshow').scroll(function () {
            //是否滚动到底部
            var _needload = isScrollLoad(this);
            //
            if (_needload && isLoad) {
                //加载数据
                loadData();
            }
        });
    })
    $(document).ready(function() {
        $('#set_note_click').unbind('click');
        $('#set_note_click').click(function(){
            var data = {};
            data.content = $('#record_content').val();
            if (!data.content) {
                alert('请填写跟进记录');
                return false;
            }
            data.id = $('#only_record_id').val();
            data.crm_cate = $('#record_resire').val();
            $.ajax({
                type: 'POST',
                url: '/user/Member/followup_add',
                data: data,
                success: function (data) {
                    //1成功 0失败  成功后 清空
                    if (data.code == 1) {
                        alert(data.msg);
                        currentPage = 1;
                        listcunt = 0;
                        loadData();
                        $('#addRecord').modal('hide');
                        ajax_member();
                    } else {
                        alert(data.msg);
                    }
                }
            })
        })
    })

}
/*
判断是否要加载接口
*/
function isScrollLoad(obj){
    var divHeight = $(obj).height();
    var nScrollHeight = $(obj)[0].scrollHeight;
    var nScrollTop = $(obj)[0].scrollTop;
    if(nScrollTop + divHeight >= nScrollHeight) {
      return true;
    }else{
      return false;
    }
}
//新增订单

function transactionOrder(id) {
    window.noly_id = id;
    $("#addOrder").modal('show');
    $('#only_crm_order_id').val(id);
    var currentPage = 1;
    $('#list_order').html('');
    var listcunt = 0;
    var isLoad = true;

    ajax_transaction_order();
    function ajax_transaction_order(){
        var data = {};
        data.crm_id = $('#only_crm_order_id').val();
        data.page = currentPage;
        var url = '/user/Member/ajax_transaction_order';
        $.ajax({
            type: 'POST',
            url: url,
            data: data,
            success: function(data) {
               if(data.code == 1){
                   if(data.data.list.length == 0){
                    //   list_order
                   }else{
                       var pageCount = data.data.pageCount; //分页总数
                       var pageNo = data.data.pageNo; //分页个数
                       var html = ''
                       $.each(data.data.list,function(i,obj){
                            if(i == 0){
                                html += '<div class="order marginT20 orderActive" data-id = "'+obj.id+'" onclick="orderSel(this);">'
                                +'<div class="control-icon">  '
                                + '<img src="/public/img/crmdel.png" data-id = "'+obj.id+'" onclick="delOrder(this);"></div>'
                                +'<p>'+obj.order_name+'</p>'
                                +'<p>'+timestampToTime(obj.create_time)+'</p> <i></i>'
                                +'</div>'
                            }else{
                                html += '<div class="order marginT20 " data-id = "'+obj.id+'" onclick="orderSel(this);">'
                                +'<div class="control-icon">  '
                                + '<img src="/public/img/crmdel.png" data-id = "'+obj.id+'" onclick="delOrder(this);"></div>'
                                +'<p>'+obj.order_name+'</p>'
                                +'<p>'+timestampToTime(obj.create_time)+'</p> <i></i>'
                                +'</div>'
                            }
                            listcunt = listcunt + 1
                       })
                       if(currentPage == 1){
                           $('#list_order').html(html);
                       }else{
                           $('#list_order').append(html);
                       }

                        if(currentPage < pageCount || listcunt < data.count){
                          isLoad = true;
                        }else if(currentPage >= pageCount || listcunt == data.count){
                          isLoad = false;
                        }
                        currentPage ++;


                        var order_id = $('.orderActive').data('id');
                        var url = '/user/Member/get_transaction_order';
                        $.ajax({
                          type: 'POST',
                          url: url,
                          data: {id:order_id},
                          success: function(data) {
                              var info = data.data;
                              $('#orderName').val(info.order_name);
                              $('#transaction_date').val(timestampToTime(info.transaction_date));
                              $('#product_name').val(info.product_name);
                              $('#number').val(info.number);
                              $('#money').val(info.money);
                              $('#salesman').val(info.salesman);
                              $('#remarks').val(info.remarks);
                              $('#only_transaction_order_id').val(info.id);
                              $('#only_crm_order_id').val(info.crm_id);
                          }
                      })
                   }
               }
            }
        })
    }
    /*
    滚动事件监听
     */
    $(document).ready(function() {
        $('#list_order').scroll(function () {
            //是否滚动到底部
            var _needload = isScrollLoad(this);
            //
            if (_needload && isLoad) {
                //加载数据
                ajax_transaction_order();
            }
        });
    })
    $(document).ready(function() {
        $('#add_transaction_order').unbind('click');
        $('#add_transaction_order').click(function(){
            var data = {};
            data.order_name = $('#orderName').val();
            if(!data.order_name){
                alert('请输入订单名称');
                return false;
            }
            data.transaction_date = $('#transaction_date').val();
            data.product_name = $('#product_name').val();
            data.number = $('#number').val();
            data.money = $('#money').val();
            data.salesman = $('#salesman').val();
            data.remarks = $('#remarks').val();
            data.crm_id = $('#only_crm_order_id').val();
            var url = '/user/Member/add_transaction_order';
            data.id = $('#only_transaction_order_id').val();
            $.ajax({
                type: 'POST',
                url: url,
                data: data,
                success: function(data) {
                    alert(data.msg);
                    currentPage = 1;
                    listcunt = 0;
                    ajax_transaction_order();
                }
            })
        })
    })
}

    function delOrder(object){
      $("#del_order").modal('show');
      var id = $(object).data('id');
      $('#confirmDelOrder').data('id',id);
    }
    function confirmDelOrder(object){
      var id = $(object).data('id');
      var href = "/User/member/delOrder";
      $.ajax({
        type: "POST",
        dataType:'json',
        url: href,
        data: {id:id},
        success: function(result) {
          alert(result.msg);
          $("#del_order").modal('hide');
          transactionOrder(window.noly_id);
        },
        error: function(data) {
          // alert("参数错误");
        }
      })
    }

    //添加跟进记录

    function add_record() {
        $("#addRecord").modal('show');
        $('#record_resire').val('');
        $('#record_content').val('');
    }

    function addOrder() {
        $("#orderName").focus();
        $('#orderName').val('');
        $('#transaction_date').val('');
        $('#product_name').val('');
        $('#number').val('');
        $('#money').val('');
        $('#salesman').val('');
        $('#remarks').val('');
        $('#only_transaction_order_id').val('');
    }

    function orderSel(obj) {
        $(obj).addClass('orderActive').siblings().removeClass('orderActive');
        var id = $(obj).data('id');
        var url = '/user/Member/get_transaction_order';
    $.ajax({
        type: 'POST',
        url: url,
        data: {id:id},
        success: function(data) {
            var info = data.data;
            $('#orderName').val(info.order_name);
            $('#transaction_date').val(timestampToTime(info.transaction_date));
            $('#product_name').val(info.product_name);
            $('#number').val(info.number);
            $('#money').val(info.money);
            $('#salesman').val(info.salesman);
            $('#remarks').val(info.remarks);
            $('#only_transaction_order_id').val(info.id);
            $('#only_crm_order_id').val(info.crm_id);
        }
    })
}

function customer_delete_all() {
    var arr = []
    $("input[name='checkids'][type='checkbox']:checked").each(function(i) {
        if ($(this).context.checked == true) {
            arr[i] = $(this).val();
        }
    });
    if (arr.length == 0 || !arr.length) {
        alert("请至少选择一项再删除");
        return false;
    }
    $('#customer-delete-all').modal('show');
    //多删的数据id存入全局变量中
    window.arr = arr;
}
// //多删数据---逻辑实现
function delete_all() {
    var data = {};
    data.type = 0;
    //判断全选是否勾选
    if ($("input[name='DataCheck_all'][type='checkbox']").is(":checked")) {
        //如果全选勾选了 删除全部
        data.type = 1;
        data.phone = $('#phone_query').val();
        data.name = $('#name_query').val();
        data.sitchair = $("#sitchair").val(); //坐席
        data.startCreateDate = $('#startCreateDate').val();
        data.endCreateDate = $('#endCreateDate').val();
        //拨打次数
        data.min_call_count = $('#min_call_count').val();
        data.max_call_count = $('#max_call_count').val();
        var brr = []
        $("input[name='desire'][type='checkbox']:checked").each(function(index, element) {
            brr[index] = $(element).val(); //意向等级 搜索条件的获取
        });
        data.brr = brr ;
		//意向等级
        data.level = [];
        $('input[name="grade"]:checked').each(function(index, object){
          data.level.push($(object).val());
        });
        //查看状态
        data.call = [];
        $('input[name="call"]:checked').each(function(index, object){
          data.call.push($(object).val());
        });
        //客户分配
        data.distribution = [];
        $('input[name="distribution"]').each(function(index, object){
          data.distribution.push($(object).val());
        });
        //客户意愿
        data.desire = [];
        $('input[name="desire"]').each(function(index, object){
          data.desire.push($(object).val());
        });
    }

    data.vals = window.arr;
    $.ajax({
        type: 'POST',
        url: '/user/Member/delete_all_customer',
        data: data,
        success: function(data) {
            if (data.code == 1) {
                alert(data.msg);
                var page = $('#Nowpagehidden').val();
                var limit = $('#Nowlimithidden').val();
                ajax_member(page, limit);
                $('#customer-delete-all').modal('hide');
            } else if (data.code == 0) {
                alert(data.msg);
                $('#customer-delete-all').modal('hide');
            }
        }
    })
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
    $('#defaultlines').attr("disabled", true);
    $('#defaultlines').attr("checked", false);
    //备注
    $('#remark').val("");
}

var Paging = new Paging01();
  Paging.init_args({
  // url:
  page: 1, //初始页码
  limit: 10, //初始每页显示的数据量
  paging_class: 'paging', //放置分页的class
  callback: ajax_member //回调函数 比如show_datas(页码, 显示条数)
});
ajax_member();
function ajax_member(page, limit) {
  $("#xiangqing_next_hidden").val(page);
  $('.l_loadfixed').show();
    var data  = {};
    // 意向等级 全选的话
    var arr=[];  //A-6 B-5 C-4
    if( $("input[name='grade_all']").is(':checked') ){
      data.arr=arr //如果全选的话 arr是空
    }else{
      //否则不是全选
      $("input[name='grade']:checked").each(function(index, element){
          arr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.arr=arr;
    }
    var brr=[]; //已查看-1 未查看-0
    //通话查看 全选的话
    if ($("input[name='call_all']").is(':checked')){
        data.brr= brr//如果全选的话 brr是空
    }else{
       //否则不是全选
      $("input[name='call']:checked").each(function(index, element){
          brr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.brr=brr;
    }
    //客户分配 全选的话
     var crr=[];//已分配-1 未分配-0
    if( $("input[name='distribution_all']").is(':checked') ){
       data.crr=crr//如果全选的话 crr是空
    }else{
        //否则不是全选

      $("input[name='distribution']:checked").each(function(index, element){
          crr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.crr=crr;
    }
    //客户意愿 全选的话
     var drr=[];  //客户分类  0：未分类  1：意向客户   2：沟通中   3：试用中   4：已成交
    if( $("input[name='desire_all']").is(':checked') ){
         data.drr=drr //如果全选的话 drr是空
    }else{
      //否则不是全选

      $("input[name='desire']:checked").each(function(index, element){
          drr[index] = $(element).val(); //意向等级 搜索条件的获取
      });
      data.drr=drr;
    }
    var url = '/user/Member/ajax_intentional_member';
    if (!page){
      page = 1;
    }
    if(!limit){
      limit = 10;
    }
    data.page = page;
    data.limit = limit;
    //把当前页码 和每页limit 数量存入 隐藏的input中方便 别的功能调用
    $('#Nowpagehidden').val(page);
    $('#Nowlimithidden').val(limit);
    //电话
    data.phone = $('#phone_query').val();
    data.name = $('#name_query').val();
    data.sitchair = $("#sitchair").val(); //坐席
    data.config_id = $("#config_id").val(); //任务
    data.startCreateDate = $('#startCreateDate').val();
    data.endCreateDate = $('#endCreateDate').val();
    //拨打次数
    data.min_call_count = $('#min_call_count').val();
    data.max_call_count = $('#max_call_count').val();

    $("#chaxun_tiaojian1").val(JSON.stringify(data));
    $.ajax({
        type: 'POST',
        url: url,
        data: data,
        success: function(data) {
       $('.l_loadfixed').hide();
            var total = data.data.total; //数据总条数
            var Nowpage = data.data.Nowpage; //当前页码
            var page = data.data.page; //数据总页数
            window.count= total;
            window.page = page;
            var limit = data.data.limit; //每页的分页条数
            var Nowpage = parseInt(Nowpage);
            var dataList = data.data.list;
            $("#memberlist").find("tr").remove();
            if (dataList.length > 0) {
                $('#consumeempty').hide();
                $(".footerB").show();
                for (var i = 0; i < dataList.length; i++) {
                    var id = dataList[i].id; //序号
                    var name = dataList[i].name; //客户名称
                    var sex = dataList[i].sex; //性别
                    var seat_name = dataList[i].seat_name; //座席名称
                    var count_record = dataList[i].count_record; //是否有通话详情 如果是新建crm没有通话详情 那么就0
                    if (!sex) {
                        sex = '未知';
                    }
                    var phone = dataList[i].phone; //电话
                    var compay_name = dataList[i].compay_name; //客户公司
                    var count_call = dataList[i].count_call;
                    if (!compay_name) {
                        compay_name = '暂无数据';
                    }
                    var task_name = dataList[i].task_name; //任务名
                    var level = dataList[i].level; //意向等级 6：A  5：B  4：C   3：D  2：E  1：F
                    var is_look = dataList[i].is_look;
                    if (level == 6) {
                        level = "A级(意向客户)";
                    } else if (level == 5) {
                        level = "B级(一般意向)";
                    } else if (level == 4) {
                        level = "C级(简单对话)";
                    } else if (level == 3) {
                        level = "D(无有效对话)";
                    } else if (level == 2) {
                        level = "E(未接通号码)";
                    } else if (level == 1) {
                        level = "F(无效号码)";
                    } else {
                        level = "暂无意向等级";
                    }
                    var crm_cate = dataList[i].crm_cate //客户分类  0：未分类  1：意向客户   2：潜在客户   3：试用客户   4：成交客户
                    switch (crm_cate) {
                        case 0:
                            crm_cate = "未分类";
                            break;
                        case 1:
                            crm_cate = "意向客户";
                            break;
                        case 2:
                            crm_cate = "潜在客户";
                            break;
                        case 3:
                            crm_cate = "试用客户";
                            break;
                        case 4:
                            crm_cate = "成交客户";
                            break;
                        case null:
                            crm_cate = "未分类";
                            break;
                    }
                    var zuoxi = "暂无隶属坐席"; //隶属坐席
                    if (seat_name && seat_name != 'null') {
                        zuoxi = seat_name;
                    }
                    var note = dataList[i].get_note; //跟进内容
                    if (!note) {
                        note = "暂无跟进信息";
                    }


                    var create_time = timestampToTime(dataList[i].create_time); //创建时间
                    var get_times = dataList[i].get_times; //最后跟进时间
                    if (!get_times) {
                        get_times = '暂无最后跟进';
                    } else {
                        get_times = timestampToTime(get_times);
                    }
                    //这个跟进类内容暂时没用
                    /*
               var get_note = dataList[i].get_note; //跟进类容
               if(!get_note){
                  get_note = '暂时数据';
               }
               */
                     var string = '<tr class="itemId'+id+'" alt="'+id+'">'
                +'<td class="text-center"><input class="rolecheck" name="checkids" value="'+id+'" type="checkbox"/></td>'
                +'<td class="text-center">'+((Nowpage-1)*limit+(i+1))+'</td>'
                +'<td class="text-center">'+name+'</td>'
                +'<td class="text-center">'+phone+'</td>'
                +'<td class="text-center">'+task_name+'</td>'
                +'<td class="text-center">'+level+'</td>'
                // +'<td class="text-center">'+compay_name+'</td>'
                +'<td class="text-center">'+crm_cate+'</td>'
                +'<td class="text-center">'+zuoxi+'</td>'
                +'<td class="text-center">'+create_time+'</td>'
                +'<td class="text-center">'+get_times+'</td>'
                +'<td class="text-center">'
                                +'<a href="javascript:;" onclick="constomerDetail('+id+');">详情</a>&nbsp;';
                                if(count_record>0){
                                 string +='<a href="javascript:;" class="view_call_record is_look'+is_look+'"   onclick="gotoDetail('+id+');">通话记录</a>&nbsp;';
                                }else{

                                }
                                string +='<a href="javascript:;" onclick="show_add_call(\''+phone+'\');" >加入呼叫</a>&nbsp;'
                                string +='<a href="javascript:;" onclick="followUpRecord('+id+');">跟进记录</a>&nbsp;';
                                string +='<a href="javascript:;" onclick="transactionOrder('+id+');">成交订单</a>&nbsp;';
                                string +='</td>';
          string += '</tr>';
          $("#memberlist").append(string);
                }
                //点击显示分配窗口的事件
                allocation_seats();

                //Nowpage  当前页
                //count    数据总条数
                //total    总共页数
                //limit    分页数量
                Paging.paging(Nowpage, total, limit);
                click_show_record();
                page_change();
            } else {
                $('#consumeempty').show();
                $(".footerB").hide();
                Paging.paging(0, 0, 1, 10);
                click_show_record();
    page_change();
            }
            //全选
            election();
        },
     error:function(){
          $('.l_loadfixed').hide();
        }
    })
}
/**
 * 显示加入呼叫的窗口
 *
 * @param int phone 手机号码
 *
*/
function show_add_call(phone)
{
  $('#add_call_phone').val(phone);
  $('#add_call_line_group_id').val('');
  $('#add_call_scenarios_id').val('');
  $('#add_call_asr_id').val('');
  $('#add_call_window').modal('show');
}

/**
 * 提交加入呼叫的表单
*/
function submit_add_call()
{
  //1.获取数据
  //2.判断数据是否填写完整
  //3.提交
  //4.判断返回值

  //1.获取数据
  var data = {
    phone:$('#add_call_phone').val(),
    line_group_id:$('#add_call_line_group_id').val(),
    scenarios_id:$('#add_call_scenarios_id').val(),
    asr_id:$('#add_call_asr_id').val()
  };
  //2.判断数据是否填写完整
  if(data.phone == ''){
    alert('号码不能为空');
    return false;
  }
  if(data.line_group_id == ''){
    alert('请选择线路组');
    return false;
  }
  if(data.scenarios_id == ''){
    alert('请选择话术');
    return false;
  }
  if(data.asr_id == ''){
    alert('请选择ASR');
    return false;
  }
  //3.提交
  var url = '/user/member/add_call';
  $.ajax({
    url:url,
    data:data,
    dataType:'json',
    type:"POST",
    success:function(result){
      if(result.code == 0){
        alert('加入呼叫成功');
      }else if(result.code == 1){
        alert('加入呼叫失败');
      }else if(result.code == 2){
        alert(result.msg);
      }
      $('#add_call_window').modal('hide');
      console.log(result);
    },
    error:function(){
      alert('提交失败');
    }
  });


}

function timestampToTime(timestamp) {
    var date = new Date(timestamp * 1000); //时间戳为10位需*1000，时间戳为13位的话不需乘1000
    var Y = date.getFullYear();
    var M = (date.getMonth() + 1 < 10 ? '0' + (date.getMonth() + 1) : date.getMonth() + 1);
    var D = date.getDate();
    if (D < 10) {
        D = '0' + D;
    }
    var h = date.getHours();
    if (h < 10) {
        h = '0' + h;
    }
    var m = date.getMinutes();
    if (m < 10) {
        m = '0' + m;
    }
    var s = date.getSeconds();
    if (s < 10) {
        s = '0' + s;
    }
    return Y + '-' + M + '-' + D + '  ' + h + ':' + m ;
}

var adFlowNoteStatus = true;

//CRM  任务开启的时候调用
function adFlowNote(){
        if(adFlowNoteStatus == false){
          return false;
        }
        adFlowNoteStatus = false;
        /**
        * 提交规则
        *   1.任务名称不能重复（范围：当前用户）
        *   2.任务名称、话术、机器人数量、线路、工作日期和工作时间不能为空
        *   3.工作时间必须在08:00到21:30范围内
        */
        //获取提交数据
        var data = {};
        //任务名称
        data.task_name = $('#form input[name="task_name"]').val();
        //话术
        data.scenarios_id = $('#form select[name="scenarios_id"]').val();
        //机器人数量
        data.robot_count = $('#form input[name="robot_count"]').val();
        //线路
        data.line_id = $('#form select[name="line_id"]').val();
        //ASR
        data.asr_id = $('#form select[name="asr_id"]').val();
        //是否自动开启
        data.is_auto = $('input[name="is_auto"]:checked').val();
        if(data.is_auto == 1){
          //指定日期
          var start_date_objects = $('input[name="start_date"]');
          var end_date_objects = $('input[name="end_date"]');
          data.start_date = [];
          data.end_date = [];
          $.each(start_date_objects, function(index, object){
            data.start_date.push($(object).val());
          });
          $.each(end_date_objects, function(index, object){
            data.end_date.push($(object).val());
          });
          //指定时间
          var start_time_objects = $('input[name="start_time"]');
          data.start_time = [];
          var end_time_objects = $('input[name="end_time"]');
          data.end_time = [];
          $.each(start_time_objects, function(index, object){
            data.start_time.push($(object).val());
          });
          $.each(end_time_objects, function(index, object){
            data.end_time.push($(object).val());
          });
        }else{
          data.start_date = [];
          data.end_date = [];
          data.start_time = [];
          data.end_time = [];
        }
        //是否设置默认线路
        // data.is_default_line = $('#form input[name="is_default_line"]').val();
        if($("input[type='checkbox'][name='is_default_line']").is(':checked')) {
          data.is_default_line = 1;
        }else{
          data.is_default_line = 0;
        }
        //验证是否发送短信
        var send_sms_status = $('input[name="Duanxinfasong"]:checked').val();
        if(send_sms_status == '是'){
          data.send_sms_status = 1;
          //验证是否有选择意向等级
          var level_length = $('input[name="Duanxinfasong-dengji"]:checked').length;
          if(level_length == 0){
            alert('至少需要选中一项触发发送短信的意向等级');
            adFlowNoteStatus = true;
            return false;
          }
          data.send_sms_level = [];
          $.each($('input[name="Duanxinfasong-dengji"]:checked'), function(index, object){
            data.send_sms_level.push($(object).val());
          });
          data.sms_template_id = $('#sms-template').val();
        }else{
          data.send_sms_status = 0;
        }
        var is_add_crm = $('input[name="joinCRM"]:checked').val();
        if(is_add_crm == '是'){
          data.is_add_crm = 1;
          data.add_crm_level = [];
          if($('input[name="crm-Yixiangdengji"]:checked').length == 0){
            alert('请选择加入CRM的客户意向等级');
            adFlowNoteStatus = true;
            return false;
          }
          $.each($('input[name="crm-Yixiangdengji"]:checked'), function(index, object){
            data.add_crm_level.push($(object).val());
          });

                    data.crm_push_user_id = $("input:checkbox[name='crm-push-users']:checked").map(function(index, elem) {
                        return $(elem).val();
                    }).get().join(','); //多推送 把多个用户的id 用逗号分隔  1,3,5

                    //data.wx_push_user_id = $('#wx-push-users').val();  //鲁健2019-2-16 注释
                    /*if (data.crm_push_user_id == '') {
                        alert('请选择推送的人员');
                        adFlowNoteStatus = true;
                        return false;
                    }*/
        }else{
          data.is_add_crm = 0;
          data.add_crm_level = [];
          data.crm_push_user_id = '';
        }
        var wx_push_status = $('input[name="Tuisong"]:checked').val();
        if(wx_push_status == '是'){
          data.wx_push_status = 1;
          data.wx_push_level = [];
          if($('input[name="wx_push-Yixiangdengji"]:checked').length == 0){
            alert('请选择微信推送的客户意向等级');
            adFlowNoteStatus = true;
            return false;
          }
          $.each($('input[name="wx_push-Yixiangdengji"]:checked'), function(index, object){
            data.wx_push_level.push($(object).val());
          });
          data.wx_push_user_id = $("input:checkbox[name='wx-push-users']:checked").map(function(index, elem) {
            return $(elem).val();
          }).get().join(','); //多推送 把多个用户的id 用逗号分隔  1,3,5
          if(data.wx_push_user_id == ''){
            alert('请选择推送的人员');
            adFlowNoteStatus = true;
            return false;
          }
        }else{
          data.wx_push_status = 0;
          data.wx_push_level = [];
          data.wx_push_user_id = '';
        }
        //任务异常短信提醒的手机号码
        data.task_abnormal_remind_phone = $('#task_abnormal_remind_phone').val();
        if(data.task_abnormal_remind_phone != ''){
          // task_abnormal_remind_phone
           if(isPhoneNo(data.task_abnormal_remind_phone) == false){
             alert('任务异常短信提醒的手机号码格式错误');
             adFlowNoteStatus = true;
             return false;
           }
        }
        //验证是否开启重新呼叫功能
        var is_again_call = $('input[name="is_again_call"]:checked').val();
        if(is_again_call == '是'){
          data.is_again_call = 1;
          //验证是否有选择需要重新呼叫的通话状态
          data.again_call_status = $('input[name="again_call_status"]:checked').length;
          if(data.again_call_status == 0){
            alert('至少需要选中一项重新呼叫的通话状态');
            adFlowNoteStatus = true;
            return false;
          }
		   data.again_call_status = $("input:checkbox[name='again_call_status']:checked").map(function(index, elem) {
						return $(elem).val();
					}).get().join(','); //重乎状态存入数据库  1,3,5
				  
		  
          //验证重新呼叫次数
           data.again_call_count = $('#again_call_count').val();
          if(data.again_call_count == 0){
            alert('请选择重新呼叫次数');
            adFlowNoteStatus = true;
            return false;
          }
        }
        //验证是否开启微信云控推送
        var yunkong_push_status = $("input[name='yunkong']:checked").val();
        if(yunkong_push_status == '是'){
          data.yunkong_push_status = 1;
          if(query_yunkong_username_status == 0){
            alert('请先检索推送给微信云控的用户是否存在');
            adFlowNoteStatus = true;
            return false;
          }
          if(query_yunkong_username_status == 2){
            alert('该微信云控推送用户不存在');
            adFlowNoteStatus = true;
            return false;
          }
          data.yunkong_push_username = $('#yunkong_username').val();
          data.yunkong_push_level = [];
          $.each($('input[name="yunkong-Yixiangdengji"]:checked'), function(index, object){
            data.yunkong_push_level.push($(object).val());
          });
          if(data.yunkong_push_level.length == 0){
            alert('请选择需要推送到微信云控的意向等级');
            adFlowNoteStatus = true;
            return false;
          }
        }
        //备注
        data.remark = $('#form textarea[name="remark"]').val();
        if($('#all_checked_count').is(":checked")){
            type = 1;
        }
        //0不是全选
        data.checkAllType=0;
        //是否全选 如果全选 得到筛选条件
        if($(".all_checked_count").is(':checked')){
            //电话
            data.phone = $('#phone_query').val();
            data.name = $('#name_query').val();
            data.sitchair = $("#sitchair").val(); //坐席
            data.startCreateDate = $('#startCreateDate').val();
            data.endCreateDate = $('#endCreateDate').val();
            //拨打次数
            data.min_call_count = $('#min_call_count').val();
            data.max_call_count = $('#max_call_count').val();
            var brr = []
            $("input[name='desire'][type='checkbox']:checked").each(function(index, element) {
                brr[index] = $(element).val(); //意向等级 搜索条件的获取
            });
            data.brr = brr ;
            data.checkAllType = 1;
        }
        //如果没有全选 那么就需要读取 各条数据下的 多选框 并且读取值 name=checkids
        var xrr=[];
        $("input[name='checkids'][type='checkbox']:checked").each(function(index,element){
          xrr[index]=$(element).val();     //crm 表中的 客户id
        });
        data.xrr=xrr;
        var href = "/User/member/create_task";
        $.ajax({
            type: "POST",
            dataType:'json',
            url: href,
            cache: false,
            data: data,
            success: function(result) {
              if(result.code == 0){
                alert('添加成功');
                adFlowNoteStatus = true;
                window.location.href = '/user/plan/newindex';
              }else{
                adFlowNoteStatus = true;
                alert(result.msg);
              }
            },
            error: function(data) {
              adFlowNoteStatus = true;
              alert("添加失败");
            }
          })
    }







$(function(){
    var options = {
         type: 'POST',     // 设置表单提交方式
         url: "/user/member/importexcelcallmember",    // 设置表单提交URL,默认为表单Form上action的路径
         dataType: 'json',    // 返回数据类型
         beforeSubmit: function(formData, jqForm, option){    // 表单提交之前的回调函数，一般用户表单验证
             // formData: 数组对象,提交表单时,Form插件会以Ajax方式自动提交这些数据,格式Json数组,形如[{name:userName, value:admin},{name:passWord, value:123}]
             // jqForm: jQuery对象,，封装了表单的元素
             // options: options对象
          //   var str = $.param(formData);    // name=admin&passWord=123
           //  var dom = jqForm[0];    // 将jqForm转换为DOM对象
            // var name = dom.name.value;    // 访问jqForm的DOM元素
             /* 表单提交前的操作 */
             return true;  // 只要不返回false,表单都会提交
         },
         success: function(responseText, statusText, xhr, $form){    // 成功后的回调函数(返回数据由responseText获得),
           if (responseText.code == 0) {
              //客户导入成功刷新 ajax_member(1)
            $(".progress-bar-data").width(100.00 + '%');
            $(".Progress_value").html('100.00' + '%');
            if($('.Progress_value').html() == '100.00%'){
              //延迟1秒在执行 加强体验度
              setTimeout(function(){
                $('.import').addClass('hidden');
                $('.finish').removeClass('hidden');
                var tmp = responseText.msg.split(',');
                temp = '';
                $.each(tmp,function(i,value){
                  temp += '<p>' + value + '</p>';
                });
                $('#effect-tips-content').html(temp);
              },1000)
              $('#upload_ok').click(function(){
                ajax_member(1);
              })
            }
           }else{
            //$('#exampleModal').modal('show');
           }
           window.clearInterval(window.import_dingshi);
         },
         error: function(xhr, status, err) {
            alert("操作失败!");    // 访问地址失败，或发生异常没有正常返回
            window.clearInterval(window.import_dingshi);
         },
         clearForm: true,    // 成功提交后，清除表单填写内容
         resetForm: true    // 成功提交后，重置表单填写内容
     };

    // 2.绑定ajaxSubmit()
    $("#fileform").submit(function(){     // 提交表单的id
        $(this).ajaxSubmit(options);
        window.import_dingshi = window.setInterval(effectTmp, 1000);
        return false;   //防止表单自动提交
    });
    ajax_member(1);
});

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
      $(".progress-bar-data").width((result.data.baifenbi) + '%');
      $(".Progress_value").html((result.data.baifenbi.toFixed(2)) + '%');
    },
    error: function() {
    }
  });
}
function out_effectTmp(){
  var url = "/user/plan/outexcel_degree"
  $.ajax({
    type: 'POST',
    dataType: "json",
    data: {
      chaos_num:window.chaos_num_out
    },
    url: url,
    success: function(result) {
      $(".progress-bar-data").width((result.data.percentage) + '%');
      $(".Progress_value").html((result.data.percentage)+ '%');
    },
    error: function() {
    }
  });
}



// ----------------------------------------------------- 通过模拟点击 实现 通话记录上一条下一条的切换功能 -------------------------------------------------//

//游戏规则
//1.点击查看下一条数据时 查询下一条数据的是否存在通话记录 如果有 点击一下 没有的话 找下下一条 直到找到为止 要是没有找到 提示"已经是最后一条数据了" 上一条也是这样
//2.


window.click_view_call_record_status = false;
/**
 * 用来绑定点击查看通话详情的事件 记录当前点击的下标
*/
function click_show_record()
{
  $('.view_call_record').unbind('click');
  $('.view_call_record').click(function(){
    window.show_record_index = $(this).parent('td').parent('tr').index();
    console.log('#####window.show_record_index'+window.show_record_index);
  });
}

function page_change()
{
  while(window.find_call_record_status)
  {
    debugger;
    console.log('#####window.show_record_index'+window.show_record_index);
    
    console.log($('#memberlist tr').eq(window.show_record_index).find('td').eq(10).find('.view_call_record').length);
    
    // 获取本页的数据最大值
    var limit = $('.limit').val();
    
    if(limit == '' || limit == undefined){
      limit = 10;
    }
    
    // 判断当前下标下的数据是否存在通话记录 存在
    if($('#memberlist tr').eq(window.show_record_index).find('td').eq(10).find('.view_call_record').length >= 1 && window.show_record_index >= 0 && window.show_record_index <= (limit - 1)){
      
      $('#memberlist tr').eq(window.show_record_index).find('td').eq(10).find('.view_call_record').click();
      
      // 不需要再去查询数据了 找到了
      window.find_call_record_status = false;
      
    // 不存在
    }else if(window.click_type == 'next'){
      
      window.show_record_index++;
      
      //获取本页的数据条数
      var length = $('#memberlist tr').length;
      
      
      
      if(length < limit && (length - 1) < window.show_record_index){
        alert('这已经是最后一条数据了');
        window.find_call_record_status = false;;
        return false;
      }
      
      // 判断是否该跳下一页了
      if(window.show_record_index > (limit - 1)){
        
        // 获取当前页码
        var current_page_number = $('.pagination li.active').data('page');
        
        // 获取下一页的页码
        var next_page_number = $('.pagination li:last').data('page');
        
        console.log('current_page_number#'+current_page_number);
        console.log('next_page_number#'+next_page_number);
        
        if(current_page_number < next_page_number && next_page_number != undefined && next_page_number != ''){
          console.log('点击下一页');
          // 是的 跳一下
          window.show_record_index = 0;
          $('.pagination li:last').click();
          return false;
        }else{
          
          alert('这已经是最后一条数据了');
          window.find_call_record_status = false;
          return false;
        }
        
        
      }
    }else if(window.click_type == 'pre'){
      window.show_record_index--;
      
      //判断是否该跳上一页了
      if(window.show_record_index < 0){
        
        // 获取当前页码
        var current_page_number = $('.pagination li.active').data('page');
        
        // 获取上一页的页码
        var pre_page_number = $('.pagination li').eq(0).data('page');
        
        if(current_page_number > pre_page_number && pre_page_number != undefined && pre_page_number != ''){
          window.show_record_index = (limit - 1);
          $('.pagination li').eq(0).click();
          return false;
        }else{
          
          alert('已经是第一条数据了');
          window.find_call_record_status = false;
          return false;
          
        }
        
        
        
      }
      
    }
  }
}


$(function(){
  $('.next').click(function(){
    console.log('能点击了');
    //递增
    window.show_record_index++;
    
    // 是否需要查询数据
    window.find_call_record_status = true;
    
    // 设置本次点击的类型 上一条 下一条 
    window.click_type = 'next';
    
    page_change();
    
    
  });
  
  //上一条数据
  $('.pre').click(function(){
    
    console.log('点击上一条数据');
    
    // 递减
    window.show_record_index--;
    
    // 是否需要查询数据
    window.find_call_record_status = true;
    
    // 设置本次点击的类型
    window.click_type = 'pre';
    
    page_change();
    
  });
});



