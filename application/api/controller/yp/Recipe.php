<?php
namespace app\api\controller\yp;

use app\api\model\Goods;
use app\api\model\SkuPrice;
use app\api\model\UserRecipe;
use app\api\model\User;
use think\Config;
use think\Db;
class Recipe extends Base{

    protected $noNeedLogin = ['wall', 'featured', 'detail', 'walldetail', 'replicate', 'share', 'comments'];
    protected $noNeedRight = '*';

    protected function tableName($name)
    {
        return Config::get('database.prefix') . $name;
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

    protected function tableExists($name)
    {
        $table = str_replace('`', '', $this->tableName($name));
        try {
            return !empty(Db::query("SHOW TABLES LIKE '{$table}'"));
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function normalizeSourceType($sourceType)
    {
        return $sourceType === 'official' ? 'official' : 'user';
    }

    protected function currentUserId()
    {
        if(!$this->auth){
            return 0;
        }
        if(!$this->auth->isLogin()){
            $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('token')));
            if($token){
                $this->auth->init($token);
            }
        }
        return $this->auth->isLogin() && isset($this->auth->id) ? (int)$this->auth->id : 0;
    }

    protected function interactionEnabled()
    {
        return $this->tableExists('yp_recipe_interaction');
    }

    protected function likeEnabled()
    {
        return $this->tableExists('yp_recipe_like');
    }

    protected function commentEnabled()
    {
        return $this->tableExists('yp_recipe_comment');
    }

    protected function userRecipeHasColumn($column)
    {
        return $this->tableHasColumn($this->tableName('yp_user_recipe'), $column);
    }

    protected function userRecipeFields($fields)
    {
        $available = [];
        foreach($fields as $field){
            if($this->userRecipeHasColumn($field)){
                $available[] = $field;
            }
        }
        return $available;
    }

    /**
     * 我的配方列表
     */
    public function lists(){
        $fields = array_merge(
            ['id','user_id','name','recipe_data','total_weight','baking','last_order_money','order_count','createtime','updatetime'],
            $this->userRecipeFields(['author_name','author_title','scene_tags','flavor_tags','description','public_status','is_featured','copy_count','favorite_count','feedback_count','feedback_tags'])
        );
        $list = UserRecipe::where(['user_id' => $this->auth->id, 'status' => 'normal'])
            ->field(implode(',', $fields))
            ->order('updatetime desc,id desc')
            ->paginate()
            ->each(function ($item) {
                $item = $this->formatRecipeForClient($item, false);
                $check = $this->checkRecipeOrderable($item['recipe_data']);
                $item['can_order'] = $check['can_order'];
                $item['invalid_items'] = $check['invalid_items'];
                return $item;
            });
        $this->success('成功', $list);
    }

    /**
     * 配方墙：合并官方热门配方和用户公开配方。
     */
    public function wall(){
        $page = max(1, (int)$this->request->param('page', 1));
        $limit = max(1, min(30, (int)$this->request->param('list_rows', 10)));
        $items = [];

        $officialRows = Db::name('customize')->where('status', 1)->order('weigh desc,id desc')->select();
        foreach($officialRows as $row){
            $items[] = $this->formatOfficialRecipeForWall($row);
        }

        if($this->userRecipeHasColumn('public_status')){
            $fields = array_merge(
                ['id','user_id','name','recipe_data','total_weight','baking','last_order_money','order_count','createtime','updatetime'],
                $this->userRecipeFields(['author_name','author_title','scene_tags','flavor_tags','description','public_status','is_featured','copy_count','favorite_count','feedback_count','feedback_tags','featured_at'])
            );
            $userRows = UserRecipe::where(['status' => 'normal'])
                ->where('public_status', 'public')
                ->field(implode(',', $fields))
                ->order($this->userRecipeHasColumn('is_featured') ? ($this->userRecipeHasColumn('featured_at') ? 'is_featured desc,featured_at desc,updatetime desc,id desc' : 'is_featured desc,updatetime desc,id desc') : 'updatetime desc,id desc')
                ->select();
            foreach($userRows as $row){
                $item = $this->formatRecipeForClient($row, true);
                $check = $this->checkRecipeOrderable($item['recipe_data']);
                $item['can_order'] = $check['can_order'];
                $item['invalid_items'] = $check['invalid_items'];
                $items[] = $item;
            }
        }

        $items = $this->applyInteractionToItems($items);
        usort($items, function($a, $b){
            $left = isset($a['hot_score']) ? (int)$a['hot_score'] : 0;
            $right = isset($b['hot_score']) ? (int)$b['hot_score'] : 0;
            if($left === $right){
                $leftTime = isset($a['updatetime']) ? (int)$a['updatetime'] : 0;
                $rightTime = isset($b['updatetime']) ? (int)$b['updatetime'] : 0;
                if($leftTime === $rightTime){
                    return 0;
                }
                return $leftTime < $rightTime ? 1 : -1;
            }
            return $left < $right ? 1 : -1;
        });
        foreach($items as &$item){
            unset($item['wall_sort']);
        }
        unset($item);

        $total = count($items);
        $data = array_slice($items, ($page - 1) * $limit, $limit);
        $this->success('成功', [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => $total > 0 ? (int)ceil($total / $limit) : 0,
            'data' => $data
        ]);
    }

    /**
     * 精选公开配方，兼容旧入口。
     */
    public function featured(){
        if(!$this->userRecipeHasColumn('public_status') || !$this->userRecipeHasColumn('is_featured')){
            $this->success('成功', [
                'total' => 0,
                'per_page' => 10,
                'current_page' => 1,
                'last_page' => 0,
                'data' => []
            ]);
        }
        $fields = array_merge(
            ['id','user_id','name','recipe_data','total_weight','baking','last_order_money','order_count','createtime','updatetime'],
            $this->userRecipeFields(['author_name','author_title','scene_tags','flavor_tags','description','public_status','is_featured','copy_count','favorite_count','feedback_count','feedback_tags','featured_at'])
        );
        $query = UserRecipe::where(['status' => 'normal']);
        $query->where('public_status', 'public');
        $query->where('is_featured', 1);
        $sort = $this->userRecipeHasColumn('featured_at') ? 'featured_at desc,updatetime desc,id desc' : 'updatetime desc,id desc';
        $list = $query
            ->field(implode(',', $fields))
            ->order($sort)
            ->paginate()
            ->each(function ($item) {
                $item = $this->formatRecipeForClient($item, true);
                $check = $this->checkRecipeOrderable($item['recipe_data']);
                $item['can_order'] = $check['can_order'];
                $item['invalid_items'] = $check['invalid_items'];
                return $item;
            });
        $this->success('成功', $list);
    }

    /**
     * 公开配方详情，用于分享入口补全首屏。
     */
    public function detail(){
        $id = (int)$this->request->param('id');
        if(!$id){
            $this->error('配方不存在');
        }
        if(!$this->userRecipeHasColumn('public_status')){
            $this->error('配方社区暂未启用');
        }
        $fields = array_merge(
            ['id','user_id','name','recipe_data','total_weight','baking','last_order_money','order_count','createtime','updatetime'],
            $this->userRecipeFields(['author_name','author_title','scene_tags','flavor_tags','description','public_status','is_featured','copy_count','favorite_count','feedback_count','feedback_tags','featured_at'])
        );
        $query = UserRecipe::where(['id' => $id, 'status' => 'normal']);
        $query->where('public_status', 'public');
        $item = $query->field(implode(',', $fields))->find();
        if(!$item){
            $this->error('配方不存在或未公开');
        }
        $item = $this->formatRecipeForClient($item, true);
        $check = $this->checkRecipeOrderable($item['recipe_data']);
        $item['can_order'] = $check['can_order'];
        $item['invalid_items'] = $check['invalid_items'];
        $items = $this->applyInteractionToItems([$item]);
        $item = $items[0];
        $this->success('成功', $item);
    }

    /**
     * 统一配方墙详情，支持官方配方和用户公开配方分享回流。
     */
    public function wallDetail(){
        $sourceType = $this->normalizeSourceType($this->request->param('source_type', 'user'));
        $sourceId = (int)$this->request->param('source_id', $this->request->param('id'));
        if(!$sourceId){
            $this->error('配方不存在');
        }
        $item = $this->getWallRecipeItem($sourceType, $sourceId);
        if(!$item){
            $this->error('配方不存在或不可访问');
        }
        $items = $this->applyInteractionToItems([$item]);
        $this->success('成功', $items[0]);
    }

    /**
     * 一键复刻计数。
     */
    public function replicate(){
        $id = (int)$this->request->param('id');
        if(!$id){
            $this->error();
        }
        if(!$this->userRecipeHasColumn('public_status')){
            $this->error('配方社区暂未启用');
        }
        $query = UserRecipe::where(['id' => $id, 'status' => 'normal']);
        $query->where('public_status', 'public');
        $recipe = $query->find();
        if(!$recipe){
            $this->error('配方不存在或未公开');
        }
        if($this->userRecipeHasColumn('copy_count')){
            UserRecipe::where('id', $id)->setInc('copy_count');
        }
        $this->success();
    }

    /**
     * 保存配方墙配方到我的配方库。
     */
    public function collect(){
        $id = (int)$this->request->post('id');
        $sourceType = $this->request->post('source_type', 'user');
        $sourceType = $sourceType === 'official' ? 'official' : 'user';
        if(!$id){
            $this->error();
        }
        if(!$this->userRecipeHasColumn('public_status')){
            $this->error('配方社区暂未启用');
        }
        if($sourceType === 'official'){
            $source = Db::name('customize')->where(['id' => $id, 'status' => 1])->find();
            if(!$source){
                $this->error('官方配方不存在或已下架');
            }
            $official = $this->formatOfficialRecipeForWall($source);
            if(!$official['can_order']){
                $this->error('配方中有豆种暂不可用，无法保存');
            }
            $recipeData = $official['recipe_data'];
            $recipeJson = json_encode($recipeData, JSON_UNESCAPED_UNICODE);
            $name = trim(isset($source['name']) ? $source['name'] : '') ?: '官方成熟配方';
            $baking = isset($source['baking']) ? $source['baking'] : '';
            $totalWeight = 1000;
            $optional = [
                'description' => isset($source['desc']) && $source['desc'] ? $source['desc'] : (isset($source['title']) ? $source['title'] : ''),
                'scene_tags' => '官方成熟配方',
                'flavor_tags' => '',
                'author_title' => '成熟拼配',
                'public_status' => 'private',
                'is_featured' => 0,
                'featured_at' => 0
            ];
        }else{
            $query = UserRecipe::where(['id' => $id, 'status' => 'normal']);
            $query->where('public_status', 'public');
            $source = $query->find();
            if(!$source){
                $this->error('配方不存在或未公开');
            }
            $recipeData = json_decode($source['recipe_data'], true) ?: [];
            $check = $this->checkRecipeOrderable($recipeData);
            if(!$check['can_order']){
                $this->error('配方中有豆种暂不可用，无法保存');
            }
            $recipeJson = $source['recipe_data'];
            $name = trim($source['name']) ?: '公开拼配配方';
            $baking = $source['baking'];
            $totalWeight = (int)$source['total_weight'];
            $optional = [
                'description' => isset($source['description']) ? $source['description'] : '',
                'scene_tags' => isset($source['scene_tags']) ? $source['scene_tags'] : '',
                'flavor_tags' => isset($source['flavor_tags']) ? $source['flavor_tags'] : '',
                'public_status' => 'private',
                'is_featured' => 0,
                'featured_at' => 0
            ];
        }
        $exists = UserRecipe::where(['user_id' => $this->auth->id, 'status' => 'normal', 'name' => $name])->find();
        if($exists){
            $this->success('已保存过', ['id' => $exists['id']]);
        }
        $data = [
            'user_id' => $this->auth->id,
            'name' => $name,
            'recipe_data' => $recipeJson,
            'total_weight' => $totalWeight,
            'baking' => $baking,
            'last_order_money' => 0,
            'order_count' => 0,
            'createtime' => time(),
            'updatetime' => time(),
            'status' => 'normal'
        ];
        foreach($optional as $field => $value){
            if($this->userRecipeHasColumn($field)){
                $data[$field] = $value;
            }
        }
        $recipeId = UserRecipe::insertGetId($data);
        if($sourceType === 'user' && $this->userRecipeHasColumn('favorite_count')){
            UserRecipe::where('id', $id)->setInc('favorite_count');
        }
        $this->incrementInteraction($sourceType, $id, 'save_count');
        $this->success('已保存，可在我的配方中微调', ['id' => $recipeId]);
    }

    /**
     * 配方墙点赞/取消点赞。
     */
    public function like(){
        $sourceType = $this->normalizeSourceType($this->request->post('source_type', 'user'));
        $sourceId = (int)$this->request->post('source_id', $this->request->post('id'));
        $userId = $this->currentUserId();
        if(!$sourceId){
            $this->error('配方不存在');
        }
        if(!$userId){
            $this->error('请先登录');
        }
        if(!$this->likeEnabled() || !$this->interactionEnabled()){
            $this->error('配方互动功能暂未启用');
        }
        if(!$this->getWallRecipeItem($sourceType, $sourceId)){
            $this->error('配方不存在或不可访问');
        }
        $where = ['source_type' => $sourceType, 'source_id' => $sourceId, 'user_id' => $userId];
        $liked = Db::name('yp_recipe_like')->where($where)->find();
        if($liked){
            Db::name('yp_recipe_like')->where('id', $liked['id'])->delete();
            $this->incrementInteraction($sourceType, $sourceId, 'like_count', -1);
            $isLiked = 0;
        }else{
            Db::name('yp_recipe_like')->insert([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'user_id' => $userId,
                'createtime' => time()
            ]);
            $this->incrementInteraction($sourceType, $sourceId, 'like_count');
            $isLiked = 1;
        }
        $count = (int)Db::name('yp_recipe_interaction')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->value('like_count');
        $this->success('成功', ['liked' => $isLiked, 'like_count' => max(0, $count)]);
    }

    /**
     * 配方墙分享计数，按点击分享统计。
     */
    public function share(){
        $sourceType = $this->normalizeSourceType($this->request->post('source_type', 'user'));
        $sourceId = (int)$this->request->post('source_id', $this->request->post('id'));
        if(!$sourceId){
            $this->error('配方不存在');
        }
        if(!$this->getWallRecipeItem($sourceType, $sourceId)){
            $this->error('配方不存在或不可访问');
        }
        $count = $this->incrementInteraction($sourceType, $sourceId, 'share_count');
        $this->success('成功', ['share_count' => $count]);
    }

    /**
     * 配方墙评论列表。
     */
    public function comments(){
        $sourceType = $this->normalizeSourceType($this->request->param('source_type', 'user'));
        $sourceId = (int)$this->request->param('source_id', $this->request->param('id'));
        $page = max(1, (int)$this->request->param('page', 1));
        $limit = max(1, min(30, (int)$this->request->param('list_rows', 10)));
        if(!$sourceId){
            $this->error('配方不存在');
        }
        if(!$this->commentEnabled()){
            $this->success('成功', ['total' => 0, 'per_page' => $limit, 'current_page' => $page, 'last_page' => 0, 'data' => []]);
        }
        if(!$this->getWallRecipeItem($sourceType, $sourceId)){
            $this->error('配方不存在或不可访问');
        }
        $query = Db::name('yp_recipe_comment')
            ->alias('c')
            ->join('__USER__ u', 'u.id = c.user_id', 'LEFT')
            ->where(['c.source_type' => $sourceType, 'c.source_id' => $sourceId, 'c.status' => 'normal']);
        $total = (int)$query->count();
        $rows = Db::name('yp_recipe_comment')
            ->alias('c')
            ->join('__USER__ u', 'u.id = c.user_id', 'LEFT')
            ->where(['c.source_type' => $sourceType, 'c.source_id' => $sourceId, 'c.status' => 'normal'])
            ->field('c.id,c.content,c.createtime,u.nickname')
            ->order('c.createtime desc,c.id desc')
            ->page($page, $limit)
            ->select();
        foreach($rows as &$row){
            $row['author_name'] = isset($row['nickname']) && $row['nickname'] ? $row['nickname'] : '咖啡朋友';
            $row['createtime_text'] = isset($row['createtime']) ? format($row['createtime']) : '';
            unset($row['nickname']);
        }
        unset($row);
        $this->success('成功', [
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'last_page' => $total > 0 ? (int)ceil($total / $limit) : 0,
            'data' => $rows
        ]);
    }

    /**
     * 提交公开短评。
     */
    public function comment(){
        $sourceType = $this->normalizeSourceType($this->request->post('source_type', 'user'));
        $sourceId = (int)$this->request->post('source_id', $this->request->post('id'));
        $userId = $this->currentUserId();
        $content = trim(strip_tags((string)$this->request->post('content', '')));
        if(!$sourceId){
            $this->error('配方不存在');
        }
        if(!$userId){
            $this->error('请先登录');
        }
        if(!$this->commentEnabled() || !$this->interactionEnabled()){
            $this->error('配方评论功能暂未启用');
        }
        if(!$this->getWallRecipeItem($sourceType, $sourceId)){
            $this->error('配方不存在或不可访问');
        }
        if($content === ''){
            $this->error('评论内容不能为空');
        }
        $contentLength = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        if($contentLength > 80){
            $this->error('评论最多80个字');
        }
        $id = Db::name('yp_recipe_comment')->insertGetId([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'user_id' => $userId,
            'content' => $content,
            'status' => 'normal',
            'createtime' => time(),
            'updatetime' => time()
        ]);
        $count = $this->incrementInteraction($sourceType, $sourceId, 'comment_count');
        $user = User::where('id', $userId)->field('nickname')->find();
        $this->success('评论成功', [
            'id' => $id,
            'content' => $content,
            'author_name' => $user && $user['nickname'] ? $user['nickname'] : '咖啡朋友',
            'createtime_text' => format(time()),
            'comment_count' => $count
        ]);
    }

    /**
     * 保存或更新我的配方
     */
    public function save(){
        $id = $this->request->post('id');
        $name = trim($this->request->post('name'));
        $goods_list = $this->request->post('goods_list/a');
        $total_weight = intval($this->request->post('total_weight'));
        $baking = trim($this->request->post('baking'));
        $last_order_money = $this->request->post('last_order_money', 0);
        $description = trim($this->request->post('description', ''));
        $scene_tags = $this->normalizeTags($this->request->post('scene_tags/a', []));
        $flavor_tags = $this->normalizeTags($this->request->post('flavor_tags/a', []));
        $public_status = $this->request->post('public_status') === 'public' ? 'public' : 'private';

        if(!$name){
            $name = '我的拼配配方';
        }
        $name_length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if($name_length > 30){
            $this->error('配方名称最多30个字');
        }
        if(!$goods_list || !is_array($goods_list)){
            $this->error('配方数据不能为空');
        }
        $count = count($goods_list);
        if($count < 2 || $count > 5){
            $this->error('拼配豆种需为2-5种');
        }

        $total_ratio = 0;
        $recipe_goods = [];
        foreach($goods_list as $v){
            $goods_id = isset($v['goods_id']) ? intval($v['goods_id']) : 0;
            $stock_id = isset($v['stock_id']) ? intval($v['stock_id']) : 0;
            $ratio = isset($v['ratio']) ? intval($v['ratio']) : 0;
            if($goods_id <= 0 || $stock_id <= 0 || $ratio <= 0){
                $this->error('配方豆种信息不完整');
            }
            $goods = Goods::where(['id' => $goods_id, 'status' => '1', 'is_customized' => 1, 'custom_status' => 1])
                ->field('id,name,image,customized_price,ag,bean_seed,special_flavour,processing_method')
                ->find();
            if(!$goods){
                $this->error('配方中存在不可用豆种');
            }
            $stock = SkuPrice::where(['id' => $stock_id, 'goods_id' => $goods_id, 'status' => 'up'])->find();
            if(!$stock){
                $this->error('配方中存在不可用规格');
            }
            $total_ratio += $ratio;
            $recipe_goods[] = [
                'goods_id' => $goods_id,
                'id' => $goods_id,
                'stock_id' => $stock_id,
                'ratio' => $ratio,
                'name' => $goods['name'],
                'image' => $goods['image'],
                'customized_price' => $goods['customized_price'],
                'ag' => $goods['ag'],
                'bean_seed' => $goods['bean_seed'],
                'special_flavour' => $goods['special_flavour'],
                'processing_method' => $goods['processing_method']
            ];
        }
        if($total_ratio != 100){
            $this->error('配方比例之和必须为100%');
        }

        $data = [
            'user_id' => $this->auth->id,
            'name' => $name,
            'recipe_data' => json_encode(['goods_list' => $recipe_goods], JSON_UNESCAPED_UNICODE),
            'total_weight' => $total_weight,
            'baking' => $baking,
            'last_order_money' => $last_order_money,
            'updatetime' => time(),
            'status' => 'normal'
        ];
        $optionalData = [
            'description' => $description,
            'scene_tags' => implode(',', $scene_tags),
            'flavor_tags' => implode(',', $flavor_tags),
            'author_title' => $this->buildAuthorTitle($baking, 0)
        ];
        if($this->userRecipeHasColumn('public_status')){
            $optionalData['public_status'] = $public_status;
        }
        if($public_status !== 'public'){
            if($this->userRecipeHasColumn('is_featured')){
                $optionalData['is_featured'] = 0;
            }
            if($this->userRecipeHasColumn('featured_at')){
                $optionalData['featured_at'] = 0;
            }
        }
        foreach($optionalData as $field => $value){
            if($this->userRecipeHasColumn($field)){
                $data[$field] = $value;
            }
        }

        if($id){
            $recipe = UserRecipe::where(['id' => $id, 'user_id' => $this->auth->id])->find();
            if(!$recipe){
                $this->error('配方不存在');
            }
            $recipe->save($data);
            $recipe_id = $recipe['id'];
        }else{
            $data['createtime'] = time();
            $recipe_id = UserRecipe::insertGetId($data);
        }
        $this->success('保存成功', ['id' => $recipe_id]);
    }

    /**
     * 用户自己切换配方公开/私有。
     */
    public function visibility(){
        $id = (int)$this->request->post('id');
        $public_status = $this->request->post('public_status') === 'public' ? 'public' : 'private';
        if(!$id){
            $this->error();
        }
        if(!$this->userRecipeHasColumn('public_status')){
            $this->error('配方公开功能暂未启用');
        }
        $recipe = UserRecipe::where(['id' => $id, 'user_id' => $this->auth->id, 'status' => 'normal'])->find();
        if(!$recipe){
            $this->error('配方不存在');
        }
        $updates = [
            'public_status' => $public_status,
            'updatetime' => time()
        ];
        if($public_status !== 'public'){
            if($this->userRecipeHasColumn('is_featured')){
                $updates['is_featured'] = 0;
            }
            if($this->userRecipeHasColumn('featured_at')){
                $updates['featured_at'] = 0;
            }
        }
        UserRecipe::where('id', $id)->update($updates);
        $this->success($public_status === 'public' ? '已公开到配方墙' : '已设为私有', ['public_status' => $public_status]);
    }

    /**
     * 删除我的配方
     */
    public function delete(){
        $id = $this->request->post('id');
        if(!$id){
            $this->error();
        }
        $recipe = UserRecipe::where(['id' => $id, 'user_id' => $this->auth->id])->find();
        if(!$recipe){
            $this->error('配方不存在');
        }
        $recipe->status = 'hidden';
        $recipe->updatetime = time();
        $recipe->save();
        $this->success('删除成功');
    }

    protected function getWallRecipeItem($sourceType, $sourceId)
    {
        $sourceType = $this->normalizeSourceType($sourceType);
        $sourceId = (int)$sourceId;
        if($sourceType === 'official'){
            $row = Db::name('customize')->where(['id' => $sourceId, 'status' => 1])->find();
            return $row ? $this->formatOfficialRecipeForWall($row) : null;
        }
        if(!$this->userRecipeHasColumn('public_status')){
            return null;
        }
        $fields = array_merge(
            ['id','user_id','name','recipe_data','total_weight','baking','last_order_money','order_count','createtime','updatetime'],
            $this->userRecipeFields(['author_name','author_title','scene_tags','flavor_tags','description','public_status','is_featured','copy_count','favorite_count','feedback_count','feedback_tags','featured_at'])
        );
        $row = UserRecipe::where(['id' => $sourceId, 'status' => 'normal'])
            ->where('public_status', 'public')
            ->field(implode(',', $fields))
            ->find();
        if(!$row){
            return null;
        }
        $item = $this->formatRecipeForClient($row, true);
        $check = $this->checkRecipeOrderable($item['recipe_data']);
        $item['can_order'] = $check['can_order'];
        $item['invalid_items'] = $check['invalid_items'];
        return $item;
    }

    protected function interactionKey($sourceType, $sourceId)
    {
        return $this->normalizeSourceType($sourceType) . '_' . (int)$sourceId;
    }

    protected function fetchInteractionMap($items)
    {
        $map = [];
        if(!$this->interactionEnabled() || empty($items)){
            return $map;
        }
        $groups = [];
        foreach($items as $item){
            $sourceType = $this->normalizeSourceType(isset($item['source_type']) ? $item['source_type'] : 'user');
            $sourceId = (int)(isset($item['source_id']) ? $item['source_id'] : (isset($item['id']) ? $item['id'] : 0));
            if(!$sourceId){
                continue;
            }
            if(!isset($groups[$sourceType])){
                $groups[$sourceType] = [];
            }
            $groups[$sourceType][] = $sourceId;
        }
        foreach($groups as $sourceType => $ids){
            $rows = Db::name('yp_recipe_interaction')
                ->where('source_type', $sourceType)
                ->where('source_id', 'in', array_unique($ids))
                ->select();
            foreach($rows as $row){
                $map[$this->interactionKey($row['source_type'], $row['source_id'])] = $row;
            }
        }
        return $map;
    }

    protected function fetchLikedMap($items)
    {
        $map = [];
        $userId = $this->currentUserId();
        if(!$userId || !$this->likeEnabled() || empty($items)){
            return $map;
        }
        $groups = [];
        foreach($items as $item){
            $sourceType = $this->normalizeSourceType(isset($item['source_type']) ? $item['source_type'] : 'user');
            $sourceId = (int)(isset($item['source_id']) ? $item['source_id'] : (isset($item['id']) ? $item['id'] : 0));
            if(!$sourceId){
                continue;
            }
            if(!isset($groups[$sourceType])){
                $groups[$sourceType] = [];
            }
            $groups[$sourceType][] = $sourceId;
        }
        foreach($groups as $sourceType => $ids){
            $rows = Db::name('yp_recipe_like')
                ->where(['source_type' => $sourceType, 'user_id' => $userId])
                ->where('source_id', 'in', array_unique($ids))
                ->field('source_type,source_id')
                ->select();
            foreach($rows as $row){
                $map[$this->interactionKey($row['source_type'], $row['source_id'])] = 1;
            }
        }
        return $map;
    }

    protected function fetchVisibleCommentCountMap($items)
    {
        $map = [];
        if(!$this->commentEnabled() || empty($items)){
            return $map;
        }
        $groups = [];
        foreach($items as $item){
            $sourceType = $this->normalizeSourceType(isset($item['source_type']) ? $item['source_type'] : 'user');
            $sourceId = (int)(isset($item['source_id']) ? $item['source_id'] : (isset($item['id']) ? $item['id'] : 0));
            if(!$sourceId){
                continue;
            }
            if(!isset($groups[$sourceType])){
                $groups[$sourceType] = [];
            }
            $groups[$sourceType][] = $sourceId;
        }
        foreach($groups as $sourceType => $ids){
            $rows = Db::name('yp_recipe_comment')
                ->where(['source_type' => $sourceType, 'status' => 'normal'])
                ->where('source_id', 'in', array_unique($ids))
                ->field('source_type,source_id,COUNT(*) AS total')
                ->group('source_type,source_id')
                ->select();
            foreach($rows as $row){
                $map[$this->interactionKey($row['source_type'], $row['source_id'])] = (int)$row['total'];
            }
        }
        return $map;
    }

    protected function applyInteractionToItems($items)
    {
        $statsMap = $this->fetchInteractionMap($items);
        $likedMap = $this->fetchLikedMap($items);
        $commentMap = $this->fetchVisibleCommentCountMap($items);
        foreach($items as &$item){
            $sourceType = $this->normalizeSourceType(isset($item['source_type']) ? $item['source_type'] : 'user');
            $sourceId = (int)(isset($item['source_id']) ? $item['source_id'] : (isset($item['id']) ? $item['id'] : 0));
            $key = $this->interactionKey($sourceType, $sourceId);
            $stats = isset($statsMap[$key]) ? $statsMap[$key] : [];
            $saveCount = isset($stats['save_count']) ? (int)$stats['save_count'] : 0;
            $legacyFavorite = isset($item['favorite_count']) ? (int)$item['favorite_count'] : 0;
            $item['save_count'] = max($saveCount, $legacyFavorite);
            $item['favorite_count'] = $item['save_count'];
            $item['like_count'] = isset($stats['like_count']) ? (int)$stats['like_count'] : 0;
            $item['comment_count'] = isset($commentMap[$key]) ? (int)$commentMap[$key] : (isset($stats['comment_count']) ? (int)$stats['comment_count'] : 0);
            $item['share_count'] = isset($stats['share_count']) ? (int)$stats['share_count'] : 0;
            $item['liked'] = isset($likedMap[$key]) ? 1 : 0;
            $item['hot_score'] = $this->buildHotScore($item);
        }
        unset($item);
        return $items;
    }

    protected function buildHotScore($item)
    {
        $score = ((int)(isset($item['order_count']) ? $item['order_count'] : 0)) * 50;
        $score += ((int)(isset($item['favorite_count']) ? $item['favorite_count'] : 0)) * 20;
        $score += ((int)(isset($item['feedback_count']) ? $item['feedback_count'] : 0)) * 20;
        $score += ((int)(isset($item['comment_count']) ? $item['comment_count'] : 0)) * 15;
        $score += ((int)(isset($item['share_count']) ? $item['share_count'] : 0)) * 12;
        $score += ((int)(isset($item['like_count']) ? $item['like_count'] : 0)) * 10;
        $score += ((int)(isset($item['copy_count']) ? $item['copy_count'] : 0)) * 5;
        if(isset($item['is_featured']) && (int)$item['is_featured'] === 1){
            $score += 200;
        }
        if(isset($item['source_type']) && $item['source_type'] === 'official'){
            $score += min(200, (int)(isset($item['official_weigh']) ? $item['official_weigh'] : 0));
        }
        return $score;
    }

    protected function incrementInteraction($sourceType, $sourceId, $field, $step = 1)
    {
        $sourceType = $this->normalizeSourceType($sourceType);
        $sourceId = (int)$sourceId;
        $allowed = ['like_count', 'comment_count', 'share_count', 'save_count'];
        if(!$sourceId || !in_array($field, $allowed) || !$this->interactionEnabled()){
            return 0;
        }
        $now = time();
        $step = (int)$step;
        if($step >= 0){
            Db::execute(
                "INSERT INTO `" . $this->tableName('yp_recipe_interaction') . "` (`source_type`,`source_id`,`{$field}`,`createtime`,`updatetime`) VALUES (:source_type,:source_id,:step,:createtime,:updatetime) ON DUPLICATE KEY UPDATE `{$field}` = `{$field}` + :step2, `updatetime` = :updatetime2",
                ['source_type' => $sourceType, 'source_id' => $sourceId, 'step' => $step, 'createtime' => $now, 'updatetime' => $now, 'step2' => $step, 'updatetime2' => $now]
            );
        }else{
            $old = (int)Db::name('yp_recipe_interaction')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->value($field);
            $new = max(0, $old + $step);
            Db::name('yp_recipe_interaction')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->update([$field => $new, 'updatetime' => $now]);
        }
        return (int)Db::name('yp_recipe_interaction')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->value($field);
    }

    protected function checkRecipeOrderable($recipeData)
    {
        $goodsList = isset($recipeData['goods_list']) && is_array($recipeData['goods_list']) ? $recipeData['goods_list'] : [];
        $invalidItems = [];
        foreach($goodsList as $item){
            $goodsId = isset($item['goods_id']) ? intval($item['goods_id']) : (isset($item['id']) ? intval($item['id']) : 0);
            $stockId = isset($item['stock_id']) ? intval($item['stock_id']) : 0;
            $invalid = '';
            $goods = Goods::where(['id' => $goodsId, 'status' => '1', 'is_customized' => 1, 'custom_status' => 1])->find();
            if(!$goods){
                $invalid = '该咖啡豆暂不可用于定制拼配';
            }else{
                $stock = SkuPrice::where(['id' => $stockId, 'goods_id' => $goodsId, 'status' => 'up'])->find();
                if(!$stock){
                    $invalid = '该咖啡豆规格暂不可用';
                }
            }
            if($invalid){
                $item['invalid_reason'] = $invalid;
                $invalidItems[] = $item;
            }
        }
        return ['can_order' => count($invalidItems) == 0, 'invalid_items' => $invalidItems];
    }

    protected function formatRecipeForClient($item, $public = false)
    {
        $item['recipe_data'] = json_decode($item['recipe_data'], true) ?: [];
        $item['createtime_text'] = isset($item['createtime']) ? format($item['createtime']) : '';
        $item['updatetime_text'] = isset($item['updatetime']) ? format($item['updatetime']) : '';
        $item['scene_tags_arr'] = $this->splitTags(isset($item['scene_tags']) ? $item['scene_tags'] : '');
        $item['flavor_tags_arr'] = $this->splitTags(isset($item['flavor_tags']) ? $item['flavor_tags'] : '');
        $item['feedback_tags_arr'] = $this->feedbackTagsToList(isset($item['feedback_tags']) ? $item['feedback_tags'] : '');
        $item['copy_count'] = isset($item['copy_count']) ? (int)$item['copy_count'] : 0;
        $item['favorite_count'] = isset($item['favorite_count']) ? (int)$item['favorite_count'] : 0;
        $item['feedback_count'] = isset($item['feedback_count']) ? (int)$item['feedback_count'] : 0;
        $item['order_count'] = isset($item['order_count']) ? (int)$item['order_count'] : 0;
        $item['like_count'] = isset($item['like_count']) ? (int)$item['like_count'] : 0;
        $item['comment_count'] = isset($item['comment_count']) ? (int)$item['comment_count'] : 0;
        $item['share_count'] = isset($item['share_count']) ? (int)$item['share_count'] : 0;
        $item['save_count'] = isset($item['save_count']) ? (int)$item['save_count'] : $item['favorite_count'];
        $item['liked'] = isset($item['liked']) ? (int)$item['liked'] : 0;
        $item['hot_score'] = isset($item['hot_score']) ? (int)$item['hot_score'] : 0;
        $item['source_type'] = 'user';
        $item['source_id'] = (int)$item['id'];
        $item['wall_key'] = 'user_' . $item['id'];
        $item['is_featured'] = isset($item['is_featured']) ? (int)$item['is_featured'] : 0;
        $item['public_status'] = isset($item['public_status']) ? $item['public_status'] : 'private';
        if($public){
            $user = User::where('id', $item['user_id'])->field('nickname')->find();
            $fallbackName = $user && $user['nickname'] ? $user['nickname'] : ('拼豆师NO.' . $item['user_id']);
            $item['author_name'] = isset($item['author_name']) && $item['author_name'] ? $item['author_name'] : $fallbackName;
            $item['author_title'] = isset($item['author_title']) && $item['author_title'] ? $item['author_title'] : $this->buildAuthorTitle($item['baking'], $item['order_count']);
        }
        return $item;
    }

    protected function formatOfficialRecipeForWall($row)
    {
        $dataArr = json_decode(isset($row['data']) ? $row['data'] : '', true);
        $dataArr = is_array($dataArr) ? $dataArr : [];
        $invalidItems = [];
        $canOrder = true;
        foreach($dataArr as &$item){
            $goodsId = isset($item['id']) ? (int)$item['id'] : (isset($item['goods_id']) ? (int)$item['goods_id'] : 0);
            $item['id'] = $goodsId;
            $item['goods_id'] = $goodsId;
            $goods = Goods::where(['id' => $goodsId, 'status' => '1', 'is_customized' => 1, 'custom_status' => 1])
                ->field('id,image,name,bean_seed,special_flavour,processing_method,customized_price,ag')
                ->find();
            if(!$goods){
                $canOrder = false;
                $item['image'] = isset($item['image']) ? $item['image'] : '';
                $item['name'] = isset($item['name']) ? $item['name'] : '已失效咖啡豆';
                $item['invalid_reason'] = '该咖啡豆暂不可用于定制拼配';
                $invalidItems[] = $item;
                continue;
            }
            $stock = SkuPrice::where(['goods_id' => $goodsId, 'status' => 'up'])->find();
            $item['image'] = $goods['image'];
            $item['name'] = $goods['name'];
            $item['bean_seed'] = $goods['bean_seed'];
            $item['special_flavour'] = $goods['special_flavour'];
            $item['processing_method'] = $goods['processing_method'];
            $item['customized_price'] = $goods['customized_price'];
            $item['ag'] = $goods['ag'];
            $item['stock_id'] = $stock ? $stock['id'] : 0;
            if(!$stock){
                $canOrder = false;
                $item['invalid_reason'] = '该咖啡豆规格暂不可用';
                $invalidItems[] = $item;
            }
        }
        unset($item);
        $id = (int)$row['id'];
        return [
            'id' => $id,
            'source_type' => 'official',
            'source_id' => $id,
            'wall_key' => 'official_' . $id,
            'name' => isset($row['name']) ? $row['name'] : '',
            'description' => isset($row['desc']) && $row['desc'] ? $row['desc'] : (isset($row['title']) ? $row['title'] : ''),
            'baking' => isset($row['baking']) ? $row['baking'] : '',
            'author_name' => '夯萃官方',
            'author_title' => '成熟拼配',
            'scene_tags_arr' => ['官方成熟配方'],
            'flavor_tags_arr' => [],
            'feedback_tags_arr' => [],
            'copy_count' => isset($row['see']) ? (int)$row['see'] : 0,
            'favorite_count' => 0,
            'feedback_count' => 0,
            'order_count' => isset($row['sale']) ? (int)$row['sale'] : 0,
            'like_count' => 0,
            'comment_count' => 0,
            'share_count' => 0,
            'save_count' => 0,
            'liked' => 0,
            'hot_score' => 0,
            'is_featured' => 0,
            'public_status' => 'public',
            'recipe_data' => ['goods_list' => array_values($dataArr)],
            'data_arr' => array_values($dataArr),
            'invalid_items' => $invalidItems,
            'can_order' => $canOrder,
            'createtime' => isset($row['createtime']) ? (int)$row['createtime'] : 0,
            'updatetime' => isset($row['updatetime']) ? (int)$row['updatetime'] : 0,
            'official_weigh' => (int)(isset($row['weigh']) ? $row['weigh'] : 0),
            'wall_sort' => (int)(isset($row['weigh']) ? $row['weigh'] : 0)
        ];
    }

    protected function splitTags($value)
    {
        if(is_array($value)){
            return $this->normalizeTags($value);
        }
        $value = trim((string)$value);
        if($value === ''){
            return [];
        }
        return $this->normalizeTags(preg_split('/[,，、\s]+/u', $value));
    }

    protected function normalizeTags($tags)
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

    protected function feedbackTagsToList($value)
    {
        $data = json_decode((string)$value, true);
        if(!is_array($data)){
            return [];
        }
        arsort($data);
        $result = [];
        foreach($data as $tag => $count){
            $result[] = ['name' => $tag, 'count' => (int)$count];
            if(count($result) >= 6){
                break;
            }
        }
        return $result;
    }

    protected function buildAuthorTitle($baking, $orderCount)
    {
        if(strpos((string)$baking, '浅') !== false){
            return '浅烘探索者';
        }
        if(strpos((string)$baking, '深') !== false){
            return '厚感调配者';
        }
        if((int)$orderCount >= 3){
            return '复刻达人';
        }
        return '拼豆师';
    }
}
