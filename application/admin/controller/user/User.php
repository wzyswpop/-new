<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;
use think\Db;
use think\Exception;
use think\Exception\ValidateException;
use fast\Form;
use app\api\model\User as UserModel;
use app\admin\model\yp\Order as OrderModel;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    const REFERRAL_TREE_MAX_DEPTH = 100;

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';
    protected $noNeedRight = ['referral_rate', 'referralrate'];

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
        $data = ['增加','减少'];
        $select = Form::select('type_id',$data,null,['class' => 'type_id']);
        $this->assignconfig('select',$select);
    }

    protected function hasDistributionRateColumn()
    {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }
        $table = config('database.prefix') . 'user';
        $hasColumn = !empty(Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'distribution_rate'"));
        return $hasColumn;
    }

    protected function moneyMul($left, $right, $scale = 2)
    {
        return function_exists('bcmul') ? bcmul((string)$left, (string)$right, $scale) : number_format((float)$left * (float)$right, $scale, '.', '');
    }

    protected function moneyDiv($left, $right, $scale = 4)
    {
        if ((float)$right == 0) {
            return '0';
        }
        return function_exists('bcdiv') ? bcdiv((string)$left, (string)$right, $scale) : number_format((float)$left / (float)$right, $scale, '.', '');
    }

    protected function getOrderStatsMap($userIds)
    {
        $empty = ['total' => [], 'paid' => [], 'settled' => []];
        if (!$userIds) {
            return $empty;
        }
        $maps = $empty;
        $totalRows = OrderModel::where('user_id', 'in', $userIds)
            ->where('type', 0)
            ->field('user_id, COUNT(*) AS order_count, SUM(order_money) AS sales_money, MAX(createtime) AS last_order_time')
            ->group('user_id')
            ->select();
        foreach ($totalRows as $row) {
            $maps['total'][$row['user_id']] = [
                'order_count' => (int)$row['order_count'],
                'sales_money' => (float)$row['sales_money'],
                'last_order_time' => (int)$row['last_order_time'],
            ];
        }

        $paidRows = OrderModel::where('user_id', 'in', $userIds)
            ->where('status', 'in', ['2', '3', '4'])
            ->where('type', 0)
            ->field('user_id, COUNT(*) AS order_count, SUM(order_money) AS sales_money')
            ->group('user_id')
            ->select();
        foreach ($paidRows as $row) {
            $maps['paid'][$row['user_id']] = [
                'order_count' => (int)$row['order_count'],
                'sales_money' => (float)$row['sales_money'],
            ];
        }

        $settledRows = OrderModel::where('user_id', 'in', $userIds)
            ->where('status', '4')
            ->where('type', 0)
            ->field('user_id, COUNT(*) AS order_count, SUM(order_money) AS sales_money')
            ->group('user_id')
            ->select();
        foreach ($settledRows as $row) {
            $maps['settled'][$row['user_id']] = [
                'order_count' => (int)$row['order_count'],
                'sales_money' => (float)$row['sales_money'],
            ];
        }

        return $maps;
    }

    protected function getReferralTree($rootUserId)
    {
        $rootUserId = (int)$rootUserId;
        $withRate = $this->hasDistributionRateColumn();
        $fields = 'id,pid,nickname,avatar,mobile,commission,integral,jointime,status';
        if ($withRate) {
            $fields .= ',distribution_rate';
        }
        $rootFields = $withRate ? 'id,nickname,distribution_rate' : 'id,nickname';
        $root = $this->model->where('id', $rootUserId)->field($rootFields)->find();
        $rootRate = $root && isset($root['distribution_rate']) ? (float)$root['distribution_rate'] : 0;
        $rootOwner = $rootRate > 0 && $root ? [
            'id' => (int)$root['id'],
            'nickname' => $root['nickname'],
        ] : null;

        $visited = [$rootUserId => true];
        $currentParentIds = [$rootUserId];
        $nodes = [];
        $childrenByPid = [];
        $depth = 0;
        $truncated = false;

        while ($currentParentIds && $depth < self::REFERRAL_TREE_MAX_DEPTH) {
            $depth++;
            $children = $this->model
                ->where('pid', 'in', $currentParentIds)
                ->field($fields)
                ->order('id', 'desc')
                ->select();
            $nextParentIds = [];
            foreach ($children as $child) {
                $id = (int)$child['id'];
                $pid = (int)$child['pid'];
                if (isset($visited[$id])) {
                    $truncated = true;
                    continue;
                }
                $visited[$id] = true;
                $row = $child->toArray();
                $row['id'] = $id;
                $row['pid'] = $pid;
                $row['level'] = $depth;
                $row['distribution_rate'] = isset($row['distribution_rate']) ? $row['distribution_rate'] : '0.00';
                $nodes[$id] = $row;
                if (!isset($childrenByPid[$pid])) {
                    $childrenByPid[$pid] = [];
                }
                $childrenByPid[$pid][] = $id;
                $nextParentIds[] = $id;
            }
            $currentParentIds = $nextParentIds;
        }
        if ($currentParentIds) {
            $truncated = true;
        }

        $userIds = array_keys($nodes);
        $statsMaps = $this->getOrderStatsMap($userIds);
        $summary = [
            'direct_referral_count' => isset($childrenByPid[$rootUserId]) ? count($childrenByPid[$rootUserId]) : 0,
            'referral_count' => count($nodes),
            'total_order_count' => 0,
            'total_sales_money' => 0,
            'paid_order_count' => 0,
            'paid_sales_money' => 0,
            'settled_order_count' => 0,
            'settled_sales_money' => 0,
            'attributed_paid_order_count' => 0,
            'attributed_paid_sales_money' => 0,
            'attributed_settled_order_count' => 0,
            'attributed_settled_sales_money' => 0,
            'truncated' => $truncated ? 1 : 0,
        ];

        foreach ($nodes as $id => &$node) {
            $orders = isset($statsMaps['total'][$id]) ? $statsMaps['total'][$id] : ['order_count' => 0, 'sales_money' => 0, 'last_order_time' => 0];
            $paid = isset($statsMaps['paid'][$id]) ? $statsMaps['paid'][$id] : ['order_count' => 0, 'sales_money' => 0];
            $settled = isset($statsMaps['settled'][$id]) ? $statsMaps['settled'][$id] : ['order_count' => 0, 'sales_money' => 0];
            $directCount = isset($childrenByPid[$id]) ? count($childrenByPid[$id]) : 0;

            $node['avatar'] = $node['avatar'] ? cdnurl($node['avatar'], true) : letter_avatar($node['nickname']);
            $node['mobile_text'] = $node['mobile'] ?: '未绑定手机';
            $node['status_text'] = $node['status'] === 'normal' ? '正常' : '禁用';
            $node['jointime_text'] = !empty($node['jointime']) ? date('Y-m-d H:i', $node['jointime']) : '未知';
            $node['last_order_time_text'] = $orders['last_order_time'] ? date('Y-m-d H:i', $orders['last_order_time']) : '暂无订单';
            $node['direct_referral_count'] = $directCount;
            $node['indent_px'] = min(max(((int)$node['level'] - 1) * 26, 0), 260);
            $node['total_order_count'] = $orders['order_count'];
            $node['total_sales_money'] = number_format($orders['sales_money'], 2, '.', '');
            $node['paid_order_count'] = $paid['order_count'];
            $node['paid_sales_money'] = number_format($paid['sales_money'], 2, '.', '');
            $node['settled_order_count'] = $settled['order_count'];
            $node['settled_sales_money'] = number_format($settled['sales_money'], 2, '.', '');
            $node['attribution_owner_id'] = 0;
            $node['attribution_owner_name'] = '未归属';
            $node['included_in_current'] = 0;
            $node['included_text'] = '不计入当前';

            $summary['total_order_count'] += $orders['order_count'];
            $summary['total_sales_money'] += $orders['sales_money'];
            $summary['paid_order_count'] += $paid['order_count'];
            $summary['paid_sales_money'] += $paid['sales_money'];
            $summary['settled_order_count'] += $settled['order_count'];
            $summary['settled_sales_money'] += $settled['sales_money'];
        }
        unset($node);
        $this->applyReferralAttribution($rootUserId, $rootUserId, $rootOwner, $childrenByPid, $nodes, $statsMaps, $summary);

        $rows = [];
        $this->flattenReferralRows($rootUserId, $childrenByPid, $nodes, $rows);
        $summary['total_sales_money'] = number_format($summary['total_sales_money'], 2, '.', '');
        $summary['paid_sales_money'] = number_format($summary['paid_sales_money'], 2, '.', '');
        $summary['settled_sales_money'] = number_format($summary['settled_sales_money'], 2, '.', '');
        $summary['attributed_paid_sales_money'] = number_format($summary['attributed_paid_sales_money'], 2, '.', '');
        $summary['attributed_settled_sales_money'] = number_format($summary['attributed_settled_sales_money'], 2, '.', '');

        return [
            'rows' => $rows,
            'nodes' => $nodes,
            'summary' => $summary,
            'truncated' => $truncated,
        ];
    }

    protected function applyReferralAttribution($rootUserId, $parentId, $currentOwner, $childrenByPid, &$nodes, $statsMaps, &$summary)
    {
        if (empty($childrenByPid[$parentId])) {
            return;
        }
        foreach ($childrenByPid[$parentId] as $childId) {
            if (!isset($nodes[$childId])) {
                continue;
            }
            $owner = $currentOwner;
            $included = $owner && (int)$owner['id'] === (int)$rootUserId;
            $nodes[$childId]['attribution_owner_id'] = $owner ? (int)$owner['id'] : 0;
            $nodes[$childId]['attribution_owner_name'] = $owner ? $owner['nickname'] : '未归属';
            $nodes[$childId]['included_in_current'] = $included ? 1 : 0;
            $nodes[$childId]['included_text'] = $included ? '计入当前' : '不计入当前';

            if ($included) {
                $paid = isset($statsMaps['paid'][$childId]) ? $statsMaps['paid'][$childId] : ['order_count' => 0, 'sales_money' => 0];
                $settled = isset($statsMaps['settled'][$childId]) ? $statsMaps['settled'][$childId] : ['order_count' => 0, 'sales_money' => 0];
                $summary['attributed_paid_order_count'] += $paid['order_count'];
                $summary['attributed_paid_sales_money'] += $paid['sales_money'];
                $summary['attributed_settled_order_count'] += $settled['order_count'];
                $summary['attributed_settled_sales_money'] += $settled['sales_money'];
            }

            $nextOwner = ((float)$nodes[$childId]['distribution_rate'] > 0) ? [
                'id' => (int)$nodes[$childId]['id'],
                'nickname' => $nodes[$childId]['nickname'],
            ] : $currentOwner;
            $this->applyReferralAttribution($rootUserId, $childId, $nextOwner, $childrenByPid, $nodes, $statsMaps, $summary);
        }
    }

    protected function flattenReferralRows($pid, $childrenByPid, $nodes, &$rows)
    {
        if (empty($childrenByPid[$pid])) {
            return;
        }
        foreach ($childrenByPid[$pid] as $childId) {
            if (!isset($nodes[$childId])) {
                continue;
            }
            $rows[] = $nodes[$childId];
            $this->flattenReferralRows($childId, $childrenByPid, $nodes, $rows);
        }
    }

    public function change($ids){
        $num = $this->request->param('num');
        $type = $this->request->param('type');
        $category = $this->request->param('category');
        if(!is_numeric($num)){
            $this->error('变动数量错误');
        }
        switch ($category){
            case 'integral':
                UserModel::changeIntegral([
                    'money' => $num,
                    'user_id' => $ids,
                    'type' => $type ? 'sub' : 'add',
                    'memo' => '管理员变动',
                    'change_type' => 'admin'
                ]);
                break;
            default:
                $this->error('参数错误');
                break;
        }
        $this->success('成功');
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('level')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->setAttr('distribution_rate', isset($v['distribution_rate']) ? $v['distribution_rate'] : '0.00');
                $v->hidden(['password', 'salt']);
            }
            $userIds = [];
            foreach ($list as $item) {
                $userIds[] = $item['id'];
            }
            $referralCountMap = [];
            $recommenderIds = [];
            $recommenderMap = [];
            if ($userIds) {
                $referralRows = $this->model
                    ->where('pid', 'in', $userIds)
                    ->field('pid, COUNT(*) AS referral_count')
                    ->group('pid')
                    ->select();
                foreach ($referralRows as $referralRow) {
                    $referralCountMap[$referralRow['pid']] = (int)$referralRow['referral_count'];
                }
            }
            foreach ($list as $item) {
                if (!empty($item['pid'])) {
                    $recommenderIds[] = $item['pid'];
                }
            }
            $recommenderIds = array_values(array_unique($recommenderIds));
            if ($recommenderIds) {
                $recommenders = $this->model
                    ->where('id', 'in', $recommenderIds)
                    ->field('id,nickname,mobile')
                    ->select();
                foreach ($recommenders as $recommender) {
                    $recommenderMap[$recommender['id']] = [
                        'id' => $recommender['id'],
                        'nickname' => $recommender['nickname'],
                        'mobile' => $recommender['mobile'],
                    ];
                }
            }
            foreach ($list as $item) {
                $item->setAttr('referral_count', isset($referralCountMap[$item['id']]) ? $referralCountMap[$item['id']] : 0);
                if (!empty($item['pid']) && isset($recommenderMap[$item['pid']])) {
                    $recommender = $recommenderMap[$item['pid']];
                    $item->setAttr('recommender_id', $recommender['id']);
                    $item->setAttr('recommender_nickname', $recommender['nickname']);
                    $item->setAttr('recommender_mobile', $recommender['mobile']);
                    $item->setAttr('recommender_text', $recommender['nickname'] ?: ('用户ID ' . $recommender['id']));
                } elseif (!empty($item['pid'])) {
                    $item->setAttr('recommender_id', $item['pid']);
                    $item->setAttr('recommender_nickname', '');
                    $item->setAttr('recommender_mobile', '');
                    $item->setAttr('recommender_text', '推荐人已删除');
                } else {
                    $item->setAttr('recommender_id', 0);
                    $item->setAttr('recommender_nickname', '');
                    $item->setAttr('recommender_mobile', '');
                    $item->setAttr('recommender_text', '自然注册');
                }
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
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->setAttr('distribution_rate', isset($row['distribution_rate']) ? $row['distribution_rate'] : '0.00');
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return $this->edits($ids);
    }

    /**
     * 推荐用户
     */
    public function referrals($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->avatar = $row->avatar ? cdnurl($row->avatar, true) : letter_avatar($row->nickname);
        $row->setAttr('distribution_rate', isset($row['distribution_rate']) ? $row['distribution_rate'] : '0.00');
        $tree = $this->getReferralTree($ids);
        $list = $tree['rows'];
        $rate = (float)$row['distribution_rate'];
        $summary = $tree['summary'];
        $summary['rate'] = number_format($rate, 2, '.', '');
        $summary['has_rate'] = $rate > 0 ? 1 : 0;
        $summary['commission_money'] = $rate > 0 ? $this->moneyMul($summary['attributed_settled_sales_money'], $this->moneyDiv($rate, 100, 4), 2) : '0.00';
        $summary['commission_text'] = $rate > 0 ? ('￥' . $summary['commission_money']) : '未设置比例';

        $this->view->assign('row', $row);
        $this->view->assign('lists', $list);
        $this->view->assign('summary', $summary);
        return $this->view->fetch();
    }

    /**
     * 推荐账户总览
     */
    public function referralAccounts()
    {
        $sort = $this->request->get('sort', 'referral_count');
        $order = $this->request->get('order', 'desc');
        $sortMap = [
            'referral_count' => '全链路客户数',
            'direct_referral_count' => '直属客户数',
            'settled_order_count' => '完成订单数',
            'settled_sales_money' => '完成销售额',
            'real_commission_money' => '真实累计佣金',
            'withdrawn_money' => '已提现',
            'available_commission' => '可提现佣金',
        ];
        if (!isset($sortMap[$sort])) {
            $sort = 'referral_count';
        }
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $referralRows = $this->model
            ->where('pid', '>', 0)
            ->field('pid, COUNT(*) AS referral_count')
            ->group('pid')
            ->select();
        $pids = [];
        $referralCountMap = [];
        foreach ($referralRows as $referralRow) {
            $pids[] = $referralRow['pid'];
            $referralCountMap[$referralRow['pid']] = (int)$referralRow['referral_count'];
        }

        $lists = [];
        $summary = [
            'account_count' => 0,
            'referral_count' => 0,
            'direct_referral_count' => 0,
            'settled_sales_money' => 0,
            'real_commission_money' => 0,
            'withdrawn_money' => 0,
            'available_commission' => 0,
            'truncated' => 0,
        ];
        if ($pids) {
            $userFields = 'id,nickname,avatar,mobile,commission,jointime,status';
            if ($this->hasDistributionRateColumn()) {
                $userFields .= ',distribution_rate';
            }
            $lists = $this->model
                ->where('id', 'in', $pids)
                ->field($userFields)
                ->order('id', 'desc')
                ->select();

            $commissionMap = [];
            $commissionRows = Db::name('yp_money_log')
                ->where('user_id', 'in', $pids)
                ->where('classify', 'commission')
                ->where('type', 'add')
                ->where('change_type', 'commission')
                ->field('user_id, SUM(num) AS real_commission_money')
                ->group('user_id')
                ->select();
            foreach ($commissionRows as $commissionRow) {
                $commissionMap[$commissionRow['user_id']] = (float)$commissionRow['real_commission_money'];
            }

            $withdrawnMap = [];
            $withdrawnRows = Db::name('yp_withdrawal')
                ->where('user_id', 'in', $pids)
                ->where('type', 1)
                ->where('status', 2)
                ->field('user_id, SUM(money) AS withdrawn_money')
                ->group('user_id')
                ->select();
            foreach ($withdrawnRows as $withdrawnRow) {
                $withdrawnMap[$withdrawnRow['user_id']] = (float)$withdrawnRow['withdrawn_money'];
            }

            $summaryReferralIds = [];
            foreach ($lists as $item) {
                $tree = $this->getReferralTree($item['id']);
                $treeSummary = $tree['summary'];
                $realCommission = isset($commissionMap[$item['id']]) ? $commissionMap[$item['id']] : 0;
                $withdrawnMoney = isset($withdrawnMap[$item['id']]) ? $withdrawnMap[$item['id']] : 0;
                $availableCommission = isset($item['commission']) ? (float)$item['commission'] : 0;
                $rate = isset($item['distribution_rate']) ? (float)$item['distribution_rate'] : 0;

                $item->avatar = $item->avatar ? cdnurl($item->avatar, true) : letter_avatar($item->nickname);
                $item->setAttr('mobile_text', $item['mobile'] ?: '未绑定手机');
                $item->setAttr('distribution_rate', isset($item['distribution_rate']) ? $item['distribution_rate'] : '0.00');
                $item->setAttr('has_rate', $rate > 0 ? 1 : 0);
                $item->setAttr('referral_count', $treeSummary['referral_count']);
                $item->setAttr('direct_referral_count', $treeSummary['direct_referral_count']);
                $item->setAttr('settled_order_count', $treeSummary['attributed_settled_order_count']);
                $item->setAttr('settled_sales_money', $treeSummary['attributed_settled_sales_money']);
                $item->setAttr('real_commission_money', $rate > 0 ? number_format($realCommission, 2, '.', '') : '未设置比例');
                $item->setAttr('withdrawn_money', number_format($withdrawnMoney, 2, '.', ''));
                $item->setAttr('available_commission', $rate > 0 ? number_format($availableCommission, 2, '.', '') : '未设置比例');

                foreach (array_keys($tree['nodes']) as $nodeId) {
                    $summaryReferralIds[$nodeId] = true;
                }
                $summary['direct_referral_count'] += $item['direct_referral_count'];
                $summary['real_commission_money'] += $realCommission;
                $summary['withdrawn_money'] += $withdrawnMoney;
                $summary['available_commission'] += $availableCommission;
                if (!empty($treeSummary['truncated'])) {
                    $summary['truncated'] = 1;
                }
            }
            $summary['account_count'] = count($lists);
            $summary['referral_count'] = count($summaryReferralIds);
            foreach ($lists as $item) {
                $summary['settled_sales_money'] += (float)$item['settled_sales_money'];
            }

            $sortDirection = $order === 'asc' ? 1 : -1;
            $sortField = $sort;
            if (!is_array($lists)) {
                $lists = iterator_to_array($lists);
            }
            usort($lists, function ($left, $right) use ($sortField, $sortDirection) {
                $leftValue = isset($left[$sortField]) ? (float)$left[$sortField] : 0;
                $rightValue = isset($right[$sortField]) ? (float)$right[$sortField] : 0;
                if ($leftValue == $rightValue) {
                    return 0;
                }
                return $leftValue > $rightValue ? $sortDirection : -$sortDirection;
            });
        }
        $summary['settled_sales_money'] = number_format($summary['settled_sales_money'], 2, '.', '');
        $summary['real_commission_money'] = number_format($summary['real_commission_money'], 2, '.', '');
        $summary['withdrawn_money'] = number_format($summary['withdrawn_money'], 2, '.', '');
        $summary['available_commission'] = number_format($summary['available_commission'], 2, '.', '');
        $summary['rate_column_ready'] = $this->hasDistributionRateColumn() ? 1 : 0;

        $this->view->assign('lists', $lists);
        $this->view->assign('summary', $summary);
        $this->view->assign('sort', $sort);
        $this->view->assign('order', $order);
        $this->view->assign('nextOrder', $order === 'asc' ? 'desc' : 'asc');
        $this->view->assign('sortMap', $sortMap);
        return $this->view->fetch();
    }

    public function referral_accounts()
    {
        return $this->referralAccounts();
    }

    public function referral_rate()
    {
        return $this->saveReferralRate();
    }

    public function referralrate()
    {
        return $this->saveReferralRate();
    }

    protected function saveReferralRate()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        if (!$this->auth->isSuperAdmin() && !$this->auth->check('user/user/referral_accounts') && !$this->auth->check('user/user/referralaccounts')) {
            $this->error(__('You have no permission'));
        }
        if (!$this->hasDistributionRateColumn()) {
            $this->error('请先执行 distribution_rate 字段 SQL');
        }
        $ids = $this->request->post('ids/d');
        $rate = $this->request->post('distribution_rate');
        if (!$ids || !is_numeric($rate)) {
            $this->error(__('Invalid parameters'));
        }
        $rate = round((float)$rate, 2);
        if ($rate < 0 || $rate > 100) {
            $this->error('推荐比例必须在 0 到 100 之间');
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        $rateValue = number_format($rate, 2, '.', '');
        $table = config('database.prefix') . 'user';
        Db::execute("UPDATE `{$table}` SET `distribution_rate` = ? WHERE `id` = ?", [$rateValue, $ids]);
        $savedRate = Db::table($table)->where('id', $ids)->value('distribution_rate');
        $savedRate = number_format((float)$savedRate, 2, '.', '');
        if ($savedRate !== $rateValue) {
            $this->error('推荐比例保存失败，请检查数据库字段或缓存');
        }
        $this->success('推荐比例已保存', null, ['distribution_rate' => $savedRate]);
    }


    /**
     * 编辑
     */
    public function edits($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->setAttr('distribution_rate', isset($row['distribution_rate']) ? $row['distribution_rate'] : '0.00');
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if($params['money'] < 0){
                $this->error('金额错误');
            }
            if($params['commission'] < 0){
                $this->error('可提现金额错误');
            }
            if(isset($params['distribution_rate']) && ($params['distribution_rate'] < 0 || $params['distribution_rate'] > 100)){
                $this->error('推荐比例必须在 0 到 100 之间');
            }
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

}
