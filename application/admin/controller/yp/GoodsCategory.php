<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;

/**
 * 商品分类
 *
 * @icon fa fa-circle-o
 */
class GoodsCategory extends Backend
{

    /**
     * GoodsCategory模型对象
     * @var \app\admin\model\yp\GoodsCategory
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\GoodsCategory;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("showsList", $this->model->getShowsList());
    }


    public function select(){
        $data = $this->model->where(['status' => '1'])->field('id as value,name as label')->order('weigh DESC')->select();
        $this->success('s','',$data);
    }

}
