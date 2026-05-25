<?php

namespace app\admin\model\yp;

use think\Model;
use think\Log;
use app\common\library\WechatMerchantTransfer;


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
            $pay = WechatMerchantTransfer::instance($config);
            $body = [
                'appid' => $config['appid'],
                'out_bill_no' => $params['out_bill_no'],
                'transfer_scene_id' => isset($params['transfer_scene_id']) ? $params['transfer_scene_id'] : $config['transfer_scene_id'],
                'openid' => $params['openid'],
                'transfer_amount' => $params['transfer_amount'],
                'transfer_remark' => $params['transfer_remark'],
            ];
            if (!empty($params['transfer_scene_report_infos'])) {
                $body['transfer_scene_report_infos'] = $params['transfer_scene_report_infos'];
            } elseif (!empty($config['transfer_scene_report_infos'])) {
                $reportInfos = json_decode($config['transfer_scene_report_infos'], true);
                if (is_array($reportInfos)) {
                    $body['transfer_scene_report_infos'] = $reportInfos;
                }
            }
            if (empty($body['transfer_scene_report_infos']) && $body['transfer_scene_id'] == '1005') {
                $body['transfer_scene_report_infos'] = [
                    [
                        'info_type' => '岗位类型',
                        'info_content' => '分销员',
                    ],
                    [
                        'info_type' => '报酬说明',
                        'info_content' => '佣金提现',
                    ],
                ];
            }
            $result = $pay->createBill($body);
            Log::write('微信零钱提现返回：' . json_encode($result, JSON_UNESCAPED_UNICODE));
            if(!$result || isset($result['code']) || !isset($result['out_bill_no'])){
                $message = isset($result['message']) ? $result['message'] : '微信转账受理失败';
                if(isset($result['code'])){
                    $message = $result['code'] . '：' . $message;
                }
                throw new \Exception($message);
            }
            return $result;
        } catch (\Exception $exception) {
            Log::write('微信零钱提现失败：' . $exception->getMessage());
            throw $exception;
        }
    }

    public function queryTransfer($outBillNo)
    {
        $config = transfer_config();
        $pay = WechatMerchantTransfer::instance($config);
        return $pay->queryByOutBillNo($outBillNo);
    }
}
