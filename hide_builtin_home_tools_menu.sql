-- 隐藏后台左侧“首页”下偏系统维护性质的入口。
-- 只关闭菜单展示，保留路由/权限正常可用：
-- 1. general/attachment 仍支撑上传组件、附件选择弹窗、历史文件管理能力。
-- 2. general/profile 仍可通过右上角头像菜单进入个人资料。

UPDATE `fa_auth_rule`
SET `ismenu` = 0,
    `updatetime` = UNIX_TIMESTAMP()
WHERE `name` IN ('general/attachment', 'general/profile');

SELECT `id`, `pid`, `name`, `title`, `ismenu`, `status`
FROM `fa_auth_rule`
WHERE `name` IN ('general/attachment', 'general/profile');
