-- 将“用户 - 分享海报图”指向专用页面：user/share_poster
-- 这个页面只负责小程序「我的 - 分享夯萃小程序」的海报图与分享标题。

UPDATE `fa_auth_rule`
SET `ismenu` = 0,
    `status` = 'hidden',
    `updatetime` = UNIX_TIMESTAMP()
WHERE `title` IN ('分享', '分享图', '分享海报图')
  AND `name` <> 'user/share_poster';

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file',
       user_parent.`id`,
       'user/share_poster',
       '分享海报图',
       'fa fa-share-alt',
       '',
       '管理小程序「我的 - 分享夯萃小程序」海报图',
       1,
       'addtabs',
       '',
       'fxhbt',
       'fenxianghaibaotu',
       UNIX_TIMESTAMP(),
       UNIX_TIMESTAMP(),
       86,
       'normal'
FROM `fa_auth_rule` AS user_parent
WHERE user_parent.`name` = 'user'
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'user/share_poster') AS existing_menu
  );

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS user_parent ON user_parent.`name` = 'user'
SET menu.`pid` = user_parent.`id`,
    menu.`title` = '分享海报图',
    menu.`icon` = 'fa fa-share-alt',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`weigh` = 86,
    menu.`status` = 'normal',
    menu.`updatetime` = UNIX_TIMESTAMP()
WHERE menu.`name` = 'user/share_poster';

SELECT `id`, `pid`, `name`, `title`, `ismenu`, `menutype`, `status`
FROM `fa_auth_rule`
WHERE `name` = 'user/share_poster'
   OR `title` IN ('分享', '分享图', '分享海报图');
