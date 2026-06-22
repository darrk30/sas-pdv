<?php

namespace App\Models;

use App\Observers\AjusteDetalleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteDetalle extends Model
{
    protected $fillable = [
        'ajuste_id',
        'producto_id',
        'variante_id',
        'nombre_producto',
        'unidad_id',
        'cantidad',
        'costo_unitario',
        'costo_total',
    ];

    protected $casts = [
        'cantidad'       => 'float',
        'costo_unitario' => 'float',
        'costo_total'    => 'float',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function ajuste(): BelongsTo
    {
        return $this->belongsTo(Ajuste::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadesMedida::class, 'unidad_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * ¿Este detalle corresponde a un producto simple (sin variante)?
     */
    public function esProductoSimple(): bool
    {
        return !is_null($this->producto_id) && is_null($this->variante_id);
    }

    /**
     * ¿Este detalle corresponde a una variante específica?
     */
    public function esVariante(): bool
    {
        return is_null($this->producto_id) && !is_null($this->variante_id);
    }

    /**
     * Genera el nombre snapshot automáticamente.
     * Llama esto antes de guardar si no se provee nombre_producto.
     *
     * Resultado: "Polo" o "Polo (Rojo - S)"
     */
    public static function generarNombre(
        ?Producto $producto,
        ?Variante $variante
    ): string {
        if ($variante) {
            // Carga los valores de la variante con sus nombres
            $valores = $variante->valores()
                ->with('valor')
                ->get()
                ->map(fn($pav) => $pav->valor->nombre)
                ->implode(' - ');

            $nombreBase = $variante->producto?->nombre ?? 'Producto';

            return $valores
                ? "{$nombreBase} ({$valores})"
                : $nombreBase;
        }

        return $producto?->nombre ?? 'Producto';
    }
}