<?php

namespace App\Filament\Resources\PaymentMethodResource\Pages;

use App\Filament\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ManagePaymentMethods extends ManageRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Додати спосіб оплати')->createAnother(false)
                ->using(function (array $data): PaymentMethod {
                    try {
                        return PaymentMethod::create($data);
                    } catch (UniqueConstraintViolationException) {
                        $statePath = $this->getMountedActionSchema()?->getStatePath();
                        $field = $statePath ? $statePath.'.driver' : 'driver';

                        throw ValidationException::withMessages([$field => 'Спосіб оплати такого типу вже існує.']);
                    }
                }),
        ];
    }
}
