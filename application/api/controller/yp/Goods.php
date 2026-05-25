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

    protected $noNeedLogin = ['hotgoods','category','info','commentList','quickblendrecommend','quickblendbudgetoptions'];


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
            ->where(['b.status' => '1','b.is_shop_sale' => 1])
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
        $goods_info = GoodsModel::where(['id' => $id,'status' => '1','is_shop_sale'=>1])->find();
        if(!$goods_info){
            $this->error('该商品未上架商城，无法购买');
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
        $info = GoodsModel::where(['id' => $id,'status' => '1','is_shop_sale'=>1])
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
        $info['money'] = $this->formatShopDisplayMoney($info);
        $this->success('成功',$info);
    }
    public function singleInfo(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = GoodsModel::where(['id' => $id,'status' => '1','is_customized'=>1,'custom_status'=>1])
            ->with(['stock'])
            ->field('id,ag,images,customized_price,line_money,name,sales,see,content,is_stock,image,product_area,bean_seed,special_flavour,processing_method,moisture_content,density,specs,baking')
            ->find();
        if(!$info){
            $this->error('商品不存在');
        }
        if($info['ag'] >= 50 && $info['ag'] < 60){
            $info['baking'] = '深度烘焙';
        }elseif($info['ag'] >= 60 && $info['ag'] < 70){
            $info['baking'] = '中深烘焙';
        }elseif($info['ag'] >= 70 && $info['ag'] < 80){
            $info['baking'] = '中度烘焙';
        }elseif($info['ag'] >= 80 && $info['ag'] < 90){
            $info['baking'] = '浅度烘焙';
        }elseif($info['ag'] >= 90 && $info['ag'] <= 100){
            $info['baking'] = '极浅烘焙';
        }
        $info['start_weight'] = getValues('start_weight');
        $info['add_weight'] = getValues('add_weight');

        $info['mul_start_weight'] = getValues('mul_start_weight');
        $info['mul_add_weight'] = getValues('mul_add_weight');
        $info['images'] = $info['images'] ? explode(',', $info['images']) : [$info['image']];

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
                $invalid_items = [];
                $can_order = true;
                foreach ($data_arr as $k1=>&$v1){
                    $goods = GoodsModel::where(['id' => $v1['id'],'status' => '1','is_customized'=>1,'custom_status'=>1])
                        ->field('image,name')
                        ->find();
                    if(!$goods){
                        $can_order = false;
                        $v1['image'] = isset($v1['image']) ? $v1['image'] : '';
                        $v1['name'] = isset($v1['name']) ? $v1['name'] : '已失效咖啡豆';
                        $v1['invalid_reason'] = '该咖啡豆暂不可用于定制拼配';
                        $invalid_items[] = $v1;
                        continue;
                    }
                    $v1['image'] = $goods['image'];
                    $v1['name'] = $goods['name'];
                    $stock = SkuPrice::where(['goods_id'=>$v1['id'],'status'=>'up'])->find();
                    if(!$stock){
                        $can_order = false;
                        $v1['invalid_reason'] = '该咖啡豆规格暂不可用';
                        $invalid_items[] = $v1;
                    }
                }
                $data_arr = array_values($data_arr);
                $list[$k]['data_arr'] = $data_arr;
                $list[$k]['invalid_items'] = $invalid_items;
                $list[$k]['can_order'] = $can_order;
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
        $invalid_items = [];
        $can_order = true;
        foreach ($data_arr as $k1=>&$v1){
            $goods = GoodsModel::where(['id' => $v1['id'],'status' => '1','is_customized'=>1,'custom_status'=>1])
                ->field('image,name,ag')
                ->find();
            if(!$goods){
                $can_order = false;
                $v1['image'] = isset($v1['image']) ? $v1['image'] : '';
                $v1['name'] = isset($v1['name']) ? $v1['name'] : '已失效咖啡豆';
                $v1['ag'] = isset($v1['ag']) ? $v1['ag'] : 0;
                $v1['stock'] = null;
                $v1['invalid_reason'] = '该咖啡豆暂不可用于定制拼配';
                $invalid_items[] = $v1;
                $total_weight += bcmul($v1['ratio']/100,1000,0);
                continue;
            }
            $v1['image'] = $goods['image'];
            $v1['name'] = $goods['name'];
            $v1['ag'] = $goods['ag'];
            $v1['stock'] = SkuPrice::where(['goods_id'=>$v1['id'],'status'=>'up'])->find();
            if(!$v1['stock']){
                $can_order = false;
                $v1['invalid_reason'] = '该咖啡豆规格暂不可用';
                $invalid_items[] = $v1;
            }
            $total_weight += bcmul($v1['ratio']/100,1000,0);
        }
        $info['total_weight'] = $total_weight;
        $info['data_arr'] = $data_arr;
        $info['invalid_items'] = $invalid_items;
        $info['can_order'] = $can_order;
        $info['start_weight'] = getValues('start_weight');
        $info['add_weight'] = getValues('add_weight');
        $info['mul_start_weight'] = getValues('mul_start_weight');
        $info['mul_add_weight'] = getValues('mul_add_weight');


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
        $new_ag = 0;
        foreach ($goods_list as $k=>$v){
            $total_ratio += $v['ratio'];
            $goods_info = GoodsModel::with(['stock'])->where(['id' => $v['goods_id'],'is_customized'=>1,'custom_status'=>1,'status'=>1])->field('id,name,image,customized_price,baking,ag')->find();
            if(!$goods_info){
                $this->error('商品不存在');
            }
            $new_ag += $goods_info['ag']*$v['ratio']/100;
           /* if($goods_info['baking']){
                $baking = $goods_info['baking'];
            }*/
            if($goods_info['ag'] >= 50 && $goods_info['ag'] < 60){
                $goods_info['baking'] = '深度烘焙';
            }elseif($goods_info['ag'] >= 60 && $goods_info['ag'] < 70){
                $goods_info['baking'] = '中深烘焙';
            }elseif($goods_info['ag'] >= 70 && $goods_info['ag'] < 80){
                $goods_info['baking'] = '中度烘焙';
            }elseif($goods_info['ag'] >= 80 && $goods_info['ag'] < 90){
                $goods_info['baking'] = '浅度烘焙';
            }elseif($goods_info['ag'] >= 90 && $goods_info['ag'] <= 100){
                $goods_info['baking'] = '极浅烘焙';
            }

            $total_money = $total_money + bcmul($goods_info['customized_price'],bcdiv($v['ratio'],100,2),2);
            $total_weight = $total_weight + bcmul(1000,bcdiv($v['ratio'],100,2),0);
            array_push($list,$goods_info);
        }
        if($data['type'] == 2 &&$total_ratio != 100){
            $this->error('商品比例错误');
        }
        if($new_ag >= 50 && $new_ag < 60){
            $baking = '深度烘焙';
        }elseif($new_ag >= 60 && $new_ag < 70){
            $baking = '中深烘焙';
        }elseif($new_ag >= 70 && $new_ag < 80){
            $baking = '中度烘焙';
        }elseif($new_ag >= 80 && $new_ag < 90){
            $baking = '浅度烘焙';
        }elseif($new_ag >= 90 && $new_ag <= 100){
            $baking = '极浅烘焙';
        }

        $this->success('成功',compact('list','total_money','total_ratio','baking','total_weight'));
    }

    public function quickBlendRecommend(){
        $scene = $this->request->param('scene');
        $flavor = $this->request->param('flavor');
        $budget = $this->request->param('budget');
        $baking = $this->request->param('baking');
        $totalWeight = $this->normalizeQuickBlendTotalWeight($this->request->param('total_weight', 1000));
        $refreshSeed = (int)$this->request->param('refresh_seed', 0);
        $refreshMode = $this->request->param('refresh_mode', '');
        $currentPlan = $this->normalizeQuickBlendCurrentPlan($this->request->param('current_plan', []));
        $currentPlans = $this->normalizeQuickBlendCurrentPlans($this->request->param('current_plans', []));
        $allowBaking = ['中浅烘焙','中度烘焙','中深烘焙','深度烘焙'];
        if(!in_array($baking, $allowBaking)){
            $this->error('请选择烘焙度');
        }
        $budgetRange = $this->quickBlendBudgetRange($budget, $this->request->param('budget_min'), $this->request->param('budget_max'));
        if(!$budgetRange){
            $this->error('请选择成本区间');
        }
        $budgetTemplateKey = $this->quickBlendBudgetTemplateKey($budgetRange);

        $template = $this->quickBlendTemplate($scene, $flavor, $budgetTemplateKey);
        $templates = $this->quickBlendTemplateFamily($scene, $flavor, $budgetTemplateKey, $baking);
        $candidates = $this->getQuickBlendCandidates($scene, $flavor);
        $discountRate = $this->getQuickBlendWeightDiscountRate($totalWeight);
        if(count($candidates) < 2){
            $poolStats = $this->quickBlendCandidatePoolStats();
            $this->quickBlendFail('暂无足够可拼配豆种', [
                'reasons' => ['当前定制豆池中可推荐且有库存的咖啡豆少于 2 种。已启用定制豆 '.$poolStats['custom_total'].' 种，允许推荐 '.$poolStats['ai_allowed'].' 种，其中有可售库存 '.$poolStats['stocked'].' 种。'],
                'suggestions' => ['请在后台确认定制豆已启用、允许 AI 推荐，并且至少 2 支豆有上架规格和库存。']
            ]);
        }

        $plans = $this->buildQuickBlendPlansByTemplateFamily($candidates, $templates, $scene, $flavor, $budgetRange, $baking, $discountRate, $refreshSeed);
        if(count($plans) < 1){
            $fallbackTemplates = $this->quickBlendFallbackTemplateFamily($scene, $flavor, $baking);
            $plans = $this->buildQuickBlendPlansByTemplateFamily($candidates, $fallbackTemplates, $scene, $flavor, $budgetRange, $baking, $discountRate, $refreshSeed);
        }
        if(count($plans) < 1){
            $plans = $this->buildQuickBlendEmergencyPlans($candidates, $scene, $flavor, $budgetRange, $baking, $discountRate);
        }
        if(count($plans) < 1){
            $plans = $this->buildQuickBlendRescuePlans($candidates, $scene, $flavor, $budgetRange, $baking, $discountRate);
        }
        if(count($plans) < 1){
            $this->quickBlendFail('暂无符合目标的推荐配方', $this->quickBlendFailureDiagnosis($candidates, $template, $budgetRange, $baking, $flavor, $discountRate));
        }
        $plans = $this->selectQuickBlendPlans($plans, $template, $flavor, $baking, $refreshSeed, $refreshMode, $currentPlan, $currentPlans);
        if(count($plans) < 1){
            $this->quickBlendFail('暂无符合目标的推荐配方', $this->quickBlendFailureDiagnosis($candidates, $template, $budgetRange, $baking, $flavor, $discountRate));
        }
        $list = $plans[0]['list'];
        $estimated_cost = $this->weightedBlendCost($list, $discountRate);
        if(!$this->blendCostInRange($list, $budgetRange, $discountRate) || !$this->greenBlendHardAllowed($list, $scene, $baking)){
            $this->quickBlendFail('暂无符合目标的推荐配方', $this->quickBlendFailureDiagnosis($candidates, $template, $budgetRange, $baking, $flavor, $discountRate));
        }

        $estimated_cost = sprintf('%.2f', $estimated_cost);
        $target_ag = sprintf('%.1f', $this->weightedBlendAg($list));
        $reference_ag = $target_ag;
        $target_baking = $baking;
        $plan_type = $template['name'];
        $plan_desc = $template['desc'];
        $weight_discount_rate = $discountRate;
        $weight_discount_text = $this->formatQuickBlendWeightDiscountText($discountRate);
        $total_weight = $totalWeight;
        $this->success('成功', compact('plans','list','estimated_cost','baking','target_ag','reference_ag','target_baking','plan_type','plan_desc','total_weight','weight_discount_rate','weight_discount_text'));
    }

    public function quickBlendBudgetOptions(){
        $scene = $this->request->param('scene');
        $flavor = $this->request->param('flavor');
        $baking = $this->request->param('baking');
        $totalWeight = $this->normalizeQuickBlendTotalWeight($this->request->param('total_weight', 1000));
        $allowBaking = ['中浅烘焙','中度烘焙','中深烘焙','深度烘焙'];
        if(!in_array($baking, $allowBaking)){
            $this->error('请选择烘焙度');
        }
        $budgetRange = $this->quickBlendBudgetRange($this->request->param('budget'), $this->request->param('budget_min'), $this->request->param('budget_max'));
        if(!$budgetRange){
            $this->error('请选择成本区间');
        }
        $budgetTemplateKey = $this->quickBlendBudgetTemplateKey($budgetRange);
        $candidates = $this->getQuickBlendCandidates($scene, $flavor);
        $discountRate = $this->getQuickBlendWeightDiscountRate($totalWeight);
        $available = $this->quickBlendBudgetLikelyAvailable($candidates, $budgetRange, $scene, $baking, $discountRate);
        $this->success('成功', [
            'available' => $available,
            'reason' => $available ? '' : '当前成本区间暂无匹配配方',
            'budget_min' => $budgetRange[0],
            'budget_max' => $budgetRange[1],
            'total_weight' => $totalWeight,
            'weight_discount_rate' => $discountRate,
            'weight_discount_text' => $this->formatQuickBlendWeightDiscountText($discountRate)
        ]);
    }

    private function quickBlendBudgetLikelyAvailable($candidates, $budgetRange, $scene, $baking, $discountRate = 1){
        if(count($candidates) < 2){
            return false;
        }
        $prices = [];
        $baseCount = 0;
        foreach($candidates as $candidate){
            $price = (float)(isset($candidate['customized_price']) ? $candidate['customized_price'] : 0) * $discountRate;
            if($price > 0){
                $prices[] = $price;
            }
            if($this->quickBlendCandidateCanServeRole($candidate, 'base')){
                $baseCount++;
            }
        }
        if(!$prices || $baseCount < 1){
            return false;
        }
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $min = $budgetRange[0];
        $max = $budgetRange[1];
        if($max > 0 && $minPrice > $max){
            return false;
        }
        return true;
    }

    private function quickBlendBudgetRange($budget, $min = null, $max = null){
        if($min !== null && $min !== ''){
            $minValue = max(0, (int)$min);
            $maxValue = ($max === null || $max === '' || (int)$max >= 300) ? 0 : (int)$max;
            if($maxValue > 0 && $maxValue <= $minValue){
                return false;
            }
            return [$minValue, $maxValue];
        }
        $budgetMap = [
            '80-100' => [80, 100],
            '100-150' => [100, 150],
            '150-200' => [150, 200],
            '200+' => [200, 0]
        ];
        return isset($budgetMap[$budget]) ? $budgetMap[$budget] : false;
    }

    private function quickBlendBudgetTemplateKey($budgetRange){
        $min = $budgetRange[0];
        $max = $budgetRange[1];
        $mid = $max > 0 ? (($min + $max) / 2) : max(200, $min);
        if($mid < 100){
            return '80-100';
        }
        if($mid < 150){
            return '100-150';
        }
        if($mid < 200){
            return '150-200';
        }
        return '200+';
    }

    private function quickBlendTemplate($scene, $flavor, $budget){
        if($scene == 'milk' && $flavor == 'nut_cocoa' && in_array($budget, ['80-100','100-150'])){
            return [
                'name' => '基础稳定型',
                'desc' => '优先保证奶咖稳定、甜感和成本清晰。',
                'roles' => ['base','sweet'],
                'ratios' => [70,30]
            ];
        }
        if($scene == 'both' && $flavor == 'enhanced' && in_array($budget, ['150-200','200+'])){
            return [
                'name' => '复杂旗舰型',
                'desc' => '兼顾奶咖厚度、黑咖层次和明确记忆点。',
                'roles' => ['base','sweet','aroma','balance','accent'],
                'ratios' => [40,25,15,10,10]
            ];
        }
        if($scene == 'both' || in_array($flavor, ['enhanced', 'floral_clean'])){
            return [
                'name' => '层次增强型',
                'desc' => '在稳定基底上增加香气或增味表达。',
                'roles' => $flavor == 'enhanced' ? ['base','sweet','aroma','accent'] : ['base','sweet','aroma','balance'],
                'ratios' => [45,25,20,10]
            ];
        }
        if($scene == 'black'){
            return [
                'name' => '标准专业型',
                'desc' => '强调黑咖平衡、干净度和风味层次。',
                'roles' => ['base','aroma','balance'],
                'ratios' => [50,30,20]
            ];
        }
        return [
            'name' => '标准专业型',
            'desc' => '兼顾稳定性、甜感和出杯表现。',
            'roles' => ['base','sweet','balance'],
            'ratios' => [55,30,15]
        ];
    }

    private function quickBlendTemplateFamily($scene, $flavor, $budget, $baking = ''){
        $templates = [$this->quickBlendTemplate($scene, $flavor, $budget)];
        if($baking == '深度烘焙'){
            $templates[] = [
                'name' => '深烘低酸型',
                'desc' => '提高基底层比例，优先保证低酸、厚度和传统咖啡味。',
                'roles' => ['base','base','sweet'],
                'ratios' => [45,20,35]
            ];
            $templates[] = [
                'name' => '深烘稳态型',
                'desc' => '以高基底结构保证深烘稳定性，保留少量平衡位。',
                'roles' => ['base','base','balance'],
                'ratios' => [45,20,35]
            ];
        }
        if($scene == 'milk'){
            $templates[] = [
                'name' => '奶咖厚甜型',
                'desc' => '提高基底和甜感比例，优先保证牛奶穿透力与圆润口感。',
                'roles' => ['base','base','sweet'],
                'ratios' => [40,20,40]
            ];
            $templates[] = [
                'name' => '奶咖平衡型',
                'desc' => '在厚度基础上保留少量平衡位，降低单一甜厚感。',
                'roles' => ['base','sweet','balance'],
                'ratios' => [55,30,15]
            ];
            if($flavor == 'enhanced'){
                $templates[] = [
                    'name' => '奶咖记忆点型',
                    'desc' => '以稳定基底承托少量特殊处理风味，增加识别度。',
                    'roles' => ['base','sweet','balance','accent'],
                    'ratios' => [50,25,15,10]
                ];
            }
        }elseif($scene == 'black'){
            $templates[] = [
                'name' => '黑咖甜感型',
                'desc' => '在黑咖平衡里增加甜感支撑，让入口更顺口。',
                'roles' => ['base','sweet','aroma'],
                'ratios' => [45,25,30]
            ];
            $templates[] = [
                'name' => '黑咖双基底型',
                'desc' => '用两支基底型豆共同提供稳定结构，再补充香气和平衡。',
                'roles' => ['base','base','aroma','balance'],
                'ratios' => [30,20,30,20]
            ];
            if(in_array($flavor, ['nut_cocoa','floral_clean'])){
                $templates[] = [
                    'name' => '黑咖顺滑型',
                    'desc' => '减少香气波动，强调干净、顺滑和持续性。',
                    'roles' => ['base','sweet','balance'],
                    'ratios' => [50,25,25]
                ];
            }
        }else{
            $templates[] = [
                'name' => '通用双基底型',
                'desc' => '两支基底型豆共同承担主体结构，兼顾奶咖与黑咖稳定性。',
                'roles' => ['base','base','sweet','balance'],
                'ratios' => [30,20,30,20]
            ];
            $templates[] = [
                'name' => '通用香气型',
                'desc' => '保留足够基底结构，同时增加香气层次。',
                'roles' => ['base','sweet','aroma','balance'],
                'ratios' => [45,25,20,10]
            ];
            if($flavor == 'enhanced'){
                $templates[] = [
                    'name' => '通用记忆点型',
                    'desc' => '在稳定结构中加入小比例增味豆，提升差异化表达。',
                    'roles' => ['base','base','sweet','aroma','accent'],
                    'ratios' => [25,20,25,20,10]
                ];
            }
        }
        if($flavor == 'floral_fruit'){
            $templates[] = [
                'name' => '果香平衡型',
                'desc' => '以基底和甜感托住果香，避免酸感孤立。',
                'roles' => ['base','sweet','aroma','balance'],
                'ratios' => [40,25,25,10]
            ];
        }
        return $this->uniqueQuickBlendTemplates($templates);
    }

    private function quickBlendFallbackTemplateFamily($scene, $flavor, $baking){
        $minBase = $this->quickBlendBaseStructureMinRatio($scene, $baking);
        $baseRatio = max(60, $minBase);
        $sweetRatio = 100 - $baseRatio;
        $templates = [
            [
                'name' => '稳妥双豆型',
                'desc' => '优先保证基底结构、成本和生拼稳定性，减少复杂角色约束。',
                'roles' => ['base','sweet'],
                'ratios' => [$baseRatio,$sweetRatio]
            ],
            [
                'name' => '稳妥双基底型',
                'desc' => '用两支基底型豆共同构成主体，降低单一豆种波动。',
                'roles' => ['base','base','sweet'],
                'ratios' => [40,20,40]
            ],
            [
                'name' => '稳妥平衡型',
                'desc' => '在高基底结构中加入平衡位，保证大多数场景可用。',
                'roles' => ['base','sweet','balance'],
                'ratios' => [$baseRatio,25,75 - $baseRatio]
            ]
        ];
        if($baking == '深度烘焙'){
            $templates[] = [
                'name' => '深烘兜底型',
                'desc' => '深烘低酸场景下优先保证 60% 以上基底层。',
                'roles' => ['base','base'],
                'ratios' => [60,40]
            ];
        }
        return $this->uniqueQuickBlendTemplates($templates);
    }

    private function uniqueQuickBlendTemplates($templates){
        $result = [];
        $seen = [];
        foreach($templates as $template){
            $key = implode(',', $template['roles']).'|'.implode(',', $template['ratios']);
            if(isset($seen[$key])){
                continue;
            }
            $seen[$key] = true;
            $result[] = $template;
        }
        return $result;
    }

    private function quickBlendFail($message, $diagnosis){
        $reasons = isset($diagnosis['reasons']) && is_array($diagnosis['reasons']) ? array_values(array_filter($diagnosis['reasons'])) : [];
        $suggestions = isset($diagnosis['suggestions']) && is_array($diagnosis['suggestions']) ? array_values(array_filter($diagnosis['suggestions'])) : [];
        if(!$reasons){
            $reasons[] = '当前选择暂时没有找到同时满足成本、目标烘焙适配、风味结构和生拼共烘稳定性的组合。';
        }
        if(!$suggestions){
            $suggestions[] = '建议先调整相邻成本区间或相邻烘焙偏好，再重新生成推荐。';
            $suggestions[] = '也可以切换风味方向或使用场景，系统会重新选择不同结构的配方。';
        }
        $this->error($message, [
            'diagnosis' => [
                'title' => $message,
                'reasons' => $reasons,
                'suggestions' => $suggestions
            ]
        ]);
    }

    private function quickBlendFailureDiagnosis($candidates, $template, $budgetRange, $baking, $flavor, $discountRate = 1){
        $reasons = [];
        $suggestions = [];
        $prices = [];
        foreach($candidates as $candidate){
            $prices[] = (float)$candidate['customized_price'] * $discountRate;
        }
        if($prices){
            $minPrice = min($prices);
            $maxPrice = max($prices);
            if($budgetRange[1] > 0 && $minPrice > $budgetRange[1]){
                $reasons[] = '当前可用豆子按定制数量折扣后的最低定制价约为 ¥'.sprintf('%.0f', $minPrice).'/kg，高于你选择的成本上限。';
                $suggestions[] = '建议提高一档成本区间，或增加定制数量后重新生成。';
            }else{
                $suggestions[] = '可以尝试放宽成本区间，例如向上或向下调整一档。';
            }
        }
        foreach($template['roles'] as $index => $role){
            $ratio = $template['ratios'][$index];
            $count = $this->quickBlendRoleCandidateCount($candidates, $role, $ratio, $flavor);
            if($count < 1){
                $label = $this->formulaRoleLabel($role);
                $reasons[] = '当前可用于配方的咖啡豆中，暂时缺少“'.$label.'”定位的豆子。';
                $suggestions[] = '建议切换风味方向或使用场景，系统会重新选择不同结构的配方。';
            }
        }
        if(!$reasons){
            $reasons[] = '单独看成本、目标烘焙适配和配方定位都有可用豆，但组合后无法同时满足生拼共烘和风味结构。';
            $suggestions[] = '建议优先放宽一个条件：成本区间、烘焙偏好、或风味方向。';
            $suggestions[] = '如果选择了风味增强，可以改成花果香或坚果黑巧方向，生拼共烘稳定性会更高。';
        }
        return [
            'reasons' => array_values(array_unique($reasons)),
            'suggestions' => array_values(array_unique($suggestions))
        ];
    }

    private function quickBlendRoleCandidateCount($candidates, $role, $ratio, $flavor){
        $count = 0;
        foreach($candidates as $candidate){
            if($candidate['formula_strong_process'] && $role != 'accent' && $ratio > 10){
                continue;
            }
            if(!$this->ratioHardAllowed($candidate, $role, $ratio)){
                continue;
            }
            if(!$this->candidateAllowedForTemplateRole($candidate, $role)){
                continue;
            }
            if($role == 'accent' && !$candidate['formula_strong_process'] && $flavor == 'enhanced'){
                continue;
            }
            if($this->roleMatchScore($candidate, $role) >= 20){
                $count++;
            }
        }
        return $count;
    }

    private function getQuickBlendCandidates($scene, $flavor){
        $goods = GoodsModel::where(['status' => '1','is_customized'=>1,'custom_status'=>1])
            ->field('id,name,ag,image,customized_price,category_id,sales,product_area,bean_seed,processing_method,special_flavour,moisture_content,density,specs,baking,custom_flavour_tags,blend_role,taste_acidity,taste_sweetness,taste_aroma,taste_body,taste_aftertaste,recommend_ratio,allow_ai_recommend')
            ->order('weigh desc,createtime desc')
            ->limit(120)
            ->select();
        $goodsIds = [];
        foreach($goods as $key){
            $goodsIds[] = $key['id'];
        }
        $stockRows = $goodsIds ? SkuPrice::where('goods_id', 'in', $goodsIds)
            ->where(['status'=>'up'])
            ->where('stock','>',0)
            ->order('goods_id asc,id asc')
            ->select() : [];
        $stockMap = [];
        foreach($stockRows as $stockRow){
            if(!isset($stockMap[$stockRow['goods_id']])){
                $stockMap[$stockRow['goods_id']] = $stockRow;
            }
        }
        $list = [];
        foreach($goods as $key){
            if(isset($key['allow_ai_recommend']) && (int)$key['allow_ai_recommend'] === 0){
                continue;
            }
            $stock = isset($stockMap[$key['id']]) ? $stockMap[$key['id']] : null;
            if(!$stock){
                continue;
            }
            $item = $key->toArray();
            $item['goods_id'] = $item['id'];
            $item['stock_id'] = $stock['id'];
            $item['baking'] = $this->agToBaking($item['ag']);
            $position = $this->parseFormulaPosition($item);
            $item['formula_primary_position'] = $position['primary'];
            $item['formula_secondary_positions'] = $position['secondary'];
            $item['formula_strength'] = $position['strength'];
            $item['formula_strong_process'] = $position['strong_process'];
            $item['formula_role_scores'] = $position['scores'];
            $item['formula_role_reason'] = $position['reason'];
            $item['formula_recommended_ratio'] = $position['recommended_ratio'];
            $item['formula_avoid_roles'] = $position['avoid_roles'];
            $item['bean_role_label'] = $this->formulaRoleLabel($item['formula_primary_position']);
            $item['score'] = $this->quickBlendScore($item, $scene, $flavor);
            $list[] = $item;
        }
        return $list;
    }

    private function quickBlendCandidatePoolStats(){
        $total = GoodsModel::where(['status' => '1','is_customized'=>1,'custom_status'=>1])->count();
        $aiAllowed = GoodsModel::where(['status' => '1','is_customized'=>1,'custom_status'=>1])
            ->where(function ($query) {
                $query->where('allow_ai_recommend', '<>', 0)->whereOr('allow_ai_recommend', null);
            })
            ->count();
        $goodsIds = GoodsModel::where(['status' => '1','is_customized'=>1,'custom_status'=>1])
            ->where(function ($query) {
                $query->where('allow_ai_recommend', '<>', 0)->whereOr('allow_ai_recommend', null);
            })
            ->column('id');
        $stocked = $goodsIds ? SkuPrice::where('goods_id', 'in', $goodsIds)
            ->where(['status'=>'up'])
            ->where('stock','>',0)
            ->group('goods_id')
            ->count() : 0;
        return [
            'custom_total' => (int)$total,
            'ai_allowed' => (int)$aiAllowed,
            'stocked' => (int)$stocked
        ];
    }

    private function buildQuickBlendByTemplate($candidates, $template, $scene, $flavor){
        $selected = [];
        $usedIds = [];
        foreach($template['roles'] as $index => $role){
            $ratio = $template['ratios'][$index];
            $best = $this->pickCandidateForRole($candidates, $role, $usedIds, $ratio, $scene, $flavor, $selected);
            if(!$best){
                continue;
            }
            $best['ratio'] = $ratio;
            $best['assigned_role'] = $role;
            $best['assigned_role_label'] = $this->formulaRoleLabel($role);
            $best['formula_role_label'] = $this->formulaRoleLabel($best['formula_primary_position']);
            $best['bean_role_label'] = $best['formula_role_label'];
            $selected[] = $best;
            $usedIds[] = $best['id'];
        }
        if(count($selected) < 2){
            foreach($candidates as $candidate){
                if(in_array($candidate['id'], $usedIds)){
                    continue;
                }
                $candidate['ratio'] = count($selected) == 0 ? 60 : 40;
                $candidate['formula_role_label'] = $this->formulaRoleLabel($candidate['formula_primary_position']);
                $candidate['bean_role_label'] = $candidate['formula_role_label'];
                $selected[] = $candidate;
                $usedIds[] = $candidate['id'];
                if(count($selected) >= 2){
                    break;
                }
            }
        }
        return $this->normalizeQuickBlendRatios($selected);
    }

    private function buildQuickBlendByBudget($candidates, $template, $scene, $flavor, $budgetRange, $baking, $discountRate = 1){
        $plans = $this->buildQuickBlendPlansByBudget($candidates, $template, $scene, $flavor, $budgetRange, $baking, $discountRate);
        return count($plans) ? $plans[0]['list'] : [];
    }

    private function buildQuickBlendPlansByTemplateFamily($candidates, $templates, $scene, $flavor, $budgetRange, $baking, $discountRate = 1, $refreshSeed = 0){
        $allPlans = [];
        $seen = [];
        foreach($templates as $templateIndex => $template){
            $plans = $this->buildQuickBlendPlansByBudget($candidates, $template, $scene, $flavor, $budgetRange, $baking, $discountRate, $refreshSeed);
            foreach($plans as $plan){
                $signature = $refreshSeed > 0 ? $this->quickBlendPlanBeanSignature($plan['list']) : $this->quickBlendPlanSignature($plan['list']);
                if(isset($seen[$signature])){
                    continue;
                }
                $seen[$signature] = true;
                $plan['template_name'] = $template['name'];
                $plan['template_desc'] = $template['desc'];
                $plan['template_index'] = $templateIndex;
                $plan['score'] -= $templateIndex * 1.5;
                $allPlans[] = $plan;
            }
            if(count($allPlans) >= 300){
                break;
            }
        }
        usort($allPlans, function ($a, $b) {
            if($a['score'] == $b['score']){
                if($a['template_index'] == $b['template_index']){
                    return 0;
                }
                return $a['template_index'] > $b['template_index'] ? 1 : -1;
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });
        return array_slice($allPlans, 0, 300);
    }

    private function buildQuickBlendEmergencyPlans($candidates, $scene, $flavor, $budgetRange, $baking, $discountRate = 1){
        $baseCandidates = [];
        $supportCandidates = [];
        foreach($candidates as $candidate){
            if(!empty($candidate['formula_strong_process'])){
                continue;
            }
            $score = $candidate['score'] + $this->roastSuitabilityScore($candidate, $baking, $scene, $flavor);
            if($this->quickBlendCandidateCanServeRole($candidate, 'base')){
                $candidate['_emergency_score'] = $score + 30;
                $baseCandidates[] = $candidate;
            }
            $candidate['_emergency_score'] = $score + ($this->quickBlendCandidateCanServeRole($candidate, 'base') ? -5 : 10);
            $supportCandidates[] = $candidate;
        }
        usort($baseCandidates, function ($a, $b) {
            return $a['_emergency_score'] == $b['_emergency_score'] ? 0 : ($a['_emergency_score'] > $b['_emergency_score'] ? -1 : 1);
        });
        usort($supportCandidates, function ($a, $b) {
            return $a['_emergency_score'] == $b['_emergency_score'] ? 0 : ($a['_emergency_score'] > $b['_emergency_score'] ? -1 : 1);
        });
        $baseCandidates = array_slice($baseCandidates, 0, 16);
        $supportCandidates = array_slice($supportCandidates, 0, 24);
        $plans = [];
        $seen = [];
        foreach($baseCandidates as $base){
            foreach($supportCandidates as $support){
                if($base['id'] == $support['id']){
                    continue;
                }
                $ratioA = $this->quickBlendBaseStructureMinRatio($scene, $baking);
                $ratioA = max(50, min(70, $ratioA));
                $trial = [
                    $this->quickBlendEmergencyItem($base, $ratioA, 'base'),
                    $this->quickBlendEmergencyItem($support, 100 - $ratioA, $this->quickBlendEmergencySupportRole($support))
                ];
                $this->appendEmergencyPlan($plans, $seen, $trial, $budgetRange, $baking, $scene, $discountRate);
                foreach($baseCandidates as $base2){
                    if($base2['id'] == $base['id'] || $base2['id'] == $support['id']){
                        continue;
                    }
                    $trial3 = [
                        $this->quickBlendEmergencyItem($base, 40, 'base'),
                        $this->quickBlendEmergencyItem($base2, 20, 'base'),
                        $this->quickBlendEmergencyItem($support, 40, $this->quickBlendEmergencySupportRole($support))
                    ];
                    $this->appendEmergencyPlan($plans, $seen, $trial3, $budgetRange, $baking, $scene, $discountRate);
                    if(count($plans) >= 80){
                        break 3;
                    }
                }
            }
        }
        usort($plans, function ($a, $b) {
            if($a['score'] == $b['score']){
                return 0;
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });
        return $plans;
    }

    private function quickBlendEmergencyItem($candidate, $ratio, $role){
        $candidate['ratio'] = $ratio;
        $candidate['assigned_role'] = $role;
        $candidate['assigned_role_label'] = $this->formulaRoleLabel($role);
        $candidate['formula_role_label'] = $this->formulaRoleLabel($candidate['formula_primary_position']);
        $candidate['bean_role_label'] = $candidate['formula_role_label'];
        unset($candidate['_emergency_score']);
        return $candidate;
    }

    private function quickBlendEmergencySupportRole($candidate){
        if($this->quickBlendCandidateCanServeRole($candidate, 'sweet')){
            return 'sweet';
        }
        if($this->quickBlendCandidateCanServeRole($candidate, 'balance')){
            return 'balance';
        }
        if($this->quickBlendCandidateCanServeRole($candidate, 'aroma')){
            return 'aroma';
        }
        return isset($candidate['formula_primary_position']) ? $candidate['formula_primary_position'] : 'sweet';
    }

    private function appendEmergencyPlan(&$plans, &$seen, $trial, $budgetRange, $baking, $scene, $discountRate = 1){
        $trial = $this->normalizeQuickBlendRatios($trial);
        if(!$this->blendCostInRange($trial, $budgetRange, $discountRate) || !$this->greenBlendHardAllowed($trial, $scene, $baking)){
            return;
        }
        $signature = $this->quickBlendPlanBeanSignature($trial);
        if(isset($seen[$signature])){
            return;
        }
        $seen[$signature] = true;
        $cost = $this->weightedBlendCost($trial, $discountRate);
        $ag = $this->weightedBlendAg($trial);
        $plans[] = [
            'score' => $this->quickBlendPlanScore($trial, $budgetRange, $baking, $cost, $ag, $scene),
            'cost' => $cost,
            'ag' => $ag,
            'flavor_score' => $this->quickBlendFlavorPlanScore($trial),
            'bean_signature' => $this->quickBlendPlanBeanSignature($trial),
            'template_name' => '稳妥兜底型',
            'template_desc' => '在当前条件较紧时，优先保证基底结构、成本和生拼稳定性。',
            'template_index' => 99,
            'list' => $trial
        ];
    }

    private function buildQuickBlendRescuePlans($candidates, $scene, $flavor, $budgetRange, $baking, $discountRate = 1){
        $pool = [];
        foreach($candidates as $candidate){
            if(!empty($candidate['formula_strong_process'])){
                continue;
            }
            $candidate['_rescue_score'] = $this->rescueBeanScore($candidate, $scene, $flavor, $baking);
            $pool[] = $candidate;
        }
        usort($pool, function ($a, $b) {
            if($a['_rescue_score'] == $b['_rescue_score']){
                return $a['customized_price'] > $b['customized_price'] ? 1 : -1;
            }
            return $a['_rescue_score'] > $b['_rescue_score'] ? -1 : 1;
        });
        $pool = array_slice($pool, 0, 14);
        $ratioSets = $this->rescueRatioSets($scene, $baking);
        $plans = [];
        $seen = [];
        foreach($pool as $first){
            foreach($pool as $second){
                if($first['id'] == $second['id']){
                    continue;
                }
                foreach($ratioSets as $ratios){
                    $trial = [
                        $this->quickBlendRescueItem($first, $ratios[0], 'base'),
                        $this->quickBlendRescueItem($second, 100 - $ratios[0], $this->quickBlendEmergencySupportRole($second))
                    ];
                    $this->appendRescuePlan($plans, $seen, $trial, $budgetRange, $baking, $scene, $discountRate);
                    if(count($plans) >= 60){
                        break 3;
                    }
                }
                foreach($pool as $third){
                    if($third['id'] == $first['id'] || $third['id'] == $second['id']){
                        continue;
                    }
                    foreach($ratioSets as $ratios){
                        if(count($ratios) < 3){
                            continue;
                        }
                        $trial = [
                            $this->quickBlendRescueItem($first, $ratios[0], 'base'),
                            $this->quickBlendRescueItem($second, $ratios[1], $this->quickBlendEmergencySupportRole($second)),
                            $this->quickBlendRescueItem($third, $ratios[2], $this->quickBlendEmergencySupportRole($third))
                        ];
                        $this->appendRescuePlan($plans, $seen, $trial, $budgetRange, $baking, $scene, $discountRate);
                        if(count($plans) >= 60){
                            break 4;
                        }
                    }
                }
            }
        }
        usort($plans, function ($a, $b) {
            if($a['score'] == $b['score']){
                return 0;
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });
        return $plans;
    }

    private function rescueBeanScore($candidate, $scene, $flavor, $baking){
        $text = $this->beanJudgeText($candidate);
        $score = isset($candidate['score']) ? $candidate['score'] : 0;
        $score += $this->roastSuitabilityScore($candidate, $baking, $scene, $flavor);
        $score += $this->keywordScore($text, ['巴西','哥伦比亚','危地马拉','洪都拉斯','云南','坚果','可可','巧克力','焦糖','蜂蜜','甜','平衡','顺滑','醇厚','低酸']);
        $score += $this->quickBlendCandidateCanServeRole($candidate, 'base') ? 30 : 0;
        $score += $this->quickBlendCandidateCanServeRole($candidate, 'sweet') ? 12 : 0;
        $score += $this->beanFermentationStrength($candidate) <= 3 ? 10 : -12;
        $score += $this->beanAromaStrength($candidate) >= 5 && $scene == 'milk' ? -10 : 0;
        $score += max(0, 220 - (float)$candidate['customized_price']) / 10;
        return $score;
    }

    private function rescueRatioSets($scene, $baking){
        $minBase = $this->quickBlendBaseStructureMinRatio($scene, $baking);
        $base = max($minBase, 55);
        $sets = [
            [$base, 100 - $base],
            [min(75, $base + 10), 100 - min(75, $base + 10)],
            [max($minBase, 60), 25, 15],
            [max($minBase, 55), 30, 15],
            [max($minBase, 50), 35, 15]
        ];
        $result = [];
        foreach($sets as $set){
            if(array_sum($set) != 100){
                continue;
            }
            $valid = true;
            foreach($set as $ratio){
                if($ratio < 10){
                    $valid = false;
                    break;
                }
            }
            if($valid){
                $result[] = $set;
            }
        }
        return $result;
    }

    private function quickBlendRescueItem($candidate, $ratio, $role){
        $candidate['ratio'] = $ratio;
        $candidate['assigned_role'] = $role;
        $candidate['assigned_role_label'] = $this->formulaRoleLabel($role);
        $candidate['formula_role_label'] = $this->formulaRoleLabel(isset($candidate['formula_primary_position']) ? $candidate['formula_primary_position'] : $role);
        $candidate['bean_role_label'] = $candidate['formula_role_label'];
        unset($candidate['_rescue_score']);
        return $candidate;
    }

    private function appendRescuePlan(&$plans, &$seen, $trial, $budgetRange, $baking, $scene, $discountRate = 1){
        $trial = $this->normalizeQuickBlendRatios($trial);
        if(!$this->blendCostInRange($trial, $budgetRange, $discountRate) || !$this->greenBlendHardAllowed($trial, $scene, $baking)){
            return;
        }
        $signature = $this->quickBlendPlanBeanSignature($trial);
        if(isset($seen[$signature])){
            return;
        }
        $seen[$signature] = true;
        $cost = $this->weightedBlendCost($trial, $discountRate);
        $ag = $this->weightedBlendAg($trial);
        $plans[] = [
            'score' => $this->quickBlendPlanScore($trial, $budgetRange, $baking, $cost, $ag, $scene) - 12,
            'cost' => $cost,
            'ag' => $ag,
            'flavor_score' => $this->quickBlendFlavorPlanScore($trial),
            'bean_signature' => $this->quickBlendPlanBeanSignature($trial),
            'template_name' => '保守稳配型',
            'template_desc' => '在当前条件难以命中模板时，优先使用稳定豆、甜感豆和低风险比例生成保守方案。',
            'template_index' => 120,
            'list' => $trial
        ];
    }

    private function buildQuickBlendPlansByBudget($candidates, $template, $scene, $flavor, $budgetRange, $baking, $discountRate = 1, $refreshSeed = 0){
        $roleBuckets = [];
        foreach($template['roles'] as $index => $role){
            $ratio = $template['ratios'][$index];
            $bucket = [];
            foreach($candidates as $candidate){
                if($candidate['formula_strong_process'] && $role != 'accent' && $ratio > 10){
                    continue;
                }
                if(!$this->quickBlendCandidateCanServeRole($candidate, $role)){
                    continue;
                }
                $score = $this->roleMatchScore($candidate, $role) + $candidate['score'];
                $score += $this->roastSuitabilityScore($candidate, $baking, $scene, $flavor);
                if(!$this->ratioHardAllowed($candidate, $role, $ratio)){
                    continue;
                }
                if($role == 'accent' && !$candidate['formula_strong_process'] && $flavor == 'enhanced'){
                    $score -= 10;
                }
                $candidate['_template_role'] = $role;
                $candidate['_template_ratio'] = $ratio;
                $candidate['_template_score'] = $score;
                $bucket[] = $candidate;
            }
            usort($bucket, function ($a, $b) {
                if($a['_template_score'] == $b['_template_score']){
                    if($a['customized_price'] == $b['customized_price']){
                        return 0;
                    }
                    return $a['customized_price'] > $b['customized_price'] ? 1 : -1;
                }
                return $a['_template_score'] > $b['_template_score'] ? -1 : 1;
            });
            $roleBuckets[] = $this->quickBlendRoleBucketCandidates($bucket, $baking, $refreshSeed, $index);
        }
        $preferBeanDiversity = $refreshSeed > 0;
        $best = [
            'score' => -999999,
            'list' => [],
            'plans' => [],
            'checked' => 0,
            'limit' => 1800,
            'plan_limit' => 140,
            'min_checked_before_stop' => 260,
            'ratio_variant_cache' => [],
            'scene' => $scene,
            'baking' => $baking,
            'unique_bean_signatures' => $preferBeanDiversity,
            'seen_bean_signatures' => []
        ];
        $this->searchQuickBlendCombinations($roleBuckets, 0, [], [], $budgetRange, $baking, $best, $discountRate);
        usort($best['plans'], function ($a, $b) {
            if($a['score'] == $b['score']){
                return 0;
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });
        return $best['plans'];
    }

    private function searchQuickBlendCombinations($roleBuckets, $index, $selected, $usedIds, $budgetRange, $baking, &$best, $discountRate = 1){
        if($best['checked'] >= $best['min_checked_before_stop'] && count($best['plans']) >= $best['plan_limit']){
            return;
        }
        if($index >= count($roleBuckets)){
            if($best['checked'] >= $best['limit']){
                return;
            }
            $best['checked']++;
            if(count($selected) < 2){
                return;
            }
            $list = [];
            foreach($selected as $item){
                $item['ratio'] = $item['_template_ratio'];
                $item['assigned_role'] = $item['_template_role'];
                $item['assigned_role_label'] = $this->formulaRoleLabel($item['_template_role']);
                $item['formula_role_label'] = $this->formulaRoleLabel($item['formula_primary_position']);
                $item['bean_role_label'] = $item['formula_role_label'];
                unset($item['_template_role'], $item['_template_ratio'], $item['_template_score']);
                $list[] = $item;
            }
            $list = $this->normalizeQuickBlendRatios($list);
            $variants = $this->quickBlendRatioVariantsCached($selected, $best);
            foreach($variants as $ratios){
                if($best['checked'] >= $best['min_checked_before_stop'] && count($best['plans']) >= $best['plan_limit']){
                    return;
                }
                $trial = [];
                foreach($list as $idx => $item){
                    $item['ratio'] = $ratios[$idx];
                    $trial[] = $item;
                }
                $trial = $this->normalizeQuickBlendRatios($trial);
                $trialCost = $this->weightedBlendCost($trial, $discountRate);
                $trialAg = $this->weightedBlendAg($trial);
                $scene = isset($best['scene']) ? $best['scene'] : '';
                $targetBaking = isset($best['baking']) ? $best['baking'] : $baking;
                if(!$this->costInRangeValue($trialCost, $budgetRange) || !$this->greenBlendHardAllowed($trial, $scene, $targetBaking)){
                    continue;
                }
                $score = $this->quickBlendPlanScore($trial, $budgetRange, $targetBaking, $trialCost, $trialAg, $scene);
                $beanSignature = $this->quickBlendPlanBeanSignature($trial);
                if(!empty($best['unique_bean_signatures'])){
                    if(isset($best['seen_bean_signatures'][$beanSignature])){
                        continue;
                    }
                    $best['seen_bean_signatures'][$beanSignature] = true;
                }
                $plan = [
                    'score' => $score,
                    'cost' => $trialCost,
                    'ag' => $trialAg,
                    'flavor_score' => $this->quickBlendFlavorPlanScore($trial),
                    'bean_signature' => $beanSignature,
                    'list' => $trial
                ];
                if(count($best['plans']) < $best['plan_limit']){
                    $best['plans'][] = $plan;
                }
                if($score > $best['score']){
                    $best['score'] = $score;
                    $best['list'] = $trial;
                }
            }
            return;
        }
        foreach($roleBuckets[$index] as $candidate){
            if($best['checked'] >= $best['limit'] || ($best['checked'] >= $best['min_checked_before_stop'] && count($best['plans']) >= $best['plan_limit'])){
                return;
            }
            if(in_array($candidate['id'], $usedIds)){
                continue;
            }
            if($candidate['formula_strong_process'] && $this->selectedStrongProcessCount($selected) >= 1 && $candidate['_template_ratio'] > 10){
                continue;
            }
            $nextSelected = $selected;
            $nextSelected[] = $candidate;
            $nextUsedIds = $usedIds;
            $nextUsedIds[] = $candidate['id'];
            $this->searchQuickBlendCombinations($roleBuckets, $index + 1, $nextSelected, $nextUsedIds, $budgetRange, $baking, $best, $discountRate);
        }
    }

    private function quickBlendPlanScore($list, $budgetRange, $baking, $cost = null, $ag = null, $scene = ''){
        $score = 0;
        foreach($list as $item){
            $score += isset($item['score']) ? $item['score'] : 0;
        }
        if($cost === null){
            $cost = $this->weightedBlendCost($list);
        }
        $min = $budgetRange[0];
        $max = $budgetRange[1];
        if($min > 0 && $max > 0){
            $mid = ($min + $max) / 2;
            $score -= abs($cost - $mid) * 2;
        }elseif($min > 0){
            $score -= abs($cost - $min) * 0.5;
        }
        if($ag === null){
            $ag = $this->weightedBlendAg($list);
        }
        $score += $this->greenBlendCompatibilityScore($list, $scene, $baking);
        foreach($list as $item){
            $score += $this->roastSuitabilityScore($item, $baking, '', '') * ($item['ratio'] / 100);
        }
        return $score;
    }

    private function quickBlendFlavorPlanScore($list){
        $score = 0;
        foreach($list as $item){
            $role = isset($item['formula_role_label']) ? $this->roleByLabel($item['formula_role_label']) : '';
            if($role == 'aroma' || $role == 'accent'){
                $score += $item['ratio'] * 2;
            }
            if(!empty($item['formula_strong_process'])){
                $score += $item['ratio'] * 3;
            }
            $score += isset($item['score']) ? $item['score'] : 0;
        }
        return $score;
    }

    private function selectQuickBlendPlans($plans, $template, $flavor, $baking, $refreshSeed = 0, $refreshMode = '', $currentPlan = [], $currentPlans = []){
        $selected = [];
        $used = [];
        $preferBeanChange = $refreshSeed > 0 && !empty($currentPlan);
        $balanced = $this->pickPlanByMode($plans, 'balanced', $used, [], $this->seedForMode($refreshSeed, $refreshMode, 'balanced'), $currentPlan, $preferBeanChange, $currentPlans);
        if($balanced){
            $selected[] = $this->formatQuickBlendPlan($balanced, '平衡推荐', $this->quickBlendPlanTemplateDesc($balanced, $template), $baking);
            $used[] = $this->quickBlendPlanSignature($balanced['list']);
        }
        $flavorPlan = $this->pickPlanByMode($plans, 'flavor', $used, $selected, $this->seedForMode($refreshSeed, $refreshMode, 'flavor'), $currentPlan, $preferBeanChange, $currentPlans);
        if($flavorPlan){
            $selected[] = $this->formatQuickBlendPlan($flavorPlan, '风味更突出', $this->flavorPlanDesc($flavor), $baking);
            $used[] = $this->quickBlendPlanSignature($flavorPlan['list']);
        }
        $costPlan = $this->pickPlanByMode($plans, 'cost', $used, $selected, $this->seedForMode($refreshSeed, $refreshMode, 'cost'), $currentPlan, $preferBeanChange, $currentPlans);
        if($costPlan){
            $balancedCost = $balanced ? (float)$balanced['cost'] : 0;
            if(!$balanced || $balancedCost - (float)$costPlan['cost'] >= 5){
                $selected[] = $this->formatQuickBlendPlan($costPlan, '成本更友好', '在满足目标风味、目标烘焙适配和生拼共烘稳定性的前提下，成本至少比平衡方案低 ¥5/kg。', $baking);
                $used[] = $this->quickBlendPlanSignature($costPlan['list']);
            }
        }
        $alternatePlans = [];
        foreach($plans as $plan){
            if(count($selected) >= 3){
                break;
            }
            $signature = $this->quickBlendPlanSignature($plan['list']);
            if(in_array($signature, $used)){
                continue;
            }
            if($preferBeanChange && $this->quickBlendMatchesAnyCurrentPlan($plan['list'], $currentPlans)){
                continue;
            }
            if(!$this->planDiverseEnough($plan, $selected)){
                continue;
            }
            $alternatePlans[] = $plan;
            if($refreshMode != 'alternate'){
                break;
            }
        }
        if($alternatePlans && count($selected) < 3){
            $alternateIndex = 0;
            if($refreshMode == 'alternate' && $refreshSeed > 0 && count($alternatePlans) > 1){
                $alternateIndex = abs((int)$refreshSeed) % min(6, count($alternatePlans));
            }
            $alternatePlan = $alternatePlans[$alternateIndex];
            $selected[] = $this->formatQuickBlendPlan($alternatePlan, '备选方案', $this->quickBlendPlanTemplateDesc($alternatePlan, $template), $baking);
        }
        if(!$selected && !empty($plans)){
            $fallbackIndex = $refreshSeed > 0 ? abs((int)$refreshSeed) % min(6, count($plans)) : 0;
            $selected[] = $this->formatQuickBlendPlan($plans[$fallbackIndex], '平衡推荐', $this->quickBlendPlanTemplateDesc($plans[$fallbackIndex], $template), $baking);
        }
        return $selected;
    }

    private function quickBlendPlanTemplateDesc($plan, $fallbackTemplate){
        return isset($plan['template_desc']) && $plan['template_desc'] ? $plan['template_desc'] : $fallbackTemplate['desc'];
    }

    private function seedForMode($refreshSeed, $refreshMode, $mode){
        if($refreshSeed > 0 && $refreshMode == $mode){
            return $refreshSeed;
        }
        return 0;
    }

    private function pickPlanByMode($plans, $mode, $used, $selected = [], $refreshSeed = 0, $currentPlan = [], $preferBeanChange = false, $currentPlans = []){
        $candidates = [];
        $fallbackCandidates = [];
        $shownFallbackCandidates = [];
        foreach($plans as $plan){
            if(in_array($this->quickBlendPlanSignature($plan['list']), $used)){
                continue;
            }
            if(!$this->planDiverseEnough($plan, $selected)){
                continue;
            }
            if($mode == 'cost'){
                $score = -1 * $plan['cost'];
            }elseif($mode == 'flavor'){
                $score = $plan['flavor_score'];
            }else{
                $score = $plan['score'];
            }
            $item = [
                'score' => $score,
                'change_score' => $this->quickBlendPlanChangeScore($plan['list'], $currentPlan),
                'change_tier' => $this->quickBlendPlanChangeTier($plan['list'], $currentPlan),
                'plan' => $plan
            ];
            if($preferBeanChange && !$this->quickBlendHasBeanChange($plan['list'], $currentPlan)){
                $fallbackCandidates[] = $item;
            }elseif($preferBeanChange && $this->quickBlendMatchesAnyCurrentPlan($plan['list'], $currentPlans)){
                $shownFallbackCandidates[] = $item;
            }else{
                $candidates[] = $item;
            }
        }
        if(!$candidates && $preferBeanChange){
            $candidates = $shownFallbackCandidates ? $shownFallbackCandidates : $fallbackCandidates;
        }
        if(!$candidates){
            return null;
        }
        usort($candidates, function ($a, $b) use ($preferBeanChange) {
            if($preferBeanChange){
                if($a['change_tier'] != $b['change_tier']){
                    return $a['change_tier'] > $b['change_tier'] ? -1 : 1;
                }
                if($a['change_score'] != $b['change_score']){
                    return $a['change_score'] > $b['change_score'] ? -1 : 1;
                }
            }
            if($a['score'] == $b['score']){
                return 0;
            }
            return $a['score'] > $b['score'] ? -1 : 1;
        });
        $poolSize = min($preferBeanChange ? 18 : 6, count($candidates));
        $offset = 0;
        if($refreshSeed > 0 && $poolSize > 1){
            $offset = abs((int)$refreshSeed + crc32($mode)) % $poolSize;
        }
        return $candidates[$offset]['plan'];
    }

    private function planDiverseEnough($plan, $selected){
        if(!$selected){
            return true;
        }
        foreach($selected as $selectedPlan){
            $selectedList = isset($selectedPlan['list']) ? $selectedPlan['list'] : [];
            if(!$this->planPairDiverseEnough($plan['list'], $selectedList)){
                return false;
            }
        }
        return true;
    }

    private function planPairDiverseEnough($listA, $listB){
        $mapA = [];
        $mapB = [];
        foreach($listA as $item){
            $mapA[$item['id']] = isset($item['ratio']) ? (int)$item['ratio'] : 0;
        }
        foreach($listB as $item){
            $mapB[$item['id']] = isset($item['ratio']) ? (int)$item['ratio'] : 0;
        }
        $changedBeans = 0;
        foreach($mapA as $id => $ratio){
            if(!isset($mapB[$id])){
                $changedBeans++;
            }
        }
        foreach($mapB as $id => $ratio){
            if(!isset($mapA[$id])){
                $changedBeans++;
            }
        }
        if($changedBeans >= 2){
            return true;
        }
        $ratioDistance = 0;
        $allIds = array_unique(array_merge(array_keys($mapA), array_keys($mapB)));
        foreach($allIds as $id){
            $ratioDistance += abs((isset($mapA[$id]) ? $mapA[$id] : 0) - (isset($mapB[$id]) ? $mapB[$id] : 0));
        }
        return $ratioDistance >= 30;
    }

    private function normalizeQuickBlendCurrentPlan($plan){
        if(is_string($plan)){
            $decoded = json_decode($plan, true);
            $plan = is_array($decoded) ? $decoded : [];
        }
        if(!is_array($plan)){
            return [];
        }
        $list = [];
        foreach($plan as $item){
            if(!is_array($item)){
                continue;
            }
            $id = isset($item['id']) ? (int)$item['id'] : (isset($item['goods_id']) ? (int)$item['goods_id'] : 0);
            if($id <= 0){
                continue;
            }
            $list[] = [
                'id' => $id,
                'ratio' => isset($item['ratio']) ? (int)$item['ratio'] : 0
            ];
        }
        return $list;
    }

    private function normalizeQuickBlendCurrentPlans($plans){
        if(is_string($plans)){
            $decoded = json_decode($plans, true);
            $plans = is_array($decoded) ? $decoded : [];
        }
        if(!is_array($plans)){
            return [];
        }
        $result = [];
        foreach($plans as $plan){
            $normalized = $this->normalizeQuickBlendCurrentPlan($plan);
            if($normalized){
                $result[] = $normalized;
            }
        }
        return $result;
    }

    private function quickBlendMatchesAnyCurrentPlan($list, $currentPlans){
        foreach($currentPlans as $currentPlan){
            if(!$this->quickBlendHasBeanChange($list, $currentPlan)){
                return true;
            }
        }
        return false;
    }

    private function quickBlendHasBeanChange($list, $currentPlan){
        if(empty($currentPlan)){
            return true;
        }
        $currentIds = [];
        foreach($currentPlan as $item){
            if(isset($item['id'])){
                $currentIds[] = (int)$item['id'];
            }
        }
        $nextIds = [];
        foreach($list as $item){
            if(isset($item['id'])){
                $nextIds[] = (int)$item['id'];
            }
        }
        sort($currentIds);
        sort($nextIds);
        return $currentIds !== $nextIds;
    }

    private function quickBlendPlanChangeTier($list, $currentPlan){
        $metrics = $this->quickBlendPlanChangeMetrics($list, $currentPlan);
        if($metrics['changed_beans'] >= 4){
            return 4;
        }
        if($metrics['changed_beans'] >= 2){
            return 3;
        }
        if($metrics['changed_beans'] >= 1){
            return 2;
        }
        if($metrics['ratio_distance'] >= 50){
            return 1;
        }
        return 0;
    }

    private function quickBlendPlanChangeScore($list, $currentPlan){
        $metrics = $this->quickBlendPlanChangeMetrics($list, $currentPlan);
        return $metrics['changed_beans'] * 35 + $metrics['ratio_distance'];
    }

    private function quickBlendPlanChangeMetrics($list, $currentPlan){
        if(empty($currentPlan)){
            return ['changed_beans' => 0, 'ratio_distance' => 0];
        }
        $mapA = [];
        $mapB = [];
        foreach($list as $item){
            if(isset($item['id'])){
                $mapA[(int)$item['id']] = isset($item['ratio']) ? (int)$item['ratio'] : 0;
            }
        }
        foreach($currentPlan as $item){
            if(isset($item['id'])){
                $mapB[(int)$item['id']] = isset($item['ratio']) ? (int)$item['ratio'] : 0;
            }
        }
        $changedBeans = 0;
        foreach($mapA as $id => $ratio){
            if(!isset($mapB[$id])){
                $changedBeans++;
            }
        }
        foreach($mapB as $id => $ratio){
            if(!isset($mapA[$id])){
                $changedBeans++;
            }
        }
        $ratioDistance = 0;
        $allIds = array_unique(array_merge(array_keys($mapA), array_keys($mapB)));
        foreach($allIds as $id){
            $ratioDistance += abs((isset($mapA[$id]) ? $mapA[$id] : 0) - (isset($mapB[$id]) ? $mapB[$id] : 0));
        }
        return ['changed_beans' => $changedBeans, 'ratio_distance' => $ratioDistance];
    }

    private function quickBlendRoleBucketCandidates($bucket, $baking, $refreshSeed = 0, $slotIndex = 0){
        $map = [];
        $result = [];
        foreach(array_slice($bucket, 0, 8) as $item){
            $key = $item['id'];
            if(!isset($map[$key])){
                $map[$key] = true;
                $result[] = $item;
            }
        }
        $cheap = $bucket;
        usort($cheap, function ($a, $b) {
            if($a['customized_price'] == $b['customized_price']){
                return 0;
            }
            return $a['customized_price'] > $b['customized_price'] ? 1 : -1;
        });
        foreach(array_slice($cheap, 0, 8) as $item){
            $key = $item['id'];
            if(!isset($map[$key])){
                $map[$key] = true;
                $result[] = $item;
            }
        }
        $roastSuitable = $bucket;
        usort($roastSuitable, function ($a, $b) use ($baking) {
            $scoreA = $this->roastSuitabilityScore($a, $baking, '', '');
            $scoreB = $this->roastSuitabilityScore($b, $baking, '', '');
            if($scoreA == $scoreB){
                return 0;
            }
            return $scoreA > $scoreB ? -1 : 1;
        });
        foreach(array_slice($roastSuitable, 0, 8) as $item){
            $key = $item['id'];
            if(!isset($map[$key])){
                $map[$key] = true;
                $result[] = $item;
            }
        }
        if($refreshSeed > 0){
            $refreshPool = [];
            $refreshMap = [];
            foreach([array_slice($bucket, 0, 32), array_slice($cheap, 0, 16), array_slice($roastSuitable, 0, 16)] as $group){
                foreach($group as $item){
                    $key = $item['id'];
                    if(!isset($refreshMap[$key])){
                        $refreshMap[$key] = true;
                        $refreshPool[] = $item;
                    }
                }
            }
            usort($refreshPool, function ($a, $b) use ($refreshSeed, $slotIndex) {
                $hashA = sprintf('%u', crc32($refreshSeed.':'.$slotIndex.':'.$a['id']));
                $hashB = sprintf('%u', crc32($refreshSeed.':'.$slotIndex.':'.$b['id']));
                if($hashA == $hashB){
                    return 0;
                }
                return $hashA > $hashB ? 1 : -1;
            });
            $mixed = [];
            $mixedMap = [];
            foreach(array_merge(array_slice($refreshPool, 0, 12), $result) as $item){
                $key = $item['id'];
                if(!isset($mixedMap[$key])){
                    $mixedMap[$key] = true;
                    $mixed[] = $item;
                }
            }
            return array_slice($mixed, 0, 18);
        }
        return array_slice($result, 0, 16);
    }

    private function formatQuickBlendPlan($plan, $name, $desc, $baking){
        return [
            'name' => $name,
            'desc' => $desc,
            'plan_type' => $name,
            'plan_desc' => $desc,
            'baking' => $baking,
            'estimated_cost' => sprintf('%.2f', $plan['cost']),
            'target_ag' => sprintf('%.1f', $plan['ag']),
            'reference_ag' => sprintf('%.1f', $plan['ag']),
            'target_baking' => $baking,
            'template_name' => isset($plan['template_name']) ? $plan['template_name'] : '',
            'template_desc' => isset($plan['template_desc']) ? $plan['template_desc'] : '',
            'list' => $plan['list']
        ];
    }

    private function quickBlendPlanSignature($list){
        $parts = [];
        foreach($list as $item){
            $parts[] = $item['id'].':'.$item['ratio'];
        }
        sort($parts);
        return implode('|', $parts);
    }

    private function quickBlendPlanBeanSignature($list){
        $parts = [];
        foreach($list as $item){
            if(isset($item['id'])){
                $parts[] = (int)$item['id'];
            }
        }
        sort($parts);
        return implode('|', $parts);
    }

    private function flavorPlanDesc($flavor){
        if($flavor == 'enhanced'){
            return '在满足成本、目标烘焙适配和生拼共烘稳定性的前提下，让增味或创新处理法表达更明确。';
        }
        if($flavor == 'floral_fruit'){
            return '在满足成本、目标烘焙适配和生拼共烘稳定性的前提下，让果香和酸甜感更突出。';
        }
        if($flavor == 'floral_clean'){
            return '在满足成本、目标烘焙适配和生拼共烘稳定性的前提下，让花香、干净度和清爽层次更突出。';
        }
        return '在满足成本、目标烘焙适配和生拼共烘稳定性的前提下，让坚果、可可和甜感表达更集中。';
    }

    private function quickBlendRatioVariantsCached($selected, &$best){
        $keyParts = [];
        foreach($selected as $item){
            $keyParts[] = $item['_template_role'].':'.$item['_template_ratio'].':'.(!empty($item['formula_strong_process']) ? 1 : 0);
        }
        $cacheKey = implode('|', $keyParts);
        if(!isset($best['ratio_variant_cache'][$cacheKey])){
            $best['ratio_variant_cache'][$cacheKey] = $this->quickBlendRatioVariants($selected);
        }
        return $best['ratio_variant_cache'][$cacheKey];
    }

    private function quickBlendRatioVariants($selected){
        $ranges = [];
        $templateRatios = [];
        foreach($selected as $item){
            $ranges[] = $this->hardRatioRangeByRole($item['_template_role'], $item);
            $templateRatios[] = (int)$item['_template_ratio'];
        }
        $variants = [];
        $this->appendQuickBlendRatioVariant($variants, $templateRatios, $ranges);
        $this->collectRatioVariantsNearTemplate($ranges, $templateRatios, 0, [], 0, $variants, 80);
        if(count($variants) < 20){
            $this->collectRatioVariants($ranges, 0, [], 0, $variants, 80);
        }
        if(!$variants){
            $variants[] = $templateRatios;
        }
        usort($variants, function ($a, $b) use ($templateRatios) {
            $distanceA = 0;
            $distanceB = 0;
            foreach($templateRatios as $idx => $ratio){
                $distanceA += abs($a[$idx] - $ratio);
                $distanceB += abs($b[$idx] - $ratio);
            }
            if($distanceA == $distanceB){
                return 0;
            }
            return $distanceA > $distanceB ? 1 : -1;
        });
        return array_slice($variants, 0, 60);
    }

    private function appendQuickBlendRatioVariant(&$variants, $ratios, $ranges){
        if(count($ratios) != count($ranges) || array_sum($ratios) != 100){
            return;
        }
        foreach($ratios as $index => $ratio){
            if($ratio < $ranges[$index][0] || $ratio > $ranges[$index][1] || $ratio % 5 != 0){
                return;
            }
        }
        $key = implode(',', $ratios);
        foreach($variants as $variant){
            if(implode(',', $variant) == $key){
                return;
            }
        }
        $variants[] = $ratios;
    }

    private function collectRatioVariantsNearTemplate($ranges, $templateRatios, $index, $current, $sum, &$variants, $limit){
        if(count($variants) >= $limit){
            return;
        }
        $window = 10;
        if($index == count($ranges) - 1){
            $last = 100 - $sum;
            if($last >= $ranges[$index][0] && $last <= $ranges[$index][1] && $last % 5 == 0 && abs($last - $templateRatios[$index]) <= $window){
                $current[] = $last;
                $this->appendQuickBlendRatioVariant($variants, $current, $ranges);
            }
            return;
        }
        $start = max($ranges[$index][0], $templateRatios[$index] - $window);
        $end = min($ranges[$index][1], $templateRatios[$index] + $window);
        $start = (int)(ceil($start / 5) * 5);
        $end = (int)(floor($end / 5) * 5);
        for($ratio = $start; $ratio <= $end; $ratio += 5){
            if($sum + $ratio >= 100){
                break;
            }
            $next = $current;
            $next[] = $ratio;
            $this->collectRatioVariantsNearTemplate($ranges, $templateRatios, $index + 1, $next, $sum + $ratio, $variants, $limit);
        }
    }

    private function collectRatioVariants($ranges, $index, $current, $sum, &$variants, $limit){
        if(count($variants) >= $limit){
            return;
        }
        if($index == count($ranges) - 1){
            $last = 100 - $sum;
            if($last >= $ranges[$index][0] && $last <= $ranges[$index][1] && $last % 5 == 0){
                $current[] = $last;
                $this->appendQuickBlendRatioVariant($variants, $current, $ranges);
            }
            return;
        }
        for($ratio = $ranges[$index][0]; $ratio <= $ranges[$index][1]; $ratio += 5){
            if($sum + $ratio >= 100){
                break;
            }
            $next = $current;
            $next[] = $ratio;
            $this->collectRatioVariants($ranges, $index + 1, $next, $sum + $ratio, $variants, $limit);
        }
    }

    private function pickCandidateForRole($candidates, $role, $usedIds, $ratio, $scene, $flavor, $selected){
        $best = null;
        $bestScore = -999999;
        foreach($candidates as $candidate){
            if(in_array($candidate['id'], $usedIds)){
                continue;
            }
            if($candidate['formula_strong_process'] && $this->selectedStrongProcessCount($selected) >= 1 && $ratio > 10){
                continue;
            }
            if(!$this->ratioHardAllowed($candidate, $role, $ratio)){
                continue;
            }
            if(!$this->candidateAllowedForTemplateRole($candidate, $role)){
                continue;
            }
            $score = $this->roleMatchScore($candidate, $role) + $candidate['score'];
            if($role == 'accent' && !$candidate['formula_strong_process'] && $flavor == 'enhanced'){
                $score -= 10;
            }
            if($score > $bestScore){
                $bestScore = $score;
                $best = $candidate;
            }
        }
        return $best;
    }

    private function tuneQuickBlendCost($list, $candidates, $budgetRange, $scene, $flavor, $direction = ''){
        if(count($list) < 2){
            return $list;
        }
        $min = $budgetRange[0];
        $max = $budgetRange[1];
        $cost = $this->weightedBlendCost($list);
        if($direction == ''){
            if($max > 0 && $cost > $max){
                $direction = 'down';
            }elseif($min > 0 && $cost < $min){
                $direction = 'up';
            }else{
                return $list;
            }
        }
        for($round = 0; $round < 8; $round++){
            $cost = $this->weightedBlendCost($list);
            if($direction == 'down' && ($max <= 0 || $cost <= $max)){
                break;
            }
            if($direction == 'up' && ($min <= 0 || $cost >= $min)){
                break;
            }
            $replaceIndex = $this->replacementIndexByCost($list, $direction);
            $role = isset($list[$replaceIndex]['assigned_role']) ? $list[$replaceIndex]['assigned_role'] : (isset($list[$replaceIndex]['formula_primary_position']) ? $list[$replaceIndex]['formula_primary_position'] : 'base');
            $ratio = $list[$replaceIndex]['ratio'];
            $usedIds = [];
            foreach($list as $idx => $item){
                if($idx != $replaceIndex){
                    $usedIds[] = $item['id'];
                }
            }
            $replacement = $this->pickCostCandidate($candidates, $role, $usedIds, $ratio, $scene, $flavor, $direction, $list[$replaceIndex]['customized_price']);
            if(!$replacement){
                break;
            }
            $replacement['ratio'] = $ratio;
            $replacement['assigned_role'] = $role;
            $replacement['assigned_role_label'] = $this->formulaRoleLabel($role);
            $replacement['formula_role_label'] = $this->formulaRoleLabel($replacement['formula_primary_position']);
            $replacement['bean_role_label'] = $replacement['formula_role_label'];
            $list[$replaceIndex] = $replacement;
        }
        return $list;
    }

    private function pickCostCandidate($candidates, $role, $usedIds, $ratio, $scene, $flavor, $direction, $currentPrice){
        $best = null;
        $bestScore = -999999;
        foreach($candidates as $candidate){
            if(in_array($candidate['id'], $usedIds)){
                continue;
            }
            if($direction == 'down' && $candidate['customized_price'] >= $currentPrice){
                continue;
            }
            if($direction == 'up' && $candidate['customized_price'] <= $currentPrice){
                continue;
            }
            if(!$this->candidateAllowedForTemplateRole($candidate, $role)){
                continue;
            }
            $score = $this->roleMatchScore($candidate, $role) + $candidate['score'];
            $score += $direction == 'down' ? (200 - $candidate['customized_price']) / 10 : $candidate['customized_price'] / 10;
            if($score > $bestScore){
                $bestScore = $score;
                $best = $candidate;
            }
        }
        return $best;
    }

    private function replacementIndexByCost($list, $direction){
        $targetIndex = 0;
        foreach($list as $index => $item){
            if($direction == 'down'){
                if($item['customized_price'] > $list[$targetIndex]['customized_price']){
                    $targetIndex = $index;
                }
            }else{
                if($item['customized_price'] < $list[$targetIndex]['customized_price']){
                    $targetIndex = $index;
                }
            }
        }
        return $targetIndex;
    }

    private function normalizeQuickBlendRatios($list){
        $total = 0;
        foreach($list as $item){
            $total += $item['ratio'];
        }
        if($total == 100){
            return $this->cleanQuickBlendItems($list);
        }
        $diff = 100 - $total;
        $list[0]['ratio'] += $diff;
        return $this->cleanQuickBlendItems($list);
    }

    private function cleanQuickBlendItems($list){
        foreach($list as &$item){
            unset($item['score']);
        }
        return $list;
    }

    private function weightedBlendCost($list, $discountRate = 1){
        $cost = 0;
        foreach($list as $item){
            $cost += $item['customized_price'] * $item['ratio'] / 100;
        }
        return $cost * $discountRate;
    }

    private function blendCostInRange($list, $budgetRange, $discountRate = 1){
        $cost = $this->weightedBlendCost($list, $discountRate);
        return $this->costInRangeValue($cost, $budgetRange);
    }

    private function costInRangeValue($cost, $budgetRange){
        if($budgetRange[1] > 0 && $cost > $budgetRange[1]){
            return false;
        }
        return true;
    }

    private function normalizeQuickBlendTotalWeight($weight){
        $value = (int)$weight;
        if($value <= 250){
            return 250;
        }
        if($value <= 500){
            return 500;
        }
        if($value <= 1000){
            return 1000;
        }
        return (int)(ceil($value / 1000) * 1000);
    }

    private function getQuickBlendWeightDiscountRate($weight){
        $kg = max(1, (int)ceil(((float)$weight) / 1000));
        $rawRules = getValues('custom_weight_discount_rules');
        $rules = [];
        if(is_string($rawRules) && trim($rawRules) !== ''){
            $decoded = json_decode($rawRules, true);
            if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
                $rules = $decoded;
            }else{
                $lines = preg_split('/\r\n|\r|\n/', trim($rawRules));
                foreach($lines as $line){
                    $line = trim($line);
                    if($line === '' || strpos($line, '|') === false){
                        continue;
                    }
                    $parts = array_map('trim', explode('|', $line, 2));
                    if($parts[0] !== '' && $parts[1] !== ''){
                        $rules[$parts[0]] = $parts[1];
                    }
                }
            }
        }elseif(is_array($rawRules)){
            $rules = $rawRules;
        }
        if(!$rules){
            $rules = [
                1 => 100,
                2 => 100,
                3 => 98,
                4 => 97,
                5 => 96,
                6 => 95,
                7 => 94,
                8 => 93,
                9 => 92,
                10 => 91,
                11 => 90,
                12 => 89,
                13 => 88,
                14 => 87,
                15 => 86,
                16 => 85,
                17 => 84,
                18 => 83,
                19 => 82,
                20 => 80
            ];
        }
        ksort($rules, SORT_NUMERIC);
        $selected = null;
        foreach($rules as $limit => $discount){
            if($kg >= (int)$limit){
                $selected = $discount;
            }else{
                break;
            }
        }
        if($selected === null){
            $selected = 100;
        }
        $rate = (float)$selected;
        if($rate > 1){
            $rate = $rate / 100;
        }
        if($rate <= 0){
            $rate = 1;
        }
        return $rate;
    }

    private function formatQuickBlendWeightDiscountText($rate){
        $percent = round($rate * 100);
        return $percent >= 100 ? '原价' : $percent . '折';
    }

    private function weightedBlendAg($list){
        $ag = 0;
        foreach($list as $item){
            $ag += $item['ag'] * $item['ratio'] / 100;
        }
        return $ag;
    }

    private function selectedStrongProcessCount($selected){
        $count = 0;
        foreach($selected as $item){
            if(!empty($item['formula_strong_process'])){
                $count++;
            }
        }
        return $count;
    }

    private function quickBlendScore($goods, $scene, $flavor){
        $text = $goods['name'].$goods['bean_seed'].$goods['processing_method'].$goods['special_flavour'].$goods['product_area'].$goods['custom_flavour_tags'];
        $score = 0;
        if($goods['formula_strength'] == 'high'){
            $score += 12;
        }elseif($goods['formula_strength'] == 'low'){
            $score -= 6;
        }
        if($flavor == 'nut_cocoa'){
            $score += $this->keywordScore($text, ['坚果','可可','巧克力','黑巧','焦糖','杏仁','榛果']);
            $score += intval($goods['taste_sweetness']) + intval($goods['taste_body']);
        }elseif($flavor == 'floral_fruit'){
            $score += $this->keywordScore($text, ['果','莓','柑橘','桃','葡萄','柠檬','热带','水果','果汁','酸甜']);
            $score += intval($goods['taste_acidity']) + intval($goods['taste_sweetness']) + intval($goods['taste_aftertaste']);
        }elseif($flavor == 'floral_clean'){
            $score += $this->keywordScore($text, ['花','茉莉','白花','玫瑰','橙花','茶','佛手柑','干净','清爽','清新']);
            $score += intval($goods['taste_aroma']) + intval($goods['taste_aftertaste']);
        }elseif($flavor == 'enhanced'){
            $score += $this->keywordScore($text, ['厌氧','发酵','酵母','酒桶','特殊','创新','蜜处理','日晒','增味']);
            $score += !empty($goods['formula_strong_process']) ? 16 : 0;
        }
        if($scene == 'milk'){
            $score += $this->keywordScore($text, ['坚果','可可','巧克力','焦糖','奶油','甜']);
            if($goods['ag'] >= 55 && $goods['ag'] <= 78){
                $score += 8;
            }
        }elseif($scene == 'black'){
            $score += $this->keywordScore($text, ['花','果','柑橘','干净','明亮','茶']);
            if($goods['ag'] >= 70 && $goods['ag'] <= 88){
                $score += 8;
            }
        }else{
            if($goods['ag'] >= 62 && $goods['ag'] <= 82){
                $score += 8;
            }
        }
        return $score;
    }

    private function roleMatchScore($goods, $role){
        if($goods['formula_primary_position'] == $role){
            return 90;
        }
        if(in_array($role, $goods['formula_secondary_positions'])){
            return 58;
        }
        $text = $goods['name'].$goods['bean_seed'].$goods['processing_method'].$goods['special_flavour'].$goods['custom_flavour_tags'];
        $keywords = [
            'base' => ['巴西','哥伦比亚','基底','醇厚','稳定','坚果','巧克力'],
            'sweet' => ['甜','焦糖','蜂蜜','可可','坚果','奶油','黑糖'],
            'aroma' => ['花','果','莓','柑橘','茉莉','红茶','葡萄'],
            'accent' => ['厌氧','发酵','酒桶','酵母','特殊','日晒','增味'],
            'balance' => ['平衡','干净','柔和','尾韵','顺滑']
        ];
        return isset($keywords[$role]) ? $this->keywordScore($text, $keywords[$role]) : 0;
    }

    private function parseFormulaPosition($goods){
        $raw = isset($goods['blend_role']) ? $goods['blend_role'] : '';
        $config = [];
        $hasManualPrimary = false;
        if($raw && is_string($raw) && substr($raw, 0, 1) == '{'){
            $decoded = json_decode($raw, true);
            if(is_array($decoded)){
                $config = $decoded;
                $hasManualPrimary = !empty($config['primary']);
            }
        }elseif($raw && is_string($raw) && strpos($raw, '|') !== false){
            $parts = explode('|', $raw);
            $config['primary'] = isset($parts[0]) && $parts[0] !== '' ? $parts[0] : 'base';
            $config['secondary'] = isset($parts[1]) && $parts[1] !== '' ? array_filter(explode(',', $parts[1])) : [];
            $config['strength'] = isset($parts[4]) && $parts[4] !== '' ? $parts[4] : 'medium';
            $config['strong_process'] = isset($parts[5]) ? (int)$parts[5] : 0;
            $config['confirmed'] = isset($parts[6]) ? (int)$parts[6] : 0;
            $hasManualPrimary = isset($parts[0]) && $parts[0] !== '';
        }elseif($raw){
            $config['primary'] = $raw == 'flavour' ? 'aroma' : $raw;
            $hasManualPrimary = true;
        }
        $judgement = $this->judgeFormulaPosition($goods);
        $primary = $hasManualPrimary && isset($config['primary']) && $config['primary'] ? $config['primary'] : $judgement['primary'];
        $secondary = isset($config['secondary']) && is_array($config['secondary']) ? $config['secondary'] : $judgement['secondary'];
        $strongProcess = $this->isStrongProcessByText($goods);
        return [
            'primary' => $primary,
            'secondary' => array_values(array_diff($secondary, [$primary])),
            'strength' => isset($config['strength']) ? $config['strength'] : 'medium',
            'strong_process' => $strongProcess,
            'scores' => $judgement['scores'],
            'reason' => $judgement['reason'],
            'recommended_ratio' => $this->recommendedRatioByRole($primary, $goods),
            'avoid_roles' => $this->avoidRolesByRole($primary, $judgement['scores'])
        ];
    }

    private function inferFormulaPrimaryPosition($goods){
        $judgement = $this->judgeFormulaPosition($goods);
        return $judgement['primary'];
    }

    private function judgeFormulaPosition($goods){
        $text = $this->beanJudgeText($goods);
        $acidity = intval(isset($goods['taste_acidity']) ? $goods['taste_acidity'] : 0);
        $sweetness = intval(isset($goods['taste_sweetness']) ? $goods['taste_sweetness'] : 0);
        $body = intval(isset($goods['taste_body']) ? $goods['taste_body'] : 0);
        $aroma = $this->beanAromaStrength($goods);
        $fermentation = $this->beanFermentationStrength($goods);
        $cleanliness = $this->beanCleanlinessStrength($goods);
        $priceLevel = $this->beanPriceLevel($goods);
        $specialProcess = $this->isSpecialProcessByText($goods);
        $ordinaryProcess = !$this->keywordScore($text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','特殊发酵']);
        $scores = ['base' => 0, 'sweet' => 0, 'aroma' => 0, 'accent' => 0, 'balance' => 0];

        if($this->keywordScore($text, ['巴西','洪都拉斯','危地马拉','哥伦比亚','云南']) > 0){ $scores['base'] += 2; }
        if($this->keywordScore($text, ['坚果','可可','巧克力','焦糖','麦芽','奶油','红糖']) > 0){ $scores['base'] += 2; }
        if($body >= 4){ $scores['base'] += 3; }
        if($sweetness >= 3){ $scores['base'] += 1; }
        if($acidity > 0 && $acidity <= 3){ $scores['base'] += 1; }
        if($aroma > 0 && $aroma <= 3){ $scores['base'] += 1; }
        if($fermentation <= 2){ $scores['base'] += 2; }
        if($priceLevel <= 3){ $scores['base'] += 1; }
        if($this->keywordScore($text, ['意式','奶咖','通用']) > 0){ $scores['base'] += 2; }
        if($specialProcess){ $scores['base'] -= 2; }
        if($aroma >= 5){ $scores['base'] -= 1; }
        if($fermentation >= 4){ $scores['base'] -= 3; }
        if($priceLevel >= 4){ $scores['base'] -= 2; }
        if($this->keywordScore($text, ['玫瑰','茉莉','荔枝','热带水果','酒香']) > 0){ $scores['base'] -= 1; }

        if($sweetness >= 4){ $scores['sweet'] += 4; }
        if($this->keywordScore($text, ['蜂蜜','焦糖','红糖','黄糖','枫糖','果脯','熟水果','奶油','甜橙','黄桃','甜瓜']) > 0){ $scores['sweet'] += 3; }
        if($this->keywordScore($text, ['蜜处理','日晒','半日晒','厌氧蜜处理']) > 0){ $scores['sweet'] += 2; }
        if($body >= 3){ $scores['sweet'] += 1; }
        if($acidity > 0 && $acidity <= 4){ $scores['sweet'] += 1; }
        if($fermentation <= 3){ $scores['sweet'] += 1; }
        if($this->keywordScore($text, ['奶咖','意式','通用']) > 0){ $scores['sweet'] += 1; }
        if($acidity >= 5){ $scores['sweet'] -= 2; }
        if($fermentation >= 4){ $scores['sweet'] -= 2; }
        if($this->keywordScore($text, ['尖酸','青苹果','草本','番茄']) > 0){ $scores['sweet'] -= 1; }
        if($cleanliness > 0 && $cleanliness <= 2){ $scores['sweet'] -= 2; }

        if($aroma >= 4){ $scores['aroma'] += 4; }
        if($this->keywordScore($text, ['茉莉','白花','玫瑰','橙花','柑橘','佛手柑','荔枝','葡萄','水蜜桃','热带水果','莓果']) > 0){ $scores['aroma'] += 3; }
        if($this->keywordScore($text, ['埃塞俄比亚','巴拿马']) > 0){ $scores['aroma'] += 2; }
        if($this->keywordScore($text, ['瑰夏','粉波旁','sidra','SL28','74110','74158']) > 0){ $scores['aroma'] += 2; }
        if($this->keywordScore($text, ['水洗','日晒','蜜处理']) > 0){ $scores['aroma'] += 1; }
        if($cleanliness >= 4){ $scores['aroma'] += 1; }
        if($fermentation >= 4){ $scores['aroma'] -= 1; }
        if($body >= 5 && $aroma <= 3){ $scores['aroma'] -= 2; }
        if($aroma <= 3 && $this->keywordScore($text, ['坚果','可可','麦芽','烟熏']) > 0){ $scores['aroma'] -= 2; }

        if($specialProcess){ $scores['accent'] += 4; }
        if($this->keywordScore($text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵']) > 0){ $scores['accent'] += 4; }
        if($fermentation >= 4){ $scores['accent'] += 3; }
        if($this->keywordScore($text, ['酒香','雪莉','白兰地','朗姆','草莓','葡萄','荔枝','香草','热带水果','乳酸','果酱']) > 0){ $scores['accent'] += 3; }
        if($aroma >= 4){ $scores['accent'] += 1; }
        if($sweetness >= 4){ $scores['accent'] += 1; }
        if($priceLevel >= 4){ $scores['accent'] += 1; }
        if(!$specialProcess && $fermentation <= 2 && $ordinaryProcess){ $scores['accent'] -= 3; }
        if($this->keywordScore($text, ['坚果','可可','焦糖']) > 0 && $fermentation <= 2){ $scores['accent'] -= 2; }
        if($cleanliness > 0 && $cleanliness <= 2){ $scores['accent'] -= 2; }

        if($this->betweenScore($acidity, 2, 4)){ $scores['balance'] += 2; }
        if($this->betweenScore($sweetness, 3, 4)){ $scores['balance'] += 2; }
        if($this->betweenScore($body, 3, 4)){ $scores['balance'] += 2; }
        if($this->betweenScore($aroma, 2, 4)){ $scores['balance'] += 1; }
        if($fermentation <= 2){ $scores['balance'] += 2; }
        if($cleanliness >= 4){ $scores['balance'] += 3; }
        if($this->keywordScore($text, ['哥伦比亚','危地马拉','洪都拉斯','墨西哥','秘鲁']) > 0){ $scores['balance'] += 2; }
        if($this->keywordScore($text, ['水洗','蜜处理']) > 0){ $scores['balance'] += 1; }
        if($this->keywordScore($text, ['通用','意式','黑咖']) > 0){ $scores['balance'] += 1; }
        if($aroma >= 5){ $scores['balance'] -= 1; }
        if($fermentation >= 4){ $scores['balance'] -= 3; }
        if($acidity >= 5){ $scores['balance'] -= 2; }
        if($body > 0 && $body <= 2){ $scores['balance'] -= 1; }
        if($specialProcess){ $scores['balance'] -= 2; }

        $primary = $this->highestScoreRole($scores);
        if($specialProcess && $fermentation >= 4){
            $primary = 'accent';
        }elseif($aroma >= 4 && $cleanliness >= 4 && $fermentation <= 3){
            $primary = 'aroma';
        }elseif($body >= 4 && $fermentation <= 2 && $priceLevel <= 3){
            $primary = 'base';
        }elseif($sweetness >= 4 && $aroma <= 4 && $fermentation <= 3){
            $primary = 'sweet';
        }elseif($this->betweenScore($acidity, 2, 4) && $this->betweenScore($sweetness, 3, 4) && $this->betweenScore($body, 3, 4) && $this->betweenScore($aroma, 2, 4) && $cleanliness >= 4){
            $primary = 'balance';
        }
        $secondary = $this->subRolesByScores($scores, $primary);
        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'scores' => $scores,
            'reason' => $this->roleReasonByRole($primary)
        ];
    }

    private function hardRatioRangeByRole($role, $goods = []){
        $range = [10, 95];
        if(!empty($goods['formula_strong_process'])){
            $range[1] = min($range[1], $role == 'accent' ? 12 : 10);
        }
        return $range;
    }

    private function ratioHardAllowed($goods, $role, $ratio){
        $range = $this->hardRatioRangeByRole($role, $goods);
        return $ratio >= $range[0] && $ratio <= $range[1];
    }

    private function candidateAllowedForTemplateRole($candidate, $role){
        if(!isset($candidate['formula_primary_position'])){
            return false;
        }
        if($candidate['formula_primary_position'] == $role){
            return true;
        }
        $secondary = isset($candidate['formula_secondary_positions']) && is_array($candidate['formula_secondary_positions']) ? $candidate['formula_secondary_positions'] : [];
        return in_array($role, $secondary);
    }

    private function quickBlendCandidateCanServeRole($candidate, $role){
        return $this->candidateAllowedForTemplateRole($candidate, $role) || $this->roleMatchScore($candidate, $role) >= 20;
    }

    private function roastSuitabilityScore($goods, $baking, $scene = '', $flavor = ''){
        $text = $this->beanJudgeText($goods);
        $score = 0;
        $acidity = intval(isset($goods['taste_acidity']) ? $goods['taste_acidity'] : 0);
        $sweetness = intval(isset($goods['taste_sweetness']) ? $goods['taste_sweetness'] : 0);
        $body = intval(isset($goods['taste_body']) ? $goods['taste_body'] : 0);
        $aroma = $this->beanAromaStrength($goods);
        $fermentation = $this->beanFermentationStrength($goods);
        $cleanliness = $this->beanCleanlinessStrength($goods);
        $strongProcess = !empty($goods['formula_strong_process']) || $this->isStrongProcessByText($goods);
        $specialProcess = $this->isSpecialProcessByText($goods);

        if($baking == '中浅烘焙'){
            if($aroma >= 4){ $score += 14; }
            if($cleanliness >= 4){ $score += 8; }
            if($acidity >= 3 && $acidity <= 5){ $score += 6; }
            $score += $this->keywordScore($text, ['水洗','花','茉莉','柑橘','莓','茶','明亮','干净']);
            if($body >= 5 && $aroma <= 3){ $score -= 10; }
            if($this->keywordScore($text, ['烟熏','烘烤','低酸','厚重']) > 0){ $score -= 8; }
            if($fermentation >= 5){ $score -= 6; }
        }elseif($baking == '中度烘焙'){
            if($sweetness >= 3){ $score += 8; }
            if($body >= 3 && $body <= 4){ $score += 6; }
            if($cleanliness >= 3){ $score += 5; }
            $score += $this->keywordScore($text, ['甜','焦糖','坚果','巧克力','平衡','蜂蜜','奶油']);
            if($fermentation >= 5){ $score -= 4; }
        }elseif($baking == '中深烘焙'){
            if($body >= 4){ $score += 10; }
            if($sweetness >= 3){ $score += 6; }
            if($acidity > 0 && $acidity <= 3){ $score += 5; }
            $score += $this->keywordScore($text, ['坚果','可可','巧克力','黑巧','焦糖','厚度','奶油']);
            if($aroma >= 5 && $body <= 2){ $score -= 8; }
            if($strongProcess){ $score -= 8; }
        }elseif($baking == '深度烘焙'){
            if($body >= 4){ $score += 12; }
            if($acidity > 0 && $acidity <= 2){ $score += 8; }
            $score += $this->keywordScore($text, ['可可','巧克力','黑巧','坚果','焦糖','低酸','厚重']);
            if($aroma >= 5){ $score -= 12; }
            if($acidity >= 5){ $score -= 10; }
            if($strongProcess || $specialProcess){ $score -= 18; }
            if($this->keywordScore($text, ['花香','茉莉','瑰夏','柑橘','莓果']) > 0){ $score -= 10; }
        }

        if($scene == 'milk' && in_array($baking, ['中深烘焙','深度烘焙'])){
            $score += $body + $sweetness;
        }
        if($scene == 'black' && $baking == '深度烘焙' && $flavor == 'floral_fruit'){
            $score -= 10;
        }
        if($flavor == 'enhanced' && $strongProcess && in_array($baking, ['中浅烘焙','中度烘焙'])){
            $score += 8;
        }
        return $score;
    }

    private function greenBlendHardAllowed($list, $scene = '', $baking = ''){
        if(count($list) < 2 || count($list) > 5){
            return false;
        }
        if($this->quickBlendBaseStructureRatio($list) < $this->quickBlendBaseStructureMinRatio($scene, $baking)){
            return false;
        }
        $strongProcessCount = 0;
        $strongProcessRatio = 0;
        $aromaAccentRatio = 0;
        foreach($list as $item){
            $ratio = (int)(isset($item['ratio']) ? $item['ratio'] : 0);
            if($ratio < 10){
                return false;
            }
            $role = isset($item['assigned_role']) ? $item['assigned_role'] : (isset($item['formula_primary_position']) ? $item['formula_primary_position'] : '');
            if(!empty($item['formula_strong_process'])){
                $strongProcessCount++;
                $strongProcessRatio += $ratio;
            }
            if($role == 'aroma' || $role == 'accent'){
                $aromaAccentRatio += $ratio;
            }
        }
        if($strongProcessCount > 1 || $strongProcessRatio > 12){
            return false;
        }
        if($aromaAccentRatio > 40){
            return false;
        }
        return $this->greenBlendCompatibilityScore($list, $scene, $baking) > -45;
    }

    private function greenBlendCompatibilityScore($list, $scene = '', $baking = ''){
        $score = 30;
        $baseStructureRatio = $this->quickBlendBaseStructureRatio($list);
        if($baseStructureRatio < $this->quickBlendBaseStructureMinRatio($scene, $baking)){
            return -999;
        }
        $densities = [];
        $moistures = [];
        $strongProcessCount = 0;
        $strongProcessRatio = 0;
        $aromaAccentRatio = 0;
        $processTypes = [];
        foreach($list as $item){
            $ratio = (int)(isset($item['ratio']) ? $item['ratio'] : 0);
            if($ratio < 10){
                return -999;
            }
            $density = $this->normalizeBeanDensity(isset($item['density']) ? $item['density'] : 0);
            if($density > 0){
                $densities[] = $density;
            }
            $moisture = $this->normalizeBeanMoisture(isset($item['moisture_content']) ? $item['moisture_content'] : 0);
            if($moisture > 0){
                $moistures[] = $moisture;
            }
            $role = isset($item['assigned_role']) ? $item['assigned_role'] : (isset($item['formula_primary_position']) ? $item['formula_primary_position'] : '');
            if(!empty($item['formula_strong_process'])){
                $strongProcessCount++;
                $strongProcessRatio += $ratio;
            }
            if($role == 'aroma' || $role == 'accent'){
                $aromaAccentRatio += $ratio;
            }
            $processType = $this->beanProcessType($item);
            if($processType){
                $processTypes[$processType] = true;
            }
        }

        if(count($densities) >= 2){
            $densityDiff = max($densities) - min($densities);
            if($densityDiff > 0.08){ $score -= 24; }
            elseif($densityDiff > 0.05){ $score -= 12; }
            elseif($densityDiff <= 0.03){ $score += 6; }
        }
        if(count($moistures) >= 2){
            $moistureDiff = max($moistures) - min($moistures);
            if($moistureDiff > 2){ $score -= 20; }
            elseif($moistureDiff > 1.2){ $score -= 10; }
            elseif($moistureDiff <= 0.8){ $score += 6; }
        }
        if($strongProcessCount > 0){
            $score -= $strongProcessCount * 14;
            $score -= max(0, $strongProcessRatio - 8) * 2;
        }
        if($aromaAccentRatio > 35){
            $score -= ($aromaAccentRatio - 35) * 1.5;
        }
        if(count($processTypes) >= 4){
            $score -= 12;
        }elseif(count($processTypes) <= 2){
            $score += 4;
        }
        if(count($list) == 2 || count($list) == 3){
            $score += 6;
        }elseif(count($list) >= 5){
            $score -= 6;
        }
        return $score;
    }

    private function quickBlendBaseStructureMinRatio($scene = '', $baking = ''){
        if($baking == '深度烘焙'){
            return 60;
        }
        if($scene == 'milk'){
            return 50;
        }
        if($scene == 'both'){
            return 45;
        }
        return 40;
    }

    private function quickBlendBaseStructureRatio($list){
        $ratio = 0;
        foreach($list as $item){
            if($this->quickBlendItemIsBaseStructure($item)){
                $ratio += (int)(isset($item['ratio']) ? $item['ratio'] : 0);
            }
        }
        return $ratio;
    }

    private function quickBlendItemIsBaseStructure($item){
        $assignedRole = isset($item['assigned_role']) ? $item['assigned_role'] : '';
        $primaryRole = isset($item['formula_primary_position']) ? $item['formula_primary_position'] : '';
        $roleLabel = isset($item['formula_role_label']) ? $item['formula_role_label'] : '';
        $beanRoleLabel = isset($item['bean_role_label']) ? $item['bean_role_label'] : '';
        return $assignedRole == 'base' || $primaryRole == 'base' || $roleLabel == '基底' || $beanRoleLabel == '基底';
    }

    private function normalizeBeanDensity($value){
        $number = (float)$value;
        if($number <= 0){
            return 0;
        }
        if($number > 10){
            return $number / 1000;
        }
        return $number;
    }

    private function normalizeBeanMoisture($value){
        $number = (float)$value;
        if($number <= 0){
            return 0;
        }
        if($number > 1 && $number <= 100){
            return $number;
        }
        return $number * 100;
    }

    private function beanProcessType($goods){
        $text = $this->beanJudgeText($goods);
        if($this->keywordScore($text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','特殊发酵']) > 0){
            return 'strong';
        }
        if($this->keywordScore($text, ['水洗']) > 0){ return 'washed'; }
        if($this->keywordScore($text, ['日晒']) > 0){ return 'natural'; }
        if($this->keywordScore($text, ['蜜处理','半日晒']) > 0){ return 'honey'; }
        return 'other';
    }

    private function isStrongProcessByText($goods){
        $text = $goods['name'].$goods['processing_method'].$goods['special_flavour'].$goods['custom_flavour_tags'];
        return $this->keywordScore($text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','强发酵','特殊发酵','特殊处理']) > 0 ? 1 : 0;
    }

    private function isSpecialProcessByText($goods){
        $text = $this->beanJudgeText($goods);
        return $this->keywordScore($text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','特殊发酵','特殊处理']) > 0 ? 1 : 0;
    }

    private function beanJudgeText($goods){
        $fields = ['name','product_area','bean_seed','processing_method','special_flavour','custom_flavour_tags','specs'];
        $parts = [];
        foreach($fields as $field){
            $parts[] = isset($goods[$field]) ? $goods[$field] : '';
        }
        return implode(' ', $parts);
    }

    private function beanAromaStrength($goods){
        $text = $this->beanJudgeText($goods);
        $score = intval(isset($goods['taste_aroma']) ? $goods['taste_aroma'] : 0);
        if($this->keywordScore($text, ['茉莉','白花','玫瑰','橙花','柑橘','佛手柑','荔枝','葡萄','水蜜桃','热带水果','莓果','花香','果香']) > 0){
            $score = max($score, 4);
        }
        return $score;
    }

    private function beanFermentationStrength($goods){
        $text = $this->beanJudgeText($goods);
        if($this->keywordScore($text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','强发酵','特殊发酵']) > 0){
            return 5;
        }
        if($this->keywordScore($text, ['发酵','酒香','乳酸','果酱']) > 0){
            return 4;
        }
        if($this->keywordScore($text, ['日晒','蜜处理']) > 0){
            return 3;
        }
        return 2;
    }

    private function beanCleanlinessStrength($goods){
        $text = $this->beanJudgeText($goods);
        if($this->keywordScore($text, ['干净','清晰','透明','水洗']) > 0){
            return 4;
        }
        if($this->keywordScore($text, ['浑浊','杂味','粗糙']) > 0){
            return 2;
        }
        return 3;
    }

    private function beanPriceLevel($goods){
        $price = floatval(isset($goods['customized_price']) ? $goods['customized_price'] : 0);
        if($price >= 200){ return 5; }
        if($price >= 150){ return 4; }
        if($price >= 100){ return 3; }
        if($price > 0){ return 2; }
        return 3;
    }

    private function betweenScore($value, $min, $max){
        return $value >= $min && $value <= $max;
    }

    private function highestScoreRole($scores){
        arsort($scores);
        $keys = array_keys($scores);
        return $keys ? $keys[0] : 'base';
    }

    private function subRolesByScores($scores, $primary){
        arsort($scores);
        $roles = [];
        foreach($scores as $role => $score){
            if($role == $primary || $score < 7){
                continue;
            }
            $roles[] = $role;
            if(count($roles) >= 2){
                break;
            }
        }
        return $roles;
    }

    private function recommendedRatioByRole($role, $goods = []){
        if($role == 'base'){ return '40%-70%'; }
        if($role == 'sweet'){ return '15%-35%'; }
        if($role == 'aroma'){
            $text = $this->beanJudgeText($goods);
            return $this->keywordScore($text, ['瑰夏','Geisha','gesha']) > 0 || $this->beanPriceLevel($goods) >= 4 ? '5%-15%' : '5%-20%';
        }
        if($role == 'accent'){
            return $this->isSpecialProcessByText($goods) ? '3%-10%' : '3%-15%';
        }
        if($role == 'balance'){ return '10%-30%'; }
        return '';
    }

    private function avoidRolesByRole($primary, $scores){
        $avoid = [];
        foreach($scores as $role => $score){
            if($role != $primary && $score < 4){
                $avoid[] = $role;
            }
        }
        return $avoid;
    }

    private function roleReasonByRole($role){
        $reasons = [
            'base' => '该豆醇厚度和稳定性更适合作为配方主体，提供咖啡感和厚度。',
            'sweet' => '该豆甜感表现更突出，适合提升配方的圆润度和顺口感。',
            'aroma' => '该豆香气或花果香特征更明显，适合提升配方识别度。',
            'accent' => '该豆处理法或风味表现更有个性，适合小比例增加特殊风味层次。',
            'balance' => '该豆酸甜厚度较均衡，适合协调配方结构。'
        ];
        return isset($reasons[$role]) ? $reasons[$role] : '';
    }

    private function formulaRoleLabel($role){
        $labels = [
            'base' => '基底',
            'sweet' => '甜感',
            'aroma' => '香气',
            'accent' => '增味',
            'balance' => '平衡'
        ];
        return isset($labels[$role]) ? $labels[$role] : '配方定位';
    }

    private function roleByLabel($label){
        $roles = [
            '基底' => 'base',
            '甜感' => 'sweet',
            '香气' => 'aroma',
            '增味' => 'accent',
            '平衡' => 'balance'
        ];
        return isset($roles[$label]) ? $roles[$label] : '';
    }

    private function keywordScore($text, $keywords){
        $score = 0;
        foreach($keywords as $keyword){
            if(strpos($text, $keyword) !== false){
                $score += 10;
            }
        }
        return $score;
    }

    private function agToBaking($ag){
        if($ag >= 50 && $ag < 60){
            return '深度烘焙';
        }elseif($ag >= 60 && $ag < 70){
            return '中深烘焙';
        }elseif($ag >= 70 && $ag < 80){
            return '中度烘焙';
        }elseif($ag >= 80 && $ag < 90){
            return '浅度烘焙';
        }elseif($ag >= 90 && $ag <= 100){
            return '极浅烘焙';
        }
        return '';
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
        $total_count = Comment::where('goods_id',$id)->where('status',1)->order('id desc')->count();
        $image_count = Comment::where('goods_id',$id)->where('status',1)->whereNotNull('images')->count();
        $this->success('ok',compact('list','total_count','image_count'));

    }



    /**
     * 热销商品
     */
    public function hotGoods(){
        $list = GoodsModel::where(['is_hot' => 1,'status' => '1','is_shop_sale'=>1])
            ->field('id,category_id,name,image,money,is_stock')
            ->order('weigh desc,createtime desc')
            ->paginate()
            ->each(function ($key){
                $key->append(['goods_category']);
                $key['money'] = $this->formatShopDisplayMoney($key);
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
        $model = GoodsModel::where(['status' => '1','is_shop_sale'=>1]);
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
            $category =  new \stdClass();
        }
        $list = $model->field('id,name,image,money,category_id,sales,special_flavour,specs,baking,is_stock')
            ->order('weigh desc,createtime desc')
            ->paginate()
            ->each(function ($key){
                $key->append(['goods_category']);
                $key['money'] = $this->formatShopDisplayMoney($key);
                return $key;
            });
        $this->success('成功',compact('list','category'));
    }

    public function customeGoodsList(){
        $name = $this->request->param('name');
        $category_id = $this->request->param('category_id');
        $model = GoodsModel::where(['status' => '1','is_customized'=>1,'custom_status'=>1]);
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
            $ids =  GoodsCategory::where(['shows' => 1])->column('id');
            $model->whereIn('category_id', $ids);
            $category = [];
        }

         /* AG值100-90  ：极浅烘焙
          AG值80-90 ：浅度烘焙
          AG值70-80 ：中度烘焙
          AG值60-70 ：中深烘焙
          AG值50-60 ：深度烘焙*/

        $list = $model->field('id,name,ag,image,customized_price,category_id,sales,product_area,bean_seed,processing_method,special_flavour,moisture_content,density,specs,baking')
            ->order('weigh desc,createtime desc')
            ->paginate()
            ->each(function ($key){
                $key->append(['goods_category']);
                $key['goods_id'] = $key['id'];
                $stock = SkuPrice::where(['goods_id'=>$key['id'],'status'=>'up'])->find();
                $key['stock_id'] = $stock ? $stock['id'] : 0;
                if($key['ag'] >= 50 && $key['ag'] < 60){
                    $key['baking'] = '深度烘焙';
                }elseif($key['ag'] >= 60 && $key['ag'] < 70){
                    $key['baking'] = '中深烘焙';
                }elseif($key['ag'] >= 70 && $key['ag'] < 80){
                    $key['baking'] = '中度烘焙';
                }elseif($key['ag'] >= 80 && $key['ag'] < 90){
                    $key['baking'] = '浅度烘焙';
                }elseif($key['ag'] >= 90 && $key['ag'] <= 100){
                    $key['baking'] = '极浅烘焙';
                }
                return $key;
            });
        $this->success('成功',compact('list','category'));
    }

    public function searchHistory()
    {
      $list = Search::where('user_id',$this->auth->id)->order('createtime desc')->group('name')->select();
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
        $is_dingzhi = $this->request->param('is_dingzhi');
        if($is_dingzhi && $is_dingzhi == 1){
            $list = GoodsCategory::where(['status' => '1','shows'=>1])
                ->field('id,name')
                ->order('weigh desc')
                ->select();
            $new_list[0]['id'] = 0;
            $new_list[0]['name'] = '全部';
            foreach ($list as $k=>$v){
                array_push($new_list,$v);
            }
            $this->success('成功',$new_list);
        }else{
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

    protected function formatShopDisplayMoney($goods)
    {
        if (!isset($goods['id'])) {
            return isset($goods['money']) ? $goods['money'] : '0.00';
        }
        $money = SkuPrice::where(['goods_id' => $goods['id'], 'status' => 'up'])
            ->where('stock', '>', 0)
            ->min('money');
        if ($money === null || $money === '') {
            $money = isset($goods['money']) ? $goods['money'] : 0;
        }
        return number_format((float)$money, 2, '.', '');
    }
}
