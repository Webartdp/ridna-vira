<?php

namespace App\Filament\Resources;

use App\Models\ProductCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ProductCategoryResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $modelLabel = 'категорію';

    protected static ?string $pluralModelLabel = 'Категорії товарів';

    protected static string|\UnitEnum|null $navigationGroup = 'Крамниця';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Назва')->required()->maxLength(190),
            TextInput::make('slug')->label('Адреса')->required()->regex('/^[a-z0-9-]+$/D')->maxLength(190)->unique(ignoreRecord: true),
            Select::make('parent_id')->label('Основна категорія')->placeholder('Самостійна категорія')
                ->options(fn (?ProductCategory $record) => ProductCategory::whereNull('parent_id')
                    ->when($record, fn ($query) => $query->whereKeyNot($record->id))->orderBy('name')->pluck('name', 'id'))
                ->disabled(fn (?ProductCategory $record) => $record?->children()->exists() ?? false)
                ->rule(fn (?ProductCategory $record) => Rule::exists('product_categories', 'id')
                    ->whereNull('parent_id')->whereNot('id', $record?->id ?? 0))
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Назва')->searchable(), TextColumn::make('parent.name')->label('Основна категорія')->placeholder('—')->sortable(), TextColumn::make('slug')->label('Адреса')])->recordActions([EditAction::make(), DeleteAction::make()->visible(fn (ProductCategory $record) => ! $record->children_exists)]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withExists('children');
    }

    public static function getPages(): array
    {
        return ['index' => ProductCategoryResource\Pages\ManageCategories::route('/')];
    }
}
