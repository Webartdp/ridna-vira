<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommunityResource extends ContentPageResource
{
    protected static ?string $slug = 'community-pages';

    protected static ?string $modelLabel = 'громаду';

    protected static ?string $pluralModelLabel = 'Громади';

    protected static ?string $navigationLabel = 'Громади';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string $contentSection = 'communities';

    protected static string $createLabel = 'Додати громаду';

    protected static function extraTabs(): array
    {
        return [Tab::make('Громада та головна')->schema([
            Toggle::make('community.featured')->label('Показувати на головній')->default(false)->helperText('На головній відображаються лише опубліковані громади.'),
            TextInput::make('community.position')->label('Порядок на головній')->numeric()->minValue(0)->maxValue(9999)->default(100)->required()->helperText('Менше число — ближче до початку.')->dehydrateStateUsing(fn ($state) => (int) $state),
            TextInput::make('community.city')->label('Місто / селище')->required()->maxLength(150),
            Select::make('community.type')->label('Тип осередку')->options(['community' => 'Громада', 'representation' => 'Представництво'])->default('community')->required(),
            TextInput::make('community.region')->label('Область')->maxLength(150),
            TextInput::make('community.person')->label('Представник / провідник')->maxLength(190),
            TextInput::make('community.role')->label('Посада')->maxLength(500),
            TextInput::make('community.address')->label('Адреса / місце зустрічей')->maxLength(500),
            TagsInput::make('community.phones')->label('Телефони')->nestedRecursiveRules(['string', 'max:50']),
            TagsInput::make('community.emails')->label('Електронна пошта')->nestedRecursiveRules(['email', 'max:190']),
        ])->columns(2)];
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->columns([
            TextColumn::make('title')->label('Громада')->searchable()->wrap()->weight('semibold'),
            TextColumn::make('community.city')->label('Місто'),
            IconColumn::make('community.featured')->label('На головній')->boolean(),
            TextColumn::make('community.position')->label('Порядок'),
            IconColumn::make('is_published')->label('Опубліковано')->boolean(),
            TextColumn::make('updated_at')->label('Оновлено')->date('d.m.Y')->sortable(),
        ])->defaultSort('title')->recordUrl(null);
    }

    public static function getPages(): array
    {
        return ['index' => CommunityResource\Pages\ManageCommunities::route('/')];
    }
}
