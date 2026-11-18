<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;

class AddMakerSampleProducts extends Command
{
    protected $signature = 'shop:seed-maker-samples {email : Електронна пошта зареєстрованого майстра}';

    protected $description = 'Додати два приховані тестові вироби в кабінет майстра';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $maker = User::whereRaw('LOWER(email) = ?', [$email])->where('role', 'maker')->first();
        if (! $maker) {
            $this->error('Обліковий запис із цією поштою та роллю «Майстер» не знайдено.');

            return self::FAILURE;
        }

        $samples = [
            'gliniana-myska' => ['Тест · Глиняна миска', 'Глиняна миска ручної роботи. Приклад виробу в наявності.', 45000, 3, false],
            'tkana-kraika' => ['Тест · Ткана крайка', 'Ткана крайка з геометричним візерунком. Приклад виробу під замовлення.', 80000, 0, true],
        ];

        foreach ($samples as $key => [$name, $description, $price, $stock, $madeToOrder]) {
            $product = Product::withTrashed()->firstOrCreate(
                ['slug' => "test-maister-{$maker->id}-{$key}"],
                ['maker_id' => $maker->id, 'name' => $name, 'description' => '<p>'.e($description).'</p>',
                    'maker_price_minor' => $price, 'stock' => $stock, 'made_to_order' => $madeToOrder, 'is_active' => false],
            );
            $this->line(($product->wasRecentlyCreated ? 'Додано' : 'Уже існує')." · {$name}");
        }

        return self::SUCCESS;
    }
}
