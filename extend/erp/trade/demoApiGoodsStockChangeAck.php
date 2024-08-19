<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/api_goods_stock_change_ack.php';

	$stock_sync_list = array(
		array(
			'rec_id' => '1',
			'sync_stock' => '100',
        	'stock_change_count' => '5634245'
		)
	);

	$c->putApiParam('stock_sync_list',json_encode($stock_sync_list,JSON_UNESCAPED_UNICODE));
	$c->putApiParam('limit',100);
	$json = $c->wdtOpenApi();
	var_dump($json);
?>