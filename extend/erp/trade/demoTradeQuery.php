<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/trade_query.php';


	$c->putApiParam('start_time','2020-11-09 17:55:09');
	$c->putApiParam('end_time','2020-11-09 17:55:19');
	$json = $c->wdtOpenApi();
	var_dump($json);
	
?>