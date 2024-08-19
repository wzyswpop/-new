<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/purchase_order_push.php';
	
	$purchase_info = array (
		"provider_no" => "2",
	    "warehouse_no" =>"001",
	    "outer_no" =>"ghsnsfs01nn",
	    "is_use_outer_no" =>"0",
	    "is_check" =>"1",
	    "contact" =>"旺旺旺",
	    "telno" =>"1333333333",
	    "receive_address" =>"天博中润掌上先机",
	    "logistics_type" =>"4",
	    "other_fee" =>"100.01",
	    "post_fee" =>"100.02",
	    "remark" =>"API测试",
	    "details_list" => array( 
			array(
		        "spec_no" =>"NESTX0002003",
		        "num" =>1,
		        "price" =>"10.11",
		        "discount" =>"10.22",
		        "tax" =>"0.2",
		        "remark" =>"API测试",
		        "prop1" =>"011",
		        "prop2" =>"022"
	        )
		)
	);
	
	$c->putApiParam('purchase_info', json_encode($purchase_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>