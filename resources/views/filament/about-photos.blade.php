<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">Зберегти світлини</x-filament::button>
            <x-filament::button tag="a" :href="route('about')" target="_blank" rel="noopener" color="gray">Переглянути сторінку</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
