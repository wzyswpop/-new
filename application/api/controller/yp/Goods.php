<?php
namespace app\api\controller\yp;

use app\api\model\Goods as GoodsModel;
use app\api\model\GoodsCategory;
use app\api\model\Collect;
use app\api\model\Coupons;
use app\api\model\UserCoupons;
use EasyWeChat\Factory;
use app\api\model\UserBrowse;

class Goods extends Base{

    protected $noNeedLogin = ['hotgoods','goodslist','category','info'];


    /**
     * 邀请海报
     */
    public function share_code_image(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $path = 'uploads/code/';
        $file = $id.'_'.$this->auth->id.'.png';
        if(file_exists(ROOT_PATH.DS.'public'.DS.$path.DS.$file)){
            $this->success('成功','/'.$path.$file);
        }
        $data = ['uid' => $this->auth->id,'goods_id' => $id];
        $config = getValues(['miniapp_id','miniapp_secret']);
        $config = ['app_id' => $config['miniapp_id'], 'secret' => $config['miniapp_secret']];
        $app = Factory::miniProgram($config);
        $response = $app->app_code->getUnlimit(http_build_query($data));
        if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
            $response->saveAs($path, $file);
        }
        $this->success('成功','/'.$path.$file);
    }

    /**
     * 收藏列表
     */
    public function collectList(){
        Collect::whereNotExists(function ($query) {
            $goodsTableName = (new GoodsModel())->getQuery()->getTable();
            $tableName = (new Collect())->getQuery()->getTable();
            $query = $query->table($goodsTableName)->where($goodsTableName . '.id=' . $tableName . '.goods_id');
            return $query;
        })->where([
            'user_id' => $this->auth->id
        ])->delete();
        $list = Collect::alias('a')
            ->where(['a.user_id' => $this->auth->id])
            ->join('yp_goods b','a.goods_id = b.id')
            ->field('b.id,b.name,b.image')
            ->order('a.createtime desc')
            ->paginate();
        $this->success('成功',$list);
    }

    /**
     * 收藏|取消
     */
    public function collect(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $goods_info = GoodsModel::where(['id' => $id,'status' => '1'])->find();
        if(!$goods_info){
            $this->error('商品不存在');
        }
        $collect_info = Collect::where(['user_id' => $this->auth->id,'goods_id' => $id])->find();
        if($collect_info){
            $collect_info->delete();
        }else{
            Collect::insert([
                'user_id' => $this->auth->id,
                'goods_id' => $id,
                'createtime' => time()
            ]);
        }
        $this->success();
    }

    /**
     * 商品详情
     */
    public function info(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = GoodsModel::where(['id' => $id,'status' => '1'])
            ->with(['stock'])
            ->field('id,images,money,name,sales,see,content,is_stock,image')
            ->find();
        if(!$info){
            $this->error('商品不存在');
        }
        $info['images'] = explode(',',$info['images']);
        $info['content'] = str_replace('src="/uploads','src="https://'.$this->request->host().'/uploads',$info['content']);
        $info->setInc('see');
        $info->append(['sku']);
        $info['is_collect'] = 0;
        $coupons = Coupons::where(function ($query) use ($info) {
            $table = UserCoupons::getTable();
            return $query->whereRaw("find_in_set({$info['id']},goods_ids) or goods_ids = ''")->whereNotIn('id',"select coupons_id from {$table} where user_id = {$this->auth->id}");
        })->where(['stock' => ['>',0],'status' => '1','endtime' => ['>',time()]])->field('id,name,goods_type,amount,use_money,day,endtime');
        if($this->auth->isLogin()){
            $coupons->whereNotExists(function ($query){
                $userCoupons = UserCoupons::getTable();
                $coupons = Coupons::getTable();
                $query->table($userCoupons)->where($coupons.'.id = '.$userCoupons.'.coupons_id')->where('user_id',$this->auth->id);
                return $query;
            });
        }
        $coupons = $coupons->select();
        foreach ($coupons as &$v){
            $v['is_get'] = 0;
            if($this->auth->isLogin()){
                $v['is_get'] = UserCoupons::where(['coupons_id' => $v['id'],'user_id' => $this->auth->id])->find() ? 1 : 0;
            }
            $v['endtime'] = format($v['endtime']);
        }
        unset($v);
        $info['coupons'] = $coupons;
        if($this->auth->isLogin()){
            $info['is_collect'] = Collect::where(['user_id' => $this->auth->id,'goods_id' => $info['id']])->find() ? 1 : 0;
            UserBrowse::insert(['user_id' => $this->auth->id,'goods_title' => $info['name'],'goods_image' => $info['image'],'createtime' => time()]);
        }
        $this->success('成功',$info);
    }

    /**
     * 热销商品
     */
    public function hotGoods(){
        $list = GoodsModel::where(['is_hot' => 1,'status' => '1'])
            ->field('id,category_id,name,image,money')
            ->order('weigh desc,createtime desc')
            ->paginate()
            ->each(function ($key){
                $key->append(['goods_category']);
               return $key;
            });
        $this->success('成功',$list);
    }

    /**
     * 商品列表
     */
    public function goodsList(){
        $name = $this->request->param('name');
        $category_id = $this->request->param('category_id');
        $model = GoodsModel::where(['status' => '1']);
        if($name){
            $model->where(['name' => ['like',"%{$name}%"]]);
        }
        if($category_id){
            $model->where(['category_id' => $category_id]);
        }
        $list = $model->field('id,name,image,money,category_id')
            ->order('weigh desc,createtime desc')
            ->paginate()
            ->each(function ($key){
                $key->append(['goods_category']);
                return $key;
            });
        $this->success('成功',$list);
    }

    /**
     * 商品分类
     */
    public function category(){
        $list = GoodsCategory::where(['status' => '1'])
            ->field('id,name')
            ->order('weigh desc')
            ->select();
        $this->success('成功',$list);
    }
}