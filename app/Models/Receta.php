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

    /**
     * Calcula el costo del producto terminado sumando el costo de cada insumo
     * multiplicado por la cantidad requerida, aplicando conversión de unidades
     * con la misma lógica que consumirReceta().
     */
    public function costoCalculado(): float
    {
        $this->loadMissing([
            'detalles.unidadMedida',
            'detalles.insumoProducto.unidadMedida',
            'detalles.insumoVariante.producto.unidadMedida',
        ]);

        $total = 0.0;

        foreach ($this->detalles as $det) {
            $cantBase = (float) $det->cantidad;

            if ($det->insumo_variante_id) {
                $costo = (float) ($det->insumoVariante?->precio_costo ?? 0);
                $total += $costo * $cantBase;
                continue;
            }

            if (! $det->insumo_producto_id) continue;

            // Conversión de unidades (igual que en VentaService::consumirReceta)
            if ($det->unidadMedida) {
                $productoUnidad = $det->insumoProducto?->unidadMedida;
                if ($productoUnidad && $productoUnidad->id !== $det->unidad_medida_id) {
                    $factorReceta   = (float) $det->unidadMedida->factor_base;
                    $factorProducto = (float) $productoUnidad->factor_base;
                    if ($factorProducto > 0) {
                        $cantBase = round($cantBase * $factorReceta / $factorProducto, 4);
                    }
                }
            }

            $costo = (float) ($det->insumoProducto?->precio_costo ?? 0);
            $total += $costo * $cantBase;
        }

        return round($total, 4);
    }

    /** Actualiza precio_costo del producto/variante con el costo calculado de esta receta. */
    public function sincronizarCosto(): void
    {
        $costo = $this->costoCalculado();

        if ($this->variante_id) {
            \App\Models\Variante::where('id', $this->variante_id)
                ->update(['precio_costo' => $costo]);
        } elseif ($this->producto_id) {
            \App\Models\Producto::where('id', $this->producto_id)
                ->update(['precio_costo' => $costo]);
        }
    }
}
