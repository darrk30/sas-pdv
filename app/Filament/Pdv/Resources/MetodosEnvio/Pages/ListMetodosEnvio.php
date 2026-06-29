<?php

namespace App\Filament\Pdv\Resources\MetodosEnvio\Pages;

use App\Filament\Pdv\Resources\MetodosEnvio\MetodoEnvioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetodosEnvio extends ListRecords
{
    protected static string $resource = MetodoEnvioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
