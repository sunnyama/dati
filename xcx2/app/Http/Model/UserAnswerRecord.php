<?php
/**
 * Created by PhpStorm.
 * User: tianjianlong
 * Date: 2018/4/10
 * Time: 14:49
 */

namespace App\Http\Model;
use App\Http\Model\QuestionOption;
use Illuminate\Database\Eloquent\Model;
use App\Http\Model\Question;
use DB;

class UserAnswerRecord extends Model
{
    protected $table = 'user_answer_record_tb';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id',
        'question_id',
        'question_option_id',
        'remark',
    ];
    public $timestamps = true;

    static $tableName = 'user_answer_record_tb';

    /*
     * 保存用户答题记录信息
     * @params int $userId 用户id
     * @params array $answers 答题答案
     * @return void
     * */
    static function createRecord($userId, $answers){
        foreach($answers as $answer){
            //校验数据格式，内容长度
            if(!isset($answer['id']) || !isset($answer['option']) || !array_key_exists('remark', $answer)){
                throw new \Exception('question_question_submit_format_error');
            }elseif(strlen($answer['remark']) > 500) {
                throw new \Exception('question_question_remark_too_long');
            }
            //定义题目id，选项id，备注信息
            $answerQid = $answer['id'];
            $answerOptions = $answer['option'];
            $answerRemark = $answer['remark'];

            $questionOrm = Question::find($answerQid);
            if(empty($questionOrm)){
                throw new \Exception('question_question_empty');
            }
            switch ($questionOrm->type){
                case Question::TYPE_SINGLE:
                    $answerOptIds = array_splice($answerOptions, 0, 1);//选择题只选择第一个选项
                    break;
                case Question::TYPE_MULTIPLE:
                    $answerOptIds = $answerOptions;
                    break;
                case Question::TYPE_QA:
                    $answerOptIds = [];
                    break;
                default:
                    throw new \Exception('question_question_type_error');
            }
            //选择题
            if(!empty($answerOptIds)){
                foreach($answerOptIds as $optId){
                    $optionOrm = QuestionOption::where(['id'=>$optId, 'question_id'=>$answerQid])->first();
                    if(empty($optionOrm)){
                        throw new \Exception('question_question_option_empty');
                    }
                    self::checkAndCreateRecord($userId, $answerQid, $optionOrm, $answerRemark);
                }
            }else{
                if(empty($answerRemark)){
                    throw new \Exception('question_question_qa_remark_empty');
                }
                //问答题
                self::checkAndCreateRecord($userId, $answerQid, null, $answerRemark);
            }
        }
    }

    //保存用户答题记录，应考虑选择题，问答题
    static function checkAndCreateRecord($userId, $qid, $optionOrm = null, $remark = null){
        if(is_null($optionOrm)){
            $record = self::where(['user_id'=>$userId, 'question_id'=>$qid])->whereNull('question_option_id')->first();
        }else{
            $record = self::where(['user_id'=>$userId, 'question_id'=>$qid, 'question_option_id'=>$optionOrm->id])->first();
        }
        if(!empty($record)){
            throw new \Exception('question_answered');
        }
        $create = self::create([
            'user_id'=>$userId,
            'question_id'=>$qid,
            'question_option_id'=>!is_null($optionOrm) ? (int)$optionOrm->id : null,
            'remark'=>!is_null($optionOrm) ? ($optionOrm->has_text_box ? $remark : null) : $remark,
        ]);
        if(!$create){
            throw new \Exception('question_answer_submit_error');//保存用户答题信息失败
        }
    }
    /*
     * 获取指定答题，指定选项的备注信息列表，按时间排序
     * params int $questionId 答题id
     * params int $optionId   选项id
     * params int $start      起始条目
     * params int $limit      限制条目，默认为0，不做限制
     * */
    static function getOptionRemarkList($questionId, $optionId, $start = 0, $limit = 20){
        $questionOrm = Question::find($questionId);
        $questionOptionOrm = QuestionOption::find($optionId);
        if(empty($questionOrm) || empty($questionOptionOrm)){
            return null;
        }
        $qcount = self::where(['question_id'=>$questionId])->count();
        $ocount = self::where(['question_id'=>$questionId, 'question_option_id'=>$optionId])->count();
        $ret = [
            'qname'=>$questionOrm->name,
            'qcount'=>$qcount,
            'oname'=>$questionOptionOrm->name,
            'ocount'=>$ocount,
            'opercent'=>!empty($qcount) ? round($ocount / $qcount * 100) : 0,
        ];
        $recordTable = self::$tableName;
        $userRepositoryTable = UserRepository::$tableName;
        $query = DB::table($recordTable)->join($userRepositoryTable, "$userRepositoryTable.user_id", '=', "$recordTable.user_id")
            ->select("$userRepositoryTable.info", "$recordTable.created_at", 'remark')
            ->where(['question_id'=>$questionId, 'question_option_id'=>$optionId])
            ->whereNotNull('remark')
            ->orderBy("$recordTable.created_at", 'desc');

        if($limit != 0){
            $query = $query->limit($limit)->offset($start);
        }

        $records = $query->get()->toArray();
        $list = [];
        foreach($records as $record){
            $info = @json_decode($record->info);
            $list[] = [
                'name'=>!empty($info->user_name) ? $info->user_name : '',
                'cbuId'=>!empty($info->cbu_id) ? $info->cbu_id : '',
                'cbuName'=>!empty($info->cbu_name) ? $info->cbu_name : '',
                'time'=>!empty($record->created_at) ? date('m.d H:i:s', strtotime($record->created_at)) : '',
                'remark'=>$record->remark
            ];
        }
        $ret['list'] = $list;

        return $ret;
    }

}