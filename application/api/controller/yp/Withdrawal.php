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
        $list = $model->field('memo,createtime,type,num,classify,after')
            ->paginate()
            ->each(function ($key){
                $key->append(['classify_text']);
                $key['createtime'] = format($key['createtime']);
                return $key;
            });
        $money = $this->auth->money;
        $avatar = $this->auth->avatar;
        $nickname = $this->auth->nickname;
        $this->success('成功',compact('money','list','avatar','nickname'));
    }
    public function moneyList()
    {
        $type = $this->request->param('type');
        $andwhere = [];
        if($type){
           if($type == 'recharge'){
               $andwhere['change_type'] = 'recharge';
           }elseif($type == 'pay'){
               $andwhere = ['change_type'=>['<>','recharge']];
           }
        }
        $list = MoneyLog::where(['user_id'=>$this->auth->id])->where($andwhere)->order('id desc')->paginate(200);
        $new_list = $this->groupList($list);
        $per_page = 200;
        $total = MoneyLog::where(['user_id'=>$this->auth->id])->count();
        $current_page = $this->request->param('page')?$this->request->param('page'):1;
        $last_page = ceil($total/200);
        $avatar = $this->auth->avatar;
        $nickname = $this->auth->nickname;
        $this->success('ok', compact('per_page','total','current_page','last_page','new_list','avatar','nickname'));
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
            if($v['change_type'] == 'recharge'){
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
        $this->success('成功',compact('money','list','avatar','nickname'));
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
        $list = IntegralLog::where(['user_id'=>$this->auth->id])->where($andwhere)->order('id desc')->paginate(200);
        $new_list = $this->integralGroupList($list);
        $per_page = 200;
        $total = IntegralLog::where(['user_id'=>$this->auth->id])->count();
        $current_page = $this->request->param('page')?$this->request->param('page'):1;
        $last_page = ceil($total/200);
        $avatar = $this->auth->avatar;
        $nickname = $this->auth->nickname;
        $this->success('ok', compact('per_page','total','current_page','last_page','new_list','avatar','nickname'));
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
        $type = 2;
        if(!$type || !in_array($type,[1,2])){
            $this->error();
        }
        $pattern = '/^[1-9]\d*|0$|^[1-9]\d*\.\d{1,2}$|^0\.\d{1,2}$/';
        if (!preg_match($pattern, $money)) {
            $this->error('金额错误');
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