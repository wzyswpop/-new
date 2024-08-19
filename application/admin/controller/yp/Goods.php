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

            $list = $this->model
                    ->with(['category'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                $row->getRelation('category')->visible(['name']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
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
                if (!$params['is_stock']) {
                    // 单规格，price 必须是数字
                    if (!preg_match('/^[0-9]+(.[0-9]{1,8})?$/', $params['money'])) {
                        $this->error("请填写正确的价格");
                    }
                }
                if(!$params['category_id']){
                    $this->error("请选择商品分类");
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
                $this->excludeFields = ['is_stock'];
                $params = $this->preExcludeFields($params);
                if(!$params['category_id']){
                    $this->error("请选择商品分类");
                }
                $result = false;
                Db::startTrans();
                try {
                    $result = $row->validateFailException(true)->validate('\app\admin\validate\yp\Goods.edit')->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->editSku($row, $sku, 'edit');
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


    protected function editSku($goods, $sku, $type = 'add')
    {

        if ($goods['is_stock']) {
            // 多规格
            $this->editMultSku($goods, $sku, $type);
        } else {
            $this->editSimSku($goods, $sku, $type);
        }

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
            'erp_spec_no' => $params['erp_spec_no'] ?? ''
        ];
        if ($type == 'add') {
            $goodsSkuPrice = new \app\admin\model\yp\SkuPrice();
        } else {
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
        if (count($skuList) < 1) {
            throw Exception('请填写规格列表');
        }
        foreach ($skuList as $sku) {
            if (count($sku['children']) <= 0) {
                throw Exception('主规格至少要有一个子规格');
            }

            // 验证子规格不能为空
            foreach ($sku['children'] as $child) {
                if (!isset($child['name']) || empty(trim($child['name']))) {
                    throw Exception('子规格不能为空');
                }
            }
        }

        if (count($skuPrice) < 1) {
            throw Exception('请填写规格价格');
        }

        foreach ($skuPrice as &$price) {
            if (empty($price['money']) || $price['money'] == 0) {
                throw Exception('请填写规格价格');
            }
            if ($price['stock'] === '') {
                throw Exception('请填写规格库存');
            }
            if ($price['erp_spec_no'] === '') {
                throw Exception('请填写商家编码');
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
                $k3['erp_spec_no'] = intval($k3['erp_spec_no']);
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
                $data['erp_spec_no'] = $k3['erp_spec_no'];
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
