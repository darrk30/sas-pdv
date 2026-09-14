<?php

namespace App\Filament\Pdv\Resources\GastosFijos\Pages;

use App\Filament\Pdv\Resources\GastosFijos\GastoFijoResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateGastoFijo extends CreateRecord
{
    protected static string $resource = GastoFijoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = Filament::getTenant()->id;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
