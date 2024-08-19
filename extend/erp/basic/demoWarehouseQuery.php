<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/warehouse_query.php';
	
	//$c->putApiParam('warehouse_no', 'octmami');
	$c->putApiParam('warehouse_type', '1');
	$c->putApiParam('start_time', '2016-06-20 10:00:47');
	$c->putApiParam('end_time', '2016-06-20 10:59:59');
	$json = $c->wdtOpenApi();
	var_dump($json);
?>