<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Pages;

use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Resources\IngresoEgresos\IngresoEgresoResource;
use App\Filament\Pdv\Resources\IngresoEgresos\Schemas\IngresoEgresoForm;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateIngresoEgreso extends CreateRecord
{
    protected static string $resource = IngresoEgresoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Verificar que existe una sesión abierta
        $sesion = IngresoEgresoForm::sesionAbierta();

        if (! $sesion) {
            Notification::make()
                ->danger()
                ->title('Sin sesión de caja abierta')
                ->body('Debes aperturar una caja antes de registrar movimientos.')
                ->send();

            throw new Halt();
        }

        $data['sesion_caja_id'] = $sesion->id;
        $data['estado']         = 'aprobado';

        // Limpiar campos que no aplican
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
}
