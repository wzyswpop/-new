<?php

namespace app\api\model;

use think\Model;

class ServiceOrder extends Model
{

    protected $name = 'yp_service_order';

    public $type = [1 => '仅退款',2 => '退款退货'];
    public $return_goods = ['买家取消','等待卖家确认','等待用户退货','用户已发货','卖家拒绝收货','卖家确认收货','卖家拒绝'];
    public $return_money = ['买家取消','退款审核中','退款成功','退款驳回'];

    /**
     * 退货退款
     */
    public function getReturnTextAttr($value,$row){
        if($row['return_goods'] == null){
            return '';
        }
        return $this->return_goods[$row['return_goods']];
    }

    /**
     * 退款
     */
    public function getReturnMoneyTextAttr($value,$row){
        if($row['return_money'] == null){
            return '';
        }
        return $this->return_money[$row['return_money']];
    }

    /**
     * 售后类型
     */
    public function getTypeTextAttr($value,$row){
        return $this->type[$row['type']];
    }

    public function orders(){
        return $this->belongsTo(Order::class,'order_id','id');
    }

    public function item(){
        return $this->hasMany(OrderItem::class,'order_id','order_id');
    }
}
