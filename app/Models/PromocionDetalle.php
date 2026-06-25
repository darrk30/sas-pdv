<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromocionDetalle extends Model
{
    protected $table = 'promocion_detalles';

    protected $fillable = [
        'promocion_id',
        'producto_id',
        'variante_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
    ];

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }
}
