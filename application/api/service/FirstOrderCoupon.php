<?php
namespace app\api\service;

use app\api\model\Coupons;
use app\api\model\Order;
use app\api\model\User;
use app\api\model\UserCoupons;
use think\Db;
use think\Exception;

class FirstOrderCoupon
{
    const SOURCE = 'first_order';

    public static function grant($userId, $scene = 'register', $pid = 0)
    {
        $userId = (int)$userId;
        $pid = (int)$pid;
        if ($userId <= 0) {
            return null;
        }
        if ($scene === 'promoter_share' && $pid <= 0) {
            return null;
        }
        if ($pid > 0 && $pid === $userId) {
            return null;
        }

        Db::startTrans();
        try {
            $user = User::where(['id' => $userId])->lock(true)->find();
            if (!$user) {
                Db::rollback();
                return null;
            }

            $paidOrder = Order::where(['user_id' => $userId])
                ->where('status', 'in', ['2', '3', '4', '5', '6', '7'])
                ->find();
            if ($paidOrder) {
                Db::rollback();
                return null;
            }

            $ownedCouponIds = UserCoupons::where(['user_id' => $userId, 'source' => self::SOURCE])
                ->column('coupons_id');
            $ownedCouponIds = array_map('intval', $ownedCouponIds ?: []);

            $coupons = Coupons::where([
                    'is_first_order' => 1,
                    'status' => '1',
                    'stock' => ['>', 0],
                    'endtime' => ['>', time()]
                ])
                ->order('use_money asc,amount asc,id asc')
                ->lock(true)
                ->select();
            if (!$coupons) {
                Db::rollback();
                return null;
            }

            $grantedCoupons = [];
            foreach ($coupons as $coupon) {
                if (in_array((int)$coupon['id'], $ownedCouponIds, true)) {
                    continue;
                }
                $dec = Coupons::where([
                        'id' => $coupon['id'],
                        'stock' => ['>', 0],
                        'status' => '1'
                    ])
                    ->where('endtime', '>', time())
                    ->setDec('stock');
                if ($dec !== 1) {
                    continue;
                }

                $endtime = time() + ((int)$coupon['day']) * 86400;
                UserCoupons::insert([
                    'user_id' => $userId,
                    'coupons_id' => $coupon['id'],
                    'name' => $coupon['name'],
                    'goods_type' => $coupon['goods_type'],
                    'goods_ids' => $coupon['goods_ids'],
                    'amount' => $coupon['amount'],
                    'use_money' => $coupon['use_money'],
                    'endtime' => $endtime,
                    'createtime' => time(),
                    'source' => self::SOURCE,
                    'source_pid' => $pid,
                    'grant_scene' => $scene
                ]);

                $ownedCouponIds[] = (int)$coupon['id'];
                $grantedCoupons[] = [
                    'granted' => true,
                    'id' => $coupon['id'],
                    'name' => $coupon['name'],
                    'amount' => $coupon['amount'],
                    'use_money' => $coupon['use_money'],
                    'endtime' => format($endtime)
                ];
            }

            if (!$grantedCoupons) {
                Db::rollback();
                return null;
            }

            Db::commit();

            return [
                'granted' => true,
                'count' => count($grantedCoupons),
                'coupons' => $grantedCoupons,
                'name' => $grantedCoupons[0]['name'],
                'amount' => $grantedCoupons[0]['amount'],
                'use_money' => $grantedCoupons[0]['use_money'],
                'endtime' => $grantedCoupons[0]['endtime']
            ];
        } catch (\Exception $e) {
            Db::rollback();
            return null;
        }
    }
}
