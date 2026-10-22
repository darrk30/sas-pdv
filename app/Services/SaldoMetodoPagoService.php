<?php

namespace App\Services;

use App\Models\IngresoEgreso;
use Illuminate\Support\Facades\DB;

class SaldoMetodoPagoService
{
    /**
     * Saldo acumulado de un método de pago para una empresa.
     *
     * Suma:  ventas completadas + ingresos de caja
     * Resta: egresos de caja + gastos aprobados
     */
    public static function saldo(int $empresaId, int $metodoPagoId): float
    {
        // 1. Ventas completadas — solo métodos contado (crédito = por cobrar, no en caja)
        $ventas = (float) DB::table('venta_pagos')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('metodos_pago.condicion_pago', 'contado')
            ->where('venta_pagos.metodo_pago_id', $metodoPagoId)
            ->sum('venta_pagos.monto');

        // 2. Ingresos/egresos de caja (aprobados)
        $movimientos = (float) DB::table('transacciones')
            ->where('empresa_id', $empresaId)
            ->where('metodo_pago_id', $metodoPagoId)
            ->where('estado', 'aprobado')
            ->where('transaccionable_type', IngresoEgreso::class)
            ->selectRaw("SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) AS saldo")
            ->value('saldo') ?? 0;

        // 3. Gastos variables (no anulados)
        $gastos = (float) DB::table('gastos')
            ->where('empresa_id', $empresaId)
            ->where('metodo_pago_id', $metodoPagoId)
            ->where('estado', '!=', 'anulado')
            ->sum('monto');

        // 4. Compras confirmadas y pagadas con este método
        $compras = (float) DB::table('compra_pagos')
            ->join('compras', 'compras.id', '=', 'compra_pagos.compra_id')
            ->where('compras.empresa_id', $empresaId)
            ->where('compras.estado', 'confirmado')
            ->where('compras.estado_pago', 'pagado')
            ->where('compra_pagos.metodo_pago_id', $metodoPagoId)
            ->sum('compra_pagos.monto');

        return round($ventas + $movimientos - $gastos - $compras, 2);
    }

    /**
     * Saldo de todos los métodos de pago de una empresa.
     * Retorna array [ metodo_pago_id => saldo ]
     */
    public static function saldosTodos(int $empresaId): array
    {
        $metodos = DB::table('metodos_pago')
            ->where('empresa_id', $empresaId)
            ->pluck('id')
            ->all();

        $result = [];
        foreach ($metodos as $id) {
            $result[$id] = self::saldo($empresaId, $id);
        }

        return $result;
    }
}
