<?php

namespace app\api\controller\yp;

use app\admin\model\Comment;
use app\api\model\OrderItem;
use think\Request;
use app\api\model\KuaidiSub;
use app\api\model\UserCoupons;
use think\Config;
use think\Db;
use think\Exception;
use app\api\model\Order as OrderModel;

class Order extends Base {

    protected $noNeedLogin = [];
    protected $noNeedRight = '*';
    protected $model;

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
        $this->model = new \app\api\model\Order;
    }

    protected function tableHasColumn($table, $column)
    {
        $table = str_replace('`', '', $table);
        $column = str_replace(["'", '`'], '', $column);
        try {
            return !empty(Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'"));
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function tableName($name)
    {
        return Config::get('database.prefix') . $name;
    }

    protected function availableFields($table, $fields)
    {
        $available = [];
        foreach ($fields as $field) {
            if ($this->tableHasColumn($table, $field)) {
                $available[] = $field;
            }
        }
        return $available;
    }

    protected function normalizeRecipeFeedbackTags($tags)
    {
        if(!is_array($tags)){
            $tags = [$tags];
        }
        $result = [];
        foreach($tags as $tag){
            $tag = trim((string)$tag);
            if($tag === ''){
                continue;
            }
            $tag = function_exists('mb_substr') ? mb_substr($tag, 0, 12, 'UTF-8') : substr($tag, 0, 24);
            if(!in_array($tag, $result, true)){
                $result[] = $tag;
            }
            if(count($result) >= 8){
                break;
            }
        }
        return $result;
    }

    protected function extractRecipeIdFromOrderItems($goodsList)
    {
        foreach($goodsList as $item){
            $json = isset($item['json']) ? json_decode($item['json'], true) : [];
            if(is_array($json) && isset($json['recipe_id']) && (int)$json['recipe_id'] > 0){
                return (int)$json['recipe_id'];
            }
        }
        return 0;
    }

    protected function mergeRecipeFeedbackTags($recipeId, $tags)
    {
        $recipeId = (int)$recipeId;
        $tags = $this->normalizeRecipeFeedbackTags($tags);
        if($recipeId <= 0 || empty($tags)){
            return;
        }
        $recipeTable = $this->tableName('yp_user_recipe');
        $updates = [];
        if($this->tableHasColumn($recipeTable, 'feedback_tags')){
            $oldValue = Db::name('yp_user_recipe')->where('id', $recipeId)->value('feedback_tags');
            $stats = json_decode((string)$oldValue, true);
            if(!is_array($stats)){
                $stats = [];
            }
            foreach($tags as $tag){
                $stats[$tag] = isset($stats[$tag]) ? ((int)$stats[$tag] + 1) : 1;
            }
            $updates['feedback_tags'] = json_encode($stats, JSON_UNESCAPED_UNICODE);
        }
        if($this->tableHasColumn($recipeTable, 'feedback_count')){
            Db::name('yp_user_recipe')->where('id', $recipeId)->setInc('feedback_count');
        }
        if($this->tableHasColumn($recipeTable, 'updatetime')){
            $updates['updatetime'] = time();
        }
        if(!empty($updates)){
            Db::name('yp_user_recipe')->where('id', $recipeId)->update($updates);
        }
    }

    /**
     * 支付页数据
     */
    public function payData(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->where(['id' => $id,'user_id' => $this->auth->id,'status' => '1'])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $order_money = $info['order_money'];
        $money = $this->auth->money;
        $this->success('成功',compact('order_money','money'));
    }

    /**
     * 可用优惠券
     */
    public function availableCoupons(){
        $goods_ids = $this->request->param('goods_ids/a');
        $money = $this->request->param('money');
        if(!$goods_ids || !is_array($goods_ids) || !$money || $money <= 0){
            $this->error();
        }
        $pattern = '/^[1-9]\d*|0$|^[1-9]\d*\.\d{1,2}$|^0\.\d{1,2}$/';
        // 进行验证
        if (!preg_match($pattern, $money)) {
            $this->error();
        }
        $list = UserCoupons::where(['user_id' => $this->auth->id,'status' => '1','use_money' => ['<=',$money]])
            ->field('id,name,goods_type,amount,goods_ids')
            ->select();
        if($list){
            foreach ($list as $k=>$v){
                if($v['goods_type'] == 2){
                    $coupons_goods_id = explode(',',$v['goods_ids']);
                    $res = false;
                    foreach ($goods_ids as $vv){
                        if(in_array($vv,$coupons_goods_id)){
                            $res = true;
                            break;
                        }
                    }
                    if(!$res){
                        unset($list[$k]);
                    }
                }
            }
            $list = array_values($list);
        }
        $this->success('',$list);
    }

    /**
     * 确认订单
     */
    public function confirmOrder(){
        $data = $this->request->post();
        $this->checkData($data);
        $return = $this->model->pre($data,$this->auth->id);

        $return['start_weight'] = getValues('start_weight');
        $return['add_weight'] = getValues('add_weight');

        $return['mul_start_weight'] = getValues('mul_start_weight');
        $return['mul_add_weight'] = getValues('mul_add_weight');

        $this->success('',$return);
    }

    /**
     * 提交订单
     */
    public function createOrder(){
        $data = $this->request->post();
        $this->checkData($data);
        $order = $this->model->createOrder($data,$this->auth->id);
        $this->success('创建成功',$order);
    }

    /**
     * 订单详情
     */
    public function details(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $orderTable = $this->tableName('yp_order');
        $itemTable = $this->tableName('yp_order_item');
        $hasOrderType = $this->tableHasColumn($orderTable, 'order_type');
        $itemFields = $this->availableFields($itemTable, ['id','order_id','num','goods_title','goods_image','stock_title','money','goods_id','goods_category','weight','baking','json']);
        $orderFields = $this->availableFields($orderTable, ['remarks','express_name','express_no','id','status','order_no','name','phone','province_name','city_name','county_name','address','goods_money','goods_num','order_money','discount_money','payment','createtime','paytime','canceltime','delivertime','confirmtime','order_type','cash_money','is_comment']);
        $query = Db::name('yp_order')->where(['user_id' => $this->auth->id,'id' => $id,'type' => 0]);
        if(!empty($orderFields)){
            $query->field(implode(',', $orderFields));
        }
        $order = $query->find();
        if(!$order){
            $this->error('订单不存在');
        }
        $itemQuery = Db::name('yp_order_item')->where(['order_id' => isset($order['id']) ? $order['id'] : $id]);
        if(!empty($itemFields)){
            $itemQuery->field(implode(',', $itemFields));
        }
        $order['item'] = $itemQuery->select();
        if(!$order['item']){
            $order['item'] = [];
        }
        $orderStatus = isset($order['status']) ? (int)$order['status'] : 0;
        $orderGoodsMoney = isset($order['goods_money']) ? (float)$order['goods_money'] : 0;
        if($orderStatus == 1 && isset($order['createtime'])){
            $endtime = $order['createtime'] + getValues('overtime')*60;
            $order['cancel_text'] = '订单将于'.format($endtime).'后自动关闭，请尽快付款';
        }else{
            $order['cancel_text'] = '';
        }
        if($hasOrderType && isset($order['order_type']) && $order['order_type'] == 1 && !empty($order['item'])){
            foreach($order['item'] as $index => $item){
                $order['item'][$index]['stock_title'] = '';
            }
        }
        if($orderGoodsMoney <= 0 && !empty($order['item'])){
            $goodsMoney = 0;
            foreach($order['item'] as $item){
                $num = isset($item['num']) && $item['num'] > 0 ? $item['num'] : 1;
                $money = isset($item['money']) ? $item['money'] : 0;
                $goodsMoney += (float)$money * (float)$num;
            }
            if($goodsMoney > 0){
                $order['goods_money'] = sprintf('%.2f', $goodsMoney);
            }
        }
        $order['order_status'] = isset(\app\api\model\Base::$order_status[$orderStatus]) ? \app\api\model\Base::$order_status[$orderStatus] : '';
        foreach (['createtime','paytime','canceltime','delivertime','confirmtime'] as $timeField) {
            if(isset($order[$timeField])){
                $order[$timeField] = format($order[$timeField]);
            }
        }

        if(isset($order['express_no']) && $order['express_no']){
            $kuaidi = KuaidiSub::where(['express_no' => $order['express_no']])->value('data');
            if($kuaidi){
                $kuaidi = json_decode($kuaidi,true);
            }else{
                $kuaidi = [];
            }
        }else{
            $kuaidi = [];
        }
        $order['kuaidi'] = $kuaidi;



        $this->success('成功',$order);
    }
    public function comment(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->where(['id' => $id,'is_comment'=>0,'user_id' => $this->auth->id,'status' => ['in',[4]],'type' => 0])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        Db::startTrans();
        try {
            $insert_data = [];
            $insert_data['user_id'] = $this->auth->id;
            $insert_data['order_no'] = $info['order_no'];
            $insert_data['star'] = $this->request->param('star');
            $insert_data['comment'] = $this->request->param('comment');
            $insert_data['images'] = $this->request->param('images');
            $insert_data['createtime'] = time();
            $insert_data['updatetime'] = time();
            $goods_list = OrderItem::where(['order_id' => $id])->select();
            $recipeTags = $this->request->param('recipe_tags/a');
            if(!$recipeTags){
                $recipeTags = $this->request->param('tags/a');
            }
            $recipeId = $this->extractRecipeIdFromOrderItems($goods_list);
            foreach($goods_list as $k=>$v){
                $insert_data['goods_id'] = $v['goods_id'];
                $insert_data['sku_text'] = $v['stock_title']?:'';
                Comment::insert($insert_data);
            }
            $this->mergeRecipeFeedbackTags($recipeId, $recipeTags);
            $info->is_comment = 1;
            $info->save();
        }catch ( \Exception $e ){
            $this->error($e->getMessage());
            Db::rollback();
        }
        Db::commit();
        $this->success();
    }

    public function getComment()
    {
        $id = $this->request->param('id');
        $order_no = $this->model->where('id', $id)->value('order_no');
        $info = Comment::where(['order_no' => $order_no])->find();
        if(!$info){
            $this->error();
        }
        if($info['images']){
            $info['images'] = explode(',',$info['images']);
        }else{
            $info['images'] = [];
        }
        $this->success('ok',$info);


    }

    /**
     * 订单列表
     * 0=已取消,1=待支付,2=待发货,3=待收货,4=已完成,5=退款审核中,6=退款成功,7=待评价
     * 1=待支付,2=待发货,3=待收货,7=待评价
     */
    public function orderList(){
        $type = $this->request->param('type');
        $type = in_array((string)$type, ['1','2','3','4','5','6','7'], true) ? (int)$type : 0;
        $page = max(1, (int)$this->request->param('page', 1));
        $limit = max(1, (int)$this->request->param('list_rows', 10));
        $orderTable = $this->tableName('yp_order');
        $itemTable = $this->tableName('yp_order_item');
        $hasOrderType = $this->tableHasColumn($orderTable, 'order_type');
        $hasItemWeight = $this->tableHasColumn($itemTable, 'weight');
        $hasItemBaking = $this->tableHasColumn($itemTable, 'baking');
        $hasItemJson = $this->tableHasColumn($itemTable, 'json');

        $where = ['user_id' => $this->auth->id, 'type' => 0];
        if($type){
            if($type == 7){
                $where['status'] = 4;
                $where['is_comment'] = 0;
            }else{
                $where['status'] = $type;
            }
        }

        $orderFields = ['id','order_no','status','goods_num','order_money','is_comment','createtime'];
        if ($hasOrderType) {
            $orderFields[] = 'order_type';
        }
        $orders = Db::name('yp_order')
            ->where($where)
            ->field(implode(',', $orderFields))
            ->order('createtime DESC')
            ->page($page, $limit)
            ->select();
        $total = Db::name('yp_order')->where($where)->count();

        if($orders){
            $orderIds = array_column($orders, 'id');
            $itemFields = ['id','order_id','goods_id','num','goods_title','goods_image','stock_title','money'];
            if ($hasItemWeight) {
                $itemFields[] = 'weight';
            }
            if ($hasItemBaking) {
                $itemFields[] = 'baking';
            }
            if ($hasItemJson) {
                $itemFields[] = 'json';
            }
            $items = Db::name('yp_order_item')
                ->where(['order_id' => ['in', $orderIds]])
                ->field(implode(',', $itemFields))
                ->select();
            $itemMap = [];
            foreach($items as $item){
                $itemMap[$item['order_id']][] = $item;
            }
            foreach($orders as $index => $order){
                if (!$hasOrderType) {
                    $orders[$index]['order_type'] = 0;
                }
                $orders[$index]['order_status'] = isset(\app\api\model\Base::$order_status[$order['status']]) ? \app\api\model\Base::$order_status[$order['status']] : '';
                $orders[$index]['item'] = isset($itemMap[$order['id']]) ? $itemMap[$order['id']] : [];
                if($orders[$index]['order_type'] == 1 && !empty($orders[$index]['item'])){
                    foreach($orders[$index]['item'] as $itemIndex => $item){
                        $orders[$index]['item'][$itemIndex]['stock_title'] = '';
                    }
                }
            }
        }

        $list = [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => (int)ceil($total / $limit),
            'data' => $orders ?: []
        ];
        $this->success('获取成功',$list);
    }

    /**
     * 删除订单
     */
    public function del(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = $this->model->with('item')->where(['id' => $id,'user_id' => $this->auth->id,'status' => ['in',[0,4]],'type' => 0])->find();
        if(!$info){
            $this->error('订单不存在');
        }
        $info->type = 1;
        $info->save();
        $this->success();
    }

    /**
     * 取消订单
     */
    public function cancelOrder(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        Db::startTrans();




        try {
            $info = $this->model->with('item')->where(['id' => $id,'user_id' => $this->auth->id,'type' => 0])->whereIn('status',[1,2])->lock(true)->find();
            if(!$info){
                Db::rollback();
                $this->error('订单不存在或已取消');
            }
            if((int)$info['status'] !== 1){
                Db::rollback();
                $this->error('已付款订单请申请退款');
            }
            OrderModel::cancelWithRestore($info);
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        Db::commit();
        $this->success();
    }

    /**
     * 确认收货
     */
    public function receiving(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        Db::startTrans();
        try{
            $info = OrderModel::where(['id' => $id,'user_id' => $this->auth->id,'status' => '3','type' => 0])->lock(true)->find();
            if(!$info){
                $this->error('订单不存在');
            }
            OrderModel::confirmReceipt($info);
            Db::commit();
        }catch (Exception $e){
            Db::rollback();
            $this->error('失败');
        }
        $this->success();
    }

    /**
     * 查看物流
     */
    public function kuaidi(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $order_info = $this->model
            ->where(['id' => $id,'status' => ['not in','0,1,2']])
            ->field('name,phone,province_name,city_name,county_name,address,express_name,express_no')
            ->find();
        if(!$order_info){
            $this->error('订单不存在');
        }
        $kuaidi = KuaidiSub::where(['express_no' => $order_info['express_no']])->value('data');
        if($kuaidi){
            $kuaidi = json_decode($kuaidi,true);
        }
        $this->success('',compact('kuaidi','order_info'));
    }
}
