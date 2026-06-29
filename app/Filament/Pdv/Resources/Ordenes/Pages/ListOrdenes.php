<?php

namespace App\Filament\Pdv\Resources\Ordenes\Pages;

use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrdenes extends ListRecords
{
    protected static string $resource = OrdenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
