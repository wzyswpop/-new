<?php
	require_once ('../WdtClient.php');
	$c = new WdtClient();
	$c->sid = '';
	$c->appkey = '';
	$c->appsecret = '';
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/stock_sync_by_pd.php';
	
	$goods_list = array (
		array (
			"spec_no" => "99075",
			"stock_num" => 1
		)
	);
	
	$c->putApiParam('warehouse_no', '001');
	$c->putApiParam('is_adjust_stock', '0');
	$c->putApiParam('goods_list', json_encode($goods_list));
	$json = $c->wdtOpenApi();
	var_dump($json);
?>