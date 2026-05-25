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
        'status',
        'channel_text',
        'shop_status_text',
        'custom_status_text'
    ];
    

    
    public function getIsHotList()
    {
        return ['1' => __('Is_hot 1'), '2' => __('Is_hot 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getShopStatusList()
    {
        return ['1' => '上架', '2' => '下架'];
    }

    public function getCustomStatusList()
    {
        return ['1' => '启用', '2' => '停用'];
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

    public function getChannelTextAttr($value, $data)
    {
        $isShop = isset($data['is_shop_sale']) ? (int)$data['is_shop_sale'] : ((isset($data['is_customized']) && (int)$data['is_customized'] === 1) ? 0 : 1);
        $isCustom = isset($data['is_customized']) ? (int)$data['is_customized'] : 0;
        if ($isShop && $isCustom) {
            return '双渠道';
        }
        if ($isShop) {
            return '商城';
        }
        if ($isCustom) {
            return '定制';
        }
        return '归档';
    }

    public function getShopStatusTextAttr($value, $data)
    {
        $isShop = isset($data['is_shop_sale']) ? (int)$data['is_shop_sale'] : ((isset($data['is_customized']) && (int)$data['is_customized'] === 1) ? 0 : 1);
        return $isShop ? '上架' : '下架';
    }

    public function getCustomStatusTextAttr($value, $data)
    {
        $isCustom = isset($data['is_customized']) ? (int)$data['is_customized'] : 0;
        if (!$isCustom) {
            return '未启用';
        }
        $status = isset($data['custom_status']) ? (string)$data['custom_status'] : '1';
        $list = $this->getCustomStatusList();
        return isset($list[$status]) ? $list[$status] : '启用';
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
