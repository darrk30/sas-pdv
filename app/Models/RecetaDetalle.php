<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetaDetalle extends Model
{
    protected $fillable = [
        'receta_id',
        'insumo_producto_id',
        'insumo_variante_id',
        'cantidad',
        'unidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class);
    }

    public function insumoProducto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'insumo_producto_id');
    }

    public function insumoVariante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'insumo_variante_id');
    }

    /** Nombre legible del insumo. */
    public function getNombreInsumoAttribute(): string
    {
        if ($this->insumo_variante_id && $this->insumoVariante) {
            $vals = $this->insumoVariante->valores
                ->map(fn ($pav) => $pav->valor?->nombre)
                ->filter()->implode(' / ');
            $prod = $this->insumoVariante->producto?->nombre ?? '—';
            return $vals ? "{$prod} ({$vals})" : $prod;
        }
        return $this->insumoProducto?->nombre ?? '—';
    }
}
