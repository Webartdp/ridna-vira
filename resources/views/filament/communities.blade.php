<x-filament-panels::page>
    <p>Зміни одразу з’являться на сторінці зв’язку та інтерактивній мапі.</p>
    <form wire:submit="save" class="space-y-6">{{ $this->form }}<x-filament::button type="submit">Зберегти громади</x-filament::button></form>
</x-filament-panels::page>
