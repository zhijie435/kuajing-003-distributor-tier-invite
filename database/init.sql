-- ========================================================
-- 内容审核标注平台 - 经销商等级邀请码流转系统
-- 数据库初始化脚本 (MySQL 5.7+)
-- 字符集：utf8mb4
-- ========================================================

CREATE DATABASE IF NOT EXISTS `annotation_platform`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `annotation_platform`;

-- ========================================================
-- 1. 经销商等级表
-- ========================================================
DROP TABLE IF EXISTS `dealer_levels`;
CREATE TABLE `dealer_levels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '等级名称',
  `code` VARCHAR(30) NOT NULL COMMENT '等级编码',
  `level` INT NOT NULL COMMENT '等级权重，数字越大等级越高',
  `icon` VARCHAR(100) DEFAULT NULL COMMENT '等级图标',
  `description` TEXT DEFAULT NULL COMMENT '等级描述',
  `min_achievement` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '升级所需最小业绩',
  `min_invite_count` INT NOT NULL DEFAULT 0 COMMENT '升级所需最小邀请人数',
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '佣金比例%',
  `reward_bonus` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '升级奖励金额',
  `privileges` JSON DEFAULT NULL COMMENT '等级特权配置',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  UNIQUE KEY `uk_level` (`level`),
  KEY `idx_level` (`level`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='经销商等级表';

-- ========================================================
-- 2. 用户表
-- ========================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL COMMENT '用户名',
  `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
  `password` VARCHAR(255) NOT NULL COMMENT '密码',
  `nickname` VARCHAR(50) DEFAULT NULL COMMENT '昵称',
  `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像',
  `dealer_level_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '经销商等级ID',
  `total_achievement` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '累计业绩',
  `current_month_achievement` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '当月业绩',
  `total_invite_count` INT NOT NULL DEFAULT 0 COMMENT '累计邀请人数',
  `inviter_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '邀请人用户ID',
  `invite_path` VARCHAR(500) DEFAULT NULL COMMENT '邀请链路路径，格式：ID1-ID2-ID3',
  `invite_depth` INT NOT NULL DEFAULT 0 COMMENT '邀请深度',
  `api_token` VARCHAR(80) DEFAULT NULL COMMENT 'API Token',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1正常 0禁用',
  `last_login_at` TIMESTAMP NULL DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_phone` (`phone`),
  UNIQUE KEY `uk_api_token` (`api_token`),
  KEY `idx_dealer_level_id` (`dealer_level_id`),
  KEY `idx_inviter_id` (`inviter_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_users_dealer_level` FOREIGN KEY (`dealer_level_id`) REFERENCES `dealer_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ========================================================
-- 3. 邀请码表
-- ========================================================
DROP TABLE IF EXISTS `invite_codes`;
CREATE TABLE `invite_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL COMMENT '邀请码',
  `owner_id` BIGINT UNSIGNED NOT NULL COMMENT '拥有者用户ID',
  `target_dealer_level_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '绑定的目标等级ID',
  `max_uses` INT NOT NULL DEFAULT 1 COMMENT '最大使用次数',
  `used_count` INT NOT NULL DEFAULT 0 COMMENT '已使用次数',
  `reward_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '邀请奖励金额',
  `new_user_bonus` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '新用户注册奖励',
  `expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT '过期时间',
  `activated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '激活时间',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1正常 2已用完 3已过期',
  `remark` TEXT DEFAULT NULL COMMENT '备注',
  `created_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '创建人ID',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_target_dealer_level_id` (`target_dealer_level_id`),
  KEY `idx_status` (`status`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_invite_codes_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invite_codes_target_level` FOREIGN KEY (`target_dealer_level_id`) REFERENCES `dealer_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invite_codes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请码表';

-- ========================================================
-- 4. 邀请链路表
-- ========================================================
DROP TABLE IF EXISTS `invite_chains`;
CREATE TABLE `invite_chains` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inviter_id` BIGINT UNSIGNED NOT NULL COMMENT '邀请人ID',
  `invitee_id` BIGINT UNSIGNED NOT NULL COMMENT '被邀请人ID',
  `invite_code_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '使用的邀请码ID',
  `depth` INT NOT NULL DEFAULT 1 COMMENT '相对深度，直接邀请=1',
  `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '佣金比例%',
  `total_commission` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '累计佣金',
  `reward_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '邀请奖励',
  `is_rewarded` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已发放奖励',
  `rewarded_at` TIMESTAMP NULL DEFAULT NULL COMMENT '奖励发放时间',
  `status` TINYINT NOT NULL DEFAULT 2 COMMENT '状态：1待确认 2已确认 3已取消 4已发奖',
  `operator_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '最后操作人ID',
  `operation_logs` JSON DEFAULT NULL COMMENT '操作日志JSON数组',
  `confirmed_at` TIMESTAMP NULL DEFAULT NULL COMMENT '确认时间',
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL COMMENT '取消时间',
  `remark` TEXT DEFAULT NULL COMMENT '备注',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_inviter_invitee` (`inviter_id`, `invitee_id`),
  KEY `idx_inviter_id` (`inviter_id`),
  KEY `idx_invitee_id` (`invitee_id`),
  KEY `idx_invite_code_id` (`invite_code_id`),
  KEY `idx_depth` (`depth`),
  KEY `idx_status` (`status`),
  KEY `idx_operator_id` (`operator_id`),
  CONSTRAINT `fk_invite_chains_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invite_chains_invitee` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invite_chains_code` FOREIGN KEY (`invite_code_id`) REFERENCES `invite_codes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invite_chains_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请链路表';

-- ========================================================
-- 5. 升级记录表
-- ========================================================
DROP TABLE IF EXISTS `upgrade_records`;
CREATE TABLE `upgrade_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
  `old_level_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '原等级ID',
  `new_level_id` BIGINT UNSIGNED NOT NULL COMMENT '新等级ID',
  `upgrade_type` TINYINT NOT NULL DEFAULT 1 COMMENT '升级类型：1自动 2手动 3邀请码 4后台调整',
  `achievement_at_upgrade` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '升级时业绩',
  `invite_count_at_upgrade` INT NOT NULL DEFAULT 0 COMMENT '升级时邀请人数',
  `reward_bonus` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '升级奖励金额',
  `is_rewarded` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '奖励是否已发放',
  `rewarded_at` TIMESTAMP NULL DEFAULT NULL COMMENT '奖励发放时间',
  `status` TINYINT NOT NULL DEFAULT 2 COMMENT '状态：1待审核 2审核通过 3审核拒绝 4已发奖',
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL COMMENT '审核时间',
  `reviewer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人ID',
  `operator_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '创建操作人ID',
  `invite_code_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联邀请码ID',
  `operation_logs` JSON DEFAULT NULL COMMENT '操作日志JSON数组',
  `remark` TEXT DEFAULT NULL COMMENT '备注',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_old_level_id` (`old_level_id`),
  KEY `idx_new_level_id` (`new_level_id`),
  KEY `idx_upgrade_type` (`upgrade_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`),
  KEY `idx_reviewer_id` (`reviewer_id`),
  CONSTRAINT `fk_upgrade_records_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_upgrade_records_old_level` FOREIGN KEY (`old_level_id`) REFERENCES `dealer_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_upgrade_records_new_level` FOREIGN KEY (`new_level_id`) REFERENCES `dealer_levels` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_upgrade_records_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_upgrade_records_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_upgrade_records_code` FOREIGN KEY (`invite_code_id`) REFERENCES `invite_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='升级记录表';

-- ========================================================
-- 迁移记录表（Laravel 需要）
-- ========================================================
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 初始化：经销商等级数据
-- ========================================================
INSERT INTO `dealer_levels` (`name`, `code`, `level`, `description`, `min_achievement`, `min_invite_count`, `commission_rate`, `reward_bonus`, `privileges`, `is_active`, `created_at`, `updated_at`) VALUES
('普通经销商', 'NORMAL', 1, '初始等级，所有新用户默认等级', 0.00, 0, 5.00, 0.00, JSON_ARRAY('vip_service'), 1, NOW(), NOW()),
('银牌经销商', 'SILVER', 2, '达到一定业绩和邀请人数即可升级', 50000.00, 5, 8.00, 2000.00, JSON_ARRAY('vip_service', 'priority_shipping'), 1, NOW(), NOW()),
('金牌经销商', 'GOLD', 3, '高等级经销商，享有更多特权', 150000.00, 15, 12.00, 5000.00, JSON_ARRAY('vip_service', 'priority_shipping', 'special_discount', 'training'), 1, NOW(), NOW()),
('铂金经销商', 'PLATINUM', 4, '最高等级，专属定制服务', 500000.00, 30, 18.00, 15000.00, JSON_ARRAY('vip_service', 'priority_shipping', 'special_discount', 'training', 'marketing', 'area_protection'), 1, NOW(), NOW()),
('钻石经销商', 'DIAMOND', 5, '荣誉等级，仅授予卓越贡献者', 1000000.00, 80, 25.00, 50000.00, JSON_ARRAY('vip_service', 'priority_shipping', 'special_discount', 'training', 'marketing', 'area_protection', 'exclusive_manager', 'annual_award'), 1, NOW(), NOW());

-- ========================================================
-- 初始化：测试用户数据
-- ========================================================
INSERT INTO `users` (`username`, `email`, `phone`, `password`, `nickname`, `dealer_level_id`, `total_achievement`, `total_invite_count`, `inviter_id`, `invite_path`, `invite_depth`, `status`, `created_at`, `updated_at`) VALUES
('admin', 'admin@example.com', '13800000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超级管理员', 5, 3680000.00, 520, NULL, '1', 0, 1, NOW(), NOW()),
('founder', 'founder@example.com', '13800000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '创始人', 4, 1280000.00, 152, 1, '1-2', 1, 1, NOW(), NOW()),
('leader_a', 'leader_a@example.com', '13800000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '团队领导A', 4, 856000.00, 86, 2, '1-2-3', 2, 1, NOW(), NOW()),
('leader_b', 'leader_b@example.com', '13800000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '团队领导B', 3, 268500.00, 18, 3, '1-2-3-4', 3, 1, NOW(), NOW()),
('member_01', 'member01@example.com', '13800000010', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '成员01', 3, 168000.00, 12, 4, '1-2-3-4-5', 4, 1, NOW(), NOW()),
('member_02', 'member02@example.com', '13800000011', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '成员02', 2, 68000.00, 6, 4, '1-2-3-4-6', 4, 1, NOW(), NOW()),
('member_03', 'member03@example.com', '13800000012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '成员03', 2, 52000.00, 5, 3, '1-2-3-7', 3, 1, NOW(), NOW()),
('member_04', 'member04@example.com', '13800000013', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '成员04', 1, 12000.00, 0, 5, '1-2-3-4-5-8', 5, 1, NOW(), NOW());

-- ========================================================
-- 初始化：测试邀请码
-- ========================================================
INSERT INTO `invite_codes` (`code`, `owner_id`, `target_dealer_level_id`, `max_uses`, `used_count`, `reward_amount`, `new_user_bonus`, `expires_at`, `activated_at`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
('FOUNDER1', 2, 4, 1, 1, 10000.00, 2000.00, '2025-12-31 23:59:59', NOW(), 2, 1, NOW(), NOW()),
('GOLD1234', 3, 3, 10, 6, 5000.00, 500.00, '2025-12-31 23:59:59', NOW(), 1, 1, NOW(), NOW()),
('SILVER88', 4, 2, 20, 12, 2000.00, 200.00, '2025-06-30 23:59:59', NOW(), 1, 1, NOW(), NOW()),
('VIP50000', 1, 5, 1, 0, 50000.00, 10000.00, '2026-12-31 23:59:59', NOW(), 1, 1, NOW(), NOW()),
('NORMAL11', 5, NULL, 1, 1, 0.00, 100.00, '2025-12-31 23:59:59', NOW(), 5, 1, NOW(), NOW()),
('EXPIRED1', 6, 2, 5, 0, 2000.00, 0.00, '2024-01-01 23:59:59', NOW(), 3, 6, NOW(), NOW());

-- ========================================================
-- 初始化：测试邀请链路
-- ========================================================
INSERT INTO `invite_chains` (`inviter_id`, `invitee_id`, `invite_code_id`, `depth`, `commission_rate`, `total_commission`, `reward_amount`, `is_rewarded`, `rewarded_at`, `status`, `operator_id`, `operation_logs`, `confirmed_at`, `cancelled_at`, `remark`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 1, 25.00, 128000.00, 50000.00, 1, NOW(), 4, 1, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','系统初始化创建','old_status',2,'new_status',2,'created_at','2024-01-01 00:00:00'), JSON_OBJECT('action','confirm','action_label','确认邀请关系','operator_id',1,'operator_name','超级管理员','remark','初始化确认有效','old_status',2,'new_status',2,'created_at','2024-01-01 00:05:00'), JSON_OBJECT('action','reward','action_label','发放邀请奖励','operator_id',1,'operator_name','超级管理员','remark','首批发奖','old_status',2,'new_status',4,'created_at',NOW())), NOW(), NULL, '直接邀请', NOW(), NOW()),
(1, 3, NULL, 2, 20.00, 85600.00, 0.00, 1, NOW(), 2, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','系统初始化创建','old_status',2,'new_status',2,'created_at','2024-02-01 00:00:00')), NOW(), NULL, '深度2间接邀请', NOW(), NOW()),
(1, 4, NULL, 3, 15.00, 26850.00, 0.00, 1, NOW(), 2, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','系统初始化创建','old_status',2,'new_status',2,'created_at','2024-03-01 00:00:00')), NOW(), NULL, '深度3间接邀请', NOW(), NOW()),
(2, 3, 1, 1, 18.00, 85600.00, 15000.00, 1, NOW(), 4, 1, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','使用邀请码建立关系','old_status',2,'new_status',2,'created_at','2024-02-15 10:00:00'), JSON_OBJECT('action','reward','action_label','发放邀请奖励','operator_id',1,'operator_name','超级管理员','remark','邀请码激活奖励','old_status',2,'new_status',4,'created_at',NOW())), NOW(), NULL, '使用 FOUNDER1 邀请码', NOW(), NOW()),
(2, 4, NULL, 2, 14.40, 26850.00, 0.00, 1, NOW(), 2, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','系统初始化创建','old_status',2,'new_status',2,'created_at','2024-03-10 00:00:00')), NOW(), NULL, '深度2间接邀请', NOW(), NOW()),
(3, 4, 2, 1, 12.00, 26850.00, 5000.00, 1, NOW(), 2, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','使用邀请码建立关系','old_status',2,'new_status',2,'created_at','2024-04-01 00:00:00')), NOW(), NULL, '使用 GOLD1234 邀请码', NOW(), NOW()),
(3, 7, NULL, 1, 12.00, 5200.00, 2000.00, 1, NOW(), 2, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','系统初始化创建','old_status',2,'new_status',2,'created_at','2024-05-10 00:00:00')), NOW(), NULL, '直接邀请', NOW(), NOW()),
(4, 5, 3, 1, 8.00, 16800.00, 2000.00, 1, NOW(), 4, 1, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','使用邀请码建立关系','old_status',2,'new_status',2,'created_at','2024-06-01 10:00:00'), JSON_OBJECT('action','reward','action_label','发放邀请奖励','operator_id',1,'operator_name','超级管理员','remark','月度奖励发放','old_status',2,'new_status',4,'created_at',NOW())), NOW(), NULL, '使用 SILVER88 邀请码', NOW(), NOW()),
(4, 6, 3, 1, 8.00, 6800.00, 2000.00, 0, NULL, 1, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','使用邀请码建立关系','old_status',1,'new_status',1,'created_at','2024-11-20 00:00:00')), NULL, NULL, '使用 SILVER88 邀请码', NOW(), NOW()),
(5, 8, 5, 1, 5.00, 1200.00, 0.00, 1, NOW(), 3, 1, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建邀请关系','operator_id',NULL,'operator_name','系统','remark','使用邀请码建立关系','old_status',1,'new_status',1,'created_at','2024-12-01 10:00:00'), JSON_OBJECT('action','cancel','action_label','取消邀请关系','operator_id',1,'operator_name','超级管理员','remark','邀请码重复使用，取消关系','old_status',1,'new_status',3,'created_at','2024-12-02 10:00:00')), NULL, '2024-12-02 10:00:00', '使用 NORMAL11 邀请码', NOW(), NOW());

-- ========================================================
-- 初始化：测试升级记录
-- ========================================================
INSERT INTO `upgrade_records` (`user_id`, `old_level_id`, `new_level_id`, `upgrade_type`, `achievement_at_upgrade`, `invite_count_at_upgrade`, `reward_bonus`, `is_rewarded`, `rewarded_at`, `status`, `reviewed_at`, `reviewer_id`, `operator_id`, `invite_code_id`, `operation_logs`, `remark`, `created_at`, `updated_at`) VALUES
(2, NULL, 2, 1, 50000.00, 5, 2000.00, 1, NOW(), 4, '2024-03-15 10:30:00', NULL, NULL, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','自动升级检查触发','old_status',2,'new_status',2,'created_at','2024-03-15 10:30:00'), JSON_OBJECT('action','reward','action_label','发放升级奖励','operator_id',1,'operator_name','超级管理员','remark','月度自动发奖','old_status',2,'new_status',4,'created_at',NOW())), '自动升级：满足银牌条件', '2024-03-15 10:30:00', NOW()),
(2, 2, 3, 1, 150000.00, 15, 5000.00, 1, NOW(), 4, '2024-06-20 14:20:00', NULL, NULL, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','自动升级检查触发','old_status',2,'new_status',2,'created_at','2024-06-20 14:20:00'), JSON_OBJECT('action','reward','action_label','发放升级奖励','operator_id',1,'operator_name','超级管理员','remark','季度升级奖励','old_status',2,'new_status',4,'created_at',NOW())), '自动升级：满足金牌条件', '2024-06-20 14:20:00', NOW()),
(2, 3, 4, 3, 180000.00, 18, 15000.00, 1, NOW(), 4, '2024-08-10 09:15:00', 1, NULL, 1, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','邀请码激活升级','old_status',2,'new_status',2,'created_at','2024-08-10 09:15:00'), JSON_OBJECT('action','approve','action_label','审核通过','operator_id',1,'operator_name','超级管理员','remark','邀请码验证通过','old_status',2,'new_status',2,'created_at','2024-08-10 09:20:00'), JSON_OBJECT('action','reward','action_label','发放升级奖励','operator_id',1,'operator_name','超级管理员','remark','邀请码奖励发放','old_status',2,'new_status',4,'created_at',NOW())), '邀请码升级：使用FOUNDER1', '2024-08-10 09:15:00', NOW()),
(3, NULL, 2, 1, 52000.00, 6, 2000.00, 1, NOW(), 2, '2024-04-10 11:00:00', NULL, NULL, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','自动升级检查触发','old_status',2,'new_status',2,'created_at','2024-04-10 11:00:00')), '自动升级', '2024-04-10 11:00:00', NOW()),
(3, 2, 3, 3, 156000.00, 16, 5000.00, 1, NOW(), 2, '2024-09-05 16:30:00', 1, NULL, 2, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','邀请码激活升级','old_status',2,'new_status',2,'created_at','2024-09-05 16:30:00'), JSON_OBJECT('action','approve','action_label','审核通过','operator_id',1,'operator_name','超级管理员','remark','资料齐全通过审核','old_status',2,'new_status',2,'created_at','2024-09-05 17:00:00')), '邀请码升级：使用GOLD1234', '2024-09-05 16:30:00', NOW()),
(4, NULL, 2, 3, 60000.00, 5, 2000.00, 1, NOW(), 2, '2024-07-18 13:45:00', 1, NULL, 3, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','邀请码激活升级','old_status',2,'new_status',2,'created_at','2024-07-18 13:45:00'), JSON_OBJECT('action','approve','action_label','审核通过','operator_id',1,'operator_name','超级管理员','remark','有效邀请码','old_status',2,'new_status',2,'created_at','2024-07-18 14:00:00')), '邀请码升级：使用SILVER88', '2024-07-18 13:45:00', NOW()),
(4, 2, 3, 1, 158000.00, 16, 5000.00, 1, NOW(), 4, '2024-10-12 08:20:00', NULL, NULL, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','自动升级检查触发','old_status',2,'new_status',2,'created_at','2024-10-12 08:20:00'), JSON_OBJECT('action','reward','action_label','发放升级奖励','operator_id',1,'operator_name','超级管理员','remark','月度奖励','old_status',2,'new_status',4,'created_at',NOW())), '自动升级', '2024-10-12 08:20:00', NOW()),
(5, NULL, 2, 1, 51000.00, 5, 2000.00, 1, NOW(), 2, '2024-11-01 10:10:00', NULL, NULL, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','自动升级检查触发','old_status',2,'new_status',2,'created_at','2024-11-01 10:10:00')), '自动升级', '2024-11-01 10:10:00', NOW()),
(5, 2, 3, 4, 168000.00, 12, 5000.00, 0, NULL, 1, NULL, NULL, 1, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',1,'operator_name','超级管理员','remark','后台手动调整','old_status',1,'new_status',1,'created_at','2024-12-01 15:00:00')), '后台手动调整-待审核', '2024-12-01 15:00:00', NOW()),
(6, NULL, 2, 1, 50500.00, 5, 2000.00, 1, NOW(), 3, '2024-11-20 17:25:00', 1, NULL, NULL, JSON_ARRAY(JSON_OBJECT('action','create','action_label','创建升级记录','operator_id',NULL,'operator_name','系统','remark','自动升级检查触发','old_status',1,'new_status',1,'created_at','2024-11-20 17:25:00'), JSON_OBJECT('action','reject','action_label','审核拒绝','operator_id',1,'operator_name','超级管理员','remark','业绩数据异常，需重新核实','old_status',1,'new_status',3,'created_at','2024-11-21 10:00:00')), '自动升级-审核拒绝', '2024-11-20 17:25:00', NOW());

-- ========================================================
-- 迁移记录（让 Laravel 识别已执行的迁移）
-- ========================================================
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2024_01_01_000001_create_dealer_levels_table', 1),
('2024_01_01_000002_create_users_table', 1),
('2024_01_01_000003_create_invite_codes_table', 1),
('2024_01_01_000004_create_invite_chains_table', 1),
('2024_01_01_000005_create_upgrade_records_table', 1),
('2024_01_01_000006_add_status_and_operation_logs_to_invite_chains_and_upgrade_records', 1);

SHOW TABLES;
SELECT '数据库初始化完成！' AS message;
