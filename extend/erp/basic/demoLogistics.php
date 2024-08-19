<?php
	require_once '../WdtClient.php';
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/logistics.php';
	
	$c->putApiParam('logistics_no', 11);
	$json = $c->wdtOpenApi();
	var_dump($json);
?>