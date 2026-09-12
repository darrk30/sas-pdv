<?php

namespace App\Filament\Pdv\Resources\Pisos\Pages;

use App\Filament\Pdv\Resources\Pisos\PisoResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePiso extends CreateRecord
{
    protected static string $resource = PisoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = Filament::getTenant()->id;
        return $data;
    }
}
