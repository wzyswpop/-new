<?php

namespace app\api\model;

use app\admin\model\Customize;
use think\Model;
use think\Exception;
use think\Db;
use addons\epay\library\Service;

class Order extends Base
{

    protected $name = 'yp_order';
    const AUTO_RECEIVE_DAYS = 15;
    const AUTO_REFUND_SECONDS = 900;

    /**
     * 取消订单并恢复订单占用的资源。
     * 调用方负责开启事务，并在需要时先对订单加锁。
     */
    public static function cancelWithRestore($order, $refundWechat = true)
    {
        $oldStatus = (int)$order['status'];

        if($order['discount_integral'] > 0){
            $res = User::changeIntegral([
                'user_id' => $order['user_id'],
                'money' => $order['discount_integral'],
                'type' => 'add',
                'memo' => '取消订单',
                'order_no' => $order['order_no'],
                'change_type' => 'cancel'
            ]);
            if(!$res){
                throw new Exception('增加积分错误');
            }
        }

        $order->status = '0';
        $order->canceltime = time();
        $order->save();

        if($oldStatus > 1){
            $balanceMoney = isset($order['cash_money']) ? $order['cash_money'] : 0;
            if($balanceMoney > 0){
                $res = User::changeMoney([
                    'user_id' => $order['user_id'],
                    'money' => $balanceMoney,
                    'type' => 'add',
                    'memo' => '取消订单',
                    'order_no' => $order['order_no'],
                    'change_type' => 'service_order'
                ]);
                if(!$res){
                    throw new Exception('增加余额错误');
                }
            }

            if($order['payment'] == 'wechat'){
                $wechatMoney = bcsub($order['order_money'], $balanceMoney, 2);
                if($refundWechat && $wechatMoney > 0){
                    $params = [];
                    $params['transaction_id'] = $order['transaction_id'];
                    $params['out_refund_no'] = order_no();
                    $params['total_fee'] = bcmul($wechatMoney, 100, 0);
                    $params['refund_fee'] = bcmul($wechatMoney, 100, 0);
                    $result = Service::refund($params);
                    if(!isset($result['return_code']) || $result['return_code'] != 'SUCCESS'){
                        throw new Exception('微信退款失败');
                    }
                }
            }else{
                $res = User::changeMoney([
                    'user_id' => $order['user_id'],
                    'money' => $order['order_money'],
                    'type' => 'add',
                    'memo' => '取消订单',
                    'order_no' => $order['order_no'],
                    'change_type' => 'service_order'
                ]);
                if(!$res){
                    throw new Exception('增加余额错误');
                }
            }
        }

        if($order['coupon_id']){
            $couponEndtime = UserCoupons::where(['id' => $order['coupon_id']])->value('endtime');
            $couponStatus = $couponEndtime && $couponEndtime < time() ? '3' : '1';
            UserCoupons::where(['id' => $order['coupon_id'], 'user_id' => $order['user_id'], 'status' => '2'])->update(['status' => $couponStatus]);
        }

        foreach ($order['item'] as $v){
            SkuPrice::where(['id' => $v['stock_id'],'goods_id' => $v['goods_id']])->setInc('stock',$v['num']);
        }
    }

    protected static function compareMoneyValue($left, $right)
    {
        if(function_exists('bccomp')){
            return bccomp((string)$left, (string)$right, 2);
        }
        $leftFloat = round((float)$left, 2);
        $rightFloat = round((float)$right, 2);
        if($leftFloat == $rightFloat){
            return 0;
        }
        return $leftFloat > $rightFloat ? 1 : -1;
    }

    protected static function addMoneyValue($left, $right)
    {
        return function_exists('bcadd') ? bcadd((string)$left, (string)$right, 2) : number_format((float)$left + (float)$right, 2, '.', '');
    }

    protected static function subMoneyValue($left, $right)
    {
        return function_exists('bcsub') ? bcsub((string)$left, (string)$right, 2) : number_format((float)$left - (float)$right, 2, '.', '');
    }

    protected static function mulMoneyValue($left, $right, $scale = 0)
    {
        return function_exists('bcmul') ? bcmul((string)$left, (string)$right, $scale) : number_format((float)$left * (float)$right, $scale, '.', '');
    }

    protected static function divMoneyValue($left, $right, $scale = 4)
    {
        if((float)$right == 0){
            return '0';
        }
        return function_exists('bcdiv') ? bcdiv((string)$left, (string)$right, $scale) : number_format((float)$left / (float)$right, $scale, '.', '');
    }

