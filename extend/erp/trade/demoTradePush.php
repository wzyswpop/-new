<?php
	require_once('../WdtClient.php');

	$c = new WdtClient;
	$c->sid ='';
	$c->appkey ='';
	$c->appsecret ="";
	$c->gatewayUrl = 'https://sandbox.wangdian.cn/openapi2/trade_push.php';

	$trade_list[] = array
	(
		'tid'              => 'LxTestTid'.time(),
		'trade_status'     => 30,
		'delivery_term'    => 1,
		'pay_status'	   => 2,
		'trade_time'       => '0000-00-00 00:00:00',
		'pay_time'         => '0000-00-00 00:00:00', // 未付款情况下为0000-00-00 00:00:00
		'buyer_nick'       => '',
		'buyer_email'      => '123456234533@mail.com',
		'receiver_mobile'  => '13233456110',
		'receiver_telno'   => '1234563567',
		'receiver_zip'     => '0000000',
		'receiver_province'=>'北京',
		'receiver_name'    =>'亚历山大',
		'receiver_city'    =>'北京市',
		'receiver_district'=>'海淀区',
		'receiver_address' =>'海淀',
		'logistics_type'   => 4, // ems
		'invoice_type'     => 0,
		'invoice_title'    => '',
		'invoice_content'  => '发票内容+',
		'buyer_message'    => '发最好&&&的+',
		'cust_data'        => '72-500.0;84-368.0;67-258.0;65-99.0;87-158.0;',
		'remark'           => '测试专用',
		'remark_flag'      => 1,
		'post_amount'      => 10, //邮费
		'paid'             => 409, //已支付金额
		'cod_amount'       => '0',
		'ext_cod_fee'      => '0',
		'order_list'       => array(
			array
			(
				'oid'            => 'LxTestOid'.time(),
				'status'         => 30,
				'refund_status'  => 0,
				'goods_id'       => 'E166D18BAAEA420CB132E105B3B6128A',
				'spec_id'        => '',
				'goods_no'       => '',
				'spec_no'        => '9787533951092',
				'goods_name'     => '情商是什么？——关于生活智慧的44个故事',
				'spec_name'      => '',
				'num'            => 1,
				'price'          => 399,
				'adjust_amount'  => '0', //手工调整,特别注意:正的表示加价,负的表示减价
				'discount'       => 0, //子订单折扣
				'share_discount' => '0', //分摊优惠
				'cid'            => '13',
			)
		)
	);
		
	$c->putApiParam('shop_no','api_test');
	$c->putApiParam('switch',0);
	$c->putApiParam('trade_list',json_encode($trade_list, JSON_UNESCAPED_UNICODE));
	$json = $c->wdtOpenApi();
	var_dump($json);
	
?>