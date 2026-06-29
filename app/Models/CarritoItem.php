<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarritoItem extends Model
{
    protected $fillable = ['carrito_id', 'promocion_id', 'producto_id', 'variante_id', 'cantidad', 'precio_unitario'];

    protected $casts = [
        'precio_unitario' => 'float',
        'cantidad'        => 'integer',
    ];

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Carrito::class);
    }

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
