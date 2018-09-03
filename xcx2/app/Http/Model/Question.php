<?php

/**
 * Created by PhpStorm.
 * User: tianjianlong
 * Date: 2018/4/10
 * Time: 13:02
 */
namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Question extends Model
{
    protected $table = 'question_tb';
    protected $primaryKey = 'id';
    protected $fillable = [
        'repository_id',
        'rank',
        'name',
        'type',
        'required',
        'has_right_answer',
        'right_answer_option_id',
        'right_answer_remark',
    ];
    public $timestamps = false;

    static $tableName = 'question_tb';

    const CATCH_EXPIRE_TIME = 100;

    const TYPE_SINGLE = 0;//单选题
    const TYPE_MULTIPLE = 1;//多选题
    const TYPE_QA = 2;//问答题

    public function options(){
        //return $this->hasMany('App\Http\Model\QuestionOption', 'question_id', 'id');
        return QuestionOption::getList($this->id);
    }

    static function getListByRepository($repositoryId){
        $key = "repository_question_list_{$repositoryId}";
        $res = Cache::get($key);
        if(!$res){
            $list = self::select('id', 'name', 'type', 'required')->where(['repository_id'=>$repositoryId])->orderBy('rank')->get();
            $res = [];
            foreach($list as $k=>$item){
                $prefix = ($k+1) . '、';
                $res[] = [
                    'id'=>$item->id,
                    'name'=>$prefix . $item->name,
                    'type'=>$item->type,
                    'required'=>$item->required,
                    'options'=>$item->options()
                ];
            }

            Cache::set($key, serialize($res), self::CATCH_EXPIRE_TIME);
        }else{
            $res = unserialize($res);
        }

        return $res;
    }
}