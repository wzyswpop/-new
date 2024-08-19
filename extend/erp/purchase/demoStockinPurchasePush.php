<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockin_purchase_push.php';

	$purchase_info = array( 
		"purchase_no" => "CG201806160003",
	    "warehouse_no" => "001",
	    "outer_no" => "ghs123sfsag",
	    "purchase" => "xxxxx",

	    "details_list" => array(
			array(
			"spec_no" => "NESTX0002003",
			"stockin_price" => 12.2,
			"stockin_num" => 1,
			)
		)
	);

	$c->putApiParam('purchase_info',json_encode($purchase_info,JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>