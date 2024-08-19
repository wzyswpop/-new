<?php
namespace app\api\controller\yp;

use app\api\model\IntegralGoods;
use think\Db;
use think\Exception;
use app\api\model\Address;
use app\api\model\User;
use app\api\model\IntegralOrder;

class Integral extends Base{

    /**
     * 我的积分数量
     */
    public function integral(){
        $this->success('成功',$this->auth->integral);
    }

    /**
     * 商品详情
     */
    public function goodsInfo(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = IntegralGoods::where(['id' => $id,'status' => '1'])->field('status,createtime',true)->find();
        if(!$info){
            $this->error('商品不存在');
        }
        $info['content'] = str_replace('src="/uploads','src="https://'.$this->request->host().'/uploads',$info['content']);
        $info['images'] = explode(',',$info['images']);
        $this->success('获取成功',$info);
    }

    /**
     * 商品列表
     */
    public function goodsList(){
        $model = IntegralGoods::where(['status' => '1'])
            ->field('id,name,money,image');
        $new = $this->request->param('new');
        $integral = $this->request->param('integral');
        $num = $this->request->param('num');
        if($new && in_array($new,['asc','desc'])){
            $model->order("createtime {$new}");
        }elseif($integral && in_array($integral,['asc','desc'])){
            $model->order("money {$new}");
        }elseif($num && in_array($num,['asc','desc'])){
            $model->order("num {$new}");
        }
        $list = $model->paginate();
        $this->success('成功',$list);
    }

    /**
     * 确认订单
     */
    public function confirmOrder(){
        $id = $this->request->param('id');
        $num = $this->request->param('num');
        if(!$id){
            $this->error();
        }
        if(preg_match('/^[1-9]\d*$/', $num) !== 1){
            $this->error('数量错误');
        }
        $info = IntegralGoods::where(['id' => $id,'status' => '1','stock' => ['>',0]])->field('id,name,money,image')->find();
        if(!$info){
            $this->error('商品不存在');
        }
        $money = $num * $info['money'];
        $this->success('成功',compact('info','money'));
    }

    /**
     * 创建订单并支付
     */
    public function pay(){
        $id = $this->request->param('id');
        $num = $this->request->param('num');
        $address_id = $this->request->param('address_id');
        $remarks = $this->request->param('remarks');
        if($remarks && mb_strlen($remarks) > 100){
            $this->error('备注最多100个字');
        }
        if(!$id || !$num || !$address_id){
            $this->error();
        }
        if(preg_match('/^[1-9]\d*$/', $num) !== 1){
            $this->error('数量错误');
        }
        $address_info = Address::where(['id' => $address_id,'user_id' => $this->auth->id])->find();
        if(!$address_info){
            $this->error('收货地址不存在');
        }
        $goods_info = IntegralGoods::where(['id' => $id,'status' => '1','stock' => ['>',0]])->find();
        if(!$goods_info){
            $this->error('商品不存在或库存不足');
        }
        if($goods_info['stock'] < $num){
            $this->error('库存不足');
        }
        $integral = $goods_info['money'] * $num;
        if($integral > $this->auth->integral){
            $this->error('积分不足');
        }
        Db::startTrans();
        try{
            $order_no = order_no();
            $res = User::changeIntegral([
                'user_id' => $this->auth->id,
                'money' => $integral,
                'type' => 'sub',
                'memo' => '兑换商品',
                'order_no' => $order_no,
                'change_type' => 'pay_integral'
            ]);
            if($res !== true){
                $this->error('失败');
            }
            IntegralOrder::insert([
                'user_id' => $this->auth->id,
                'order_no' => $order_no,
                'goods_id' => $id,
                'num' => $num,
                'goods_title' => $goods_info['name'],
                'goods_image' => $goods_info['image'],
                'score' => $integral,
                'money' => $goods_info['money'],
                'remarks' => $remarks,
                'status' => '1',
                'name' => $address_info['name'],
                'phone' => $address_info['phone'],
                'province_name' => $address_info['province_name'],
                'city_name' => $address_info['city_name'],
                'county_name' => $address_info['county_name'],
                'address' => $address_info['address'],
                'createtime' => time(),
                'paytime' => time()
            ]);
            IntegralGoods::where(['id' => $id,'stock' => ['>',0]])->setDec('stock',$num);
            IntegralGoods::where(['id' => $id,'stock' => ['>',0]])->setInc('num',$num);
            Db::commit();
            $this->success();
        }catch (Exception $e){
            Db::rollback();
            $this->error('失败'.$e->getMessage());
        }
    }

    /**
     * 订单详情
     */
    public function details(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $order = IntegralOrder::where(['user_id' => $this->auth->id,'id' => $id])
            ->field('id,order_no,num,goods_title,goods_image,score,money,remarks,status,name,phone,province_name,city_name,county_name,address,express_name,express_no,createtime,paytime,canceltime,delivertime,confirmtime')
            ->find();
        if($order){
            $order->append(['integral_status']);
            $order['createtime'] = format($order['createtime']);
            $order['paytime'] = format($order['paytime']);
            $order['canceltime'] = format($order['canceltime']);
            $order['delivertime'] = format($order['delivertime']);
            $order['confirmtime'] = format($order['confirmtime']);
            $this->success('成功',$order);
        }else{
            $this->error('订单不存在');
        }
    }

    /**
     * 订单列表
     */
    public function orderList(){
        $status = $this->request->param('status');
        $model = IntegralOrder::where(['user_id' => $this->auth->id])
            ->order('createtime desc');
        if($status && in_array($status,[1,2,3])){
            $model->where(['status' => $status]);
        }
        $list = $model->field('id,order_no,goods_title,num,goods_image,score,money,status')
            ->paginate()
            ->each(function ($key){
                $key->append(['integral_status']);
                return $key;
            });
        $this->success('获取成功',$list);
    }

    /**
     * 取消订单
     */
    public function cancelOrder(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = IntegralOrder::where(['id' => $id,'user_id' => $this->auth->id,'status' => '1'])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        Db::startTrans();
        try{
            $info->status = '0';
            $info->canceltime = time();
            $info->save();
            User::changeIntegral([
                'user_id' => $this->auth->id,
                'money' => $info['score'],
                'type' => 'add',
                'memo' => '取消订单',
                'order_no' => $info['order_no'],
                'change_type' =>  'cancel'
            ]);
            IntegralGoods::where(['id' => $info['goods_id']])->setInc('stock',$info['num']);
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error('失败');
        }
        $this->success();
    }

    /**
     * 确认收货
     */
    public function receiving(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = IntegralOrder::where(['id' => $id,'user_id' => $this->auth->id,'status' => '2'])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $info->status = '3';
        $info->confirmtime = time();
        $info->save();
        $this->success();
    }
}