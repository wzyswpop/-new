<?php
namespace app\api\model;

use think\Model;

class Freight extends Model
{

    protected $name = 'yp_freight';

    public function freightdata()
    {
        return $this->hasMany(FreightData::class, 'freight_id', 'id', [], 'LEFT');
    }

}