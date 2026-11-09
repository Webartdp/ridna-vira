<div class="rounded-xl border p-4">
    <h3 class="font-bold">Склад замовлення</h3>
    @foreach($getRecord()->items as $item)<p>{{ $item->name }} · {{ $item->quantity }} шт. × {{ \App\Support\Money::display($item->price_minor) }}</p>@endforeach
    <p class="font-bold">Разом: {{ \App\Support\Money::display($getRecord()->total_minor) }}</p>
</div>
