<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\TelegramAlerts;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Додати товар')->modalWidth('6xl')
                ->after(fn (Product $record) => app(TelegramAlerts::class)->productAdded($record, auth()->user())),
        ];
    }
}
