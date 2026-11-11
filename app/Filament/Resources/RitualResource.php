<?php

namespace App\Filament\Resources;

class RitualResource extends ContentPageResource
{
    protected static ?string $slug = 'rituals';

    protected static ?string $modelLabel = 'обряд';

    protected static ?string $pluralModelLabel = 'Обряди';

    protected static ?string $navigationLabel = 'Обряди';

    protected static string|\UnitEnum|null $navigationGroup = 'Рідна Віра';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static ?int $navigationSort = 7;

    protected static string $contentSection = 'rituals';

    protected static string $createLabel = 'Додати обряд';

    public static function getPages(): array
    {
        return [
            'index' => RitualResource\Pages\ManageRitualEntries::route('/'),
            'edit' => RitualResource\Pages\EditRitualEntry::route('/{record}/edit'),
        ];
    }
}
