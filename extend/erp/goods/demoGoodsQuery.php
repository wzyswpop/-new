<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/goods_query.php';

	//添加查询参数
	$c->putApiParam('start_time','2018-08-01 00:00:00');
	$c->putApiParam('end_time','2018-08-11 00:00:00');
	$json = $c->wdtOpenApi();
	var_dump($json);
	
?>