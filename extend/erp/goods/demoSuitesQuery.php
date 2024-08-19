<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->appsecret = '';
	
	$c->sid = '';
	$c->appkey = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/suites_query.php';


	//添加查询参数
	$c->putApiParam('page_size', '10');
	$c->putApiParam('suite_no','CE001');
	$json = $c->wdtOpenApi();
	print_r($json);
	
?>