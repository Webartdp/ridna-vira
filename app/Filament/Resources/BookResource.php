<?php

namespace App\Filament\Resources;

use App\Models\ContentPage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs\Tab;

class BookResource extends ContentPageResource
{
    protected static ?string $slug = 'books';

    protected static ?string $modelLabel = 'книгу';

    protected static ?string $pluralModelLabel = 'Книги';

    protected static ?string $navigationLabel = 'Книги';

    protected static string|\UnitEnum|null $navigationGroup = 'Рідна Віра';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 10;

    protected static string $contentSection = 'books';

    protected static string $createLabel = 'Додати книгу';

    protected static function extraTabs(): array
    {
        return [Tab::make('Видання та файли')->schema([
            TextInput::make('book.author')->label('Автор / упорядник')->maxLength(255),
            TextInput::make('book.publisher')->label('Видавництво')->maxLength(255),
            TextInput::make('book.year')->label('Рік видання')->maxLength(100),
            TextInput::make('book.pages')->label('Кількість сторінок')->integer()->minValue(1)->maxValue(100000),
            ...collect(['pdf', 'docx'])->flatMap(fn ($format) => [
                Toggle::make('book.'.$format.'_enabled')->label('Дозволити завантаження '.strtoupper($format))->default(true),
                FileUpload::make('book.'.$format)->label('Файл '.strtoupper($format))->disk('public')->directory('books')->visibility('public')
                    ->acceptedFileTypes([$format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])->maxSize(51200)
                    ->preventFilePathTampering(true, fn (string $file, ?ContentPage $record) => $record && $file === ($record->book[$format] ?? null))
                    ->helperText('Завантажте файл для нової книги або заміни наявного. Якщо поле порожнє, для імпортованої книги використовується її початковий локальний файл. Перемикач вище приховує формат.'),
            ])->all(),
        ])->columns(2)];
    }

    public static function getPages(): array
    {
        return [
            'index' => BookResource\Pages\ManageBookEntries::route('/'),
            'edit' => BookResource\Pages\EditBookEntry::route('/{record}/edit'),
        ];
    }
}
