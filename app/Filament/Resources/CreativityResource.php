<?php

namespace App\Filament\Resources;

class CreativityResource extends ContentPageResource
{
    protected static ?string $slug = 'creativity';

    protected static ?string $modelLabel = 'творчий матеріал';

    protected static ?string $pluralModelLabel = 'Творчість';

    protected static ?string $navigationLabel = 'Творчість';

    protected static string|\UnitEnum|null $navigationGroup = 'Публікації';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?int $navigationSort = 3;

    protected static string $contentSection = 'creativity';

    protected static string $createLabel = 'Додати творчий матеріал';

    public static function getPages(): array
    {
        return ['index' => CreativityResource\Pages\ManageCreativityEntries::route('/')];
    }
}
