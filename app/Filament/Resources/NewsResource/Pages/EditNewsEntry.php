<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\ContentPageResource\Pages\EditContentPage;
use App\Filament\Resources\NewsResource;

class EditNewsEntry extends EditContentPage
{
    protected static string $resource = NewsResource::class;
}
