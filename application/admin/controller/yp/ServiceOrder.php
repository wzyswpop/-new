<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use app\api\model\User;
use think\Db;
use think\Exception;

/**
 * 售后
 *
 * @icon fa fa-circle-o
 */
class ServiceOrder extends Backend
{

    /**
     * ServiceOrder模型对象
     * @var \app\admin\model\yp\ServiceOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\ServiceOrder;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();

            $list = $this->model
                    ->with(['user','orders'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('user')->visible(['nickname']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    public function detail($ids = null){
        $info = $this->model->where(['id' => $ids])->find();
        $info['images'] = explode(',',$info['images']);
        $this->assign(['row' => $info]);
        return $this->fetch();
    }

    /**
     * 仅退款审核
     */
    public function examine(){
        $type = $this->request->param('type');
        $ids = $this->request->param('ids');
        $reason = $this->request->param('reason','');
        $info = $this->model->where(['id' => $ids,'status' => 0])->find();
        if(!$info){
            $this->error('已经处理过了');
        }
        if($type == 'yes'){
            Db::startTrans();
            try{
                $orderInfo = \app\admin\model\Order::where(['id'=>$info['order_id']])->find();
                $info->status = '1';
                $info->handletime = time();
                $info->return_money = 2;
                $info->save();
                //判断支付方式
                if($orderInfo['payment'] == 'balance'){
                    $data = [
                        'user_id' => $info['user_id'],
                        'money' => $info['money'],
                        'type' => 'add',
                        'memo' => '退款',
                        'order_no' => $info['order_no'],
                        'change_type' => 'service_order'
                    ];
                    $res = User::changeMoney($data);
                }else{
                    //余额返还
                    if($orderInfo['cash_money'] > 0  && $orderInfo['cash_money'] >= $info['money']){
                        $data = [
                            'user_id' => $info['user_id'],
                            'money' => $info['money'],
                            'type' => 'add',
                            'memo' => '退款',
                            'order_no' => $info['order_no'],
                            'change_type' => 'service_order'
                        ];
                        $res = User::changeMoney($data);
                    }elseif($orderInfo['cash_money'] > 0  && $orderInfo['cash_money'] < $info['money']){
                        $data = [
                            'user_id' => $info['user_id'],
                            'money' => $orderInfo['cash_money'],
                            'type' => 'add',
                            'memo' => '退款',
                            'order_no' => $info['order_no'],
                            'change_type' => 'service_order'
                        ];
                        $res = User::changeMoney($data);
                        //微信退款



                    }
                }
                \app\admin\model\yp\Order::where(['id' => $info['order_id']])->update(['status' => '6']);
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }else{
            $info->status = '2';
            $info->return_money = '3';
            $info->handletime = time();
            $info->refuse_memo = $reason;
            \app\admin\model\yp\Order::where(['id' => $info['order_id']])->update(['status' => $info['order_status']]);
            $info->save();
        }
        $this->success('成功');
    }

    /**
     * 退货退款确认收货
     */
    public function return_confirm($ids = null){
        if(!$ids){
            $this->error('参数错误');
        }
        $type = $this->request->param('type','yes');
        $info = $this->model->where(['id' => $ids,'return_goods' => '3','type' => 2])->find();
        if(!$info){
            $this->error('数据不存在');
        }
        if($type == 'yes'){
            Db::startTrans();
            try{
                $data = [
                    'user_id' => $info['user_id'],
                    'money' => $info['money'],
                    'type' => 'add',
                    'memo' => '退款',
                    'order_no' => $info['order_no'],
                    'change_type' => 'service_order'
                ];
                User::changeMoney($data);
                $info->handletime = time();
                $info->return_goods = 5;
                $info->status = 1;
                $info->save();
                \app\admin\model\yp\Order::where(['id' => $info['order_id']])->update(['status' => '6']);
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }else{
            $info->return_goods = 4;
            $info->status = 2;
            $info->handletime = time();
            \app\admin\model\yp\Order::where(['id' => $info['order_id']])->update(['status' => $info['order_status']]);
            $info->save();
        }
        $this->success('成功');
    }

    /**
     * 退货退款审核
     */
    public function goods_examine(){
        $type = $this->request->param('type');
        $ids = $this->request->param('ids');
        $reason = $this->request->param('reason','');
        $info = $this->model->where(['id' => $ids,'status' => 0,'return_goods' => 1])->find();
        if(!$info){
            $this->error('已经处理过了');
        }
        if($type == 'yes'){
            Db::startTrans();
            try{
                $info->return_goods = '2';
                $info->handletime = time();
                $info->save();
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }else{
            $info->return_goods = '6';
            $info->status = '2';
            $info->handletime = time();
            $info->refuse_memo = $reason;
            \app\admin\model\yp\Order::where(['id' => $info['order_id']])->update(['status' => $info['order_status']]);
            $info->save();
        }
        $this->success('成功');
    }

}
