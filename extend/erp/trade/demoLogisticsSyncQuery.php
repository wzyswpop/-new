<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/logistics_sync_query.php';

	$c->putApiParam('shop_no','api_test');
	$c->putApiParam('is_part_sync_able',0);
	$c->putApiParam('limit',100);
	$json = $c->wdtOpenApi();
	var_dump($json);
	
?>