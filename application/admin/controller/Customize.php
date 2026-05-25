<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * 热门配方
 *
 * @icon fa fa-circle-o
 */
class Customize extends Backend
{

    /**
     * Customize模型对象
     * @var \app\admin\model\Customize
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Customize;
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    public function add1($ids = null)
    {
        if($ids){
            $baodan_info = \app\admin\model\Baodan::where("id",$ids)->find();
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $pay_data = $params['data'];
                if(empty($pay_data)){
                    $this->error(__('No rows were inserted'));
                }
                $pay_datas = json_decode($pay_data,true);
                $result = false;
                Db::startTrans();
                try {
                    $data = [];
                    $order_money = 0;
                    $goods_data = [];
                    foreach($pay_datas as $k=>$v){

                    }
                    $data['user_id'] = $baodan_info['user_id'];
                    $data['order_money'] = $order_money;
                    $data['order_no'] = order_no();
                    $data['remarks'] = $params['remarks'];
                    $data['name'] = $baodan_info['name'];
                    $data['phone'] = $baodan_info['phone'];
                    $data['province_id'] = $baodan_info['province_id'];
                    $data['city_id'] = $baodan_info['city_id'];
                    $data['county_id'] = $baodan_info['county_id'];
                    $data['address'] = $baodan_info['address'];
                    $data['createtime'] = time();
                    $data['baodan_id'] = $baodan_info['id'];

                    $order_id = $this->model->insertGetId($data);
                    foreach($goods_data as $k1=>$v1){
                        $goods_data[$k1]['order_id'] = $order_id;
                        //减少商品库存
                        \app\admin\model\Bdgoods::where('id',$v['goods_id'])->setDec('stock',$v1['num']);
                        \app\admin\model\Bdgoods::where('id',$v['goods_id'])->setInc('sales',$v1['num']);
                    }
                    $ordergoods = new \app\admin\model\OrderItem();
                    $result = $ordergoods->saveAll($goods_data);
                    $baodan_info->status = 1;
                    $baodan_info->save();
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $pay_data = $params['data'];
        if(empty($pay_data)){
            $this->error(__('No rows were inserted'));
        }
        $pay_datas = json_decode($pay_data,true);
        $total_ratio = 0;
        foreach($pay_datas as $k=>&$v){
            $total_ratio = $total_ratio + $v['ratio'];
            $v['name'] = \app\admin\model\yp\Goods::where('id',$v['id'])->value('name');

        }
        $params['data'] = json_encode($pay_datas);
        if($total_ratio != 100){
            $this->error(__('Total ratio exceeded'));
        }

        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (\PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $this->view->assign("row", $row);
        $this->view->assign("data_arr", json_decode($row->data, true));
        return $this->view->fetch();
    }


}
