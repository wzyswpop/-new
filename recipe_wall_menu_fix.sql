-- 配方墙后台菜单整理
-- 生成一个「配方墙」父入口，下面收纳「配方」「短评」两个子菜单。

SET NAMES utf8mb4;

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', 0, 'yp/recipe_wall', '配方墙', 'fa fa-flask', '', '管理配方墙内容与互动', 1, 'addtabs', '', 'pfq', 'peifangqiang', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), IFNULL(user_recipe.`weigh`, 87), 'normal'
FROM (SELECT 1) AS seed
LEFT JOIN `fa_auth_rule` AS user_recipe ON user_recipe.`name` = 'yp/user_recipe'
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_wall') AS wall
);

UPDATE `fa_auth_rule` AS wall
LEFT JOIN `fa_auth_rule` AS user_recipe ON user_recipe.`name` = 'yp/user_recipe'
SET wall.`pid` = 0,
    wall.`title` = '配方墙',
    wall.`icon` = 'fa fa-flask',
    wall.`remark` = '管理配方墙内容与互动',
    wall.`ismenu` = 1,
    wall.`menutype` = 'addtabs',
    wall.`py` = 'pfq',
    wall.`pinyin` = 'peifangqiang',
    wall.`weigh` = IFNULL(user_recipe.`weigh`, 87),
    wall.`status` = 'normal',
    wall.`updatetime` = UNIX_TIMESTAMP()
WHERE wall.`name` = 'yp/recipe_wall';

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', wall.`id`, 'yp/user_recipe', '配方', 'fa fa-list-alt', '', '管理用户公开配方的精选和下架状态', 1, 'addtabs', '', 'pf', 'peifang', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 20, 'normal'
FROM `fa_auth_rule` AS wall
WHERE wall.`name` = 'yp/recipe_wall'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_recipe') AS recipe_menu
  );

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS wall ON wall.`name` = 'yp/recipe_wall'
SET menu.`pid` = wall.`id`,
    menu.`title` = '配方',
    menu.`icon` = 'fa fa-list-alt',
    menu.`remark` = '管理用户公开配方的精选和下架状态',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`py` = 'pf',
    menu.`pinyin` = 'peifang',
    menu.`weigh` = 20,
    menu.`status` = 'normal',
    menu.`updatetime` = UNIX_TIMESTAMP()
WHERE menu.`name` = 'yp/user_recipe';

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', wall.`id`, 'yp/recipe_comment', '短评', 'fa fa-comments-o', '', '查看和隐藏配方墙公开短评', 1, 'addtabs', '', 'dp', 'duanping', UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 19, 'normal'
FROM `fa_auth_rule` AS wall
WHERE wall.`name` = 'yp/recipe_wall'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment') AS comment_menu
  );

UPDATE `fa_auth_rule` AS menu
INNER JOIN `fa_auth_rule` AS wall ON wall.`name` = 'yp/recipe_wall'
SET menu.`pid` = wall.`id`,
    menu.`title` = '短评',
    menu.`icon` = 'fa fa-comments-o',
    menu.`remark` = '查看和隐藏配方墙公开短评',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`py` = 'dp',
    menu.`pinyin` = 'duanping',
    menu.`weigh` = 19,
    menu.`status` = 'normal',
    menu.`updatetime` = UNIX_TIMESTAMP()
WHERE menu.`name` = 'yp/recipe_comment';

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file', menu.`id`, child.`name`, child.`title`, 'fa fa-circle-o', '', '', 0, NULL, '', child.`py`, child.`pinyin`, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), child.`weigh`, 'normal'
FROM `fa_auth_rule` AS menu
INNER JOIN (
    SELECT 'yp/user_recipe/index' AS `name`, '查看' AS `title`, 'ck' AS `py`, 'chakan' AS `pinyin`, 50 AS `weigh`, 'yp/user_recipe' AS `parent`
    UNION ALL SELECT 'yp/user_recipe/edit', '编辑', 'bj', 'bianji', 49, 'yp/user_recipe'
    UNION ALL SELECT 'yp/user_recipe/multi', '批量更新', 'plgx', 'pilianggengxin', 48, 'yp/user_recipe'
    UNION ALL SELECT 'yp/recipe_comment/index', '查看', 'ck', 'chakan', 50, 'yp/recipe_comment'
    UNION ALL SELECT 'yp/recipe_comment/multi', '批量更新', 'plgx', 'pilianggengxin', 49, 'yp/recipe_comment'
) AS child ON child.`parent` = menu.`name`
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT `name` FROM `fa_auth_rule`) AS existing_rule WHERE existing_rule.`name` = child.`name`
);

