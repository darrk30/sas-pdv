<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'user_id',
        'compra_id',
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

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
}
