<?php

namespace App\Filament\Pdv\Resources\Proveedores\Pages;

use App\Filament\Pdv\Resources\Proveedores\ProveedorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProveedores extends ListRecords
{
    protected static string $resource = ProveedorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
