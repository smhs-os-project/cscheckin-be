<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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



Route::get('/', 'GeneralController@isOk');

Route::post('/auth/token/{org}', 'AuthController@createToken');


Route::middleware('auth:sanctum')->group(function(){
    Route::group([
        'prefix' => 'auth'
    ], function ($router) {
        Route::post('/student', 'AuthController@setStudent');
        Route::get('/user', 'AuthController@whoami');
        Route::delete('/token', 'AuthController@revokeToken');
    });

    Route::group([
        'prefix' => 'course'
    ], function ($router) {
        Route::get('/', 'CourseController@getCourse');
        Route::get('/google', 'CourseController@getGCLCourse');
        Route::post('/', 'CourseController@createCourse');
    });

    Route::group([
        'prefix' => 'checkin'
    ], function ($router) {
        Route::get('/{course_id}', 'CheckinController@createToken');
        Route::get('/{class_id}', 'CheckinController@revokeToken');
        Route::post('/{course_id}', 'CheckinController@user');
    });
});
