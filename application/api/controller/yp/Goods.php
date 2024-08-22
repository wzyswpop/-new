<?php
namespace app\api\controller\yp;

use app\admin\model\Comment;
use app\admin\model\Customize;
use app\admin\model\Search;
use app\api\model\Goods as GoodsModel;
use app\api\model\GoodsCategory;
use app\api\model\Collect;
use app\api\model\Coupons;
use app\api\model\SkuPrice;
use app\api\model\UserCoupons;
use EasyWeChat\Factory;
use app\api\model\UserBrowse;

class Goods extends Base{

    protected $noNeedLogin = ['hotgoods','category','info'];


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
        $info = GoodsModel::where(['id' => $id,'status' => '1','is_customized'=>0])
            ->with(['stock'])
            ->field('id,images,money,line_money,name,desc,sales,see,content,is_stock,image,product_area,bean_seed,special_flavour,processing_method,moisture_content,density,specs,baking')
            ->find();
        if(!$info){
            $this->error('商品不存在');
        }
        $info['images'] = explode(',',$info['images']);
        $info['content'] = str_replace('src="/uploads','src="https://'.$this->request->host().'/uploads',$info['content']);
        $info->setInc('see');
        $info->append(['sku']);
        $comment_count = Comment::where(['goods_id'=>$id,'status'=>1])->count();
        $comment_list = Comment::where(['goods_id'=>$id,'status'=>1])->order('star desc,id desc')->limit(1)->select();
        foreach ($comment_list as $k=>&$v)
        {
            $v['avatar'] = \app\api\model\User::where('id',$v['user_id'])->value('avatar');
            $v['nickname'] = \app\api\model\User::where('id',$v['user_id'])->value('nickname');
            if($v['images']){
                $v['images'] = explode(",",$v['images']);
            }else{
                $v['images'] = [];
            }
        }
        $info['comment_count'] = $comment_count;
        $info['comment_list'] = $comment_list;
        $this->success('成功',$info);
    }
    public function singleInfo(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = GoodsModel::where(['id' => $id,'status' => '1','is_customized'=>1])
            ->with(['stock'])
            ->field('id,images,money,line_money,name,sales,see,content,is_stock,image,product_area,bean_seed,special_flavour,processing_method,moisture_content,density,specs,baking')
            ->find();
        if(!$info){
            $this->error('商品不存在');
        }
        $info->setInc('see');
        $info->append(['sku']);
        $this->success('成功',$info);
    }

    public function custom()
    {
        $list = Customize::where('status',1)->order('weigh desc')->select();
        if($list){
            foreach ($list as $k=>$v){
                $data_arr = json_decode($v['data'],true);
                foreach ($data_arr as $k1=>&$v1){
                    $v1['image'] = GoodsModel::where(['id' => $v1['id'],'status' => '1'])->value('image');
                    $v1['name'] = GoodsModel::where(['id' => $v1['id'],'status' => '1'])->value('name');
                }
                $list[$k]['data_arr'] = $data_arr;
            }
        }
        $this->success('成功',$list);
    }
    public function customInfo()
    {
        $id = $this->request->param('id');
        $info = Customize::where('status',1)->where('id',$id)->find();
        if(!$info){
            $this->error();
        }
        $data_arr = json_decode($info['data'],true);
        $total_weight = 0;
        foreach ($data_arr as $k1=>&$v1){
            $v1['image'] = GoodsModel::where(['id' => $v1['id'],'status' => '1'])->value('image');
            $v1['name'] = GoodsModel::where(['id' => $v1['id'],'status' => '1'])->value('name');
            $v1['stock'] = SkuPrice::where(['goods_id'=>$v1['id'],'status'=>'up'])->find();
            $total_weight += bcmul($v1['ratio']/100,1000,0);
        }
        $info['total_weight'] = $total_weight;
        $info['data_arr'] = $data_arr;

        $this->success('成功',$info);
    }
    public function mulGoodsInfo(){
        $data = $this->request->post();
        $goods_list = $data['goods_list'];
        $count = count($goods_list);
        if($count > 5 || $count < 2){
            $this->error('商品数量选择错误');
        }
        $total_ratio = 0;
        $total_money = 0;
        $list = [];
        $total_weight = 0;
        $baking = '';
        foreach ($goods_list as $k=>$v){
            $total_ratio += $v['ratio'];
            $goods_info = GoodsModel::with(['stock'])->where(['id' => $v['goods_id'],'is_customized'=>1,'status'=>1])->field('id,name,image,customized_price,baking')->find();
            if(!$goods_info){
                $this->error('商品不存在');
            }
            if($goods_info['baking']){
                $baking = $goods_info['baking'];
            }
            $total_money = $total_money + bcmul($goods_info['customized_price'],bcdiv($v['ratio'],100,2),2);
            $total_weight = $total_weight + bcmul(1000,bcdiv($v['ratio'],100,2),0);
            array_push($list,$goods_info);
        }
        if($data['type'] == 2 &&$total_ratio != 100){
            $this->error('商品比例错误');
        }
        $this->success('成功',compact('list','total_money','total_ratio','baking','total_weight'));
    }
    public function commentList()
    {
        $id = $this->request->param('id');
        $image = $this->request->param('image');
        if($image){
            $list = Comment::where('goods_id',$id)->where('status',1)->whereNotNull('images')->order('id desc')->paginate()->each(function ($item, $key) {
                $item['avatar'] = \app\api\model\User::where('id',$item['user_id'])->value('avatar');
                $item['nickname'] = \app\api\model\User::where('id',$item['user_id'])->value('nickname');
                if($item['images']){
                    $item['images'] = explode(",",$item['images']);
                }else{
                    $item['images'] = [];
                }
                return $item;
            });
        }else{
            $list = Comment::where('goods_id',$id)->where('status',1)->order('id desc')->paginate()->each(function ($item, $key) {
                $item['avatar'] = \app\api\model\User::where('id',$item['user_id'])->value('avatar');
                $item['nickname'] = \app\api\model\User::where('id',$item['user_id'])->value('nickname');
                if($item['images']){
                    $item['images'] = explode(",",$item['images']);
                }else{
                    $item['images'] = [];
                }
                return $item;
            });
        }
        $this->success('ok',$list);

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
        $model = GoodsModel::where(['status' => '1','is_customized'=>0]);
        if($name){
            $model->where(['name' => ['like',"%{$name}%"]]);
            //添加搜索历史
            $insert_data = [];
            $insert_data['name'] = $name;
            $insert_data['user_id'] = $this->auth->id;
            $insert_data['createtime'] = time();
            Search::insert($insert_data);
        }
        if($category_id){
            $model->where(['category_id' => $category_id]);
            $category = GoodsCategory::where(['id' => $category_id])->find();
        }else{
            $category = [];
        }
        $list = $model->field('id,name,image,money,category_id,sales')
            ->order('weigh desc,createtime desc')
            ->paginate()
            ->each(function ($key){
                $key->append(['goods_category']);
                return $key;
            });
        $this->success('成功',compact('list','category'));
    }

    public function customeGoodsList(){
        $name = $this->request->param('name');
        $category_id = $this->request->param('category_id');
        $model = GoodsModel::where(['status' => '1','is_customized'=>1]);
        if($name){
            $model->where(['name' => ['like',"%{$name}%"]]);
            //添加搜索历史
           /* $insert_data = [];
            $insert_data['name'] = $name;
            $insert_data['user_id'] = $this->auth->id;
            $insert_data['createtime'] = time();
            Search::insert($insert_data);*/
        }
        if($category_id){
            $model->where(['category_id' => $category_id]);
            $category = GoodsCategory::where(['id' => $category_id])->find();
        }else{
            $category = [];
        }
        $list = $model->field('id,name,image,customized_price,category_id,sales,product_area,bean_seed,processing_method,special_flavour,moisture_content,density,specs,baking')
            ->order('weigh desc,createtime desc')
            ->paginate()
            ->each(function ($key){
                $key->append(['goods_category']);
                return $key;
            });
        $this->success('成功',compact('list','category'));
    }

    public function searchHistory()
    {
      $list = Search::where('user_id',$this->auth->id)->order('createtime desc')->select();
      $this->success('ok',$list);
    }
    public function clearHistory()
    {
        Search::where('user_id',$this->auth->id)->delete();
        $this->success('ok');
    }

    /**
     * 商品分类
     */
    public function category(){
        $list = GoodsCategory::where(['status' => '1'])
            ->field('id,name')
            ->order('weigh desc')
            ->select();
        $new_list[0]['id'] = 0;
        $new_list[0]['name'] = '全部';
        foreach ($list as $k=>$v){
            array_push($new_list,$v);
        }
        $this->success('成功',$new_list);
    }
}