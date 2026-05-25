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
        $list = $this->model->with(['goods' => function ($query){
            return $query->field('id,name,image,category_id,status,is_shop_sale');
        },'stock' => function ($query){
            return $query->field('id,money,goods_sku_text,status');
        }])->field('id,goods_id,stock_id,num,checked,baking')
            ->where(['user_id' => $this->auth->id])
            ->select();
        $rows = [];
        $invalidItems = [];
        $totalPrice = 0;
        $selected_count = 0;
        foreach ($list as $k=>&$v){
            $reason = '';
            if(!$v['goods']){
                $reason = '商品已失效';
            }elseif((int)$v['goods']['status'] !== 1 || (int)$v['goods']['is_shop_sale'] !== 1){
                $reason = '商品已下架';
            }elseif(!$v['stock'] || $v['stock']['status'] !== 'up'){
                $reason = '商品规格已失效';
            }
            if($reason){
                $item = $v->toArray();
                $item['invalid_reason'] = $reason;
                $invalidItems[] = $item;
                continue;
            }
            $v['category'] = GoodsCategory::where(['id' => $v['goods']['category_id']])->value('name');
            $row = $v->toArray();
            $rows[] = $row;
            if((int)$row['checked'] === 1){
                $selected_count += 1;
                $totalPrice += ((float)$row['num'] * (float)$row['stock']['money']);
            }
        }
        unset($v);
        $list = $rows;
        $valid_items = $rows;
        $invalid_items = $invalidItems;
        $total_price = bcmul($totalPrice, 1, 2);
        $cart_count = count($rows);
        $this->success('成功',compact('list','valid_items','invalid_items','total_price','selected_count','cart_count'));
    }

    /**
     * 添加购物车
     */
    public function addCart(){
        $goods_id = $this->request->param('goods_id');
        $stock_id = $this->request->param('stock_id');
        $num = $this->request->param('num');
        $baking = $this->request->param('baking');
        if(!$goods_id || !$stock_id || !$num || $num <= 0 || !$baking){
            $this->error();
        }
        $goods = Goods::where(['id' => $goods_id,'status' => '1','is_shop_sale' => 1])->find();
        if(!$goods){
            $this->error('该商品未上架商城，无法购买');
        }
        $stock = \app\api\model\SkuPrice::where(['id' => $stock_id,'goods_id' => $goods_id,'status' => 'up'])->find();
        if(!$stock){
            $this->error('商品规格不存在');
        }
        $data = [
            'user_id' => $this->auth->id,
            'goods_id' => $goods_id,
            'stock_id' => $stock_id,
            'baking' => $baking
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
        $ids = $this->request->param('ids');
        if(is_string($ids)){
            $ids = array_filter(explode(',', $ids));
        }elseif(!is_array($ids)){
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if(!$ids){
            $this->error('请选择要删除的商品');
        }
        $info = $this->model->where(['id' => ['in',$ids],'user_id' => $this->auth->id])->delete();
        $info ? $this->success('删除成功') : $this->error('商品不存在或已删除');
    }
}
