<?php

namespace App\Observers;

use App\Enums\EstadoMovimiento;
use App\Enums\TipoMovimiento;
use App\Models\IngresoEgreso;
use App\Models\MetodoPago;
use App\Models\Transaccion;

class IngresoEgresoObserver
{
    public function created(IngresoEgreso $ingreso): void
    {
        // Ingresos/Egresos siempre usan Efectivo
        $efectivo = MetodoPago::where('empresa_id', $ingreso->empresa_id)
            ->where('nombre', 'Efectivo')
            ->first();

        $tipo = $ingreso->tipo instanceof TipoMovimiento
            ? $ingreso->tipo->value
            : (string) $ingreso->tipo;

        $persona = $ingreso->entregado_a ?? $ingreso->receptor?->name ?? '';
        $concepto = match (true) {
            $tipo === TipoMovimiento::Ingreso->value => 'Ingreso' . ($persona ? ": {$persona}" : ''),
            default                                  => 'Egreso'  . ($persona ? ": {$persona}" : ''),
        };

        Transaccion::create([
            'empresa_id'           => $ingreso->empresa_id,
            'sesion_caja_id'       => $ingreso->sesion_caja_id,
            'transaccionable_type' => IngresoEgreso::class,
            'transaccionable_id'   => $ingreso->id,
            'tipo'                 => $tipo,
            'concepto'             => $concepto,
            'monto'                => $ingreso->monto,
            'metodo_pago_id'       => $efectivo?->id,
            'estado'               => 'aprobado',
            'fecha'                => $ingreso->fecha_hora,
        ]);
    }

    public function updated(IngresoEgreso $ingreso): void
    {
        if ($ingreso->isDirty('estado') && $ingreso->estado === EstadoMovimiento::Anulado) {
            Transaccion::where('transaccionable_type', IngresoEgreso::class)
                ->where('transaccionable_id', $ingreso->id)
                ->update(['estado' => EstadoMovimiento::Anulado->value]);
        }
    }
}
