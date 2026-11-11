<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Filament\Resources\ContentPageResource\Pages\EditContentPage;

class EditBookEntry extends EditContentPage
{
    protected static string $resource = BookResource::class;
}