    protected static function minMoneyValue($left, $right)
    {
        return self::compareMoneyValue($left, $right) > 0 ? self::mulMoneyValue($right, 1, 2) : self::mulMoneyValue($left, 1, 2);
    }

    /**
     * 执行仅退款售后单的实际退款动作。
     * 调用方负责开启事务；传入的售后单和订单建议先加锁。
     */
    public static function refundServiceOrder($serviceOrder, $order = null)
    {
        if(!$serviceOrder){
            throw new Exception('售后单不存在');
        }
        if((int)$serviceOrder['type'] !== 1){
            throw new Exception('仅退款订单才能直接退款');
        }
        if((int)$serviceOrder['status'] !== 0 || (int)$serviceOrder['return_money'] !== 1){
            throw new Exception('售后单已经处理过了');
        }

        $orderInfo = $order ?: self::where(['id' => $serviceOrder['order_id']])->lock(true)->find();
        if(!$orderInfo){
            throw new Exception('订单不存在');
        }

        $refundMoney = self::mulMoneyValue($serviceOrder['money'], 1, 2);
        if(self::compareMoneyValue($refundMoney, 0) <= 0){
            throw new Exception('退款金额错误');
        }
        if(self::compareMoneyValue($refundMoney, $orderInfo['order_money']) > 0){
            throw new Exception('退款金额不能大于订单金额');
        }

        if($orderInfo['discount_integral'] > 0){
            $res = User::changeIntegral([
                'user_id' => $serviceOrder['user_id'],
                'money' => $orderInfo['discount_integral'],
                'type' => 'add',
                'memo' => '售后退还',
                'order_no' => $serviceOrder['order_no'],
                'change_type' => 'cancel'
            ]);
            if(!$res){
                throw new Exception('退还积分失败');
            }
        }

        if($orderInfo['payment'] == 'balance'){
            $res = User::changeMoney([
                'user_id' => $serviceOrder['user_id'],
                'money' => $refundMoney,
                'type' => 'add',
                'memo' => '退款',
                'order_no' => $serviceOrder['order_no'],
                'change_type' => 'service_order'
            ]);
            if(!$res){
                throw new Exception('退款失败');
            }
        }else{
            $cashMoney = isset($orderInfo['cash_money']) ? self::minMoneyValue($orderInfo['cash_money'], $refundMoney) : 0;
            $wechatMoney = self::subMoneyValue($refundMoney, $cashMoney);
            if(self::compareMoneyValue($wechatMoney, 0) > 0){
                if(empty($orderInfo['transaction_id'])){
                    throw new Exception('微信支付单号不存在');
                }
                $wechatTotal = self::subMoneyValue($orderInfo['order_money'], isset($orderInfo['cash_money']) ? $orderInfo['cash_money'] : 0);
                $params = [];
                $params['transaction_id'] = $orderInfo['transaction_id'];
                $params['out_refund_no'] = order_no();
                $params['total_fee'] = self::mulMoneyValue($wechatTotal, 100, 0);
                $params['refund_fee'] = self::mulMoneyValue($wechatMoney, 100, 0);
                $result = Service::refund($params);
                if(!isset($result['return_code']) || $result['return_code'] != 'SUCCESS'){
                    throw new Exception('微信退款失败');
                }
            }
            if(self::compareMoneyValue($cashMoney, 0) > 0){
                $res = User::changeMoney([
                    'user_id' => $serviceOrder['user_id'],
                    'money' => $cashMoney,
                    'type' => 'add',
                    'memo' => '退款',
                    'order_no' => $serviceOrder['order_no'],
                    'change_type' => 'service_order'
                ]);
                if(!$res){
                    throw new Exception('退款失败');
                }
            }
        }

        $serviceOrder->status = '1';
        $serviceOrder->handletime = time();
        $serviceOrder->return_money = 2;
        $serviceOrder->save();

        $orderInfo->status = '6';
        $orderInfo->save();
    }

    /**
     * 分销
     */
    public static function distribution($order_id){
        $order_info = self::where(['id' => $order_id])->find();
        if(!$order_info){
            return;
        }
        $promoter = self::resolveCommissionOwner($order_info['user_id']);
        if(!$promoter){
            return;
        }
        if(self::hasCommissionSettled($order_info['order_no'])){
            return;
        }
        $distribution_proportion = isset($promoter['distribution_rate']) ? $promoter['distribution_rate'] : 0;
        $bili = self::divMoneyValue($distribution_proportion, 100, 4);
        if((float)$bili <= 0){
            return;
        }
        $money = self::mulMoneyValue($order_info['order_money'], $bili, 2);
        if(self::compareMoneyValue($money, 0) <= 0){
            return;
        }
        User::addCommissionToWallet([
            'user_id' => $promoter['id'],
            'money' => $money,
            'memo' => '分销收益',
            'order_no' => $order_info['order_no'],
            'change_type' => 'commission'
        ]);
    }

