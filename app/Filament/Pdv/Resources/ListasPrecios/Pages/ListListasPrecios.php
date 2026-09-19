<?php

namespace App\Filament\Pdv\Resources\ListasPrecios\Pages;

use App\Filament\Pdv\Resources\ListasPrecios\ListaPrecioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListasPrecios extends ListRecords
{
    protected static string $resource = ListaPrecioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
