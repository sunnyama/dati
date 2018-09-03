<?php

/**
 * Created by PhpStorm.
 * User: tianjianlong
 * Date: 2018/4/10
 * Time: 13:02
 */
namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;
use DB;

class QuestionOption extends Model
{
    protected $table = 'question_option_tb';
    protected $primaryKey = 'id';
    protected $fillable = [
        'question_id',
        'rank',
        'name',
        'has_text_box',
    ];
    public $timestamps = false;

    static $tableName = 'question_option_tb';

    static function getList($questionId){
        return self::select('id', 'name', 'has_text_box')->where(['question_id'=>$questionId])->orderBy('rank')->get()->toArray();
    }

}