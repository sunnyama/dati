<?php

namespace App\Console\Commands;

use App\Http\Model\UserAnswerRecord;
use App\Http\Model\UserRepository;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use DB;

class TestUserQuestion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insertUserQuestion {num}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        /*
         * 导入1000用户，答题
         * */
        $params = $this->argument();
        if(!isset($params['num'])){
            throw new \Exception('请输入要增加答题记录数');
        }
        $cbuNum = DB::table('cbu_tb')->count();

        for($i=0; $i<$params['num']; $i++){
            $offset = rand(0, $cbuNum-1);
            $cbuOrm = DB::table('cbu_tb')->offset($offset)->first();
            if(empty($cbuOrm)){
                continue;
            }
            $info = [
                'cbu_id'=>$cbuOrm->cbu_id,
                'cbu_name'=>$cbuOrm->name,
                'user_name'=>$cbuOrm->cbu_id . $cbuOrm->name
            ];
            $orm = User::create([
                'nickname'=>$info['user_name'],
                'openid'=>$cbuOrm->cbu_id . '_' . str_random(6),
                'union_id'=>$cbuOrm->cbu_id . '_' . str_random(6),
                'user_info'=>json_encode($info),
            ]);
            $uid = $orm->id;
            DB::table(UserRepository::$tableName)->insert([
                'user_id'=>$uid,
                'repository_id'=>1,
                'cbu_id'=>$cbuOrm->cbu_id,
                'info'=>json_encode($info),
                'state'=>rand(UserRepository::STATE_ANSWER_NO_AWARD, UserRepository::STATE_ANSWER_NO_AWARD)
            ]);
            $op1 = [1,2,3];
            $op2 = [4,5,6];
            $op3 = [7, 8,9];
            $op4 = [10,11,12];
            $op5 = [13,14,15];
            $op6 = [16,17,18];
            $op7 = [19,20,21];
            $op8 = [22,23,24];
            $op9 = [25,26,27];
            $op10 = [28,29,30];

            $answers = [
                ['id'=>1, 'option'=>[$op1[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>2, 'option'=>[$op2[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>3, 'option'=>[$op3[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>4, 'option'=>[$op4[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>5, 'option'=>[$op5[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>6, 'option'=>[$op6[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>7, 'option'=>[$op7[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>8, 'option'=>[$op8[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>9, 'option'=>[$op9[array_rand($op1,1)]], 'remark'=>null],
                ['id'=>10,'option'=>[$op10[array_rand($op1,1)]], 'remark'=>null],
            ];
            UserAnswerRecord::createRecord($uid, $answers);

        }
    }
}
