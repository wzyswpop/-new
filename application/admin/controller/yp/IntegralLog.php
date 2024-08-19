<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\Loader;
use app\admin\model\yp\IntegralLog as IntegralLogModel;

/**
 * 会员积分变动管理
 *
 * @icon fa fa-circle-o
 */
class IntegralLog extends Backend
{

    /**
     * IntegralLog模型对象
     * @var \app\admin\model\yp\IntegralLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\IntegralLog;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("changeTypeList", $this->model->getChangeTypeList());
        $this->assignconfig('SCRIPT_NAME',$_SERVER['SCRIPT_NAME']);

    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


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
                    ->with(['user'])
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
     * 导出人员信息
     */
    public function export_log(){
        set_time_limit(0);
        $f = json_decode($this->request->param('f'),true);
        $o = json_decode($this->request->param('o'),true);
        [$where, $sort, $order, $offset, $limit] = $this->buildparams($f,$o);
        $total = $this->model
            ->where($where)
            ->count();
        if($total == 0){
            $this->error('无数据');
        }
        $current_total = 0;     // 当前已循环条数
        $page_size = 2000;
        $total_page = intval(ceil($total / $page_size));
        $page = 0;
        $newList = [];
        for ($i = 0; $i < $total_page; $i++) {
            $page = $i + 1;
            $is_last_page = ($page == $total_page) ? true : false;
            $lists = collection($this->model
                ->with(['user'])
                ->where($where)
                ->limit(($i * $page_size), $page_size)
                ->select())->toArray();
            foreach ($lists as $v) {
                $data = [
                    isset($v['user']['nickname']) ?? '',
                    $v['num'],
                    $v['before'],
                    $v['after'],
                    $v['memo'],
                    format($v['createtime']),
                    $v['type_text'],
                    $v['change_type_text']
                ];
                $newList[] = $data;
            }
        }
        for ($i = 1;$i <= 8;$i++){
            $arrs[] = '';
        }
        $newList[] = $arrs;
        $expCellName = [
            '昵称',
            '变更数量',
            '变更前',
            '变更后',
            '备注',
            '变动时间',
            '变动类型',
            '业务类型',
        ];
        $current_total += count($newList);     // 当前循环总条数
        $this->exportExcel('积分记录', $expCellName, $newList, $spreadsheet, $sheet, [
            'page' => $page,
            'page_size' => $page_size,      // 如果传了 current_total 则 page_size 就不用了
            'current_total' => $current_total,      // page_size 是 order 的，但是 newList 其实是 order_item 的
            'is_last_page' => $is_last_page
        ]);
    }

    protected function exportExcel($expTitle, $expCellName, $expTableData, &$spreadsheet = null, &$sheet = null, $pages = [])
    {
        $page = $pages['page'] ?? 1;
        $page_size = $pages['page_size'] ?? 1000;
        $is_last_page = $pages['is_last_page'] ?? 1;
        $current_total = $pages['current_total'] ?? 0;
        if ($current_total) {
            $base_cell = $current_total - count($expTableData) + 2;
        } else {
            $base_cell = ($page - 1) * $page_size + 2;
        }
        $fileName = $expTitle;
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);
        $str = 'A';
        for ($i = 1;$i <= $cellNum;$i++){
            $cellName[] = $str++;
        }
        if ($page == 1) {
            // 不限时
            set_time_limit(0);
            // 根据需要调大内存限制
            ini_set('memory_limit', '512M');
            $cache_type = 'file';
            if ($cache_type == 'file' && class_exists(FilesystemCachePool::class)) {
                // 将数据暂存磁盘，可以降低内存，但是导出速度会大幅下降 需要安装扩展包  composer require cache/filesystem-adapter
                $path = ROOT_PATH . 'runtime' . DS . 'export/';
                @mkdir($path);
                $filesystemAdapter = new Local($path);
                $filesystem        = new Filesystem($filesystemAdapter);
                $pool = new FilesystemCachePool($filesystem);
                \PhpOffice\PhpSpreadsheet\Settings::setCache($pool);
            }
            // 实例化excel
            $spreadsheet = new Spreadsheet();
            // 初始化工作簿
            $sheet = $spreadsheet->getActiveSheet(0);
            // 给表头设置边框
            $sheet->getStyle('A1:' . $cellName[$cellNum - 1] . '1')->getFont()->setBold(true);
            // 表头
            $i = 0;
            foreach ($expCellName as $key => $cell) {
                $sheet->setCellValue($cellName[$i] . '1', $cell);
                $i++;
            }
        }
        // 写入数据
        for ($i = 0; $i < $dataNum-1; $i++) {
            if ($is_last_page && $i == ($dataNum - 1)) {
                $sheet->mergeCells('A' . ($i + $base_cell) . ':' . $cellName[$cellNum - 1] . ($i + $base_cell));
                $sheet->setCellValue('A' . ($i + $base_cell), $expTableData[$i][key($expCellName)]);
            } else {
                $j = 0;
                foreach ($expCellName as $key => $cell) {
                    $sheet->setCellValue($cellName[$j] . ($i + $base_cell), $expTableData[$i][$key]);
                    $j++;
                }
            }
        }

        if ($is_last_page) {
            ini_set('memory_limit', '256M');
            ob_end_clean();
            header('pragma:public');
            header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $fileName . '.xlsx"');
            header("Content-Disposition:attachment;filename=$fileName.xlsx"); //attachment新窗口打印inline本窗口打印
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }
    }

}
