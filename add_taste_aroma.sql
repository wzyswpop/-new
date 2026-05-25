ALTER TABLE `fa_yp_goods`
  ADD COLUMN `taste_aroma` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '香气' AFTER `taste_sweetness`;
