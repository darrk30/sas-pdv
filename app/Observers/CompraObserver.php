<?php

namespace App\Observers;

use App\Enums\EstadoDespacho;
use App\Models\Compra;
use App\Services\InventarioCoreService;

class CompraObserver
{
    public function creating(Compra $compra): void
    {
        $siguiente = Compra::where('empresa_id', $compra->empresa_id)->count() + 1;
        $compra->codigo = 'CO-' . str_pad($siguiente, 5, '0', STR_PAD_LEFT);
    }

    public function updating(Compra $compra): void
    {
        if (! $compra->isDirty('estado_despacho')) {
            return;
        }

        $old = $compra->getOriginal('estado_despacho');
        $new = $compra->estado_despacho instanceof \BackedEnum
            ? $compra->estado_despacho->value
            : $compra->estado_despacho;

        // pendiente → recibido: aplicar stock (entrada)
        if ($old === EstadoDespacho::Pendiente->value && $new === EstadoDespacho::Recibido->value) {
            app(InventarioCoreService::class)->aplicarCompra($compra);
        }

        // recibido → pendiente: revertir stock
        if ($old === EstadoDespacho::Recibido->value && $new === EstadoDespacho::Pendiente->value) {
            app(InventarioCoreService::class)->revertirCompra($compra);
        }
    }

    public function deleting(Compra $compra): void
    {
        // Si fue recibida, revertir el stock antes de eliminar
        if ($compra->estaRecibida()) {
            app(InventarioCoreService::class)->revertirCompra($compra);
        }
    }
}
