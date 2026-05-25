ALTER TABLE `fa_user`
ADD COLUMN `distribution_rate` decimal(5,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '推荐佣金比例' AFTER `commission`;
