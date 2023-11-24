
//时间戳转换日期
function onmyTime() {
	(function($) {
    $.extend({
      myTime: {
        /**
         * 当前时间戳
         * @return <int>        unix时间戳(秒)  
         */
        CurTime: function() {
            return Date.parse(new Date()) / 1000;
        },
        /**              
         * 日期 转换为 Unix时间戳
         * @param <string> 2014-01-01 20:20:20  日期格式              
         * @return <int>        unix时间戳(秒)              
         */
        DateToUnix: function(string) {
          var f = string.split(' ', 2);
          var d = (f[0] ? f[0] : '').split('-', 3);
          var t = (f[1] ? f[1] : '').split(':', 3);
          return (new Date(
              parseInt(d[0], 10) || null, (parseInt(d[1], 10) || 1) - 1,
              parseInt(d[2], 10) || null,
              parseInt(t[0], 10) || null,
              parseInt(t[1], 10) || null,
              parseInt(t[2], 10) || null)).getTime() / 1000;
          },
          /**              
           * 时间戳转换日期              
           * @param <int> unixTime    待时间戳(秒)              
           * @param <bool> isFull    返回完整时间(Y-m-d 或者 Y-m-d H:i:s)              
           * @param <int>  timeZone   时区              
           */
          UnixToDate: function(unixTime, isFull, timeZone) {
              if (typeof(timeZone) == 'number') {
                  unixTime = parseInt(unixTime) + parseInt(timeZone) * 60 * 60;
              }
              var time = new Date(unixTime * 1000);
              var ymdhis = "";
              ymdhis += time.getUTCFullYear() + "-";
              ymdhis += (time.getUTCMonth() + 1) + "-";
              ymdhis += time.getUTCDate();
              if (isFull === true) {
                  ymdhis += " " + time.getUTCHours() + ":";
                  ymdhis += time.getUTCMinutes() + ":";
                  ymdhis += time.getUTCSeconds();
              }
              return ymdhis;
            }
        }
    });
  })(jQuery);
}

// 验证手机号
function isPhoneNo(phone) {
   var pattern = /^1[3456789]\d{9}$/;
   return pattern.test(phone);
}
function timestampToTime(timestamp,type) {
		if(!type){
			type = 1;
		}
	  var oDate = new Date(timestamp*1000)
	  oYear = oDate.getFullYear(),  
	  oMonth = oDate.getMonth()+1,  
	  oDay = oDate.getDate(),  
	  oHour = oDate.getHours(),  
	  oMin = oDate.getMinutes(),  
	  oSen = oDate.getSeconds();
	  if(type == 1){
	  	oTime = oYear +'-'+ getzf(oMonth) +'-'+ getzf(oDay);//最后拼接时间  
	  }else{
	  	oTime = oYear +'-'+ getzf(oMonth) +'-'+ getzf(oDay) +' '+ getzf(oHour) +':'+ getzf(oMin) +':'+getzf(oSen);//最后拼接时间  
	  }
	  return oTime; 
}
function getzf(num){  
    if(parseInt(num) < 10){  
        num = '0'+num;  
    }  
    return num;  
}
//日期对比
function Contrastdate(starttime,endtime){
	var start = new Date(starttime.replace("-", "/").replace("-", "/"));
	var end = new Date(endtime.replace("-", "/").replace("-", "/"));
	if(start > end){  
		return true;  
	}else{
		return false;  
	}
}
//当前日期
function getFormatDate(length){
		var nowDate = new Date();
    var year = nowDate.getFullYear();
    var month = nowDate.getMonth() + 1 < 10 ? "0" + (nowDate.getMonth() + 1) : nowDate.getMonth() + 1;
    var date = nowDate.getDate() < 10 ? "0" + nowDate.getDate() : nowDate.getDate();
    var hour = nowDate.getHours()< 10 ? "0" + nowDate.getHours() : nowDate.getHours();
    var minute = nowDate.getMinutes()< 10 ? "0" + nowDate.getMinutes() : nowDate.getMinutes();
    var second = nowDate.getSeconds()< 10 ? "0" + nowDate.getSeconds() : nowDate.getSeconds();
		if(length == 13){
			return year + "-" + month + "-" + date+" "+hour+":"+minute+":"+second;
		}else if(length ==10){
			return year + "-" + month + "-" + date ;
		}
}

// 格林时间转本地时间
function FormatLocaDate(obj){
  var str = '';
  str += obj.getFullYear() + '-';

  if ((obj.getMonth() + 1) < 10) {
    str += '0' + (obj.getMonth() + 1) + '-';
  }else{
    str += (obj.getMonth() + 1) + '-';
  }
  if (obj.getDate() < 10) {
    str += '0' + obj.getDate();
  } else {
    str += obj.getDate();
  }
  return str;
}

function toFixed_num(Number,length){
	if(length == 4){
		return parseFloat(Number).toFixed(4);
	}else if(length == 2){
		return parseFloat(Number).toFixed(2);
	}else if(length == 3){
		return parseFloat(Number).toFixed(3);
	}else{
		return parseFloat(Number).toFixed(length);
	}
}

function election(){
	console.log('引用成功')
	console.log(window.count)
	if(window.count == 0){
	  $(".all_checked_count").prop("checked", false);
	  $("input[name='all_checked'][type='checkbox']").prop("checked",false);
	}else{
	  if($('.all_checked_count').is(":checked")){
  		$("input[name='checkids'][type='checkbox']").prop("checked",true);
  		$("input[name='all_checked'][type='checkbox']").prop("checked",true);
  		$('#check_count').text(window.count);
  		$('#user_count').text(window.count);
  	}else{
  		$('#user_count').text(0);
  		$('#check_count').text(0);
  		$("input[name='all_checked'][type='checkbox']").prop("checked",false);
  	}
	}
	$("input[name='all_checked'][type='checkbox']").click(function(){
		 if ($("input[name='all_checked'][type='checkbox']").is(":checked")) {
	  		$("input[name='checkids'][type='checkbox']").prop("checked",true);
	  		$(".all_checked_count").prop("checked", false);
	  	} else {
	 		$("input[name='checkids'][type='checkbox']").prop("checked",false);
	 		$(".all_checked_count").prop("checked", false);
	 	}
	 	$('#user_count').text($("input[name='checkids'][type='checkbox']:checked").length);
	 	$('#check_count').text($("input[name='checkids'][type='checkbox']:checked").length);
	 });
		//子复选框的事件
		$('input[type="checkbox"][name="checkids"]').click(function(){
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
		$('.all_checked_count').click(function(){
			if($(this).prop('checked') === true){
				$.each($('.all_checked_count'),function(index,obj){
					$(obj).prop("checked",true);
				});
				$("input[name='checkids'][type='checkbox']").prop("checked",true);
				$("input[name='all_checked'][type='checkbox']").prop("checked", true);
				$('#check_count').text(window.count);
				$('#user_count').text(window.count);
			}else{
				$.each($('.all_checked_count'),function(index,obj){
					$(obj).prop("checked",false);
				});
					$("input[name='checkids'][type='checkbox']").prop("checked",false);
	 			$("input[name='all_checked'][type='checkbox']").prop("checked", false);
	 			$('#check_count').text(0);
	 			$('#user_count').text(0);
			}
	});
}