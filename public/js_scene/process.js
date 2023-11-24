 //导出记录
 function outexcel(){
   $('#backupscene').modal('hide');
   var nowsceneID = $("#nowsceneID").val();
   window.location.href = "{:url('user/Scenarios/exportexcel')}/sceneId/"+nowsceneID;
 }

   //地图拖动
   var dragpic=-1;
   var dragpicy=-1;
   $("#drop-bg").bind({
       mouseout:function(e){
           e.preventDefault();
           dragpic=-1;
       },
       mousemove:function(e){
         e.preventDefault();
           if(dragpic>=0){
               //e=event?event:window.event;  var wrapdiv = document.getElementById("rowcontent");
               dqsb = e.pageX || (e.clientX );
               dqsby = e.pageY || (e.clientY );
               $("#rowcontent").scrollLeft($("#rowcontent").scrollLeft()+(dragpic-dqsb));
               $("#rowcontent").scrollTop($("#rowcontent").scrollTop()+(dragpicy-dqsby));
               dragpic = dqsb;
               dragpicy = dqsby;
           }
       },
       mousedown:function(e){
         // console.log(e);
         // console.log(e.target.id);
          var targetId = e.target.id;
           if(targetId != "drop-bg"){
             return false;
           }

           dragpic = e.pageX || (e.clientX);
           dragpicy = e.pageY || (e.clientY);
           if (e.stopPropagation){
               // this code is for Mozilla and Opera
               e.stopPropagation();
               e.preventDefault();
           }
       },
       mouseup:function(){
           dragpic=-1;
       }
   });

