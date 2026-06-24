<?php

namespace App\Filament\Pdv\Resources\SesionCajas\Pages;

use App\Enums\EstadoSesion;
use App\Filament\Pdv\Resources\SesionCajas\SesionCajaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSesionCaja extends EditRecord
{
    protected static string $resource = SesionCajaResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $estado = $data['estado'] ?? null;
        $esCerrada = $estado instanceof EstadoSesion
            ? $estado === EstadoSesion::Cerrada
            : (string) $estado === EstadoSesion::Cerrada->value;

        // Auto-rellenar fecha_cierre si se está cerrando y no se puso fecha
        if ($esCerrada && empty($data['fecha_cierre'])) {
            $data['fecha_cierre'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
