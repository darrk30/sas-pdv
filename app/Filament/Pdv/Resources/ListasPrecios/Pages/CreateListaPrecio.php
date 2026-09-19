<?php

namespace App\Filament\Pdv\Resources\ListasPrecios\Pages;

use App\Filament\Pdv\Resources\ListasPrecios\ListaPrecioResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateListaPrecio extends CreateRecord
{
    protected static string $resource = ListaPrecioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = Filament::getTenant()->id;
        return $data;
    }
}
