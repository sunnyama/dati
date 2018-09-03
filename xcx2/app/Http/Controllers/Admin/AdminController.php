<?php
/**
 * 小程序后台
 */
namespace app\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Libs\CommonFunc;
use Illuminate\Http\Request;
use App\Http\Model\Admin;
use Illuminate\Support\Facades\Crypt;

class AdminController extends Controller
{
    /**
     * 登录
     */
    public function login(Request $request)
    {
        $phone = $request->phone;
        $pwd = $request->pwd;

        $admin = Admin::where('admin_phone',$phone)->first();
        if($admin)
        {
            if($pwd == Crypt::decrypt($admin->admin_pwd))
            {
                return CommonFunc::_success(null,1);
            } else {
                return CommonFunc::_fail(0,'密码有误');
            }

        } else {
            return CommonFunc::_fail(0,'手机号有误');
        }

    }


    //添加账号
    public function addAdmin(Request $request)
    {
        $phone = $request->phone;
        $pwd = $request->pwd;

        $admin = Admin::create([
            'admin_phone'=>$phone,
            'admin_pwd'=>Crypt::encrypt($pwd),
        ]);
        dd($admin);
    }
}