<?php

namespace app\admin\model\yp;

use think\Model;

class RecipeComment extends Model
{
    protected $name = 'yp_recipe_comment';

    protected $autoWriteTimestamp = 'integer';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    protected $append = [
        'source_type_text',
        'status_text'
    ];

    public function getSourceTypeList()
    {
        return ['official' => '官方成熟配方', 'user' => '用户公开配方'];
    }

    public function getStatusList()
    {
        return ['normal' => '显示', 'hidden' => '隐藏'];
    }

    public function getSourceTypeTextAttr($value, $data)
    {
        $value = $value ?: (isset($data['source_type']) ? $data['source_type'] : '');
        $list = $this->getSourceTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
