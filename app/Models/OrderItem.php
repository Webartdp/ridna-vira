<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'made_to_order' => 'boolean',
            'maker_price_minor' => 'integer',
            'maker_paid_minor' => 'integer',
            'maker_paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function makerAccruedMinor(): int
    {
        return $this->maker_price_minor * $this->quantity;
    }

    public function canRecordMakerPayout(): bool
    {
        return $this->maker_id !== null && $this->maker_price_minor !== null && $this->maker_paid_at === null
            && $this->order->payment_status === 'paid' && $this->order->fulfilment_status === 'completed';
    }

    public function makerPayoutStatus(): string
    {
        if ($this->maker_paid_at) {
            return $this->order->payment_status === 'refunded' ? 'Повернення після виплати' : 'Виплачено';
        }
        if ($this->order->payment_status === 'refunded' || $this->order->fulfilment_status === 'cancelled') {
            return 'Скасовано';
        }
        if ($this->order->payment_status !== 'paid') {
            return 'Очікує підтвердження оплати';
        }

        return $this->canRecordMakerPayout() ? 'До виплати' : 'Очікує виконання';
    }
}
