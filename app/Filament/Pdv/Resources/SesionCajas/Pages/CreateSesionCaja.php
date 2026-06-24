<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Pages;

use App\Filament\Pdv\Resources\SesionCajas\SesionCajaResource;
use App\Models\SesionCaja;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSesionCaja extends CreateRecord
{
    protected static string $resource = SesionCajaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['estado'] = 'abierta';

        // Validar que la caja no tenga ya una sesión abierta
        $existe = SesionCaja::where('caja_id', $data['caja_id'])
            ->where('estado', 'abierta')
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'data.caja_id' => 'Esta caja ya tiene una sesión abierta.',
            ]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
