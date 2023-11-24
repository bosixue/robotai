# API文档

## 任务模块

### 创建任务API接口
	
*	URL : user/plan/create_taks
*	请求方式 : POST
*	参数 : 
```
		{
			task_name:任务名称,
			scenarios_id:话术ID,
			start_date:[
				指定日期的开始日期1,
				指定日期的开始日期2,
				...
			],
			end_date:[
				指定日期的结束日期1,
				指定日期的结束日期2,
				...
			],
			start_time:[
				指定时间的开始时间1,
				指定时间的开始时间2,
				...
			],
			end_time:[
				指定时间的结束时间1,
				指定时间的结束时间2,
				...
			],
			is_auto:1, //是否自动开启 0:否 1:是
			robot_count:100, //机器人数量
			line_id:1, //线路ID
			is_default_line:1, //是否为默认线路 0:否 1:是
			remark:备注, //备注
		}
```
*  状态码 : 
```
	0 创建成功
	2 传参错误
```
	
	
	