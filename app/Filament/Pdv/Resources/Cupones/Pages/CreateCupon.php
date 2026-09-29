<?php

namespace App\Filament\Pdv\Resources\Cupones\Pages;

use App\Filament\Pdv\Resources\Cupones\CuponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCupon extends CreateRecord
{
    protected static string $resource = CuponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = \Filament\Facades\Filament::getTenant()->id;
        $data['codigo']     = strtoupper(trim($data['codigo']));
        return $data;
    }
}
