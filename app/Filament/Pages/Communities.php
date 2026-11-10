<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class Communities extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('content') ?? false;
    }

    protected static ?string $title = 'Громади та мапа';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected string $view = 'filament.communities';

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['locations' => json_decode(SiteSetting::read('communities', json_encode(config('communities'), JSON_UNESCAPED_UNICODE)), true)]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Repeater::make('locations')->label('Населені пункти')->collapsed()->itemLabel(fn (array $state) => $state['city'] ?? 'Нове місто')->reorderable()->maxItems(500)->schema([
                TextInput::make('id')->label('Код міста')->required()->regex('/^[a-z0-9-]+$/D')->maxLength(100)->distinct(),
                TextInput::make('city')->label('Місто / селище')->required()->maxLength(150),
                TextInput::make('region')->label('Область')->required()->maxLength(150),
                Select::make('circle')->label('Крайове Коло')->options(['zaporizke' => 'Запорозьке Коло', 'sicheslavske' => 'Січеславське Коло', 'zahidne' => 'Західне Коло'])->required(),
                TextInput::make('circleLabel')->label('Назва Кола')->required()->maxLength(150),
                TextInput::make('lat')->label('Широта')->numeric()->required()->minValue(-90)->maxValue(90),
                TextInput::make('lng')->label('Довгота')->numeric()->required()->minValue(-180)->maxValue(180),
                Repeater::make('entries')->label('Громади та представництва')->minItems(1)->schema([
                    TextInput::make('title')->label('Назва')->required()->maxLength(190),
                    Select::make('type')->label('Тип')->options(['community' => 'Громада', 'representation' => 'Представництво'])->default('community')->required(),
                    Select::make('circle')->label('Крайове Коло')->options(['zaporizke' => 'Запорозьке', 'sicheslavske' => 'Січеславське', 'zahidne' => 'Західне'])->required(),
                    TextInput::make('person')->label('Представник')->maxLength(190), TextInput::make('role')->label('Посада')->maxLength(500),
                    TextInput::make('address')->label('Адреса')->maxLength(500),
                    TagsInput::make('phones')->label('Телефони')->nestedRecursiveRules(['string', 'max:50']),
                    TagsInput::make('emails')->label('Електронна пошта')->nestedRecursiveRules(['email', 'max:190']),
                ])->columns(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        SiteSetting::write('communities', json_encode($state['locations'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        Notification::make()->title('Громади та мапу оновлено')->success()->send();
    }
}
