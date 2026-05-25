<?php
namespace app\api\model;

class MoneyLog extends Base{

    protected $name = 'yp_money_log';


    public static $withrawal_type = ['money' => '余额','commission' => '佣金'];


    public function getClassifyTextAttr($value,$row){
        return self::$withrawal_type[$row['classify']];
    }
}