-- 夯萃配方墙：点赞、评论、分享、热度排序
-- 可独立执行；默认表前缀为 fa_，如线上前缀不同，请先整体替换表名前缀。

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `fa_yp_recipe_interaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `source_type` enum('official','user') NOT NULL DEFAULT 'user' COMMENT '配方来源',
  `source_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '来源配方ID',
  `like_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点赞数',
  `comment_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '评论数',
  `share_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分享数',
  `save_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '保存数',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_source` (`source_type`,`source_id`),
  KEY `idx_hot_counts` (`like_count`,`comment_count`,`share_count`,`save_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配方墙互动统计';

CREATE TABLE IF NOT EXISTS `fa_yp_recipe_like` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `source_type` enum('official','user') NOT NULL DEFAULT 'user' COMMENT '配方来源',
  `source_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '来源配方ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_like` (`source_type`,`source_id`,`user_id`),
  KEY `idx_user` (`user_id`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配方墙点赞';

CREATE TABLE IF NOT EXISTS `fa_yp_recipe_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `source_type` enum('official','user') NOT NULL DEFAULT 'user' COMMENT '配方来源',
  `source_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '来源配方ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `content` varchar(255) NOT NULL DEFAULT '' COMMENT '短评内容',
  `status` enum('normal','hidden') NOT NULL DEFAULT 'normal' COMMENT '状态',
  `createtime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updatetime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_source_status` (`source_type`,`source_id`,`status`,`createtime`),
  KEY `idx_user` (`user_id`,`createtime`),
  KEY `idx_status` (`status`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配方墙短评';

-- 后台菜单：配方墙短评
INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file',
       parent.`id`,
       'yp/recipe_comment',
       '配方墙短评',
       'fa fa-comments',
       '',
       '查看和隐藏配方墙公开短评',
       1,
       'addtabs',
       '',
       'pfqdp',
       'peifangqiangduanping',
       UNIX_TIMESTAMP(),
       UNIX_TIMESTAMP(),
       86,
       'normal'
FROM `fa_auth_rule` AS parent
WHERE parent.`name` = 'yp'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment') AS menu
  );

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS parent ON parent.`name` = 'yp'
SET menu.`pid` = parent.`id`,
    menu.`title` = '配方墙短评',
    menu.`icon` = 'fa fa-comments',
    menu.`remark` = '查看和隐藏配方墙公开短评',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`py` = 'pfqdp',
    menu.`pinyin` = 'peifangqiangduanping',
    menu.`weigh` = 86,
    menu.`status` = 'normal',
    menu.`updatetime` = UNIX_TIMESTAMP()
WHERE menu.`name` = 'yp/recipe_comment';

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', menu.`id`, child.`name`, child.`title`, 'fa fa-circle-o', '', '', 0, NULL, '', child.`py`, child.`pinyin`, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'
FROM `fa_auth_rule` AS menu
JOIN (
    SELECT 'yp/recipe_comment/index' AS `name`, '查看' AS `title`, 'ck' AS `py`, 'chakan' AS `pinyin`
    UNION ALL SELECT 'yp/recipe_comment/multi', '批量更新', 'plgx', 'pilianggengxin'
) AS child
WHERE menu.`name` = 'yp/recipe_comment'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `name` FROM `fa_auth_rule`) AS existing_rule WHERE existing_rule.`name` = child.`name`
  );

SET FOREIGN_KEY_CHECKS = 1;
