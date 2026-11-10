<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\BookFileController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\GodController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrayerController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\RitualController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ShrineController;
use App\Http\Controllers\SitemapController;
use App\Models\ContentPage;
use App\Support\Cms;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
require __DIR__.'/account.php';

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

Route::get('/ridna-vira/obriady', [RitualController::class, 'index'])->name('faith.rituals');
Route::get('/ridna-vira/obriady/{ritual}', [RitualController::class, 'show'])
    ->where('ritual', '[a-z0-9\-]+')->name('faith.rituals.show');
Route::get('/ridna-vira/obriady/{ritual}/zavantazhyty/{format}', [RitualController::class, 'download'])
    ->where(['ritual' => '[a-z0-9\-]+', 'format' => 'pdf|docx'])->name('faith.rituals.download');
Route::get('/ridna-vira/molytvy', [PrayerController::class, 'index'])->name('faith.prayers');
Route::get('/ridna-vira/molytvy/{prayer}', [PrayerController::class, 'show'])
    ->where('prayer', '[a-z0-9\-]+')->name('faith.prayers.show');
Route::get('/ridna-vira/molytvy/{prayer}/zavantazhyty/{format}', [PrayerController::class, 'download'])
    ->where(['prayer' => '[a-z0-9\-]+', 'format' => 'pdf|djvu'])->name('faith.prayers.download');

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

Route::get('/novyny', [PublicationController::class, 'index'])->defaults('section', 'news')->name('news.index');
Route::get('/novyny/{publication}', [PublicationController::class, 'show'])
    ->where('publication', '[a-z0-9\-]+')->defaults('section', 'news')->name('news.show');
Route::get('/tvorchist', [PublicationController::class, 'index'])->defaults('section', 'creativity')->name('creativity.index');
Route::get('/tvorchist/{publication}', [PublicationController::class, 'show'])
    ->where('publication', '[a-z0-9\-]+')->defaults('section', 'creativity')->name('creativity.show');

Route::get('/kramnychka', [ShopController::class, 'index'])->name('shop.index');
Route::get('/kramnychka/{product:slug}', [ShopController::class, 'show'])->name('shop.product');
Route::get('/koshyk', [ShopController::class, 'cart'])->name('shop.cart');
Route::post('/koshyk/{product:slug}', [ShopController::class, 'updateCart'])->middleware('throttle:60,1')->name('shop.cart.update');
Route::post('/zamovlennia', [ShopController::class, 'checkout'])->middleware('throttle:10,1')->name('shop.checkout');
Route::get('/zamovlennia/{token}', [ShopController::class, 'order'])->where('token', '[A-Za-z0-9]{64}')->name('shop.order');
Route::post('/zamovlennia/{token}/oplata', [ShopController::class, 'pay'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:10,1')->name('shop.pay');
Route::match(['get', 'post'], '/oplata/povernennia/{token}', [ShopController::class, 'returned'])->where('token', '[A-Za-z0-9]{64}')->name('shop.return');
Route::post('/oplata/wayforpay', [ShopController::class, 'webhook'])->name('shop.webhook');

Route::fallback(function () {
    abort_unless(Cms::ready(), 404);
    $page = ContentPage::where('path', '/'.trim(request()->path(), '/'))->first();
    abort_unless($page && $page->visible(), 404);

    return view('cms.page', compact('page'));
});
