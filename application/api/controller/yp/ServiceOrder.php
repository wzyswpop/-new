<?php

namespace app\api\controller\yp;

use think\Request;
use think\Db;
use think\Exception;
use app\api\model\Order;
use app\api\model\ServiceType;
use app\api\model\ServiceOrder as ServiceOrderModel;


class ServiceOrder extends Base {

    protected $model;

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
        $this->model = new \app\api\model\ServiceOrder;
    }

    private function customStockTitle($item){
        $parts = [];
        if(!empty($item['weight'])){
            $parts[] = $item['weight'].'g';
        }
        if(!empty($item['baking'])){
            $parts[] = $item['baking'];
        }
        return $parts ? implode(' / ', $parts) : '定制咖啡豆';
    }

    private function normalizeCustomItems($items){
        if(empty($items)){
            return $items;
        }
        foreach($items as $index => $item){
            $items[$index]['stock_title'] = $this->customStockTitle($item);
        }
        return $items;
    }

    /**
     * 售后页数据
     */
    public function getData(){
        $order_id = $this->request->param('order_id');
        $id = $this->request->param('id');
        if(!$order_id && !$id){
            $this->error();
        }
        if($order_id){
            $info = Order::where(['id' => $order_id,'status' => ['in',[2,3]],'type' => 0,'user_id' => $this->auth->id])
                ->field('id,order_no,order_money,goods_num,order_type')
                ->with(['item' => function($query){
                    return $query->field('order_id,num,goods_title,goods_image,stock_title,weight,baking');
                }])
                ->find();
            if(!$info){
                $this->error('订单不存在');
            }
        }else{
            $service_info = $this->model->where(['id' => $id,'user_id' => $this->auth->id,'status' => '2'])->find();
            if(!$service_info){
                $this->error('数据不存在');
            }
            $order_id = $service_info['order_id'];
            $info = Order::where(['id' => $order_id,'status' => ['in',[2,3]]])
                ->field('id,order_no,order_money,goods_num,order_type')
                ->with(['item' => function($query){
                    return $query->field('order_id,num,goods_title,goods_image,stock_title,weight,baking');
                }])
                ->find();
            if(!$info){
                $this->error('订单不存在');
            }
        }
        if($info['order_type'] == 1){
            $info['item'] = $this->normalizeCustomItems($info['item']);
        }
        $this->success('成功',$info);
    }

    /**
     * 售后类型原因
     */
    public function serviceType(){
        $type = $this->request->param('type');
        if(!$type || !in_array($type,[1,2])){
            $this->error();
        }
        $list = ServiceType::where(['status' => 1,'type' => $type])->field('id,name')->order('weigh desc')->select();
        $this->success('成功',$list);
    }

    /**
     * 申请售后
     */
    public function afterSales(){
        $data = $this->request->post();
        $this->checkData($data);
        $auto_refund = false;
        $refund_status = 'pending';
        Db::startTrans();
        try{
            $order_info = Order::where(['id' => $data['id'],'user_id' => $this->auth->id,'status' => ['not in','0,1,5,6'],'type' => 0])->lock(true)->find();
            if(!$order_info){
                Db::rollback();
                $this->error('订单不存在或已申请退款');
            }
            if($data['money'] <= 0){
                Db::rollback();
                $this->error('退款金额错误');
            }
            if($order_info['order_money'] < $data['money']){
                Db::rollback();
                $this->error('退款金额不能大于订单金额');
            }
            $exists = $this->model->where(['order_id' => $data['id'],'user_id' => $this->auth->id,'status' => 0])->lock(true)->find();
            if($exists){
                Db::rollback();
                $this->error('该订单已提交退款申请');
            }

            $res = [
                'user_id' => $this->auth->id,
                'order_no' => $order_info['order_no'],
                'type' => $data['type'],
                'order_id' => $data['id'],
                'money' => $data['money'],
                'explain' => $data['explain'],
                'createtime' => time(),
                'order_status' => $order_info['status'],
                'service_name' => $data['service_name']
            ];
            if(!empty($data['images'])){
                $res['images'] = implode(',',$data['images']);
            }
            if($data['type'] == 1){
                $res['return_money'] = 1;
            }else{
                $res['return_goods'] = 1;
            }
            $order_info->status = '5';
            $order_info->save();
            $service_id = $this->model->insertGetId($res);
            if($data['type'] == 1 && (int)$res['order_status'] === 2 && (time() - (int)$order_info['createtime']) <= Order::AUTO_REFUND_SECONDS){
                $service_info = ServiceOrderModel::where(['id' => $service_id])->lock(true)->find();
                Order::refundServiceOrder($service_info, $order_info);
                $auto_refund = true;
                $refund_status = 'success';
            }
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage() ?: '失败');
        }
        $this->success($auto_refund ? '退款成功' : '售后申请已提交', [
            'auto_refund' => $auto_refund,
            'refund_status' => $refund_status
        ]);
    }

    /**
     * 售后列表
     */
    public function getList(){
        $status = $this->request->param('status');
        $where = ['user_id' => $this->auth->id,'is_del'=>0];
        if(in_array($status,['0','1','2'],true)){
            $where['status'] = $status;
        }
        $list = $this->model
            ->where($where)
            ->with(['item' => function($query){
                return $query->field('id,num,goods_title,goods_image,stock_title,money,order_id,weight,baking');
            },'orders'=>function ($query1) {
                return $query1->field('id,order_type');
            }])
            ->field('id,order_id,createtime,type,refuse_memo,return_goods,return_money,status,money')
            ->order('createtime DESC')
            ->paginate()
            ->each(function ($key){
                $key['createtime'] = format($key['createtime']);
                $key->append(['type_text','return_text','return_money_text']);
                if($key['status'] == 1 || $key['return_money'] == 2){
                    $text = '退款成功';
                    if((int)$key['type'] === 1){
                        $key['return_money'] = 2;
                        $key['return_money_text'] = '退款成功';
                    }
                }else{
                    if($key['type'] == 1){
                        $text = $key['return_money_text'];
                    }else{
                        $text = $key['return_text'];
                    }
                }
                $key['text'] = $text;
                if(!empty($key['orders']) && $key['orders']['order_type'] == 1){
                    $key['item'] = $this->normalizeCustomItems($key['item']);
                }
                return $key;
            });
        $this->success('',$list);
    }

    /**
     * 取消
     */
    public function cancel(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->where(['user_id' => $this->auth->id,'id' => $id,'status' => '0'])->field('id,order_id,order_status as ostatus,type')->lock(true)->find();
        if(!$info){
            $this->error('数据不存在');
        }
        Db::startTrans();
        try{
            Order::where(['id' => $info['order_id']])->update(['status' => $info['ostatus']]);
            $update['status'] = 2;
            if($info['type'] == 1){
                $update['return_money'] = 0;
            }else{
                $update['return_goods'] = 0;
            }
            ServiceOrderModel::where(['id' => $info->id])->update($update);
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error('失败');
        }
        $this->success();
    }
    public function del(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->where(['id' => $id,'user_id' => $this->auth->id,'status' => ['in',[1,2]],'is_del' => 0])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $info->is_del = 1;
        $info->save();
        $this->success();
    }

    /**
     * 售后订单详情
     */
    public function info(){
        $data = $this->request->param();
        if(empty($data['id'])){
            $this->error('错误');
        }
        $info = $this->model->where(['user_id' => $this->auth->id,'id' => $data['id']])
            ->field('id,order_id,type,money,explain,service_name,images,createtime,handletime,status,return_goods,return_no,return_name,return_money,refuse_memo')
            ->with(['item' => function($query){
            return $query->field('order_id,num,goods_title,goods_image,stock_title,money,weight,baking');
            },'orders'=>function ($query1) {
                return $query1->field('id,status,discount_integral,order_type');
            }])->find();
        if(!$info){
            $this->error('数据不存在');
        }
        $info['images'] = explode(',',$info['images']);
        $info['createtime'] = format($info['createtime']);
        $info['handletime'] = format($info['handletime']);
        $info->append(['return_money_text','type_text','return_text']);
        if((int)$info['type'] === 1 && ((int)$info['status'] === 1 || (int)$info['return_money'] === 2)){
            $info['return_money'] = 2;
            $info['return_money_text'] = '退款成功';
        }
        if(!empty($info['orders']) && $info['orders']['order_type'] == 1){
            $info['item'] = $this->normalizeCustomItems($info['item']);
        }
        $this->success('成功',$info);
    }

    /**
     * 填写退货信息
     */
    public function returnGoods(){
        $id = $this->request->param('id');
        $return_no = $this->request->param('return_no');
        $return_name = $this->request->param('return_name');
        if(!$id || !$return_name || !$return_no){
            $this->error();
        }
        $info = $this->model->where(['id' => $id,'status' => '0','type' => 2,'return_goods' => '2'])->find();
        if(!$info){
            $this->error('数据不存在');
        }
        $this->model->where(['id' => $id])->update([
           'return_no'  => $return_no,
            'return_name' => $return_name,
            'return_goods' => '3'
        ]);
        $this->success();
    }

    /**
     * 获取退货地址信息
     */
    public function getReturnAddress(){
        $config = getValues(['return_name','return_mobile','return_address']);
        $this->success('成功',$config);
    }
}
