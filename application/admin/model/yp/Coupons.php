<?php

namespace app\admin\model\yp;

use think\Model;


class Coupons extends Model
{

    

    

    // 表名
    protected $name = 'yp_coupons';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'goods_type_text',
        'endtime_text',
        'status_text'
    ];



    public function getGoodsTypeList()
    {
        return ['1' => __('Goods_type 1'), '2' => __('Goods_type 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getGoodsTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['goods_type']) ? $data['goods_type'] : '');
        $list = $this->getGoodsTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getEndtimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['endtime']) ? $data['endtime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }
}
