<?php

namespace App\Filament\Pdv\Resources\Cajas\Pages;

use App\Filament\Pdv\Resources\Cajas\CajaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCaja extends EditRecord
{
    protected static string $resource = CajaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
