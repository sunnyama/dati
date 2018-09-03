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

class UserAnswerRecordSpecial extends Model
{
    protected $table = 'user_answer_record_special_tb';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id',
        'question_id',
        'question_option_id',
        'remark',
    ];
    public $timestamps = true;

    static $tableName = 'user_answer_record_special_tb';
}