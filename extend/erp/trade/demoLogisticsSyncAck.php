<?php
	require_once('../WdtClient.php');

	$c = new WdtClient;
	$c->sid ='';
	$c->appkey ='';
	$c->appsecret ="";
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/logistics_sync_ack.php';

	$order_list[] = array(
		'rec_id'=>1,
		'status'=>0,
		'message'=>"同步成功"
	);
	$order_list[] = array(
		'rec_id'=>2,
		'status'=>1,
		'message'=>"同步失败"
	);
		

	$c->putApiParam('logistics_list',json_encode($order_list, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
	
?>