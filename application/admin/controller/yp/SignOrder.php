<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use app\admin\model\yp\Kuaidi;
use think\Db;
use think\Exception;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class SignOrder extends Backend
{

    /**
     * SignOrder模型对象
     * @var \app\admin\model\yp\SignOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\SignOrder;
        $this->view->assign("paymentList", $this->model->getPaymentList());
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
                    ->with(['user'])
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
    /**
     * 发货
     */
    public function delivery(){
        if($this->request->isPost()){
            $data = $this->request->post();
            $express_code = $data['express_code'];
            $express_info = Kuaidi::where(['code' => $express_code])->find();
            $delivertime = time();
            $order = [];
            foreach ($data['order']['id'] as $k=>$v){
                if(!$v || !$data['order']['express_no'][$k]){
                    $this->error('快递单号不能为空');
                }
                $express_no = $data['order']['express_no'][$k];
                $res = [
                    'id' => $v,
                    'status' => '3',
                    'express_name' => $express_info['name'],
                    'express_no' => $express_no,
                    'delivertime' => $delivertime
                ];
                $order[] = $res;
            }
            $res = $this->model->saveAll($order);
            if(!$res){
                $this->error('发货失败');
            }
            $this->success('发货成功');
        }else{
            $ids = $this->request->param('ids');
            $list = $this->model->where(['id' => ['in',$ids],'status' => '2'])->select();
            $kuaidi = Kuaidi::all();
            $this->assign('kuaidi',$kuaidi);
            $this->assign('lists',$list);
            return $this->fetch();
        }
    }

    /**
     * 详情
     */
    public function detail($ids = null){
        if(!$ids){
            $this->error('参数不存在');
        }
        $order_info = $this->model->where(['id' => $ids])->find();
        $this->assign('row',$order_info);
        return $this->fetch();
    }
}
