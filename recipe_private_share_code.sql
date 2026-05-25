-- 私有配方分享凭证
-- 可独立执行；默认表前缀为 fa_，如线上前缀不同，请先整体替换表名前缀。

SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS `hc_add_user_recipe_share_code`$$
CREATE PROCEDURE `hc_add_user_recipe_share_code`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fa_yp_user_recipe'
      AND COLUMN_NAME = 'share_code'
  ) THEN
    ALTER TABLE `fa_yp_user_recipe`
      ADD COLUMN `share_code` varchar(64) NOT NULL DEFAULT '' COMMENT '私有分享凭证' AFTER `feedback_tags`;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `hc_add_user_recipe_share_code_index`$$
CREATE PROCEDURE `hc_add_user_recipe_share_code_index`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fa_yp_user_recipe'
      AND INDEX_NAME = 'uniq_share_code'
  ) THEN
    ALTER TABLE `fa_yp_user_recipe`
      ADD UNIQUE KEY `uniq_share_code` (`share_code`);
  END IF;
END$$

DELIMITER ;

CALL `hc_add_user_recipe_share_code`();

UPDATE `fa_yp_user_recipe`
SET `share_code` = MD5(CONCAT(`id`, '-', `user_id`, '-', `createtime`, '-', RAND()))
WHERE `share_code` = '';

CALL `hc_add_user_recipe_share_code_index`();

DROP PROCEDURE IF EXISTS `hc_add_user_recipe_share_code`;
DROP PROCEDURE IF EXISTS `hc_add_user_recipe_share_code_index`;
