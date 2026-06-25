<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/{path?}', function () {
    return view('app');
})->where('path', '.*');
