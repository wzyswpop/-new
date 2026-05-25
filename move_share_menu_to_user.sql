-- 把原来“首页”下面真正负责小程序分享图/标题的“分享”菜单移动到“用户”下面。
-- 注意：这不是首页轮播图模块，不会移动 banner/轮播图数据。

-- 如果之前误执行过旧的 share_poster_menu.sql，先隐藏错误新增的轮播图代理菜单。
UPDATE `fa_auth_rule`
SET `ismenu` = 0,
    `status` = 'hidden',
    `updatetime` = UNIX_TIMESTAMP()
WHERE `name` = 'user/share_poster';

-- 如果之前误把首页轮播图菜单隐藏了，这里恢复它。
UPDATE `fa_auth_rule`
SET `ismenu` = 1,
    `status` = 'normal',
    `updatetime` = UNIX_TIMESTAMP()
WHERE `name` = 'banner';

-- 移动现有“分享”菜单本身，保留它原来的 name/route/controller，只换父级与展示文案。
UPDATE `fa_auth_rule` AS share_menu
INNER JOIN `fa_auth_rule` AS user_parent ON user_parent.`name` = 'user'
SET share_menu.`pid` = user_parent.`id`,
    share_menu.`title` = '分享海报图',
    share_menu.`icon` = 'fa fa-share-alt',
    share_menu.`ismenu` = 1,
    share_menu.`menutype` = 'addtabs',
    share_menu.`weigh` = 85,
    share_menu.`status` = 'normal',
    share_menu.`updatetime` = UNIX_TIMESTAMP()
WHERE share_menu.`title` IN ('分享', '分享图', '分享海报图')
  AND share_menu.`ismenu` = 1
  AND share_menu.`name` <> 'user/share_poster';

-- 执行后可用这条确认菜单是否已移动到“用户”下面。
SELECT share_menu.`id`,
       share_menu.`pid`,
       user_parent.`id` AS user_pid,
       share_menu.`name`,
       share_menu.`title`,
       share_menu.`ismenu`,
       share_menu.`status`
FROM `fa_auth_rule` AS share_menu
LEFT JOIN `fa_auth_rule` AS user_parent ON user_parent.`name` = 'user'
WHERE share_menu.`title` IN ('分享', '分享图', '分享海报图')
   OR share_menu.`name` = 'user/share_poster';
