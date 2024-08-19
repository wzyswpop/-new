<?php
namespace app\api\controller\yp;

use app\api\model\SignLog;
use app\api\model\Sign as SignModel;
use think\Db;
use think\Exception;
use app\api\model\SignGoods;
use app\api\model\SignOrder;
use app\api\model\Address;
use app\api\model\User;
use app\api\model\Config;

class Sign extends Base{

    /**
     * 取消订单
     */
    public function cancelOrder(){
        die;
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = SignOrder::where(['id' => $id,'user_id' => $this->auth->id,'status' => '1'])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $info->status = 0;
        $info->save();
        $user = $this->auth->getUser();
        $user->setInc('sign_num',$info['num']);
        $this->success();
    }

    /**
     * 支付页数据
     */
    public function payData(){
        die;
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = SignOrder::where(['id' => $id,'user_id' => $this->auth->id,'status' => '1'])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $order_money = $info['freight'];
        $money = $this->auth->money;
        $this->success('成功',compact('order_money','money'));
    }

    /**
     * 订单列表
     */
    public function orderList(){
        die;
        $status = $this->request->param('status');
        $model = SignOrder::where(['user_id' => $this->auth->id])
            ->order('createtime desc');
        if($status && in_array($status,[1,2,3,4,5])){
            if($status == 5){
                $status = 0;
            }
            $model->where(['status' => $status]);
        }
        $list = $model->field('id,order_no,goods_name,goods_image,status,freight')
            ->paginate()
            ->each(function ($key){
                $key->append(['sign_status']);
                return $key;
            });
        $this->success('成功',$list);
    }

    /**
     * 创建订单
     */
    public function createOrder(){
        die;
        if($this->auth->sign_num <= 0){
            $this->error('你还没有一瓶饮品,快去签到把');
        }
        $address_id = $this->request->param('address_id');
        if(!$address_id){
            $this->error('收货地址不能为空');
        }
        $address_info = Address::where(['user_id' => $this->auth->id,'id' => $address_id])->find();
        if(!$address_info){
            $this->error('收货地址错误');
        }
        $num = $this->auth->sign_num;
        $freight = 0;
        $sign_goods = SignGoods::find();
        if($this->auth->sign_num < $sign_goods['num']){
            $freight = $sign_goods['freight'];
        }
        Db::startTrans();
        try{
            $user = $this->auth->getUser();
            $user->setDec('sign_num',$num);
            $data = [
                'user_id' => $this->auth->id,
                'order_no' => order_no(),
                'num' => $num,
                'freight' => $freight,
                'name' => $address_info['name'],
                'phone' => $address_info['phone'],
                'province_name' => $address_info['province_name'],
                'city_name' => $address_info['city_name'],
                'county_name' => $address_info['county_name'],
                'address' => $address_info['address'],
                'goods_name' => $sign_goods['name'],
                'goods_image' => $sign_goods['image'],
                'goods_id' => $sign_goods['id'],
                'createtime' => time()
            ];
            if($freight <= 0){
                $data['status'] = 2;
                $data['payment'] = 'none';
                $data['paytime'] = time();
            }
            $order_id = SignOrder::insertGetId($data);
            if($order_id <= 0){
                $this->error('失败');
            }
            Db::commit();
            if($freight > 0){
                $this->success('成功',$order_id);
            }else{
                $this->success('成功',0);
            }
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 确认订单
     */
    public function confirm(){
        die;
        if($this->auth->sign_num <= 0){
            $this->error('你还没有一瓶饮品,快去签到把');
        }
        $num = $this->auth->sign_num;
        $freight = 0;
        $sign_goods = SignGoods::find();
        if($this->auth->sign_num < $sign_goods['num']){
            $freight = $sign_goods['freight'];
        }
        $this->success('成功',compact('sign_goods','freight','num'));
    }

    /**
     * 签到页面数据
     */
    public function singData(){
        $sign_day_num = getValues('sign_day_integral_num');
        $sign_type = 0;
        $sign_log = SignLog::where('user_id', $this->auth->id)->order('time desc')->find();
        $day = 0;
        if($sign_log){
            if($sign_log['time'] == strtotime((date('Y-m-d')))){  //今日已签到
                $sign_type = 1;
                $day = $sign_log['day'];
            }elseif($sign_log['time'] == strtotime(date('Y-m-d')) - 86400 && $sign_log['day'] != 28){  //昨日签到
                $day = $sign_log['day'];
            }
        }
        $num = $this->auth->sign_num;
        $this->success('成功',compact('sign_type','day','num','sign_day_num'));
    }

    /**
     * 签到
     */
    public function doSign(){
        $time = strtotime(date('Y-m-d'));
        $num = getValues('sign_day_integral_num');
        $signin = SignLog::where(['user_id' => $this->auth->id])->order('time desc')->find();
        if($signin && $signin['time'] == $time){
            $this->error('你今天已经签到过了');
        }
        $day = 1;
        if($signin && $signin['time'] == $time - 86400 && $signin['day'] != 28){
            $day = $signin['day'] + 1;
        }
        $sign = SignModel::column('day,num');
        if(isset($sign[$day])){
            $num = $sign[$day];
        }
        Db::startTrans();
        try{
            $sign_log_id = SignLog::insertGetId([
                'user_id' => $this->auth->id,
                'time' => $time,
                'createtime' => time(),
                'num' => $num,
                'day' => $day
            ]);
            if($sign_log_id <= 0){
                $this->error('签到失败');
            }
            if($num > 0){
                User::changeIntegral([
                    'money' => $num,
                    'user_id' => $this->auth->id,
                    'type' => 'add',
                    'memo' => '签到',
                    'order_no' => $sign_log_id,
                    'change_type' => 'sign'
                ]);
            }
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->success('签到失败');
        }
        $this->success('签到成功',$num);
    }
}