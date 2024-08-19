<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockout_order_query_return.php';
	
	$c->putApiParam('start_time', '2018-06-03 00:00:00');
	$c->putApiParam('end_time', '2018-06-21 20:13:41');
	$c->putApiParam('status', 40);
	$c->putApiParam('page_no', '0');
	$c->putApiParam('page_size', '10');
	$json = $c->wdtOpenApi();
	var_dump($json);
?>