    protected static function resolveCommissionOwner($buyerUserId)
    {
        $user = User::where(['id' => $buyerUserId])->find();
        if(!$user || (int)$user['pid'] <= 0){
            return null;
        }
        $visited = [(int)$buyerUserId => true];
        $parentId = (int)$user['pid'];
        $depth = 0;

        while($parentId > 0 && $depth < 100){
            if(isset($visited[$parentId])){
                break;
            }
            $visited[$parentId] = true;
            $depth++;
            $parent = User::where(['id' => $parentId])->find();
            if(!$parent){
                break;
            }
            $rate = isset($parent['distribution_rate']) ? (float)$parent['distribution_rate'] : 0;
            if($rate > 0){
                return $parent;
            }
            $parentId = (int)$parent['pid'];
        }

        return null;
    }

    protected static function hasCommissionSettled($orderNo)
    {
        if(!$orderNo){
            return false;
        }
        return Db::name('yp_money_log')
            ->where('order_no', $orderNo)
            ->where('classify', 'commission')
            ->where('type', 'add')
            ->where('change_type', 'commission')
            ->count() > 0;
    }

    /**
     * 确认收货并执行完成订单后的结算逻辑。
     * 调用方负责开启事务，并在并发场景下先锁定订单。
     */
    public static function confirmReceipt($order)
    {
        if(!$order || !in_array((int)$order['status'], [2, 3], true)){
            return false;
        }

        $order->status = '4';
        $order->confirmtime = time();
        $score = floor($order['order_money']);
        if($score >= 1){
            User::changeIntegral([
                'user_id' => $order['user_id'],
                'money' => $score,
                'type' => 'add',
                'memo' => '确认收货',
                'order_no' => $order['order_no'],
                'change_type' => 'pay'
            ]);
        }
        $order->save();
        self::distribution($order['id']);
        self::receiving($order['user_id']);

        return true;
    }

    /**
     * 是否升级
     */
    public static function receiving($user_id){
        $user_info = User::where(['id' => $user_id])->find();
        if(!$user_info || !$user_info['level_id']){
            return;
        }
        $all_money = self::where(['user_id' => $user_id,'status' => 4])->sum('order_money');
        $user_level = Level::where(['id' => $user_info['level_id']])->find();
        if(!$user_level){
            return;
        }
        $next = Level::where(['weigh' => ['>',$user_level['weigh']]])->order('weigh desc')->select();
        if($next){
            foreach ($next as $v){
                if($v['money'] <= $all_money){
                    $user_info->level_id = $v['id'];
                    $user_info->save();
                    break;
                }
            }
        }
    }

