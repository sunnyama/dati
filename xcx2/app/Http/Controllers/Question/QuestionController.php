<?php
namespace App\Http\Controllers\Question;
/**
 * Created by PhpStorm.
 * User: tianjianlong
 * Date: 2018/4/10
 * Time: 12:57
 */
use App\Http\Controllers\Controller;
use App\Http\Model\UserAnswerRecordSpecial;
use App\Http\Model\UserAnswerRecord;
use App\Http\Model\UserRepository;
use App\User;
use Illuminate\Http\Request;
use App\Http\Model\Question;
use App\Http\Model\Repository;

use DB;
use App\Libs\CommonFunc;

class QuestionController extends Controller
{

    /*
     * 题集信息
     * */
    public function repositoryInfo(Request $request){
        try{
            $repositoryId = (int)$request->input('id', 0);
            $openid = $request->input('openid', '');
            if(empty($repositoryId)){
                throw new \Exception('question_params_repository_id_empty');
            }
            if(empty($openid)){
                throw new \Exception('user_params_openid_empty');
            }
            $userOrm = User::where('openid', $openid)->first();
            if(empty($userOrm)){
                throw new \Exception('user_empty');
            }
            //不关注其他信息，只返回题集信息
            $repositoryInfo = Repository::getDetail($repositoryId);
            return $this->success($repositoryInfo);
        }catch (\Exception $e){
            return $this->fail($e->getMessage());
        }
    }

    /*
     * 指定题集下题目列表
     * */
    public function questionList(Request $request){
        $repositoryId = (int)$request->input('id', 0);
        if(empty($repositoryId)){
            return $this->fail('question_params_repository_id_empty');
        }

        $res = Question::getListByRepository($repositoryId);

        return !empty($res) ? $this->success($res) : $this->fail('question_question_list_empty');
    }

    /*
     * 提交答题信息
     * */

   /* public function questionSubmit(Request $request){
        try{
            $openid = $request->input('openid', '');
            if(empty($openid)){
                throw new \Exception('user_params_openid_empty');
            }
            //check用户[有效]
            $userOrm = User::where('openid', $openid)->first();
            if(empty($userOrm)){
                throw new \Exception('user_empty');
            }
            //check题集
            $repositoryId = (int)$request->input('id', 0);
            if(empty($repositoryId)){
                throw new \Exception('question_params_repository_id_empty');
            }
            $repositoryOrm = Repository::find($repositoryId);
            if(empty($repositoryOrm)){
                throw new \Exception('question_empty');
            }elseif(!is_null($repositoryOrm->expire_time) && strtotime($repositoryOrm->expire_time) < time()){
                throw new \Exception('question_expired');
            }
            //用户是否答过该题集
            $state = $userOrm->checkAnswered($repositoryId);
            if($state >= UserRepository::STATE_ANSWER_NO_AWARD){
                throw new \Exception('question_answered');
            }
            //校验所有题目是否都属于题集并所有题目都已回答，每个题目可选选项是否符合在题目限定内
            $answers = $request->input('answers', []);

            $answerIds = array_column($answers, 'id');
            $requiredQuestionIds = $repositoryOrm->getQuestionIds();
            foreach($requiredQuestionIds as $requiredQuestionId){
                if(!in_array($requiredQuestionId, $answerIds)){
                    throw new \Exception('question_question_required');
                }
            }

            DB::beginTransaction();
            //入库
            //print_r($answers);exit;
            UserAnswerRecord::createRecord($userOrm->id, $answers);
            $userOrm->setAnswered($repositoryId);
            DB::commit();

            return $this->success();
        }
        catch (\Exception $e){
            DB::rollBack();
            return $this->fail($e->getMessage());
        }
    }*/

