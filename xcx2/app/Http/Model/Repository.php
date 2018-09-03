<?php
/**
 * Created by PhpStorm.
 * User: tianjianlong
 * Date: 2018/4/10
 * Time: 14:36
 */

namespace App\Http\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Http\Model\QuestionOption;
use App\Http\Model\Question;
use DB;

class Repository extends Model
{
    protected $table = 'repository_tb';
    protected $primaryKey = 'id';
    protected $fillable = [
        'name',
        'desc',
        'type',
        'logo_image',
        'qr_code_image',
        'input_info',
        'create_user_id',
        'expire_time',
    ];
    public $timestamps = true;

    static $tableName = 'repository_tb';

    const CATCH_EXPIRE_TIME = 300;
    const CATCH_SUMMARY_EXPIRE_TIME = 30 * 60 * 24;

    /*
     * 获取题集信息
     * */
    static function getDetail($id){
        $key = "repository_info_{$id}";
        $res = Cache::get($key);
        if(!$res){
            $orm = self::find($id);
            if(empty($orm)){
                return null;
            }
            //获取题集信息
            $res = [
                'name'=>$orm->name,
                'desc'=>$orm->desc,
                'type'=>$orm->type,
                'logo'=>$orm->logo_image,
                'qr_code'=>$orm->qr_code_image,
                'expire_time'=>$orm->expire_time,
                'collect_info'=>!empty($orm->input_info) ? @json_decode($orm->input_info) : null,
            ];

            Cache::set($key, serialize($res), 10);
        }else{
            $res = unserialize($res);
        }

        return $res;
    }

    function options(){
        //return $this->hasManyThrough('App\Http\Model\QuestionOption', 'App\Http\Model\Question', 'repository_id', 'question_id', 'id', 'id');
        return $this->hasManyThrough('App\Http\Model\QuestionOption', 'App\Http\Model\Question');
    }

    function questions(){
        //return $this->hasManyThrough('App\Http\Model\QuestionOption', 'App\Http\Model\Question', 'repository_id', 'question_id', 'id', 'id');
        return $this->hasMany('App\Http\Model\Question');
    }

    function users(){
        //return $this->hasManyThrough('App\Http\Model\QuestionOption', 'App\Http\Model\Question', 'repository_id', 'question_id', 'id', 'id');
        return $this->hasMany('App\Http\Model\UserRepository');
    }

    /*
     * 获取题集下，答题id
     * @params $withOptionId bool 是否携带题目所含选项ids
     * */
    function getQuestionIds($onlyRequired = true){
        $key = "repository_question_ids_{$this->id}";
        if($onlyRequired){
            $key .= '_required';
        }
        $res = Cache::get($key);
        if(!$res){
            $questionTable = Question::$tableName;
            if($onlyRequired){
                $res = $this->questions()->distinct()->where('required', 1)->orderBy('rank')->pluck("$questionTable.id")->toArray();
            }else{
                $res = $this->questions()->distinct()->orderBy('rank')->pluck("$questionTable.id")->toArray();
            }
            $cacheRes = serialize($res);
            Cache::set($key, $cacheRes, self::CATCH_EXPIRE_TIME);
        }else{
            $res = unserialize($res);
        }

        return $res;
    }

    function getQuestionOptionIds(){
        $key = "repository_option_ids_{$this->id}";
        $res = Cache::get($key);
        if(!$res) {
            $optionTable = QuestionOption::$tableName;
            $res = $this->options()->pluck("$optionTable.id")->toArray();

            Cache::set($key, serialize($res), self::CATCH_EXPIRE_TIME);
        }else{
            $res = unserialize($res);
        }

        return $res;
    }

    //收集汇总，如果时间未超过截止时间，不用缓存；超过截止时间后，使用缓存
    /*
     * @params int $userId 用户id
     * */
    function summary($userId = null){
        if(is_null($this->expire_time) || strtotime($this->expire_time) > time()){
            //实时统计
            $res = ['list'=>$this->summaryData($userId), 'total'=>$this->users()->where('state', '!=', UserRepository::STATE_WAIT_ANSWER)->count()];
        }else{
            //尝试读取缓存
            $key = is_null($userId) ? "repository_summary_{$this->id}" : "repository_summary_{$this->id}_user_{$userId}";
            $res = Cache::get($key);
            if(!$res){
                $res = ['list'=>$this->summaryData($userId), 'total'=>$this->users()->where('state', '!=', UserRepository::STATE_WAIT_ANSWER)->count()];
                Cache::set($key, serialize($res), self::CATCH_SUMMARY_EXPIRE_TIME);
            }else{
                $res = unserialize($res);
            }
        }

        return $res;
    }

