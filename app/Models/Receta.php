<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receta extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'nombre',
        'producto_id',
        'variante_id',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RecetaDetalle::class);
    }

    /** Nombre descriptivo del producto/variante al que pertenece. */
    public function getTargetNombreAttribute(): string
    {
        if ($this->variante_id && $this->variante) {
            $vals = $this->variante->valores
                ->map(fn ($pav) => $pav->valor?->nombre)
                ->filter()->implode(' / ');
            $prod = $this->variante->producto?->nombre ?? '—';
            return $vals ? "{$prod} ({$vals})" : $prod;
        }
        return $this->producto?->nombre ?? $this->nombre;
    }
}