    /**
     * 确认订单
     */
    public function pre($data,$user_id){
        $return = $data;
        if(isset($return['address_id'])){
            $address_info = Address::where(['user_id' => $user_id,'id' => $return['address_id']])->find();
        }else{
            $address_info = Address::where(['user_id' => $user_id,'is_default' => '1'])->find();
        }
        $user_info = User::where(['id' => $user_id])->find();
        $goods_list = $data['goods_list'];
        $all_money = 0;        //订单总金额
        $goods_money = 0;      //商品总金额
        $freight = 0;          //运费
        $goods_num = 0;
        $order_type = 0;
        $goods_ids = [];
        $total_ratio = 0;
        $total_weight = 0;
        $is_custom_order = $this->isCustomOrderData($data);
        $custom_weight_discount_rate = $is_custom_order ? $this->getCustomWeightDiscountRate($data['total_weight'] ?? 0) : 1;
        $weight_discount_text = '';
        $custom_weight_allocations = [];
        if($is_custom_order && count($goods_list) > 1){
            $custom_weight_allocations = $this->allocateCustomWeights($data['total_weight'] ?? 0, $goods_list);
        }
        foreach ($goods_list as $goods_index => &$v){
            $goods_ids[] = $v['goods_id'];
            $goods_info = Goods::where(['id' => $v['goods_id'],'status' => '1'])
                ->field('id,name,image,category_id,is_stock,is_shop_sale,is_customized,custom_status,customized_price,ag')
                ->find();
            if(!$goods_info){
                json_error('商品不存在');
            }
            if($is_custom_order){
                if((int)$goods_info['is_customized'] !== 1 || (int)$goods_info['custom_status'] !== 1){
                    json_error('该咖啡豆暂不可用于定制拼配');
                }
            }else{
                if((int)$goods_info['is_shop_sale'] !== 1){
                    json_error('该商品未上架商城，无法购买');
                }
            }
            $goods_info['category'] = GoodsCategory::where(['id' => $goods_info['category_id']])->value('name');
            $v['goods_info'] = $goods_info;
            $goods_stock = SkuPrice::where(['id' => $v['stock_id'],'goods_id' => $v['goods_id'],'status' => 'up'])->field('id,stock,money,goods_sku_text,weight')->find();

            if(!$goods_stock){
                json_error('商品不存在');
            }
            if($v['num'] > $goods_stock['stock']){
                json_error('库存不足');
            }

            $goods_stock['goods_category'] = $goods_info['category'];
            $v['stock'] = $goods_stock;
            if($is_custom_order){
                $order_type = 1;
                if(count($goods_list) > 1){
                    if($v['ratio'] < 10 ){
                        json_error('单支咖啡豆比例不能低于10%');
                    }
                    if(count($goods_list) > 5 ||  count($goods_list) < 2){
                        json_error('商品数量错误');
                    }
                    $total_ratio = $total_ratio + $v['ratio'];
                    if(isset($data['customize_id']) && $data['customize_id'] > 0){//定制配方吃点
                        $price  = Customize::where(['id' => $data['customize_id']])->value('price');
                        $price = bcmul($price,bcdiv($data['total_weight'],1000,2),2);
                        $price = bcmul($price,$custom_weight_discount_rate,2);
                        $v['weight'] = isset($custom_weight_allocations[$goods_index]) ? $custom_weight_allocations[$goods_index] : 0;
                        $v['money'] = bcmul($price, $v['ratio']/100,2);
                    }else{
                        $v['weight'] = isset($custom_weight_allocations[$goods_index]) ? $custom_weight_allocations[$goods_index] : 0;
                        $v['money'] = bcmul($goods_info['customized_price'], $v['weight']/1000,2);
                        $v['money'] = bcmul($v['money'], $custom_weight_discount_rate, 2);
                    }
                }else{
                     $total_weight = $total_weight + $v['weight'];
                    $v['money'] = bcmul($goods_info['customized_price'],$v['weight']/1000,2);
                    $v['money'] = bcmul($v['money'], $custom_weight_discount_rate, 2);
                }

            }else{
                 $total_weight = $total_weight + $goods_stock['weight']*$v['num'];
                $v['money'] = $goods_stock['money'] * $v['num'];
            }
            $goods_money += $v['money'];
            $goods_num += $v['num'];
        }
        if($order_type == 1 && count($goods_list) > 1){
            if($total_ratio != 100){
                json_error('商品比例错误');
            }
        }
        unset($v);
        if(isset($data['customize_id']) && $data['customize_id'] > 0){//定制配方
            $all_money  = Customize::where(['id' => $data['customize_id']])->value('price');
            $all_money = bcmul($all_money,bcdiv($data['total_weight'],1000,2),2);
            $all_money = bcmul($all_money,$custom_weight_discount_rate,2);
        }else{
            $all_money += $goods_money;
        }
        $intergal = $user_info['integral'];
        $cash_integral = getValues('cash_integral');
        $intergal_cash = 0.00;
        if($cash_integral > 0){
            $intergal_cash = bcdiv($intergal,$cash_integral,2);
        }
        $discount_money = 0;
        $use_integral = 0;
        $coupon_discount_money = 0;
        $selected_coupon = null;

        if(isset($data['use_integral']) && $data['use_integral'] == 1 && $intergal && $intergal > 0 && $cash_integral> 0){
            $discount_money = bcdiv($intergal,$cash_integral,2);
            $all_money = $all_money - $discount_money;
            $use_integral = $intergal;
        }
        $available_coupons = $this->availableCoupons($goods_ids, $all_money, $user_id);
        $unavailable_coupons = $this->unavailableCoupons($goods_ids, $all_money, $user_id);
        $before_coupon_money = $all_money;
        $hasCouponParam = array_key_exists('coupon_id', $data);
        $coupon_id = $hasCouponParam ? (int)$data['coupon_id'] : 0;
        if($coupon_id > 0){
            foreach ($available_coupons as $coupon) {
                if((int)$coupon['id'] === $coupon_id){
                    $selected_coupon = $coupon;
                    break;
                }
            }
            if(!$selected_coupon){
                json_error('优惠券不可用');
            }
        }elseif(!$hasCouponParam && $available_coupons){
            $selected_coupon = $available_coupons[0];
            $coupon_id = (int)$selected_coupon['id'];
        }
        if($selected_coupon){
            $coupon_discount_money = $this->minMoney($selected_coupon['amount'], $all_money);
            $all_money = bcsub($all_money, $coupon_discount_money, 2);
        }else{
            $coupon_id = 0;
        }
        if($all_money <= 0){
            $all_money = 0;
        }
        $discount_money = bcadd($discount_money, $coupon_discount_money, 2);
        $all_money += $freight;
        $before_coupon_money = bcadd($before_coupon_money, $freight, 2);
        $all_money = bcmul($all_money,1,2);
        $before_coupon_money = bcmul($before_coupon_money,1,2);
         $goods_money = bcmul($goods_money,1,2);
        $user_money = $user_info['money'];

        $integral_rule = $cash_integral.'积分兑换1元';

        if(!isset($return['total_weight'])){
            $return['total_weight'] = $total_weight;
        }
        if($is_custom_order){
            $weight_discount_text = $this->formatCustomWeightDiscountText($data['total_weight'] ?? $total_weight, $custom_weight_discount_rate);
            $return['weight_discount_rate'] = $custom_weight_discount_rate;
            $return['weight_discount_text'] = $weight_discount_text;
        }

        return compact('order_type','user_money','all_money','before_coupon_money','discount_money','coupon_discount_money','coupon_id','selected_coupon','available_coupons','unavailable_coupons','goods_list','goods_money','goods_num','return','intergal','intergal_cash','address_info','use_integral','integral_rule','custom_weight_discount_rate','weight_discount_text');
    }

