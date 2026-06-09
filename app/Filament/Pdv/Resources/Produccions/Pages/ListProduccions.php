<?php

namespace App\Filament\Pdv\Resources\Produccions\Pages;

use App\Filament\Pdv\Resources\Produccions\ProduccionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduccions extends ListRecords
{
    protected static string $resource = ProduccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
