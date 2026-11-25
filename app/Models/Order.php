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

    protected $attributes = ['payment_method' => PaymentMethod::WAYFORPAY];

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
        return $this->payment_method === PaymentMethod::COD;
    }

    public function isWayForPay(): bool
    {
        return $this->payment_method === PaymentMethod::WAYFORPAY;
    }

    public function isManualPayment(): bool
    {
        return $this->payment_method === PaymentMethod::MANUAL;
    }

    public function paymentMethodLabel(): string
    {
        return $this->payment_method_label ?? self::paymentMethods()[$this->payment_method] ?? 'Спосіб оплати не вказано';
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

    public function canConfirmManualPayment(): bool
    {
        return ! $this->trashed() && $this->isManualPayment() && $this->payment_status === 'pending'
            && $this->hasUnpaidReservation() && in_array($this->fulfilment_status, ['new', 'processing'], true);
    }

    /** @return array<string, string> */
    public function deliveryDetails(): array
    {
        return array_filter([
            'Отримувач' => (string) $this->customer_name,
            'Телефон' => (string) $this->phone,
            'Доставка' => match ($this->delivery_method) {
                'nova_poshta' => 'Нова пошта',
                'pickup' => 'Самовивіз',
                default => (string) $this->delivery_method,
            },
            'Відділення або адреса' => (string) $this->delivery_address,
        ], filled(...));
    }

    public function paymentStatusLabel(): string
    {
        return $this->isCashOnDelivery() && $this->payment_status === 'pending'
            ? 'Оплата при отриманні'
            : (self::paymentLabels()[$this->payment_status] ?? 'Очікує перевірки');
    }

    public static function paymentMethods(): array
    {
        return [
            PaymentMethod::WAYFORPAY => 'Онлайн через WayForPay',
            PaymentMethod::COD => 'При отриманні',
            PaymentMethod::MANUAL => 'За інструкцією крамниці',
        ];
    }

    public static function paymentLabels(): array
    {
        return ['pending' => 'Очікує оплати', 'paid' => 'Оплачено', 'expired' => 'Термін оплати минув', 'failed' => 'Оплату відхилено', 'refunded' => 'Кошти повернено', 'review' => 'Потребує перевірки'];
    }
}
