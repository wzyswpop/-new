<?php
namespace app\api\controller\yp;

use think\Request;
use app\api\model\Area;
use app\api\model\Address as AddressModel;

class Address extends Base {

    protected $model;

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
        $this->model = new \app\api\model\Address;
    }

    /**
     * 设为默认
     */
    public function setDefault(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = AddressModel::where(['user_id' => $this->auth->id,'id' => $id])->find();
        if(!$info){
            $this->error();
        }
        if($info['is_default'] == 0){
            AddressModel::where(['user_id' => $this->auth->id])->update(['is_default' => 0]);
            $info->is_default = 1;
        }else{
            $info->is_default = 0;
        }
        $info->save();
        $this->success();
    }

    /**
     * 获取默认地址
     */
    public function defaultAddress(){
        $default = $this->model->where(['user_id' => $this->auth->id,'is_default' => '1'])->field('user_id,createtime,province_id,county_id,city_id',true)->find();
        $this->success('成功',$default);
    }

    /**
     * 我的收货地址列表
     */
    public function addressList(){
        $list = $this->model->where(['user_id' => $this->auth->id])->field('id,name,phone,province_name,city_name,county_name,address,is_default')->select();
        $this->success('成功',$list);
    }

    /**
     * 收货地址详情
     */
    public function addressInfo(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->where(['user_id' => $this->auth->id,'id' => $id])->field('user_id,createtime',true)->find();
        $this->success('成功',$info);
    }

    /**
     * 删除地址
     */
    public function delAddress(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $this->model->where(['user_id' => $this->auth->id,'id' => $id])->delete();
        $this->success();
    }

    /**
     * 修改/新增收货地址
     */
    public function address(){
        $data = $this->request->post();
        $this->checkData($data);
        if($data['is_default'] == 1){
            $this->model->where(['user_id' => $this->auth->id])->update(['is_default' => 0]);
        }
        if(!is_numeric($data['province_id'])){
            $str = mb_substr($data['province_id'],0,mb_strlen($data['province_id'])-1);
            $data['province_id'] = Area::where('pid','=',0)->where(function ($query) use ($data,$str){
                return $query->whereOr('name','=',$data['province_id'])->whereOr('name','=',$str);
            })->value('id');
            $data['city_id'] = Area::where(['pid' => $data['province_id'],'name' => ['like',"%{$data['city_id']}%"]])->value('id');
            $data['county_id'] = Area::where(['pid' => $data['city_id'],'name' => ['like',"%{$data['county_id']}%"]])->value('id');
        }
        $data['province_name'] = Area::where(['id' => $data['province_id']])->value('name') ?? $this->error('地址错误');
        $data['city_name'] = Area::where(['id' => $data['city_id']])->value('name') ?? $this->error('地址错误');
        $data['county_name'] = Area::where(['id' => $data['county_id']])->value('name') ?? $this->error('地址错误');
        if(!empty($data['id'])){
            $id = $data['id'];
            unset($data['id']);
            $res = $this->model->allowField(true)->isUpdate()->save($data,['user_id' => $this->auth->id,'id' => $id]);
        }else{
            $data['createtime'] = time();
            $data['user_id'] = $this->auth->id;
            unset($data['id']);
            $res = model('\app\api\model\Address')->allowField(true)->save($data);
            $id = model('\app\api\model\Address')->id;
        }
        $res !== false ? $this->success('成功',$id) : $this->error('失败');
    }

    /**
     * 三级联动
     */
    public function area(){
        $id = $this->request->param('id');
        $list = Area::where(['pid' => $id])->field('id,name')->select();
        $this->success('成功',$list);
    }
}