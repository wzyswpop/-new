<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Db;
use app\admin\model\yp\Order;
use app\admin\model\yp\IntegralOrder;
use app\admin\model\yp\SignOrder;
use app\admin\model\yp\Goods;
use app\admin\model\yp\ServiceOrder;
use app\admin\model\yp\SkuPrice;
use app\admin\model\yp\Withdrawal;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }

        $column = [];
        $starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $todayStart = Date::unixtime('day', 0);
        $todayEnd = Date::unixtime('day', 0, 'end');

        $joinlist = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        $orderlist = Db("yp_order")->where('paytime', 'between time', [$starttime, $endtime])
            ->where('status', 'in', ['2', '3', '4', '5', '6'])
            ->field('COUNT(*) AS nums, SUM(order_money) AS money, DATE_FORMAT(FROM_UNIXTIME(paytime), "%Y-%m-%d") AS pay_date')
            ->group('pay_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        $orderCountList = array_fill_keys($column, 0);
        $orderMoneyList = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }
        foreach ($orderlist as $k => $v) {
            $orderCountList[$v['pay_date']] = (int)$v['nums'];
            $orderMoneyList[$v['pay_date']] = round((float)$v['money'], 2);
        }

        $order_num = Order::where(['status' => ['>', 0]])->count();
        $integral_order_num = IntegralOrder::where(['status' => ['>', 0]])->count();
        $sign_order_num = SignOrder::where(['status' => ['>', 0]])->count();
        $monthStart = strtotime(date('Y-m-01 00:00:00'));
        $monthEnd = strtotime(date('Y-m-t 23:59:59'));
        $month_sales_money = Order::where('paytime', 'between time', [$monthStart, $monthEnd])
            ->where('status', 'in', ['2', '3', '4', '5', '6'])
            ->sum('order_money');
        $month_refund_money = Db::name('yp_service_order')->alias('s')
            ->join('__YP_ORDER__ o', 'o.id = s.order_id', 'LEFT')
            ->where('s.handletime', 'between time', [$monthStart, $monthEnd])
            ->where('s.status', '1')
            ->where('s.is_del', 0)
            ->sum('s.money');
        $month_real_sales_money = round((float)$month_sales_money - (float)$month_refund_money, 2);
        $today_order_num = Order::where('createtime', 'between time', [$todayStart, $todayEnd])->count();
        $today_paid_order_num = Order::where('paytime', 'between time', [$todayStart, $todayEnd])->where('status', 'in', ['2', '3', '4', '5', '6'])->count();
        $today_sales_money = Order::where('paytime', 'between time', [$todayStart, $todayEnd])->where('status', 'in', ['2', '3', '4', '5', '6'])->sum('order_money');
        $wait_pay_order_num = Order::where(['status' => '1'])->count();
        $wait_delivery_order_num = Order::where(['status' => '2'])->count();
        $after_sale_order_num = Order::where(['status' => '5'])->count();
        $service_order_num = ServiceOrder::where(['status' => '0'])->where(['is_del' => 0])->count();
        $withdrawal_num = Withdrawal::where(['status' => '1'])->count();
        $goods_on_sale_num = Goods::where(['status' => '1'])->count();
        $goods_off_sale_num = Goods::where(['status' => '2'])->count();
        $low_stock_num = count(array_unique(SkuPrice::where('stock', '<=', 10)->column('goods_id')));
        $sales_total_money = Order::where('status', 'in', ['2', '3', '4', '5', '6'])->sum('order_money');
        $today_sales_money = number_format((float)$today_sales_money, 2, '.', '');
        $sales_total_money = number_format((float)$sales_total_money, 2, '.', '');
        $recentOrders = Order::where(['status' => '2'])->order('paytime', 'desc')->limit(6)->select();
        $hotGoods = Goods::where(['status' => '1'])->order('sales', 'desc')->limit(6)->select();
        $assign = compact(
            'order_num',
            'integral_order_num',
            'sign_order_num',
            'today_order_num',
            'today_paid_order_num',
            'today_sales_money',
            'month_real_sales_money',
            'wait_pay_order_num',
            'wait_delivery_order_num',
            'after_sale_order_num',
            'service_order_num',
            'withdrawal_num',
            'goods_on_sale_num',
            'goods_off_sale_num',
            'low_stock_num',
            'sales_total_money',
            'recentOrders',
            'hotGoods'
        );
        $this->assign($assign);

        $dbTableList = Db::query("SHOW TABLE STATUS");
        $addonList = get_addon_list();
        $totalworkingaddon = 0;
        $totaladdon = count($addonList);
        foreach ($addonList as $index => $item) {
            if ($item['state']) {
                $totalworkingaddon += 1;
            }
        }
        $this->view->assign([
            'totaluser'         => User::count(),
            'totaladdon'        => $totaladdon,
            'totaladmin'        => Admin::count(),
            'totalcategory'     => \app\common\model\Category::count(),
            'todayusersignup'   => User::whereTime('jointime', 'today')->count(),
            'todayuserlogin'    => User::whereTime('logintime', 'today')->count(),
            'sevendau'          => User::whereTime('jointime|logintime|prevtime', '-7 days')->count(),
            'thirtydau'         => User::whereTime('jointime|logintime|prevtime', '-30 days')->count(),
            'threednu'          => User::whereTime('jointime', '-3 days')->count(),
            'sevendnu'          => User::whereTime('jointime', '-7 days')->count(),
            'dbtablenums'       => count($dbTableList),
            'dbsize'            => array_sum(array_map(function ($item) {
                return $item['Data_length'] + $item['Index_length'];
            }, $dbTableList)),
            'totalworkingaddon' => $totalworkingaddon,
            'attachmentnums'    => Attachment::count(),
            'attachmentsize'    => Attachment::sum('filesize'),
            'picturenums'       => Attachment::where('mimetype', 'like', 'image/%')->count(),
            'picturesize'       => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),
        ]);

        $this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));
        $this->assignconfig('orderdata', array_values($orderCountList));
        $this->assignconfig('moneydata', array_values($orderMoneyList));

        return $this->view->fetch();
    }

}
