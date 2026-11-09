<?php

namespace App\Filament\Resources;

use App\Models\Product;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'товар';

    protected static ?string $pluralModelLabel = 'Товари';

    protected static string|\UnitEnum|null $navigationGroup = 'Крамниця';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Назва')->required()->maxLength(255),
            TextInput::make('slug')->label('Адреса товару')->required()->regex('/^[a-z0-9-]+$/D')->maxLength(190)->unique(ignoreRecord: true)->helperText('Наприклад: knyha-ridna-vira'),
            TextInput::make('sku')->label('Артикул')->maxLength(100)->unique(ignoreRecord: true),
            Select::make('product_category_id')->label('Категорія')->relationship('category', 'name')->searchable()->preload(),
            TextInput::make('price_minor')->label('Ціна, грн')->required()->numeric()->minValue(0.01)->maxValue(9999999.99)->step('0.01')
                ->formatStateUsing(fn ($state) => $state === null ? null : Money::decimal((int) $state))->dehydrateStateUsing(fn ($state) => Money::minor((string) $state)),
            TextInput::make('stock')->label('Залишок, шт.')->integer()->required()->minValue(0)->maxValue(1000000)->default(0)->helperText('Показано вільний залишок, без зарезервованих замовлень.'),
            Hidden::make('stock_before')->afterStateHydrated(fn ($component, ?Product $record) => $component->state($record?->stock))->dehydrated(fn (?Product $record) => $record !== null),
            Toggle::make('is_active')->label('Показувати в крамниці')->default(false),
            FileUpload::make('images')->label('Фотографії')->multiple()->reorderable()->image()->imageResizeMode('contain')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1600')->imageResizeUpscale(false)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('products')->visibility('public')->maxSize(4096)->maxFiles(8)->columnSpanFull(),
            RichEditor::make('description')->label('Опис')->fileAttachmentsDisk('public')->fileAttachmentsDirectory('products')->fileAttachmentsVisibility('public')->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->fileAttachmentsMaxSize(4096)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Товар')->searchable()->sortable(),
            TextColumn::make('sku')->label('Артикул')->searchable(),
            TextColumn::make('price_minor')->label('Ціна')->formatStateUsing(fn ($state) => Money::display((int) $state))->sortable(),
            TextColumn::make('stock')->label('У наявності')->sortable(),
            IconColumn::make('is_active')->label('Активний')->boolean(),
        ])->filters([TrashedFilter::make()])->recordActions([EditAction::make()->modalWidth('6xl')->using(function (Product $record, array $data): Product {
            return DB::transaction(function () use ($record, $data) {
                $locked = Product::lockForUpdate()->findOrFail($record->id);
                if ((int) ($data['stock_before'] ?? -1) !== $locked->stock) {
                    throw ValidationException::withMessages(['stock' => 'Залишок змінився під час редагування. Відкрийте товар повторно.']);
                }
                unset($data['stock_before']);
                $locked->fill($data)->save();

                return $locked;
            }, 3);
        }), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return ['index' => ProductResource\Pages\ManageProducts::route('/')];
    }
}
