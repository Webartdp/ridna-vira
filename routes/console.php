<?php

use App\Services\MakerTelegram;
use App\Services\TelegramAlerts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('shop:expire-reservations')->everyMinute()->withoutOverlapping();

Schedule::command('telegram:alerts')->everyMinute()->when(fn () => app(TelegramAlerts::class)->configured());
Schedule::command('telegram:makers')->everyMinute()->when(fn () => app(MakerTelegram::class)->configured());

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
