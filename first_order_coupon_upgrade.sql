ALTER TABLE `fa_yp_coupons`
  ADD COLUMN `is_first_order` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否首单自动发放:0=否,1=是' AFTER `status`,
  ADD KEY `idx_first_order_status` (`is_first_order`, `status`);

ALTER TABLE `fa_yp_user_coupons`
  ADD COLUMN `source` varchar(30) NOT NULL DEFAULT 'manual' COMMENT '来源:manual=手动领取,first_order=首单自动发放' AFTER `status`,
  ADD COLUMN `source_pid` int(10) NOT NULL DEFAULT '0' COMMENT '来源推广员id' AFTER `source`,
  ADD COLUMN `grant_scene` varchar(30) NOT NULL DEFAULT '' COMMENT '发放场景' AFTER `source_pid`,
  ADD KEY `idx_user_source` (`user_id`, `source`),
  ADD KEY `idx_source_pid` (`source`, `source_pid`);
