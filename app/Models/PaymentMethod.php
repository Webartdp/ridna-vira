<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class PaymentMethod extends Model
{
    public const WAYFORPAY = 'wayforpay';

    public const COD = 'cod';

    public const MANUAL = 'manual';

    protected $guarded = ['id', 'code'];

    protected static function booted(): void
    {
        static::creating(function (self $method): void {
            $method->code = match ($method->driver) {
                self::WAYFORPAY, self::COD => $method->driver,
                self::MANUAL => 'manual-'.Str::lower((string) Str::ulid()),
                default => throw new InvalidArgumentException('Unsupported payment method type.'),
            };
        });

        static::updating(function (self $method): void {
            if ($method->isDirty(['driver', 'code'])) {
                throw new LogicException('An existing payment method cannot change its type or code.');
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public static function drivers(): array
    {
        return [
            self::WAYFORPAY => 'Онлайн через WayForPay',
            self::COD => 'Післяплата Нової пошти',
            self::MANUAL => 'Оплата з ручним підтвердженням',
        ];
    }

    public function availableForCheckout(bool $wayForPayReady): bool
    {
        return $this->is_active && ($this->driver !== self::WAYFORPAY || $wayForPayReady);
    }

    public function orderSnapshot(): array
    {
        return [
            'payment_method' => $this->driver,
            'payment_method_label' => $this->name,
            'payment_instructions' => $this->instructions,
        ];
    }
}
