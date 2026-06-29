<?php

namespace App\Filament\Pdv\Resources\Ordenes\Pages;

use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrden extends CreateRecord
{
    protected static string $resource = OrdenResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['vendedor_id'] = auth()->id();

        return $data;
    }
}
