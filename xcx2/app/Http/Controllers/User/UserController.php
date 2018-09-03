<?php
/**
 * Created by PhpStorm.
 * User: zhangyingren
 * Date: 2018/4/10
 * Time: 16:34
 */

namespace App\Http\Controllers\User;


use App\Http\Controllers\Controller;
use App\Http\Model\Question;
use App\Http\Model\UserRepository;
use App\Libs\CommonFunc;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use DB;

include(dirname(__FILE__)."/wxdatacrypt/wxBizDataCrypt.php");

class UserController extends Controller
{

    /*
     * 小程序登录解密用户敏感数据入库
     * @param encryptedData 明文,加密数据
     * @param iv            加密算法的初始向量
     * @param code          用户允许登录后，回调内容会带上 code（有效期五分钟），开发者需要将 code 发送到开发者服务器后台，将 code 换成 openid 和 session_key
     * @param repository_id 题集id
     * @return array
     */

    public function miniProgramLoginGetUserInfo(Request $request){
        $appid = env('WECHAT_APP_ID','');
        $secret = env('WECHAT_APP_SECRET','');

        $code = $request->code;
        $encryptedData = $request->encryptedData;
        $iv = $request->iv;
        $repository_id = (int)$request->id;

        if(empty($repository_id)){
            return CommonFunc::_fail('请选择题集');
        }

        //===根据code获取openid和session_key=======
        $res = $this->getOpenidAndSessionkeyOfCode($code);
        $openid = $res['openid'];
        $session_key = $res['session_key'];

        //================解密数据=================
        $pc = new \WXBizDataCrypt($appid,$session_key);
        $errCode = $pc->decryptData($encryptedData, $iv, $data );
        //@todo是否需要解析
        if ($errCode == 0) {
            $userInfo = json_decode($data,true);
        } else {
            return $errCode;
        }
        //================入库====================
        $user = User::where(
            [
                'openid'    => $userInfo['openId'],
            ]
        )->first();

        if(empty($user)){
            $user = new User();
            $user->openid = $userInfo['openId'];
        }

        $user->nickname = $userInfo['nickName'];
        $user->union_id = null;
        $user->user_info = $data;
        $user->save();
        $this->updateCache($userInfo['openId'],$user->id);

        //判断状态
        $userRepositoryObj = UserRepository::where(['user_id'=>$user->id,'repository_id'=>$repository_id])->first();
        if(empty($userRepositoryObj)){
            $res['state'] = UserRepository::STATE_WAIT_INPUT_INFO;
            $res['aganswer'] = 0;
        }else{
            $res['state'] = $userRepositoryObj->state;
            $specialDealer = ['27077'];
            if(in_array($userRepositoryObj->cbu_id,$specialDealer) ){
                $res['aganswer'] = 1;
            }else{
                $res['aganswer'] = 0;
            }
        }

        //将openid和session_key返回前端
        return CommonFunc::_success($res);

    }

    /*
     * 根据code获取openid和session_key
     * @param  code 前端登录后的传值
     * @return array
     */
    public function getOpenidAndSessionkeyOfCode($code){
        $appid = env('WECHAT_APP_ID','');
        $secret = env('WECHAT_APP_SECRET','');
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$appid.'&secret='.$secret.'&js_code='.$code.'&grant_type=authorization_code';
        $info = file_get_contents($url);
        $res = json_decode($info,true);

        return $res;
    }

