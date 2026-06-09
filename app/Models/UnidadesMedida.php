<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadesMedida extends Model
{

    protected $fillable = [
        'empresa_id',
        'dimension_id',
        'unidad_base_id',
        'nombre',
        'simbolo',
        'es_base',
        'factor_conversion',
        'estado',
    ];

    protected $casts = [
        'es_base' => 'boolean',
        'estado' => 'boolean',
        'factor_conversion' => 'float', 
    ];

    /**
     * Relación con la Dimensión/Magnitud a la que pertenece.
     */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class, 'dimension_id');
    }

    /**
     * Relación auto-referencial hacia ARRIBA:
     * Obtiene la unidad de la cual depende directamente (su padre inmediato).
     */
    public function unidadPadre(): BelongsTo
    {
        return $this->belongsTo(UnidadesMedida::class, 'unidad_base_id');
    }

    /**
     * Relación auto-referencial hacia ABAJO:
     * Obtiene las sub-unidades que dependen directamente de esta unidad.
     */
    public function subUnidades(): HasMany
    {
        return $this->hasMany(UnidadesMedida::class, 'unidad_base_id');
    }

    /**
     * Método helper: Calcula recursivamente el factor de conversión total
     * hasta llegar a la unidad base absoluta de la cadena.
     */
    public function getFactorBaseAttribute(): float
    {
        if ($this->es_base || is_null($this->unidad_base_id)) {
            return 1.0;
        }
        return $this->factor_conversion * $this->unidadPadre->factor_base;
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}