
  //添加条件到模板

  function grade_condition() {
	  //话术id
	  var id =$("#nowsceneID").val();
      var confTitle = $("#confTitle").text();
      var options_zhi = $("#options").val();
      var results_zhi = $("#results").val();
      var linkageRelationship = $("#linkageRelationship").val();
      var intentionLevel = $("#intentionLevel").val();
	    var resultss = $("#resultss").val();
      if (linkageRelationship === '') {
          alert("请选择联动关系！");
          return;
      } else if (intentionLevel === '') {
          alert("请选择加入意向！");
          return;
      }else if (results_zhi === '') {
          alert("值不能是空");
          return;
      }


      key = $("#add_type").data("type");
      // console.log(type + results);

      if (key == 'hit_problem_times'){
          results = results_zhi + '次';
		      options = options_zhi;
		      type='int';
      }else if (key == 'affirm_times'){
          results = results_zhi + '次';
		      options = options_zhi;
		      type='int';
      }else if (key == 'reject_times') {
          results = results_zhi + '次';
		      options = options_zhi;
		      type='int';
      }else if (key == 'speak_count'){
          results = results_zhi + '次';
		      options = options_zhi;
		      type='int';
      }else if (key == 'call_duration'){
          results = results_zhi + '秒';
		      options = options_zhi;
		      type='int';
      }else if(key == 'say_keyword'){
		      options= options_zhi=='in' ? '包含任一':'包含全部';
		      results=results_zhi;
		      type='string';
      }else if(key=='liuchenglable'){
        var results='';
        var arr=[];
        options = options_zhi == 'in' ? '包含任一' : '包含全部';
        var flow_label = $("input[class='flow_label']:checked");
        $.each(flow_label, function(index, object){
             arr.push($(object).val());
        });
        if(arr.length<=0){
           alert("必须选择一个流程标签");
           return;
        }
        results = arr.join(',');//数组转成为字符串
        results_zhi = arr.join(',');
        type='string';
    }else if(key=='wendalable'){
        var results='';
        var arr=[];
        options = options_zhi == 'in' ? '包含任一' : '包含全部';
        var wenda_label = $("input[class='wenda_label']:checked");
        $.each(wenda_label, function(index, object){
             arr.push($(object).val());
        });
        if(arr.length<=0){
           alert("必须选择一个问答标签");
           return;
        }
        results = arr.join(',');//数组转成为字符串
        results_zhi = arr.join(',');
        type='string';
    }else if(key=='yuyilable'){
        var results='';
        var arr=[];
        options = options_zhi == 'in' ? '包含任一' : '包含全部';
        var yuyi_label = $("input[class='yuyi_label']:checked");
        $.each(yuyi_label, function(index, object){
             arr.push($(object).val());
        });
        if(arr.length<=0){
           alert("必须选择一个语义标签");
           return;
        }
        results = arr.join(',');//数组转成为字符串
        results_zhi = arr.join(',');
        type='string';
    }else {
		  options = options_zhi
		  var array=[];
		  array[3] = '无人接听';
		  array[4] = '停机';
		  array[5] = '空号';
		  array[6] = '正在通话中';
		  array[7] = '关机';
		  array[8] = '用户拒接';
		  array[9] = '网络忙';
		  array[10] = '来电提醒';
		  array[11] = '呼叫转移失败';
		  resultss = resultss.toString()
		  type = 'array';
		  res = resultss.split(",");
		  results_zhi = resultss
		  var results = "";
		  if(res.length>1){
			  for(var i=0; i<res.length;i++){
				if(i==0){
					results=array[res[i]];
				}else{
				    results+= ','+array[res[i]];
				}
			  }
		  }else{
			 results= array[res];
		  }
      }
      var htmls = '';
      var html1 = '';
      var html2 = '';
	      //或的 html代码
          htmls += '<div class="form-group displayf conditions-content" >'
                      +'  	<div class="conditions" data-type="'+type+'" data-level="'+intentionLevel+'" data-key="'+key+'" data-op="'+options_zhi+'" data-value="'+results_zhi+'">'
                      +'  		<img src="/public/img/gradeclose.png"></img>'
                      +'  		<span style="margin-right:4px;">' + confTitle + '</span>'
                      +'  		<input type="text" class="form-control" value="'+ options +'" readonly="readonly">'
                      +'  		<input type="text" class="form-control" title="'+ results +'" value="'+ results +'" readonly="readonly">'
                      +'  	</div>'
                      +'  </div>';

          //且的html代码
          html1 += '<div class="form-group displayf  conditions-content"  >'
                      +'  	<div class="conditions" data-type="'+type+'" data-level="'+intentionLevel+'"  data-key="'+key+'" data-op="'+options_zhi+'" data-value="'+results_zhi+'">'
                      +'  		<img src="/public/img/gradeclose.png"></img>'
                      +'  		<span style="margin-right:4px;">' + confTitle + '</span>'
                      +'  		<input type="text" class="form-control" value="'+ options +'" readonly="readonly">'
                      +'  		<input type="text" class="form-control" title="'+ results +'" value="'+ results +'" readonly="readonly">'
                      +'  	</div>'
                      +'  	<div class="relationship">'
                      +'  		<img src="/public/img/relationship.png">'
                      +'  		<a href="javascript:;" class="wordh">且</a>'
                      +'  	</div>'
					  +' </div>';
          //长度不是2 而且 且的下一句html代码
          html2 +=  '<div class="conditions" data-type="'+type+'" data-level="'+intentionLevel+'"   data-key="'+key+'" data-op="'+options_zhi+'" data-value="'+results_zhi+'">'
                      +'  		<img src="/public/img/gradeclose.png"></img>'
                      +'  		<span style="margin-right:4px;">' + confTitle + '</span>'
                      +'  		<input type="text" class="form-control" value="'+ options +'" readonly="readonly">'
                      +'  		<input type="text" class="form-control" title="'+ results +'" value="'+ results +'" readonly="readonly">'
                      +'  </div>';



      if ($('#grade'+ intentionLevel + ' .nullClass>div:last-child').find('div').length == 2){
          //当前一个配置了且关系下一个无论是什么都需要显示在且后面
          $('#grade'+ intentionLevel + " .nullClass>div:last-child").append(html2);
          $("#add_type").modal("hide");
            //删除意向等级条件
          del_condition();

      } else {
        //是否选中的是且
        // console.log(htmls);
        if(linkageRelationship == 0){
          // console.log('#grade'+intentionLevel + " .nullClass");
          $('#grade'+intentionLevel + " .nullClass").append(htmls);
          $("#add_type").modal("hide");
        }else{
          $('#grade'+intentionLevel + " .nullClass").append(html1);
          $("#add_type").modal("hide");
        }
          //删除意向等级条件
       del_condition();
      }
  }


  // 删除意向等级条件
  function del_condition(){
    $(".conditions img").click(function() {
      $(this).parent().siblings(".relationship").remove();
      $(this).parent().parent().remove();
  })
  }

//更改多选下拉框
$(".btn-group.bootstrap-select.show-tick.form-control.selwidth.selwidths").click(function(){
  alert('111');
})



  function fold(obj){

    if($(obj).parent().hasClass('opens')){
        $(obj).parent().siblings().removeClass('nullClassHidden');
        $(obj).parent().siblings().children().removeClass('gradeHidden');
        $(obj).parent().removeClass('opens');
        $(obj).children().attr('src','/public/img/shouqi.png');
    }else{
        $(obj).parent().siblings().addClass('nullClassHidden');
        $(obj).parent().siblings().children().addClass('gradeHidden');
        $(obj).parent().addClass('opens');
        $(obj).children().attr('src','/public/img/zhankai.png');
    }

  }
