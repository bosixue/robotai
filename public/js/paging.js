class Paging01{

	/**
	 * 配置
	 *
	 * @param int args.page 页码 页码参数统一"page"
	 * @param int args.limit 每页显示的数量 参数统一"limit"
	 * @param string args.paging_class 放置分页的class
	 * @param function args.callback 回调函数
	 * @param string args.key 多个分页时 提供一个唯一值
	 * @param string args.data 自定义参数
	*/
	init_args(args){
		this.page = args.page;
		this.limit = args.limit;
		this.paging_class = args.paging_class;
		this.callback = args.callback;
		this.key = args.key;
		this.data = '';
	}

	//跳转
	go_up(page, limit)
	{
		this.paging(page, this.count, limit);
		// show_datas(page);
	}
	/**
	 * @param int page 页码
	 * @param int count 总数量
	 * @param int limit 显示数量
	 * @param array data 自定义参数
	 * paging(page, count, limit, data)
	*/
	paging(page, count, limit, data)
	{
		//设置默认值
		page = arguments[0]?arguments[0]:this.page;
		count = arguments[1]?arguments[1]:this.count;
		limit = arguments[2]?arguments[2]:this.limit;
		data = arguments[3]?arguments[3]:'';
		this.data = data;
		//转数值
		page = parseInt(page);
		limit = parseInt(limit);
		//计算总页码
		if(count == 0 || count == undefined || count == null){
			var page_count = 0;
		}else{
			var page_count = Math.ceil(count/limit);
		}
	  var html = '';
	  //待处理
	  //偶数
	  // if(show_count%2){
	  // 	// cha =
	  // //奇数
	  // }else{

	  // }
	  this.limit = limit;
	  //计算起点和终点
	  var start = page - 2;
	  var end = page + 2;
	  if(start < 1){
	    end = end - start;
	    start = 1;
	  }
	  if(end > page_count){
	    end = page_count;
	  }
	  //显示条数的选项
	  var limits = [
	  	10,
	  	30,
	  	50,
	  	100
	  ];
		if(page_count > 1 || limit > 10){
		  //分页html
			html += '<div>';
		    html += '<ul class="pagination">';
		        html += '<div style="font-size: 12px;display: inline-block;">';
		            html += '<select class="limit paging_limit_change'+this.key+'" style="width: 80px;margin: 0px 8px;height:32px;background:#fff;border:1px solid #ddd;">';
		                for(var i = 0; i < limits.length; i++){
		                	if(limits[i] == limit){
		                		html += '<option value="'+limits[i]+'" selected="selected">'+limits[i]+'条/页</option>';
		                	}else{
		                		html += '<option value="'+limits[i]+'">'+limits[i]+'条/页</option>';
		                	}
		                }
		            html += '</select>';
		        html += '</div>';
		        html += '<div style="font-size: 12px;margin: 0px;display: inline-block;">跳至';
		            html += '<input class="Nowpage" type="number" style="width: 50px;height:32px; margin: 1px 8px;border:1px solid #ddd;border-radius: 5px;text-align: center;" value="'+page+'" max="'+page_count+'" min="1">页</div>';
		        html += '<button class="btn btn-primary paging_go_up'+this.key+'" type="button" data-toggle="modal">确定</button>';
		        if(page === 1){
		        	html += '<li id="prevbtn" class="disabled"><span>«</span>';
		        }else{
		        	html += '<li id="prevbtn" class="paging_button'+this.key+'" data-page="'+(page - 1)+'"><a href="javascript:void(0);"><span>«</span></a>';
		        }
		        html += '</li>';
		        for(start; start<=end;start++){
		        	if(start == page){
		        		html += '<li class="paging_button'+this.key+' active" data-page="'+start+'"><a href="javascript:void(0);">'+start+' </a>';
		            html += '</li>';
		        	}else{
		        		html += '<li class="paging_button'+this.key+'" data-page="'+start+'"><a href="javascript:void(0);">'+start+' </a>';
		            html += '</li>';
		        	}
		        }
		        if(page == page_count){
		        	html += '<li id="prevbtn" class="disabled"><span>»</span>';
		        }else{
		         	html += '<li data-page="'+(page + 1)+'" class="paging_button'+this.key+'"><a href="javascript:void(0);"><span>»</span></a>';
		        }
		        html += '</li>';
		    html += '</ul>';
		    html += '<div style="font-size: 12px;float: right;margin: 14px 9px 0px 0px;display: inline-block;">';
		  		html += '<span style="font-size: 12px;">总数量：<span id="pging_all_page'+this.key+'">'+count+'条</span></span>';
		    html += '</div>';
		    html += '<div style="font-size: 12px;float: right;margin: 14px 9px 0px 0px;display: inline-block;">';
		  		html += '<span style="font-size: 12px;">总页数：<span id="pging_all_page'+this.key+'">'+page_count+'页</span></span>';
		    html += '</div>';
			html += '</div>';
		}
		$('.'+this.paging_class).html(html);
		this.paging_go_up();
		this.limit_change();
		this.click_paging_button();
	}
	//
	click_paging_button()
	{
		var _this = this;
		// $('.paging_button').unbind('click');
		$('.paging_button'+this.key).click(function(){
			var page = $(this).data('page');
			console.log(page);
			// _this.paging(page);
			console.log(_this.callback);
			if(_this.callback != undefined && typeof _this.callback == 'function'){
				console.log('JIIIIII');
				_this.callback(page, _this.limit, _this.data);
			}
		})
	}
	//
	paging_go_up()
	{
		var _this = this;
		// $('.paging_go_up').unbind('click');
		$('.paging_go_up'+this.key).click(function(){
			var limit = $(this).siblings('div').find('.limit').val();
			var page = $(this).siblings('div').find('.Nowpage').val();
			_this.limit = limit;
			// _this.go_up(page, limit);
			if(_this.callback != undefined && typeof _this.callback == 'function'){
				_this.callback(page, limit, _this.data);
			}
		});

	}

	//
	limit_change()
	{
		var _this = this;
		// $('.paging_limit_change').unbind('change');
		$('.paging_limit_change'+this.key).change(function(){
			var limit = $(this).val();
			_this.limit = limit;
			if(_this.callback != undefined && typeof _this.callback == 'function'){
				_this.callback(1, _this.limit, _this.data);
			}
		});

	}
}