    /*
     * 登录成功后填写个人信息，保存接口
     * @param openid
     * @return
     * @phone 添加手机号
     */
    /*
    public function saveUserExtraInfo(Request $request){
        $openid = $request->openid;
        $cbuid = $request->cbu_id;
        $cbuname = $request->cbu_name;
        $username = $request->username;
        $repository_id = $request->id;

        if(empty($repository_id)){
            return CommonFunc::_fail('请选择题集');
        }

        $isCbuExists = DB::table('cbu_tb')->where('cbu_id',$cbuid)->first();
        if(empty($isCbuExists)){
            return CommonFunc::_fail('经销商id不存在');
        }

        $isCbuAnswer = UserRepository::where('cbu_id',$cbuid)->first();
        if(!empty($isCbuAnswer)){
            return CommonFunc::_fail('该经销商id已有答题人参与答题');
        }

        $user_id = $this->getUserIdFromCache($openid);
        if(empty($user_id)){
            return CommonFunc::_fail('该用户不存在');
        }

        $extraInfo = [
            'cbu_id' => $cbuid,
            'cbu_name' => $cbuname,
            'user_name' => $username,
        ];

        $isExists = UserRepository::where(['user_id'=>$user_id,'repository_id'=>$repository_id])->first();
        if(empty($isExists)){
            $res = UserRepository::create([
                'user_id'  => $user_id,
                'repository_id'  => $repository_id,
                'cbu_id'  => $cbuid,
                'info' => json_encode($extraInfo,JSON_UNESCAPED_UNICODE),
                'state' => UserRepository::STATE_WAIT_ANSWER,
                'created_at'=> date("Y-m-d H:i:s",time())
            ]);

            if($res){
                return CommonFunc::_success([],'保存成功');
            }else{
                return CommonFunc::_fail('保存失败');
            }
        }

        return CommonFunc::_success([],'保存成功');
    }*/

    public function saveUserExtraInfo(Request $request){
        $openid = $request->openid;
        $cbuid = $request->cbu_id;
        $cbuname = $request->cbu_name;
        $username = $request->username;
        $repository_id = $request->id;
        $phone = $request->phone;

        if(empty($repository_id)){
            return CommonFunc::_fail('请选择题集');
        }

        $isCbuExists = DB::table('cbu_tb')->where('cbu_id',$cbuid)->first();
        if(empty($isCbuExists)){
            return CommonFunc::_fail('经销商id不存在');
        }

        $user_id = $this->getUserIdFromCache($openid);
        if(empty($user_id)){
            return CommonFunc::_fail('该用户不存在');
        }

        //判断经销商是否为特殊经销商, 是 同一经销商下是否存在该用户，存在过滤 否 判断表中是否有该经销商商 存在抛出异常
        //用户再次登录状态不会改变
        $specialDealer = ['27077'];
        if(in_array($cbuid,$specialDealer)){
            $userId = User::where(['openid'=>$openid])->first();
            $isCbuAnswer = UserRepository::where(['cbu_id'=>$cbuid,'user_id'=>$userId->id,'repository_id'=>$repository_id])->first();
            if(!empty($isCbuAnswer)){
                null;
            }
        }else{
            $isCbuAnswer = UserRepository::where('cbu_id',$cbuid)->first();
            if(!empty($isCbuAnswer)){
                return CommonFunc::_fail('该经销商id已有答题人参与答题');
            }
        }

        $extraInfo = [
            'cbu_id' => $cbuid,
            'cbu_name' => $cbuname,
            'user_name' => $username,
            'phone' => $phone,
        ];

        $isExists = UserRepository::where(['user_id'=>$user_id,'repository_id'=>$repository_id])->first();
        if(empty($isExists)){
            $res = UserRepository::create([
                'user_id'  => $user_id,
                'repository_id'  => $repository_id,
                'cbu_id'  => $cbuid,
                'info' => json_encode($extraInfo,JSON_UNESCAPED_UNICODE),
                'state' => UserRepository::STATE_WAIT_ANSWER,
                'created_at'=> date("Y-m-d H:i:s",time())
            ]);

            if($res){
                return CommonFunc::_success([],'保存成功');
            }else{
                return CommonFunc::_fail('保存失败');
            }
        }

        return CommonFunc::_success([],'保存成功');
    }

    /*
     * 跟新缓存
     */
    public function updateCache($openid,$user_id){
        $getUserIdOfOpnidKey = "userid_of_openid_{$openid}";
        if(Cache::has($getUserIdOfOpnidKey)){
            Cache::forget($getUserIdOfOpnidKey);
            Cache::set($getUserIdOfOpnidKey,$user_id,User::CACHE_HAS_ANSWERED);
        }
    }

