<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Atributo extends Model
{
    use BelongsToEmpresa; // Trait que hicimos antes

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function valores()
    {
        return $this->hasMany(Valor::class);
    }

    public function productos(): HasManyThrough
    {
        // Permite llegar a los productos pasando por 'producto_atributos'
        return $this->hasManyThrough(Producto::class, ProductoAtributo::class);
    }
}
