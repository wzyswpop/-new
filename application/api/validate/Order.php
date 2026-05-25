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
        'order_type' => 'require|in:mall,shop,normal,custom,customized,dingzhi,0,1',
        'address_id' => 'require',
        'id' => 'require',
        'status' => 'require|in:all,1,2,3,4',
        'remarks' => 'max:150',
        'recipe_name' => 'max:30',
        'recipe_total_weight' => 'number'
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'goods_list.require' => '未选择商品',
        'goods_list.array' => '未选择商品',
        'order_type.require' => '订单类型不能为空',
        'order_type.in' => '订单类型错误',
        'address_id.requireIf' => '收货地址不能为空',
        'id.require' => '参数错误',
        'status.require' => '参数错误',
        'status.in' => '参数错误',
        'remarks.max' => '备注长度错误',
        'recipe_name.max' => '配方名称长度错误',
        'recipe_total_weight.number' => '配方重量错误'
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
        'confirmorder' => ['goods_list','order_type'],
        'createorder'  => ['goods_list','order_type','address_id','remarks','recipe_name','recipe_total_weight'],
        'details' => ['id'],
        'orderlist' => ['status'],
        'cancelorder' => ['id'],
        'receiving' => ['id'],
        'kuaidi' => ['id']
    ];

}
