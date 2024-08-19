<?php
namespace app\api\controller\yp;

use app\api\model\Coupons as CouponsModel;
use app\api\model\UserCoupons;
use think\Db;
use think\Exception;

class Coupons extends Base{

    /**
     * 领券中心
     */
    public function lists(){
        $list = CouponsModel::where(['status' => '1','stock' => ['>',0],'endtime' => ['>',time()]])
            ->whereNotExists(function ($query){
                $userCoupons = UserCoupons::getTable();
                $coupons = CouponsModel::getTable();
                $query->table($userCoupons)->where($coupons.'.id = '.$userCoupons.'.coupons_id')->where('user_id',$this->auth->id);
                return $query;
            })
            ->field('id,amount,use_money,name,day,goods_type,endtime')
            ->order('endtime DESC')
            ->paginate()
            ->each(function ($key){
                $key['is_get'] = UserCoupons::where(['coupons_id' => $key['id'],'user_id' => $this->auth->id])->find() ? 1 : 0;
                $key['endtime'] = format($key['endtime']);
                return $key;
            });
        $this->success('成功',$list);
    }

    /**
     * 领取优惠券
     */
    public function get(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = CouponsModel::where(['id' => $id,'stock' => ['>',0],'endtime' => ['>',0],'status' => '1'])->find();
        if(!$info){
            $this->error('优惠券不存在');
        }
        if(UserCoupons::where(['coupons_id' => $id,'user_id' => $this->auth->id])->find()){
            $this->error('你已经领取过了');
        }
        Db::startTrans();
        try{
            $res = CouponsModel::where(['id' => $id,'stock' => ['>',0],'endtime' => ['>',0],'status' => '1'])->setDec('stock');
            if($res !== 1){
                $this->error();
            }
            UserCoupons::insert([
                'user_id' => $this->auth->id,
                'coupons_id' => $id,
                'name' => $info['name'],
                'goods_type' => $info['goods_type'],
                'goods_ids' => $info['goods_ids'],
                'amount' => $info['amount'],
                'use_money' => $info['use_money'],
                'endtime' => time() + $info['day'] * 86400,
                'createtime' => time()
            ]);
            Db::commit();
            $this->success();
        }catch (Exception $e){
            Db::rollback();
            $this->error();
        }
    }

    /**
     * 我的优惠券列表
     */
    public function meList(){
        $type = $this->request->param('type');
        $model = UserCoupons::where(['user_id' => $this->auth->id])
            ->order('createtime desc');
        if(!$type || !in_array($type,[1,2,3])){
            $this->error();
        }else{
            $model->where(['status' => $type]);
        }
        $list = $model->field('id,name,goods_type,amount,use_money,endtime')
            ->paginate()
            ->each(function ($key){
                $key['endtime'] = format($key['endtime']);
                return $key;
            });
        $this->success('成功',$list);
    }

    /**
     * 我的券状态数量
     */
    public function typeNum(){
        $data[1] = UserCoupons::where(['user_id' => $this->auth->id,'status' => 1])->count();
        $data[2] = UserCoupons::where(['user_id' => $this->auth->id,'status' => 2])->count();
        $data[3] = UserCoupons::where(['user_id' => $this->auth->id,'status' => 3])->count();
        $this->success('成功',$data);
    }
}