<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dimension extends Model
{
    use BelongsToEmpresa;
    protected $fillable = [
        'empresa_id',
        'nombre',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Obtiene todas las unidades de medida que pertenecen a esta dimensión.
     */
    public function unidadesMedida(): HasMany
    {
        return $this->hasMany(UnidadesMedida::class);
    }

    /**
     * Una dimensión tiene una única unidad base que sirve como raíz/pivote.
     * Esto optimiza las búsquedas directas en el inventario.
     */
    public function unidadBase(): HasOne
    {
        return $this->hasOne(UnidadesMedida::class)->where('es_base', true);
    }
}