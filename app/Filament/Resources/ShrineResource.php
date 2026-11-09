<?php

namespace App\Filament\Resources;

class ShrineResource extends ContentPageResource
{
    protected static ?string $slug = 'shrines';

    protected static ?string $modelLabel = 'святиню';

    protected static ?string $pluralModelLabel = 'Святині';

    protected static ?string $navigationLabel = 'Святині';

    protected static string|\UnitEnum|null $navigationGroup = 'Рідна Віра';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 6;

    protected static string $contentSection = 'shrines';

    protected static string $createLabel = 'Додати святиню';

    public static function getPages(): array
    {
        return ['index' => ShrineResource\Pages\ManageShrineEntries::route('/')];
    }
}
