<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\Log;
use app\api\model\User;
use app\admin\model\yp\Withdrawal as WithdrawalModel;

/**
 * 提现申请
 *
 * @icon fa fa-circle-o
 */
class Withdrawal extends Backend
{
    protected $noNeedRight = ['retry_transfer', 'retrytransfer', 'query_transfer', 'querytransfer'];

    /**
     * Withdrawal模型对象
     * @var \app\admin\model\yp\Withdrawal
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\Withdrawal;
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

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


    public function detail($ids){
        $info = $this->model->where(['id' => $ids])->find();
        $this->assign('row',$info);
        return $this->fetch();
    }

    protected function transferWithdrawal($info, $new_out_bill_no = '')
    {
        $userInfo = User::get($info['user_id']);
        if(!$userInfo || !$userInfo['open_id']){
            throw new \Exception('用户的openid不存在');
        }
        $out_bill_no = $new_out_bill_no ? $new_out_bill_no : ($info['out_detail_no'] ?: $info['out_batch_no']);
        if (!$out_bill_no) {
            $out_bill_no = order_no();
        }
        $params = [
            'out_bill_no' => $out_bill_no,
            'openid' => $userInfo['open_id'],
            'transfer_amount' => (int)bcmul($info['amount_received'], 100, 0),
            'transfer_remark' => '佣金提现',
        ];
        $result = $this->model->transfer($params);

        $info->status = '2';
        $info->out_batch_no = isset($result['transfer_bill_no']) ? $result['transfer_bill_no'] : '';
        $info->out_detail_no = $out_bill_no;
        $info->package_info = isset($result['package_info']) ? $result['package_info'] : '';
        $info->transfer_state = isset($result['state']) ? $result['state'] : 'WAIT_USER_CONFIRM';
        $info->transfer_fail_reason = '';
        $info->handletime = time();
        $info->save();
    }

    public function retryTransfer(){
        $ids = $this->request->param('ids');
        Log::write('重新发起微信提现：ids=' . $ids);
        $info = WithdrawalModel::where(['id' => $ids,'status' => 2])->find();
        if(!$info){
            $this->error('记录不存在或状态不是同意');
        }
        $retryStates = ['', 'NOT_FOUND', 'FAIL', 'FAILED', 'CANCELLED', 'CANCELING', 'CLOSED'];
        if ($info['transfer_state'] && !in_array($info['transfer_state'], $retryStates)) {
            $this->error('当前微信状态为' . $info['transfer_state'] . '，不能重发；请先在微信商户后台确认该转账单已失败、撤销或退回。');
        }
        Db::startTrans();
        try{
            $this->transferWithdrawal($info, order_no());
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            Log::write('重新发起微信提现失败：' . $e->getMessage());
            $this->error($e->getMessage() ?: '重新发起微信转账失败');
        }
        $this->success('成功');
    }

    public function queryTransfer(){
        $ids = $this->request->param('ids');
        $info = WithdrawalModel::where(['id' => $ids,'status' => 2])->find();
        if(!$info || !$info['out_detail_no']){
            $this->error('记录不存在或缺少商家转账单号');
        }
        try{
            $result = $this->model->queryTransfer($info['out_detail_no']);
            $info->out_batch_no = isset($result['transfer_bill_no']) ? $result['transfer_bill_no'] : $info['out_batch_no'];
            $info->transfer_state = isset($result['state']) ? $result['state'] : $info['transfer_state'];
            $info->transfer_fail_reason = isset($result['fail_reason']) ? $result['fail_reason'] : '';
            $info->save();
        }catch (\Exception $e){
            $message = $e->getMessage() ?: '查询微信转账失败';
            if (strpos($message, 'NOT_FOUND') !== false) {
                $info->transfer_state = 'NOT_FOUND';
                $info->transfer_fail_reason = '微信商家转账记录不存在，可使用重发转账重新发起';
                $info->save();
                $this->error('微信商家转账记录不存在，这通常是旧接口未受理成功的记录；请点“重发转账”重新发起。');
            }
            $this->error($message);
        }
        $this->success('成功', null, $result);
    }

    public function query_transfer(){
        return $this->queryTransfer();
    }

    public function retry_transfer(){
        return $this->retryTransfer();
    }

    public function examine(){
        $type = $this->request->param('type');
        $ids = $this->request->param('ids');
        $reason = $this->request->param('reason','');
        $info = WithdrawalModel::where(['id' => $ids,'status' => 1])->find();
        if(!$info){
            $this->error('已经处理过了');
        }
        if($type == 'no'){
            Db::startTrans();
            try{
                WithdrawalModel::where(['id' => $info['id']])->update(['status' => '3','handletime' => time(),'memo' => $reason]);
                $data = [
                    'user_id' => $info['user_id'],
                    'money' => $info['money'],
                    'type' => 'add',
                    'memo' => '驳回提现',
                    'order_no' => $info['id'],
                    'change_type' => 'withdrawal'
                ];
                if($info['type'] == 1){
                    User::changeCommissionWithdrawal($data);
                }else{
                    User::changeMoney($data);
                }
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }else{
            Db::startTrans();
            try{
                $this->transferWithdrawal($info);
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }




        }
        $this->success('成功');
    }

}
