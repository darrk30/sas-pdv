<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Pages;

use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Resources\IngresoEgresos\IngresoEgresoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIngresoEgreso extends EditRecord
{
    protected static string $resource = IngresoEgresoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $tipo = $data['tipo'] ?? '';
        $tipo = $tipo instanceof TipoMovimiento ? $tipo->value : (string) $tipo;

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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
