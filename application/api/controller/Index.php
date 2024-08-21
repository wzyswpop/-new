<?php
namespace app\api\controller;

use app\admin\model\Banner;
use app\admin\model\Feedback;
use app\api\controller\yp\Base;
use think\Request;
use think\Db;
use think\Exception;
use app\common\model\Config;
class Index extends  Base
{
    protected $noNeedLogin = ['doc','indexData'];
    protected $noNeedRight = '*';

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
    }

    public function doc($type)
    {
        $info = Config::where(['group'=>'article','name'=>$type])->find();
        $this->success('ok',$info);
    }
    public function submitFeedback()
    {
        $data = [];
        $content = $this->request->post('content');
        if(!$content){
            $this->error('请输入内容');
        }
        $data['content'] = $content;
        $images = $this->request->post('images');
        if($images){
            $data['images'] = $images;
        }
        $data['user_id'] = $this->auth->id;
        $data['createtime']  = time();
        $data['updatetime']  = time();
        Feedback::insert($data);
        $this->success('提交成功');
    }
    public function feedbackList()
    {
       $list = Feedback::where('user_id',$this->auth->id)->order('createtime','desc')->select();
       if($list){
           foreach ($list as $k=>&$v){
               $v['images'] = explode(',',$v['images']);
           }
       }
       $this->success('ok',$list);
    }

    public function indexData()
    {
        $banner = Banner::where('status',1)->order('weigh','desc')->select();
        $type = $this->request->post('type');
        if($type){
            $list = \app\api\model\Goods::where('status',1)->where('is_customized',0)->where('is_hot',1)->where('classify',$type)->field('id,name,status,is_customized,is_hot,classify,weigh,money,image,sales')->order('weigh','desc')->paginate();
        }else{
            $list = \app\api\model\Goods::where('status',1)->where('is_customized',0)->where('is_hot',1)->field('id,name,status,is_customized,is_hot,classify,weigh,money,image,sales')->order('weigh','desc')->paginate();
        }
        $this->success('ok',compact('banner','list'));
    }




}