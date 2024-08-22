<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use app\api\model\User;
use app\admin\model\yp\Withdrawal as WithdrawalModel;

/**
 * 提现申请
 *
 * @icon fa fa-circle-o
 */
class Withdrawal extends Backend
{

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
                    User::changeCommission($data);
                }else{
                    User::changeMoney($data);
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
        }else{
            Db::startTrans();
            try{
                $amount_received = $info['amount_received'];
                if($amount_received <= 500){
                    $userInfo = User::get($info['user_id']);
                    $out_detail_no = order_no();
                    $params = [];
                    $params['order_no'] = $info['order_no'];
                    $params['desc'] = '提现';
                    $params['total_amount'] = (int)($amount_received * 100);
                    $params['batch_list'][0]['out_detail_no'] = $out_detail_no;
                    $params['batch_list'][0]['transfer_amount'] = (int)($amount_received * 100);
                    $params['batch_list'][0]['transfer_remark'] = '提现';
                    if(!$userInfo['open_id']){
                        Db::rollback();
                        throw new \Exception('用户的openid不存在');
                    }
                    $params['batch_list'][0]['openid'] = $userInfo['open_id'];
                    $res = $this->model->transfer($params);

                    $info->status = '2';
                    $info->out_detail_no = $out_detail_no;
                    $info->handletime = time();
                    $info->save();

                }else{
                    $userInfo = User::get($info['user_id']);
                    if(!$userInfo['open_id']){
                        Db::rollback();
                        throw new \Exception('用户的openid不存在');
                    }
                    $params = [];
                    $params['order_no'] = $info['order_no'];
                    $params['desc'] = '提现';
                    $params['total_amount'] = (int)($amount_received * 100);
                    $i = 0;
                    $out_detail_no = '';
                    while ($amount_received > 0) {
                        $out_detail_no .= order_no().',';
                        $params['batch_list'][$i]['out_detail_no'] = $out_detail_no;
                        $params['batch_list'][$i]['transfer_amount'] = (int)(500 * 100);
                        $params['batch_list'][$i]['transfer_remark'] = '提现';
                        $i++;
                        $amount_received = $amount_received - 500;
                    }
                    $res = $this->model->transfer($params);

                    $info->status = '2';
                    $info->out_detail_no = trim($out_detail_no,',');
                    $info->handletime = time();
                    $info->save();

                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }




        }
        $this->success('成功');
    }

}
