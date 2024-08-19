<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/sales_refund_push.php';

	$api_refund_list = array( array(
			"tid" => "test00053120009-3",
		    "shop_no" => "api_test",
		    "platform_id" => 127,
		    "refund_no" => "6",
		    "type" => "2",
		    "status" => "success",
		    "refund_fee" => "",
		    "alipay_no" => "mytest",
		    "buyer_nick" => "",
		    "refund_time" => "1212121",
		    "reason" => "测试者",
		    "desc" => "北京",
			"refund_version" => "北京市",
		    "order_list" => array(
				array(
		            "oid" => "test0005-01-03",
		            "num" => 2
				)
		    )
	    )
	);

	$c->putApiParam('api_refund_list',json_encode($api_refund_list,JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>