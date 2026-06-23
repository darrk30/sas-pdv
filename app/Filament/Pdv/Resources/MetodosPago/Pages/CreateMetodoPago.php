<?php

namespace App\Filament\Pdv\Resources\MetodosPago\Pages;

use App\Filament\Pdv\Resources\MetodosPago\MetodoPagoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMetodoPago extends CreateRecord
{
    protected static string $resource = MetodoPagoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
