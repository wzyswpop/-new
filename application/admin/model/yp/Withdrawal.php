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

    public function transfer($params=[])
    {
        try {
            $config = transfer_config();
            var_dump($config);


            $pay =\WePayV3\Transfers::instance($config);
            die();

            $result = $pay->batchs([
                'out_batch_no'         => $params['order_no'],
                'batch_name'           => $params['desc'],
                'batch_remark'         => $params['desc'],
                'total_amount'         => $params['total_amount'],
                'transfer_detail_list' => $params['batch_list'],
                /*'transfer_detail_list' => [
                    [
                        'out_detail_no'   => $params['out_detail_no'],
                        'transfer_amount' => $params['transfer_amount'],
                        'transfer_remark' => $params['desc'],
                        'openid'          => $params['openid']
                    ]
                ]*/
            ]);
            var_dump($result);

        } catch (\Exception $exception) {
            // 出错啦，处理下吧
            echo $exception->getMessage() . PHP_EOL;
        }
    }
}
