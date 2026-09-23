<?php

namespace App\Models;

use App\Observers\ProductoAtributoValorObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ProductoAtributoValorObserver::class])]
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
