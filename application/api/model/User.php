<?php
namespace app\api\model;

use think\Db;
use think\Exception;

class User extends Base{

    protected static function compareMoney($left, $right)
    {
        return function_exists('bccomp') ? bccomp((string)$left, (string)$right, 2) : ((float)$left <=> (float)$right);
    }

    protected static function subMoney($left, $right)
    {
        return function_exists('bcsub') ? bcsub((string)$left, (string)$right, 2) : number_format((float)$left - (float)$right, 2, '.', '');
    }

    protected static function syncCommissionToBalance($user_info)
    {
        if(self::compareMoney($user_info->commission, $user_info->money) > 0){
            $user_info->commission = $user_info->money;
        }
    }

    /**
     * 变更余额
     */
    public static function changeMoney($data){
        $money = $data['money'];
        if($money <= 0){
            return true;
        }
        $user_info = self::where(['id' => $data['user_id']])->lock(true)->find();
        if(!$user_info){
            return true;
        }
        Db::startTrans();
        try {
            $before = $user_info->money;
            if($data['type'] == 'add'){  //增加
                $after = bcadd($user_info->money,$money,2);
                $user_info->setInc('money',$money);
            }else{                       //减少
                if($money > $user_info->money){
                    throw new Exception('余额不足');
                }
                $after = bcsub($user_info->money,$money,2);
                $user_info->setDec('money',$money);
                if(self::compareMoney($user_info->commission, $after) > 0){
                    $commissionBefore = $user_info->commission;
                    $commissionAfter = $after;
                    $commissionReduce = self::subMoney($commissionBefore, $commissionAfter);
                    $user_info->commission = $commissionAfter;
                    MoneyLog::insert([
                        'user_id' => $data['user_id'],
                        'num' => $commissionReduce,
                        'before' => $commissionBefore,
                        'after' => $commissionAfter,
                        'type' => 'sub',
                        'memo' => $data['memo'] . '扣减可提现佣金',
                        'order_no' => $data['order_no'] ?? '',
                        'createtime' => time(),
                        'change_type' => $data['change_type'],
                        'classify' => 'commission'
                    ]);
                }
            }
            MoneyLog::insert([
                'user_id' => $data['user_id'],
                'num' => $money,
                'before' => $before,
                'after' => $after,
                'type' => $data['type'],
                'memo' => $data['memo'],
                'order_no' => $data['order_no'] ?? '',
                'createtime' => time(),
                'change_type' => $data['change_type'],
                'classify' => 'money'
            ]);
            $user_info->save();
            Db::commit();
            $return = true;
        }catch (Exception $e){
            Db::rollback();
            $return = false;
        }
        return $return;
    }

    /**
     * 分销佣金入账：钱包余额与可提现佣金额度同步增加。
     */
    public static function addCommissionToWallet($data){
        $money = $data['money'];
        if($money <= 0){
            return true;
        }
        $user_info = self::where(['id' => $data['user_id']])->lock(true)->find();
        if(!$user_info){
            return true;
        }
        Db::startTrans();
        try {
            $moneyBefore = $user_info->money;
            $commissionBefore = $user_info->commission;
            $moneyAfter = bcadd($user_info->money,$money,2);
            $commissionAfter = bcadd($user_info->commission,$money,2);
            $user_info->setInc('money',$money);
            $user_info->setInc('commission',$money);
            MoneyLog::insert([
                'user_id' => $data['user_id'],
                'num' => $money,
                'before' => $moneyBefore,
                'after' => $moneyAfter,
                'type' => 'add',
                'memo' => $data['memo'],
                'order_no' => $data['order_no'] ?? '',
                'createtime' => time(),
                'change_type' => $data['change_type'],
                'classify' => 'money'
            ]);
            MoneyLog::insert([
                'user_id' => $data['user_id'],
                'num' => $money,
                'before' => $commissionBefore,
                'after' => $commissionAfter,
                'type' => 'add',
                'memo' => $data['memo'],
                'order_no' => $data['order_no'] ?? '',
                'createtime' => time(),
                'change_type' => $data['change_type'],
                'classify' => 'commission'
            ]);
            $user_info->save();
            Db::commit();
            $return = true;
        }catch (Exception $e){
            Db::rollback();
            $return = false;
        }
        return $return;
    }

