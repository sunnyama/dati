<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    //返回成功结果
    public function success($rows = null){
        return response()->json(['success'=>true, 'rows'=>$rows, 'results'=>config('response.ok')]);
    }

    //返回失败结果
    public function fail($error){
        $result = config("response.{$error}");
        if(is_null($result)){
            $result = env('APP_DEBUG') ? ['code'=>config('response.error.code'), 'desc'=>$error] : config('response.error');
        }
        return response()->json(['success'=>false, 'rows'=>null, 'results'=>$result]);
    }
}
