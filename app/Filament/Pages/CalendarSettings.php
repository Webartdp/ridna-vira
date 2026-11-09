<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CalendarSettings extends Page
{
    protected static ?string $title = 'Календар свят';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected string $view = 'filament.calendar-settings';

    public ?array $data = [];

    public string $month = 'Січень';

    public function mount(): void
    {
        $this->loadMonth();
    }

    public function updatedMonth(): void
    {
        $this->loadMonth();
    }

    private function loadMonth(): void
    {
        $this->validate(['month' => [Rule::in(array_keys(config('faith_calendar.months')))]]);
        $calendar = json_decode(SiteSetting::read('calendar', json_encode(config('faith_calendar'), JSON_UNESCAPED_UNICODE)), true);
        $entries = [];
        foreach ($calendar['months'][$this->month] ?? [] as $holiday) {
            $entries[] = [...$holiday, 'slug' => $holiday['slug'] ?? Str::slug($holiday['name']), 'description' => $holiday['description'] ?? config('faith_holidays')[$holiday['name']] ?? ''];
        }
        $this->form->fill(['entries' => $entries, 'notes' => implode("\n", $calendar['notes'] ?? [])]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Repeater::make('entries')->label('Свята')->collapsed()->itemLabel(fn (array $state) => ($state['month'] ?? '').' · '.($state['day'] ?? '').' · '.($state['name'] ?? 'Нове свято'))->maxItems(500)->schema([
                TextInput::make('day')->label('День')->required()->integer()->minValue(1)->maxValue(31),
                TextInput::make('name')->label('Назва')->required()->maxLength(190),
                TextInput::make('slug')->label('Код адреси')->required()->regex('/^[a-z0-9-]+$/D')->maxLength(190)->distinct()->helperText('Збережіть код наявного свята, щоб його посилання продовжувало працювати.'),
                Textarea::make('description')->label('Короткий опис')->maxLength(10000)->columnSpanFull(),
            ])->columns(2),
            Textarea::make('notes')->label('Примітки (кожна з нового рядка)')->rows(5)->maxLength(10000),
        ]);
    }

    public function save(): void
    {
        $this->validate(['month' => [Rule::in(array_keys(config('faith_calendar.months')))]]);
        $data = $this->form->getState();
        $calendar = json_decode(SiteSetting::read('calendar', json_encode(config('faith_calendar'), JSON_UNESCAPED_UNICODE)), true);
        $calendar['months'][$this->month] = [];
        $calendar['notes'] = array_values(array_filter(explode("\n", $data['notes'] ?? '')));
        foreach ($data['entries'] as $entry) {
            $entry['day'] = str_pad((string) $entry['day'], 2, '0', STR_PAD_LEFT);
            $calendar['months'][$this->month][] = $entry;
        }
        foreach ($calendar['months'] as &$holidays) {
            usort($holidays, fn ($a, $b) => (int) $a['day'] <=> (int) $b['day']);
        }
        unset($holidays);
        SiteSetting::write('calendar', json_encode($calendar, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        Notification::make()->title('Календар оновлено')->success()->send();
    }
}
