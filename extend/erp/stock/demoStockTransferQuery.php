<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stock_transfer_query.php';
	
	$c->putApiParam('start_time', '2018-05-05 00:00:00');
	$c->putApiParam('end_time', '2018-05-30 23:59:59');
	$c->putApiParam('status', '40');
	$json = $c->wdtOpenApi();
	var_dump($json);
?>