<?php

namespace App;

use App\Http\Model\Question;
use App\Http\Model\Repository;
use App\Http\Model\UserAnswerRecord;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Http\Model\UserRepository;

use Illuminate\Support\Facades\Cache;
use DB;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nickname',
        'openid',
        'union_id',
        'phone',
        'user_info'
    ];
    public $timestamps = true;
    protected $table = 'user_tb';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    const CACHE_HAS_ANSWERED = 60 * 60 * 24;

    /*
     * 判断用户是否参与过该答题
     * 直接去用户题集表查询记录即可
     * 已领奖后可以增加缓存
     * */
    public function checkAnswered($repositoryId){
        $key = "user_answer_record_{$this->id}_{$repositoryId}";
        $has = Cache::get($key);
        //不存在缓存记录时
        if(is_null($has)){
            $record = UserRepository::where(['user_id'=>$this->id, 'repository_id'=>$repositoryId])->first();
            if(empty($record)){
                return UserRepository::STATE_WAIT_INPUT_INFO;
            }elseif($record->state == UserRepository::STATE_ANSWER_AWARD){
                //已领奖，记录到缓存
                Cache::set($key, $record->state, self::CACHE_HAS_ANSWERED);
            }
            return $record->state;

        }else{
            return $has;
        }


        //old code
        /*$key = "user_answer_record_{$this->id}_{$repositoryId}";
        $has = Cache::get($key);
        if(is_null($has)){
            //需要查询
            $userRecordTable = UserAnswerRecord::$tableName;
            $questionTable = Question::$tableName;
            $record = DB::table($userRecordTable)->join($questionTable, "$questionTable.id", '=', "$userRecordTable.question_id")
                ->where("$userRecordTable.user_id", $this->id)
                ->where("$questionTable.repository_id", $repositoryId)
                ->first();

            $has = !empty($record) ? 1 : 0;

            Cache::set($key, $has, self::CACHE_HAS_ANSWERED);
        }

        return $has;*/
    }

    /*
     * 更新用户答题状态
     * 默认更新为答题未领奖
     * */
   /* public function setAnswered($repositoryId, $state = UserRepository::STATE_ANSWER_NO_AWARD){
        $userRepositoryTable = UserRepository::$tableName;
        $orm = DB::table($userRepositoryTable)->where(['user_id'=>$this->id, 'repository_id'=>$repositoryId])->first();
        if(empty($orm)){
            $repositoryOrm = Repository::find($repositoryId);
            if(empty($repositoryOrm)){
                return;
            }
            $repositoryInfo = json_decode($repositoryOrm->input_info, true);
            $info = [];
            foreach($repositoryInfo as $infoItem){
                $info[$infoItem['name']] = '';
            }
            UserRepository::create([
                'user_id'=>$this->id,
                'repository_id'=>$repositoryId,
                'state'=>$state,
                'info'=>json_encode($info)
            ]);
        }else{
            DB::table($userRepositoryTable)->where(['user_id'=>$this->id, 'repository_id'=>$repositoryId])->update(['state'=>$state]);
        }

    }*/
    public function setAnswered($repositoryId, $state = UserRepository::STATE_EXPERT_GROP){
        $userRepositoryTable = UserRepository::$tableName;
        $orm = DB::table($userRepositoryTable)->where(['user_id'=>$this->id, 'repository_id'=>$repositoryId])->first();
        if(empty($orm)){
            $repositoryOrm = Repository::find($repositoryId);
            if(empty($repositoryOrm)){
                return;
            }
            $repositoryInfo = json_decode($repositoryOrm->input_info, true);
            $info = [];
            foreach($repositoryInfo as $infoItem){
                $info[$infoItem['name']] = '';
            }
            UserRepository::create([
                'user_id'=>$this->id,
                'repository_id'=>$repositoryId,
                'state'=>$state,
                'info'=>json_encode($info)
            ]);
        }else{
            DB::table($userRepositoryTable)->where(['user_id'=>$this->id, 'repository_id'=>$repositoryId])->update(['state'=>$state]);
        }

    }
}
