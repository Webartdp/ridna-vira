<?php

namespace App\Filament\Resources;

class BookResource extends ContentPageResource
{
    protected static ?string $slug = 'books';

    protected static ?string $modelLabel = 'книжковий матеріал';

    protected static ?string $pluralModelLabel = 'Книги';

    protected static ?string $navigationLabel = 'Книги';

    protected static string|\UnitEnum|null $navigationGroup = 'Рідна Віра';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 10;

    protected static string $contentSection = 'books';

    protected static string $createLabel = 'Додати книжковий матеріал';

    public static function getPages(): array
    {
        return ['index' => BookResource\Pages\ManageBookEntries::route('/')];
    }
}
