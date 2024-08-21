<?php

namespace app\api\validate;

use think\Validate;

class Order extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'goods_list' => 'require|array',
        'address_id' => 'require',
        'id' => 'require',
        'status' => 'require|in:all,1,2,3,4',
        'remarks' => 'max:150'
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'goods_list.require' => '未选择商品',
        'goods_list.array' => '未选择商品',
        'address_id.requireIf' => '收货地址不能为空',
        'id.require' => '参数错误',
        'status.require' => '参数错误',
        'status.in' => '参数错误',
        'remarks.max' => '备注长度错误'
    ];

    /**
     * 字段描述
     */
    protected $field = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'carconfirmorder' => ['cart_id'],
        'confirmorder' => ['goods_list'],
        'createorder'  => ['goods_list','address_id','remarks'],
        'details' => ['id'],
        'orderlist' => ['status'],
        'cancelorder' => ['id'],
        'receiving' => ['id'],
        'kuaidi' => ['id']
    ];

}
