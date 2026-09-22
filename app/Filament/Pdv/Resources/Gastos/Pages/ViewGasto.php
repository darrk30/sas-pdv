<?php

namespace App\Filament\Pdv\Resources\Gastos\Pages;

use App\Filament\Pdv\Resources\Gastos\GastoResource;
use Filament\Resources\Pages\ViewRecord;

class ViewGasto extends ViewRecord
{
    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
