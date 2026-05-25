<?php

namespace app\admin\controller\yp;

use app\common\controller\Backend;
use think\Db;

/**
 * 配方墙配方
 *
 * @icon fa fa-flask
 */
class UserRecipe extends Backend
{
    protected $model = null;
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\yp\UserRecipe;
        $this->view->assign("publicStatusList", $this->model->getPublicStatusList());
        $this->view->assign("isFeaturedList", $this->model->getIsFeaturedList());
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
                $row->setAttr('bean_summary', $this->beanSummary($row['recipe_data']));
                $row->setAttr('tag_summary', $this->tagSummary($row));
            }
            return json(["total" => $list->total(), "rows" => $list->items()]);
        }
        return $this->view->fetch();
    }

    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $row = $this->model->get($ids);
            if (!$row) {
                $this->error(__('No Results were found'));
            }
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->normalizeParams($params, $row);
                $row->allowField(true)->save($params);
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row['bean_summary'] = $this->beanSummary($row['recipe_data']);
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    protected function normalizeParams($params, $row)
    {
        $params['is_featured'] = isset($params['is_featured']) ? (int)$params['is_featured'] : 0;
        $params['status'] = isset($params['status']) && $params['status'] === 'hidden' ? 'hidden' : 'normal';
        unset($params['public_status']);
        if ($params['is_featured'] === 1 && $row['public_status'] !== 'public') {
            $this->error('客人未公开的私有配方不能设为精选');
        }
        if ($params['is_featured'] === 1 && empty($row['featured_at'])) {
            $params['featured_at'] = time();
        }
        if ($row['public_status'] !== 'public') {
            $params['is_featured'] = 0;
        }
        foreach (['description', 'scene_tags', 'flavor_tags', 'author_name', 'author_title'] as $field) {
            $params[$field] = isset($params[$field]) ? trim($params[$field]) : '';
        }
        return $params;
    }

    protected function beanSummary($json)
    {
        $data = json_decode((string)$json, true);
        $list = isset($data['goods_list']) && is_array($data['goods_list']) ? $data['goods_list'] : [];
        $parts = [];
        foreach ($list as $item) {
            $name = isset($item['name']) ? $item['name'] : '咖啡豆';
            $ratio = isset($item['ratio']) ? $item['ratio'] : '';
            $parts[] = $ratio !== '' ? $name . ' ' . $ratio . '%' : $name;
        }
        return implode(' / ', $parts);
    }

    protected function tagSummary($row)
    {
        $tags = [];
        foreach (['scene_tags', 'flavor_tags'] as $field) {
            if (!empty($row[$field])) {
                $tags[] = $row[$field];
            }
        }
        return implode(' / ', $tags);
    }
}
