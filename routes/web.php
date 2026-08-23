<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\BookFileController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\GodController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ShrineController;
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
Route::view('/ridna-vira/knyhy', 'pages.books')->name('faith.books');
Route::get('/ridna-vira/knyhy/{book}/obkladynka', [BookFileController::class, 'cover'])
    ->where('book', '[a-z0-9\-]+')
    ->name('faith.books.cover');
Route::get('/ridna-vira/knyhy/{book}/zavantazhyty/{format}', [BookFileController::class, 'download'])
    ->where([
        'book' => '[a-z0-9\-]+',
        'format' => 'pdf|docx',
    ])
    ->name('faith.books.download');
Route::get('/ridna-vira/bohy', [GodController::class, 'index'])->name('faith.gods');
Route::get('/ridna-vira/bohy/{god}', [GodController::class, 'show'])
    ->where('god', '[a-z0-9\-]+')
    ->name('faith.gods.show');
Route::get('/ridna-vira/sviatyni', [ShrineController::class, 'index'])->name('faith.shrines');
Route::get('/ridna-vira/sviatyni/{shrine}', [ShrineController::class, 'show'])
    ->where('shrine', '[a-z0-9\-]+')
    ->name('faith.shrines.show');

foreach ([
    'rituals' => ['slug' => 'obriady', 'title' => 'Обряди'],
    'prayers' => ['slug' => 'molytvy', 'title' => 'Молитви'],
] as $name => $page) {
    Route::view('/ridna-vira/'.$page['slug'], 'pages.placeholder', ['title' => $page['title']])
        ->name('faith.'.$name);
}

Route::get('/statti', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/statti/{article}', [ArticleController::class, 'show'])
    ->where('article', '[a-z0-9\-]+')
    ->name('articles.show');
Route::get('/statti/{article}/zavantazhyty', [ArticleController::class, 'download'])
    ->where('article', '[a-z0-9\-]+')
    ->name('articles.download');

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
    'tvorchist' => 'Творчість',
    'kramnychka' => 'Крамниця',
    'koshyk' => 'Кошик',
] as $slug => $title) {
    Route::view('/'.$slug, 'pages.placeholder', compact('title'));
}