//全屏
  function fullScreen(obj){
    var pst = $("#fullScreen").css("position");
    if(pst == "relative"){
      $("#fullScreen").css({
        width: "100%",
        height: "100%",
        position: "fixed",
        top: "0",
        right: "0",
        bottom: "0",
        left:"0",
        'z-index':"999",
        });

       $("#rowcontent").css({position: 'relative',overflow: 'scroll',height:' calc(100vh - 75px)'});
       $(".left-process-list").css({'border-right':' 1px solid #ccc',height:' calc(100vh - 80px)'});

       $(obj).find("span").remove();
       $(obj).append('<span class="glyphicon glyphicon-resize-small" aria-hidden="true"></span><span>退出全屏</span>');

    }else{
       $("#fullScreen").attr("style",'position: "relative"');
       $("#myTab").attr("style",'margin-bottom: "10px";width:"auto",margin-left: "0px;"');
       $(obj).find("span").remove();
       $(obj).append('<span class="glyphicon glyphicon-resize-full" aria-hidden="true"></span><span>全屏</span>');

       $("#rowcontent").css({position: 'relative',overflow: 'scroll',height:' calc(100vh - 154px)'});
       $(".left-process-list").css({'border-right':' 1px solid #ccc',height:' calc(100vh - 172px)'});

       var wrapdiv = document.getElementById("spinContainer");
       wrapdiv.scrollLeft = 0;

    }

  }

  var NodeList = []; //当前展示的列表
  function dataStructure(data){
    var leng = data.length;
    if(leng){
      for (var i=0;i<leng;i++) {
        var temp = {};
        temp.key = "node-"+data[i]["id"];
        temp.id = data[i]["id"];
        temp.type = data[i]["type"];
        temp.name = data[i]["name"];
        temp.content = data[i]["content"];
        temp.isDelete = 0;
        temp.pid = data[i]["pid"];
        if(data[i]["pid"] > 0){
          temp.pNode = "node-"+data[i]["pid"];
        }else{
          temp.pNode = null;
        }
        if(data[i]["top"]){
         temp.top = data[i]["top"];
        }else{
          temp.top = 0;
        }
        if(data[i]["left"]){
        temp.left = data[i]["left"];
        }else{
         temp.left = 0;
        }
        if(data[i]["type"] == "Menu"){
          var sondata = data[i]["data"].choices;
          var long = sondata.length;
          var thumb = [];
          for (var j=0;j<long;j++) {
             var son = {};
             son.key = "key-"+ data[i]["id"] + "-" +sondata[j]["id"];
             son.id = sondata[j]["id"];
             son.name = sondata[j]["name"];
             son.Node = "node-"+data[i]["id"];
             son.NodeId = data[i]["id"];
             son.nextNode = sondata[j]["nextNode"];
             son.nextNodeId = sondata[j]["next_flow_id"];
             son.type = sondata[j]["type"];
             thumb.push(son);
           //	thumb["key-"+ data[i]["id"] + "-" +sondata[j]["id"]] = son;
          }
          temp.thumb = thumb;
          //NodeList["node"+data[i]["id"]] = temp;
        }
       // NodeList["node"+data[i]["id"]] = temp;
         NodeList.push(temp);
      }
     // console.log(NodeList);
    }else{
      console.log("没有数据");
    }
  }
  //保存全部数据
  function saveAllData(){
   var sceneId = $("#nowsceneID").val();
   var nowProcessId = $("#nowProcessId").val();
   var flag = 0;//检测有几个根节点.
  // console.log(NodeList);
  // return false;
   var lasting = NodeList.length;
   for (var i=0;i<lasting;i++) {
     //判断，跳转节点必须有父节点
     if(NodeList[i].type == 'WorkTime' && NodeList[i].pNode == null && NodeList[i].id > 0 && NodeList[i].isDelete == 0){
       alert("跳转节点没有连接。");
       return false;
     }
     if((NodeList[i].type == 'Menu') && NodeList[i].thumb.length == 0 && NodeList[i].id > 0 && NodeList[i].isDelete == 0){
       alert("流程节点必须有子节点。");
       return false;
     }
     if((NodeList[i].type == 'Menu') && NodeList[i].pid == 0  && NodeList[i].id > 0 && NodeList[i].isDelete == 0){
       flag = flag + 1;
     }
     var tail = false;//检测是否是以跳转节点结尾.
     if(NodeList[i].type == 'Menu' && NodeList[i].isDelete == 0){
       var thumb = NodeList[i].thumb;
       var long = thumb.length;
       for(var j = 0; j < long; j++){
         if(thumb[j].nextNodeId == null || thumb[j].nextNodeId == ''){
           tail = true;
         }
       }
       if(tail){
         alert("流程节点必须有子节点。且以跳转节点结尾。");
         return false;
       }
     }
   }
   var divnum = $("#drop-bg").find("div");
   var divnum = divnum.length;
//  		console.log(flag);
//  		console.log(divnum);
   if((flag > 1 || flag==0) && divnum > 0){
     alert("要以“流程节点”做为根节点开始，且每个场景节点只能有一个根节点。");
     return false;
   }
     var url = "{:url('user/Scenarios/saveAllNode')}";
      $.ajax({
             url : url,
             type : "post",
             data : {
               'sceneId':sceneId,
               'nowProcessId':nowProcessId,
               'NodeList':NodeList,
             },
             success: function(data){
               //console.log(data);

               var scen_node_id = $("#nowProcessId").val();

               var url = "{:url('user/Scenarios/notelist')}";

               if(scen_node_id){
                 jsPlumb.ready(function(){
                   flowFun.main(url,scen_node_id)
                 });
               }else{
                 flowFun.emptyCanvas();
               }
               if(data.code==0){
                 alert("保存成功。");
               }else{
                 alert("保存失败。");
               }
               //  location.reload();
             },
             error : function() {
               alert(data.msg);
             }
       });
  }

  //获取流程场景
  function getflow(obj){
   var val = $(obj).attr("dataId");
   $("#nowProcessId").val(val);
   $(obj).siblings(".active").removeClass("active");
   $(".talk-list-item").removeClass("active");
   $(obj).addClass("active");
 //	var nowProcessId = $("#nowProcessId").val();
   flowFun.emptyCanvas();
   var url = "{:url('user/Scenarios/notelist')}";
   var scen_node_id = val;
   //jsPlumb.ready(function(){
   flowFun.main(url,scen_node_id)
   //});
  }

  //知识库的搜索
  function getkeyword(){
    var knowledgekw = $('#contactNumber').val();
    if(knowledgekw == "" || knowledgekw == null){
       getKnowledgeList(1);
    }else{
       getKnowledgeList(1,knowledgekw);
    }
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
     $.post("{:url('user/Scenarios/delKnowledge')}",{'id':id},function(data){
         if(data){
           alert(data);
           // window.location.reload();
         }
         $('#knowledge-batch-delete').modal('hide');
         $('#knowledgebase-single-delete').modal('hide');
         getKnowledgeList(1);
     });

  }

  //刷新单个节点
  function getsingle(nowEditId,nodeId){

    var sceneId = $("#nowsceneID").val();
    var nowProcessId = $("#nowProcessId").val();


   var url = "{:url('user/Scenarios/backSingle')}";

    $.ajax({
         url : url,
         type : "post",
         data : {
           'sceneId':sceneId,
           'nowProcessId':nowProcessId,
           'nodeId':nodeId,
         },
         success: function(data){

           //console.log(data);

             if(data.code == 1){
                alert(data.msg);
             }else{

               if(isNaN(nowEditId)){
                 //flowFun.emptyNode(nowEditId);
                 var node = nowEditId;
                 //var node = nowEditId + '-heading';
               }else{
                 var node = "node-"+nowEditId;
               }

               flowFun.emptyNode(node);
               //jsPlumb.empty(node);

               var item = data.data;
               var data = {
                 id: "node-"+item.id,
                 name: item.name,
                 content: item.content,
                 top: item.top,
                 left: item.left,
                 type: "old",
                 key: item.id,
                 nextName:item.next_name,
                 choices: item.data.choices || []
               }
               var template = DataDraw.getTemplate(item)
               $('#drop-bg').append(Mustache.render(template, data));
                DataDraw['addEndpointOf' + item.type](item);
                DataDraw['connectEndpointOf' + item.type](item);
                var lasting = NodeList.length;
                for (var i=0;i<lasting;i++) {
                   var flag = nowEditId;
                   if(NodeList[i].id){
                       flag = "node-"+nowEditId;
                   }
                   if(NodeList[i].key == flag){
                       NodeList[i].key = "node-"+item.id;
                       NodeList[i].id = item.id;
                       NodeList[i].type = item.type;
                       NodeList[i].name = item.name;
                       NodeList[i].content = item.content;
                       NodeList[i].isDelete = 0;
                       NodeList[i].pid = item.pid;
                       if(item.pid > 0){
                         NodeList[i].pNode = "node-"+item.pid;
                       }else{
                         NodeList[i].pNode = null;
                       }
                       if(item.top){
                       NodeList[i].top = item.top;
                       }else{
                       NodeList[i].top = 0;
                       }
                       if(item.left){
                         NodeList[i].left = item.left;
                       }else{
                         NodeList[i].left = 0;
                       }
                       if(item.type == "Menu"){
                         var sondata = item.data.choices;
                         var long = sondata.length;
                           NodeList[i].thumb.splice(0,NodeList[i].thumb);
                           var thumb = [];
                           for (var j=0;j<long;j++) {
                             var son = {};
                             son.key = "key-"+ item.id + "-" +sondata[j]["id"];
                             son.id = sondata[j]["id"];
                             son.name = sondata[j]["name"];
                             son.Node = "node-"+item.id;
                             son.NodeId = item.id;
                             son.nextNode = sondata[j]["nextNode"];
                             son.nextNodeId = sondata[j]["next_flow_id"];
                             son.type = sondata[j]["type"];
                             // console.log(son);
                             thumb.push(son);
                           }
                           //console.log(thumb);
                           NodeList[i].thumb = thumb;
                     //console.log(NodeList[i].thumb);
                       }
                   }
                }

                //如果有父节点,重新连接父节点
                if(item.pid > 0){
                 cont(item.pid,nodeId);
                }
             }
         },
         error : function() {
             alert(data.msg);
         }
     });

  };

  //如果编辑的元素有父节点,重新连接上
  function cont(pre,child){
     var lasting = NodeList.length;
    for (var i=0;i<lasting;i++) {
       if(NodeList[i].id == pre){
           if(NodeList[i].type == "Menu"){
              var thumb = NodeList[i].thumb;
              var thumblong = thumb.length;
              for (var j=0;j<thumblong;j++) {
               if(thumb[j].nextNodeId == child){
                 DataDraw.connectEndpoint(thumb[j].key + '-out', "node-"+child+"-in")
               }
              }
           }
       }
    }
  }

   $(function () {
       $('#spinContainer ul li a').click(function (e) {

         e.preventDefault();  //去掉a标签的锚链接

         var type = $(this).attr('aria-controls');
       //	console.log(type);
         if (type == 'intentionLabel'){
           getLabelList(1); //获取意向标签数据
         }
         else if(type == "knowledgeBase"){
           getKnowledgeList(1);  //获取知识库列表数据
         }
         else if(type == "process"){
           searchdata(1,""); //获取学习的数据
           //获取流程节点列表
           getNoteList();
         }
       });
         var numArr = new Array();
         $('.Idlist').each(function(){
             numArr.push($(this).val());//添加至数组
         });
        if(numArr.length > 0){
             $("#taskItem"+numArr[0]).addClass("active");
             $("#taskItem"+numArr[0]).siblings(".active").removeClass("active");
             $("#nowsceneID").val(numArr[0]);
             // $("#dflearn").css({ 'color': "#fffff", 'background-color': "#03a9f4" });
             $("#dflearn").removeClass("btn-default");
             $("#dflearn").addClass("btn-primary");
             $("#dflearn").siblings(".btn").addClass("btn-default");
             searchdata(1,""); //获取学习的数据
             //获取流程节点列表
             getNoteList();
             gettype8();
             var nowProcessId = $("#nowProcessId").val();
             if(nowProcessId){
               var url = "{:url('user/Scenarios/notelist')}";
               var scen_node_id = nowProcessId;
               jsPlumb.ready(function(){
                 flowFun.main(url,scen_node_id)
               });
             }
          }
        jsPlumb.setContainer('diagramContainer');
        flowFun.getReady();

         // 1.基本参数设置
        var options = {
            type: 'POST',     // 设置表单提交方式
            url: "{:url('user/Scenarios/leadingZip')}",    // 设置表单提交URL,默认为表单Form上action的路径
            dataType: 'json',    // 返回数据类型
            beforeSubmit: function(formData, jqForm, option){    // 表单提交之前的回调函数，一般用户表单验证
                // formData: 数组对象,提交表单时,Form插件会以Ajax方式自动提交这些数据,格式Json数组,形如[{name:userName, value:admin},{name:passWord, value:123}]
                // jqForm: jQuery对象,，封装了表单的元素   
                // options: options对象
             //   var str = $.param(formData);    // name=admin&passWord=123
              //  var dom = jqForm[0];    // 将jqForm转换为DOM对象
               // var name = dom.name.value;    // 访问jqForm的DOM元素
                /* 表单提交前的操作 */
                return true;  // 只要不返回false,表单都会提交 
            },
            success: function(responseText, statusText, xhr, $form){    // 成功后的回调函数(返回数据由responseText获得),
             //	console.log(responseText);
              if (responseText.code == '0') {
                 $('#exampleModal').modal('hide');
               //	window.reload();
                 location.reload();
              }else{
               $('#exampleModal').modal('show');
              }
            },  
            error: function(xhr, status, err) {            
                alert("操作失败!");    // 访问地址失败，或发生异常没有正常返回
            },
            clearForm: true,    // 成功提交后，清除表单填写内容
            resetForm: true    // 成功提交后，重置表单填写内容
        };
       
        // 2.绑定ajaxSubmit()
        $("#leadingfileform").submit(function(){     // 提交表单的id
            $(this).ajaxSubmit(options);
            return false;   //防止表单自动提交
        });
   });


 function downs(obj){
   var id = $(obj).attr('dataid');
   $(obj).bind("contextmenu",function(e){
     if(e.button===2){
       e.preventDefault();
        if(id>0){
          showflowSound(id);  //顯示流程語音界面
        }
      }
   });
 }
   //获取 意向标签 intentionLabel列表
   function getLabelList(page){
     var serial_number = page;
     var sceneId = $("#nowsceneID").val();
     //取默认等级
     backdefault(sceneId);
     var url = "{:url('user/Scenarios/getLabelList')}";
     $.ajax({
             url : url,
             dataType : "json",
             type : "post",
             data : {'page':page,'sceneId':sceneId},
             success: function(data){
               var total = data.data.total;
               var Nowpage = data.data.Nowpage;
               var page = data.data.page;
               var Nowpage = parseInt(Nowpage);
               var data = data.data.list;
                if(data.length > 0){
                     $('.datatips_intent').hide();
                     $("#intentionlist").find("tr").remove();
                     for(var i=0;i<data.length;i++){
                       var id = data[i].id;
                       var name = data[i].name;
                       var level = data[i].level;
                       var type = data[i].type;
                       var rule = data[i].rule;
                       var sort = data[i].sort;
                       var status = data[i].status;
                       var create_time = data[i].create_time;
                       var update_time = data[i].update_time;
                       var string = '<tr class="itemId'+id+'" alt="'+id+'">'
                         +'<td class="text-center">  <input type="checkbox" name="" value="'+id+'" class="check_label" onclick="check_label_thing();"></td>'
                         +'<td class="text-center">'+((serial_number-1)*10+(i+1))+'</td>'
                         +'<td class="text-center">'+name+'</td>'
                         +'<td class="text-center">'+level+'</td>'
                         +'<td class="text-center">';
                           //待处理  单个删除弹框在newRule.html里面
                         string += '<a href="javascript:void(0);" onclick="editLabel('+id+');">编辑</a>&nbsp;&nbsp;'
                         +'<a style="cursor: pointer;" data-toggle="modal" data-target="#rule-single-delete" onclick="delrulesinger('+id+');">删除</a>'
                         +'</td>';
                      string += '</tr>';
                       $("#intentionlist").append(string);
                     }
                     var prepage = Nowpage-1;
                     var nextpage = Nowpage+1;
                     var str = '<div class="row">'
                     +'<div class="col-sm-7 text-left">'
                     // +'<table class="table table-bordered table-hover" style="margin-bottom: 0px; ">'
                     // +'<tbody><tr>'
                     // +'<td class="text-center l-totaltext" colspan="2">意向标签总数量：'
                     // +' <span class="l-total">'+total+'</span></td>'
                     // +'<td class=" ">'
                     //+'<span class="pull-left">意向标签总数量：</span><span class="l-total pull-left">'+total+'</span>'
                     +'<span class="pull-left"><input type="checkbox" id="check_all" onclick="check_all_thing();">全选（已选中<span id="check_all_num" data-page="'+serial_number+'" data-total="'+total+'">0</span>条意向标签）</span>'

                     +'<div class="pull-left"><span class="intention-label pull-left">以上规则均不满足时，将客户意向标签设置为<span id="defaultlevel">C级(明确拒绝)</span></span>'
                     +'<input type="hidden" id="dflevelNum" value="" />'
                     +'<span class="user-level-eidt pull-left" id="defaulttype" onclick="showdefault(this);">编辑</span></div>'
                     // +'</td>'
                     // +'</tr> '
                     // +'</tbody></table>'
                     +'</div>'
                     +'<div class="col-sm-4 l-text-right">'
                     +'<ul class="pagination">';
                     if(Nowpage == 1){
                       str += '<li id="prevbtn" class="disabled"><span>«</span></li> ';
                     }else{
                       str += '<li><a href="javascript:void(0);" onclick="getLabelList('+prepage+');"><span>«</span></a></li> ';
                     }
                     if(page > 10){
                       if(Nowpage < 7){
                         for(var i=0;i<page;i++){
                           var nownum = i+1;
                           if(nownum < 9){
                              if(nownum == Nowpage){
                                str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li> ';
                              }else{
                                str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li> ';
                              }
                           }
                           if(nownum == 9 && nownum != Nowpage){
                              str += '<li class="disabled"><span>...</span></li>';
                           }else if(nownum == 9){
                             str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li> ';
                           }
                             if(nownum > (page-2)){
                               if(nownum == Nowpage){
                                  str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li> ';
                                }else{
                                  str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li> ';
                                }
                             }
                          }
                       }else if(Nowpage > 6 && Nowpage < (page-6)){
                         for(var i=0;i<page;i++){
                           var nownum = i+1;
                           var Nowpage = parseInt(Nowpage);
                           if(nownum < 3){
                             str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li> ';
                           }
                           if((nownum > Nowpage-5) && (nownum < Nowpage+5)){
                                    if(nownum == (Nowpage-4)){
                                       str += '<li class="disabled"><span>...</span></li>';
                                    }
                                      if(nownum > (Nowpage-4) && nownum < Nowpage){
                                        str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li>';
                                      }
                                      if(nownum == Nowpage){
                                      str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li>';
                                      }
                                      if(nownum < (Nowpage + 4) && nownum > Nowpage){
                                       str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li>';
                                      }
                                      if(nownum == (Nowpage + 4)){
                                      str += '<li class="disabled"><span>...</span></li>';
                                      }
                            }
                          if(nownum > (page-2)){
                            var Nowpage = parseInt(Nowpage);
                            if(nownum == Nowpage){
                                  str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li>';
                              }else{
                                 str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li> ';
                              }
                             }
                          }
                       }else{
                         for(var i=0;i<page;i++){
                           var nownum = i+1;
                           if(nownum<3){
                             str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li>';
                           }
                           if(nownum == (page-10) && nownum != Nowpage){
                             str += '<li class="disabled"><span>...</span></li>';
                           }else if(nownum == (page-10) && nownum == Nowpage){
                             str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li>';
                           }
                           if(nownum > (page-10)){
                             if(nownum == Nowpage){
                               str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li> ';
                             }else{
                               str += '<li ><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+'</a></li>';
                             }
                           }
                         }
                       }
                     }else{
                        for(var i=0;i<page;i++){
                          var nownum = i+1;
                          if(nownum == Nowpage){
                            str += '<li class="active"><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li> ';
                          }else{
                            str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nownum+');">'+nownum+' </a></li> ';
                          }
                        }
                     }

                     if(Nowpage == page){
                       str += '<li id="prevbtn" class="disabled"><span>»</span></li> ';
                     }else{
                       str += '<li><a href="javascript:void(0);" onclick="getLabelList('+nextpage+');"><span>»</span></a></li>';
                     }
                     str += '</ul>'
                     +'</div>'
                     +'</div>'
                     $("#intentionpage").find("div").remove();
                     $("#intentionpage").append(str);

                     //获取选中框隐藏的选中状态
                       var check_state = $('.check_labels').attr('data-page');
                       if(check_state == page){
                         $('.check_labels').click();
                       }else{
                         $('.check_labels').prop("checked",false);
                       }
                       if(check_state == total){
                         $('#check_all').click();
                       }

                    }else{
                       $("#intentionpage").find("div").remove();
                       $("#intentionlist").find("tr").remove();
                     // 	alert('没有数据。');
                    }
             },
             error : function() {
               $('.datatips_intent').show();
               //alert('获取页面列表失败。');
             }
       });
  }

    //获取知识库列表
    //getKnowledgeList(1);
    function getKnowledgeList(page,knowledgekw){
       var sceneId = $("#nowsceneID").val();
       var nowProcessId = $("#nowProcessId").val();
       var serial_number = page;
       var url = "{:url('user/Scenarios/getKnowledgeList')}";
       $.ajax({
               url : url,
               dataType : "json",
               type : "post",
               data : {'page':page,'sceneId':sceneId,"processId":nowProcessId,"keyword":knowledgekw},
               success: function(data){
                 console.log(data);
                 var total = data.data.total;
                 var Nowpage = data.data.Nowpage;
                 var page = data.data.page;
                 var Nowpage = parseInt(Nowpage);
                 var data = data.data.list;
                 // var usercheck = [];
                 // $.each(data.data.knowledge_allid, function(index, object){
            //        usercheck.push(object.id);
            //     });
            //     usercheck = usercheck.join(',');
            //     $('.cancelkn').attr('data-id',usercheck);

                  if(data.length > 0){
                       $('.datatips_know').hide();
                       $("#knowledgelist").find("tr").remove();

                       for(var i=0;i<data.length;i++){
                         var id = data[i].id;
                         var sequence = data[i].sequence;
                         var name = data[i].name;
                         var keyword = data[i].keyword;
                         var breaks = data[i].break;
                         var type = data[i].type;
                         var action = data[i].action;
                         var action_id = data[i].action_id;
                         var intention = data[i].intention;
                         var update_time = data[i].update_time;
                         var knum = data[i].knum;
                         var content = data[i].content;
                         var is_default = data[i].is_default;
                         var string = '<tr class="itemId'+id+'" alt="'+id+'">';
                         if(is_default == 0){
                           string += '<td class="text-center"><input type="checkbox" name="knowcheck" value="'+id+'" disabled="disabled"></td>'
                         }else{
                           string += '<td class="text-center"><input type="checkbox" name="knowcheck" value="'+id+'" class="check_know" onclick="check_knowthing();"></td>';

                         }
                         string += '<td class="text-center">'+((serial_number-1)*10+(i+1))+'</td>'
                           +'<td class="text-center">'+name+'</td>'
                           +'<td class="text-center">'+keyword+'</td>'
                           +'<td class="text-center">'+knum+'</td>'
                           +'<td class="text-center">'+update_time+'</td>'
                           +'<td class="text-center">';
                           string += '<a href="javascript:void(0);" onclick="showThink('+id+');">编辑</a>&nbsp;&nbsp;';
                           if (is_default == 0){

                             if (type <8){
                               string += '<a href="javascript:void(0);"  style="color:gray;cursor: no-drop;">录音</a>&nbsp;&nbsp;';
                             }
                             else{
                               string += '<a href="javascript:void(0);" onclick="showSound('+id+');">录音</a>&nbsp;&nbsp;';
                             }
                             string += '<a href="javascript:void(0);" style="color:gray;cursor: no-drop;">删除</a>&nbsp;&nbsp;';
                           }
                           else{
                             //已处理 knowledgebase-single-delete弹框在thinktank.html文件中

                             // onclick="delKnowledge('+id+','+is_default+');"
                             string += '<a href="javascript:void(0);" onclick="showSound('+id+');">录音</a>&nbsp;&nbsp;';
                               string += '<a style="cursor: pointer;" data-toggle="modal"  data-target="#knowledgebase-single-delete" onclick="delKnowledgesinger('+id+');">删除</a>&nbsp;&nbsp;';
                           }
                           string += '</td>';
                           string += '</tr>';
                         $("#knowledgelist").append(string);
                       }

                       var prepage = Nowpage-1;
                       var nextpage = Nowpage+1;
                       var str = '<div class="row">'
                       +'<div class="col-sm-4 text-left">'
                       // +'<table class="table table-bordered table-hover" style="margin-bottom: 0px; ">'
                       // +'<tbody><tr>'
                       // +'<td class=" l-totaltext"  colspan="3">知识库总数量：'
                       // +' <span class="l-total">'+total+'</span></td>'
                       // +'<td class="text-center l-total">'
                       // +'</td>'
                       // +'</tr> '
                       // +'</tbody></table>'
                        +'<input type="checkbox" id="checkall" onclick="checkall_thing();">全选（已选中<span id="checkall_num" data-page="'+serial_number+'" data-total="'+total+'">0</span>条知识库）'
                       // +'知识库总数量：<span class="l-total">'+total+'</span>'
                       +'</div>'
                       +'<div class="col-sm-7 l-text-right">'
                       +'<ul class="pagination">';
                       if(Nowpage == 1){
                         str += '<li id="prevbtn" class="disabled"><span>«</span></li> ';
                       }else{
                         str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+prepage+');"><span>«</span></a></li> ';
                       }
                       if(page > 10){
                         if(Nowpage < 7){
                           for(var i=0;i<page;i++){
                             var nownum = i+1;
                             if(nownum < 9){
                                if(nownum == Nowpage){
                                  str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li> ';
                                }else{
                                  str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li> ';
                                }
                             }
                             if(nownum == 9 && nownum != Nowpage){
                                str += '<li class="disabled"><span>...</span></li>';
                             }else if(nownum == 9){
                               str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li> ';
                             }
                               if(nownum > (page-2)){
                                 if(nownum == Nowpage){
                                    str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li> ';
                                  }else{
                                    str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li> ';
                                  }
                               }
                            }
                         }else if(Nowpage > 6 && Nowpage < (page-6)){
                           for(var i=0;i<page;i++){
                             var nownum = i+1;
                             var Nowpage = parseInt(Nowpage);
                             if(nownum < 3){
                               str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li> ';
                             }
                             if((nownum > Nowpage-5) && (nownum < Nowpage+5)){
                                if(nownum == (Nowpage-4)){
                                   str += '<li class="disabled"><span>...</span></li>';
                                }
                                  if(nownum > (Nowpage-4) && nownum < Nowpage){
                                    str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li>';
                                  }
                                  if(nownum == Nowpage){
                                  str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li>';
                                  }
                                  if(nownum < (Nowpage + 4) && nownum > Nowpage){
                                   str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li>';
                                  }
                                  if(nownum == (Nowpage + 4)){
                                  str += '<li class="disabled"><span>...</span></li>';
                                  }
                              }
                            if(nownum > (page-2)){
                              var Nowpage = parseInt(Nowpage);
                              if(nownum == Nowpage){
                                    str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li>';
                                }else{
                                   str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li> ';
                                }
                               }
                            }
                         }else{
                           for(var i=0;i<page;i++){
                             var nownum = i+1;
                             if(nownum<3){
                               str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li>';
                             }
                             if(nownum == (page-10) && nownum != Nowpage){
                               str += '<li class="disabled"><span>...</span></li>';
                             }else if(nownum == (page-10) && nownum == Nowpage){
                               str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li>';
                             }
                             if(nownum > (page-10)){
                               if(nownum == Nowpage){
                                 str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li> ';
                               }else{
                                 str += '<li ><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+'</a></li>';
                               }
                             }
                           }
                         }
                       }else{
                          for(var i=0;i<page;i++){
                            var nownum = i+1;
                            if(nownum == Nowpage){
                              str += '<li class="active"><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li> ';
                            }else{
                              str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nownum+');">'+nownum+' </a></li> ';
                            }
                          }
                       }

                       if(Nowpage == page){
                         str += '<li id="prevbtn" class="disabled"><span>»</span></li> ';
                       }else{
                         str += '<li><a href="javascript:void(0);" onclick="getKnowledgeList('+nextpage+');"><span>»</span></a></li>';
                       }
                       str += '</ul>'
                       +'</div>'
                       +'</div>'
                       $("#knowledgepage").find("div").remove();
                       $("#knowledgepage").append(str);

                       //获取选中框隐藏的选中状态
                       var check_state = $('.check_knowledgebase').attr('data-page');
                       if(check_state == page){
                         $('.check_knowledgebase').click();
                       }else{
                         $('.check_knowledgebase').prop("checked",false);
                       }
                       if(check_state == total){
                         $('#checkall').click();
                       }
                      }
                      else{
                         $("#knowledgepage").find("div").remove();
                         $("#knowledgelist").find("tr").remove();
                       // 	alert('没有数据。');
                      }


               },
               error : function() {
                 $('.datatips_know').show();
                 //alert('获取页面列表失败。');
               }
         });
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

 //意向标签选中框事件
 function check_all_thing(){
   if($('#check_all').is(':checked')){
     $('.check_labels').prop("checked",true);
     $('.check_label').prop("checked",true);
     $('#check_all_num').text($('#check_all_num').attr('data-total'));
     $('.check_labels').attr('data-page',$('#check_all_num').attr('data-total'));
   }else{
     $('.check_labels').prop("checked",false);
     $('.check_label').prop("checked",false);
     $('#check_all_num').text(0);
     $('.check_labels').attr('data-page',0);
   }
 }
 function check_labels_thing(){
   if($('.check_labels').is(':checked')) {
     $('.check_label').prop("checked",true);
     $('#check_all_num').text($('.check_label:checked').length);
     $('.check_labels').attr('data-page',$('#check_all_num').attr('data-page'));
   }else{
     $('#check_all').prop("checked",false);
     $('.check_label').prop("checked",false);
     $('#check_all_num').text(0);
     $('.check_labels').attr('data-page',0);
   }
 }
 function check_label_thing(){
   if (!$(".check_label").checked) {
     $('.check_labels').prop("checked",false);
     $('#check_all').prop("checked",false);
     $('.check_labels').attr('data-page',0);
   }
   var check_know_pagenum = $('.check_label').length;
   var check_know_num = $('.check_label:checked').length;
   if(check_know_pagenum == check_know_num){
     $('.check_labels').prop("checked",true);
     $('.check_labels').attr('data-page',$('#check_all_num').attr('data-page'));
   }
   $('#check_all_num').text(check_know_num);
 }

 //批量删除意向标签
 function delLabelall(){
   var del_id = [];
   if(!$("#check_all").checked){
     $.each($('.check_label'), function(index, object){

       if($(object).prop("checked") == true){
         del_id.push($(object).val());
       }

     })
     var id = del_id.join(',');
   }else{
     //全选全部删除(待处理)
   }
   if(id == ''){
     alert("至少选择一条！");
     $('.cancellabel').removeAttr('data-target');
   }else{
     $('#intentionlabel-delete').attr('data-id',id);
     $('.cancellabel').attr('data-target','#intentionlabel-delete');
   }
 }
 //单独删除意向标签
 function delrulesinger(id){
   $("#rule-single-delete").attr('data-id',id);
 }

 //删除意向标签

  function delLabel(type){
   if(type == 2){
       //单个删除
       var id = $('#rule-single-delete').attr('data-id');
     }else{
       //批量删除
       var id = $('#intentionlabel-delete').attr('data-id');
       id = id.split(",");

     }
     console.log(id);
     $.post("{:url('user/Scenarios/delLabel')}",{'id':id},function(data){
         if(data){
           alert(data);
           // window.location.reload();
         }
         $('#intentionlabel-delete').modal('hide');
         $('#rule-single-delete').modal('hide');
         getLabelList(1);
     });
  }

   //去取回默认等级
  function backdefault(sceneId){
    var url = "{:url('user/Scenarios/backdefault')}";
    $.ajax({
           url : url,
           dataType : "json",
           type : "post",
           data : {'sceneId':sceneId},
           success: function(data){
               //console.log(data);
               if (data.code == 0) {
                  // alert(data.msg);
                   $('#defaultlevel').text(data.data.level);
                   $('#dflevelNum').val(data.data.levelNum);
               }else{
                  console.log(data.msg);
               }
           },
           error : function() {
             //alert('获取页面列表失败。');
           }
     });
  }



  function submitAuditing(id){
    var url = "{:url('user/Scenarios/setAuditing')}";
      $.ajax({
             url : url,
             dataType : "json",
             type : "post",
             data : {'id':id},
             success: function(data){
               if (data.code) {
              alert(data.msg);
           }else{
              location.reload();
           }
             },
             error : function() {
               alert('失败。');
             }
       });

  }

   //获取话术场景
   function getscenarios(obj,id){
     $(obj).addClass("active").siblings().removeClass("active");
     $("#nowsceneID").val(id);
     // $("#dflearn").css({ 'color': "#fffff", 'background-color': "#03a9f4" });
     $("#dflearn").removeClass("btn-default");
     $("#dflearn").addClass("btn-primary");
     $("#dflearn").siblings(".btn").addClass("btn-default");
     searchdata(1,""); //获取学习的数据
     $('.check_learns').removeAttr("checked");
     getLabelList(1); //获取意向标签
     $('.check_labels').removeAttr("checked");
     getNoteList();//获取话术节点
     getKnowledgeList(1); //获取知识列表
     $('.check_knowledgebase').removeAttr("checked");
     //获取知识库类型为8的记录
     gettype8();
   }

   //获取学习列表
   function getLearning(obj){
     var val = $(obj).attr("data-type");
     $(obj).removeClass("btn-default");
     $(obj).addClass("btn-primary");
     $(obj).siblings(".btn").removeClass("btn-primary");
     $(obj).siblings(".btn").addClass("btn-default");
     page = 1;
     searchdata(page,val);
   }

   //获取学习列表
   var page = 1;
   function searchdata(page,type){
     var sceneId = $("#nowsceneID").val();
     var serial_number = page;
     var url = "{:url('user/scenarios/learning')}";
     $.ajax({
             url : url,
             dataType : "json",
             type : "post",
             data : {'page':page,'sceneId':sceneId,'type':type},
             success: function(data){
               var total = data.data.total;
               var Nowpage = data.data.Nowpage;
               var page = data.data.page;
               var Nowpage = parseInt(Nowpage);
               var data = data.data.list;
                if(data.length > 0){
                     $('.datatips_learn').hide();
                     $("#tablelearninglist").find("tr").remove();
                     for(var i=0;i<data.length;i++){
                       var id = data[i].id;
                       var uid = data[i].member_id;
                       var phone = data[i].phone;
                       var call_id = data[i].call_id;
                       var content = data[i].content;
                       var create_time = data[i].create_time;
                       var status = data[i].status;
                       var strstatus = "待学习";
                       if(status == "1"){
                           strstatus = "已学习";
                       }else if(status == "2"){
                           strstatus = "忽略";
                       }
                       var string = '<tr class="itemId'+id+'" alt="'+id+'">'
                         +'<td class="text-center">  <input type="checkbox" name="" value="'+id+'" class="check_learn" onclick="check_learnthing();"></td>'
                         +'<td class="text-center">'+((serial_number-1)*10+(i+1))+'</td>'
                         +'<td class="text-center">'+content+'</td>'
                         +'<td class="text-center">'+phone+'</td>'
                         +'<td class="text-center">'+strstatus+'</td>'
                         +'<td class="text-center">'+create_time+'</td>'
                         +'<td class="text-center">';
                         string += '<a href="javascript:void(0);" onclick="gotoDetail('+uid+');">通话记录</a>&nbsp;&nbsp;'
                             //待处理onclick="del('+id+');"
                         string += '<a href="javascript:void(0);" onclick="changeStatus('+id+',1);">学习</a>&nbsp;&nbsp;'
                         +'<a href="javascript:void(0);" onclick="changeStatus('+id+',2);">忽略</a>&nbsp;&nbsp;'
                         +'<a href="javascript:void(0);" data-toggle="modal" data-target="#pbl-single-delete" >删除</a>'
                         +'</td>';
                      string += '</tr>';
                       $("#tablelearninglist").append(string);
                     }

                     var prepage = Nowpage-1;
                     var nextpage = Nowpage+1;
                     var str = '<div class="row">'
                     +'<div class="col-sm-3 text-left">'

                     // +'<table class="table table-bordered table-hover" style="margin-bottom: 0px; ">'
                     // +'<tbody><tr>'
                     // +'<td class="text-center">总数：'
                     // +'</td>'
                     // +'<td class="text-center">'+total+'&nbsp;'
                     // +'</td>'
                     // +'</tr> '
                     // +'</tbody></table>'
                     +'<input type="checkbox" id="check_alllearn" onclick="check_alllearnthing();">全选（已选中<span id="check_alllearn_num" data-page="'+serial_number+'" data-total="'+total+'">0</span>问题学习）'
                     // +'知识库总数量：<span class="l-total">'+total+'</span>'
                     +'</div>'


                     +'<div class="col-sm-9 text-right">'
                     +'<ul class="pagination">';
                     if(Nowpage == 1){
                       str += '<li id="prevbtn" class="disabled"><span>«</span></li> ';
                     }else{
                       str += '<li><a href="javascript:void(0);" onclick="searchdata('+prepage+','+type+');"><span>«</span></a></li> ';
                     }
                     if(page > 10){
                       if(Nowpage < 7){
                         for(var i=0;i<page;i++){
                           var nownum = i+1;
                           if(nownum < 9){
                              if(nownum == Nowpage){
                                str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li> ';
                              }else{
                                str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li> ';
                              }
                           }
                           if(nownum == 9 && nownum != Nowpage){
                              str += '<li class="disabled"><span>...</span></li>';
                           }else if(nownum == 9){
                             str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li> ';
                           }
                                 if(nownum > (page-2)){
                                   if(nownum == Nowpage){
                                str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li> ';
                              }else{
                                str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li> ';
                              }
                                 }

                          }
                       }else if(Nowpage > 6 && Nowpage < (page-6)){
                         for(var i=0;i<page;i++){
                           var nownum = i+1;
                           var Nowpage = parseInt(Nowpage);
                           if(nownum < 3){
                             str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li> ';
                           }

                           if((nownum > Nowpage-5) && (nownum < Nowpage+5)){
                                    if(nownum == (Nowpage-4)){
                                       str += '<li class="disabled"><span>...</span></li>';
                                    }
                                      if(nownum > (Nowpage-4) && nownum < Nowpage){
                                        str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li>';
                                      }
                                      if(nownum == Nowpage){
                                      str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li>';
                                      }
                                      if(nownum < (Nowpage + 4) && nownum > Nowpage){
                                       str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li>';
                                      }
                                      if(nownum == (Nowpage + 4)){
                                      str += '<li class="disabled"><span>...</span></li>';
                                      }
                            }

                          if(nownum > (page-2)){
                            var Nowpage = parseInt(Nowpage);
                            if(nownum == Nowpage){
                                  str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li>';
                              }else{
                                 str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li> ';
                              }
                             }
                          }
                       }else{
                         for(var i=0;i<page;i++){
                           var nownum = i+1;
                           if(nownum<3){
                             str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li>';
                           }
                           if(nownum == (page-10) && nownum != Nowpage){
                             str += '<li class="disabled"><span>...</span></li>';
                           }else if(nownum == (page-10) && nownum == Nowpage){
                             str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li>';
                           }
                           if(nownum > (page-10)){
                             if(nownum == Nowpage){
                               str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li> ';
                             }else{
                               str += '<li ><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+'</a></li>';
                             }
                           }
                         }
                       }
                     }else{
                        for(var i=0;i<page;i++){
                          var nownum = i+1;
                          if(nownum == Nowpage){
                            str += '<li class="active"><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li> ';
                          }else{
                            str += '<li><a href="javascript:void(0);" onclick="searchdata('+nownum+','+type+');">'+nownum+' </a></li> ';
                          }
                        }
                     }
                     if(Nowpage == page){
                       str += '<li id="prevbtn" class="disabled"><span>»</span></li> ';
                     }else{
                       str += '<li><a href="javascript:void(0);" onclick="searchdata('+nextpage+','+type+');"><span>»</span></a></li>';
                     }
                     str += '</ul>'
                     +'</div>'
                     +'</div>'
                     $("#modalbody").find("div").remove();
                     $("#modalbody").append(str);

                     //获取选中框隐藏的选中状态
                     var check_state = $('.check_learns').attr('data-page');
                     if(check_state == page){
                       $('.check_learns').click();
                     }else{
                       $('.check_learns').prop("checked",false);
                     }
                     if(check_state == total){
                       $('#check_alllearn').click();
                     }

                    } else{
                       $("#modalbody").find("div").remove();
                       $("#tablelearninglist").find("tr").remove();
                     // 	alert('没有数据。');
                    }

             },
             error : function() {
               $('.datatips_learn').show();
               //alert('获取页面列表失败。');
             }
       });
  }

  //改变学习状态
  function changeStatus(id,status){
     var ids;
     if(id){
       var Ids=[];
       Ids.push(id);
       ids = Ids;
     }else{
         var IdsVal = [];
           var roleids = document.getElementsByName("adminids");
         for ( var j = 0; j < roleids.length; j++) {
           if (roleids.item(j).checked == true) {
             IdsVal.push(roleids.item(j).value);
           }
         }
         ids = IdsVal;
     }

     if(!ids.length){
     alert("至少选择一条。");
       return false;
   }

     var url = "{:url('user/Scenarios/changeStatus')}";
     $.ajax({
           url : url,
           dataType : "json",
           type : "post",
           data : {'Ids':ids,'status':status},
           success: function(data){
             if (data.code) {
                 alert(data.msg);
             }else{
               searchdata(page,0);
             }
           },
           error : function() {
             alert('修改失败。');
           }
     });


  }

 //问题学习选中框事件
 function check_alllearnthing(){
   if($('#check_alllearn').is(':checked')){
     $('.check_learns').prop("checked",true);
     $('.check_learn').prop("checked",true);
     $('#check_alllearn_num').text($('#check_alllearn_num').attr('data-total'));
     $('.check_learns').attr('data-page',$('#check_alllearn_num').attr('data-total'));
   }else{
     $('.check_learns').prop("checked",false);
     $('.check_learn').prop("checked",false);
     $('#check_alllearn_num').text(0);
     $('.check_learns').attr('data-page',0);
   }
 }
 function check_learnsthing(){
   if($('.check_learns').is(':checked')) {
     $('.check_learn').prop("checked",true);
     $('#check_alllearn_num').text($('.check_learn:checked').length);
     $('.check_learns').attr('data-page',$('#check_alllearn_num').attr('data-page'));
   }else{
     $('#check_alllearn').prop("checked",false);
     $('.check_learn').prop("checked",false);
     $('#check_alllearn_num').text(0);
     $('.check_learns').attr('data-page',0);
   }
 }
 function check_learnthing(){
   if(!$(".check_learn").checked) {
     $('.check_learns').prop("checked",false);
     $('#check_alllearn').prop("checked",false);
     $('.check_learns').attr('data-page',0);
   }
   var check_know_pagenum = $('.check_learn').length;
   var check_know_num = $('.check_learn:checked').length;
   if(check_know_pagenum == check_know_num){
     $('.check_learns').prop("checked",true);
     $('.check_learns').attr('data-page',$('#check_alllearn_num').attr('data-page'));
   }
   $('#check_alllearn_num').text(check_know_num);
 }

 //批量删除学习
 function dellearn(){
   var del_id = [];
   $.each($('.check_learn'), function(index, object){

     if($(object).prop("checked") == true){
       del_id.push($(object).val());
     }

   })
   var id = del_id.join(',');
   if(id == ''){
     alert("至少选择一条！");
     $('.cancellearn').removeAttr('data-target');
   }else{
     $('#pbl-delete').attr('data-id',id);
     $('.cancellearn').attr('data-target','#pbl-delete');
   }
 }

  //删除学习
  function del(id){

   // var r=confirm('确认删除?');
   // 		if (!r)
   // 				return;

   // 		var ids=[];
   // 		if(id){
   // 			ids.push(id);
   // 		}else{

   // 			var roleids = document.getElementsByName("customerIds");
   // 			for ( var j = 0; j < roleids.length; j++) {
   // 					if (roleids.item(j).checked == true) {
   // 						ids.push(roleids.item(j).value);
   // 					}
   // 			}
   // 		}

   // 		if(!ids.length){
   // 			alert("至少选择一条。");
   // 			return false;
   // 		}
     $.post("{:url('user/Scenarios/delLearning')}",{'id':id},function(data){
         if(data){
           alert(data);
         }else{
           searchdata(page,0);
         }
     });
  }

   //删除
   function delete_sc(id){

      $('#delscene').attr('data-id',id);
   }
   function delScenarios(){
     var id = $('#delscene').attr('data-id');

      $.post("{:url('delScenarios')}",{'id':id},function(data){
         if(data){
           alert(data);
         }else{
           window.location.href=window.location.href;
         }
     });
     $('#delscene').hide();
   }

   // 恢复默认配置
   function recovery(){

     var r=confirm('是否确定恢复默认配置?');
       if (!r)
           return;

      var sceneId = $("#nowsceneID").val();

      $.post("{:url('user/Scenarios/recovery')}",{'sceneId':sceneId},function(data){
       if(data){
         if (data.code) {
             alert(data.msg);
         }else{
           getLabelList(0);
         }
       }else{
         window.location.href=window.location.href;
       }
      });
   }

   //获取类型为8的记录,绑到“指定未回复"
   function gettype8(){

     var sceneId = $("#nowsceneID").val();

     var href = "{:url('user/Scenarios/getKnlgEight')}";

     $.ajax({
       url : href,
       dataType : "json",
       type : "post",
       data : {
         'sceneId':sceneId,
       },
       success: function(data){
         $("#eightList").empty();
         var string = '';
         $("#eightList").append("<option value=''>请选择用户未说话的话术</option> ");
         if(data.code == 0){
           var item = data.data;
           var leng = item.length;

           for(var i=0;i<leng;i++){
             // 0 普通 1业务问题 2肯定3 否定 4拒绝 5中性  6 未识别 7重复 8用户未说话 9无法回答 10 无法回答次数
             var name = item[i]["name"];
             var kid = item[i]["id"];
             string += '<option value="'+kid+'">'+name+'</option>';
           }
         }
         if (string){
           $('#eightList').append(string);
         }
       },
       error : function() {
         alert('获取信息失败。');
       }
     });
   }


 /////////////////////////////////
 //note列表
 function getNoteList(){
    var flowlistnode = '<div class="talk-list-item" ><div class="talklist-item-content "><div class="node">暂无场景节点</div></div></div>';
    var onelistnode = '<div class="talk-list-item" dataid="90" ><div class="talklist-item-content"><div class="node">暂无场景节点</div></div></div>';
    var oneliststate = 0;
    var flowliststate = 0;
    var sceneId = $("#nowsceneID").val();
    var href = "{:url('user/scenarios/getNoteList')}";
    $.ajax({
        type: "POST",
        dataType:'json',
        url: href,
        cache: false,
        data: {"sceneId":sceneId},
        success: function(data) {

          // console.log(data);
           $(".flowlist").find("div").remove();
            $(".onelist").find("div").remove();

           $("#nowProcessId").val("");
             // console.log(data);
           if (data.code == 0) {
             var data = data.data;
             if(data.length > 0){

               for(var i=0;i<data.length;i++){

                  if(i==0){

                    $("#nowProcessId").val(data[i].id);

                    var url = "{:url('user/Scenarios/notelist')}";
                    var scen_node_id = data[i].id;
                    //console.log(scen_node_id);
                    if(scen_node_id){
                         flowFun.main(url,scen_node_id)
                    }else{
                       flowFun.emptyCanvas();
                    }
                    var string = '<div class="talk-list-item active" dataId="'+data[i].id+'" onclick="getflow(this);">';

                  }else{

                    var string = '<div class="talk-list-item" dataId="'+data[i].id+'" onclick="getflow(this);">';
                  }

                   string += '<div class="control-icon">'
                        +'<span class="anticon glyphicon glyphicon-edit" onclick="newflowModal('+data[i].id+');" aria-hidden="true"></span>'
                        +'<span class="anticon glyphicon glyphicon-trash" data-toggle="modal" data-target="#l-delnote" onclick="delete_flow('+data[i].id+');" aria-hidden="true"></span>'
                        +'</div>'
                        +'<div class="talklist-item-content"><div>'+data[i].name+'</div></div>'
                        +'</div>';

                  if(data[i].type > 0){
                    oneliststate = oneliststate + 1;
                    $(".onelist").append(string);
                  }else{
                    flowliststate = flowliststate + 1;
                    $(".flowlist").append(string);
                  }
               }

               if(flowliststate == 0){ $(".flowlist").html(flowlistnode); }
               if(oneliststate == 0){ $(".onelist").html(onelistnode); }
             }else{
               // console.log(data.length);

               $(".flowlist").html(flowlistnode);
               $(".onelist").html(onelistnode);
             }

           }else{

             // console.log(data.msg);
              //alert(data.msg);
              flowFun.emptyCanvas();

               $(".flowlist").html(flowlistnode);
               $(".onelist").html(onelistnode);
           }

        },
        error: function(data) {
          alert("提交失败");
        }
    })
 }

 //删除学习
 function delete_flow(id){
   $('#l-delnote').attr('data-id',id);
 }
 function delflowNote(){

  var id = $('#l-delnote').attr('data-id');

   $.post("{:url('user/Scenarios/delflowNote')}",{'id':id},function(data){
       if(data){
         alert(data);
       }else{
         getNoteList();
       }
   });

   $('#l-delnote').modal('hide');
 }

//双击
 function gotocommonNode(obj){

   var type = $(obj).attr("datatype");
   $("#commonNodeType").val(type);

   $('#CustomList').find("div").remove();
   $("#Othersettings").prop("checked",false);

   $("#nodeLabel").val("");
   $("#cpauseTime").val("3000");

   $('#fixedList').find("div").remove();

   $("#smsInfo").prop("checked",false);
   $('#smstpl').css("display","none");
   $("#smsList").val("");
   getsmsTpl('smsList');  //获取短信模板
   if(type == "new"){

     getdefaultMt("new");  //获取默认答法的

     $("#cNodeName").val("");
     $("#AIStechnique").val("");
     $("#tc_id").val("");

     var pid = $(obj).parent().attr("id");
     $("#commonNodeId").val(pid);
     $('#commonNode').modal('show');

     $('#answeredlist').css("display","none");
     $("#eightList").val("");
     $("#unanswered").prop("checked",false);

     $('#agentbox').css("display","none");
     $("#agentList").val("");
     $("#agent").prop("checked",false);

   }
   else{

       var dataid = $(obj).attr("dataid");
       $("#commonNodeId").val(dataid);
       var href = "{:url('user/Scenarios/getFnodeInfo')}";
       $.ajax({
         url : href,
         dataType : "json",
         type : "post",
         data : {'fId':dataid},
         success: function(data){
           getdefaultMt("edit");  //获取默认答法的
         //	console.log(data);
           var back = data.data;
           $("#cNodeName").val(back.name);
           $("#AIStechnique").val(back.content);
           $("#tc_id").val(back.tc_id);
           if(back.break == "1"){
             $("#Othersettings").prop("checked",true);
           }else{
             $("#Othersettings").prop("checked",false);
           }
           if(back.no_speak_knowledge_id > 0){
             $("#unanswered").prop("checked",true);
             $('#answeredlist').css("display","block");
             $("#eightList").val(back.no_speak_knowledge_id);
           }
           if(back.sms_template_id > 0){
             $("#smsInfo").prop("checked",true);
             $('#smstpl').css("display","block");
             $("#smsList").val(back.sms_template_id);
           }
           if(back.bridge > 0){
             $("#agent").prop("checked",true);
             $('#agentbox').css("display","block");
             $("#agentList").val(back.bridge);
           }
           $("#nodeLabel").val(back.flow_label);
           $("#cpauseTime").val(back.pause_time);
           var returns = back.returns;
           //console.log(typeof returns);
           var leng = returns.length;
           for(var i=0;i<leng;i++){
             if(returns[i]["type"] == 0){
                 var name = returns[i]["name"];
                 var keyword =	returns[i]["keyword"];
                 var Method = returns[i]["keyword"];
                 var utId = returns[i]["id"];
                 var is_select = returns[i]["is_select"];
                 var select = "";
                 if(is_select == 1){
                   select = "checked";
                 }

                 var string = '<div class="leftfl">'
                   +'<input class="customAnswer" id="'+utId+'" '+select+' name="customAnswer" value="'+name+'" alt="'+name+'" type="checkbox" />	'
                   +'<span id="'+utId+'text">'+name+'</span>'
                   +'<span class="glyphicon glyphicon-pencil showpen" onclick="gotoeditorial(\'customclass\',\''+utId+'\');" aria-hidden="true"></span>'
                   +'<span class="glyphicon glyphicon-trash showpen" delId="'+utId+'" aria-hidden="true" onclick="delbranch(this);"></span>'
                   +'<input id="'+utId+'Id" class="branchId" type="hidden" value="'+utId+'" \/>'
                   +'<input id="'+utId+'Name" class="customName" type="hidden" value="'+name+'" \/>'
                   +'<input id="'+utId+'KW" class="customKW" type="hidden" value="'+keyword+'" \/>'
                   +'<input id="'+utId+'Method" class="customMethod" type="hidden" value="'+Method+'" \/>'
                   +'</div>';
               $('#CustomList').append(string);
             }
             else{
               // 0 普通 1业务问题 2肯定3 否定 4拒绝 5中性  6 未识别 7重复 8用户未说话 9无法回答 10 无法回答次数
               var name = returns[i]["name"];
               var keyword =	returns[i]["keyword"];
               var Method = returns[i]["keyword"];
               var utId = returns[i]["id"];
               var sort = returns[i]["type"];
               var is_select = returns[i]["is_select"];
               var select = "";
               if(is_select == 1){
                 select = "checked";
               }
               var string = '<div class="leftfl">'
                 +'<input class="fixedAnswer" oid="'+utId+'" '+select+' id="'+utId+'" name="fixedAnswer" value="'+name+'" alt="'+name+'" type="checkbox" />	'
                 +'<span id="'+utId+'text">'+name+'</span>'
                 +'<span class="glyphicon glyphicon-pencil showpen" onclick="gotoeditorial(\'fixedclass\',\''+utId+'\');" aria-hidden="true"></span>'
                 +'<input id="'+utId+'Id" class="branchId" type="hidden" value="'+utId+'" \/>'
                 +'<input id="'+utId+'Name" class="fixedName" type="hidden" value="'+name+'" \/>'
                 +'<input id="'+utId+'KW" class="fixedKW" type="hidden" value="'+keyword+'" \/>'
                 +'<input id="'+utId+'Method" class="fixedMethod sort'+sort+'" type="hidden" value="'+Method+'" \/>'
                 +'<input id="'+utId+'sort" class="fixedsort" type="hidden" value="'+sort+'" \/>'
                 +'</div>';
               $('#fixedList').append(string);
             }
           }
           $('#commonNode').modal('show');
         },
         error : function() {
           alert('获取信息失败。');
         }
       });
   }
 }

 //获取短信模板"
  function getsmsTpl(bindObj){
    var sceneId = $("#nowsceneID").val();
    var href = "{:url('user/scenarios/getSmsTpl')}";
    $.ajax({
      url : href,
      dataType : "json",
      type : "post",
      data : {
        'sceneId':sceneId,
      },
      success: function(data){
        $("#"+bindObj).empty();
        var string = '';
        $("#"+bindObj).append("<option value=''>请选择短信模板</option> ");
        if(data.code == 0){
          var item = data.data;
          var leng = item.length;

          for(var i=0;i<leng;i++){
            var name = item[i]["name"];
            var kid = item[i]["id"];
            string += '<option value="'+kid+'">'+name+'</option>';
          }
        }
        if (string){
          $("#"+bindObj).append(string);
        }
      },
      error : function() {
        alert('获取信息失败。');
      }
    });
  }

//跳转节点
  function gotojumpNote(obj){

    $("#flowNoteName").val("");
    $("#jfkeyword").val("");
    $("#nextflow").val(" ");
    $("#mainflow").val(" ");
    $("#fjtc_id").val(" ");
    $("#jpauseTime").val("3000");

    var type = $(obj).attr("datatype");
    $("#fNodeType").val(type);


    if(type == "new"){

      var pid = $(obj).parent().attr("id");
      $("#nowEditId").val(pid);

      $('#jumpNote').modal('show');

    }
    else{


        var dataid = $(obj).attr("dataid");
        $("#nowEditId").val(dataid);

        var href = "{:url('user/Scenarios/getFnodeInfo')}";

        $.ajax({
          url : href,
          dataType : "json",
          type : "post",
          data : {'fId':dataid},
          success: function(data){

            var back = data.data;
            $("#flowNoteName").val(back.name);
            $("#fjtc_id").val(back.tc_id);
            $("#jfkeyword").val(back.content);
            $("#nextflow").val(back.action);
            $("#jpauseTime").val(back.pause_time);
            if(back.action == "2"){
                  $("#mainflowdiv").css("display","block");

                  var sceneId = $("#nowsceneID").val();
                  var href = "{:url('user/Scenarios/getNoteList')}";

                  $.ajax({
                      type: "POST",
                      dataType:'json',
                      url: href,
                      cache: false,
                      data: {"sceneId":sceneId},
                      success: function(data) {
                          $("#mainflow").find("option").remove();
                          if (data.code == 0) {
                              var data = data.data;
                              if(data.length > 0){
                                  var string = '<option value=" ">选择要跳转到的流程节点</option>';
                                  for(var i=0;i<data.length;i++){
                                    if(data[i].id == back.action_id){
                                      string += '<option selected value="'+data[i].id+'">'+data[i].name+'</option>';
                                    }else{
                                      string += '<option value="'+data[i].id+'">'+data[i].name+'</option>';
                                    }
                                  }
                                  $("#mainflow").append(string);
                              }
                          }else{
                            console.log(data.msg);
                            //alert(data.msg);
                          }
                      },
                      error: function(data) {
                        alert("提交失败");
                      }
                  })
            }
            else{
              $("#mainflowdiv").css("display","none");
            }
            $('#jumpNote').modal('show');
          },
          error : function() {
            alert('获取信息失败。');
          }
        });
    }
  }

  //获取默认答法的
  function getdefaultMt(cate){
    $('#fixedList').find("div").remove();
    var sceneId = $("#nowsceneID").val();
    var nowProcessId = $("#nowProcessId").val();
    var href = "{:url('user/Scenarios/defaultMt')}";
    $.ajax({
      url : href,
      dataType : "json",
      type : "post",
      data : {
        'sceneId':sceneId,
        'processId':nowProcessId,
      },
      success: function(data){
        if(data.code == 0){
          var item = data.data;
          var leng = item.length;
          for(var i=0;i<leng;i++){
               // 0 普通 1业务问题 2肯定3 否定 4拒绝 5中性  6 未识别 7重复 8用户未说话 9无法回答 10 无法回答次数
            var name = item[i]["name"];
            var keyword =	item[i]["keyword"];
            var Method = item[i]["keyword"];
            var utId = item[i]["id"];
            var sort = item[i]["type"];
            if(cate == 'new'){
              var string = '<div class="leftfl">'
                  +'<input class="fixedAnswer" id="" oid="'+utId+'" name="fixedAnswer" value="'+name+'" alt="'+name+'" type="checkbox" />	'
                  +'<span id="'+utId+'text">'+name+'</span>'
                  +'<span class="glyphicon glyphicon-pencil showpen" onclick="gotoeditorial(\'fixedclass\',\''+utId+'\');" aria-hidden="true"></span>'
                  +'<input id="'+utId+'Id" class="branchId" type="hidden" value="'+utId+'" \/>'
                  +'<input id="'+utId+'Name" class="fixedName" type="hidden" value="'+name+'" \/>'
                  +'<input id="'+utId+'KW" class="fixedKW" type="hidden" value="" \/>'
                  +'<input id="'+utId+'Method" class="fixedMethod" type="hidden" value="'+Method+'" \/>'
                  +'<input id="'+utId+'sort" class="fixedsort" type="hidden" value="'+sort+'" \/>'
                  +'</div>';
              $('#fixedList').append(string);
            }else{
              $(".leftfl").find(".sort"+sort).val(Method);
            }
          }
        }else{
          console.log(data);
        }
        var item = data.data;
      },
      error : function() {
        alert('获取信息失败。');
      }
    });
  }
