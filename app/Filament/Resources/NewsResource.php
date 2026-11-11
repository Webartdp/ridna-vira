<?php

namespace App\Filament\Resources;

class NewsResource extends ContentPageResource
{
    protected static ?string $slug = 'news';

    protected static ?string $modelLabel = 'новину';

    protected static ?string $pluralModelLabel = 'Новини';

    protected static ?string $navigationLabel = 'Новини';

    protected static string|\UnitEnum|null $navigationGroup = 'Публікації';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 1;

    protected static string $contentSection = 'news';

    protected static string $createLabel = 'Додати новину';

    public static function getPages(): array
    {
        return [
            'index' => NewsResource\Pages\ManageNewsEntries::route('/'),
            'edit' => NewsResource\Pages\EditNewsEntry::route('/{record}/edit'),
        ];
    }
}
