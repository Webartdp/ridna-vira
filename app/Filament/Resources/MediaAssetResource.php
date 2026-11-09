<?php

namespace App\Filament\Resources;

use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAssetResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = MediaAsset::class;

    protected static ?string $modelLabel = 'файл';

    protected static ?string $pluralModelLabel = 'Файли та видання';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Назва')->required()->maxLength(190),
            FileUpload::make('file')->label('Файл')->required()->disk('public')->directory('library')->visibility('public')->maxSize(20480)
                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/vnd.djvu', 'image/x-djvu', 'audio/mpeg', 'image/jpeg', 'image/png', 'image/webp'])
                ->helperText('PDF, DOC, DOCX, DJVU, MP3 або зображення. До 20 МБ. Після збереження скопіюйте адресу й додайте посилання до потрібної сторінки.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Назва')->searchable(),
            TextColumn::make('file')->label('Адреса файлу')->formatStateUsing(fn ($state) => Storage::disk('public')->url($state))->copyable()->copyableState(fn ($state) => Storage::disk('public')->url($state))->limit(60),
        ])->recordActions([EditAction::make(), Action::make('open')->label('Відкрити')->url(fn (MediaAsset $record) => Storage::disk('public')->url($record->file))->openUrlInNewTab()]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => MediaAssetResource\Pages\ManageMediaAssets::route('/')];
    }
}
