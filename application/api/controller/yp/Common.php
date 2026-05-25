<?php
namespace app\api\controller\yp;

use app\common\exception\UploadException;
use app\common\library\Upload;
use app\api\model\Goods;
use app\api\model\SkuPrice;

class Common extends Base{

    protected $noNeedLogin = ['indexdata','getconfig'];

    /**
     * 获取配置项
     */
    public function getConfig(){
        $name = $this->request->param('name/a');
        if(!$name){
            $this->error();
        }
        $arr = ['commission','service','privacy','share_image','share_title','vip_article','withdrawal_service','sign_article','avatar'];
        if(is_array($name)){
            foreach ($name as $v){
                if(!in_array($v,$arr)){
                    $this->error();
                }
            }
        }elseif(!in_array($name,$arr)){
            $this->error();
        }
        $config = getValues($name,true);
        $this->success('成功',$config);
    }

    /**
     * 图片上传
     */
    public function upload()
    {
        //默认普通上传文件
        $file = $this->request->file('file');
        if(!in_array($file->getMime(),['image/png','image/jpg','image/jpeg'])){
            $this->error('文件格式错误');
        }
        try {
            $upload = new Upload($file);
            $attachment = $upload->upload();
        } catch (UploadException $e) {
            $this->error($e->getMessage());
        }
        $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
    }

    /**
     * 首页数据
     */
    public function indexData(){
        $config = getValues(['index_carousel','index_video'],true);
        $carousel = $config['index_carousel'];
        $video = $config['index_video'];
        $goods_list = Goods::where(['is_hot' => 1,'status' => '1','is_shop_sale' => 1])->field('id,name,money,category_id,image,is_stock')->order('weigh desc,createtime desc')->select();
        foreach ($goods_list as $v){
            $v->append(['goods_category']);
            $money = SkuPrice::where(['goods_id' => $v['id'], 'status' => 'up'])
                ->where('stock', '>', 0)
                ->min('money');
            if ($money !== null && $money !== '') {
                $v['money'] = number_format((float)$money, 2, '.', '');
            }
        }
        $this->success('成功',compact('carousel','video','goods_list'));
    }
}
