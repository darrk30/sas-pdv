<?php

namespace App\Filament\Pdv\Resources\Gastos\Pages;

use App\Filament\Pdv\Resources\Gastos\GastoResource;
use App\Models\Gasto;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateGasto extends CreateRecord
{
    protected static string $resource = GastoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $empresaId = Filament::getTenant()->id;

        $data['empresa_id']  = $empresaId;
        $data['user_id']     = auth()->id();
        $data['serie']       = 'G';
        $data['correlativo'] = Gasto::siguienteCorrelativo($empresaId, 'G');
        $data['estado']      = 'pendiente';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
