<?php

namespace App\Observers;

use App\Enums\TipoComprobante;
use App\Models\Compra;
use App\Services\InventarioCoreService;
use Illuminate\Support\Facades\DB;

class CompraObserver
{
    public function creating(Compra $compra): void
    {
        // Para sin_comprobante el sistema genera serie y correlativo internos
        if ($compra->tipo_comprobante === TipoComprobante::SinComprobante->value) {
            $siguiente = DB::table('compras')
                ->where('empresa_id', $compra->empresa_id)
                ->where('tipo_comprobante', TipoComprobante::SinComprobante->value)
                ->count() + 1;

            $compra->serie       = 'SC';
            $compra->correlativo = str_pad($siguiente, 8, '0', STR_PAD_LEFT);
        }

        // codigo siempre es serie-correlativo
        $compra->codigo = ($compra->serie ?? '') . '-' . ($compra->correlativo ?? '');
    }

    public function deleting(Compra $compra): void
    {
        if ($compra->estaRecibida()) {
            app(InventarioCoreService::class)->revertirCompra($compra);
        }
    }
}
