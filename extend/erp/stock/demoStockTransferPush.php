<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stock_transfer_push.php';
	
	$transfer_info = array (
		'outer_no' => 'ghs_001',
		'from_warehouse_no' => '001',
		'to_warehouse_no' => '001',
		'skus' => array (
			array (
				'spec_no' => 'NESTX0002003',
				'num' => '1'
			)
		)
	);
	
	$c->putApiParam('transfer_info', json_encode($transfer_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>