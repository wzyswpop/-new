<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/shop.php';
	
	$c->putApiParam('shop_no', 'api_test');
	$json = $c->wdtOpenApi();
	var_dump($json);
?>