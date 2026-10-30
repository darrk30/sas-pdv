<?php

namespace App\Filament\Pdv\Resources\TareasProgramadasPdv\Pages;

use App\Filament\Pdv\Resources\TareasProgramadasPdv\TareaProgramadaPdvResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTareaProgramadaPdv extends CreateRecord
{
    protected static string $resource = TareaProgramadaPdvResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['empresa_id'] = Filament::getTenant()->id;
        $data['tipo']       = 'envio_boletas_rc';

        if (empty($data['dias_semana'])) {
            $data['dias_semana'] = null;
        }

        return $data;
    }
}
