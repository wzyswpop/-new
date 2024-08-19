<?php
	require_once '../WdtClient.php';
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockin_refund_push.php';
	
	$stockin_refund_info = array (
		"refund_no" => "TK1608030001",
		"outer_no" => "ghs",
	    "warehouse_no" => "xy001",
	    "logistics_code" => "yjwl04",
	    "detail_list"  => array( array(
			    "spec_no" => "33412",
			    "stockin_num" => 2,
			    "batch_no" => "20160601",
			    "stockin_price" => "0.01"
		    )
     	)
	);
	$c->putApiParam('stockin_refund_info', json_encode($stockin_refund_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>