    /**
     * 佣金提现冻结：只允许从可提现佣金额度发起，同时扣减钱包余额。
     */
    public static function changeCommissionWithdrawal($data){
        $money = $data['money'];
        if($money <= 0){
            return true;
        }
        $user_info = self::where(['id' => $data['user_id']])->lock(true)->find();
        if(!$user_info){
            return true;
        }
        Db::startTrans();
        try {
            if($data['type'] == 'add'){
                $moneyBefore = $user_info->money;
                $commissionBefore = $user_info->commission;
                $moneyAfter = bcadd($user_info->money,$money,2);
                $commissionAfter = bcadd($user_info->commission,$money,2);
                $user_info->setInc('money',$money);
                $user_info->setInc('commission',$money);
            }else{
                if(self::compareMoney($money, $user_info->commission) > 0){
                    throw new Exception('余额不足');
                }
                if(self::compareMoney($money, $user_info->money) > 0){
                    $syncBefore = $user_info->money;
                    $syncMoney = self::subMoney($money, $user_info->money);
                    $syncAfter = bcadd($user_info->money,$syncMoney,2);
                    $user_info->setInc('money',$syncMoney);
                    MoneyLog::insert([
                        'user_id' => $data['user_id'],
                        'num' => $syncMoney,
                        'before' => $syncBefore,
                        'after' => $syncAfter,
                        'type' => 'add',
                        'memo' => '历史佣金同步至钱包',
                        'order_no' => $data['order_no'] ?? '',
                        'createtime' => time(),
                        'change_type' => 'commission',
                        'classify' => 'money'
                    ]);
                    $user_info->money = $syncAfter;
                }
                $moneyBefore = $user_info->money;
                $commissionBefore = $user_info->commission;
                $moneyAfter = self::subMoney($user_info->money,$money);
                $commissionAfter = self::subMoney($user_info->commission,$money);
                $user_info->setDec('money',$money);
                $user_info->setDec('commission',$money);
            }
            MoneyLog::insert([
                'user_id' => $data['user_id'],
                'num' => $money,
                'before' => $moneyBefore,
                'after' => $moneyAfter,
                'type' => $data['type'],
                'memo' => $data['memo'],
                'order_no' => $data['order_no'] ?? '',
                'createtime' => time(),
                'change_type' => $data['change_type'],
                'classify' => 'money'
            ]);
            MoneyLog::insert([
                'user_id' => $data['user_id'],
                'num' => $money,
                'before' => $commissionBefore,
                'after' => $commissionAfter,
                'type' => $data['type'],
                'memo' => $data['memo'],
                'order_no' => $data['order_no'] ?? '',
                'createtime' => time(),
                'change_type' => $data['change_type'],
                'classify' => 'commission'
            ]);
            $user_info->save();
            Db::commit();
            $return = true;
        }catch (Exception $e){
            Db::rollback();
            $return = false;
        }
        return $return;
    }

    /**
     * 变更可提现
     */
    public static function changeCommission($data){
        $money = $data['money'];
        if($money <= 0){
            return true;
        }
        $user_info = self::where(['id' => $data['user_id']])->lock(true)->find();
        if(!$user_info){
            return true;
        }
        Db::startTrans();
        try {
            $before = $user_info->commission;
            if($data['type'] == 'add'){  //增加
                $after = bcadd($user_info->commission,$money,2);
                $user_info->setInc('commission',$money);
            }else{                       //减少
                if($money > $user_info->commission){
                    throw new Exception('余额不足');
                }
                $after = bcsub($user_info->commission,$money,2);
                $user_info->setDec('commission',$money);
            }
            MoneyLog::insert([
                'user_id' => $data['user_id'],
                'num' => $money,
                'before' => $before,
                'after' => $after,
                'type' => $data['type'],
                'memo' => $data['memo'],
                'order_no' => $data['order_no'] ?? '',
                'createtime' => time(),
                'change_type' => $data['change_type'],
                'classify' => 'commission'
            ]);
            $user_info->save();
            Db::commit();
            $return = true;
        }catch (Exception $e){
            Db::rollback();
            $return = false;
        }
        return $return;
    }

    /**
     * 变更积分
     */
    public static function changeIntegral($data){
        $money = $data['money'];
        if($money <= 0){
            return true;
        }
        $user_info = self::where(['id' => $data['user_id']])->lock(true)->find();
        if(!$user_info){
            return true;
        }
        Db::startTrans();
        try {
            $before = $user_info->integral;
            if($data['type'] == 'add'){  //增加
                $after = bcadd($user_info->integral,$money,2);
                $user_info->setInc('integral',$money);
            }else{                       //减少
                if($money > $user_info->integral){
                    throw new Exception('余额不足');
                }
                $after = bcsub($user_info->integral,$money,2);
                $user_info->setDec('integral',$money);
            }
            IntegralLog::insert([
                'user_id' => $data['user_id'],
                'num' => $money,
                'before' => $before,
                'after' => $after,
                'type' => $data['type'],
                'memo' => $data['memo'],
                'order_no' => $data['order_no'] ?? '',
                'createtime' => time(),
                'change_type' => $data['change_type']
            ]);
            $user_info->save();
            Db::commit();
            $return = true;
        }catch (Exception $e){
            Db::rollback();
            $return = false;
        }
        return $return;
    }

}
