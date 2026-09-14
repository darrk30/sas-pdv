<?php

namespace App\Filament\Pdv\Resources\GastosFijos\Pages;

use App\Filament\Pdv\Resources\GastosFijos\GastoFijoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGastoFijo extends EditRecord
{
    protected static string $resource = GastoFijoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
