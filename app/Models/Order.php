<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'checkout_token'];

    protected function casts(): array
    {
        return ['total_minor' => 'integer', 'reserved_until' => 'datetime', 'stock_released_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function publicUrl(): string
    {
        return route('shop.order', $this->access_token);
    }

    public static function paymentLabels(): array
    {
        return ['pending' => 'Очікує оплати', 'paid' => 'Оплачено', 'expired' => 'Термін оплати минув', 'failed' => 'Оплату відхилено', 'refunded' => 'Кошти повернено', 'review' => 'Потребує перевірки'];
    }
}
