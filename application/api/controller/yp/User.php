<?php
namespace app\api\controller\yp;

use EasyWeChat\Factory;
use app\api\model\User as UserModel;
use app\api\model\Level;
use app\api\model\Collect;
use app\api\model\UserCoupons;
use app\api\model\Order;
use think\Validate;

class User extends Base{

    protected $noNeedLogin = ['minilogin'];

    protected $session_key;

    /**
     * 小程序登录
     */
    public function miniLogin(){
        if($this->request->isPost()){
            $code = $this->request->post('code');
            if(!$code){
                $this->error('参数错误');
            }
            $pid = $this->request->param('pid');
           /* $config = getValues(['miniapp_id','miniapp_secret','send_integral']);
            $data = ['app_id' => $config['miniapp_id'], 'secret' => $config['miniapp_secret']];
            $app = Factory::miniProgram($data);
            $decryptSession = $app->auth->session($code);
            if (!isset($decryptSession['session_key'])) {
                $this->error('未获取session_key,请重启应用');
            }
            $this->session_key = $decryptSession['session_key'];
            $open_id = $decryptSession['openid'];*/
            $open_id = 'obF8q5Gpjr3BvzAaU8RYnpU72JwU';
            $user_info = UserModel::where(['open_id' => $open_id])->find();
            if($user_info){
                $res = $this->auth->direct($user_info['id']);
            }else{
                $extend = ['open_id' => $open_id];
                $extend['nickname'] = $this->request->post('nickname')?:'迷失的小鹿';
                $extend['avatar'] = $this->request->post('avatar')?:'/uploads/logo.png';
                if($pid && UserModel::where(['id' => $pid])->find()){
                    $extend['pid'] = $pid;
                }
                $res = $this->auth->miniRegister($extend);
            }
            if($res){
                $this->userInfo();
            }
        }
        $this->error();
    }

    /**
     * 获取用户信息
     */
    public function userInfo(){
        $this->auth->setAllowFields(['id','nickname','avatar','money','integral','username','gender','email','mobile','createtime','pid','commission']);
        $user = $this->auth->getUserinfo();
        if($this->session_key){
            $user['session_key'] = $this->session_key;
        }
        $this->success('成功', $user);
    }

    /**
     * 修改昵称|头像|性别|年龄
     */
    public function profile() {
        if($this->request->isPost()){
            $nickname = $this->request->post('nickname');
            $avatar = $this->request->post('avatar');
            $email = $this->request->post('email');
            $mobile = $this->request->post('mobile');
            $gender = $this->request->post('gender');
            $username = $this->request->post('username');
            $user = $this->auth->getUser();
            if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
                $this->error(__('手机号不正确'));
            }
            $pattern = "/^\S+@\S+\.\S+$/";
            if (!$email || !preg_match($pattern, $email)) {
                $this->error(__('邮箱不正确'));
            }
            if ($nickname) {
                if(strlen($nickname) > 20){
                    $this->error('昵称长度超出');
                }
                $user->nickname = $nickname;
            }
            if ($avatar) {
                $user->avatar = $avatar;
            }
            if($username){
                $user->username = $username;
            }
            if($gender && in_array($gender,[1,2])){
                $user->gender = $gender;
            }
            $user->save();
            $this->success('修改成功');
        }
        $this->error();
    }

    /**
     * 绑定上级
     */
    public function bind(){
        if($this->auth->pid != 0){
            $this->error('该用户已被绑定');
        }
        $uid = $this->request->param('uid');
        if($this->auth->id == $uid){
            $this->error('不能绑定自己');
        }
        $user_info = UserModel::where(['id' => $uid])->find();
        if(!$user_info){
            $this->error('用户不存在');
        }
        $user = $this->auth->getUser();
        $user->pid = $uid;
        $user->save();
        $this->success('成功');
    }

    /**
     * 注销登录
     */
    public function logout() {
        $this->auth->logout();
        $this->success();
    }

    /**
     * 绑定手机号
     */
    public function bindMobile(){
        $session_key = $this->request->post('session_key');
        $iv = $this->request->post('iv');
        $encryptedData = $this->request->post('encryptedData');
        if(!$session_key || !$iv || !$encryptedData){
            $this->error();
        }
        $config = getValues(['miniapp_id','miniapp_secret']);
        $data = ['app_id' => $config['miniapp_id'], 'secret' => $config['miniapp_secret']];
        $app = Factory::miniProgram($data);
        $decryptUserInfo = $app->encryptor->decryptData($session_key, $iv, $encryptedData);
        if(isset($decryptUserInfo['purePhoneNumber'])){
            $mobile = $decryptUserInfo['purePhoneNumber'];
            $user_info = UserModel::where(['mobile' => $decryptUserInfo['purePhoneNumber']])->find();
            if(!$user_info){
                $user = $this->auth->getUser();
                $user->mobile = $mobile;
                $user->save();
                $this->success('成功',$mobile);
            }
            $this->error('改手机号已被绑定');
        }
        $this->error();
    }

    /**
     * 邀请海报
     */
    public function code_image(){
        $path = 'uploads/code/';
        $file = $this->auth->id.'.png';
        if(file_exists(ROOT_PATH.DS.'public'.DS.$path.DS.$file)){
            $this->success('成功','/'.$path.$file);
        }
        $data = ['uid' => $this->auth->id];
        $config = getValues(['miniapp_id','miniapp_secret']);
        $config = ['app_id' => $config['miniapp_id'], 'secret' => $config['miniapp_secret']];
        $app = Factory::miniProgram($config);
        $response = $app->app_code->getUnlimit(http_build_query($data));
        if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
            $response->saveAs($path, $file);
        }
        $this->success('成功','/'.$path.$file);
    }

    /**
     * 会员中心
     */
    public function userMember(){
        $level = Level::where(['id' => $this->auth->level_id])->find();
        $next = Level::where(['weigh' => ['>',$level['weigh']]])->order('weigh asc')->find();
        $this->success('成功',compact('level','next'));
    }
}