<?php

namespace App\Filament\Pdv\Resources\IngresoEgresos\Pages;

use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Resources\IngresoEgresos\IngresoEgresoResource;
use App\Filament\Pdv\Resources\IngresoEgresos\Schemas\IngresoEgresoForm;
use App\Models\MetodoPago;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;

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

        // Validar saldo suficiente para egresos
        $tipo = $data['tipo'] ?? '';
        $tipoVal = $tipo instanceof TipoMovimiento ? $tipo->value : (string) $tipo;

        if ($tipoVal === TipoMovimiento::Egreso->value && ! empty($data['metodo_pago_id'])) {
            $saldo = (float) (DB::table('transacciones')
                ->where('sesion_caja_id', $sesion->id)
                ->where('metodo_pago_id', $data['metodo_pago_id'])
                ->where('estado', 'aprobado')
                ->selectRaw("SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) AS saldo")
                ->value('saldo') ?? 0);

            if ($saldo < (float) ($data['monto'] ?? 0)) {
                $metodo = MetodoPago::find($data['metodo_pago_id'])?->nombre ?? 'el método de pago';
                Notification::make()
                    ->danger()
                    ->title('Saldo insuficiente')
                    ->body("No hay saldo suficiente en {$metodo}. Disponible: S/ " . number_format($saldo, 2) . ".")
                    ->send();

                throw new Halt();
            }
        }

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
