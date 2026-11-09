<?php

namespace App\Filament\Widgets;

use App\Models\ContentPage;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShopOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Матеріали сайту', ContentPage::count())->description('Сторінки, статті, молитви та інші матеріали'),
            Stat::make('Оплачені нові замовлення', Order::where('payment_status', 'paid')->where('fulfilment_status', 'new')->count())->color('success'),
            Stat::make('Потребують перевірки', Order::where('payment_status', 'review')->count())->color('danger'),
            Stat::make('Активні товари', Product::where('is_active', true)->count()),
        ];
    }
}
