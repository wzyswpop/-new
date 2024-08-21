<?php
namespace app\api\controller\yp;

use addons\epay\library\Service;
use app\api\model\Order;
use app\api\model\User;
use think\Db;
use think\Exception;
use think\Log;
use app\api\model\RechargeOrder;
use app\api\model\SignOrder;

class Pay extends Base{

    protected $noNeedLogin = ['ordernotify','rechargenotify','signnotify'];

    /**
     * 签到订单回调
     */
    public function signNotify(){
        Log::write('签到订单支付回调'.file_get_contents('php://input'));
        $pay = Service::checkNotify('wechat',pay_config());
        if (!$pay) {
            echo '签名错误';
            return;
        }
        $data = $pay->verify();
        $out_trade_no = $data['out_trade_no'];
        $order_info = SignOrder::where(['order_no' => $out_trade_no])->find();
        if($order_info && $order_info['status'] == 1){
            Db::startTrans();
            try {
                $order_info->status = 2;
                $order_info->paytime = time();
                $order_info->payment = 'wechat';
                $order_info->save();
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                Log::write('签到支付回调错误'.$e->getMessage());
            }
        }
        exit($pay->success());
    }


    /**
     * 积分订单支付
     */
    public function signPay(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $payment = $this->request->param('payment');
        if(!$payment || !in_array($payment,['wechat','balance'])){
            $this->error();
        }
        $order_info = SignOrder::where(['user_id' => $this->auth->id,'status' => '1','id' => $id])->find();
        if(!$order_info){
            $this->error('订单不存在');
        }
        if($payment == 'balance'){
            $this->signBalance($order_info);
        }
        if(!$this->auth->open_id){
            $this->error('缺少 openid');
        }
        $params = [
            'amount'=>$order_info['freight'],
            'orderid'=>$order_info['order_no'],
            'type' =>"wechat",
            'title'=>"购买商品",
            'notifyurl'=>$this->request->domain().'/api/yp.pay/signNotify',
            'returnurl'=>"",
            'method'=>"miniapp",
            'openid'=>$this->auth->open_id,
            'config' => pay_config()
        ];
        $_data = Service::submitOrder($params);
        $this->success('',$_data);
    }

