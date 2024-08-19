<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockout_order_query_trade.php';
	
	$c->putApiParam('status', 55);
	$c->putApiParam('start_time', '2017-04-01 00:00:00');
	$c->putApiParam('end_time', '2017-4-12 23:59:59');
	$c->putApiParam('page_no', '0');
	$c->putApiParam('page_size', '1');
	$json = $c->wdtOpenApi();
	var_dump($json);
?>