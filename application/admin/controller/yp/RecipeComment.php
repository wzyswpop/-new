<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;

/**
 * 配方墙短评
 *
 * @icon fa fa-comments
 */
class RecipeComment extends Backend
{
    protected $model = null;
    protected $relationSearch = true;
    protected $multiFields = 'status';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\RecipeComment;
        $this->view->assign("sourceTypeList", $this->model->getSourceTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $row) {
                $row->getRelation('user')->visible(['nickname', 'mobile']);
            }
            return json(["total" => $list->total(), "rows" => $list->items()]);
        }
        return $this->view->fetch();
    }

    public function multi($ids = null)
    {
        $ids = $ids ?: $this->request->post('ids');
        $params = $this->request->post('params');
        parse_str((string)$params, $values);
        $status = isset($values['status']) && $values['status'] === 'hidden' ? 'hidden' : 'normal';
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $rows = $this->model->where($this->model->getPk(), 'in', $ids)->select();
        $count = 0;
        foreach ($rows as $row) {
            $count += $row->allowField(true)->isUpdate(true)->save(['status' => $status, 'updatetime' => time()]);
            $this->refreshCommentCount($row['source_type'], $row['source_id']);
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were updated'));
    }

    protected function refreshCommentCount($sourceType, $sourceId)
    {
        $sourceType = $sourceType === 'official' ? 'official' : 'user';
        $sourceId = (int)$sourceId;
        if (!$sourceId) {
            return;
        }
        $count = Db::name('yp_recipe_comment')->where([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'normal'
        ])->count();
        $exists = Db::name('yp_recipe_interaction')->where(['source_type' => $sourceType, 'source_id' => $sourceId])->find();
        if ($exists) {
            Db::name('yp_recipe_interaction')->where('id', $exists['id'])->update(['comment_count' => $count, 'updatetime' => time()]);
        } else {
            Db::name('yp_recipe_interaction')->insert([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'comment_count' => $count,
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
    }
}
