<?php
namespace app\api\controller\yp;

use EasyWeChat\Factory;
use app\api\model\User as UserModel;
use app\api\model\Level;
use app\api\model\Collect;
use app\api\model\UserCoupons;
use app\api\model\Order;
use app\api\service\FirstOrderCoupon;
use app\common\library\Token;
use think\Db;
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
            $config = getValues(['miniapp_id','miniapp_secret','send_integral']);
            $data = ['app_id' => $config['miniapp_id'], 'secret' => $config['miniapp_secret']];
            $app = Factory::miniProgram($data);
            $decryptSession = $app->auth->session($code);
            if (!isset($decryptSession['session_key'])) {
                $this->error('未获取session_key,请重启应用');
            }
            $this->session_key = $decryptSession['session_key'];
            $open_id = $decryptSession['openid'];
            //$open_id = 'oA0Jr7S7W9g1hvQgGY8mr5fv-cxk';
            $user_info = UserModel::where(['open_id' => $open_id])->find();
            $first_order_coupon = null;
            if($user_info){
                $res = $this->auth->direct($user_info['id']);
                if($res && $pid){
                    $first_order_coupon = FirstOrderCoupon::grant($user_info['id'], 'promoter_share', $pid);
                }
            }else{
                $extend = ['open_id' => $open_id];
                $nickname = $this->request->post('nickname');
                if($nickname){
                    $extend['nickname'] = $nickname;
                }
                $extend['avatar'] = $this->request->post('avatar')?:'/uploads/logo.png';
                if($pid && UserModel::where(['id' => $pid])->find()){
                    $extend['pid'] = $pid;
                }
                $res = $this->auth->miniRegister($extend);
                if($res && !$nickname){
                    $user = $this->auth->getUser();
                    $user->nickname = '拼豆师NO.' . $user->id;
                    $user->save();
                }
                if($res){
                    $first_order_coupon = FirstOrderCoupon::grant($this->auth->id, 'register', $pid);
                }
            }
            if($res){
                $this->userInfo($first_order_coupon);
            }
        }
        $this->error();
    }

    /**
     * 获取用户信息
     */
    public function userInfo($first_order_coupon = null){
        $this->auth->setAllowFields(['id','nickname','avatar','money','integral','username','gender','email','mobile','createtime','pid','commission']);
        $user = $this->auth->getUserinfo();
        try {
            $recipeCount = Db::name('yp_user_recipe')->where(['user_id' => $this->auth->id, 'status' => 'normal'])->count();
            $orderCount = Db::name('yp_user_recipe')->where(['user_id' => $this->auth->id, 'status' => 'normal'])->sum('order_count');
            $publicCount = 0;
            if(!empty(Db::query("SHOW COLUMNS FROM `" . \think\Config::get('database.prefix') . "yp_user_recipe` LIKE 'is_featured'"))){
                $publicCount = Db::name('yp_user_recipe')->where(['user_id' => $this->auth->id, 'status' => 'normal', 'is_featured' => 1])->count();
            }
            $user['recipe_count'] = (int)$recipeCount;
            $user['recipe_order_count'] = (int)$orderCount;
            $user['featured_recipe_count'] = (int)$publicCount;
            if($publicCount > 0){
                $user['community_title'] = '精选配方师';
            }elseif($orderCount >= 3){
                $user['community_title'] = '复刻达人';
            }elseif($recipeCount > 0){
                $user['community_title'] = '拼豆师';
            }else{
                $user['community_title'] = '咖啡探索者';
            }
        } catch (\Exception $e) {
            $user['community_title'] = '咖啡探索者';
        }
        if($this->session_key){
            $user['session_key'] = $this->session_key;
        }
        if($first_order_coupon){
            $user['first_order_coupon'] = $first_order_coupon;
            if(isset($first_order_coupon['coupons']) && is_array($first_order_coupon['coupons'])){
                $user['first_order_coupons'] = $first_order_coupon['coupons'];
            }
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
            if ($mobile) {
                if (!\think\Validate::regex($mobile, "^1\d{10}$")) {
                    $this->error(__('手机号不正确'));
                }
                $user->mobile = $mobile;
            }
            $pattern = "/^\S+@\S+\.\S+$/";
            if ($email) {
                if (!preg_match($pattern, $email)) {
                    $this->error(__('邮箱不正确'));

                }
                $user->email = $email;
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
     * 使用微信手机号快速验证组件绑定手机号
     */
    public function bindMobileByCode(){
        if(!$this->request->isPost()){
            $this->error();
        }
        $code = $this->request->post('code');
        if(!$code){
            $this->error('手机号授权失败，请重新授权');
        }
        $config = getValues(['miniapp_id','miniapp_secret']);
        $data = ['app_id' => $config['miniapp_id'], 'secret' => $config['miniapp_secret']];
        $app = Factory::miniProgram($data);
        try {
            $phoneInfo = $app->getPhoneNumber($code);
        } catch (\Exception $e) {
            $this->error('手机号验证失败，请稍后重试');
        }
        if(isset($phoneInfo['errcode']) && $phoneInfo['errcode'] != 0){
            $this->error(isset($phoneInfo['errmsg']) ? $phoneInfo['errmsg'] : '手机号验证失败');
        }
        $mobile = '';
        if(isset($phoneInfo['phone_info']['purePhoneNumber'])){
            $mobile = $phoneInfo['phone_info']['purePhoneNumber'];
        }elseif(isset($phoneInfo['phone_info']['phoneNumber'])){
            $mobile = $phoneInfo['phone_info']['phoneNumber'];
        }
        if(!$mobile || !Validate::regex($mobile, "^1\d{10}$")){
            $this->error('手机号格式错误');
        }
        $user = $this->auth->getUser();
        $user_info = UserModel::where(['mobile' => $mobile])->where('id', '<>', $user->id)->find();
        if($user_info){
            $this->loginByBoundMobile($user, $user_info, $mobile);
        }
        $user->mobile = $mobile;
        $user->save();
        $this->success('绑定成功', $this->formatLoginData($mobile, 'bind'));
    }

    /**
     * 手机号已绑定其它账号时，切换登录到该手机号账号。
     */
    protected function loginByBoundMobile($currentUser, $targetUser, $mobile){
        $oldToken = $this->auth->getToken();
        $openId = isset($currentUser['open_id']) ? $currentUser['open_id'] : '';
        if($openId){
            Db::startTrans();
            try {
                UserModel::where(['open_id' => $openId])->where('id', '<>', $targetUser['id'])->update(['open_id' => null]);
                UserModel::where(['id' => $targetUser['id']])->update(['open_id' => $openId]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('账号同步失败，请稍后重试');
            }
        }
        if(!$this->auth->direct($targetUser['id'])){
            $this->error('登录该手机号账号失败，请稍后重试');
        }
        if($oldToken){
            Token::delete($oldToken);
        }
        $this->success('已登录该手机号账号', $this->formatLoginData($mobile, 'switch'));
    }

    /**
     * 统一返回前端需要刷新的登录态和用户资料。
     */
    protected function formatLoginData($mobile, $type){
        $this->auth->setAllowFields(['id','nickname','avatar','money','integral','username','gender','email','mobile','createtime','pid','commission']);
        $user = $this->auth->getUserinfo();
        return [
            'mobile' => $mobile,
            'login_type' => $type,
            'token' => isset($user['token']) ? $user['token'] : $this->auth->getToken(),
            'userid' => isset($user['id']) ? $user['id'] : 0,
            'user' => $user
        ];
    }

    /**
     * 推广小程序码
     */
    public function code_image(){
        $path = 'uploads/code/';
        $file = 'promoter_custom_'.$this->auth->id.'.png';
        if(file_exists(ROOT_PATH.DS.'public'.DS.$path.DS.$file)){
            $this->success('成功','/'.$path.$file);
        }
        $directory = ROOT_PATH.DS.'public'.DS.$path;
        if(!is_dir($directory)){
            @mkdir($directory, 0755, true);
        }
        $config = getValues(['miniapp_id','miniapp_secret']);
        $config = ['app_id' => $config['miniapp_id'], 'secret' => $config['miniapp_secret']];
        $app = Factory::miniProgram($config);
        $response = $app->app_code->getUnlimit(http_build_query(['pid' => $this->auth->id]), [
            'page' => 'pages/huo/huo',
            'width' => 430
        ]);
        if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
            $response->saveAs($path, $file);
            $this->success('成功','/'.$path.$file);
        }
        $this->error(isset($response['errmsg']) ? $response['errmsg'] : '小程序码生成失败');
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
