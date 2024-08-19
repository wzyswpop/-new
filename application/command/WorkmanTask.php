<?php

namespace app\command;

use think\console\input\Argument;
use think\console\input\Option;
use think\console\Command;
use think\console\Output;
use think\console\Input;
use Workerman\Lib\Timer;
use Workerman\Worker;
use app\api\model\Order;
use app\api\model\SkuPrice;
use app\api\model\IntegralOrder;
use app\api\model\UserCoupons;
use app\api\model\Coupons;
use think\Db;
use think\Exception;
use app\api\model\SignOrder;
use app\api\model\SignGoods;

require_once ROOT_PATH . 'extend' . DS . 'GatewayWorker' . DS . 'vendor' . DS . 'autoload.php';


class WorkmanTask extends Command
{
    protected function configure ()
    {
        $this->setName('task')->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status|connections", 'start')->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the workerman server in daemon mode.')->setDescription('启动成功');
    }

    protected function execute (Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        if (DIRECTORY_SEPARATOR !== '\\') {
            if (!in_array($action, ['start', 'stop', 'reload', 'restart', 'status', 'connections'])) {
                $output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload|status|connections .</error>");
                return false;
            }
            global $argv;
            array_shift($argv);
            array_shift($argv);
            array_unshift($argv, 'think', $action);
        } elseif ('start' != $action) {
            $output->writeln("<error>Not Support action:{$action} on Windows.</error>");
            return false;
        }
        if ('start' == $action) {
            $output->writeln('Starting GatewayWorker server...');
        }
        // 启动计划任务
        $this->plan();
        Worker::runAll();
    }

    // 初始化 计划任务 进程
    public function plan ()
    {
        // 全局静态属性
        if ($this->input->hasOption('daemon')) {
            // 以daemon(守护进程)方式运行
            Worker::$daemonize = true;
        }
        Worker::$pidFile       = '/var/run/yptask.pid';
        $worker                = new Worker();
        $worker->count         = 1;
        $worker->onWorkerStart = function ($worker) {
            echo "\r\n";
            echo "计划任务 启动成功";
            echo "\r\n";
            Timer::add(5, [$this, 'order']);
            Timer::add(5, [$this, 'coupon']);
        };
    }

    /**
     * 自动订单
     */
    public function order(){
        $config = getValues(['overtime','confirmtime']);
        if($config['overtime']){
            $time = time() - $config['overtime'] * 60;
            Db::startTrans();
            try {
                $order_list = Order::where(['status' => '1','createtime' => ['<',$time]])->with('item')->select();
                if($order_list){
                    foreach ($order_list as $v){
                        $v->status = '0';
                        $v->canceltime = time();
                        $v->save();
                        foreach ($v['item'] as $vv){
                            SkuPrice::where(['id' => $vv['stock_id'],'goods_id' => $vv['goods_id']])->setInc('stock',$vv['num']);
                        }
                    }
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                echo $e->getMessage()."\n\r";
            }
            Db::startTrans();
            try {
                $sign_order_list = SignOrder::where(['status' => '1','createtime' => ['<',$time]])->select();
                if($sign_order_list){
                    foreach ($sign_order_list as $v){
                        $v->status = '0';
                        $v->canceltime = time();
                        $v->save();
//                        SignGoods::where(['id' => $v['goods_id']])->setInc('stock',$v['num']);
                    }
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                echo $e->getMessage()."\n\r";
            }
        }
        if($config['confirmtime']){
            $time = time() - $config['confirmtime'] * 86400;
            Db::startTrans();
            try {
                $order_list = Order::where(['status' => '3','delivertime' => ['<',$time]])->select();
                if($order_list){
                    foreach ($order_list as $v){
                        $v->status = '4';
                        $v->confirmtime = time();
                        $score = floor($v['order_money']);
                        if($score >= 1){
                            $score_log = [
                                'user_id' => $v['user_id'],
                                'money' => $score,
                                'type' => 'add',
                                'memo' => '确认收货',
                                'order_no' => $v['order_no'],
                                'change_type' => 'pay'
                            ];
                            \app\api\model\User::changeIntegral($score_log);
                        }
                        $v->save();
                        Order::distribution($v['id']);
                        Order::receiving($v['user_id']);
                    }
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                echo $e->getMessage()."\n\r";
            }
            Db::startTrans();
            try {
                $score_order = IntegralOrder::where(['status' => '2','delivertime' => ['<',$time]])->select();
                if($score_order){
                    foreach ($score_order as $v){
                        $v->status = '3';
                        $v->confirmtime =  time();
                        $v->save();
                    }
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                echo $e->getMessage()."\n\r";
            }
            Db::startTrans();
            try {
                $sign_order = SignOrder::where(['status' => '3','delivertime' => ['<',$time]])->select();
                if($sign_order){
                    foreach ($sign_order as $v){
                        $v->status = '4';
                        $v->confirmtime =  time();
                        $v->save();
                    }
                }
                Db::commit();
            }catch (Exception $e){
                Db::rollback();
                echo $e->getMessage()."\n\r";
            }
        }
        echo "订单计划任务执行成功\n\r";

    }

    /**
     * 自动优惠券
     */
    public function coupon(){
        UserCoupons::where(['endtime' => ['<',time()],'status' => '1'])->update(['status' => '3']);
        Coupons::where(['endtime' => ['<',time()],'status' => '1'])->update(['status' => '2']);
    }
}