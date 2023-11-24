--给crm 加索引 提升查询速度
ALTER TABLE `rk_crm`
ADD INDEX `member_id_status` (`member_id`, `status`),
ADD INDEX `seat_id_status` (`seat_id`, `status`);


--加索引提升速度
ALTER TABLE `rk_tel_intention_rule`
ADD INDEX `scenarios_id_status` (`scenarios_id`, `status`);
ALTER TABLE `rk_tel_intention_rule`
ADD INDEX `level` (`level`);

ALTER TABLE `rk_tel_label`
ADD INDEX `type_scenarios_id_label` (`type`, `scenarios_id`, `label`);
ALTER TABLE `rk_tel_label`
ADD INDEX `query_order` (`query_order`);


ALTER TABLE `rk_tel_line_group`
ADD `update_time` int(1) NULL DEFAULT 0 COMMENT '更新时间';


CREATE TABLE `rk_crm_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(32) NOT NULL COMMENT '客户电话',
  `message` text NOT NULL,
  `duration` int(11) DEFAULT '0',
  `path` varchar(100) NOT NULL COMMENT '录音文件路径',
  `role` tinyint(4) NOT NULL COMMENT '0 机器  1客户',
  `status` varchar(30) NOT NULL COMMENT '识别状态',
  `hit_keyword` varchar(200) DEFAULT NULL,
  `create_time` bigint(13) DEFAULT NULL,
  `call_id` varchar(64) DEFAULT NULL,
  `hit_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '命中关键词的类型 1 流程 2知识库 0没命中',
  `hit_info` varchar(255) DEFAULT NULL COMMENT '命中关键词的信息字符串，如果流程的话：场景节点名称_流程节点名称_分支节点名称  如果是知识库的话：知识库标题就行',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `phone` (`phone`) USING BTREE,
  KEY `call_id` (`call_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='crm的话单';
