<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockin_order_push.php';
	
	$stockin_info = array (
		'outer_no' => 'ghs123safag',
		"warehouse_no" => "WH001",
	    "post_fee" => "0.00",
	    "other_fee" => "0.00",
	    "discount" => "0.00",
	    "goods_list" => array (
	    	array (
				"spec_no" => "test-ptsd-00001",
				"stockin_num" => 2,
			    "stockin_price" => 0.00,
			    "position_no" => "ZANCUN",
			    "batch_no" => "",
			    "production_date" => "2015-06-09 00:00:01",
			    "validity_days" => 3,
			    "price" => 0.00,
			    "tax" => 0.00
		     )
	    )
	);
	
	$c->putApiParam('stockin_info', json_encode($stockin_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>