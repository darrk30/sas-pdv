<?php

namespace App\Observers;

use App\Models\Compra;

class CompraObserver
{
    public function creating(Compra $compra): void
    {
        $siguiente = Compra::where('empresa_id', $compra->empresa_id)->count() + 1;
        $compra->codigo = 'CO-' . str_pad($siguiente, 5, '0', STR_PAD_LEFT);
    }
}
