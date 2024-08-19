<?php

namespace app\admin\model\yp;

use think\Model;


class Freight extends Model
{

    

    

    // 表名
    protected $name = 'yp_freight';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'valuation_text'
    ];
    

    
    public function getValuationList()
    {
        return ['0' => __('Valuation 0'), '1' => __('Valuation 1'), '2' => __('Valuation 2')];
    }


    public function getValuationTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['valuation']) ? $data['valuation'] : '');
        $list = $this->getValuationList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function freightdata()
    {
        return $this->hasMany(FreightData::class, 'freight_id', 'id', [], 'LEFT');
    }

}
