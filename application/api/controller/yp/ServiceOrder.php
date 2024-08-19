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
                ->field('id,order_no,order_money,goods_num')
                ->with(['item' => function($query){
                    return $query->field('order_id,num,goods_title,goods_image,stock_title');
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
                ->field('id,order_no,order_money,goods_num')
                ->with(['item' => function($query){
                    return $query->field('order_id,num,goods_title,goods_image,stock_title');
                }])
                ->find();
            if(!$info){
                $this->error('订单不存在');
            }
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
        $order_info = Order::where(['id' => $data['id'],'user_id' => $this->auth->id,'status' => ['not in','0,1,4,5,6']])->find();
        if(!$order_info){
            $this->error('订单不存在');
        }
        if($order_info['order_money'] < $data['money']){
            $this->error('退款金额不能大于订单金额');
        }
        $service_name = ServiceType::where(['id' => $data['type_id']])->value('name');
        Db::startTrans();
        try{
            $res = [
                'user_id' => $this->auth->id,
                'order_no' => $order_info['order_no'],
                'type' => $data['type'],
                'order_id' => $data['id'],
                'money' => $data['money'],
                'explain' => $data['explain'],
                'createtime' => time(),
                'order_status' => $order_info['status'],
                'service_name' => $service_name
            ];
            if($data['images']){
                $res['images'] = implode(',',$data['images']);
            }
            if($data['type'] == 1){
                $res['return_money'] = 1;
            }else{
                $res['return_goods'] = 1;
            }
            $order_info->status = '5';
            $order_info->save();
            $this->model->insert($res);
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error('失败');
        }
        $this->success();
    }

    /**
     * 售后列表
     */
    public function getList(){
        $status = $this->request->param('status');
        $where = ['user_id' => $this->auth->id];
        if(in_array($status,['0','1','2'],true)){
            $where['status'] = $status;
        }
        $list = $this->model
            ->where($where)
            ->with(['item' => function($query){
                return $query->field('id,num,goods_title,goods_image,stock_title,money,order_id');
            }])
            ->field('id,order_id,createtime,type,refuse_memo,return_goods,return_money,status')
            ->order('createtime DESC')
            ->paginate()
            ->each(function ($key){
                $key['createtime'] = format($key['createtime']);
                $key->append(['type_text','return_text','return_money_text']);
                if($key['status'] == 1){
                    $text = '卖家同意,售后完成';
                }else{
                    if($key['type'] == 1){
                        $text = $key['return_money_text'];
                    }else{
                        $text = $key['return_text'];
                    }
                }
                $key['text'] = $text;
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
            if($info->type == 1){
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

    /**
     * 售后订单详情
     */
    public function info(){
        $data = $this->request->param();
        if(empty($data['id'])){
            $this->error('错误');
        }
        $info = $this->model->where(['user_id' => $this->auth->id,'id' => $data['id']])
            ->field('id,order_id,type,money,explain,service_name,images,createtime,status,return_goods,return_no,return_name,return_money,refuse_memo')
            ->with(['item' => function($query){
            return $query->field('order_id,num,goods_title,goods_image,stock_title');
            }])->find();
        if(!$info){
            $this->error('数据不存在');
        }
        $info['images'] = explode(',',$info['images']);
        $info['createtime'] = format($info['createtime']);
        $info->append(['return_money_text','type_text','return_text']);
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