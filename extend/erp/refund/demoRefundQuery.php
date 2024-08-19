<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/refund_query.php';
	
	$c->putApiParam('process_status', '90');
	$c->putApiParam('start_time', '2017-12-07 13:00:44');
	$c->putApiParam('end_time', '2017-12-07 13:59:44');
	$json = $c->wdtOpenApi();
	var_dump($json);	
?>
	