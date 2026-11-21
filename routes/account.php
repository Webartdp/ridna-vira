<?php

use App\Http\Controllers\AccountAvatarController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\MakerController;
use App\Http\Controllers\MakerOrdersController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/uviyty', 'account.login')->name('login');
    Route::post('/uviyty', [AccountController::class, 'login'])->middleware('throttle:6,1');
    Route::view('/uviyty/maister', 'account.login', ['maker' => true])->name('maker.login');
    Route::post('/uviyty/maister', [AccountController::class, 'login'])->middleware('throttle:6,1')->name('maker.login.submit');
    Route::view('/reiestratsiia', 'account.register')->name('register');
    Route::post('/reiestratsiia', [AccountController::class, 'register'])->middleware('throttle:4,1');
    Route::view('/reiestratsiia/maister', 'account.register', ['maker' => true])->name('maker.register');
    Route::post('/reiestratsiia/maister', [AccountController::class, 'register'])->middleware('throttle:4,1')->name('maker.register.submit');
    Route::view('/parol/vidnovyty', 'account.forgot')->name('password.request');
    Route::post('/parol/vidnovyty', [AccountController::class, 'forgot'])->middleware('throttle:4,1')->name('password.email');
    Route::get('/parol/skynuty/{token}', fn (string $token) => view('account.reset', ['token' => $token]))->name('password.reset');
    Route::post('/parol/skynuty', [AccountController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::get('/kabinet/maisternia', [MakerController::class, 'index'])->name('maker.index');
    Route::get('/kabinet/maisternia/vyplaty', [MakerController::class, 'payouts'])->name('maker.payouts');
    Route::middleware('verified')->prefix('/kabinet/maisternia')->name('maker.')->group(function () {
        Route::get('/zamovlennia', [MakerOrdersController::class, 'index'])->name('orders');
        Route::get('/zamovlennia/{order}', [MakerOrdersController::class, 'show'])->whereUuid('order')->name('order');
        Route::post('/telegram', [MakerOrdersController::class, 'connectTelegram'])->middleware('throttle:6,1')->name('telegram.connect');
        Route::delete('/telegram', [MakerOrdersController::class, 'disconnectTelegram'])->middleware('throttle:6,1')->name('telegram.disconnect');
    });
    Route::post('/kabinet/maisternia', [MakerController::class, 'activate'])->middleware(['verified', 'throttle:4,1'])->name('maker.activate');
    Route::post('/kabinet/maisternia/vyroby', [MakerController::class, 'store'])->middleware(['verified', 'throttle:10,1'])->name('maker.products.store');
    Route::middleware(['verified', 'throttle:20,1'])->prefix('/kabinet/maisternia/vyroby/{product}')->name('maker.products.')->group(function () {
        Route::get('/redahuvaty', [MakerController::class, 'edit'])->name('edit');
        Route::put('/', [MakerController::class, 'update'])->name('update');
        Route::patch('/prykhovaty', [MakerController::class, 'hide'])->name('hide');
        Route::delete('/', [MakerController::class, 'destroy'])->name('destroy');
    });
    Route::post('/vyity', [AccountController::class, 'logout'])->name('logout');
    Route::get('/kabinet', [AccountController::class, 'index'])->name('account.index');
    Route::view('/kabinet/profil', 'account.profile')->name('account.profile');
    Route::put('/kabinet/profil', [AccountController::class, 'profile'])->middleware('throttle:10,1');
    Route::get('/kabinet/profil/avatar', [AccountAvatarController::class, 'show'])->name('account.avatar.show');
    Route::post('/kabinet/profil/avatar', [AccountAvatarController::class, 'update'])->middleware('throttle:8,1')->name('account.avatar.update');
    Route::delete('/kabinet/profil/avatar', [AccountAvatarController::class, 'destroy'])->middleware('throttle:8,1')->name('account.avatar.destroy');
    Route::get('/kabinet/zamovlennia/{reference}', [AccountController::class, 'order'])->whereUuid('reference')->name('account.order');
    Route::post('/kabinet/zamovlennia/dodaty', [AccountController::class, 'claim'])->middleware(['verified', 'throttle:6,1'])->name('account.claim');
    Route::view('/kabinet/pidtverdzhennia-poshty', 'account.verify')->name('verification.notice');
    Route::post('/kabinet/pidtverdzhennia-poshty', [AccountController::class, 'resend'])->middleware('throttle:2,1')->name('verification.send');
    Route::get('/kabinet/pidtverdzhennia-poshty/{id}/{hash}', [AccountController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});
