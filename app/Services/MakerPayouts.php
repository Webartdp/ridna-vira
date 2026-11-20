<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MakerPayouts
{
    public function record(User $admin, OrderItem $item, string $amount, string $reference): void
    {
        abort_unless($admin->canManage('admin'), 403);
        DB::transaction(function () use ($admin, $item, $amount, $reference) {
            // Serialize with WayForPay callbacks before checking eligibility.
            $order = Order::withTrashed()->lockForUpdate()->findOrFail($item->order_id);
            $locked = $order->items()->lockForUpdate()->findOrFail($item->id);
            $locked->setRelation('order', $order);
            if (! $locked->canRecordMakerPayout()) {
                throw ValidationException::withMessages(['amount' => 'Позиція вже виплачена або більше не доступна до виплати. Оновіть список.']);
            }
            try {
                $minor = Money::minor($amount);
            } catch (\InvalidArgumentException) {
                $minor = 0;
            }
            if ($minor < 1 || $minor > $locked->makerAccruedMinor()) {
                throw ValidationException::withMessages(['amount' => 'Фактична виплата має бути більшою за нуль і не перевищувати нараховану суму.']);
            }

            $locked->update([
                'maker_paid_at' => now(), 'maker_paid_minor' => $minor,
                'maker_payout_reference' => $reference, 'maker_paid_by' => $admin->id,
            ]);
        }, 3);
    }
}
