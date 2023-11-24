var Paginga = new Paging01();
Paginga.init_args({
    // url:
    page: 1, //初始页码
    limit: 10, //初始每页显示的数据量
    paging_class: 'pagingA', //放置分页的class
    callback: show_datad //回调函数 比如show_datas(页码, 显示条数)
});
show_datad();
function show_datad(page,limit){
    if (!page) {
        page = 1;
    }
    if (!limit) {
        limit = 10;
    }
    window.page = page;
    window.limit = limit;
    var data = {};
    var url = "/user/scenarios/ajax_sendout_scene"
    data.scenarios_name = $('#numberGroup').val();
    data.role_id = $('#role_id').val();
    data.userName = $('#userName').val();
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
                } else {
                    $('#consumeempty').hide();
                    var total = result.data.page; //总页数
                    var Nowpage = result.data.Nowpage; //当前页码
                    var count = result.data.total; //总条数
                    window.count = count;
                    var Nowpage = parseInt(Nowpage);
                    var i = (Nowpage - 1) * limit + 1;
                    $.each(result.data.list, function(index, object) {
                        data.i = i;
                        var html = $('#sendout_scene_data').html();
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%sequence%}',i);
                        html = html.replace('{%scenarios_name%}', object.scenarios_name);
                        html = html.replace('{%username%}', object.username);
                        html = html.replace('{%role_name%}', object.name);
                        html = html.replace('{%sendout_num%}', object.sendout_num);
                        html = html.replace('{%create_time%}', object.create_time);
                        html = html.replace('{%id%}', object.id);
                        html = html.replace('{%remark%}', object.remark);
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
                $('#sendSceneList').html(htmls);
                election();
            }
        },
        error: function(error) {
            console.log(error);
            alert('数据获取失败！');
        }
    });
}

function del(id){
  var url ="/user/scenarios/del_sendout_scene"
  $.ajax({
    url: url,
    dataType: 'json',
    type: 'post',
    data: {id:id},
    success: function(result) {
      console.log(result);
      if(result.code == 0){
        alert(result.msg);
        show_datad(window.page,window.limit);
      }else{
        alert(result.msg);
      }
      $('#tips_model').modal('hide');
    },
    error: function(error) {
      
    }
  });
}
