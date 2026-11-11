<?php

namespace App\Filament\Resources;

class PrayerResource extends ContentPageResource
{
    protected static ?string $slug = 'prayers';

    protected static ?string $modelLabel = 'молитву або славу';

    protected static ?string $pluralModelLabel = 'Молитви та слави';

    protected static ?string $navigationLabel = 'Молитви та слави';

    protected static string|\UnitEnum|null $navigationGroup = 'Рідна Віра';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 8;

    protected static string $contentSection = 'prayers';

    protected static string $createLabel = 'Додати молитву або славу';

    public static function getPages(): array
    {
        return [
            'index' => PrayerResource\Pages\ManagePrayerEntries::route('/'),
            'edit' => PrayerResource\Pages\EditPrayerEntry::route('/{record}/edit'),
        ];
    }
}
