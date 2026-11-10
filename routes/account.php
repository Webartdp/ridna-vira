<?php

use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/uviyty', 'account.login')->name('login');
    Route::post('/uviyty', [AccountController::class, 'login'])->middleware('throttle:6,1');
    Route::view('/reiestratsiia', 'account.register')->name('register');
    Route::post('/reiestratsiia', [AccountController::class, 'register'])->middleware('throttle:4,1');
    Route::view('/parol/vidnovyty', 'account.forgot')->name('password.request');
    Route::post('/parol/vidnovyty', [AccountController::class, 'forgot'])->middleware('throttle:4,1')->name('password.email');
    Route::get('/parol/skynuty/{token}', fn (string $token) => view('account.reset', ['token' => $token]))->name('password.reset');
    Route::post('/parol/skynuty', [AccountController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/vyity', [AccountController::class, 'logout'])->name('logout');
    Route::get('/kabinet', [AccountController::class, 'index'])->name('account.index');
    Route::view('/kabinet/profil', 'account.profile')->name('account.profile');
    Route::put('/kabinet/profil', [AccountController::class, 'profile'])->middleware('throttle:10,1');
    Route::get('/kabinet/zamovlennia/{reference}', [AccountController::class, 'order'])->whereUuid('reference')->name('account.order');
    Route::post('/kabinet/zamovlennia/dodaty', [AccountController::class, 'claim'])->middleware(['verified', 'throttle:6,1'])->name('account.claim');
    Route::view('/kabinet/pidtverdzhennia-poshty', 'account.verify')->name('verification.notice');
    Route::post('/kabinet/pidtverdzhennia-poshty', [AccountController::class, 'resend'])->middleware('throttle:2,1')->name('verification.send');
    Route::get('/kabinet/pidtverdzhennia-poshty/{id}/{hash}', [AccountController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});
