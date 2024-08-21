<?php

namespace app\api\model;

use think\Model;
use think\Exception;
use think\Db;

class Order extends Base
{

    protected $name = 'yp_order';

    /**
     * 分销
     */
    public static function distribution($order_id){
        $order_info = self::where(['id' => $order_id])->find();
        $user_info = User::where(['id' => $order_info['user_id']])->find();
        $distribution_proportion = getValues('distribution_proportion');
        if($user_info['pid'] > 0 && $distribution_proportion > 0){
            $money = $order_info['order_money'];
            $bili = bcdiv($distribution_proportion,100,2);
            if($bili > 0){
                $money = bcmul($money,$bili,2);
                User::changeCommission([
                    'user_id' => $user_info['pid'],
                    'money' => $money,
                    'type' => 'add',
                    'memo' => '分销收益',
                    'order_no' => $order_info['order_no'],
                    'change_type' => 'commission'
                ]);
            }
        }
    }

    /**
     * 是否升级
     */
    public static function receiving($user_id){
        $user_info = User::where(['id' => $user_id])->find();
        $all_money = self::where(['user_id' => $user_id,'status' => 4])->sum('order_money');
        $user_level = Level::where(['id' => $user_info['level_id']])->find();
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
        foreach ($goods_list as &$v){
            $goods_ids[] = $v['goods_id'];
            $goods_info = Goods::where(['id' => $v['goods_id'],'status' => '1'])->field('id,name,image,category_id,is_stock,is_customized,customized_price')->find();
            if(!$goods_info){
                json_error('商品不存在');
            }
            $goods_info['category'] = GoodsCategory::where(['id' => $goods_info['category_id']])->value('name');
            $v['goods_info'] = $goods_info;
            $goods_stock = SkuPrice::where(['id' => $v['stock_id'],'goods_id' => $v['goods_id'],'status' => 'up'])->field('id,stock,money,goods_sku_text')->find();
            if(!$goods_stock){
                json_error('商品不存在');
            }
            if($v['num'] > $goods_stock['stock']){
                json_error('库存不足');
            }
            $goods_stock['goods_category'] = $goods_info['category'];
            $v['stock'] = $goods_stock;
            if($goods_info['is_customized'] == 1){
                $order_type = 1;
                if(count($goods_list) > 1){
                    if($v['ratio'] <=0 ){
                        json_error('商品比例错误');
                    }
                    if(count($goods_list) > 5 ||  count($goods_list) < 2){
                        json_error('商品数量错误');
                    }
                    $total_ratio = $total_ratio + $v['ratio'];
                    $v['money'] = bcmul($goods_info['customized_price'],$v['ratio']/100,2);
                    $v['weight'] = bcmul(1000,bcdiv($v['ratio'],100,2),0);
                }else{
                    $v['money'] = bcmul($goods_info['customized_price'],$v['weight']/1000,2);
                }

            }else{
                $v['money'] = $goods_stock['money'] * $v['num'];
            }
            $goods_money += $v['money'];
            $goods_money = number_format($goods_money,2);
            $goods_num += $v['num'];
        }
        if($order_type == 1 && count($goods_list) > 1){
            if($total_ratio != 100){
                json_error('商品数量错误');
            }
        }
        unset($v);
        $all_money += $goods_money;
        $intergal = $user_info['integral'];
        $cash_integral = getValues('cash_integral');
        $intergal_cash = 0.00;
        if($cash_integral > 0){
            $intergal_cash = bcdiv($intergal,$cash_integral,2);
        }
        $discount_money = 0;
        $use_integral = 0;

        if($data['use_integral'] == 1 && $intergal && $intergal > 0 && $cash_integral> 0){
            $discount_money = bcdiv($intergal,$cash_integral,2);
            $all_money = $all_money - $discount_money;
            $use_integral = $intergal;
        }
        if($all_money <= 0){
            $all_money = 0;
        }
        $all_money += $freight;
        $all_money = number_format($all_money,2);
        $user_money = $user_info['money'];

        return compact('order_type','user_money','all_money','discount_money','goods_list','goods_money','goods_num','return','intergal','intergal_cash','address_info','use_integral');
    }

    /**
     * 可用优惠券
     */
    protected function availableCoupons($goods_ids,$money,$user_id){
        $list = UserCoupons::where(['user_id' => $user_id,'status' => '1','use_money' => ['<=',$money]])
            ->field('id,name,goods_type,amount,goods_ids,use_money,endtime')
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
                $v['endtime'] = format($v['endtime']);
            }
            unset($v);
            $list = array_values($list);
            return $list;
        }else{
            return [];
        }
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
            $order_data = [
                'order_type' => $info['order_type'],
                'user_id' => $user_id,
                'order_no' => $order_no,
                'order_money' => $info['all_money'],
                'goods_money' => $info['goods_money'],
                'discount_money' => $info['discount_money'],
                'discount_integral' => $info['use_integral'],
                'createtime' => time(),
                'remarks' => $data['remarks'],
                'goods_num' => $info['goods_num']
            ];
            $order_data = array_merge($order_data,$address_info);
            //判断用户余额
            $userInfo = User::where(['id'=>$user_id])->find();
            if($data['use_integral'] == 1){
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

            foreach ($info['goods_list'] as $v){

                $item = [
                    'order_id' => $order_id,
                    'goods_id' => $v['goods_id'],
                    'stock_id' => $v['stock_id'],
                    'num' => $v['num'],
                    'goods_title' => $v['goods_info']['name'],
                    'goods_image' => $v['goods_info']['image'],
                    'stock_title' => $v['stock']['goods_sku_text'],
                    'money' => $v['money'],
                    'json' => json_encode($v),
                    'goods_category' => $v['stock']['goods_category'],
                    'unit_price' => $v['stock']['money']
                ];
                if(isset($v['weight']) && $v['weight'] > 0){
                    $item['weight'] = $v['weight'];
                }
                if(isset($v['baking']) && !empty($v['baking'])){
                    $item['baking'] = $v['baking'];
                }
                SkuPrice::where(['id' => $v['stock_id']])->setDec('stock',$v['num']);
                $order_item[] = $item;
            }
            model('\app\api\model\OrderItem')->saveAll($order_item);
            if(!empty($data['cart_id'])){
                Cart::where(['id' => ['in',$data['cart_id']],'user_id' => $user_id])->delete();
            }
            $this->commit();
        }catch (Exception $e){
            $this->rollback();
            json_error('创建订单失败');
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
