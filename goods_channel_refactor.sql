ALTER TABLE `fa_yp_goods`
  ADD COLUMN `is_shop_sale` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '是否商城出售:0=否,1=是' AFTER `desc`,
  ADD COLUMN `shop_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商城显示名称' AFTER `is_shop_sale`,
  ADD COLUMN `custom_name` varchar(255) NOT NULL DEFAULT '' COMMENT '定制显示名称' AFTER `shop_name`,
  ADD COLUMN `custom_status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '定制状态:1=启用,2=停用' AFTER `is_customized`,
  ADD COLUMN `custom_flavour_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '定制风味标签' AFTER `customized_price`,
  ADD COLUMN `blend_role` varchar(64) NOT NULL DEFAULT '' COMMENT '拼配角色' AFTER `custom_flavour_tags`,
  ADD COLUMN `taste_acidity` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '酸度' AFTER `blend_role`,
  ADD COLUMN `taste_sweetness` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '甜感' AFTER `taste_acidity`,
  ADD COLUMN `taste_aroma` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '香气' AFTER `taste_sweetness`,
  ADD COLUMN `taste_body` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '醇厚度' AFTER `taste_aroma`,
  ADD COLUMN `taste_aftertaste` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '余韵' AFTER `taste_body`,
  ADD COLUMN `recommend_ratio` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '推荐比例' AFTER `taste_aftertaste`,
  ADD COLUMN `allow_ai_recommend` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '允许AI推荐:0=否,1=是' AFTER `recommend_ratio`,
  ADD COLUMN `allow_manual_select` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '允许用户手动选择:0=否,1=是' AFTER `allow_ai_recommend`,
  ADD COLUMN `custom_pricing_method` varchar(32) NOT NULL DEFAULT 'weight' COMMENT '定制计价方式' AFTER `allow_manual_select`;

UPDATE `fa_yp_goods`
SET
  `is_shop_sale` = IF(`is_customized` = 1, 0, 1),
  `shop_name` = IFNULL(`name`, ''),
  `custom_name` = IFNULL(`name`, ''),
  `custom_status` = 1,
  `custom_flavour_tags` = IFNULL(`special_flavour`, ''),
  `custom_pricing_method` = 'weight';
