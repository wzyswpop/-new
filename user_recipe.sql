-- 夯萃配方墙：用户配方表、增量字段、后台菜单
-- 可独立执行；支持新库创建，也支持已有 fa_yp_user_recipe 表补齐字段和索引。
-- 默认数据库表前缀为 fa_，如线上前缀不同，请先整体替换表名前缀。

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `fa_yp_user_recipe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `name` varchar(80) NOT NULL DEFAULT '' COMMENT '配方名称',
  `recipe_data` text NOT NULL COMMENT '配方数据',
  `total_weight` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '常用重量g',
  `baking` varchar(50) NOT NULL DEFAULT '' COMMENT '烘焙度',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '配方简介',
  `scene_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '适用场景标签',
  `flavor_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '风味标签',
  `author_name` varchar(80) NOT NULL DEFAULT '' COMMENT '公开作者名',
  `author_title` varchar(80) NOT NULL DEFAULT '' COMMENT '作者称号',
  `public_status` enum('private','public') NOT NULL DEFAULT 'private' COMMENT '公开状态',
  `is_featured` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否精选',
  `featured_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '精选时间',
  `copy_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '复刻次数',
  `favorite_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '保存次数',
  `feedback_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '反馈次数',
  `feedback_tags` text COMMENT '反馈标签统计',
  `last_order_money` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '最近订单金额',
  `order_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '使用次数',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_featured` (`is_featured`,`public_status`,`status`,`featured_at`),
  KEY `idx_updatetime` (`updatetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户保存的拼配配方';

DELIMITER $$

DROP PROCEDURE IF EXISTS `hc_add_user_recipe_column`$$
CREATE PROCEDURE `hc_add_user_recipe_column`(IN p_column varchar(64), IN p_sql text)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fa_yp_user_recipe'
      AND COLUMN_NAME = p_column
  ) THEN
    SET @hc_sql = p_sql;
    PREPARE hc_stmt FROM @hc_sql;
    EXECUTE hc_stmt;
    DEALLOCATE PREPARE hc_stmt;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `hc_add_user_recipe_index`$$
CREATE PROCEDURE `hc_add_user_recipe_index`(IN p_index varchar(64), IN p_sql text)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fa_yp_user_recipe'
      AND INDEX_NAME = p_index
  ) THEN
    SET @hc_sql = p_sql;
    PREPARE hc_stmt FROM @hc_sql;
    EXECUTE hc_stmt;
    DEALLOCATE PREPARE hc_stmt;
  END IF;
END$$

DELIMITER ;

CALL `hc_add_user_recipe_column`('description', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `description` varchar(255) NOT NULL DEFAULT '' COMMENT '配方简介' AFTER `baking`");
CALL `hc_add_user_recipe_column`('scene_tags', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `scene_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '适用场景标签' AFTER `description`");
CALL `hc_add_user_recipe_column`('flavor_tags', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `flavor_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '风味标签' AFTER `scene_tags`");
CALL `hc_add_user_recipe_column`('author_name', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `author_name` varchar(80) NOT NULL DEFAULT '' COMMENT '公开作者名' AFTER `flavor_tags`");
CALL `hc_add_user_recipe_column`('author_title', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `author_title` varchar(80) NOT NULL DEFAULT '' COMMENT '作者称号' AFTER `author_name`");
CALL `hc_add_user_recipe_column`('public_status', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `public_status` enum('private','public') NOT NULL DEFAULT 'private' COMMENT '公开状态' AFTER `author_title`");
CALL `hc_add_user_recipe_column`('is_featured', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `is_featured` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否精选' AFTER `public_status`");
CALL `hc_add_user_recipe_column`('featured_at', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `featured_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '精选时间' AFTER `is_featured`");
CALL `hc_add_user_recipe_column`('copy_count', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `copy_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '复刻次数' AFTER `featured_at`");
CALL `hc_add_user_recipe_column`('favorite_count', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `favorite_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '保存次数' AFTER `copy_count`");
CALL `hc_add_user_recipe_column`('feedback_count', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `feedback_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '反馈次数' AFTER `favorite_count`");
CALL `hc_add_user_recipe_column`('feedback_tags', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `feedback_tags` text COMMENT '反馈标签统计' AFTER `feedback_count`");
CALL `hc_add_user_recipe_column`('last_order_money', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `last_order_money` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '最近订单金额' AFTER `feedback_tags`");
CALL `hc_add_user_recipe_column`('order_count', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `order_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '使用次数' AFTER `last_order_money`");
CALL `hc_add_user_recipe_column`('status', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态' AFTER `order_count`");
CALL `hc_add_user_recipe_column`('createtime', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间' AFTER `status`");
CALL `hc_add_user_recipe_column`('updatetime', "ALTER TABLE `fa_yp_user_recipe` ADD COLUMN `updatetime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间' AFTER `createtime`");

CALL `hc_add_user_recipe_index`('idx_user_status', "ALTER TABLE `fa_yp_user_recipe` ADD KEY `idx_user_status` (`user_id`,`status`)");
CALL `hc_add_user_recipe_index`('idx_featured', "ALTER TABLE `fa_yp_user_recipe` ADD KEY `idx_featured` (`is_featured`,`public_status`,`status`,`featured_at`)");
CALL `hc_add_user_recipe_index`('idx_updatetime', "ALTER TABLE `fa_yp_user_recipe` ADD KEY `idx_updatetime` (`updatetime`)");

DROP PROCEDURE IF EXISTS `hc_add_user_recipe_column`;
DROP PROCEDURE IF EXISTS `hc_add_user_recipe_index`;

-- 后台菜单：配方墙配方
INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file',
       parent.`id`,
       'yp/user_recipe',
       '配方墙配方',
       'fa fa-flask',
       '',
       '管理用户公开配方的精选和下架状态',
       1,
       'addtabs',
       '',
       'pfqpf',
       'peifangqiangpeifang',
       UNIX_TIMESTAMP(),
       UNIX_TIMESTAMP(),
       87,
       'normal'
FROM `fa_auth_rule` AS parent
WHERE parent.`name` = 'yp'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_recipe') AS menu
  );

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS parent ON parent.`name` = 'yp'
SET menu.`pid` = parent.`id`,
    menu.`title` = '配方墙配方',
    menu.`icon` = 'fa fa-flask',
    menu.`remark` = '管理用户公开配方的精选和下架状态',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`py` = 'pfqpf',
    menu.`pinyin` = 'peifangqiangpeifang',
    menu.`weigh` = 87,
    menu.`status` = 'normal',
    menu.`updatetime` = UNIX_TIMESTAMP()
WHERE menu.`name` = 'yp/user_recipe';

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', menu.`id`, child.`name`, child.`title`, 'fa fa-circle-o', '', '', 0, NULL, '', child.`py`, child.`pinyin`, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'
FROM `fa_auth_rule` AS menu
JOIN (
    SELECT 'yp/user_recipe/index' AS `name`, '查看' AS `title`, 'ck' AS `py`, 'chakan' AS `pinyin`
    UNION ALL SELECT 'yp/user_recipe/edit', '编辑', 'bj', 'bianji'
    UNION ALL SELECT 'yp/user_recipe/multi', '批量更新', 'plgx', 'pilianggengxin'
) AS child
WHERE menu.`name` = 'yp/user_recipe'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `name` FROM `fa_auth_rule`) AS existing_rule WHERE existing_rule.`name` = child.`name`
  );

SET FOREIGN_KEY_CHECKS = 1;
