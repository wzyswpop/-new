<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use app\admin\model\yp\Kuaidi;
use app\admin\model\yp\KuaidiSub;
use think\Db;
use think\Exception;
use app\admin\library\Export;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\Loader;
use app\admin\model\User;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\yp\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\Order;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->assignconfig('SCRIPT_NAME',$_SERVER['SCRIPT_NAME']);
    }

    private function getCustomRatio($item)
    {
        if (empty($item['json'])) {
            return '';
        }
        $data = json_decode($item['json'], true);
        return isset($data['ratio']) && $data['ratio'] !== '' ? $data['ratio'] : '';
    }

    private function getCustomJson($item)
    {
        if (empty($item['json'])) {
            return [];
        }
        $data = json_decode($item['json'], true);
        return is_array($data) ? $data : [];
    }

    private function normalizeCustomItems($items)
    {
        foreach ($items as $index => $item) {
            $items[$index]['stock_title'] = '';
            $json = $this->getCustomJson($item);
            $items[$index]['ratio'] = isset($json['ratio']) && $json['ratio'] !== '' ? $json['ratio'] : '';
        }
        return $items;
    }

    private function normalizeCustomOrderMeta($row)
    {
        $recipeName = '';
        $recipeTotalWeight = 0;
        $totalWeight = 0;

        foreach ($row['item'] as $item) {
            if (!empty($item['weight'])) {
                $totalWeight += (int)$item['weight'];
            }
            $json = $this->getCustomJson($item);
            if ($recipeName === '' && !empty($json['recipe_name'])) {
                $recipeName = $json['recipe_name'];
            }
            if ($recipeTotalWeight <= 0 && !empty($json['recipe_total_weight'])) {
                $recipeTotalWeight = (int)$json['recipe_total_weight'];
            }
            if ($recipeTotalWeight <= 0 && !empty($json['total_weight'])) {
                $recipeTotalWeight = (int)$json['total_weight'];
            }
        }

        $row['recipe_name'] = $recipeName;
        $row['recipe_total_weight'] = $recipeTotalWeight > 0 ? $recipeTotalWeight : $totalWeight;
        return $row;
    }

    private function getRoastLossRate($baking)
    {
        $baking = trim((string)$baking);
        if ($baking === '') {
            return ['rate' => 0.13, 'label' => '13%', 'is_default' => true];
        }
        if (strpos($baking, '中深') !== false) {
            return ['rate' => 0.15, 'label' => '15%', 'is_default' => false];
        }
        if (strpos($baking, '深') !== false) {
            return ['rate' => 0.17, 'label' => '17%', 'is_default' => false];
        }
        if (strpos($baking, '中浅') !== false || strpos($baking, '中烘') !== false || strpos($baking, '中') !== false) {
            return ['rate' => 0.13, 'label' => '13%', 'is_default' => false];
        }
        if (strpos($baking, '浅') !== false) {
            return ['rate' => 0.11, 'label' => '11%', 'is_default' => false];
        }
        return ['rate' => 0.13, 'label' => '13%', 'is_default' => true];
    }

    private function normalizeWeight($weight)
    {
        return max(0, (int)round((float)$weight));
    }

    private function allocateCustomWeights($totalWeight, $items)
    {
        $totalWeight = $this->normalizeWeight($totalWeight);
        $allocations = [];
        $remainders = [];
        $allocatedWeight = 0;

        foreach ($items as $index => $item) {
            $ratio = isset($item['ratio']) ? (float)$item['ratio'] : 0;
            $exactWeight = $totalWeight * $ratio / 100;
            $baseWeight = (int)floor($exactWeight);
            $allocations[$index] = $baseWeight;
            $allocatedWeight += $baseWeight;
            $remainders[] = [
                'index' => $index,
                'remainder' => $exactWeight - $baseWeight
            ];
        }

        $remainingWeight = $totalWeight - $allocatedWeight;
        if ($remainingWeight > 0 && $remainders) {
            usort($remainders, function ($left, $right) {
                if ($left['remainder'] == $right['remainder']) {
                    return $left['index'] <=> $right['index'];
                }
                return $left['remainder'] < $right['remainder'] ? 1 : -1;
            });

            $remainderCount = count($remainders);
            for ($i = 0; $i < $remainingWeight; $i++) {
                $target = $remainders[$i % $remainderCount]['index'];
                $allocations[$target]++;
            }
        }

        ksort($allocations);
        return $allocations;
    }

    private function canRebuildCustomWeights($items, $totalWeight)
    {
        if ($totalWeight <= 0 || count($items) <= 1) {
            return false;
        }

        $totalRatio = 0;
        foreach ($items as $item) {
            if (!isset($item['ratio']) || $item['ratio'] === '' || !is_numeric($item['ratio'])) {
                return false;
            }
            $totalRatio += (float)$item['ratio'];
        }

        return abs($totalRatio - 100) < 0.0001;
    }

    private function formatProductionWeight($weight)
    {
        $weight = $this->normalizeWeight($weight);
        if ($weight >= 1000 && $weight % 1000 === 0) {
            return ($weight / 1000) . 'kg';
        }
        if ($weight >= 1000) {
            return rtrim(rtrim(number_format($weight / 1000, 2, '.', ''), '0'), '.') . 'kg';
        }
        return $weight . 'g';
    }

    private function formatProductionSpec($weight)
    {
        $weight = $this->normalizeWeight($weight);
        if ($weight > 1000 && $weight % 1000 === 0) {
            return '1kg*' . ($weight / 1000) . '包';
        }
        return $this->formatProductionWeight($weight) . '*1包';
    }

    private function formatProductionRatio($ratio)
    {
        if ($ratio === '') {
            return '';
        }
        if (is_numeric($ratio)) {
            return rtrim(rtrim(number_format((float)$ratio, 2, '.', ''), '0'), '.');
        }
        return (string)$ratio;
    }

    private function buildProductionCopyText($row, $items, $totalRoastedWeight)
    {
        if (!$items) {
            return '';
        }

        $address = (isset($row['province_name']) ? $row['province_name'] : '')
            . (isset($row['city_name']) ? $row['city_name'] : '')
            . (isset($row['county_name']) ? $row['county_name'] : '')
            . (isset($row['address']) ? $row['address'] : '');
        $recipientParts = [
            isset($row['name']) ? $row['name'] : '',
            isset($row['phone']) ? $row['phone'] : '',
            $address
        ];
        $recipientLine = implode('，', array_filter($recipientParts, function ($item) {
            return $item !== '';
        }));

        $formulaParts = [];
        foreach ($items as $item) {
            $ratio = $item['ratio'];
            if ($ratio === '' && $totalRoastedWeight > 0) {
                $ratio = round($item['roasted_weight'] / $totalRoastedWeight * 100);
            }
            $ratio = $this->formatProductionRatio($ratio);
            $formulaParts[] = ($ratio !== '' ? $ratio . '%' : '') . $item['goods_title'];
        }

        $baking = '';
        foreach ($items as $item) {
            if ($item['baking'] !== '') {
                $baking = $item['baking'];
                break;
            }
        }

        $lines = [];
        if ($recipientLine !== '') {
            $lines[] = $recipientLine;
        }
        $lines[] = '名称：定制拼配（' . (isset($row['name']) ? $row['name'] : '') . '）';
        $lines[] = '配方：' . implode('+', $formulaParts);
        $lines[] = '烘焙度：' . ($baking !== '' ? $baking : '中度烘焙');
        $lines[] = '规格：' . $this->formatProductionSpec($totalRoastedWeight);

        return implode("\n", $lines);
    }

    private function buildProductionData($row)
    {
        $items = [];
        $totalRoastedWeight = 0;
        $totalGreenWeight = 0;
        $targetRoastedWeight = isset($row['recipe_total_weight']) ? $this->normalizeWeight($row['recipe_total_weight']) : 0;
        $rebuiltWeights = $this->canRebuildCustomWeights($row['item'], $targetRoastedWeight)
            ? $this->allocateCustomWeights($targetRoastedWeight, $row['item'])
            : [];

        foreach ($row['item'] as $index => $item) {
            $roastedWeight = isset($rebuiltWeights[$index]) ? $rebuiltWeights[$index] : $this->normalizeWeight(isset($item['weight']) ? $item['weight'] : 0);
            if ($roastedWeight <= 0) {
                continue;
            }
            $baking = isset($item['baking']) ? $item['baking'] : '';
            $loss = $this->getRoastLossRate($baking);
            $greenWeight = (int)ceil($roastedWeight / (1 - $loss['rate']));
            $items[] = [
                'goods_title' => isset($item['goods_title']) ? $item['goods_title'] : '',
                'baking' => $baking !== '' ? $baking : '中度烘焙',
                'roasted_weight' => $roastedWeight,
                'loss_rate' => $loss['label'],
                'loss_default' => $loss['is_default'],
                'green_weight' => $greenWeight,
                'ratio' => isset($item['ratio']) ? $item['ratio'] : ''
            ];
            $totalRoastedWeight += $roastedWeight;
            $totalGreenWeight += $greenWeight;
        }

        $lines = [];
        if ($items) {
            $lines[] = '生产单：订单 ' . $row['order_no'];
            if (!empty($row['recipe_name'])) {
                $lines[] = '配方名：' . $row['recipe_name'];
            }
            $lines[] = '配方总熟豆重量：' . $totalRoastedWeight . 'g';
            $lines[] = '';
            foreach ($items as $index => $item) {
                $bakingText = $item['baking'];
                if ($item['loss_default']) {
                    $bakingText .= '（按默认损水率）';
                }
                $lines[] = ($index + 1) . '. ' . $item['goods_title'];
                $lines[] = '   烘焙：' . $bakingText;
                if ($item['ratio'] !== '') {
                    $lines[] = '   比例：' . $item['ratio'] . '%';
                }
                $lines[] = '   熟豆：' . $item['roasted_weight'] . 'g';
                $lines[] = '   损水率：' . $item['loss_rate'];
                $lines[] = '   生豆：' . $item['green_weight'] . 'g';
                $lines[] = '';
            }
            $lines[] = '合计熟豆：' . $totalRoastedWeight . 'g';
            $lines[] = '合计生豆：' . $totalGreenWeight . 'g';
        }

        $row['production_items'] = $items;
        $row['production_total_roasted_weight'] = $totalRoastedWeight;
        $row['production_total_green_weight'] = $totalGreenWeight;
        $row['production_detail_text'] = implode("\n", $lines);
        $row['production_text'] = $this->buildProductionCopyText($row, $items, $totalRoastedWeight);
        $row['has_production_data'] = !empty($items);
        return $row;
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
                    ->with(['user','item'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                switch ($row['payment']){
                    case 'wechat':
                        $row['payment_name'] = '微信';
                        break;
                    case 'balance':
                        $row['payment_name'] = '余额';
                        break;
                }
                switch ($row['order_type']){
                    case '0':
                        $row['order_type_name'] = '普通订单';
                        break;
                    case '1':
                        $row['order_type_name'] = '定制订单';
                        $row['item'] = $this->normalizeCustomItems($row['item']);
                        $row = $this->normalizeCustomOrderMeta($row);
                        $row = $this->buildProductionData($row);
                        break;
                }
                
                $row->getRelation('user')->visible(['nickname']);
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 订单详情
     */
    public function detail(){
        $id = $this->request->param('id');
        $order_info = $this->model->with(['user','item'])->where(['order.id' => $id])->find();
        if(!$order_info){
            $this->error('订单不存在');
        }
        $order_info = $order_info->toArray();
        if($order_info['order_type'] == 1){
            $order_info['item'] = $this->normalizeCustomItems($order_info['item']);
            $order_info = $this->normalizeCustomOrderMeta($order_info);
            $order_info = $this->buildProductionData($order_info);
        }
        $this->assign('row',$order_info);
        return $this->fetch();
    }

    /**
     * 待支付订单改价
     */
    public function changeprice($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($row['status'] != 1) {
            $this->error('只有待支付订单可以改价');
        }
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (!$params || !isset($params['order_money'])) {
                $this->error('请输入新的订单金额');
            }
            $order_money = trim($params['order_money']);
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $order_money)) {
                $this->error('订单金额格式不正确，最多保留2位小数');
            }
            if (bccomp($order_money, '0', 2) < 0) {
                $this->error('订单金额不能小于0');
            }
            $old_money = $row['order_money'];
            $new_money = bcadd($order_money, '0', 2);
            $user_money = User::where(['id' => $row['user_id']])->value('money');
            $user_money = $user_money === null ? '0.00' : $user_money;
            $row->order_money = $new_money;
            if (bccomp($new_money, $user_money, 2) > 0) {
                $row->payment = 'wechat';
                $row->cash_money = $user_money > 0 ? bcsub($new_money, $user_money, 2) : 0;
            } else {
                $row->payment = 'balance';
                $row->cash_money = 0;
            }
            $row->save();
            if (!$this->request->isAjax()) {
                return '<!doctype html><html><head><meta charset="utf-8"></head><body><script>(function(){var p=parent||window;if(p.Toastr){p.Toastr.success("改价成功");}if(p.$){p.$(".btn-refresh").trigger("click");}if(p.Layer){var index=p.Layer.getFrameIndex(window.name);p.Layer.close(index);}})();</script></body></html>';
            }
            $this->success('改价成功', null, [
                'old_money' => $old_money,
                'order_money' => $row->order_money
            ]);
        }
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }


    /**
     * 发货
     */
    public function delivery(){
        if($this->request->isPost()){
            $data = $this->request->post();
            $express_code = $data['express_code'];
            $express_info = Kuaidi::where(['code' => $express_code])->find();
            $delivertime = time();
            $kuaidi_key = getValues('kuaidi_key');
            $order = [];
            $kaidi = new \app\common\library\Kuaidi($kuaidi_key,$this->request->domain().'/api/yp.kuaidi/callBack');
            foreach ($data['order']['id'] as $k=>$v){
                if(!$v || !$data['order']['express_no'][$k]){
                    $this->error('快递单号不能为空');
                }
                $express_no = $data['order']['express_no'][$k];
                $res = [
                    'id' => $v,
                    'status' => '3',
                    'express_name' => $express_info['name'],
                    'express_no' => $express_no,
                    'delivertime' => $delivertime
                ];
                $order[] = $res;
//                $is_express = KuaidiSub::where(['express_no' => $express_no])->find();
//                if(!$is_express && $kuaidi_key){
//                    $return = $kaidi->subScribe($express_code,$express_no);
//                    if ($return['returnCode'] != 200) {
//                        $this->error('快递订阅接口异常-'.$return['message']);
//                    }
//                    KuaidiSub::insert([
//                        'sign' => $kaidi->sign($express_no),
//                        'express_no' => $express_no,
//                        'returncode' => $return['returnCode'],
//                        'message' => $return['message']
//                    ]);
//                }
            }
            $res = $this->model->saveAll($order);
            if(!$res){
                $this->error('发货失败');
            }
            $this->success('发货成功');
        }else{
            $ids = $this->request->param('ids');
            $list = $this->model->where(['id' => ['in',$ids],'status' => '2'])->select();
            $kuaidi = Kuaidi::all();
            $this->assign('kuaidi',$kuaidi);
            $this->assign('lists',$list);
            return $this->fetch();
        }
    }


    /**
     * 快递查询
     */
    public function relative($id = null)
    {
        $row = $this->model->get($id);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $data = model('app\admin\model\yp\KuaidiSub')
            ->where(['express_no' => $row['express_no']])
            ->find();
        $data = json_decode($data['data'], true);
        $list = [];
        $week = array(
            "0"=>"星期日",
            "1"=>"星期一",
            "2"=>"星期二",
            "3"=>"星期三",
            "4"=>"星期四",
            "5"=>"星期五",
            "6"=>"星期六"
        );
        if($data){
            foreach($data as $vo){
                $list[] = [
                    'time' => strtotime($vo['time']),
                    'status' => in_array('status', $vo) ? $vo['status'] : '在途',  // 1.0.6升级
                    'context' => $vo['context'],
                    'week' => $week[date('w', strtotime($vo['time']))]
                ];
            }
        }
        $this->view->assign("week", $week);
        $this->view->assign("list", $list);
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    protected function buildparams($f = [],$o = [])
    {
        $searchfields = $this->searchFields;
        $search = $this->request->get("search", '');
        $filter = $this->request->get("filter", '');
        $op = $this->request->get("op", '', 'trim');
        $sort = $this->request->get("sort", !empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
        $order = $this->request->get("order", "DESC");
        $offset = $this->request->get("offset/d", 0);
        $limit = $this->request->get("limit/d", 20);
        //新增自动计算页码
        $page = $limit ? intval($offset / $limit) + 1 : 1;
        if ($this->request->has("page")) {
            $page = $this->request->get("page/d", 1);
        }
        $this->request->get([config('paginate.var_page') => $page]);
        $filter = (array)json_decode($filter, true);
        $op = (array)json_decode($op, true);
        $filter = $filter ? $filter : [];
        if($f && $o){
            $filter = array_merge($f,$filter);
            $op = array_merge($o,$op);
        }
        $where = [];
        $alias = [];
        $bind = [];
        $name = '';
        $aliasName = '';
        if (!empty($this->model) && $this->relationSearch) {
            $name = $this->model->getTable();
            $alias[$name] = Loader::parseName(basename(str_replace('\\', '/', get_class($this->model))));
            $aliasName = $alias[$name] . '.';
        }
        $sortArr = explode(',', $sort);
        foreach ($sortArr as $index => & $item) {
            $item = stripos($item, ".") === false ? $aliasName . trim($item) : $item;
        }
        unset($item);
        $sort = implode(',', $sortArr);
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, ".") === false ? $aliasName . $v : $v;
            }
            unset($v);
            $where[] = [implode("|", $searcharr), "LIKE", "%{$search}%"];
        }
        $index = 0;
        foreach ($filter as $k => $v) {
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $k)) {
                continue;
            }
            $sym = isset($op[$k]) ? $op[$k] : '=';
            if (stripos($k, ".") === false) {
                $k = $aliasName . $k;
            }
            $v = !is_array($v) ? trim($v) : $v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            //null和空字符串特殊处理
            if (!is_array($v)) {
                if (in_array(strtoupper($v), ['NULL', 'NOT NULL'])) {
                    $sym = strtoupper($v);
                }
                if (in_array($v, ['""', "''"])) {
                    $v = '';
                    $sym = '=';
                }
            }

            switch ($sym) {
                case '=':
                case '<>':
                    $where[] = [$k, $sym, (string)$v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[] = [$k, trim(str_replace('%...%', '', $sym)), "%{$v}%"];
                    break;
                case '>':
                case '>=':
                case '<':
                case '<=':
                    $where[] = [$k, $sym, intval($v)];
                    break;
                case 'FINDIN':
                case 'FINDINSET':
                case 'FIND_IN_SET':
                    $v = is_array($v) ? $v : explode(',', str_replace(' ', ',', $v));
                    $findArr = array_values($v);
                    foreach ($findArr as $idx => $item) {
                        $bindName = "item_" . $index . "_" . $idx;
                        $bind[$bindName] = $item;
                        $where[] = "FIND_IN_SET(:{$bindName}, `" . str_replace('.', '`.`', $k) . "`)";
                    }
                    break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[] = [$k, str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr, function ($v) {
                            return $v != '' && $v !== false && $v !== null;
                        })) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, $sym, $arr];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || !array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'RANGE' ? '<=' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'RANGE' ? '>=' : '<';
                        $arr = $arr[0];
                    }
                    $tableArr = explode('.', $k);
                    if (count($tableArr) > 1 && $tableArr[0] != $name && !in_array($tableArr[0], $alias) && !empty($this->model)) {
                        //修复关联模型下时间无法搜索的BUG
                        $relation = Loader::parseName($tableArr[0], 1, false);
                        $alias[$this->model->$relation()->getTable()] = $tableArr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $sym) . ' TIME', $arr];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                default:
                    break;
            }
            $index++;
        }
        if (!empty($this->model)) {
            $this->model->alias($alias);
        }
        $model = $this->model;
        $where = function ($query) use ($where, $alias, $bind, &$model) {
            if (!empty($model)) {
                $model->alias($alias);
                $model->bind($bind);
            }
            foreach ($where as $k => $v) {
                if (is_array($v)) {
                    call_user_func_array([$query, 'where'], $v);
                } else {
                    $query->where($v);
                }
            }
        };
        return [$where, $sort, $order, $offset, $limit, $page, $alias, $bind];
    }


    /**
     * 导出表格
     */
    public function export_log()
    {
        set_time_limit(0);
        $f = json_decode($this->request->param('f'),true);
        $o = json_decode($this->request->param('o'),true);
        if(isset($f['status'])){
            $table_name = $this->model->getTable();
            $f[$table_name.'.status'] = $f['status'];
            $o[$table_name.'.status'] = $o['status'];
            unset($f['status'],$o['status']);
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams($f,$o);
        $order = $this->model
            ->with(['user','item'])
            ->where($where)
            ->order($sort, $order)
            ->limit(5000)
            ->select();
        $expCellName = [
            'order_no' => '订单号',
            'order_type' => '订单类型',
            'recipe_name' => '客人配方名',
            'recipe_total_weight' => '配方总重量',
            'payment' => '支付类型',
            'order_money' => '订单金额',
            'cash_money' => '扣减余额',
            'discount_money' => '积分抵扣金额',
            'goods_num' => '商品总数量',
            'goods_title' => '购买商品',
            'weight' => '商品重量',
            'baking' => '烘培程度',
            'ratio' => '拼配比例',
            'green_weight' => '生豆用量',
            'loss_rate' => '损水率',
            'stock_title' => '商品规格',
            'num' => '数量',
            'money' => '金额',
            'unit_price' => '单价',
            'status' => '订单状态',
            'name' => '收货人',
            'phone' => '手机号',
            'address' => '收货地址',
            'express_name' => '快递名称',
            'express_no' => '快递单号',
            'createtime' => '下单时间',
            'paytime' => '支付时间',
            'delivertime' => '发货时间',
            'confirmtime' => '收货时间'
        ];
        $newList = [];
        foreach ($order as $v) {
            $items = [];
            foreach ($v['item'] as $item) {
                if ($v['order_type'] == 1) {
                    $item['stock_title'] = '';
                    $item['ratio'] = $this->getCustomRatio($item);
                    $loss = $this->getRoastLossRate(isset($item['baking']) ? $item['baking'] : '');
                    $roastedWeight = $this->normalizeWeight(isset($item['weight']) ? $item['weight'] : 0);
                    $item['green_weight'] = $roastedWeight > 0 ? ceil($roastedWeight / (1 - $loss['rate'])) . 'g' : '';
                    $item['loss_rate'] = $loss['label'] . ($loss['is_default'] ? '（默认）' : '');
                }
                $items[] = $item;
            }
            if ($v['order_type'] == 1) {
                $v['item'] = $items;
                $v = $this->normalizeCustomOrderMeta($v);
                $v = $this->buildProductionData($v);
                foreach ($items as $itemIndex => $item) {
                    if (isset($v['production_items'][$itemIndex])) {
                        $items[$itemIndex]['weight'] = $v['production_items'][$itemIndex]['roasted_weight'] . 'g';
                        $items[$itemIndex]['green_weight'] = $v['production_items'][$itemIndex]['green_weight'] . 'g';
                        $items[$itemIndex]['loss_rate'] = $v['production_items'][$itemIndex]['loss_rate'];
                    }
                }
            }
            $newList[] = [
                'order_no' => $v['order_no'],
                'order_type' => $v['order_type'] == 0 ? '普通订单' : '定制订单',
                'recipe_name' => isset($v['recipe_name']) ? $v['recipe_name'] : '',
                'recipe_total_weight' => !empty($v['recipe_name']) && !empty($v['recipe_total_weight']) ? $v['recipe_total_weight'] . 'g' : '',
                'payment' => $v['payment'] == 'wechat' ? '微信' : '余额',
                'order_money' => $v['order_money'],
                'cash_money' => $v['cash_money'],
                'discount_money' => $v['discount_money'],
                'goods_num' => $v['goods_num'],
                'item' => $items,
                'status' => $v['status_text'],
                'name' => $v['name'],
                'phone' => $v['phone'] . ' ',
                'address' => $v['province_name'].$v['city_name'].$v['county_name'].$v['address'],
                'express_name' => $v['express_name'],
                'express_no' => $v['express_no'] . ' ',
                'createtime' => date('Y-m-d H:i:s',$v['createtime']),
                'paytime' => $v['paytime'] ? date('Y-m-d H:i:s',$v['paytime']) : '',
                'delivertime' => $v['delivertime'] ? date('Y-m-d H:i:s',$v['delivertime']) : '',
                'confirmtime' => $v['confirmtime'] ? date('Y-m-d H:i:s',$v['confirmtime']) : ''
            ];
        }
        $no_merge_field = ['goods_title','weight','baking','ratio','green_weight','loss_rate','stock_title','num','money','unit_price'];  //不合并的列
        $this->exportExcel('订单信息', $expCellName, $newList,$no_merge_field);
    }

    protected function exportExcel($expTitle, $expCellName, $expTableData,$no_merge_field = [])
    {
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);
        $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        if (class_exists(FilesystemCachePool::class)) {
            $path = ROOT_PATH . 'runtime' . DS . 'export/';
            @mkdir($path);
            $filesystemAdapter = new Local($path);
            $filesystem        = new Filesystem($filesystemAdapter);
            $pool = new FilesystemCachePool($filesystem);
            \PhpOffice\PhpSpreadsheet\Settings::setCache($pool);
        }
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $spreadsheet->getDefaultStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet = $spreadsheet->getActiveSheet(0);
        $sheet->getStyle('A1:' . $cellName[$cellNum - 1] . '1')->getFont()->setBold(true);
        $i = 0;
        foreach ($expCellName as $key => $cell) {
            $sheet->setCellValue($cellName[$i] . '1', $cell);
            $i++;
        }
        for ($i = 0; $i < $cellNum; $i++) {
            $sheet->getColumnDimension($cellName[$i])->setWidth(30);
        }
        $num = 2;
        $base_cell = 0;
        for ($i = 0; $i < $dataNum; $i++) {
            $j = 0;
            $item_num = count($expTableData[$i]['item']) - 1;
            $is_hebing = [];
            foreach ($expCellName as $key => $cell) {
                $s = ($num + $base_cell);
                $b = (($num+$base_cell) + $item_num);
                if(!in_array($key,$no_merge_field)){
                    if(!isset($is_hebing[$key])){
                        $is_hebing[$key] = 1;
                        if($item_num > 0){
                            $sheet->mergeCells($cellName[$j] . ($num + $base_cell).':'.$cellName[$j] . (($num+$base_cell) + $item_num));
                        }
                    }
                    $sheet->setCellValue($cellName[$j] . ($num + $base_cell), $expTableData[$i][$key]);
                } else{
                    foreach ($expTableData[$i]['item'] as $v){
                        if($s <= $b){
                            if($key == 'stock_title' && !$v[$key]){
                                $sheet->setCellValue($cellName[$j] . $s, '单规格');
                            }else{
                                $sheet->setCellValue($cellName[$j] . $s, $v[$key]);
                            }
                        }
                        $s++;
                    }
                }
                $j++;
            }
            $num += 1;
            $num += $item_num;
        }
        ob_end_clean();
        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $expTitle . '.xlsx"');
        header("Content-Disposition:attachment;filename=$expTitle.xlsx"); //attachment新窗口打印inline本窗口打印
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

}
