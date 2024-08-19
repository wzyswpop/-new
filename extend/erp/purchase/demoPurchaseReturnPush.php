<?php
	require_once("../WdtClient.php");
	$c = new WdtClient;
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/purchase_return_push.php';
	
	$return_info = array (
		"receiver_city" => "",
        "logistics_code" => "",
        "warehouse_no" => "api_test",
        "contact" => "",
        "post_fee" => "0",
        "telno" => "",
        "receiver_province" => "",
        "detail_list" => array (
            array (
                "spec_no" => "test-sd-ptpt-00001",
                "detail_remark" => "",
                "num" => "6.0000",
                "price" => "149.3097",
                "discount" => "0"
            )
		),
        "receiver_district" => "",
        "address" => "",
        "remark" => "",
        "outer_no" => "1525",
        "purchaser_no" => "",
        "provider_no" => "88888805"
	);
	
	$c->putApiParam('return_info', json_encode($return_info, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>