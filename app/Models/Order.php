<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

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

    public function displayNumber(): string
    {
        return 'RV-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function scopeSearchNumber(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if (preg_match('/^RV-([0-9]+)$/iD', $search, $matches)) {
            $id = filter_var(ltrim($matches[1], '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            return $query->whereKey($id === false ? [] : $id);
        }

        return $query->whereLike('reference', '%'.$search.'%');
    }

    public function publicUrl(): string
    {
        return route('shop.order', $this->access_token);
    }

    public function isCashOnDelivery(): bool
    {
        return $this->payment_method === 'cod';
    }

    public function hasUnpaidReservation(): bool
    {
        return ! $this->paid_at && ! $this->stock_released_at
            && in_array($this->payment_status, ['pending', 'failed', 'expired'], true);
    }

    public function canEditItems(): bool
    {
        return ! $this->trashed() && $this->isCashOnDelivery() && $this->payment_status === 'pending'
            && $this->hasUnpaidReservation() && in_array($this->fulfilment_status, ['new', 'processing'], true);
    }

    public function paymentStatusLabel(): string
    {
        return $this->isCashOnDelivery() && $this->payment_status === 'pending'
            ? 'Оплата при отриманні'
            : (self::paymentLabels()[$this->payment_status] ?? 'Очікує перевірки');
    }

    public static function paymentMethods(): array
    {
        return ['wayforpay' => 'Онлайн через WayForPay', 'cod' => 'При отриманні'];
    }

    public static function paymentLabels(): array
    {
        return ['pending' => 'Очікує оплати', 'paid' => 'Оплачено', 'expired' => 'Термін оплати минув', 'failed' => 'Оплату відхилено', 'refunded' => 'Кошти повернено', 'review' => 'Потребує перевірки'];
    }
}
