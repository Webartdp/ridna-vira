<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\CommunityMap;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $this->form->fill([
            'circles' => CommunityMap::circles(),
            'locations' => CommunityMap::locations(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Repeater::make('circles')->label('Крайові кола')->addActionLabel('Додати коло')
                ->collapsed()->live()->minItems(1)
                ->itemLabel(fn (array $state) => $state['label'] ?? 'Нове коло')
                ->schema([
                    TextInput::make('label')->label('Назва кола')->required()->maxLength(150)->live(onBlur: true),
                    TextInput::make('id')->label('Код кола')->required()->regex('/^[a-z0-9-]+$/D')
                        ->notIn(['all'])->distinct()->maxLength(100)->live(onBlur: true)
                        ->helperText('Латинські літери, цифри й дефіси. Перед видаленням кола перепризначте його осередки.'),
                ])->columns(2),
            Repeater::make('locations')->label('Населені пункти')->collapsed()->itemLabel(fn (array $state) => $state['city'] ?? 'Нове місто')->reorderable()->maxItems(500)->schema([
                TextInput::make('id')->label('Код міста')->required()->regex('/^[a-z0-9-]+$/D')->maxLength(100)->distinct(),
                TextInput::make('city')->label('Місто / селище')->required()->maxLength(150),
                TextInput::make('region')->label('Область')->required()->maxLength(150),
                Select::make('circle')->label('Крайове Коло')->options(fn (): array => $this->circleOptions())->required()->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                        if (! $state) {
                            return;
                        }
                        $entries = $get('entries') ?? [];
                        foreach ($entries as &$entry) {
                            if (($entry['circle'] ?? null) === $old) {
                                $entry['circle'] = $state;
                            }
                        }
                        unset($entry);
                        $set('entries', $entries);
                    }),
                TextInput::make('lat')->label('Широта')->numeric()->required()->minValue(-90)->maxValue(90),
                TextInput::make('lng')->label('Довгота')->numeric()->required()->minValue(-180)->maxValue(180),
                Repeater::make('entries')->label('Громади та представництва')->minItems(1)->schema([
                    TextInput::make('title')->label('Назва')->required()->maxLength(190),
                    Select::make('type')->label('Тип')->options(['community' => 'Громада', 'representation' => 'Представництво'])->default('community')->required(),
                    Select::make('circle')->label('Крайове Коло')->options(fn (): array => $this->circleOptions())
                        ->default(fn (Get $get) => $get('../../circle'))->required(),
                    TextInput::make('person')->label('Представник')->maxLength(190), TextInput::make('role')->label('Посада')->maxLength(500),
                    TextInput::make('address')->label('Адреса')->maxLength(500),
                    TagsInput::make('phones')->label('Телефони')->nestedRecursiveRules(['string', 'max:50']),
                    TagsInput::make('emails')->label('Електронна пошта')->nestedRecursiveRules(['email', 'max:190']),
                ])->columns(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    private function circleOptions(): array
    {
        return array_column(array_filter($this->data['circles'] ?? [], fn (array $circle) => filled($circle['id'] ?? null) && filled($circle['label'] ?? null)), 'label', 'id');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $labels = array_column($state['circles'], 'label', 'id');
        foreach ($state['locations'] as &$location) {
            $assigned = [$location['circle'], ...array_column($location['entries'], 'circle')];
            if (array_diff($assigned, array_keys($labels))) {
                throw ValidationException::withMessages(['data.circles' => 'Перепризначте населені пункти та осередки, які належать до видаленого кола.']);
            }
            $location['circleLabel'] = $labels[$location['circle']];
        }
        unset($location);
        DB::transaction(function () use ($state) {
            SiteSetting::write('community_circles', json_encode($state['circles'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            SiteSetting::write('communities', json_encode($state['locations'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        });
        Notification::make()->title('Громади та мапу оновлено')->success()->send();
    }
}
