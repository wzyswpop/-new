<?php
	require_once '../WdtClient.php';
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/purchase_provider_create.php';
	
	$c->putApiParam('provider_no', 'xxx');
	$c->putApiParam('provider_name', 'xxx');
	$c->putApiParam('min_purchase_num', 1);
	$c->putApiParam('purchase_cycle_days', 1);
	$c->putApiParam('arrive_cycle_days', 1);
	$c->putApiParam('last_purchase_time', '2018-08-01 00:00:00');
	
	$json = $c->wdtOpenApi();
	var_dump($json);
?>