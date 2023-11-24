window.click_view_call_record_status = false;
/**
 * 用来绑定点击查看通话详情的事件 记录当前点击的下标
*/
function click_show_record()
{
	$('.click_show_record').unbind('click');
	$('.click_show_record').click(function(){
		window.show_record_index = $(this).parent('td').parent('tr').index();
		//console.log('#####window.show_record_index'+window.show_record_index);
	});
}
function paging_change()
{
  //console.log('paging_change');
	click_show_record();
	//console.log('window.click_view_call_record_status#'+window.click_view_call_record_status);
	if(window.click_view_call_record_status === true && window.click_type == 'next'){
	  
		window.show_record_index = 0;
		// 加载完成后 点击查看第一条通话记录的通话详情
		$('#tablepagelist > tr').eq(window.show_record_index).find('td').eq(10).find('a.click_show_record').click();
		
		window.click_view_call_record_status = false;
	
	  
	}else if(window.click_view_call_record_status === true && window.click_type == 'pre'){
	  
	  // 获取本页的最大数据量的条数
  	var limit = $('#Nowlimithidden').val();
  	// 如果为空 设置默认值
  	if(limit === ''){
  		limit = 10;
  	}
	  window.show_record_index = (limit - 1);
	  // 加载完成后 点击查看第一条通话记录的通话详情
		$('#tablepagelist > tr').eq(window.show_record_index).find('td').eq(10).find('a.click_show_record').click();
		//
	  window.click_view_call_record_status = false;
	  
	}
}
$('.next').click(function(){
  //下标加一
	window.show_record_index++;
	
	// 获取本页的最大数据量的条数
	var limit = $('#Nowlimithidden').val();
	// 如果为空 设置默认值
	if(limit === ''){
		limit = 10;
	}
// 	console.log({
// 		show_record_index:window.show_record_index,
// 		limit:limit
// 	});
	// 当查询的数据不存在本页时 点击下一页
	if(window.show_record_index > (limit - 1)){
	    // 获取下一页的页码 
	    var next_page_number = $('.pagination li:last').data('page');
	    //获取当前页码
	    var current_page_number = $('.pagination .active').data('page');
	    if(next_page_number > current_page_number){
	      //设置电话的类型 下一条
	    	window.click_type = 'next';
	    	// 监听下一页的数据是否加载完成
	    	window.click_view_call_record_status = true;
	    	// 点击下一页
	    	$('.pagination li:last').click();
	    }else{
	    	alert('已经是最后一条数据了');
	    	return false;
	    }
  // 否则 点击查看下一条数据的通话详情
	}else{
	  $('#tablepagelist > tr').eq(window.show_record_index).find('td').eq(10).find('a.click_show_record').click();
	}
});

//上一条数据
$('.pre').click(function(){
  // 下标减一
	window.show_record_index--;
	if(window.show_record_index < 0){
	  // 获取当前页码
	  var current_page_number = $('.pagination .active').data('page');
	  if(current_page_number == 1){
	    alert('已经是第一条数据');
	    return false;
	  }
	  // 设置电话的类型 下一条
  	window.click_type = 'pre';
  	// 监听上一页的数据是否加载完成
  	window.click_view_call_record_status = true;
  	// 点击上一页
  	$('.pagination li').eq(0).click();
	}else{
	  $('#tablepagelist > tr').eq(window.show_record_index).find('td').eq(10).find('a.click_show_record').click();
	}
});