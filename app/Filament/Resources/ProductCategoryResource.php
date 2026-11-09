<?php

namespace App\Filament\Resources;

use App\Models\ProductCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductCategoryResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $modelLabel = 'категорію';

    protected static ?string $pluralModelLabel = 'Категорії товарів';

    protected static string|\UnitEnum|null $navigationGroup = 'Крамниця';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Назва')->required()->maxLength(190), TextInput::make('slug')->label('Адреса')->required()->regex('/^[a-z0-9-]+$/D')->maxLength(190)->unique(ignoreRecord: true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Назва')->searchable(), TextColumn::make('slug')->label('Адреса')])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ProductCategoryResource\Pages\ManageCategories::route('/')];
    }
}
