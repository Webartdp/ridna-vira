<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

foreach ([
    'pro-tsentr' => 'Про центр',
    'ridna-vira' => 'Рідна Віра',
    'novyny' => 'Новини',
    'statti' => 'Статті',
    'tvorchist' => 'Творчість',
    'kramnychka' => 'Крамничка',
    'zviazok' => 'Зв’язок',
    'koshyk' => 'Кошик',
] as $slug => $title) {
    Route::view('/'.$slug, 'pages.placeholder', compact('title'));
}
