-- 将后台菜单“定制方案”改为与前端一致的“热门配方”。
-- 只改菜单展示文案，不改 customize 路由、权限名或数据表。

UPDATE `fa_auth_rule`
SET `title` = '热门配方',
    `py` = 'rmpf',
    `pinyin` = 'remenpeifang',
    `updatetime` = UNIX_TIMESTAMP()
WHERE `name` = 'customize';

SELECT `id`, `pid`, `name`, `title`, `py`, `pinyin`, `ismenu`, `status`
FROM `fa_auth_rule`
WHERE `name` = 'customize';
