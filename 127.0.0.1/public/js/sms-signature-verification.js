//获取数据
var Paging = new Paging01();
Paging.init_args({
	page: 1,
	limit: 10,
	paging_class: 'paging',
	callback: get_datas,
});
get_datas(1, 10);
function get_datas(page, limit)
{
	var url = '/user/sms/get_signature_record';
	var data = {};
	data.page = page;
	data.limit = limit;
	data.start_create_time = $('#startDate').val();
	data.end_create_time = $('#endTime').val();
	data.keyword = $('#keyword').val();
	$.ajax({
		type:"POST",
		dataType:"JSON",
		data:data,
		url:url,
		success:function(result){
			console.log(result);
			if(result.code == 0){
				var htmls = '';
				if(result.data.datas == 0){
					$('#consumeempty').show();
					$('.main-box-footer').hide();
				}else{
					$('#consumeempty').hide();
					$('.main-box-footer').show();
					$.each(result.data.datas, function(index, object){
						var html = $('#option_template').html();
								html = html.replace('{%key%}', object.key);
								html = html.replace('{%username%}', object.username);
								html = html.replace('{%channel_name%}', object.channel_name);
								html = html.replace('{%sign_name%}', object.sign_name);
								html = html.replace('{%date%}', object.create_time);
						htmls += html;
					});
				}
				$('#show_datas').html(htmls);
				$('#show_count').html(result.data.count);
				//(page, count, limit, data)
				Paging.paging(page, result.data.count, limit);
			}
		},
		error:function(){
			console.log('错误');
		}
	});
}