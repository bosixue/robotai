//知识库的搜索
function getkeyword(){
   getKnowledgeList(1);
}
//删除知识库
function delKnowledge(type){
 if(type == 2){
 		//单个删除
 		var id = $('#knowledgebase-single-delete').attr('data-id');
 	}else{
 		//批量删除
 		var id = $('#knowledge-batch-delete').attr('data-id');
 		id = id.split(",");
 	}
	console.log(id);
	$.post("/user/scenarios/delKnowledge",{'id':id},function(data){
			if(data){
				alert(data);
				// window.location.reload();
			}
			$('#knowledge-batch-delete').modal('hide');
			$('#knowledgebase-single-delete').modal('hide');
			getKnowledgeList(1);
	});
}
//批量删除知识库
function delKnowledgeall(){
	var del_id = [];
	if(!$("#checkall").checked){
		$.each($('.check_know'), function(index, object){
			if($(object).prop("checked") == true){
				del_id.push($(object).val());
			}
		});
		var id = del_id.join(','); //转字符串
	}else{
		//全选全部删除(待处理)
	}
	if(id == ''){
		alert("至少选择一条！");
		$('.cancelkn').removeAttr('data-target');
	}else{
		$('#knowledge-batch-delete').attr('data-id',id);
		$('.cancelkn').attr('data-target','#knowledge-batch-delete');
	}
}
//单独删除知识库
function delKnowledgesinger(id){
	$("#knowledgebase-single-delete").attr('data-id',id);
}
//知识库选项框选中事件
function checkall_thing(){ //全选
	if($('#checkall').is(':checked')){
		$('.check_knowledgebase').prop("checked",true);
		$('.check_know').prop("checked",true);
		$('#checkall_num').text($('#checkall_num').attr('data-total'));
		$('.check_knowledgebase').attr('data-page',$('#checkall_num').attr('data-total'));
	}else{
		$('.check_knowledgebase').prop("checked",false);
		$('.check_know').prop("checked",false);
		$('#checkall_num').text(0);
		$('.check_knowledgebase').attr('data-page',0);
	}
}
function check_knowledgebase_thing(){ //当前页全选
	if($('.check_knowledgebase').is(':checked')) {
		$('.check_know').prop("checked",true);
		$('#checkall_num').text($('.check_know:checked').length);
		$('.check_knowledgebase').attr('data-page',$('#checkall_num').attr('data-page'));
	}else{
		$('#checkall').prop("checked",false);
		$('.check_know').prop("checked",false);
		$('#checkall_num').text(0);
		$('.check_knowledgebase').attr('data-page',0);
	}
}
function check_knowthing(){  //单个选
	if (!$(".check_know").checked) {
		$('.check_knowledgebase').prop("checked",false);
		$('#checkall').prop("checked",false);
		$('.check_knowledgebase').attr('data-page',0);
	}
	var check_know_pagenum = $('.check_know').length;
	var check_know_num = $('.check_know:checked').length;
	if(check_know_pagenum == check_know_num){
		$('.check_knowledgebase').prop("checked",true);
		$('.check_knowledgebase').attr('data-page',$('#checkall_num').attr('data-page'));
	}
	$('#checkall_num').text(check_know_num);
}


// 录音管理-页面切换
$('.r_recorddata li').click(function() {
	var index = $('.r_recorddata li').index($(this));
	var data = $('.r_recordshow');
	$(this).addClass('active').siblings().removeClass('active');
	$('.r_recordshow').addClass('hidden');
	data.eq(index).removeClass('hidden');
});