    /**
     * 商品订单余额支付
     */
    public function signBalance($order){
        $user = $this->auth->getUser();
        if($user->money < $order['freight']){
            $this->error('余额不足');
        }
        Db::startTrans();
        try{
            $log = [
                'user_id' => $this->auth->id,
                'money' => $order['freight'],
                'type' => 'sub',
                'memo' => '支付签到订单扣除',
                'order_no' => $order['order_no'],
                'change_type' => 'sign'
            ];
            $res = User::changeMoney($log);
            if(!$res){
                throw new Exception('失败');
            }
            $order->status = '2';
            $order->payment = 'balance';
            $order->paytime = time();
            $order->save();
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    /**
     * 充值订单支付回调
     */
    public function rechargeNotify(){
        Log::write('充值订单支付回调'.file_get_contents('php://input'));
        $pay = Service::checkNotify('wechat',pay_config());
        if (!$pay) {
            echo '签名错误';
            return;
        }
        $data = $pay->verify();
        $out_trade_no = $data['out_trade_no'];
        $order_info = RechargeOrder::where(['order_no' => $out_trade_no])->find();
        if($order_info && $order_info['status'] == 0){
            Db::startTrans();
            try {
                User::changeMoney([
                    'user_id' => $order_info['user_id'],
                    'money' => $order_info['money'],
                    'type' => 'add',
                    'memo' => '充值增加',
                    'order_no' => $order_info['order_no'],
                    'change_type' => 'recharge'
                ]);
                $order_info->status = 1;
                $order_info->paytime = time();
                $order_info->save();
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                Log::write('充值支付回调错误'.$e->getMessage());
            }
        }
        exit($pay->success());
    }

    /**
     * 充值创建订单并支付
     */
    public function recharge(){
        if(!$this->auth->open_id){
            $this->error('缺少 openid');
        }
        $money = $this->request->param('money');
        $pattern = '/^[1-9]\d*|0$|^[1-9]\d*\.\d{1,2}$|^0\.\d{1,2}$/';
        // 进行验证
        if (!preg_match($pattern, $money)) {
            $this->error();
        }
        $order_no = order_no();
        $order = [
            'order_no' => $order_no,
            'user_id' => $this->auth->id,
            'money' => $money,
            'createtime' => time(),
        ];
        RechargeOrder::insert($order);
        $params = [
            'amount'=>$money,
            'orderid'=>$order_no,
            'type'=>"wechat",
            'title'=>"充值",
            'notifyurl'=>$this->request->domain().'/api/yp.pay/rechargeNotify',
            'returnurl'=>"",
            'method'=>"miniapp",
            'openid'=>$this->auth->open_id,
            'config' => pay_config()
        ];
        $_data = Service::submitOrder($params);
        $this->success('',$_data);
    }

    /**
     * 订单支付回调
     */
    public function orderNotify(){
        Log::write('订单支付回调'.file_get_contents('php://input'));
        $pay = Service::checkNotify('wechat',pay_config());
        if (!$pay) {
            echo '签名错误';
            return;
        }
        $s = false;
        $data = $pay->verify();
        $out_trade_no = $data['out_trade_no'];
        $order_info = Order::where(['order_no' => $out_trade_no])->find();
        if($order_info && $order_info['status'] == 1){
            Db::startTrans();
            try {
                $order_info->paytime = time();
                $order_info->payment = 'wechat';
                $score = $order_info['cash_money'];
                if($score >= 0){
                    $score_log = [
                        'user_id' => $order_info['user_id'],
                        'money' => $score,
                        'type' => 'sub',
                        'memo' => '支付订单扣减',
                        'order_no' => $order_info['order_no'],
                        'change_type' => 'pay'
                    ];
                    User::changeMoney($score_log);
                }
                $order_info->status = '2';
                $order_info->paytime = time();
                $order_info->save();
                $s = true;
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                Log::write('商品支付回调错误'.$e->getMessage());
            }
            if($s){
                pushOrder($order_info['id'],30,30,0);
            }
        }
        exit($pay->success());
    }

    /**
     * 拉起支付
     */
    public function prepay(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $payment = $this->request->param('payment');
        if(!$payment || !in_array($payment,['wechat','balance'])){
            $this->error();
        }
        $order_info = Order::where(['user_id' => $this->auth->id,'status' => '1','id' => $id])->find();
        if(!$order_info){
            $this->error('订单不存在');
        }
        if($order_info['order_money'] > $this->auth->money){
            $cash_money = $order_info['order_money'] - $this->auth->money;
            $order_info->cash_money = $cash_money;
            $order_info->payment = 'wechat';
            $payment = 'wechat';
        }else{
            $payment = 'balance';
            $order_info->payment = 'balance';
            $order_info->cash_money = 0;
        }
        $order_info->save();
        if($payment == 'balance'){
            $order_info = Order::where(['user_id' => $this->auth->id,'status' => '1','id' => $id])->find();
            $this->balance($order_info);
        }else{
            //判断用户余额
            $order_info = Order::where(['user_id' => $this->auth->id,'status' => '1','id' => $id])->find();
            $order_money = $order_info['order_money'] - $cash_money;
        }
        if(!$this->auth->open_id){
            $this->error('缺少 openid');
        }
        $params = [
            'amount'=>$order_money,
            'orderid'=>$order_info['order_no'],
            'type' =>"wechat",
            'title'=>"购买商品",
            'notifyurl'=>$this->request->domain().'/api/yp.pay/orderNotify',
            'returnurl'=>"",
            'method'=>"miniapp",
            'openid'=>$this->auth->open_id,
            'config' => pay_config()
        ];
        $_data = Service::submitOrder($params);
        $this->success('',$_data);
    }

    /**
     * 商品订单余额支付
     */
    public function balance($order){
        $user = $this->auth->getUser();
        if($user->money < $order['order_money']){
            $this->error('余额不足');
        }
        Db::startTrans();
        try{
            $log = [
                'user_id' => $this->auth->id,
                'money' =>$order['order_money'],
                'type' => 'sub',
                'memo' => '支付订单',
                'order_no' => $order['order_no'],
                'change_type' => 'pay'
            ];
            $res = User::changeMoney($log);
            if(!$res){
                throw new Exception('失败');
            }
            $order_integral = getValues('order_integral');
            $score = floor($order['order_money']*$order_integral);
            if($score >= 1){
                $score_log = [
                    'user_id' => $this->auth->id,
                    'money' => $score,
                    'type' => 'add',
                    'memo' => '支付订单增加',
                    'order_no' => $order['order_no'],
                    'change_type' => 'pay'
                ];
               User::changeIntegral($score_log);
            }
            $order->status = '2';
            $order->payment = 'balance';
            $order->paytime = time();
            $order->save();
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }
}