    /*
     * 从缓存获取user_id
     */
    public function getUserIdFromCache($openid){
        $getUserIdOfOpnidKey = "userid_of_openid_{$openid}";
        $isCacheUserId = Cache::get($getUserIdOfOpnidKey);

        if(!$isCacheUserId){
            $user_info = DB::table('user_tb')->where('openid',$openid)->first();
            if(!empty($user_info)){
                $user_id = $user_info->id;
            }else{
                $user_id = "";
            }
            Cache::set($getUserIdOfOpnidKey,$user_id,User::CACHE_HAS_ANSWERED);
        }else{
            $user_id = $isCacheUserId;
        }

        return $user_id;
    }

    /*
     * 参与调查用户数据统计
     * @param  repository_id 题集id
     * @return array
     */
    public function joinQuestionUserStatistics(Request $request){
        $repository_id = $request->input('id',1);
        $page = (int)$request->input('page', 1);
        $length = (int)$request->input('length', 10);
        $offset = ($page-1)*$length;

        if(empty($repository_id)){
            return CommonFunc::_fail('请选择题集');
        }

        $obj = DB::table('user_repository_tb')->where('repository_id',$repository_id)->where('state','!=',0);

        $totalObj = clone $obj;
        $listObj = clone $obj;

        $total = $totalObj->count();
        $list = $listObj->select('user_id','info','created_at')->offset($offset)->limit($length)->get()->toArray();

        $res = [];
        foreach ($list as $lk=>$lv){
            $repositoryid = DB::table('user_expert_tb')->where(['user_id'=>$lv->user_id,'repository_id'=>$repository_id])->select('is_expertgroup')->first();
            $is_expertgroup = !empty($repositoryid)?$repositoryid->is_expertgroup:0; //判断用户是否加入专家组
            $userInfo = [];
            $num = $offset+$lk+1;
            $userInfo = json_decode($lv->info,true);
            $CBUID = $userInfo['cbu_id'];
            $username = $userInfo['user_name'];
            $CBUNAME = $userInfo['cbu_name'];
            $phone = $userInfo['phone'];
            $answerTime = $lv->created_at;
            
            $res[] = [
                'num'=>$num,
                'cbu_id'=>$CBUID,
                'user_name'=>$username,
                'cbu_name'=>$CBUNAME,
                'is_expertgroup' => $is_expertgroup,
                'phone'=>$phone,
                'answer_time'=>date("m-d H:i:s",strtotime($answerTime))
            ];
        }
        
        return CommonFunc::_success(['total'=>$total,'rows'=>$res]);

    }

    public function test(){
        $user = new User();
        $user = User::find(6);
        $user->nickname = 'haha12';
        $user->openid = '4564654564654';
        $user->union_id = '898989898';
        $user->user_info = json_encode(['username'=>1,'age'=>22],JSON_UNESCAPED_UNICODE);
        $user->save();
        dd($user->id);
    }


    public function experts(Request $request){
        try{
            $userId = $request->openid;
            $is_expert = $request->expert;
            $repository_id = $request->input('id',1);

            $user = DB::table('user_tb')->where(['openid'=>$userId])->first();
            if(empty($user)){
                return CommonFunc::_fail('该用户不存在');
            }
            $repository = DB::table('user_repository_tb')->where(['user_id'=>$user->id,'state'=>3,'repository_id'=>$repository_id])->first();
            if(empty($repository)){
                return CommonFunc::_fail('未答题或已完成领奖');
            }
            $is_existence = DB::table('user_expert_tb')->where(['user_id'=>$user->id,'repository_id'=>$repository_id])->first();
            if (!empty($is_existence)){
                DB::table('user_expert_tb')->where(['user_id'=>$user->id,'repository_id'=>$repository_id])->delete();
            }
            DB::beginTransaction();
            $expert = DB::table('user_expert_tb')->insert(['user_id'=>$user->id,'is_expertgroup'=>$is_expert,'repository_id'=>$repository_id]);
            $update = DB::table('user_repository_tb')->where(['user_id'=>$user->id,'repository_id'=>$repository_id])->update(['state'=>1]);
            if($expert && $update){
                DB::commit();
                return CommonFunc::_success([],'保存成功');
            }else{
                DB::rollBack();
                return CommonFunc::_fail('保存失败');
            }
        } catch (\Exception $e){
            DB::rollBack();
            return $this->fail($e->getMessage());
        }
    }
}