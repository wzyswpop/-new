<?php

namespace app\admin\model\yp;

use think\Model;
use fast\Tree;


class Goods extends Model
{

    

    

    // 表名
    protected $name = 'yp_goods';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_hot_text',
        'status_text',
        'images_arr',
        'status'
    ];
    

    
    public function getIsHotList()
    {
        return ['1' => __('Is_hot 1'), '2' => __('Is_hot 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getIsHotTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_hot']) ? $data['is_hot'] : '');
        $list = $this->getIsHotList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getStatusAttr($value, $data)
    {
        return (int)$value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getImagesArrAttr($value, $data)
    {
        $imagesArray = [];
        if (!empty($data['images'])) {
            $imagesArray = explode(',', $data['images']);
            return $imagesArray;
        }
        return $imagesArray;
    }

    public function getCategoryIdsArrAttr($value, $data)
    {
        $arr = $data['category_id'] ? explode(',', $data['category_id']) : [];
        foreach ($arr as &$v){
            $v = (int)$v;
        }
        return [$arr];
    }


    public function category()
    {
        return $this->belongsTo(GoodsCategory::class, 'category_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
