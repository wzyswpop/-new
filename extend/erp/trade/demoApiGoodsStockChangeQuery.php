<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/api_goods_stock_change_query.php';

	//添加查询参数
	$c->putApiParam('shop_no','api_test');
	$c->putApiParam('limit',100);
	$json = $c->wdtOpenApi();
	var_dump($json);
?>