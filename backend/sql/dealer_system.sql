-- =============================================
-- 经销商等级邀请码流转系统 数据库设计
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------
-- 经销商等级表
-- ---------------------------------------------
DROP TABLE IF EXISTS `dealer_level`;
CREATE TABLE `dealer_level` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '等级ID',
    `level_name` VARCHAR(50) NOT NULL COMMENT '等级名称',
    `level_code` VARCHAR(30) NOT NULL COMMENT '等级编码(唯一)',
    `level_weight` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '等级权重(数值越大等级越高)',
    `min_invite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '升级所需最低邀请人数',
    `min_order_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '升级所需最低订单金额',
    `discount_rate` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT '折扣率(百分比)',
    `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '佣金比例(百分比)',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '等级描述',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:1启用 0禁用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_level_code` (`level_code`),
    KEY `idx_level_weight` (`level_weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='经销商等级表';

-- 初始化等级数据
INSERT INTO `dealer_level` (`level_name`, `level_code`, `level_weight`, `min_invite_count`, `min_order_amount`, `discount_rate`, `commission_rate`, `description`) VALUES
('普通经销商', 'NORMAL', 1, 0, 0.00, 100.00, 5.00, '初始等级，无门槛'),
('铜牌经销商', 'BRONZE', 2, 5, 10000.00, 95.00, 8.00, '邀请5人或累计订单1万元'),
('银牌经销商', 'SILVER', 3, 20, 50000.00, 90.00, 12.00, '邀请20人或累计订单5万元'),
('金牌经销商', 'GOLD', 4, 50, 200000.00, 85.00, 18.00, '邀请50人或累计订单20万元'),
('钻石经销商', 'DIAMOND', 5, 100, 500000.00, 80.00, 25.00, '邀请100人或累计订单50万元');

-- ---------------------------------------------
-- 经销商主表
-- ---------------------------------------------
DROP TABLE IF EXISTS `dealer`;
CREATE TABLE `dealer` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '经销商ID',
    `dealer_no` VARCHAR(32) NOT NULL COMMENT '经销商编号(唯一)',
    `name` VARCHAR(100) NOT NULL COMMENT '经销商名称/姓名',
    `phone` VARCHAR(20) NOT NULL COMMENT '联系电话',
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
    `level_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '当前等级ID',
    `parent_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '上级邀请人ID(根节点为NULL)',
    `invite_code_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '当前使用的邀请码ID',
    `total_invite_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计邀请人数(直推)',
    `total_team_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '团队总人数',
    `total_order_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计订单金额',
    `total_commission` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计佣金',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:1正常 0冻结',
    `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_dealer_no` (`dealer_no`),
    UNIQUE KEY `uk_phone` (`phone`),
    KEY `idx_level_id` (`level_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_registered_at` (`registered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='经销商主表';

-- ---------------------------------------------
-- 邀请码表
-- ---------------------------------------------
DROP TABLE IF EXISTS `invite_code`;
CREATE TABLE `invite_code` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '邀请码ID',
    `code` VARCHAR(32) NOT NULL COMMENT '邀请码(唯一)',
    `owner_id` BIGINT UNSIGNED NOT NULL COMMENT '所属经销商ID',
    `level_id` INT UNSIGNED NOT NULL COMMENT '生成时的等级ID',
    `max_use_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '最大使用次数(0表示不限)',
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已使用次数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:1可用 0已失效 2已用尽',
    `expire_at` DATETIME DEFAULT NULL COMMENT '过期时间(NULL表示永不过期)',
    `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '生成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_owner_id` (`owner_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请码表';

-- ---------------------------------------------
-- 邀请链路表(绑定关系记录)
-- ---------------------------------------------
DROP TABLE IF EXISTS `invite_relation`;
CREATE TABLE `invite_relation` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '关系ID',
    `invite_code_id` BIGINT UNSIGNED NOT NULL COMMENT '使用的邀请码ID',
    `inviter_id` BIGINT UNSIGNED NOT NULL COMMENT '邀请人ID',
    `invitee_id` BIGINT UNSIGNED NOT NULL COMMENT '被邀请人ID',
    `inviter_level_id` INT UNSIGNED NOT NULL COMMENT '邀请时邀请人等级ID',
    `invitee_level_id` INT UNSIGNED NOT NULL COMMENT '被邀请人初始等级ID',
    `bind_depth` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '绑定层级深度(相对于根节点)',
    `path` VARCHAR(500) DEFAULT NULL COMMENT '邀请路径(逗号分隔的ID链,如:1,5,12)',
    `bound_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '绑定时间',
    `remark` VARCHAR(255) DEFAULT NULL COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_invitee_id` (`invitee_id`),
    KEY `idx_inviter_id` (`inviter_id`),
    KEY `idx_invite_code_id` (`invite_code_id`),
    KEY `idx_bound_at` (`bound_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请链路表';

-- ---------------------------------------------
-- 升级记录表
-- ---------------------------------------------
DROP TABLE IF EXISTS `upgrade_log`;
CREATE TABLE `upgrade_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
    `dealer_id` BIGINT UNSIGNED NOT NULL COMMENT '经销商ID',
    `old_level_id` INT UNSIGNED NOT NULL COMMENT '原等级ID',
    `new_level_id` INT UNSIGNED NOT NULL COMMENT '新等级ID',
    `upgrade_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '升级类型:1自动升级 2人工调整 3活动晋升',
    `upgrade_reason` VARCHAR(500) DEFAULT NULL COMMENT '升级原因说明',
    `operator_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '操作人ID(人工调整时必填)',
    `operator_name` VARCHAR(100) DEFAULT NULL COMMENT '操作人姓名',
    `snapshot_data` JSON DEFAULT NULL COMMENT '升级时快照数据(邀请人数/订单金额等)',
    `upgraded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '升级时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_dealer_id` (`dealer_id`),
    KEY `idx_new_level_id` (`new_level_id`),
    KEY `idx_upgrade_type` (`upgrade_type`),
    KEY `idx_upgraded_at` (`upgraded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='升级记录表';

-- ---------------------------------------------
-- 邀请码使用流水表
-- ---------------------------------------------
DROP TABLE IF EXISTS `invite_code_usage`;
CREATE TABLE `invite_code_usage` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '流水ID',
    `invite_code_id` BIGINT UNSIGNED NOT NULL COMMENT '邀请码ID',
    `code` VARCHAR(32) NOT NULL COMMENT '邀请码(冗余)',
    `owner_id` BIGINT UNSIGNED NOT NULL COMMENT '邀请码所属人ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '使用人ID',
    `user_name` VARCHAR(100) DEFAULT NULL COMMENT '使用人姓名(冗余)',
    `user_phone` VARCHAR(20) DEFAULT NULL COMMENT '使用人电话(冗余)',
    `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '使用时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_invite_code_id` (`invite_code_id`),
    KEY `idx_owner_id` (`owner_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_used_at` (`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请码使用流水表';

SET FOREIGN_KEY_CHECKS = 1;
