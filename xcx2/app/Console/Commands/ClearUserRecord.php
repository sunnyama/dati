<?php

namespace App\Console\Commands;

use App\Http\Model\UserAnswerRecord;
use App\Http\Model\UserRepository;
use Illuminate\Console\Command;
use DB;

class ClearUserRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clearUserRecord';

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
         * 1，将用户答题表状态记录标记为0
         * 2，清空用户答题选项表
         * */
        DB::table('user_tb')->truncate();
        //DB::table(UserRepository::$tableName)->update(['state'=>UserRepository::STATE_WAIT_ANSWER]);
        DB::table(UserRepository::$tableName)->truncate();

        DB::table(UserAnswerRecord::$tableName)->truncate();
    }
}
