/*
 Navicat Premium Data Transfer
使用前将plugin_替换成相应前缀，如果没有则替换成空
 Date: 25/05/2022 15:54:24
*/

SET NAMES utf8mb4;
SET
FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for plugin_wf_flow
-- ----------------------------
DROP TABLE IF EXISTS `plugin_wf_flow`;
CREATE TABLE `plugin_wf_flow`
(
    `flow_id`       int(11) NOT NULL AUTO_INCREMENT,
    `template_id`   int(11) NOT NULL DEFAULT '0' COMMENT '工作流模板ID',
    `template_name` varchar(50)  NOT NULL DEFAULT '' COMMENT '模板名（冗余）',
    `template_desc` varchar(11)  NOT NULL DEFAULT '' COMMENT '模板描述（冗余）',
    `flow_name`     varchar(50)  NOT NULL DEFAULT '' COMMENT '工作流名称',
    `flow_desc`     varchar(255) NOT NULL DEFAULT '' COMMENT '工作流描述',
    `create_time`   int(11) NOT NULL DEFAULT '0',
    `update_time`   int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`flow_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COMMENT='工作流本地表';

-- ----------------------------
-- Table structure for plugin_wf_flow_process
-- ----------------------------
DROP TABLE IF EXISTS `plugin_wf_flow_process`;
CREATE TABLE `plugin_wf_flow_process`
(
    `process_id`       int(11) NOT NULL AUTO_INCREMENT,
    `flow_id`          int(11) NOT NULL DEFAULT '0' COMMENT '工作流ID',
    `process_name`     varchar(100) NOT NULL DEFAULT '' COMMENT '步骤名',
    `level`            int(11) NOT NULL DEFAULT '0' COMMENT '层级',
    `branch_pid`       int(11) NOT NULL DEFAULT '0' COMMENT '如果产生分支为分支顶端模板ID',
    `process_desc`     varchar(255) NOT NULL DEFAULT '' COMMENT '步骤描述',
    `process_type`     int(11) NOT NULL DEFAULT '0' COMMENT '步骤类型  0为开始 1为步骤 2为结束 3网关',
    `next_process_ids` varchar(100) NOT NULL DEFAULT '' COMMENT '下一步骤IDS',
    `role_ids`         varchar(100) NOT NULL DEFAULT '' COMMENT '审核角色IDS',
    `can_back`         tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否可驳回 0否 1是',
    `status_id`        int(11) NOT NULL DEFAULT '0' COMMENT '关联状态ID',
    `sign_type`        int(11) NOT NULL DEFAULT '0' COMMENT '签名模式 0或签 1会签',
    `condition`        varchar(100) NOT NULL DEFAULT '' COMMENT '判断条件（网关类型步骤专属）',
    `jump_process_id`  int(11) NOT NULL DEFAULT '0' COMMENT '符合条件跳转步骤ID（网关类型步骤专属）',
    `create_time`      int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`process_id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COMMENT='工作流本地步骤表';

-- ----------------------------
-- Table structure for plugin_wf_role
-- ----------------------------
DROP TABLE IF EXISTS `plugin_wf_role`;
CREATE TABLE `plugin_wf_role`
(
    `role_id`     int(11) NOT NULL AUTO_INCREMENT,
    `role_name`   varchar(50) NOT NULL,
    `create_time` int(10) NOT NULL DEFAULT '0',
    PRIMARY KEY (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COMMENT='工作流角色表';

-- ----------------------------
-- Table structure for plugin_wf_run
-- ----------------------------
DROP TABLE IF EXISTS `plugin_wf_run`;
CREATE TABLE `plugin_wf_run`
(
    `run_id`          int(11) NOT NULL AUTO_INCREMENT COMMENT '执行ID',
    `user_id`         int(11) NOT NULL COMMENT '创建用户ID',
    `role_id`         int(11) NOT NULL DEFAULT '0' COMMENT '角色ID（冗余）',
    `flow_id`         int(11) NOT NULL DEFAULT '0' COMMENT '工作流模板ID',
    `now_process_ids` varchar(50) NOT NULL DEFAULT '0' COMMENT '流转到第几步ID',
    `start_time`      int(11) NOT NULL DEFAULT '0' COMMENT '开始时间',
    `end_time`        int(10) unsigned NOT NULL DEFAULT '0' COMMENT '结束时间',
    `status`          int(10) unsigned NOT NULL DEFAULT '0' COMMENT '状态，0未开始 1流转中 2结束',
    `create_time`     int(11) DEFAULT '0',
    `update_time`     int(11) DEFAULT '0',
    PRIMARY KEY (`run_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='工作流执行状态表';

-- ----------------------------
-- Table structure for plugin_wf_run_log
-- ----------------------------
DROP TABLE IF EXISTS `plugin_wf_run_log`;
CREATE TABLE `plugin_wf_run_log`
(
    `log_id`      int(11) NOT NULL AUTO_INCREMENT COMMENT '步骤执行ID',
    `flow_id`     int(10) unsigned NOT NULL DEFAULT '0' COMMENT '工作流ID',
    `process_id`  int(11) unsigned NOT NULL DEFAULT '0' COMMENT '工作流步骤ID',
    `run_id`      int(10) unsigned NOT NULL DEFAULT '0' COMMENT '执行ID',
    `user_id`     int(11) NOT NULL COMMENT '操作用户ID',
    `content`     text CHARACTER SET utf8 NOT NULL COMMENT '日志内容',
    `create_time` int(11) NOT NULL COMMENT '创建时间',
    PRIMARY KEY (`log_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COMMENT='工作流执行日志表';

-- ----------------------------
-- Table structure for plugin_wf_run_process
-- ----------------------------
DROP TABLE IF EXISTS `plugin_wf_run_process`;
CREATE TABLE `plugin_wf_run_process`
(
    `run_process_id`   int(11) NOT NULL AUTO_INCREMENT COMMENT '执行步骤ID',
    `run_id`           int(10) unsigned NOT NULL DEFAULT '0' COMMENT '执行ID',
    `flow_id`          int(10) unsigned NOT NULL DEFAULT '0' COMMENT '流程ID',
    `process_id`       int(11) unsigned NOT NULL DEFAULT '0' COMMENT '对应步骤ID',
    `approval_opinion` text        NOT NULL COMMENT '审批意见',
    `status`           tinyint(3) NOT NULL DEFAULT '0' COMMENT '状态 0为未处理，1为处理中 ,2为已同意,3已结束,-1已打回',
    `is_sign`          varchar(50) NOT NULL DEFAULT '0' COMMENT '已会签过ID',
    `is_back`          tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '被退回的 0否(默认) 1是',
    `receive_time`     int(11) NOT NULL DEFAULT '0' COMMENT '接收时间',
    `handle_time`      int(11) NOT NULL DEFAULT '0' COMMENT '处理时间',
    `create_time`      int(11) NOT NULL COMMENT '创建时间',
    PRIMARY KEY (`run_process_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COMMENT='工作流执行步骤表';

-- ----------------------------
-- Table structure for plugin_wf_status
-- ----------------------------
DROP TABLE IF EXISTS `plugin_wf_status`;
CREATE TABLE `plugin_wf_status`
(
    `status_id`   int(11) NOT NULL AUTO_INCREMENT,
    `status_name` varchar(50) NOT NULL,
    `create_time` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COMMENT='工作流状态对应表';

SET
FOREIGN_KEY_CHECKS = 1;
