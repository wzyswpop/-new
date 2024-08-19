<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockin_order_query.php';
	
	$c->putApiParam('start_time', '2017-04-05 00:00:00');
	$c->putApiParam('end_time', '2017-04-06 23:59:59');
	$json = $c->wdtOpenApi();
	var_dump($json);
?>