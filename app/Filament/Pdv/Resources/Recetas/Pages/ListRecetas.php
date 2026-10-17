<?php

namespace App\Filament\Pdv\Resources\Recetas\Pages;

use App\Filament\Pdv\Resources\Recetas\RecetaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecetas extends ListRecords
{
    protected static string $resource = RecetaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
