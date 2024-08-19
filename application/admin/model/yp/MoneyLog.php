<?php

namespace app\admin\model\yp;

use think\Model;


class MoneyLog extends Model
{

    

    

    // 表名
    protected $name = 'yp_money_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'change_type_text',
        'classify_text'
    ];
    

    
    public function getTypeList()
    {
        return ['add' => __('Type add'), 'sub' => __('Type sub')];
    }

    public function getChangeTypeList()
    {
        return ['pay' => __('Pay')];
    }

    public function getClassifyList()
    {
        return ['money' => __('Classify money'), 'commission' => __('Classify commission')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getChangeTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['change_type']) ? $data['change_type'] : '');
        $list = $this->getChangeTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getClassifyTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['classify']) ? $data['classify'] : '');
        $list = $this->getClassifyList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
