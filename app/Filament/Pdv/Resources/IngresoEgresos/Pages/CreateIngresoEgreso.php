<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Pages;

use App\Filament\Pdv\Resources\IngresoEgresos\IngresoEgresoResource;
use App\Enums\TipoMovimiento;
use Filament\Resources\Pages\CreateRecord;

class CreateIngresoEgreso extends CreateRecord
{
    protected static string $resource = IngresoEgresoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tipo = $data['tipo'] ?? '';
        $tipo = $tipo instanceof TipoMovimiento ? $tipo->value : (string) $tipo;

        // Limpiar campos que no aplican según el tipo
        if ($tipo === TipoMovimiento::Ingreso->value) {
            $data['categoria']        = null;
            $data['user_receptor_id'] = null;
        } else {
            $categoria = $data['categoria'] ?? '';
            $categoria = $categoria instanceof \BackedEnum ? $categoria->value : (string) $categoria;

            if ($categoria === 'remuneracion') {
                $data['entregado_a'] = null;
            } else {
                $data['user_receptor_id'] = null;
            }
        }

        return $data;
    }
}
