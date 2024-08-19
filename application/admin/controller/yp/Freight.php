<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;

/**
 * 运费模板
 */
class Freight extends Backend
{

    /**
     * Freight模型对象
     * @var \app\admin\model\yp\Freight
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\Freight;
        $this->area = collection(model('app\common\model\Area')->where('level','in',[1,2])->field('id,pid,name,level')->select())->toArray();
        $this->assignconfig('area', $this->getChild(0));
        $this->view->assign("valuationList", $this->model->getValuationList());
    }


    public function select(){
        $data = $this->model->select();
        $this->success('','',$data);
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if(empty($params['rule'])){
                $this->error(__('必须添加配送区域！！'));
            }
            if ($params) {
                $result = false;
                Db::startTrans();
                try {
                    $data = [
                        'name' => $params['name'],
                        'valuation' => $params['valuation']
                    ];
                    if($this->model->save($data)){
                        $result = true;
                    }
                    $list = [];
                    if($params['valuation'] == 2){
                        foreach ($params['rule']['fixed_money'] as $key => $value) {
                            $freight_data = [
                                'freight_id' => $this->model->id,
                                'province' => $params['rule']['province'][$key],
                                'citys' => $params['rule']['citys'][$key],
                                'fixed_money' => $value,
                            ];
                            $list[] = $freight_data;
                        }
                    }else{
                        foreach ($params['rule']['first'] as $key => $value) {
                            $freight_data = [
                                'freight_id' => $this->model->id,
                                'province' => $params['rule']['province'][$key],
                                'citys' => $params['rule']['citys'][$key],
                                'first' => $params['rule']['first'][$key],
                                'first_fee' => $params['rule']['first_fee'][$key],
                                'additional' => $params['rule']['additional'][$key],
                                'additional_fee' => $params['rule']['additional_fee'][$key]
                            ];
                            $list[] = $freight_data;
                        }
                    }
                    if(!model('app\admin\model\yp\FreightData')->allowField(true)->saveAll($list)){
                        $result = false;
                    }
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

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $result = false;
                $results = false;
                Db::startTrans();
                try {
                    $result = $row->allowField(true)->save($params);
                    model('app\admin\model\yp\FreightData')
                        ->where('freight_id','in',$ids)
                        ->delete();
                    $list = [];
                    if($params['valuation'] == 2){
                        foreach ($params['rule']['fixed_money'] as $key => $value) {
                            $freight_data = [
                                'freight_id' => $ids,
                                'province' => $params['rule']['province'][$key],
                                'citys' => $params['rule']['citys'][$key],
                                'fixed_money' => $value,
                                'first' => 0,
                                'first_fee' => 0,
                                'additional' => 0,
                                'additional_fee' => 0
                            ];
                            $list[] = $freight_data;
                        }
                    }else{
                        foreach ($params['rule']['first'] as $key => $value) {
                            $freight_data = [
                                'freight_id' => $ids,
                                'province' => $params['rule']['province'][$key],
                                'citys' => $params['rule']['citys'][$key],
                                'first' => $params['rule']['first'][$key],
                                'first_fee' => $params['rule']['first_fee'][$key],
                                'additional' => $params['rule']['additional'][$key],
                                'additional_fee' => $params['rule']['additional_fee'][$key],
                                'fixed_money' => 0
                            ];
                            $list[] = $freight_data;
                        }
                    }

                    $results = model('app\admin\model\yp\FreightData')->allowField(true)->saveAll($list);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false || $results !== false ) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->assignconfig('data', $row->freightdata);
        $this->assignconfig('valuation', $row['valuation']);
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $list = $this->model->where($pk, 'in', $ids)->select();
            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $value) {
                    if(\app\admin\model\yp\Goods::where(['freight_id' => $value['id']])->find()){
                        $this->error('有商品正在使用该模板,无法删除');
                    }
                    $count += $value->delete();
                    model('app\admin\model\yp\FreightData')
                        ->where('freight_id','in',$value['id'])
                        ->delete();
                }
                Db::commit();
            }catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    protected function getChild($myid)
    {
        $newarr = [];
        foreach ($this->area as $key => $value) {
            if (!isset($value['id'])) {
                continue;
            }
            if ($value['pid'] == $myid) {
                $newarr[$value['id']] = $value;
                $newarr[$value['id']]['city'] = $this->getChild($value['id']);
            }
        }
        return $newarr;
    }

}
