ALTER TABLE `fa_yp_withdrawal`
  ADD COLUMN `package_info` varchar(512) NOT NULL DEFAULT '' COMMENT '微信确认收款包' AFTER `out_detail_no`,
  ADD COLUMN `transfer_state` varchar(32) NOT NULL DEFAULT '' COMMENT '微信商家转账状态' AFTER `package_info`,
  ADD COLUMN `transfer_fail_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '微信转账失败原因' AFTER `transfer_state`;

INSERT INTO `fa_config`
  (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES
  ('transfer_scene_id', 'basic', '商家转账场景ID', '微信支付商家转账产品中配置的场景ID，默认1005', 'string', '', '1005', '', '', '', '')
ON DUPLICATE KEY UPDATE `value` = IF(`value` = '', VALUES(`value`), `value`);

UPDATE `fa_config`
SET `value` = '[{\"info_type\":\"岗位类型\",\"info_content\":\"分销员\"},{\"info_type\":\"报酬说明\",\"info_content\":\"佣金提现\"}]'
WHERE `name` = 'transfer_scene_report_infos' AND (`value` IS NULL OR `value` = '');

INSERT INTO `fa_config`
  (`name`, `group`, `title`, `tip`, `type`, `visible`, `value`, `content`, `rule`, `extend`, `setting`)
VALUES
  ('transfer_scene_report_infos', 'basic', '商家转账场景报备信息', '按微信支付商户平台对应场景要求填写JSON数组；佣金报酬1005需包含岗位类型、报酬说明', 'text', '', '[{\"info_type\":\"岗位类型\",\"info_content\":\"分销员\"},{\"info_type\":\"报酬说明\",\"info_content\":\"佣金提现\"}]', '', '', '', '')
ON DUPLICATE KEY UPDATE `value` = IF(`value` = '', VALUES(`value`), `value`);
