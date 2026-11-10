<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Місяць календаря</x-slot>
        <x-slot name="description">Додавайте свята, змінюйте дати й короткі описи. Повні матеріали редагуються в розділі «Свята — тексти».</x-slot>
        <div style="max-width: 360px">{{ $this->monthForm }}</div>
        <p style="margin-top: 16px; font-size: 14px">Збережіть зміни перед переходом до іншого місяця.</p>
    </x-filament::section>
    <form wire:submit="save" class="space-y-6">{{ $this->form }}<x-filament::button type="submit">Зберегти календар</x-filament::button></form>
</x-filament-panels::page>
