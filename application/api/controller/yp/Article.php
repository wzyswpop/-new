<?php
namespace app\api\controller\yp;

use app\api\model\Article as ArticleModel;
use app\api\model\ArticleLike;
use app\api\model\ArticleRelish;

class Article extends Base{


    /**
     * 取消|收藏
     */
    public function relish(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = ArticleModel::where(['status' => '1','id' => $id])
            ->find();
        if(!$info){
            $this->error();
        }
        $like = ArticleRelish::where(['user_id' => $this->auth->id,'article_id' => $id])->find();
        if($like){
            $like->delete();
        }else{
            ArticleRelish::insert([
                'user_id' => $this->auth->id,
                'article_id' => $id,
                'createtime' => time()
            ]);
        }
        $this->success();
    }

    /**
     * 文章列表
     */
    public function lists(){
        $list = ArticleModel::where(['status' => '1'])
            ->field('id,name,createtime,author,image')
            ->order('createtime desc')
            ->paginate()
            ->each(function ($key){
                $key['createtime'] = format($key['createtime']);
                $key['is_like'] = ArticleLike::where(['user_id' => $this->auth->id,'article_id' => $key['id']])->find() ? 1 : 0;
                $key['is_relish'] = ArticleRelish::where(['user_id' => $this->auth->id,'article_id' => $key['id']])->find() ? 1 : 0;
                return $key;
            });
        $this->success('成功',$list);
    }

    /**
     * 文章详情
     */
    public function info(){
        $id = $this->request->param('id');
        $info = ArticleModel::where(['id' => $id,'status' => '1'])
            ->field('status',true)
            ->find();
        if($info){
            $info->setInc('see');
            $info['is_like'] = ArticleLike::where(['user_id' => $this->auth->id,'article_id' => $info['id']])->find() ? 1 : 0;
            $info['is_relish'] = ArticleRelish::where(['user_id' => $this->auth->id,'article_id' => $info['id']])->find() ? 1 : 0;
            $info['createtime'] = format($info['createtime']);
            $info['content'] = str_replace('src="/uploads','src="https://'.$this->request->host().'/uploads',$info['content']);
            $this->success('成功',$info);
        }else{
            $this->error('笔记不存在');
        }
    }

    /**
     * 取消|收藏
     */
    public function like(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error();
        }
        $info = ArticleModel::where(['status' => '1','id' => $id])
            ->find();
        if(!$info){
            $this->error();
        }
        $like = ArticleLike::where(['user_id' => $this->auth->id,'article_id' => $id])->find();
        if($like){
            $like->delete();
        }else{
            ArticleLike::insert([
                'user_id' => $this->auth->id,
                'article_id' => $id,
                'createtime' => time()
            ]);
        }
        $this->success();
    }
}