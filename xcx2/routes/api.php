<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/question/list', 'Question\QuestionController@questionList');
Route::post('/question/repository', 'Question\QuestionController@repositoryInfo');
Route::post('/question/summary', 'Question\QuestionController@questionSummary');
Route::post('/question/remark', 'Question\QuestionController@questionRemark');
Route::post('/question/submit', 'Question\QuestionController@questionSubmit');
//确认领取奖品
Route::post('/question/affirmReward', 'Question\QuestionController@affirmReward');

Route::get('/zyrtest', 'User\UserController@getUserInfo');
Route::post('/miniProgramLoginGetUserInfo', 'User\UserController@miniProgramLoginGetUserInfo');
Route::post('/saveUserExtraInfo', 'User\UserController@saveUserExtraInfo');
Route::post('/joinQuestionUserStatistics', 'User\UserController@joinQuestionUserStatistics');


//后台登录
Route::post('/admin/login', 'Admin\AdminController@login');
Route::post('/admin/addAdmin', 'Admin\AdminController@addAdmin');

//测试接口
Route::get('/tjl/test', 'Question\QuestionController@testApi');

Route::post('/expert','User\UserController@experts'); //是否加入专家组