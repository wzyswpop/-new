<?php
	require_once '../WdtClient.php';
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/goods_push.php';
	
	$goods_list[] =  array
	(
		"goods_no" => "test001",
		"goods_type" => 1,
		"goods_name" => "test",
		"spec_list" => array ( array(
	        "spec_no" => "ghs_123",
	        "spec_code" => "test001_01",
	        "barcode" => "test001",
	        "spec_name" => "test",
	        "lowest_price" => 1,
	        "img_url" => 'http://baidu.com',
	        "retail_price" => 1,
	        "wholesale_price" => 1,
	        "member_price" => 1,
	        "market_price" => 1,
	        "sale_score" => 1,
	        "pack_score" => 1,
	        "pick_score" => 1,
	        "validity_days" => "2015-07-06 00:00:01",
			)
		)
	);
	$c->putApiParam('goods_list', json_encode($goods_list), JSON_UNESCAPED_UNICODE);
	$json = $c->wdtOpenApi();
	var_dump($json);
?>