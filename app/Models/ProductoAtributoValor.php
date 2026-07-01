<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoAtributoValor extends Model
{
    protected $fillable = ['producto_atributo_id', 'valor_id', 'precio_adicional', 'imagen', 'estado'];

    public function valor()
    {
        return $this->belongsTo(Valor::class);
    }
    
    public function productoAtributo(): BelongsTo
    {
        return $this->belongsTo(ProductoAtributo::class, 'producto_atributo_id');
    }
}