    protected function allocateCustomWeights($totalWeight, $goodsList)
    {
        $totalWeight = max(0, (int)round((float)$totalWeight));
        $allocations = [];
        $remainders = [];
        $allocatedWeight = 0;

        foreach ($goodsList as $index => $item) {
            $ratio = isset($item['ratio']) ? (float)$item['ratio'] : 0;
            $exactWeight = $totalWeight * $ratio / 100;
            $baseWeight = (int)floor($exactWeight);
            $allocations[$index] = $baseWeight;
            $allocatedWeight += $baseWeight;
            $remainders[] = [
                'index' => $index,
                'remainder' => $exactWeight - $baseWeight
            ];
        }

        $remainingWeight = $totalWeight - $allocatedWeight;
        if($remainingWeight > 0 && $remainders){
            usort($remainders, function ($left, $right) {
                if($left['remainder'] == $right['remainder']){
                    return $left['index'] <=> $right['index'];
                }
                return $left['remainder'] < $right['remainder'] ? 1 : -1;
            });

            $remainderCount = count($remainders);
            for($i = 0; $i < $remainingWeight; $i++){
                $target = $remainders[$i % $remainderCount]['index'];
                $allocations[$target]++;
            }
        }

        ksort($allocations);
        return $allocations;
    }

    protected function minMoney($left, $right)
    {
        if(function_exists('bccomp')){
            return bccomp((string)$left, (string)$right, 2) > 0 ? bcmul($right, 1, 2) : bcmul($left, 1, 2);
        }
        return number_format(min((float)$left, (float)$right), 2, '.', '');
    }

    protected function compareMoney($left, $right)
    {
        if(function_exists('bccomp')){
            return bccomp((string)$left, (string)$right, 2);
        }
        $leftFloat = round((float)$left, 2);
        $rightFloat = round((float)$right, 2);
        if($leftFloat == $rightFloat){
            return 0;
        }
        return $leftFloat > $rightFloat ? 1 : -1;
    }

    protected function isCustomOrderData($data)
    {
        if (!isset($data['order_type'])) {
            json_error('订单类型不能为空');
        }
        $orderType = strtolower((string)$data['order_type']);
        if (in_array($orderType, ['custom', 'customized', 'dingzhi', '1'], true)) {
            return true;
        }
        if (in_array($orderType, ['mall', 'shop', 'normal', '0'], true)) {
            return false;
        }
        json_error('订单类型错误');
    }

