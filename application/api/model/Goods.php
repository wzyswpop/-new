<?php
namespace app\api\model;

class Goods extends Base{

    protected $name = 'yp_goods';

    public function stock(){
        return $this->hasMany(SkuPrice::class,'goods_id','id')->where(['status' => 'up']);
    }

    protected function getSkuAttr($value, $data)
    {
        $sku = Sku::all([
            'goods_id'=>$data['id'],
            'pid' => 0,
        ]);
        foreach ($sku as $s => &$k) {
            $sku[$s]['content'] = Sku::all([
                'goods_id' => $data['id'],
                'pid' => $k['id']
            ]);
        }
        return $sku;
    }
}