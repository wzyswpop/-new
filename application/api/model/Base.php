<?php
namespace app\api\model;

use think\Model;

class Base extends Model{

    public static $order_status = ['已取消','未支付','待发货','待收货','已完成','售后','售后完成'];
    public static $refund_status = [1 => '待审核','同意','拒绝'];
    public static $integral_order_status = ['已取消','待发货','待收货','已完成'];
    public static $sign_order_status = ['已取消','待支付','待发货','待收货','已完成'];

    /**
     * 积分订单状态
     */
    public function getSignStatusAttr($value,$row){
        return self::$sign_order_status[$row['status']];
    }

    /**
     * 积分订单状态
     */
    public function getIntegralStatusAttr($value,$row){
        return self::$integral_order_status[$row['status']];
    }

    /**
     * 商品分类
     */
    public function getGoodsCategoryAttr($value,$row){
        if(empty($row['category_id'])){
            return '';
        }
        return GoodsCategory::where(['id' => $row['category_id']])->value('name');
    }

    /**
     * 订单状态
     */
    public function getOrderStatusAttr($value,$row){
        return self::$order_status[$row['status']];
    }

    /**
     * 退款订单状态
     */
    public function getRefundStatusAttr($value,$row){
        return self::$refund_status[$row['status']];
    }
}