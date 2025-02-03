<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\FileUploadController;

Route::match(['get','post'],'/login', [AuthController::class, 'login'])->name('admin-login');;
Route::middleware('admin')->post('/logout', [AuthController::class, 'logout']);

Route::post('/admin',[AdminController::class,'store']);

//Admin
Route::group(['middleware' => 'admin', 'prefix' => 'admin'], function () {
    Route::get('/edit', [AdminController::class,'edit']);
});

//Sales
Route::group(['middleware' => 'admin', 'prefix' => 'sales'], function () {
    Route::get('/index', [SaleController::class, 'index']);
});

//Upload
Route::post('/upload', [FileUploadController::class, 'upload']);