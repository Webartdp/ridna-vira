<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::view('/pro-tsentr', 'pages.about')->name('about');
Route::view('/pro-tsentr/upravlinnia', 'pages.management')->name('about.management');
Route::redirect('/pro-tsentr/entsyklopediia', 'https://wiki.svit.in.ua/', 302)->name('about.encyclopedia');
Route::view('/zviazok', 'pages.contact')->name('contact');

foreach ([
    'ridna-vira' => 'Рідна Віра',
    'novyny' => 'Новини',
    'statti' => 'Статті',
    'tvorchist' => 'Творчість',
    'kramnychka' => 'Крамниця',
    'koshyk' => 'Кошик',
] as $slug => $title) {
    Route::view('/'.$slug, 'pages.placeholder', compact('title'));
}
