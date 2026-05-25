<?php
namespace app\api\controller\yp;

use app\common\controller\Api;
use think\Loader;

class Base extends Api{

    protected $noNeedRight = '*';

    /**
     * 操作失败返回的数据
     */
    protected function error($msg = '网络错误', $data = null, $code = 0, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 操作成功返回的数据
     */
    protected function success($msg = '成功', $data = null, $code = 1, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 验证
     */
    protected function checkData($data,$class = '',$action = '') {
        if(!$class){
            $controller = $this->request->controller();
            if(strpos($controller,'.')){
                $class = ucwords(explode('.',$controller)[1]);
            }else{
                $class = ucwords($controller);
            }
        }
        $validate = Loader::validate('\app\api\validate\\'.$class);
        if (!$action) {
            $action = $this->request->action();
        }
        if (!$validate->scene($action)->check($data)) {
            $this->error($validate->getError());
        }
    }
}
