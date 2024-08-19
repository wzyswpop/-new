<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockin_transfer_push.php';
	
	$stockin_info = array (
		'outer_no' => 'test',
		"src_order_type" => "2",
		'src_order_no' => 'TF201809030011',
		"warehouse_no" => "api_test",
	    "goods_list" => array (
	    	array (
				"spec_no" => "test-ptsd-00001",
			   	'num' => '1'
		     )
	    )
	);
	
	$c->putApiParam('stockin_info', json_encode($stockin_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>