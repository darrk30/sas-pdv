<?php

namespace App\Filament\Pdv\Resources\Proveedores\Pages;

use App\Filament\Pdv\Resources\Proveedores\ProveedorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProveedor extends EditRecord
{
    protected static string $resource = ProveedorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
