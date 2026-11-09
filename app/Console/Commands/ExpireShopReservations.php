<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Checkout;
use Illuminate\Console\Command;

class ExpireShopReservations extends Command
{
    protected $signature = 'shop:expire-reservations';

    protected $description = 'Повернути в наявність товари з неоплачених прострочених замовлень';

    public function handle(Checkout $checkout): int
    {
        Order::whereNull('paid_at')->whereNull('stock_released_at')->where('reserved_until', '<', now())
            ->chunkById(100, function ($orders) use ($checkout) {
                foreach ($orders as $order) {
                    $checkout->release($order);
                }
            });

        return self::SUCCESS;
    }
}
