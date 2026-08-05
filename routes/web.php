<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/pro-tsentr', 'pages.about')->name('about');
Route::view('/pro-tsentr/upravlinnia', 'pages.placeholder', ['title' => 'Управління'])->name('about.management');
Route::view('/pro-tsentr/entsyklopediia', 'pages.placeholder', ['title' => 'Енциклопедія'])->name('about.encyclopedia');

foreach ([
    'ridna-vira' => 'Рідна Віра',
    'novyny' => 'Новини',
    'statti' => 'Статті',
    'tvorchist' => 'Творчість',
    'kramnychka' => 'Крамниця',
    'zviazok' => 'Зв’язок',
    'koshyk' => 'Кошик',
] as $slug => $title) {
    Route::view('/'.$slug, 'pages.placeholder', compact('title'));
}
