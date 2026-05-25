SET @table_name = 'fa_yp_goods';

SET @missing_is_shop_sale = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @table_name
    AND column_name = 'is_shop_sale'
);

SET @sql = (
  SELECT IF(
    @missing_is_shop_sale,
    'ALTER TABLE `fa_yp_goods` ADD COLUMN `is_shop_sale` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT ''是否商城出售:0=否,1=是'' AFTER `desc`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @missing_custom_status = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @table_name
    AND column_name = 'custom_status'
);

SET @sql = (
  SELECT IF(
    @missing_custom_status,
    'ALTER TABLE `fa_yp_goods` ADD COLUMN `custom_status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT ''定制状态:1=启用,2=停用'' AFTER `is_customized`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `fa_yp_goods`
SET
  `is_shop_sale` = IF(@missing_is_shop_sale, IF(`is_customized` = 1, 0, 1), `is_shop_sale`),
  `custom_status` = 1
WHERE `is_shop_sale` IS NULL
   OR `custom_status` IS NULL
   OR @missing_is_shop_sale;
