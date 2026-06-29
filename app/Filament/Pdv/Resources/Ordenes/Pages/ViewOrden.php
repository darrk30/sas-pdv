<?php

namespace App\Filament\Pdv\Resources\Ordenes\Pages;

use App\Filament\Pdv\Resources\Ordenes\OrdenResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrden extends ViewRecord
{
    protected static string $resource = OrdenResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
