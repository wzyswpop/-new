<?php
namespace app\api\model;

class Withdrawal extends Base{

    protected $name = 'yp_withdrawal';

    public static $withrawal_type = [1 => '佣金',2 => '余额'];


    public function getTypeTextAttr($value,$row){
        return self::$withrawal_type[$row['type']];
    }
}