<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockout_transfer_push.php';
	
	$stockout_info = array (
		'src_order_type' => '2',
		'src_order_no' => 'TF201809030011',
		'warehouse_no' => 'xy001',
		'goods_list' => array (
			array (
				'spec_no' => 'test-ptsd-00001',
				'num' => '1'
			)
		)
	);
	
	$c->putApiParam('stockout_info', json_encode($stockout_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>