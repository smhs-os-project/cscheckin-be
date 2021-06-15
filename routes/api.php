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
Route::get('/info/{org?}', 'GeneralController@getInfo');

Route::post('/auth/token/{org}', 'AuthController@createToken');


Route::middleware('auth:sanctum')->group(function () {
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
        Route::post('/{google_classroom_id}', 'CourseController@createCourse');
        Route::post('/share/{course_id}', 'CourseController@shareCourse');
        Route::delete('/{course_id}', 'CourseController@endCourse');
    });

    Route::group([
        'prefix' => 'checkin'
    ], function ($router) {
        Route::get('/{course_id}', 'CheckinController@getCourseCheckin');
        Route::post('/{course_uuid}', 'CheckinController@checkin');
    });
});

Route::get('/course/{course_uuid}', 'CourseController@getCourseByUuid');
