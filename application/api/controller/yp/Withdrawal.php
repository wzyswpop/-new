<?php
namespace app\api\controller\yp;

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
        $list = $model->field('bank_name,card_id,name,money,service_charge,amount_received,createtime,memo,type')
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
            ->order('createtime desc');
        if($type && in_array($type,['sub','add'])){
            $model->where(['type' => $type]);
        }
        $list = $model->field('memo,createtime,type,num,classify')
            ->paginate()
            ->each(function ($key){
                $key->append(['classify_text']);
                $key['createtime'] = format($key['createtime']);
                return $key;
            });
        $this->success('成功',$list);
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
        $bank_id = $this->request->param('bank_id');
        $money = $this->request->param('money');
        $name = $this->request->param('name');
        $card_id = $this->request->param('card_id');
        $type = $this->request->param('type');
        if(!$type || !in_array($type,[1,2])){
            $this->error();
        }
        $pattern = '/^[1-9]\d*|0$|^[1-9]\d*\.\d{1,2}$|^0\.\d{1,2}$/';
        if (!preg_match($pattern, $money)) {
            $this->error('金额错误');
        }
        if(!$card_id || !$name){
            $this->error();
        }
        $bank = Bank::where(['id' => $bank_id,'status' => '1'])->find();
        if(!$bank){
            $this->error();
        }
        $withdrawal_service = getValues('withdrawal_service');
        $user_info = User::where(['id' => $this->auth->id])->lock(true)->find();
        if($type == 1){  //佣金
            if($user_info['commission'] <= 0 || $user_info['commission'] < $money){
                $this->error('余额不足');
            }
        }else{           //余额
            if($user_info['money'] <= 0  || $user_info['money'] < $money){
                $this->error('余额不足');
            }
        }
        if($withdrawal_service > 0 && $type == 1){
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
            if($type == 1){
                $res = User::changeCommission($money_log);
            }else{
                $res = User::changeMoney($money_log);
            }
            if($res !== true){
                throw new Exception('');
            }
            $withdrawal_data = [
                'user_id' => $this->auth->id,
                'money' => $money,
                'service_charge' => $service_charge,
                'amount_received' => bcsub($money,$service_charge,2),
                'createtime' => time(),
                'card_id' => $card_id,
                'name' => $name,
                'bank_name' => $bank['title'],
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
}