<?php

namespace app\admin\model\yp;

use think\Model;

class SkuPrice extends Model
{
    // 表名
    protected $name = 'yp_goods_sku_price';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 追加属性
    protected $append = [
        'goods_sku_text',
        'store_take_arr',
        'town_take_arr'
    ];
//
//    public function getImageAttr($value, $data)
//    {
//        if (!empty($value)) return cdnurl($value, true);
//        return $value;
//
//    }

    public function getGoodsSkuTextAttr($value, $data)
    {
        return array_filter(explode(',', $value));
    }

    public function getStoreTakeArrAttr($value, $data)
    {
        return (isset($data['store_take']) && $data['store_take']) ? json_decode($data['store_take'], true) : [];
    }

    public function getTownTakeArrAttr($value, $data)
    {
        return (isset($data['town_take']) && $data['town_take']) ? json_decode($data['town_take'], true) : [];
    }

    protected function setStoreTakeAttr($value)
    {
        return $value && is_array($value) ? json_encode($value) : $value;
    }

    protected function setTownTakeAttr($value)
    {
        return $value && is_array($value) ? json_encode($value) : $value;
    }
}
