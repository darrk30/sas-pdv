<?php

namespace App\Filament\Pdv\Resources\MetodosPago\Pages;

use App\Filament\Pdv\Resources\MetodosPago\MetodoPagoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMetodoPago extends EditRecord
{
    protected static string $resource = MetodoPagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