   /*
    * 提交答题信息
    * 甲甲覆盖
    * */
    public function questionSubmit(Request $request){
        try{
            $openid = $request->input('openid', '');
            if(empty($openid)){
                throw new \Exception('user_params_openid_empty');
            }
            //check用户[有效]
            $userOrm = User::where('openid', $openid)->first();
            if(empty($userOrm)){
                throw new \Exception('user_empty');
            }
            //check题集
            $repositoryId = (int)$request->input('id', 0);
            if(empty($repositoryId)){
                throw new \Exception('question_params_repository_id_empty');
            }

            $repositoryOrm = Repository::find($repositoryId);
            if(empty($repositoryOrm)){
                throw new \Exception('question_empty');
            }elseif(!is_null($repositoryOrm->expire_time) && strtotime($repositoryOrm->expire_time) < time()){
                throw new \Exception('question_expired');
            }

            //同一经销商同一人再次进入答题页面会法伤覆盖
            //用户是否存在用户题集表中
            $specialDealer = ['27077'];
            $userState = UserRepository::where(['user_id'=>$userOrm->id,'repository_id'=>$repositoryId])->first();
            if(in_array($userState->cbu_id,$specialDealer) && $userState->state == UserRepository::STATE_ANSWER_AWARD){
               //获取question_id 找到数据放到另一张表中 然后删除用户答题表的数据
                $question = Question::where(['repository_id'=>$repositoryId])->select('id')->get()->toArray();
                $answerRecords = UserAnswerRecord::where(['user_id'=>$userOrm->id])->whereIn('question_id',$question)->get()->toArray();
                $this->UserAnswerRecordSpecialOperation($answerRecords);
                UserRepository::where(['user_id'=>$userOrm->id,'repository_id'=>$repositoryId])->update(['state'=>0]);
            }else{
                //用户是否答过该题集
                $state = $userOrm->checkAnswered($repositoryId);
                if($state >= UserRepository::STATE_ANSWER_NO_AWARD){
                    throw new \Exception('question_answered');
                }
            }

            //校验所有题目是否都属于题集并所有题目都已回答，每个题目可选选项是否符合在题目限定内
           $answers = $request->input('answers', []);
            /*$answers =  array(
                array('id'=>1,'option'=>[2],'remark'=>null),
                array('id'=>3,'option'=>[7],'remark'=>null),
                array('id'=>2,'option'=>[4],'remark'=>null),
                array('id'=>4,'option'=>[10],'remark'=>null),
                array('id'=>5,'option'=>[13],'remark'=>null),
                array('id'=>6,'option'=>[16],'remark'=>null),
                array('id'=>7,'option'=>[19],'remark'=>null),
                array('id'=>8,'option'=>[22],'remark'=>null),
                array('id'=>9,'option'=>[26],'remark'=>null),
                array('id'=>10,'option'=>[28],'remark'=>null)
            );*/
            $answerIds = array_column($answers, 'id');
            $requiredQuestionIds = $repositoryOrm->getQuestionIds();
            foreach($requiredQuestionIds as $requiredQuestionId){
                if(!in_array($requiredQuestionId, $answerIds)){
                    throw new \Exception('question_question_required');
                }
            }
            DB::beginTransaction();
            //入库
            //print_r($answers);exit;
            UserAnswerRecord::createRecord($userOrm->id, $answers);
            $userOrm->setAnswered($repositoryId);
            DB::commit();

            return $this->success();
        }
        catch (\Exception $e){
            DB::rollBack();
            return $this->fail($e->getMessage());
        }
    }


    public function UserAnswerRecordSpecialOperation($answerRecords) {
        try{
            DB::beginTransaction();
            $insert = UserAnswerRecordSpecial::insert($answerRecords);
            foreach ($answerRecords as $value){
                $answerRecordes[] = $value['id'];
            }
            $dealte = UserAnswerRecord::whereIn('id',$answerRecordes)->delete();
            if($insert && $dealte){
               DB::commit();
                return $this->success();
            }else{
                DB::rollBack();
            }
        }catch (\Exception $e){
            DB::rollBack();
            return $this->fail($e->getMessage());
        }
    }

