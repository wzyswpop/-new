-- 配方社区第一版：配方墙字段与后台菜单
-- 适用于已有 fa_yp_user_recipe 表的增量升级。

ALTER TABLE `fa_yp_user_recipe`
  ADD COLUMN `description` varchar(255) NOT NULL DEFAULT '' COMMENT '配方简介' AFTER `baking`,
  ADD COLUMN `scene_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '适用场景标签' AFTER `description`,
  ADD COLUMN `flavor_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '风味标签' AFTER `scene_tags`,
  ADD COLUMN `author_name` varchar(80) NOT NULL DEFAULT '' COMMENT '公开作者名' AFTER `flavor_tags`,
  ADD COLUMN `author_title` varchar(80) NOT NULL DEFAULT '' COMMENT '作者称号' AFTER `author_name`,
  ADD COLUMN `public_status` enum('private','public') NOT NULL DEFAULT 'private' COMMENT '公开状态' AFTER `author_title`,
  ADD COLUMN `is_featured` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否精选' AFTER `public_status`,
  ADD COLUMN `featured_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '精选时间' AFTER `is_featured`,
  ADD COLUMN `copy_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '复刻次数' AFTER `featured_at`,
  ADD COLUMN `favorite_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '保存次数' AFTER `copy_count`,
  ADD COLUMN `feedback_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '反馈次数' AFTER `favorite_count`,
  ADD COLUMN `feedback_tags` text COMMENT '反馈标签统计' AFTER `feedback_count`,
  ADD KEY `idx_featured` (`is_featured`,`public_status`,`status`,`featured_at`);

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
       'jxpf',
       'jingxuanpeifang',
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
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
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
