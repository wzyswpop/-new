UPDATE `fa_auth_rule` AS old_menu
SET old_menu.`name` = 'user/user/referral_accounts'
WHERE old_menu.`name` = 'user/user/referralAccounts'
AND NOT EXISTS (
    SELECT 1
    FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'user/user/referral_accounts') AS new_menu
);

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', parent.`id`, 'user/user/referral_accounts', '推广者管理', 'fa fa-sitemap', '', '', 1, 'addtabs', '', 'tgzgl', 'tuiguangzheguanli', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 99, 'normal'
FROM `fa_auth_rule` AS parent
WHERE parent.`name` = 'user'
AND NOT EXISTS (
    SELECT 1
    FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'user/user/referral_accounts') AS menu
);

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS parent ON parent.`name` = 'user'
SET menu.`pid` = parent.`id`,
    menu.`title` = '推广者管理',
    menu.`icon` = 'fa fa-sitemap',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`weigh` = 99,
    menu.`status` = 'normal'
WHERE menu.`name` = 'user/user/referral_accounts';

UPDATE `fa_auth_rule` AS old_menu
SET old_menu.`ismenu` = 0,
    old_menu.`status` = 'hidden'
WHERE old_menu.`name` = 'user/user/referralAccounts'
AND EXISTS (
    SELECT 1
    FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'user/user/referral_accounts') AS new_menu
);

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', menu.`id`, 'user/user/referrals', '推荐用户明细', 'fa fa-circle-o', '', '', 0, NULL, '', 'tjyhmx', 'tuijianyonghumingxi', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'
FROM `fa_auth_rule` AS menu
WHERE menu.`name` = 'user/user/referral_accounts'
AND NOT EXISTS (
    SELECT 1
    FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'user/user/referrals') AS referral_rule
);

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', menu.`id`, 'user/user/referral_rate', '设置推荐比例', 'fa fa-circle-o', '', '', 0, NULL, '', 'sztjbl', 'shezhituijianbili', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0, 'normal'
FROM `fa_auth_rule` AS menu
WHERE menu.`name` = 'user/user/referral_accounts'
AND NOT EXISTS (
    SELECT 1
    FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'user/user/referral_rate') AS referral_rate_rule
);
