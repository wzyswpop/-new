SET @order_table = 'fa_yp_order';

SET @missing_order_type = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @order_table
    AND column_name = 'order_type'
);

SET @sql = (
  SELECT IF(
    @missing_order_type,
    'ALTER TABLE `fa_yp_order` ADD COLUMN `order_type` int(12) NOT NULL DEFAULT 0 COMMENT ''订单类型:0=普通订单,1=定制订单'' AFTER `user_id`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @missing_cash_money = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @order_table
    AND column_name = 'cash_money'
);

SET @sql = (
  SELECT IF(
    @missing_cash_money,
    'ALTER TABLE `fa_yp_order` ADD COLUMN `cash_money` decimal(10,2) DEFAULT ''0.00'' COMMENT ''微信支付的余额'' AFTER `order_money`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @item_table = 'fa_yp_order_item';

SET @missing_goods_category = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @item_table
    AND column_name = 'goods_category'
);

SET @sql = (
  SELECT IF(
    @missing_goods_category,
    'ALTER TABLE `fa_yp_order_item` ADD COLUMN `goods_category` varchar(255) DEFAULT NULL COMMENT ''商品分类'' AFTER `json`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @missing_unit_price = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @item_table
    AND column_name = 'unit_price'
);

SET @sql = (
  SELECT IF(
    @missing_unit_price,
    'ALTER TABLE `fa_yp_order_item` ADD COLUMN `unit_price` decimal(10,2) DEFAULT ''0.00'' COMMENT ''单价'' AFTER `goods_category`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @missing_weight = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @item_table
    AND column_name = 'weight'
);

SET @sql = (
  SELECT IF(
    @missing_weight,
    'ALTER TABLE `fa_yp_order_item` ADD COLUMN `weight` int(12) DEFAULT 0 COMMENT ''定制商品重量'' AFTER `unit_price`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @missing_baking = (
  SELECT COUNT(*) = 0
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = @item_table
    AND column_name = 'baking'
);

SET @sql = (
  SELECT IF(
    @missing_baking,
    'ALTER TABLE `fa_yp_order_item` ADD COLUMN `baking` varchar(255) DEFAULT '''' COMMENT ''烘焙程度'' AFTER `weight`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
