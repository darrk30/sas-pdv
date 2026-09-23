<?php

namespace App\Models;

use App\Observers\GaleriaProductoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([GaleriaProductoObserver::class])]
class GaleriaProducto extends Model
{
    protected $table = 'galeria_productos';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'imagen_path',
        'orden',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
