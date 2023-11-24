//显示窗口
function show_number_resources_window()
{
  //显示窗口
  $('#number_resources_window').modal('show');

}
//提交获取号码资源的请求
var get_number_resources_status = true;
function get_number_resources()
{
  if(get_number_resources_status == false){
    return false;
  }
  get_number_resources_status = false;
  var number_resources_number_group_name = $('#number_resources_number_group_name').val();
  var number_resources_number_count = $('#number_resources_number_count').val();
  var number_resources_note = $('#number_resources_note').val();
  if(number_resources_number_group_name == ''){
    alert('请输入号码组名称');
    get_number_resources_status = true;
    return false;
  }
  if(number_resources_number_count < 1){
    alert('号码数量不能小于1个');
    get_number_resources_status = true;
    return false;
  }
  if(number_resources_number_count > 10000){
    alert('号码数量不能大于10000个');
    get_number_resources_status = true;
    return false;
  }
  var data = {
    group_name:number_resources_number_group_name,
    number_count:number_resources_number_count,
    note:number_resources_note
  };
  var url = '/user/plan/get_number_resources';
  $.ajax({
    type:"GET",
    data:data,
    url:url,
    dataType:'json',
    success:function(result){
      if(result.code == 0){
        alert('创建成功');
        window.location.href = '';
      }else{
        alert(result.msg);
      }
      get_number_resources_status = true;
    },
    error:function(e){
      alert('提交失败');
      get_number_resources_status = true;
    }
  })
}
