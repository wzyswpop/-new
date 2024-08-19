<?php

namespace app\admin\validate\yp;

use think\Validate;

class Goods extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require',  // 商品标题：必填
        'category_id' => 'require',
        'image' => 'require',
        'images' => 'require',
        'money' => 'require'
    ];
    /**
     * 提示消息
     */
    protected $message = [
        'name.require' => '商品名称必须填写',
        'category_id.require' => '所属分类必须选择',
        'image.require' => '商品主图必须上传',
        'images.require' => '至少上传一张轮播图',
        'money.require' => '价格必须填写'
    ];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['name', 'image', 'images', 'category_ids','money'],
        'edit' => ['name', 'image', 'images', 'category_ids','money'],
    ];

}
