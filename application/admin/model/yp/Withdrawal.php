<?php

namespace app\admin\model\yp;

use think\Model;


class Withdrawal extends Model
{

    

    

    // 表名
    protected $name = 'yp_withdrawal';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'handletime_text'
    ];



    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }


    public function getHandletimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['handletime']) ? $data['handletime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function transfer()
    {
        try {
            $config = include "./pay-v3-config.php";
            //$config =  [];

            $pay =\WePayV3\Transfers::instance($config);

            $result = $pay->batchs([
                'out_batch_no'         => 'plfk2020042013',
                'batch_name'           => '2019年1月深圳分部报销单',
                'batch_remark'         => '2019年1月深圳分部报销单',
                'total_amount'         => 100,
                'transfer_detail_list' => [
                    [
                        'out_detail_no'   => 'x23zy545Bd5436',
                        'transfer_amount' => 100,
                        'transfer_remark' => '2020年4月报销',
                        'openid'          => 'o-MYE42l80oelYMDE34nYD456Xoy',
                        'user_name'       => '小小邹'
                    ]
                ]
            ]);
            var_dump($result);

        } catch (\Exception $exception) {
            // 出错啦，处理下吧
            echo $exception->getMessage() . PHP_EOL;
        }
    }
}
