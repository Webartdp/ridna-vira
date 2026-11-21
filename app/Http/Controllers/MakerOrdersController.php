<?php

namespace App\Http\Controllers;

use App\Models\MakerTelegramLink;
use App\Models\Order;
use App\Services\MakerTelegram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MakerOrdersController extends Controller
{
    private const STATUS_LABELS = [
        'new' => 'Нове',
        'processing' => 'Готується',
        'shipped' => 'Відправлено',
        'completed' => 'Виконано',
        'cancelled' => 'Скасовано',
    ];

    public function index(Request $request, MakerTelegram $telegram): View
    {
        $orders = $this->visibleOrders($request)->latest('id')->paginate(12);
        $link = MakerTelegramLink::where('user_id', $request->user()->id)->first();

        return view('maker.orders', [
            'orders' => $orders,
            'statuses' => self::STATUS_LABELS,
            'telegramConfigured' => $telegram->configured(),
            'telegramConnected' => $link?->chat_id !== null,
            'telegramPending' => $link?->pairing_expires_at?->isFuture() ?? false,
        ]);
    }

    public function show(Request $request, string $order): View
    {
        $order = $this->visibleOrders($request)->where('reference', $order)->firstOrFail();

        return view('maker.order', ['order' => $order, 'status' => self::STATUS_LABELS[$order->fulfilment_status] ?? 'Уточнюється']);
    }

    public function connectTelegram(Request $request, MakerTelegram $telegram): RedirectResponse
    {
        abort_unless($request->user()->roleName() === 'maker', 403);

        return redirect()->away($telegram->connectUrl($request->user()), 303);
    }

    public function disconnectTelegram(Request $request, MakerTelegram $telegram): RedirectResponse
    {
        abort_unless($request->user()->roleName() === 'maker', 403);
        $telegram->disconnect($request->user());

        return redirect()->route('maker.orders')->with('status', 'Сповіщення в Telegram вимкнено.');
    }

    private function visibleOrders(Request $request): Builder
    {
        $maker = $request->user();
        abort_unless($maker->roleName() === 'maker', 403);

        return Order::query()
            ->select(['id', 'reference', 'payment_method', 'payment_status', 'fulfilment_status', 'created_at'])
            ->where(fn ($query) => $query->where('payment_method', 'cod')
                ->orWhere(fn ($query) => $query->whereNotNull('paid_at')->whereIn('payment_status', ['paid', 'refunded'])))
            ->withWhereHas('items', fn ($query) => $query->where('maker_id', $maker->id)
                ->select(['id', 'order_id', 'name', 'quantity', 'maker_price_minor', 'made_to_order']));
    }
}
