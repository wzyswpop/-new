<?php
	require_once '../WdtClient.php';
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/api_goodsspec_push.php';
	
	$api_goods_info = array( 
			'platform_id'=>127,
			'shop_no' => 'shop_test',
			'goods_list'=>array( 
				array (
					"status" => "1",
			        "goods_id" => "20151009100903",
			        "goods_no" => "xjftest002",
			        "cid" => "1",
			        "goods_name" => "test",
			        "price" => "1",
			        "stock_num" => "2",
			        "pic_url" => "",
			        "spec_id" => "20151009100903",
			        "spec_code" => "test002",
			        "spec_name" => "test",
			        "spec_no" => "xjftest004"
		       	)
			)
			
	);	

	$c->putApiParam('api_goods_info', json_encode($api_goods_info, JSON_UNESCAPED_UNICODE));
	$json = $c -> wdtOpenApi();
	var_dump($json);
?>