<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Зберегти налаштування</x-filament::button>
    </form>
    <div class="rounded-xl border p-5 space-y-3">
        <h2 class="font-bold">Підключення WayForPay</h2>
        <p>Внесіть реквізити магазину з кабінету WayForPay. Приймання карток стане доступним після активації магазину платіжною системою.</p>
        <p>Адреса підтверджень платежу: <code>{{ route('shop.webhook') }}</code>. Сайт передає її автоматично. HTTPS має бути ввімкнений.</p>
        <p>Після повернення покупця статус змінюється лише після перевіреного повідомлення WayForPay. Повернення коштів оформлюйте в кабінеті WayForPay.</p>
    </div>
</x-filament-panels::page>
