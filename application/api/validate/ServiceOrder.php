<?php

namespace app\api\validate;

use think\Validate;

class ServiceOrder extends Validate
{

    /**
     * 验证规则
     */
    protected $rule = [
        'id' => 'require',
        'money' => 'require|egt:0',
        'explain' => 'require|checkExplain',
        'images' => 'length:0,3',
        'status' => 'require|in:all,0,1,2',
        'type' => 'require|in:1,2'
    ];

    /**
     * 提示消息
     */
    protected $message = [
        'id.require' => '参数错误',
        'money.require' => '退款金额错误',
        'money.egt' => '退款金额错误',
        'explain.require' => '退款原因不能为空',
        'images.length' => '最多上传3张图',
        'status.require' => '参数错误',
        'status.in' => '参数错误',
        'type.require' => '售后类型不能为空',
        'type.in' => '售后类型错误'
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
        'aftersales' => ['id','money','explain','images','type'],
        'send' => ['id','money','explain','images','type'],
        'getlist' => ['status'],
        'cancel' => ['id']
    ];

    protected function checkExplain($value){
        if(mb_strlen($value) > 100){
            return '退款原因最多100个字';
        }else{
            return true;
        }
    }
}
