<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderItem;
use App\Services\AdminOrderEditor;
use App\Support\Money;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $order = $this->getRecord();
        if ($order->canEditItems()) {
            $data['items'] = $order->items()->orderBy('id')->get()->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'original_name' => $item->name,
                'unit_price' => Money::display($item->price_minor),
            ])->all();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(AdminOrderEditor::class)->update($record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $field) => ['data.'.$field => $messages])->all(),
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl();
    }
}