    /*
     * 题集汇总
     * 1，用户是否有查看权限
     * 2，题集是否存在
     * @todo 用户查看后台权限修改
     * */
    public function questionSummary(Request $request){
        try{
            $repositoryId = (int)$request->input('id');
            if(empty($repositoryId)){
                throw new \Exception('question_params_repository_id_empty');
            }
            $repositoryOrm = Repository::find($repositoryId);
            if(empty($repositoryOrm)){
                throw new \Exception('question_empty');
            }
            $openid = $request->input('openid', '');
            if(!empty($openid)){
                $userOrm = User::where('openid', $openid)->first();
                if(empty($userOrm)){
                    throw new \Exception('user_empty');
                }
                //用户没有答过题也不能查询
                $userRepository = UserRepository::where(['user_id'=>$userOrm->id, 'repository_id'=>$repositoryId])->where('state', '!=', UserRepository::STATE_WAIT_ANSWER)->first();
                if(empty($userRepository)){
                    throw new \Exception('question_question_user_not_answered');
                }
                $ret = $repositoryOrm->summary($userOrm->id);
            }else{
                //收集汇总
                $ret = $repositoryOrm->summary();
            }

            return $this->success($ret);
        }catch (\Exception $e){
            return $this->fail($e->getMessage());
        }
    }


    /*
     * 确认领取奖品
     * */
    public function affirmReward(Request $request)
    {
        $openid = $request->openid;
        $repositoryId = (int)$request->id;
        $user = User::where('openid',$openid)->first();
        if($user)
        {
            $update = DB::table('user_repository_tb')
                ->where(['user_id'=>$user->id,'repository_id'=>$repositoryId])
                ->update([
                    'state'=>2,
                    'updated_at'=>date('Y-m-d H:i:s',time()),
                ]);
            if($update)
            {
                $userRepositoryObj = DB::table('user_repository_tb')->where(['user_id'=>$user->id,'repository_id'=>$repositoryId])->select('cbu_id')->first();
                $specialDealer = ['27077'];
                if(in_array($userRepositoryObj->cbu_id,$specialDealer) ){
                    $aganswer = 1;
                }else{
                    $aganswer = 0;
                }
                return CommonFunc::_success(null,['date'=>1,'aganswer'=>$aganswer]);
            } else {
                return CommonFunc::_fail(0,'user_repository_tb表信息有误');
            }
        } else {
            return CommonFunc::_fail(0,'查询用户信息失败');
        }

    }

    /*
     * 答题收集备注信息列表
     * */
    public function questionRemark(Request $request){
        $questionId = (int)$request->input('qid', 0);
        $optionId = (int)$request->input('oid', 0);
        $start = (int)$request->input('start', 0);
        $limit = (int)$request->input('limit', 20);
        if(empty($questionId)){
            return $this->fail('question_params_question_id_empty');
        }
        if(empty($optionId)){
            return $this->fail('question_params_option_id_empty');
        }

        //指定答题，指定选项的用户答题记录信息
        $ret = UserAnswerRecord::getOptionRemarkList($questionId, $optionId, $start, $limit);

        return is_null($ret) ? $this->fail('question_question_or_option_empty') : $this->success($ret);
    }

    /*
     * test
     * */
    public function testApi(Request $request){
        $param=[
            'unionid'=>'112233',
            'id'=>1,
            'answers'=>[
                '1'=>['option'=>['1'], 'remark'=>null],
                '2'=>['option'=>['5'], 'remark'=>null],
                '3'=>['option'=>['10'], 'remark'=>null],
                '4'=>['option'=>['13','14','15', '18'], 'remark'=>'其他补充信息'],
                '5'=>['option'=>[], 'remark'=>'问答题'],
            ],

        ];
        $result = CommonFunc::testApi($request,'api/question/submit',$param,'post',1000, 100);

    }


}