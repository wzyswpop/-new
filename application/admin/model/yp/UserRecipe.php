<?php

namespace app\admin\model\yp;

use think\Model;

class UserRecipe extends Model
{
    protected $name = 'yp_user_recipe';

    protected $autoWriteTimestamp = 'integer';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    protected $append = [
        'public_status_text',
        'is_featured_text',
        'status_text',
        'featured_at_text'
    ];

    public function getPublicStatusList()
    {
        return ['private' => '私有', 'public' => '公开'];
    }

    public function getIsFeaturedList()
    {
        return ['0' => '未精选', '1' => '精选'];
    }

    public function getStatusList()
    {
        return ['normal' => '正常', 'hidden' => '下架'];
    }

    public function getPublicStatusTextAttr($value, $data)
    {
        $value = $value ?: (isset($data['public_status']) ? $data['public_status'] : '');
        $list = $this->getPublicStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getIsFeaturedTextAttr($value, $data)
    {
        $value = $value !== '' ? $value : (isset($data['is_featured']) ? $data['is_featured'] : '');
        $list = $this->getIsFeaturedList();
        return isset($list[(string)$value]) ? $list[(string)$value] : '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getFeaturedAtTextAttr($value, $data)
    {
        $value = $value ?: (isset($data['featured_at']) ? $data['featured_at'] : '');
        return is_numeric($value) && $value > 0 ? date("Y-m-d H:i:s", $value) : '';
    }

    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
