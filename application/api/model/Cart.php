<?php
namespace app\api\model;

class Cart extends Base{

    protected $name = 'yp_cart';

    public function goods(){
        return $this->belongsTo(Goods::class,'goods_id','id');
    }

    public function stock(){
        return $this->belongsTo(SkuPrice::class,'stock_id','id');
    }
}