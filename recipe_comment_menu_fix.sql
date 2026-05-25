-- 配方墙短评菜单修复
-- 适用于线上没有 `yp` 父菜单、`yp/user_recipe` 为顶级菜单的 FastAdmin 后台。

SET NAMES utf8mb4;

INSERT INTO `fa_auth_rule`
(`type`, `pid`, `name`, `title`, `icon`, `condition`, `remark`, `ismenu`, `menutype`, `extend`, `py`, `pinyin`, `createtime`, `updatetime`, `weigh`, `status`)
SELECT 'file',
       IFNULL(user_recipe.`pid`, 0),
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
       IFNULL(user_recipe.`weigh`, 87) - 1,
       'normal'
FROM (SELECT 1) AS seed
LEFT JOIN `fa_auth_rule` AS user_recipe ON user_recipe.`name` = 'yp/user_recipe'
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment') AS menu
);

UPDATE `fa_auth_rule` AS menu
LEFT JOIN `fa_auth_rule` AS user_recipe ON user_recipe.`name` = 'yp/user_recipe'
SET menu.`pid` = IFNULL(user_recipe.`pid`, 0),
    menu.`title` = '配方墙短评',
    menu.`icon` = 'fa fa-comments',
    menu.`remark` = '查看和隐藏配方墙公开短评',
    menu.`ismenu` = 1,
    menu.`menutype` = 'addtabs',
    menu.`py` = 'pfqdp',
    menu.`pinyin` = 'peifangqiangduanping',
    menu.`weigh` = IFNULL(user_recipe.`weigh`, 87) - 1,
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

UPDATE `fa_auth_rule` AS child
INNER JOIN `fa_auth_rule` AS menu ON menu.`name` = 'yp/recipe_comment'
SET child.`pid` = menu.`id`,
    child.`ismenu` = 0,
    child.`status` = 'normal',
    child.`updatetime` = UNIX_TIMESTAMP()
WHERE child.`name` IN ('yp/recipe_comment/index', 'yp/recipe_comment/multi');

SET @user_recipe_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/user_recipe' LIMIT 1);
SET @recipe_comment_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment' LIMIT 1);
SET @recipe_comment_index_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment/index' LIMIT 1);
SET @recipe_comment_multi_id := (SELECT `id` FROM `fa_auth_rule` WHERE `name` = 'yp/recipe_comment/multi' LIMIT 1);

UPDATE `fa_auth_group`
SET `rules` = CONCAT_WS(',',
    `rules`,
    IF(FIND_IN_SET(CAST(@recipe_comment_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @recipe_comment_id IS NULL, NULL, @recipe_comment_id),
    IF(FIND_IN_SET(CAST(@recipe_comment_index_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @recipe_comment_index_id IS NULL, NULL, @recipe_comment_index_id),
    IF(FIND_IN_SET(CAST(@recipe_comment_multi_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci) OR @recipe_comment_multi_id IS NULL, NULL, @recipe_comment_multi_id)
)
WHERE `status` = 'normal'
  AND `rules` <> '*'
  AND @user_recipe_id IS NOT NULL
  AND FIND_IN_SET(CAST(@user_recipe_id AS CHAR) COLLATE utf8mb4_unicode_ci, CONVERT(`rules` USING utf8mb4) COLLATE utf8mb4_unicode_ci);
