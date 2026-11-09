<?php

namespace App\Filament\Resources;

class ArticleResource extends ContentPageResource
{
    protected static ?string $slug = 'articles';

    protected static ?string $modelLabel = 'статтю';

    protected static ?string $pluralModelLabel = 'Статті';

    protected static ?string $navigationLabel = 'Статті';

    protected static string|\UnitEnum|null $navigationGroup = 'Публікації';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    protected static string $contentSection = 'articles';

    protected static string $createLabel = 'Додати статтю';

    public static function getPages(): array
    {
        return ['index' => ArticleResource\Pages\ManageArticleEntries::route('/')];
    }
}
