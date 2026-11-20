<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddMakerSamplePayouts extends Command
{
    protected $signature = 'shop:seed-maker-payouts {email : Пошта майстра} {--remove : Видалити ці тестові записи}';

    protected $description = 'Додати або прибрати чотири тестові приклади виплат майстра';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $maker = User::whereRaw('LOWER(email) = ?', [$email])->where('role', 'maker')->first();
        if (! $maker) {
            $this->error('Майстра з цією поштою не знайдено.');

            return self::FAILURE;
        }

        $marker = 'DEMO-MAKER-PAYOUTS-'.$maker->id;
        $samples = [
            ['Мідний браслет', 60000, 2, 'new', false],
            ['Ткана крайка', 80000, 1, 'completed', false],
            ['Глиняна миска', 45000, 3, 'completed', true],
            ['Дерев’яний оберіг', 35000, 1, 'cancelled', false],
        ];

        DB::transaction(function () use ($maker, $marker, $samples) {
            User::whereKey($maker->id)->lockForUpdate()->firstOrFail();
            foreach ($samples as $index => [$name, $price, $quantity, $status, $paid]) {
                $key = $marker.'-'.($index + 1);
                $existing = Order::where('admin_notes', $key)->where('email', $maker->email)
                    ->whereHas('items', fn ($query) => $query->where('maker_id', $maker->id))->first();
                if ($this->option('remove')) {
                    $existing?->delete();

                    continue;
                }
                if ($existing) {
                    continue;
                }

                $date = now()->subDays(4 - $index);
                $retailPrice = (int) round($price * 1.3);
                $order = Order::create([
                    'reference' => (string) Str::uuid(),
                    'access_token' => Str::random(64),
                    'checkout_token' => (string) Str::uuid(),
                    'customer_name' => 'ТЕСТ · Приклад виплати',
                    'email' => $maker->email,
                    'phone' => 'TEST',
                    'delivery_method' => 'nova_poshta',
                    'delivery_address' => 'Тестовий запис для перегляду кабінету',
                    'comment' => 'ТЕСТ · Демонстраційні дані виплат',
                    'admin_notes' => $key,
                    'total_minor' => $retailPrice * $quantity,
                    'payment_status' => 'paid',
                    'fulfilment_status' => $status,
                    'reserved_until' => $date,
                    'stock_released_at' => $date,
                    'paid_at' => $date,
                    'created_at' => $date,
                ]);
                $order->items()->create([
                    'maker_id' => $maker->id,
                    'name' => 'ТЕСТ · '.$name,
                    'quantity' => $quantity,
                    'price_minor' => $retailPrice,
                    'maker_price_minor' => $price,
                    'maker_paid_minor' => $paid ? $price * $quantity : null,
                    'maker_paid_at' => $paid ? $date->copy()->addDay() : null,
                    'maker_payout_reference' => $paid ? 'ТЕСТ-ПЕРЕКАЗ-001' : null,
                ]);
            }
        });

        $this->info($this->option('remove') ? 'Тестові виплати видалено.' : 'Чотири тестові виплати готові до перегляду.');

        return self::SUCCESS;
    }
}
