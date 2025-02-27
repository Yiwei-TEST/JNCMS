<?php

namespace app\admin\controller;
use app\admin\model\UserType;
use app\admin\model\MemberGroupModel;
use think\Controller;
use think\Db;
use org\Verify;
use com\Geetestlib;

class Login extends Controller
{

    /**
     * 登录页面
     * @return
     */
    public function index()
    {
        $this->assign('verify_type', config('verify_type'));
        return $this->fetch('/login');
    }


    /**
     * 登录操作
     * @return
     */
    public function doLogin()
    {
        $username = input("param.username");
        $password = input("param.password");

        if (config('verify_type') == 1) {
            $code = input("param.code");
        }

        $result = $this->validate(compact('username', 'password'), 'MemberValidate');
        if(true !== $result){
            return json(['code' => -5, 'url' => '', 'msg' => $result]);
        }
        $verify = new Verify();
        if (config('verify_type') == 1) {
            if (!$code) {
                return json(['code' => -4, 'url' => '', 'msg' => '请输入验证码']);
            }
            if (!$verify->check($code)) {
                return json(['code' => -4, 'url' => '', 'msg' => '验证码错误']);
            }
        }

        $hasUser = Db::name('member')->where('account', $username)->find();
        if(empty($hasUser)){
            return json(['code' => -1, 'url' => '', 'msg' => '管理员不存在']);
        }

        if(md5(md5($password) . config('auth_key')) != $hasUser['password']){
            writelog($hasUser['id'],$username,'用户【'.$username.'】登录失败：密码错误',2);
            return json(['code' => -2, 'url' => '', 'msg' => '账号或密码错误']);
        }

        if(1 != $hasUser['status']){
            writelog($hasUser['id'],$username,'用户【'.$username.'】登录失败：该账号被禁用',2);
            return json(['code' => -6, 'url' => '', 'msg' => '该账号被禁用']);
        }
        $token = md5($hasUser['nickname'] . $hasUser['password'] . $hasUser['id'] . time());
        if(empty($hasUser['qr_code']) && $hasUser['group_id']==3 || empty($hasUser['qr_url']) && $hasUser['group_id']==3 ){
            $this->addurlcode($hasUser['id'],$token);
        };

        //获取该管理员的角色信息
        $user = new MemberGroupModel();
        $info = $user->getRoleInfo($hasUser['group_id']);
        session('mid', $hasUser['id']);         //用户ID
        session('username', $hasUser['nickname']);  //用户名
        session('rolename', $info['title']);    //角色名
        session('rule', $info['rules']);        //角色节点
        session('name', $info['name']);         //角色权限

        //更新管理员状态
        $param = [
            'login_num' => intval($hasUser['login_num']) + 1,
            'last_login_ip' => request()->ip(),
            'last_login_time' => time(),
            'token' => md5($hasUser['nickname'] . $hasUser['password'])
        ];

        Db::name('member')->where('id', $hasUser['id'])->update($param);
        writelog($hasUser['id'],session('nickname'),'用户【'.session('nickname').'】登录成功',1);
        return json(['code' => 1, 'url' => url('index/index'), 'msg' => '登录成功！']);
    }

    protected function addurlcode($id,$token){
        $domain = domain();
        $url    = $domain."/index/login/register?s_nid=".$id;
        $qrcode = Qrcode($token,$url);
        $data['qr_code'] = $qrcode;
        $data['qr_url'] = $url;
        return  Db::name('member')->where('id',$id)->update($data);
    }
    /**
     * 验证码
     * @return
     */
    public function checkVerify()
    {
        $verify = new Verify();
        $verify->imageH = 32;
        $verify->imageW = 100;
		$verify->codeSet = '0123456789';
        $verify->length = 4;
        $verify->useNoise = false;
        $verify->fontSize = 14;
        return $verify->entry();
    }


    /**
     * 退出登录
     * @return
     */
    public function loginOut()
    {
        session(null);
        cache('db_config_data',null);//清除缓存中网站配置信息
        $this->redirect('login/index');
    }
}
