<?php

use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\HolidayController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::view('/pro-tsentr', 'pages.about')->name('about');
Route::view('/pro-tsentr/upravlinnia', 'pages.management')->name('about.management');
Route::redirect('/pro-tsentr/entsyklopediia', 'https://wiki.svit.in.ua/', 302)->name('about.encyclopedia');

Route::view('/ridna-vira', 'pages.faith')->name('faith');
Route::view('/ridna-vira/kalendar', 'pages.calendar')->name('faith.calendar');
Route::get('/ridna-vira/kalendar/{holiday}', HolidayController::class)
    ->where('holiday', '[a-z0-9\-]+')
    ->name('faith.holiday');

foreach ([
    'books' => ['slug' => 'knyhy', 'title' => 'Книги'],
    'shrines' => ['slug' => 'sviatyni', 'title' => 'Святині'],
    'gods' => ['slug' => 'bohy', 'title' => 'Боги'],
    'rituals' => ['slug' => 'obriady', 'title' => 'Обряди'],
    'prayers' => ['slug' => 'molytvy', 'title' => 'Молитви'],
] as $name => $page) {
    Route::view('/ridna-vira/'.$page['slug'], 'pages.placeholder', ['title' => $page['title']])
        ->name('faith.'.$name);
}

Route::view('/zviazok', 'pages.contact')->name('contact');
Route::post('/zviazok', ContactFormController::class)
    ->middleware('throttle:5,1')
    ->name('contact.submit');

Route::get('/dokumenti/{document}', [DocumentDownloadController::class, 'show'])
    ->where('document', '[a-z0-9\-]+')
    ->name('documents.download');

Route::get('/dokumenti/{document}/zavantazhyty', [DocumentDownloadController::class, 'download'])
    ->where('document', '[a-z0-9\-]+')
    ->name('documents.download.file');

foreach ([
    'novyny' => 'Новини',
    'statti' => 'Статті',
    'tvorchist' => 'Творчість',
    'kramnychka' => 'Крамниця',
    'koshyk' => 'Кошик',
] as $slug => $title) {
    Route::view('/'.$slug, 'pages.placeholder', compact('title'));
}
