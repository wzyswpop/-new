<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/purchase_return_order_push.php';

	$purchase_return_info = array (
	 	"return_no" => "CR201610110002",
	    "outer_no" => "ghsf23",
	    "post_cost" => "0.01",
	    "logistics_code" => "TT",
	    "logistics_no" => "212123434354",
	    "detail_list" => array (
			array (
		    	"spec_no" => "kangxiwen1",
		    	"num" => 2,
		    	"position_no" => "A121",
		    	"price" => "0.01",
			)
	    )
	);

	$c->putApiParam('purchase_return_info',json_encode($purchase_return_info,JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>