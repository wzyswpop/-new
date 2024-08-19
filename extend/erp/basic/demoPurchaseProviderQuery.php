<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/purchase_provider_query.php';
	
	$c->putApiParam('column', 'provider_name,address,website,remark,is_disabled,deleted,modified,created');
	$c->putApiParam('page_no', 0);
	$c->putApiParam('page_size', 10);
	$c->putApiParam('provider_no', '88888805');
	$json = $c->wdtOpenApi();
	var_dump($json);
?>