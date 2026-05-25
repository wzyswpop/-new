<?php

namespace app\api\controller\yp;

use app\common\controller\Api;
use think\Request;
use think\Exception;

class Kuaidi extends Api {

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = '*';
    protected $model;

    public function __construct(Request $request = NULL) {
        parent::__construct($request);
    }

    public function callBack()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isPost()) {
            $kuaidi = model('app\api\model\KuaidiSub');
            $post = $this->request->post();
            // 接收消息
            try {
                $param = json_decode($post["param"], true);
                $status = $param['status']; // 状态 polling:监控中，shutdown:结束，abort:中止，updateall：重新推送
                $message = $param['lastResult']['message']; // 消息体
                $state = $param['lastResult']['state']; // 快递单当前状态，包括0在途，1揽收，2疑难，3签收，4退签，5派件，6退回，7转投
                $ischeck = $param['lastResult']['ischeck']; // 是否签收标记
                $nu = $param['lastResult']['nu']; // 快递单号
                $com = $param['lastResult']['com']; // 快递公司编码
                $data = $param['lastResult']['data']; // 数组，包含多个对象，每个对象字段如展开所示
                // 查询快递是否存在
                $express = $kuaidi->get(['express_no' => $nu]);
                if($express){
                    $express->message = $message;
                    $express->status = $status;
                    $express->state = $state;
                    $express->ischeck = $ischeck;
                    $express->com = $com;
                    $express->data = json_encode($data);
                    $express->save();
                    // 判断更新状态
                    if($express){
                        return json(["result" => true, "returnCode" => "200", "message" => "接收成功"]);
                    }
                }else{
                    return json(["result" => false, "returnCode" => "404", "message" => "快递单号不存在"]);
                }
            } catch (Exception $e) {
                return json(["result" => false, "returnCode" => "500", "message" => "服务器错误"]);
            }
        }
        return json(["result" => false, "returnCode" => "500", "message" => "非正常访问"]);
    }
}