<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use think\Db;
use think\Exception;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        $this->redirect('/ht_admin.php');
        return $this->view->fetch();
    }

    public function userData(){
        return $this->fetch();
    }


    public function getPage(){
        $list = Db::name('yp_user_browse')
            ->alias('a')
            ->join('user b','a.user_id = b.id')
            ->field('a.*,b.nickname,b.avatar')
            ->order('createtime desc')
            ->paginate()
            ->each(function ($row){
                $row['createtime'] = date('Y-m-d H:i:s',$row['createtime']);
                return $row;
            });
        $this->success('成功','',$list);
    }
}
