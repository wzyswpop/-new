<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\Config as ConfigModel;
use think\Config as ThinkConfig;
use think\Db;
use think\Exception;

/**
 * 分享海报图
 *
 * @icon fa fa-share-alt
 */
class SharePoster extends Backend
{
    protected $noNeedRight = ['index'];

    public function _initialize()
    {
        if ($this->request->isPost()) {
            ThinkConfig::set('default_return_type', 'json');
        }
        parent::_initialize();
    }

    public function index()
    {
        if ($this->request->isPost()) {
            $this->token();
            $row = $this->request->post('row/a', [], 'trim');
            if (!$row) {
                $this->error(__('Parameter %s can not be empty', ''));
            }

            $shareImage = isset($row['share_image']) ? trim($row['share_image']) : '';
            $shareTitle = isset($row['share_title']) ? trim($row['share_title']) : '';
            if ($shareImage === '') {
                $this->error('请先上传分享海报图');
            }

            Db::startTrans();
            try {
                $this->saveConfig('share_image', $shareImage, '分享海报图', 'image');
                $this->saveConfig('share_title', $shareTitle, '分享标题', 'string');
                ConfigModel::refreshFile(false);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            $this->success('保存成功');
        }

        $this->view->assign('shareImage', $this->getConfigValue('share_image'));
        $this->view->assign('shareTitle', $this->getConfigValue('share_title'));
        return $this->view->fetch();
    }

    protected function getConfigValue($name)
    {
        $config = ConfigModel::getByName($name);
        return $config ? $config['value'] : '';
    }

    protected function saveConfig($name, $value, $title, $type)
    {
        $config = ConfigModel::getByName($name);
        if ($config) {
            $config->allowField(true)->save(['value' => $value]);
            return;
        }

        ConfigModel::create([
            'name' => $name,
            'group' => 'distribution',
            'title' => $title,
            'tip' => '',
            'type' => $type,
            'value' => $value,
            'content' => '',
            'rule' => '',
            'extend' => '',
            'setting' => '',
        ], true);
    }
}
