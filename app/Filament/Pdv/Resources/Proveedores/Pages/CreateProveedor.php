<?php

namespace App\Filament\Pdv\Resources\Proveedores\Pages;

use App\Filament\Pdv\Resources\Proveedores\ProveedorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProveedor extends CreateRecord
{
    protected static string $resource = ProveedorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
