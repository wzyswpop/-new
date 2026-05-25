-- 隐藏左侧菜单里的“商品分类”入口。
-- 商品列表页仍保留“标签与分类”按钮，继续打开同一个 yp/goods_category 管理页面。

UPDATE `fa_auth_rule`
SET `ismenu` = 0,
    `status` = 'hidden',
    `updatetime` = UNIX_TIMESTAMP()
WHERE `name` = 'yp/goods_category';

SELECT `id`, `pid`, `name`, `title`, `ismenu`, `status`
FROM `fa_auth_rule`
WHERE `name` = 'yp/goods_category';
