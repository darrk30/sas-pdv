<?php

namespace App\Filament\Pdv\Resources\Promociones\Pages;

use App\Filament\Pdv\Resources\Promociones\PromocionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromociones extends ListRecords
{
    protected static string $resource = PromocionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva Promoción'),
        ];
    }
}
