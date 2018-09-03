<?php
/**
 * Created by PhpStorm.
 * User: tianjianlong
 * Date: 2018/4/12
 * Time: 16:07
 */

namespace App\Http\Model;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends Model
{
    protected $table = 'user_repository_tb';
    //protected $primaryKey = ['user_id', 'repository_id'];
    protected $fillable = [
        'user_id',
        'repository_id',
        'cbu_id',
        'info',
        'state',
    ];
    public $timestamps = true;

    static $tableName = 'user_repository_tb';

    const STATE_WAIT_INPUT_INFO = -1;//未填cbuid等信息
    const STATE_WAIT_ANSWER = 0;//未答题
    const STATE_ANSWER_NO_AWARD = 1;//答题未领奖
    const STATE_ANSWER_AWARD = 2;//答题已领奖
    const STATE_EXPERT_GROP = 3;//答题未选择专家组



}