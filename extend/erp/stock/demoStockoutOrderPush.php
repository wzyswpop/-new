<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stockout_order_push.php';
	
	$stockout_info = array (
		'outer_no' => 'ghs_12',
		"warehouse_no" => "001",
		'num' => '1',
		"remark" => "测试新增其他出库单",
	    "detail_list" => array (
	    	array (
				"spec_no" => "test-ptsd-00001",
			   	'num' => '1',
	    		'price' => '12'
		     )
	    )
	);
	
	$c->putApiParam('stockout_info', json_encode($stockout_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>