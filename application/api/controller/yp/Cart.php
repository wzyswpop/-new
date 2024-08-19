<?php
namespace app\api\controller\yp;

use think\Request;
use think\Db;
use think\Exception;
use app\api\model\Goods;
use app\api\model\Cart as CartModel;
use app\api\model\GoodsCategory;

class Cart extends Base {

    protected $model;

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
        $this->model = new \app\api\model\Cart;
    }

    /**
     * @return void
     */
    public function checked(){
        $id = $this->request->param('id');
        $checked = $this->request->param('checked');
        $this->model->where(['user_id' => $this->auth->id,'id' => $id])->update(['checked' => $checked]);
        $this->success('成功');
    }

    /**
     * 购物车数量变动
     */
    public function modifyNum(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $num = $this->request->param('num');
        if($num <= 0){
            $this->error();
        }
        $res = CartModel::where(['user_id' => $this->auth->id,'id' => $id])->find();
        if($res){
            $res->num = $num;
            $res->save();
            $this->success();
        }else{
            $this->error('购物车商品不存在');
        }
    }

    /**
     * 购物车商品样数
     */
    public function carNum(){
        $this->success('成功',CartModel::where(['user_id' => $this->auth->id])->group('goods_id,stock_id')->count());
    }

    /**
     * 购物车列表
     */
    public function cartList(){
        $this->model->whereNotExists(function ($query) {
            $goodsTableName = (new Goods())->getQuery()->getTable();
            $tableName = (new CartModel())->getQuery()->getTable();
            $query = $query->table($goodsTableName)->where($goodsTableName . '.id=' . $tableName . '.goods_id');
            return $query;
        })->where([
            'user_id' => $this->auth->id
        ])->delete();
        $list = $this->model->with(['goods' => function ($query){
            return $query->field('id,name,image,category_id');
        },'stock' => function ($query){
            return $query->field('id,money,goods_sku_text');
        }])->field('id,goods_id,stock_id,num,checked')
            ->where(['user_id' => $this->auth->id])
            ->select();
        foreach ($list as &$v){
            $v['category'] = GoodsCategory::where(['id' => $v['goods']['category_id']])->value('name');
        }
        unset($v);
        $this->success('成功',compact('list'));
    }

    /**
     * 添加购物车
     */
    public function addCart(){
        $goods_id = $this->request->param('goods_id');
        $stock_id = $this->request->param('stock_id');
        $num = $this->request->param('num');
        if(!$goods_id || !$stock_id || !$num || $num <= 0){
            $this->error();
        }
        $data = [
            'user_id' => $this->auth->id,
            'goods_id' => $goods_id,
            'stock_id' => $stock_id
        ];
        $info = CartModel::where($data)->find();
        if($info){
            $info->setInc('num',$num);
            $info->save();
        }else{
            $data['num'] = $num;
            $data['createtime'] = time();
            $this->model->insert($data);
        }
        $this->success();
    }

    /**
     * 删除购物车商品
     */
    public function delCart(){
        $ids = $this->request->param('ids/a');
        if(!$ids || !is_array($ids)){
            $this->error();
        }
        $info = $this->model->where(['id' => ['in',$ids],'user_id' => $this->auth->id])->delete();
        $info ? $this->success() : $this->error('失败');
    }
}