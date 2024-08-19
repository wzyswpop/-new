<?php

namespace app\api\validate;

use think\Validate;

class Address extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'name' => 'require',
        'phone' => 'require|regex:^1\d{10}$',
        'province_id' => 'require',
        'city_id' => 'require',
        'county_id' => 'require',
        'address' => 'require',
        'is_default' => 'require|in:1,0'
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'name.require' => '收货人不能为空',
        'phone.require' => '手机号不能为空',
        'phone.regex' => '手机号格式错误',
        'province_id.require' => '省不能为空',
        'city_id.require' => '市不能为空',
        'county_id.require' => '县不能为空',
        'address.require' => '详细地址不能为空',
        'is_default.require' => '参数错误',
        'is_default.in' => '参数错误'
    ];

    /**
     * 字段描述
     */
    protected $field = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'address' => ['name','phone','province_id','city_id','county_id','address','is_default']
    ];

}