UPDATE `fa_auth_rule` AS child
INNER JOIN (
    SELECT 'yp/user_recipe/index' AS `name`, 'yp/user_recipe' AS `parent`, 50 AS `weigh`
    UNION ALL SELECT 'yp/user_recipe/edit', 'yp/user_recipe', 49
    UNION ALL SELECT 'yp/user_recipe/multi', 'yp/user_recipe', 48
    UNION ALL SELECT 'yp/recipe_comment/index', 'yp/recipe_comment', 50
    UNION ALL SELECT 'yp/recipe_comment/multi', 'yp/recipe_comment', 49
) AS spec ON spec.`name` = child.`name`
INNER JOIN `fa_auth_rule` AS menu ON menu.`name` = spec.`parent`
SET child.`pid` = menu.`id`,
    child.`icon` = 'fa fa-circle-o',
    child.`ismenu` = 0,
    child.`weigh` = spec.`weigh`,
    child.`status` = 'normal',
    child.`updatetime` = UNIX_TIMESTAMP();

SET @wall_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_wall' LIMIT 1);
SET @user_recipe_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_recipe' LIMIT 1);
SET @user_recipe_index_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_recipe/index' LIMIT 1);
SET @user_recipe_edit_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_recipe/edit' LIMIT 1);
SET @user_recipe_multi_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_recipe/multi' LIMIT 1);
SET @recipe_comment_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment' LIMIT 1);
SET @recipe_comment_index_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment/index' LIMIT 1);
SET @recipe_comment_multi_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment/multi' LIMIT 1);

UPDATE `fa_auth_group`
SET `rules` = CONCAT_WS(',',
    NULLIF(`rules`, ''),
    IF(FIND_IN_SET(CAST(@wall_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @wall_id IS NULL, NULL, @wall_id),
    IF(FIND_IN_SET(CAST(@user_recipe_index_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @user_recipe_index_id IS NULL, NULL, @user_recipe_index_id),
    IF(FIND_IN_SET(CAST(@user_recipe_edit_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @user_recipe_edit_id IS NULL, NULL, @user_recipe_edit_id),
    IF(FIND_IN_SET(CAST(@user_recipe_multi_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @user_recipe_multi_id IS NULL, NULL, @user_recipe_multi_id),
    IF(FIND_IN_SET(CAST(@recipe_comment_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @recipe_comment_id IS NULL, NULL, @recipe_comment_id),
    IF(FIND_IN_SET(CAST(@recipe_comment_index_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @recipe_comment_index_id IS NULL, NULL, @recipe_comment_index_id),
    IF(FIND_IN_SET(CAST(@recipe_comment_multi_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @recipe_comment_multi_id IS NULL, NULL, @recipe_comment_multi_id)
)
WHERE `status` = 'normal'
  AND `rules` <> '*'
  AND @user_recipe_id IS NOT NULL
  AND FIND_IN_SET(CAST(@user_recipe_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci);

SELECT `id`, `pid`, `name`, `title`, `icon`, `ismenu`, `menutype`, `weigh`, `status`
FROM `fa_auth_rule`
WHERE `name` IN (
    'yp/recipe_wall',
    'yp/user_recipe',
    'yp/user_recipe/index',
    'yp/user_recipe/edit',
    'yp/user_recipe/multi',
    'yp/recipe_comment',
    'yp/recipe_comment/index',
    'yp/recipe_comment/multi'
)
ORDER BY `pid` ASC, `weigh` DESC, `id` ASC;
