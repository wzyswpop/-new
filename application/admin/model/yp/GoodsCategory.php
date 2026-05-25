<?php

namespace app\admin\model\yp;

use think\Model;


class GoodsCategory extends Model
{

    

    

    // 表名
    protected $name = 'yp_goods_category';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'shows_text'
    ];

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getShowsList()
    {
        return ['1' => __('显示'), '0' => __('不显示')];
    }


    public function getShowsTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['shows']) ? $data['shows'] : '');
        $list = $this->getShowsList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
