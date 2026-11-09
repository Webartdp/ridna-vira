<?php

namespace App\Filament\Resources;

class HolidayResource extends ContentPageResource
{
    protected static ?string $slug = 'holidays';

    protected static ?string $modelLabel = 'опис свята';

    protected static ?string $pluralModelLabel = 'Свята — тексти';

    protected static ?string $navigationLabel = 'Свята — тексти';

    protected static string|\UnitEnum|null $navigationGroup = 'Рідна Віра';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 5;

    protected static string $contentSection = 'holidays';

    protected static string $createLabel = 'Додати опис свята';

    public static function getPages(): array
    {
        return ['index' => HolidayResource\Pages\ManageHolidayEntries::route('/')];
    }
}
