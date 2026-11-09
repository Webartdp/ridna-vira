<?php

namespace App\Filament\Resources;

class GodResource extends ContentPageResource
{
    protected static ?string $slug = 'gods';

    protected static ?string $modelLabel = 'опис Бога';

    protected static ?string $pluralModelLabel = 'Боги';

    protected static ?string $navigationLabel = 'Боги';

    protected static string|\UnitEnum|null $navigationGroup = 'Рідна Віра';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static ?int $navigationSort = 4;

    protected static string $contentSection = 'gods';

    protected static string $createLabel = 'Додати опис Бога';

    public static function getPages(): array
    {
        return ['index' => GodResource\Pages\ManageGodEntries::route('/')];
    }
}
