<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;

/**
 * 优惠券
 *
 * @icon fa fa-circle-o
 */
class Coupons extends Backend
{

    /**
     * Coupons模型对象
     * @var \app\admin\model\yp\Coupons
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\Coupons;
        $this->view->assign("goodsTypeList", $this->model->getGoodsTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
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
        if($params['goods_type'] == 2){
            if(empty($params['goods_ids'])){
                $this->error('请选择商品');
            }else{
                $params['goods_ids'] = array_filter($params['goods_ids']);
                $params['goods_ids'] = implode(',',$params['goods_ids']);
            }
        }else{
            $params['goods_ids'] = '';
        }
        if($params['amount'] <= 0){
            $this->error('面额必须大于0');
        }
        if($params['use_money'] < 0){
            $this->error('适用门槛错误');
        }
        if($params['day'] <= 0){
            $this->error('领取后到期天数错误');
        }
        $params['endtime'] = strtotime($params['endtime']);
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

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->assignconfig('id',$row['id']);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if($params['goods_type'] == 2){
            if(empty($params['goods_ids'])){
                $this->error('请选择商品');
            }else{
                $params['goods_ids'] = array_filter($params['goods_ids']);
                $params['goods_ids'] = implode(',',$params['goods_ids']);
            }
        }else{
            $params['goods_ids'] = '';
        }
        if($params['amount'] <= 0){
            $this->error('面额必须大于0');
        }
        if($params['use_money'] < 0){
            $this->error('适用门槛错误');
        }
        if($params['day'] <= 0){
            $this->error('领取后到期天数错误');
        }
        $params['endtime'] = strtotime($params['endtime']);
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 详情
     */
    public function detail(){
        $id = $this->request->param('id');
        $info = $this->model->where(['id' => $id])->field('id,name,goods_type,goods_ids,amount,stock,use_money,endtime,day,status')->find();
        $info['goods_ids'] = explode(',',$info['goods_ids']);
        if($info['goods_type'] == 2){
            $info['goods_list'] = \app\admin\model\yp\Goods::where(['id' => ['in',$info['goods_ids']]])->field('id,name,image,money')->select();
        }
        $info['endtime'] = date('Y-m-d H:i:s',$info['endtime']);
        return json(['code' => 1,'data' => $info]);
    }
}
