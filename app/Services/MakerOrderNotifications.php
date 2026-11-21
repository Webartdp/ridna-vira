<?php

namespace App\Services;

use App\Mail\MakerOrderReceived;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

final class MakerOrderNotifications
{
    public function __construct(private TelegramAlerts $telegram) {}

    public function enqueue(Order $order): void
    {
        $itemsByMaker = $order->items()->whereNotNull('maker_id')->whereNotNull('maker_price_minor')->get()->groupBy('maker_id');
        if ($itemsByMaker->isEmpty()) {
            return;
        }

        $this->telegram->enqueueMakerOrder($order, $itemsByMaker);
        DB::afterCommit(fn () => defer(fn () => $this->sendEmails($order, $itemsByMaker))->always());
    }

    private function sendEmails(Order $order, Collection $itemsByMaker): void
    {
        $makers = User::whereKey($itemsByMaker->keys()->all())->where('role', 'maker')
            ->where('is_active', true)->whereNotNull('email_verified_at')->get(['id', 'email']);
        $orderUrl = rtrim((string) config('app.url'), '/').route('maker.order', ['order' => $order->reference], false);

        foreach ($makers as $maker) {
            $items = $itemsByMaker[$maker->id]->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'made_to_order' => $item->made_to_order,
                'total_minor' => $item->makerAccruedMinor(),
            ]);

            try {
                Mail::to($maker->email)->send(new MakerOrderReceived(
                    $order->displayNumber(),
                    $order->paymentStatusLabel(),
                    $orderUrl,
                    $items->all(),
                    $items->sum('total_minor'),
                ));
            } catch (Throwable $exception) {
                Log::warning('Maker order email could not be delivered.', [
                    'order_id' => $order->id, 'maker_id' => $maker->id, 'exception' => $exception::class,
                ]);
            }
        }
    }
}
