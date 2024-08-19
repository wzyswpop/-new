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
        $this->assign('row',$order_info);
        return $this->fetch();
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
        $limit = $this->request->get("limit/d", 999999);
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
            ->select();
        $expCellName = [
            'order_no' => '订单号',
            'payment' => '支付类型',
            'order_money' => '订单金额',
            'freight' => '运费',
            'goods_num' => '商品总数量',
            'goods_title' => '购买商品',
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
            $newList[] = [
                'order_no' => $v['order_no'],
                'payment' => $v['payment'] == 'wechat' ? '微信' : '余额',
                'order_money' => $v['order_money'],
                'freight' => $v['freight'],
                'goods_num' => $v['goods_num'],
                'item' => $v['item'],
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
        $no_merge_field = ['goods_title','stock_title','num','money','unit_price'];  //不合并的列
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
