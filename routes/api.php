<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\BusinessGroupController;

Route::match(['get','post'],'/login', [AuthController::class, 'login'])->name('admin-login');;
Route::middleware('admin')->post('/logout', [AuthController::class, 'logout']);

Route::post('/admin',[AdminController::class,'store']);

//Admin
Route::group(['middleware' => 'admin'], function () {
    Route::resource('/admin', AdminController::class);
    Route::put('/profile/update',[AdminController::class, 'UpdateID']);
    Route::get('/profile',[AdminController::class, 'getProfile']);
    Route::delete('/profile/delete',[AdminController::class, 'delete']);
    
    //Reset Password
    Route::put('/password/update-default-password',[AdminController::class, 'updateDefaultPassword']);
    Route::patch('/password/update-password/{id}',[AdminController::class,'updatePasswordID']);
    Route::patch('/password/change-password',[AdminController::class,'changePassword']);
    Route::get('/password/get-password',[AdminController::class,'getPassword']);
});


//Group
Route::group(['middleware' => 'admin'], function () {
    Route::delete('/groups/delete',[BusinessGroupController::class,'delete']);
    Route::resource('group', BusinessGroupController::class);
    Route::get('groups/search', [BusinessGroupController::class, 'search']);

    //Employee
    Route::get('/listEmployee',[AdminController::class,'listEmployee']);
});


//Sales
Route::group(['middleware' => 'admin', 'prefix' => 'sales'], function () {
    Route::get('/index', [SaleController::class, 'index']);
    Route::delete('/{id}',[SaleController::class, 'destroy']);
    Route::delete('/delete',[SaleController::class, 'delete']);
});

Route::group(['middleware' => 'admin', 'prefix' => 'sale'], function () {
    Route::delete('/delete',[SaleController::class, 'delete']);
    Route::get('/{id}',[SaleController::class,'edit']);
    Route::put('/{id}',[SaleController::class,'updateNote']);
});

//Upload
Route::post('/upload', [FileUploadController::class, 'upload']);

