<?php

namespace App\Filament\Resources\ContentPageResource\Pages;

use App\Filament\Resources\ContentPageResource;
use App\Support\ContentEditor;
use Filament\Resources\Pages\EditRecord;

class EditContentPage extends EditRecord
{
    protected static string $resource = ContentPageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ContentEditor::prepare($data);
    }
}
