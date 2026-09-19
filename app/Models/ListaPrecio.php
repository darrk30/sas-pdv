<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListaPrecio extends Model
{
    use BelongsToEmpresa;

    protected $table = 'listas_precios';

    protected $fillable = ['empresa_id', 'nombre', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    public function preciosProducto(): HasMany
    {
        return $this->hasMany(ListaPrecioProducto::class, 'lista_precio_id');
    }
}
