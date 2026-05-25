-- 恢复后台左侧“优惠券”入口。
-- 后台优惠券代码已经存在：yp/coupons 和 yp/user_coupons。
-- 本脚本只修复 fa_auth_rule 菜单节点，不修改优惠券业务数据。

-- 1. 恢复一级菜单“优惠券”。
INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', 0, 'coupons', '优惠券', 'fa fa-ticket', '', '', 1, 'addtabs', '', 'yhq', 'youhuiquan', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 88, 'normal'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'coupons') AS existing_menu
);

UPDATE `fa_auth_rule`
SET `pid` = 0,
    `title` = '优惠券',
    `icon` = 'fa fa-ticket',
    `ismenu` = 1,
    `menutype` = 'addtabs',
    `weigh` = 88,
    `status` = 'normal',
    `updatetime` = UNIX_TIMESTAMP()
WHERE `name` = 'coupons';

-- 2. 恢复“优惠券 - 优惠券”管理入口。
INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', parent.`id`, 'yp/coupons', '优惠券', 'fa fa-circle-o', '', '', 1, 'addtabs', '', 'yhq', 'youhuiquan', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 'normal'
FROM `fa_auth_rule` AS parent
WHERE parent.`name` = 'coupons'
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/coupons') AS existing_menu
  );

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS parent ON parent.`name` = 'coupons'
SET menu.`pid` = parent.`id`,
    menu.`title` = '优惠券',
    menu.`icon` = 'fa fa-circle-o',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`weigh` = 10,
    menu.`status` = 'normal',
    menu.`updatetime` = UNIX_TIMESTAMP()
WHERE menu.`name` = 'yp/coupons';

-- 3. 恢复“优惠券 - 领取记录”入口。
INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', parent.`id`, 'yp/user_coupons', '领取记录', 'fa fa-circle-o', '', '', 1, 'addtabs', '', 'lqjl', 'lingqujilu', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 9, 'normal'
FROM `fa_auth_rule` AS parent
WHERE parent.`name` = 'coupons'
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_coupons') AS existing_menu
  );

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS parent ON parent.`name` = 'coupons'
SET menu.`pid` = parent.`id`,
    menu.`title` = '领取记录',
    menu.`icon` = 'fa fa-circle-o',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`weigh` = 9,
    menu.`status` = 'normal',
    menu.`updatetime` = UNIX_TIMESTAMP()
WHERE menu.`name` = 'yp/user_coupons';

-- 4. 补齐常用操作权限节点，避免按钮权限检查失败。
INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', parent.`id`, child.`name`, child.`title`, 'fa fa-circle-o', '', '', 0, NULL, '', child.`py`, child.`pinyin`, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), child.`weigh`, 'normal'
FROM `fa_auth_rule` AS parent
INNER JOIN (
    SELECT 'yp/coupons/index' AS `name`, '查看' AS `title`, 'zk' AS `py`, 'zhakan' AS `pinyin`, 50 AS `weigh`
    UNION ALL SELECT 'yp/coupons/add', '添加', 'tj', 'tianjia', 49
    UNION ALL SELECT 'yp/coupons/edit', '编辑', 'bj', 'bianji', 48
    UNION ALL SELECT 'yp/coupons/del', '删除', 'sc', 'shanchu', 47
    UNION ALL SELECT 'yp/coupons/multi', '批量更新', 'plgx', 'pilianggengxin', 46
    UNION ALL SELECT 'yp/coupons/detail', '详情', 'xq', 'xiangqing', 45
) AS child
WHERE parent.`name` = 'yp/coupons'
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT `id`, `name` FROM `fa_auth_rule`) AS existing_rule
      WHERE existing_rule.`name` = child.`name`
  );

UPDATE `fa_auth_rule` AS child
INNER JOIN `fa_auth_rule` AS parent ON parent.`name` = 'yp/coupons'
SET child.`pid` = parent.`id`,
    child.`ismenu` = 0,
    child.`status` = 'normal',
    child.`updatetime` = UNIX_TIMESTAMP()
WHERE child.`name` IN (
    'yp/coupons/index',
    'yp/coupons/add',
    'yp/coupons/edit',
    'yp/coupons/del',
    'yp/coupons/multi',
    'yp/coupons/detail'
);

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', parent.`id`, child.`name`, child.`title`, 'fa fa-circle-o', '', '', 0, NULL, '', child.`py`, child.`pinyin`, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), child.`weigh`, 'normal'
FROM `fa_auth_rule` AS parent
INNER JOIN (
    SELECT 'yp/user_coupons/index' AS `name`, '查看' AS `title`, 'zk' AS `py`, 'zhakan' AS `pinyin`, 50 AS `weigh`
    UNION ALL SELECT 'yp/user_coupons/add', '添加', 'tj', 'tianjia', 49
    UNION ALL SELECT 'yp/user_coupons/edit', '编辑', 'bj', 'bianji', 48
    UNION ALL SELECT 'yp/user_coupons/del', '删除', 'sc', 'shanchu', 47
    UNION ALL SELECT 'yp/user_coupons/multi', '批量更新', 'plgx', 'pilianggengxin', 46
) AS child
WHERE parent.`name` = 'yp/user_coupons'
  AND NOT EXISTS (
      SELECT 1
      FROM (SELECT `id`, `name` FROM `fa_auth_rule`) AS existing_rule
      WHERE existing_rule.`name` = child.`name`
  );

UPDATE `fa_auth_rule` AS child
INNER JOIN `fa_auth_rule` AS parent ON parent.`name` = 'yp/user_coupons'
SET child.`pid` = parent.`id`,
    child.`ismenu` = 0,
    child.`status` = 'normal',
    child.`updatetime` = UNIX_TIMESTAMP()
WHERE child.`name` IN (
    'yp/user_coupons/index',
    'yp/user_coupons/add',
    'yp/user_coupons/edit',
    'yp/user_coupons/del',
    'yp/user_coupons/multi'
);

-- 5. 查询确认菜单结构。
SELECT `id`, `pid`, `name`, `title`, `ismenu`, `menutype`, `weigh`, `status`
FROM `fa_auth_rule`
WHERE `name` IN (
    'coupons',
    'yp/coupons',
    'yp/coupons/index',
    'yp/coupons/add',
    'yp/coupons/edit',
    'yp/coupons/del',
    'yp/coupons/multi',
    'yp/coupons/detail',
    'yp/user_coupons',
    'yp/user_coupons/index',
    'yp/user_coupons/add',
    'yp/user_coupons/edit',
    'yp/user_coupons/del',
    'yp/user_coupons/multi'
)
ORDER BY `pid` ASC, `weigh` DESC, `id` ASC;