    //获取所有答题，所有选项的信息【汇总不支持问答题】
    function summaryData($userId = null){
        $questionTable = Question::$tableName;
        $optionTable = QuestionOption::$tableName;
        $recordTable = UserAnswerRecord::$tableName;

        $select = [
            "$questionTable.name AS qname",//题目名
            "$questionTable.rank AS qidx",//题目顺序
            "$optionTable.name AS oname",//选项名
            "$optionTable.id",//选项id
            "$optionTable.rank",//选项排名
            "$optionTable.question_id",//题目id
            "$questionTable.type",//题目类型
            "$questionTable.required",//是否必填
            'has_text_box',//是否有输入框
            'has_right_answer',//是否有正确答案
            'right_answer_option_id',//正确答案选项
            'right_answer_remark',//正确答案补充说明
        ];
        $questions = DB::table($questionTable)->rightJoin($optionTable, "$questionTable.id", '=', "$optionTable.question_id")
            ->select($select)
            ->where("$questionTable.repository_id", $this->id)
            ->where("$questionTable.type", '!=', Question::TYPE_QA)
            ->orderBy("$questionTable.rank")
            ->orderBy("$optionTable.rank")
            ->get()->toArray();
        $ret = [];

        /*
         * 获取用户题集答题记录，优化sql
         * */
        if(!is_null($userId)){
            $userRecord = DB::table($recordTable)->leftJoin($questionTable, "$recordTable.question_id", '=', "$questionTable.id")
                ->where('user_id', $userId)
                ->where("$questionTable.repository_id", $this->id)
                ->get()->toArray();
            $userRecordList = [];
            foreach($userRecord as $recordItem){
                $userRecordList[$recordItem->question_id][] = $recordItem->question_option_id;
            }
        }
        //初始化
        $optIndex = 'ABCDEFGHIJKLMNOPQRSTUVWXYZX';
        foreach($questions as $question){
            $prefix = ($question->qidx + 1) . '、';
            $ret[$question->question_id]['qid'] = $question->question_id;
            $ret[$question->question_id]['name'] = $prefix . $question->qname;
            $ret[$question->question_id]['type'] = $question->type;
            $ret[$question->question_id]['required'] = $question->required;

            $optItem = ['oid'=>$question->id, 'name'=>$question->oname, 'detail'=>$question->has_text_box, 'is_right'=>0];
            if(is_null($userId)){
                $ret[$question->question_id]['total'] = 0;
                $optItem['count'] = 0;
                $optItem['percent'] = 0;
            }
            //判断正确答案
            if($question->has_right_answer && $question->id == $question->right_answer_option_id){
                $ret[$question->question_id]['right_answer'] = '答案 ' . substr($optIndex, $question->rank, 1) . (!empty($question->right_answer_remark) ? "：{$question->right_answer_remark}" : '');
                $optItem['is_right'] = 1;
            }
            /*
             * 查询sql较多@todo 优化
             * */
            if(!is_null($userId)){
                //执行次数是题目数*选项数
                /*$userOptOrm = UserAnswerRecord::where(['user_id'=>$userId, 'question_id'=>$question->question_id, 'question_option_id'=>$question->id])->first();
                $optItem['user_select'] = !empty($userOptOrm) ? 1 : 0;*/
                //优化后
                $optItem['user_select'] = !empty($userRecordList[$question->question_id]) && in_array($question->id, $userRecordList[$question->question_id]) ? 1 : 0;
                if($optItem['user_select']){
                    $ret[$question->question_id]['selected'] = substr($optIndex, $question->rank, 1);
                }
            }

            $ret[$question->question_id]['option'][$question->id] = $optItem;
        }
        //更新选项人数【只有后台需要】
        if(is_null($userId)){
            foreach($questions as $question){
                $option = DB::table($recordTable)->select(DB::raw('COUNT(id) AS count, question_option_id'))->where('question_id', $question->question_id)->groupBy('question_option_id')->get()->toArray();
                $total = DB::table($recordTable)->where('question_id', $question->question_id)->count();
                foreach($option as $item){
                    $ret[$question->question_id]['option'][$item->question_option_id]['count'] = $item->count;
                    $ret[$question->question_id]['option'][$item->question_option_id]['percent'] = !empty($total) ? round($item->count / $total * 100)  : 0;
                }
                $ret[$question->question_id]['total'] = $total;
            }
        }
        array_walk($ret, function(&$item){
            $item['option'] = array_values($item['option']);
        });
        unset($item);

        return array_values($ret);
    }
}