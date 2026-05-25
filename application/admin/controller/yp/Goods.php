<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use app\admin\model\yp\Sku;
use app\admin\model\yp\SkuPrice;

/**
 * 商品
 *
 * @icon fa fa-circle-o
 */
class Goods extends Backend
{

    /**
     * Goods模型对象
     * @var \app\admin\model\yp\Goods
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\Goods;
        $this->view->assign("isHotList", $this->model->getIsHotList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("goodsScopeList", [
            'all' => '全部咖啡豆',
            'shop' => '商城成品',
            'custom' => '定制豆池',
            'taxonomy' => '标签与分类',
        ]);
    }

    /**
     * 删除
     *
     * @param $ids
     * @return void
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function del($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post("ids");
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        $list = $this->model->where($pk, 'in', $ids)->select();

        $count = 0;
        Db::startTrans();
        try {
            foreach ($list as $item) {
                $this->checkDeleteAllowed($item['id']);
                $count += $item->delete();
                Sku::where(['goods_id' => $item['id']])->delete();
                SkuPrice::where(['goods_id' => $item['id']])->delete();
            }
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();
            $stockWarning = $this->request->get('stock_warning/d', 0);
            $lowStockGoodsIds = [];
            if ($stockWarning) {
                $lowStockGoodsIds = SkuPrice::where('stock', '<=', 10)->column('goods_id');
                $lowStockGoodsIds = array_values(array_unique(array_filter($lowStockGoodsIds)));
            }

            $query = $this->model
                ->with(['category'])
                ->where($where);
            $scope = $this->request->get('scope', 'all');
            if ($scope === 'shop') {
                $query->where('goods.is_shop_sale', 1);
            } elseif ($scope === 'custom') {
                $query->where('goods.is_customized', 1);
            }
            if ($stockWarning) {
                if ($lowStockGoodsIds) {
                    $query->where('goods.id', 'in', $lowStockGoodsIds);
                } else {
                    $query->where('goods.id', '=', 0);
                }
            }
            $list = $query->order($sort, $order)->paginate($limit);

            $goodsIds = [];
            foreach ($list as $row) {
                $goodsIds[] = $row['id'];
            }
            $stockRows = $goodsIds ? SkuPrice::where('goods_id', 'in', $goodsIds)
                ->field('goods_id, SUM(stock) AS total_stock, MIN(stock) AS min_stock, COUNT(*) AS sku_count')
                ->group('goods_id')
                ->select() : [];
            $stockMap = [];
            foreach ($stockRows as $stockRow) {
                $stockMap[$stockRow['goods_id']] = [
                    'total_stock' => (int)$stockRow['total_stock'],
                    'min_stock'   => (int)$stockRow['min_stock'],
                    'sku_count'   => (int)$stockRow['sku_count'],
                ];
            }

            foreach ($list as $row) {
                $stock = isset($stockMap[$row['id']]) ? $stockMap[$row['id']] : ['total_stock' => 0, 'min_stock' => 0, 'sku_count' => 0];
                $row->setAttr('total_stock', $stock['total_stock']);
                $row->setAttr('min_stock', $stock['min_stock']);
                $row->setAttr('sku_count', $stock['sku_count']);
                $row->setAttr('stock_warning', $stock['sku_count'] > 0 && $stock['min_stock'] <= 10 ? 1 : 0);
                $row->getRelation('category')->visible(['name']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    protected function checkDeleteAllowed($goodsId)
    {
        $hasOrderItem = Db::name('yp_order_item')->where('goods_id', $goodsId)->count();
        if ($hasOrderItem) {
            throw new Exception('该商品已有订单关联，不能硬删除，请使用下架或归档');
        }
        $hasComment = Db::name('comment')->where('goods_id', $goodsId)->count();
        if ($hasComment) {
            throw new Exception('该商品已有评论关联，不能硬删除，请使用下架或归档');
        }
        $hasCollect = Db::name('yp_goods_collect')->where('goods_id', $goodsId)->count();
        if ($hasCollect) {
            throw new Exception('该商品已有收藏关联，不能硬删除，请使用下架或归档');
        }
        $hasCart = Db::name('yp_cart')->where('goods_id', $goodsId)->count();
        if ($hasCart) {
            throw new Exception('该商品已有购物车关联，不能硬删除，请使用下架或归档');
        }
        $customizeRows = Db::name('customize')->field('id,data')->select();
        foreach ($customizeRows as $row) {
            if ($this->jsonContainsGoodsId($row['data'], $goodsId)) {
                throw new Exception('该商品已被热门配方引用，不能硬删除，请使用下架或归档');
            }
        }
        $recipeRows = Db::name('yp_user_recipe')->field('id,recipe_data')->select();
        foreach ($recipeRows as $row) {
            if ($this->jsonContainsGoodsId($row['recipe_data'], $goodsId)) {
                throw new Exception('该商品已被用户配方引用，不能硬删除，请使用下架或归档');
            }
        }
    }

    protected function jsonContainsGoodsId($json, $goodsId)
    {
        $data = json_decode($json, true);
        if (!$data) {
            return false;
        }
        return $this->arrayContainsGoodsId($data, (int)$goodsId);
    }

    protected function arrayContainsGoodsId($data, $goodsId)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'goods_id'], true) && (int)$value === $goodsId) {
                return true;
            }
            if (is_array($value) && $this->arrayContainsGoodsId($value, $goodsId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 批量调整商城渠道
     */
    public function changeStatus()
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $this->request->post('ids');
        $status = $this->request->post('status/d');
        if (!$ids || !in_array($status, [1, 2])) {
            $this->error(__("Invalid parameters"));
        }
        $isShopSale = $status == 1 ? 1 : 0;
        $count = $this->model->where('id', 'in', $ids)->update([
            'is_shop_sale' => $isShopSale,
            'status' => '1',
        ]);
        if ($count !== false) {
            $this->success($status == 1 ? '上架成功' : '下架成功');
        }
        $this->error('操作失败');
    }


    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $sku = $this->request->post("sku/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $params = $this->normalizeChannelParams($params);
                $this->validateImagesByChannel($params);
                $this->validateCustomPriceByChannel($params);
                if (!$params['is_stock']) {
                    // 单规格，price 必须是数字
                    if (!preg_match('/^[0-9]+(.[0-9]{1,8})?$/', $params['money'])) {
                        $this->error("请填写正确的价格");
                    }
                }
                if(!$params['category_id']){
                    $this->error("请选择商品分类");
                }
                if ((int)$params['is_stock'] === 1) {
                    $params['money'] = $this->getMinSkuMoneyFromPost($sku);
                }
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();
                try {
                    $result = $this->model->validateFailException(true)
                        ->validate('\app\admin\validate\yp\Goods.add')
                        ->allowField(true)->save($params);
                    if ($result) {
                        $this->editSku($this->model, $sku);
                        Db::commit();
                    }
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success("添加成功");
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if (!$ids) {
            $ids = $this->request->get('id');
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $sku = $this->request->post("sku/a");
            if ($params) {
                $oldIsStock = (int)$row['is_stock'];
                $params = $this->preExcludeFields($params);
                $params = $this->normalizeChannelParams($params);
                $this->validateImagesByChannel($params);
                $this->validateCustomPriceByChannel($params);
                if(!$params['category_id']){
                    $this->error("请选择商品分类");
                }
                if ((int)$params['is_stock'] === 1) {
                    $params['money'] = $this->getMinSkuMoneyFromPost($sku);
                }
                $result = false;
                Db::startTrans();
                try {
                    $result = $row->validateFailException(true)->validate('\app\admin\validate\yp\Goods.edit')->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->editSku($row, $sku, 'edit', $oldIsStock);
                        Db::commit();
                    }

                }catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success("编辑成功");
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        $skuList = \app\admin\model\yp\Sku::all(['pid' => 0, 'goods_id' => $ids]);
        if ($skuList) {
            foreach ($skuList as &$s) {
                $s->children = \app\admin\model\yp\Sku::all(['pid' => $s->id, 'goods_id' => $ids]);
            }
        }
        $this->assignconfig('skuList', $skuList);
        $this->assignconfig('id', $row->id);
        $skuPrice = \app\admin\model\yp\SkuPrice::all(['goods_id' => $ids]);
        $this->assignconfig('skuPrice', $skuPrice);
        return $this->view->fetch();
    }

    /**
     * 查看详情
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->append(['category_ids_arr']);
        $result = [];
        if ($row['is_stock']) {
            $skuList = \app\admin\model\yp\Sku::all(['pid' => 0, 'goods_id' => $ids]);
            if ($skuList) {
                foreach ($skuList as &$s) {
                    $s->children = \app\admin\model\yp\Sku::all(['pid' => $s->id, 'goods_id' => $ids]);
                }
            }
            $result['skuList'] = $skuList;

            $skuPrice = \app\admin\model\yp\SkuPrice::all(['goods_id' => $ids]);
            $result['skuPrice'] = $skuPrice;
        } else {
            // 将单规格的部分数据直接放到 row 上
            $goodsSkuPrice = \app\admin\model\yp\SkuPrice::where('goods_id', $ids)->order('id', 'asc')->find();
            $row['stock'] = $goodsSkuPrice['stock'] ?? 0;
            $row['sn'] = $goodsSkuPrice['sn'] ?? "";
            $row['weight'] = $goodsSkuPrice['weight'] ?? 0;
            $result['skuList'] = [];
            $result['skuPrice'] = [];
        }
        $result['detail'] = $row;

        $this->success('获取成功', null, $result);
    }

    public function select(){
        if($this->request->isAjax()){
            return $this->index();
        }
        return $this->fetch();
    }


    protected function editSku($goods, $sku, $type = 'add', $oldIsStock = null)
    {

        if ($goods['is_stock']) {
            // 多规格
            if ($type === 'edit' && $oldIsStock !== null && (int)$oldIsStock === 0) {
                SkuPrice::where(['goods_id' => $goods['id']])->delete();
            }
            $this->editMultSku($goods, $sku, $type);
        } else {
            $this->editSimSku($goods, $sku, $type);
        }

    }

    protected function normalizeChannelParams($params)
    {
        if (!isset($params['is_shop_sale'])) {
            $params['is_shop_sale'] = isset($params['is_customized']) && (int)$params['is_customized'] === 1 ? 0 : 1;
        }
        $params['status'] = 1;
        if (!isset($params['shop_name']) || trim($params['shop_name']) === '') {
            $params['shop_name'] = $params['name'] ?? '';
        }
        if (!isset($params['custom_name']) || trim($params['custom_name']) === '') {
            $params['custom_name'] = $params['name'] ?? '';
        }
        $params['custom_status'] = isset($params['is_customized']) && (int)$params['is_customized'] === 1 ? 1 : 2;
        if (!isset($params['allow_ai_recommend'])) {
            $params['allow_ai_recommend'] = 1;
        }
        if (!isset($params['allow_manual_select'])) {
            $params['allow_manual_select'] = 1;
        }
        $params['custom_pricing_method'] = 'weight';
        if (isset($params['erp_goods_no'])) {
            $params['erp_goods_no'] = trim($params['erp_goods_no']);
        }
        return $params;
    }

    protected function validateImagesByChannel($params)
    {
        $isShopSale = isset($params['is_shop_sale']) ? (int)$params['is_shop_sale'] === 1 : true;
        if ($isShopSale && (!isset($params['images']) || trim($params['images']) === '')) {
            $this->error('至少上传一张轮播图');
        }
    }

    protected function validateCustomPriceByChannel($params)
    {
        $isCustomized = isset($params['is_customized']) ? (int)$params['is_customized'] === 1 : false;
        $price = isset($params['customized_price']) ? (float)$params['customized_price'] : 0;
        if ($isCustomized && $price <= 0) {
            $this->error('开启定制可选后，请填写大于 0 的定制价格');
        }
    }

    protected function getMinSkuMoneyFromPost($sku)
    {
        $skuPrice = isset($sku['priceData']) ? json_decode($sku['priceData'], true) : [];
        $skuPrice = is_array($skuPrice) ? $skuPrice : [];
        $minMoney = null;
        foreach ($skuPrice as $price) {
            $status = isset($price['status']) ? $price['status'] : 'up';
            $money = isset($price['money']) ? (float)$price['money'] : 0;
            if ($status !== 'up' || $money <= 0) {
                continue;
            }
            $minMoney = $minMoney === null ? $money : min($minMoney, $money);
        }
        return $minMoney === null ? 0 : $minMoney;
    }


    /**
     * 添加编辑单规格
     */
    protected function editSimSku($goods, $sku, $type = 'add')
    {
        $params = $this->request->post("row/a");
        $data = [
            "goods_id" => $goods['id'],
            "stock" => $params['stock'] ?? 0,
            "sn" => $params['sn'] ?? "",
            "weight" => $params['weight'] ? intval($params['weight']) : 0,
            "money" => $params['money'] ?? 0,
            "status" => 'up',
            'erp_spec_no' => isset($params['erp_spec_no']) ? trim($params['erp_spec_no']) : ''
        ];
        if ($type == 'add') {
            $goodsSkuPrice = new \app\admin\model\yp\SkuPrice();
        } else {
            \app\admin\model\yp\Sku::where(['goods_id' => $goods['id']])->delete();
            $oldSkuPriceIds = \app\admin\model\yp\SkuPrice::where(['goods_id' => $goods['id']])->order('id', 'asc')->column('id');
            if (count($oldSkuPriceIds) > 1) {
                \app\admin\model\yp\SkuPrice::where('id', 'in', array_slice($oldSkuPriceIds, 1))->delete();
            }
            // 查询
            $goodsSkuPrice = \app\admin\model\yp\SkuPrice::where('goods_id', $goods['id'])->order('id', 'asc')->find();
            if (!$goodsSkuPrice) {
                $goodsSkuPrice = new \app\admin\model\yp\SkuPrice();
            }
        }
        $goodsSkuPrice->save($data);
    }


    /**
     * 添加编辑多规格
     */
    protected function editMultSku($goods, $sku, $type = 'add')
    {

        $skuList = json_decode($sku['listData'], true);
        $skuPrice = json_decode($sku['priceData'], true);
        $skuList = is_array($skuList) ? $skuList : [];
        $skuPrice = is_array($skuPrice) ? $skuPrice : [];
        if (count($skuList) < 1) {
            throw new Exception('请填写规格列表');
        }
        foreach ($skuList as $sku) {
            if (count($sku['children']) <= 0) {
                throw new Exception('主规格至少要有一个子规格');
            }

            // 验证子规格不能为空
            foreach ($sku['children'] as $child) {
                if (!isset($child['name']) || empty(trim($child['name']))) {
                    throw new Exception('子规格不能为空');
                }
            }
        }

        if (count($skuPrice) < 1) {
            throw new Exception('请填写规格价格');
        }

        foreach ($skuPrice as &$price) {
            if (empty($price['money']) || $price['money'] == 0) {
                throw new Exception('请填写规格价格');
            }
            if ($price['stock'] === '') {
                throw new Exception('请填写规格库存');
            }
            $price['erp_spec_no'] = isset($price['erp_spec_no']) ? trim($price['erp_spec_no']) : '';
            if ($price['erp_spec_no'] === '') {
                throw new Exception('请填写商家编码');
            }
            if (empty($price['weight'])) {
                $price['weight'] = 0;
            }
        }
        // 编辑保存规格项
        $allChildrenSku = $this->saveSkuList($goods, $skuList, $type);

        if ($type == 'add') {
            // 创建新产品，添加规格列表和规格价格
            foreach ($skuPrice as &$k3) {
                $k3['goods_sku_ids'] = $this->checkRealIds($k3['goods_sku_temp_ids'], $allChildrenSku);
                $k3['goods_id'] = $goods->id;
                $k3['goods_sku_text'] = implode(',', $k3['goods_sku_text']);
                $k3['weight'] = intval($k3['weight']);
                $k3['erp_spec_no'] = trim($k3['erp_spec_no']);
                $k3['createtime'] = time();
                $k3['updatetime'] = time();
                unset($k3['id']);
                unset($k3['temp_id']);      // 前端临时 id
                unset($k3['goods_sku_temp_ids']);       // 前端临时规格 id,查找真实 id 用
            }
            (new \app\admin\model\yp\SkuPrice)->allowField(true)->saveAll($skuPrice);
        } else {
            // 编辑旧商品，先删除老的不用的 skuPrice
            $oldSkuPriceIds = array_column($skuPrice, 'id');
            // 删除当前商品老的除了在基础上修改的skuPrice
            \app\admin\model\yp\SkuPrice::where('goods_id', $goods->id)
                ->where('id', 'not in', $oldSkuPriceIds)->delete();
            foreach ($skuPrice as &$k3) {
                $data['goods_sku_ids'] = $this->checkRealIds($k3['goods_sku_temp_ids'], $allChildrenSku);
                $data['goods_id'] = $goods->id;
                $data['goods_sku_text'] = implode(',', $k3['goods_sku_text']);
                $data['image'] = $k3['image'];
                $data['stock'] = $k3['stock'];
                $data['sn'] = $k3['sn'];
                $data['weight'] = intval($k3['weight']);
                $data['money'] = $k3['money'];
                $data['status'] = $k3['status'];
                $data['createtime'] = time();
                $data['updatetime'] = time();
                $data['erp_spec_no'] = trim($k3['erp_spec_no']);
                if ($k3['id']) {
                    // 编辑
                    $goodsSkuPrice = \app\admin\model\yp\SkuPrice::get($k3['id']);
                } else {
                    // 新增数据
                    $goodsSkuPrice = new \app\admin\model\yp\SkuPrice();
                }
                if ($goodsSkuPrice) {
                    $goodsSkuPrice->allowField(true)->save($data);
                }
            }
        }
    }


    // 根据前端临时 temp_id 获取真实的数据库 id
    private function checkRealIds($newGoodsSkuIds, $allChildrenSku)
    {
        $newIdsArray = [];
        foreach ($newGoodsSkuIds as $id) {
            $newIdsArray[] = $allChildrenSku[$id];
        }
        return implode(',', $newIdsArray);

    }


    // 差异更新 规格规格项（多的删除，少的添加）
    private function saveSkuList($goods, $skuList, $type = 'add')
    {
        $allChildrenSku = [];

        if ($type == 'edit') {
            // 删除无用老规格
            // 拿出需要更新的老规格
            $oldSkuIds = [];
            foreach ($skuList as $sku) {
                $oldSkuIds[] = $sku['id'];
                $childSkuIds = [];
                if ($sku['children']) {
                    // 子项 id
                    $childSkuIds = array_column($sku['children'], 'id');
                }

                $oldSkuIds = array_merge($oldSkuIds, $childSkuIds);
                $oldSkuIds = array_unique($oldSkuIds);
            }
            // 删除老的除了在基础上修改的规格项
            \app\admin\model\yp\Sku::where('goods_id', $goods->id)->where('id', 'not in', $oldSkuIds)->delete();
        }

        foreach ($skuList as $s1 => &$k1) {
            //添加主规格
            if ($k1['id']) {
                // 编辑
                \app\admin\model\yp\Sku::where('id', $k1['id'])->update([
                    'name' => $k1['name'],
                ]);

                $skuId[$s1] = $k1['id'];
            } else {
                $skuId[$s1] = \app\admin\model\yp\Sku::insertGetId([
                    'name' => $k1['name'],
                    'pid' => 0,
                    'goods_id' => $goods->id
                ]);

            }
            $k1['id'] = $skuId[$s1];
            foreach ($k1['children'] as $s2 => &$k2) {
                if ($k2['id']) {
                    // 编辑
                    \app\admin\model\yp\Sku::where('id', $k2['id'])->update([
                        'name' => $k2['name'],
                    ]);
                    $skuChildrenId[$s1][$s2] = $k2['id'];
                } else {
                    $skuChildrenId[$s1][$s2] = \app\admin\model\yp\Sku::insertGetId([
                        'name' => $k2['name'],
                        'pid' => $k1['id'],
                        'goods_id' => $goods->id
                    ]);

                }

                $allChildrenSku[$k2['temp_id']] = $skuChildrenId[$s1][$s2];
                $k2['id'] = $skuChildrenId[$s1][$s2];
                $k2['pid'] = $k1['id'];
            }
        }

        return $allChildrenSku;
    }
}
