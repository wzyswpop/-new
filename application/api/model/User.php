<?php
namespace app\api\model;

use think\Db;
use think\Exception;

class User extends Base{

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