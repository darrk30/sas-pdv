<?php

namespace App\Filament\Pdv\Resources\Cupones\Pages;

use App\Filament\Pdv\Resources\Cupones\CuponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCupones extends ListRecords
{
    protected static string $resource = CuponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo cupón'),
        ];
    }
}
