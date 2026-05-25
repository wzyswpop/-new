<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;

/**
 * 销售统计
 *
 * @icon fa fa-line-chart
 */
class SalesStatistics extends Backend
{
    public function index()
    {
        $year = (int)$this->request->get('year', date('Y'));
        $currentYear = (int)date('Y');
        if ($year < 2000 || $year > $currentYear) {
            $year = (int)date('Y');
        }
        $orderType = $this->request->get('order_type', '');
        $payment = $this->request->get('payment', '');

        $stats = $this->buildMonthlyStats($year, $orderType, $payment);

        $this->assign('year', $year);
        $this->assign('orderType', $orderType);
        $this->assign('payment', $payment);
        $this->assign('summary', $stats['summary']);
        $this->assign('rows', $stats['rows']);
        $this->assign('years', $this->getYearOptions($year));
        $this->assignconfig('salesStatistics', [
            'months' => array_column($stats['rows'], 'month_name'),
            'grossSales' => array_column($stats['rows'], 'gross_sales'),
            'refundAmount' => array_column($stats['rows'], 'refund_amount'),
            'realSales' => array_column($stats['rows'], 'real_sales'),
        ]);

        return $this->view->fetch();
    }

    private function buildMonthlyStats($year, $orderType, $payment)
    {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');
        $start = strtotime($year . '-01-01 00:00:00');
        $lastMonth = $year >= $currentYear ? $currentMonth : 12;
        $end = strtotime(sprintf('%04d-%02d-%02d 23:59:59', $year, $lastMonth, (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $lastMonth)))));
        $months = [];
        for ($month = 1; $month <= $lastMonth; $month++) {
            $key = sprintf('%04d-%02d', $year, $month);
            $months[$key] = [
                'month' => $key,
                'month_name' => $month . '月',
                'paid_order_count' => 0,
                'gross_sales' => 0.00,
                'refund_count' => 0,
                'refund_amount' => 0.00,
                'real_sales' => 0.00,
                'refund_rate' => '0.00%',
            ];
        }

        $salesQuery = Db::name('yp_order')
            ->where('paytime', 'between', [$start, $end])
            ->where('status', 'in', ['2', '3', '4', '5', '6']);
        if ($orderType !== '') {
            $salesQuery->where('order_type', $orderType);
        }
        if ($payment !== '') {
            $salesQuery->where('payment', $payment);
        }
        $salesList = $salesQuery
            ->field('DATE_FORMAT(FROM_UNIXTIME(paytime), "%Y-%m") AS stat_month, COUNT(*) AS order_count, IFNULL(SUM(order_money), 0) AS gross_sales')
            ->group('stat_month')
            ->select();

        foreach ($salesList as $item) {
            if (!isset($months[$item['stat_month']])) {
                continue;
            }
            $months[$item['stat_month']]['paid_order_count'] = (int)$item['order_count'];
            $months[$item['stat_month']]['gross_sales'] = round((float)$item['gross_sales'], 2);
        }

        $refundQuery = Db::name('yp_service_order')->alias('s')
            ->join('__YP_ORDER__ o', 'o.id = s.order_id', 'LEFT')
            ->where('s.handletime', 'between', [$start, $end])
            ->where('s.status', '1')
            ->where('s.is_del', 0);
        if ($orderType !== '') {
            $refundQuery->where('o.order_type', $orderType);
        }
        if ($payment !== '') {
            $refundQuery->where('o.payment', $payment);
        }
        $refundList = $refundQuery
            ->field('DATE_FORMAT(FROM_UNIXTIME(s.handletime), "%Y-%m") AS stat_month, COUNT(*) AS refund_count, IFNULL(SUM(s.money), 0) AS refund_amount')
            ->group('stat_month')
            ->select();

        foreach ($refundList as $item) {
            if (!isset($months[$item['stat_month']])) {
                continue;
            }
            $months[$item['stat_month']]['refund_count'] = (int)$item['refund_count'];
            $months[$item['stat_month']]['refund_amount'] = round((float)$item['refund_amount'], 2);
        }

        $summary = [
            'gross_sales' => 0.00,
            'refund_amount' => 0.00,
            'real_sales' => 0.00,
            'paid_order_count' => 0,
            'refund_count' => 0,
            'refund_rate' => '0.00%',
        ];
        foreach ($months as &$item) {
            $item['real_sales'] = round($item['gross_sales'] - $item['refund_amount'], 2);
            $item['refund_rate'] = $item['gross_sales'] > 0 ? number_format($item['refund_amount'] / $item['gross_sales'] * 100, 2) . '%' : '0.00%';
            $summary['gross_sales'] += $item['gross_sales'];
            $summary['refund_amount'] += $item['refund_amount'];
            $summary['real_sales'] += $item['real_sales'];
            $summary['paid_order_count'] += $item['paid_order_count'];
            $summary['refund_count'] += $item['refund_count'];
        }
        unset($item);

        $summary['gross_sales'] = round($summary['gross_sales'], 2);
        $summary['refund_amount'] = round($summary['refund_amount'], 2);
        $summary['real_sales'] = round($summary['real_sales'], 2);
        $summary['refund_rate'] = $summary['gross_sales'] > 0 ? number_format($summary['refund_amount'] / $summary['gross_sales'] * 100, 2) . '%' : '0.00%';

        return [
            'summary' => $summary,
            'rows' => array_values($months),
        ];
    }

    private function getYearOptions($selectedYear)
    {
        $currentYear = (int)date('Y');
        $startYear = min($selectedYear, $currentYear) - 4;
        $endYear = $currentYear;
        $years = [];
        for ($year = $endYear; $year >= $startYear; $year--) {
            $years[] = $year;
        }
        return $years;
    }
}
