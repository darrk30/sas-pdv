<?php

namespace App\Filament\Pdv\Resources\Pisos\Pages;

use App\Filament\Pdv\Resources\Pisos\PisoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPisos extends ListRecords
{
    protected static string $resource = PisoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
