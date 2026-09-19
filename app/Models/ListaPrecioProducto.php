<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaPrecioProducto extends Model
{
    protected $table = 'lista_precio_producto';

    protected $fillable = ['lista_precio_id', 'producto_id', 'precio'];

    protected $casts = ['precio' => 'float'];

    public function lista(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
