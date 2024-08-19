<?php

namespace app\api\controller\yp;

use think\Request;
use app\api\model\KuaidiSub;
use app\api\model\SkuPrice;
use app\api\model\UserCoupons;
use think\Db;
use think\Exception;
use app\api\model\Order as OrderModel;

class Order extends Base {

    protected $noNeedLogin = [];
    protected $noNeedRight = '*';
    protected $model;

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
        $this->model = new \app\api\model\Order;
    }

    /**
     * 支付页数据
     */
    public function payData(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->where(['id' => $id,'user_id' => $this->auth->id,'status' => '1'])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $order_money = $info['order_money'];
        $money = $this->auth->money;
        $this->success('成功',compact('order_money','money'));
    }

    /**
     * 可用优惠券
     */
    public function availableCoupons(){
        $goods_ids = $this->request->param('goods_ids/a');
        $money = $this->request->param('money');
        if(!$goods_ids || !is_array($goods_ids) || !$money || $money <= 0){
            $this->error();
        }
        $pattern = '/^[1-9]\d*|0$|^[1-9]\d*\.\d{1,2}$|^0\.\d{1,2}$/';
        // 进行验证
        if (!preg_match($pattern, $money)) {
            $this->error();
        }
        $list = UserCoupons::where(['user_id' => $this->auth->id,'status' => '1','use_money' => ['<=',$money]])
            ->field('id,name,goods_type,amount,goods_ids')
            ->select();
        if($list){
            foreach ($list as $k=>$v){
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
                    }
                }
            }
            $list = array_values($list);
        }
        $this->success('',$list);
    }

    /**
     * 确认订单
     */
    public function confirmOrder(){
        $data = $this->request->post();
        $this->checkData($data);
        $return = $this->model->pre($data,$this->auth->id);
        $this->success('',$return);
    }

    /**
     * 提交订单
     */
    public function createOrder(){
        $data = $this->request->post();
        $this->checkData($data);
        $order = $this->model->createOrder($data,$this->auth->id);
        $this->success('创建成功',$order);
    }

    /**
     * 订单详情
     */
    public function details(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $order = $this->model->with(['item' => function ($query){
            return $query->field('order_id,num,goods_title,goods_image,stock_title,money,goods_category,goods_id');
        }])
            ->where(['user_id' => $this->auth->id,'id' => $id,'type' => 0])
            ->field('express_name,express_no,id,status,order_no,name,phone,province_name,city_name,county_name,address,goods_money,freight,order_money,discount_money,level_discount_money,payment,createtime,paytime,canceltime,delivertime,confirmtime')
            ->find();
        if(!$order){
            $this->error('订单不存在');
        }
        $order->append(['order_status']);
        $order['createtime'] = format($order['createtime']);
        $order['paytime'] = format($order['paytime']);
        $order['canceltime'] = format($order['canceltime']);
        $order['delivertime'] = format($order['delivertime']);
        $order['confirmtime'] = format($order['confirmtime']);
        $this->success('成功',$order);
    }

    /**
     * 订单列表
     */
    public function orderList(){
        $type = $this->request->param('type');
        $model = $this->model->with(['item' => function ($query){
            return $query->field('id,order_id,goods_id,num,goods_title,goods_image,stock_title,money,goods_category');
        }])->where(['user_id' => $this->auth->id,'type' => 0])
            ->field('id,order_no,status,goods_num,order_money');
        if($type && in_array($type,[1,2,3,4,5])){
            if($type == 5){
                $type = 0;
            }
            $model->where(['status' => $type]);
        }
        $list = $model->order('createtime DESC')
            ->paginate()
            ->each(function ($key){
                $key->append(['order_status']);
                return $key;
            });
        $this->success('获取成功',$list);
    }

    /**
     * 删除订单
     */
    public function del(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->with('item')->where(['id' => $id,'user_id' => $this->auth->id,'status' => ['in',[0,4]],'type' => 0])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $info->type = 1;
        $info->save();
        $this->success();
    }

    /**
     * 取消订单
     */
    public function cancelOrder(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->with('item')->where(['id' => $id,'user_id' => $this->auth->id,'status' => '1','type' => 0])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $info->status = '0';
        $info->canceltime = time();
        $info->save();
        foreach ($info['item'] as $v){
            SkuPrice::where(['id' => $v['stock_id'],'goods_id' => $v['goods_id']])->setInc('stock',$v['num']);
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
        Db::startTrans();
        try{
            $info = OrderModel::where(['id' => $id,'user_id' => $this->auth->id,'status' => '3','type' => 0])->find();
            if(!$info){
                $this->error('订单不存在');
            }
            $info->status = '4';
            $info->confirmtime = time();
            $score = floor($info['order_money']);
            if($score >= 1){
                $score_log = [
                    'user_id' => $this->auth->id,
                    'money' => $score,
                    'type' => 'add',
                    'memo' => '确认收货',
                    'order_no' => $info['order_no'],
                    'change_type' => 'pay'
                ];
                \app\api\model\User::changeIntegral($score_log);
            }
            $info->save();
            OrderModel::receiving($this->auth->id);
            OrderModel::distribution($id);
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error('失败');
        }
        $this->success();
    }

    /**
     * 查看物流
     */
    public function kuaidi(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $order_info = $this->model
            ->where(['id' => $id,'status' => ['not in','0,1,2']])
            ->field('name,phone,province_name,city_name,county_name,address,express_name,express_no')
            ->find();
        if(!$order_info){
            $this->error('订单不存在');
        }
        $kuaidi = KuaidiSub::where(['express_no' => $order_info['express_no']])->value('data');
        if($kuaidi){
            $kuaidi = json_decode($kuaidi,true);
        }
        $this->success('',compact('kuaidi','order_info'));
    }
}