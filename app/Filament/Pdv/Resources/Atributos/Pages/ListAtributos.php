<?php

namespace App\Filament\Pdv\Resources\Atributos\Pages;

use App\Filament\Pdv\Resources\Atributos\AtributoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAtributos extends ListRecords
{
    protected static string $resource = AtributoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
