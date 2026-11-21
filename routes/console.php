<?php

use App\Services\MakerTelegram;
use App\Services\TelegramAlerts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn (): bool => Artisan::call('shop:expire-reservations') === 0)
    ->name('shop:expire-reservations')->everyMinute()->withoutOverlapping();

Schedule::call(fn (): bool => Artisan::call('telegram:alerts') === 0)
    ->name('telegram:alerts')->everyMinute()->when(fn () => app(TelegramAlerts::class)->configured());
Schedule::call(fn (): bool => Artisan::call('telegram:makers') === 0)
    ->name('telegram:makers')->everyMinute()->when(fn () => app(MakerTelegram::class)->configured());

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
