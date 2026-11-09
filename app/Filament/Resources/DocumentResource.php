<?php

namespace App\Filament\Resources;

class DocumentResource extends ContentPageResource
{
    protected static ?string $slug = 'documents';

    protected static ?string $modelLabel = 'документ';

    protected static ?string $pluralModelLabel = 'Документи';

    protected static ?string $navigationLabel = 'Документи';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?int $navigationSort = 9;

    protected static string $contentSection = 'documents';

    protected static string $createLabel = 'Додати документ';

    public static function getPages(): array
    {
        return ['index' => DocumentResource\Pages\ManageDocumentEntries::route('/')];
    }
}
