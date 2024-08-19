<?php

namespace app\admin\model\yp;

use think\Model;


class FreightData extends Model
{
    // 表名
    protected $name = 'yp_freight_data';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    public function getProvinceAttr($value, $data)
    {
        return explode(',', $value);
    }

    public function getCityAttr($value, $data)
    {
        return explode(',', $value);
    }

}