    protected function getCustomWeightDiscountRules()
    {
        $rawRules = getValues('custom_weight_discount_rules');
        $rules = [];

        if (is_string($rawRules) && trim($rawRules) !== '') {
            $decoded = json_decode($rawRules, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $rules = $decoded;
            } else {
                $lines = preg_split('/\r\n|\r|\n/', trim($rawRules));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '|') === false) {
                        continue;
                    }
                    [$kg, $discount] = array_map('trim', explode('|', $line, 2));
                    if ($kg !== '' && $discount !== '') {
                        $rules[$kg] = $discount;
                    }
                }
            }
        } elseif (is_array($rawRules)) {
            $rules = $rawRules;
        }

        if (!$rules) {
            $rules = [
                1 => 100,
                2 => 100,
                3 => 98,
                4 => 97,
                5 => 96,
                6 => 95,
                7 => 94,
                8 => 93,
                9 => 92,
                10 => 91,
                11 => 90,
                12 => 89,
                13 => 88,
                14 => 87,
                15 => 86,
                16 => 85,
                17 => 84,
                18 => 83,
                19 => 82,
                20 => 80,
            ];
        }

        ksort($rules, SORT_NUMERIC);
        return $rules;
    }

    protected function getCustomWeightDiscountRate($weight)
    {
        $kg = max(1, (int)ceil(((float)$weight) / 1000));
        $rules = $this->getCustomWeightDiscountRules();
        $selected = null;

        foreach ($rules as $limit => $discount) {
            if ($kg >= (int)$limit) {
                $selected = $discount;
            } else {
                break;
            }
        }

        if ($selected === null) {
            $selected = 100;
        }

        $rate = (float)$selected;
        if ($rate > 1) {
            $rate = $rate / 100;
        }
        if ($rate <= 0) {
            $rate = 1;
        }

        return $rate;
    }

    protected function formatCustomWeightDiscountText($weight, $rate)
    {
        $percent = round($rate * 100);
        return $percent >= 100 ? '原价' : $percent . '折';
    }

    protected function couponMatchesGoods($coupon, $goods_ids)
    {
        if($coupon['goods_type'] != 2){
            return true;
        }
        $coupons_goods_id = explode(',', $coupon['goods_ids']);
        foreach ($goods_ids as $goodsId){
            if(in_array($goodsId, $coupons_goods_id)){
                return true;
            }
        }
        return false;
    }

    /**
     * 可用优惠券
     */
    protected function availableCoupons($goods_ids,$money,$user_id){
        $list = UserCoupons::where(['user_id' => $user_id,'status' => '1','use_money' => ['<=',$money], 'endtime' => ['>', time()]])
            ->field('id,coupons_id,name,goods_type,amount,goods_ids,use_money,endtime,source')
            ->order('endtime asc')
            ->select();
        if($list){
            foreach ($list as $k=>&$v){
                if($v['goods_type'] == 2){
                    $coupons_goods_id = explode(',',$v['goods_ids']);
                    $res = false;
                    foreach ($goods_ids as $vv){
                        if(in_array($vv,$coupons_goods_id)){
                            $res = true;
                            break;
                        }
                    }
                    if(!$res){
                        unset($list[$k]);
                        continue;
                    }
                }
                $v['_endtime_ts'] = $v['endtime'];
                $v['discount_money'] = $this->minMoney($v['amount'], $money);
                $v['endtime'] = format($v['endtime']);
            }
            unset($v);
            $list = array_values($list);
            usort($list, function ($a, $b) {
                $discountCompare = $this->compareMoney($b['discount_money'], $a['discount_money']);
                if($discountCompare !== 0){
                    return $discountCompare;
                }
                $amountCompare = $this->compareMoney($b['amount'], $a['amount']);
                if($amountCompare !== 0){
                    return $amountCompare;
                }
                $useMoneyCompare = $this->compareMoney($a['use_money'], $b['use_money']);
                if($useMoneyCompare !== 0){
                    return $useMoneyCompare;
                }
                if($a['_endtime_ts'] == $b['_endtime_ts']){
                    return 0;
                }
                return $a['_endtime_ts'] > $b['_endtime_ts'] ? 1 : -1;
            });
            foreach ($list as &$v){
                unset($v['_endtime_ts']);
            }
            unset($v);
            return $list;
        }else{
            return [];
        }
    }

    /**
     * 当前订单不可用但仍处于待使用状态的优惠券
     */
    protected function unavailableCoupons($goods_ids,$money,$user_id){
        $list = UserCoupons::where(['user_id' => $user_id,'status' => '1', 'endtime' => ['>', time()]])
            ->field('id,coupons_id,name,goods_type,amount,goods_ids,use_money,endtime,source')
            ->order('endtime asc,amount desc,use_money desc')
            ->select();
        if(!$list){
            return [];
        }
        $unavailable = [];
        foreach ($list as $v){
            $reason = '';
            if(!$this->couponMatchesGoods($v, $goods_ids)){
                $reason = '不适用于当前商品';
            }elseif(bccomp((string)$v['use_money'], (string)$money, 2) > 0){
                $left = bcsub($v['use_money'], $money, 2);
                $reason = '还差 ¥'.$left.' 可用';
            }
            if(!$reason){
                continue;
            }
            $v['unavailable_reason'] = $reason;
            $v['endtime'] = format($v['endtime']);
            $unavailable[] = $v;
        }
        return array_values($unavailable);
    }

    /**
     * 运费处理
     */
    protected function freight($data,$goods_info,$address){
        if(!$address){
            return ['price' => 0,'delivery' => false];
        }
        $freight_id = $goods_info['freight_id'];
        $freight_info = Freight::where(['id' => $freight_id])->find();
        if(!$freight_info){
            json_error('无运费模板数据');
        }
        $list = FreightData::where([
            ['EXP', Db::raw('FIND_IN_SET('.$address['city_id'].', citys)')],
            'freight_id' => $freight_id
        ])->find();
        if(!$list){
            return ['price' => 0,'delivery' => false];
        }
        // 计价方式:0=按件数,1=按重量,2=固定运费
        if($freight_info['valuation'] == 0){
            if($data['num'] <= $list['first']){
                $price = $list['first_fee'];
            }else{
                $additional = $list['additional'] > 0 ? $list['additional'] : 1;
                $price = bcadd(bcmul(ceil(($data['num'] - $list['first']) / $additional), $list['additional_fee'], 2), $list['first_fee'], 2);
            }
        }elseif($freight_info['valuation'] == 1){
            $weigh = $data['stock']['weight'] * $data['num']; // 订单总重量
            if($weigh <= $list['first']){ //如果重量小于等首重，则首重价格
                $price = $list['first_fee'];
            }else{
                $additional = $list['additional'] > 0 ? $list['additional'] : 1;
                $price = bcadd(bcmul(ceil(($weigh - $list['first']) / $additional), $list['additional_fee'], 2), $list['first_fee'], 2);
            }
        }else{
            $price = $list['fixed_money'];
        }
        return ['price' => $price];
    }

    protected function lockAndUseCoupon($couponId, $userId, $goodsIds, $money)
    {
        $couponId = (int)$couponId;
        if($couponId <= 0){
            return null;
        }
        $coupon = UserCoupons::where(['id' => $couponId, 'user_id' => $userId, 'status' => '1'])
            ->where('endtime', '>', time())
            ->lock(true)
            ->find();
        if(!$coupon){
            throw new Exception('优惠券不可用');
        }
        if(bccomp((string)$coupon['use_money'], (string)$money, 2) > 0){
            throw new Exception('未达到优惠券使用门槛');
        }
        if($coupon['goods_type'] == 2){
            $couponGoodsIds = explode(',', $coupon['goods_ids']);
            $matched = false;
            foreach($goodsIds as $goodsId){
                if(in_array($goodsId, $couponGoodsIds)){
                    $matched = true;
                    break;
                }
            }
            if(!$matched){
                throw new Exception('优惠券不适用于当前商品');
            }
        }
        $coupon->status = '2';
        $coupon->save();
        return $coupon;
    }

    /**
     * 创建订单
     */
    public function createOrder($data,$user_id){
        $address_info = Address::where(['id' => $data['address_id'],'user_id' => $user_id,])->field('name,phone,province_id,city_id,county_id,address,province_name,city_name,county_name')->find();
        if(!$address_info){
            json_error('收货地址错误');
        }
        $address_info = $address_info->toArray();
        $info = $this->pre($data,$user_id);
        $this->startTrans();
        try{
            $order_no = $this->order_no($user_id);
            $couponId = isset($info['coupon_id']) ? (int)$info['coupon_id'] : 0;
            $goodsIds = [];
            foreach($info['goods_list'] as $goodsItem){
                $goodsIds[] = $goodsItem['goods_id'];
            }
            if($couponId > 0){
                $this->lockAndUseCoupon($couponId, $user_id, $goodsIds, bcadd(bcsub($info['all_money'], 0, 2), $info['coupon_discount_money'], 2));
            }
            $order_data = [
                'order_type' => $info['order_type'],
                'user_id' => $user_id,
                'order_no' => $order_no,
                'order_money' => $info['all_money'],
                'goods_money' => $info['goods_money'],
                'discount_money' => $info['discount_money'],
                'discount_integral' => $info['use_integral'],
                'coupon_id' => $couponId ?: null,
                'createtime' => time(),
                'remarks' => isset($data['remarks']) ? $data['remarks'] : '',
                'goods_num' => $info['goods_num']
            ];
            $order_data = array_merge($order_data,$address_info);
            //判断用户余额
            $userInfo = User::where(['id'=>$user_id])->find();
            if(isset($data['use_integral']) && $data['use_integral'] == 1){
                //扣除积分
                $res = User::changeIntegral(
                    [
                        'user_id' => $userInfo['id'],
                        'money' => $userInfo['integral'],
                        'type' => 'sub',
                        'memo' => '订单抵扣',
                        'order_no' => $order_no,
                        'change_type' => 'pay_integral'
                    ]
                );
                if(!$res){
                    Db::rollback();
                    throw new \Exception('扣减积分错误');
                }
            }
            if($userInfo['money'] >= $info['all_money']){
                $payment = $order_data['payment'] = 'balance';
            }else{
                $order_data['cash_money'] = $userInfo['money'];
                $payment =  $order_data['payment'] = 'wechat';
            }

            $order_id = $this->insertGetId($order_data);
            if(!$order_id){
                throw new Exception('');
            }
            $order_item = [];
            $recipeName = isset($data['recipe_name']) ? trim($data['recipe_name']) : '';
            $recipeTotalWeight = isset($data['recipe_total_weight']) ? (int)$data['recipe_total_weight'] : 0;
            if ($recipeTotalWeight <= 0 && isset($info['return']['total_weight'])) {
                $recipeTotalWeight = (int)$info['return']['total_weight'];
            }
            $recipeId = isset($data['recipe_id']) ? (int)$data['recipe_id'] : 0;

            foreach ($info['goods_list'] as $v){
                $itemJson = $v;
                if ($info['order_type'] == 1) {
                    if ($recipeName !== '') {
                        $itemJson['recipe_name'] = $recipeName;
                    }
                    if ($recipeTotalWeight > 0) {
                        $itemJson['recipe_total_weight'] = $recipeTotalWeight;
                        $itemJson['total_weight'] = $recipeTotalWeight;
                    }
                    if ($recipeId > 0) {
                        $itemJson['recipe_id'] = $recipeId;
                    }
                }

                $item = [
                    'order_id' => $order_id,
                    'goods_id' => $v['goods_id'],
                    'stock_id' => $v['stock_id'],
                    'num' => $v['num'],
                    'goods_title' => $v['goods_info']['name'],
                    'goods_image' => $v['goods_info']['image'],
                    'stock_title' => $info['order_type'] == 1 ? '' : $v['stock']['goods_sku_text'],
                    'money' => $v['money'],
                    'json' => json_encode($itemJson),
                    'goods_category' => $v['stock']['goods_category'],
                    'unit_price' => $v['stock']['money']
                ];
                if(isset($v['weight']) && $v['weight'] > 0){
                    $item['weight'] = $v['weight'];
                }
                if(isset($v['baking']) && !empty($v['baking'])){
                    $item['baking'] = $v['baking'];
                }
                if($info['order_type'] == 1){
                    $item['unit_price'] = $v['goods_info']['customized_price'];
                }
                $stockResult = SkuPrice::where(['id' => $v['stock_id'], 'goods_id' => $v['goods_id'], 'status' => 'up'])
                    ->where('stock', '>=', $v['num'])
                    ->setDec('stock', $v['num']);
                if (!$stockResult) {
                    throw new \Exception('库存不足');
                }
                $order_item[] = $item;
            }
            model('\app\api\model\OrderItem')->saveAll($order_item);
            if($recipeId > 0 && $info['order_type'] == 1){
                Db::name('yp_user_recipe')
                    ->where(['id' => $recipeId, 'status' => 'normal'])
                    ->update([
                        'last_order_money' => $info['all_money'],
                        'updatetime' => time()
                    ]);
                Db::name('yp_user_recipe')
                    ->where(['id' => $recipeId, 'status' => 'normal'])
                    ->setInc('order_count');
            }
            if(!empty($data['cart_id'])){
                Cart::where(['id' => ['in',$data['cart_id']],'user_id' => $user_id])->delete();
            }
            $this->commit();
        }catch (Exception $e){
            $this->rollback();
            json_error($e->getMessage() ?: '创建订单失败');
        }
        return compact('order_id','payment');
    }

    /**
     * 创建订单号
     */
    public function order_no($user_id){
        $rand = $user_id < 9999 ? mt_rand(100000, 9999999) : mt_rand(100, 99999);
        $order_sn = date('Yhis') . $rand;
        $id = str_pad($user_id, (24 - strlen($order_sn)), '0', STR_PAD_BOTH);
        return $order_sn . $id;
    }

    public function item(){
        return $this->hasMany(OrderItem::class,'order_id','id');
    }

}
