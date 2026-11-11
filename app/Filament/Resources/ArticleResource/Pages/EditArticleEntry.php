<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ContentPageResource\Pages\EditContentPage;

class EditArticleEntry extends EditContentPage
{
    protected static string $resource = ArticleResource::class;
}
