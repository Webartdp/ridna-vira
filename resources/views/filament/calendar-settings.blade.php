<x-filament-panels::page>
    <p>Тут можна додати свято, змінити дату та короткий опис. Повні тексти свят редагуються в розділі «Свята — тексти».</p>
    <label>Місяць <select wire:model.live="month" class="rounded-lg border p-2">@foreach(array_keys(config('faith_calendar.months')) as $month)<option value="{{ $month }}">{{ $month }}</option>@endforeach</select></label>
    <p>Збережіть зміни перед переходом до іншого місяця.</p>
    <form wire:submit="save" class="space-y-6">{{ $this->form }}<x-filament::button type="submit">Зберегти календар</x-filament::button></form>
</x-filament-panels::page>
