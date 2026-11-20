@extends('account.layout', ['accountTitle' => 'Мої виплати'])

@push('styles') @include('maker.styles') @endpush

@section('account-content')
<section class="shop-panel maker-inventory" aria-label="Історія виплат">
    <p>Після отримання замовлення покупцем адміністратор відмічає його виконаним. Фактична сума й номер переказу з’являться після ручної виплати.</p>
    @if ($items->isNotEmpty())
        <div class="maker-table-scroll" role="region" aria-label="Таблиця виплат" tabindex="0">
            <table class="maker-table">
                <thead><tr>
                    <th scope="col">Виріб і замовлення</th>
                    <th scope="col">Нараховано</th>
                    <th scope="col">Виплачено</th>
                    <th scope="col">Статус</th>
                </tr></thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <th scope="row">{{ $item->name }} · {{ $item->quantity }} шт.
                            <small>{{ $item->order->displayNumber() }} · {{ $item->order->created_at->format('d.m.Y') }}</small>
                        </th>
                        <td>{{ \App\Support\Money::display($item->makerAccruedMinor()) }}</td>
                        <td>
                            @if ($item->maker_paid_at)
                                {{ \App\Support\Money::display($item->maker_paid_minor) }}
                                <small>{{ $item->maker_paid_at->timezone('Europe/Kyiv')->format('d.m.Y H:i') }}</small>
                                <small>Переказ {{ $item->maker_payout_reference }}</small>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $item->makerPayoutStatus() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $items->links('partials.articles-pagination') }}
    @else
        <p class="maker-inventory-empty">Тут з’являться нарахування за вашими виробами після підтвердження оплати.</p>
    @endif
</section>
@endsection
