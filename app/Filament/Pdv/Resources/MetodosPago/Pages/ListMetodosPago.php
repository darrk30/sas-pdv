<?php

namespace App\Filament\Pdv\Resources\MetodosPago\Pages;

use App\Filament\Pdv\Resources\MetodosPago\MetodoPagoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetodosPago extends ListRecords
{
    protected static string $resource = MetodoPagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
