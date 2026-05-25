<?php
namespace app\api\controller\yp;

use app\api\model\IntegralLog;
use think\Request;
use think\Db;
use think\Exception;
use app\api\model\User;
use app\api\model\Bank;
use app\api\model\MoneyLog;

class Withdrawal extends Base {

    protected $model;

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
        $this->model = new \app\api\model\Withdrawal;
    }

    /**
     * 提现记录
     */
    public function withdrawalLog(){
        $type = $this->request->param('type');
        $model = $this->model->where(['user_id' => $this->auth->id])
            ->order('createtime desc');
        if($type && in_array($type,[1,2,3])){
            $model->where(['status' => $type]);
        }
        $list = $model->field('id,bank_name,card_id,name,money,service_charge,amount_received,createtime,memo,type,status,transfer_state,transfer_fail_reason')
            ->paginate()
            ->each(function ($key){
                $key->append(['type_text']);
                $key['createtime'] = format($key['createtime']);
                return $key;
            });
        $this->success('成功',$list);
    }

    /**
     * 资产记录
     */
    public function moneyLog(){
        $type = $this->request->param('type');
        $model = MoneyLog::where(['user_id' => $this->auth->id])
            ->where(['classify' => 'money'])
            ->order('createtime desc');
        if($type && in_array($type,['sub','add'])){
            $model->where(['type' => $type]);
        }
        $list = $model->field('memo,createtime,type,num,classify,after')
            ->paginate()
            ->each(function ($key){
                $key->append(['classify_text']);
                $key['createtime'] = format($key['createtime']);
                return $key;
            });
        $money = $this->auth->money;
        $commission = $this->auth->commission;
        $avatar = $this->auth->avatar;
        $nickname = $this->auth->nickname;
        $this->success('成功',compact('money','commission','list','avatar','nickname'));
    }
    public function moneyList()
    {
        $type = $this->request->param('type');
        $andwhere = [];
        if($type){
           if($type == 'recharge'){
               $andwhere['change_type'] = 'recharge';
           }elseif($type == 'pay'){
               $andwhere = ['type' => 'sub'];
           }
        }
        $list = MoneyLog::where(['user_id'=>$this->auth->id,'classify' => 'money'])->where($andwhere)->order('id desc')->paginate(2000000);
        $lists = $this->groupList($list);
        $new_list = [];
        foreach ($lists as $k=>$v){
            $v['date'] = $k;
            array_push($new_list,$v);
        }
        $avatar = $this->auth->avatar;
        $nickname = $this->auth->nickname;
        $money = $this->auth->money;
        $commission = $this->auth->commission;
        $this->success('ok', compact('new_list','avatar','nickname','money','commission'));
    }
    public function groupList($list)
    {
        $new_list = [];
        foreach($list as $v){
            $year = date('Y',$v['createtime']);
            $month = date('m',$v['createtime']);
            $date = $year.'年'.$month.'月';
            $new_list[$date]['list'][] = $v;
            if(!isset($new_list[$date]['add'])){
                $new_list[$date]['add'] = 0;
            }
            if(!isset($new_list[$date]['sub'])){
                $new_list[$date]['sub'] = 0;
            }
            if($v['type'] == 'add'){
                $new_list[$date]['add']=  $new_list[$date]['add'] + $v['num'];
            }else{
                $new_list[$date]['sub']=  $new_list[$date]['sub'] + $v['num'];
            }
        }
        return $new_list;
    }
    public function integralGroupList($list)
    {
        $new_list = [];
        foreach($list as $v){
            $year = date('Y',$v['createtime']);
            $month = date('m',$v['createtime']);
            $date = $year.'年'.$month.'月';

            $new_list[$date]['list'][] = $v;
            if(!isset($new_list[$date]['add'])){
                $new_list[$date]['add'] = 0;
            }
            if(!isset($new_list[$date]['sub'])){
                $new_list[$date]['sub'] = 0;
            }
            if($v['type'] == 'add'){
                $new_list[$date]['add']=  $new_list[$date]['add'] + $v['num'];
            }else{
                $new_list[$date]['sub']=  $new_list[$date]['sub'] + $v['num'];
            }

        }
        return $new_list;
    }
    public function integralLog(){
        $model = IntegralLog::where(['user_id' => $this->auth->id])
            ->order('createtime desc');
        $list = $model->field('memo,createtime,type,num,after')
            ->paginate()
            ->each(function ($key){
                $key['createtime'] = format($key['createtime']);
                return $key;
            });
        $money = $this->auth->integral;
        $avatar = $this->auth->avatar;
        $nickname = $this->auth->nickname;
        $integral = $this->auth->integral;
        $this->success('成功',compact('money','list','avatar','nickname','integral'));
    }
    public function integralList()
    {
        $type = $this->request->param('type');
        $andwhere = [];
        if($type){
            if($type == 'add'){
                $andwhere['type'] = 'add';
            }elseif($type == 'sub'){
                $andwhere = ['type'=>'sub'];
            }
        }
        $list = IntegralLog::where(['user_id'=>$this->auth->id])->where($andwhere)->order('id desc')->paginate(2000000);
        $lists = $this->integralGroupList($list);
        $new_list = [];
        foreach ($lists as $k=>$v){
            $v['date'] = $k;
            array_push($new_list,$v);
        }


        $avatar = $this->auth->avatar;
        $nickname = $this->auth->nickname;
        $this->success('ok', compact('new_list','avatar','nickname'));
    }



    /**
     * 用户资产
     */
    public function wallet(){
        $money = $this->auth->money;
        $commission = $this->auth->commission;
        $this->success('成功',compact('money','commission'));
    }

    /**
     * 支持的提现方式
     */
    public function bank(){
        $list = Bank::where(['status' => '1'])->select();
        $this->success('成功',$list);
    }

    /**
     * 申请提现
     */
    public function applyWithdrawal(){
        $money = $this->request->param('money');
        $type = 1;
        if(!$type || !in_array($type,[1,2])){
            $this->error();
        }
        $pattern = '/^[1-9]\d*|0$|^[1-9]\d*\.\d{1,2}$|^0\.\d{1,2}$/';
        if (!preg_match($pattern, $money)) {
            $this->error('金额错误');
        }
        $withdrawal_service = getValues('withdrawal_service');
        $user_info = User::where(['id' => $this->auth->id])->lock(true)->find();
        if($user_info['commission'] <= 0 || $user_info['commission'] < $money){
            $this->error('可提现佣金不足');
        }
        if($withdrawal_service > 0){
            $service_charge = bcmul($money,bcdiv($withdrawal_service,100,2),2);
        }else{
            $service_charge = 0;
        }
        Db::startTrans();
        try{
            $money_log = [
                'user_id' => $this->auth->id,
                'money' => $money,
                'type' => 'sub',
                'memo' => '提现申请',
                'change_type' => 'withdrawal'
            ];
            $res = User::changeCommissionWithdrawal($money_log);
            if($res !== true){
                throw new Exception('');
            }
            $withdrawal_data = [
                'user_id' => $this->auth->id,
                'money' => $money,
                'service_charge' => $service_charge,
                'amount_received' => bcsub($money,$service_charge,2),
                'createtime' => time(),
                'out_batch_no' => order_no(),
                'type' => $type
            ];
            $this->model->insert($withdrawal_data);
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    public function merchantTransferPackage()
    {
        $info = $this->model->where([
                'user_id' => $this->auth->id,
                'status' => 2,
                'transfer_state' => 'WAIT_USER_CONFIRM',
            ])
            ->where('package_info', '<>', '')
            ->order('handletime desc,id desc')
            ->find();
        if (!$info) {
            $this->success('暂无待确认转账', null);
        }
        $config = transfer_config();
        $this->success('成功', [
            'id' => $info['id'],
            'mchId' => $config['mch_id'],
            'appId' => $config['appid'],
            'package' => $info['package_info'],
            'out_bill_no' => $info['out_detail_no'],
            'amount_received' => $info['amount_received'],
        ]);
    }

    public function confirmMerchantTransfer()
    {
        $id = $this->request->param('id');
        $info = $this->model->where(['id' => $id, 'user_id' => $this->auth->id, 'status' => 2])->find();
        if (!$info) {
            $this->error('提现记录不存在');
        }
        $this->success('成功');
    }
}
