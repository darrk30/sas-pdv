<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoAtributo extends Model
{
    protected $fillable = ['producto_id', 'atributo_id', 'estado'];

    public function atributo()
    {
        return $this->belongsTo(Atributo::class);
    }

    // 1. Relación para el Select Múltiple (Guarda los IDs) — solo valores activos
    public function valores()
    {
        return $this->belongsToMany(Valor::class, 'producto_atributo_valors', 'producto_atributo_id', 'valor_id')
            ->withPivot('precio_adicional', 'imagen')
            ->withTimestamps()
            ->wherePivot('estado', 'activo');
    }

    // 2. Relación para el Modal de Precios Extra (Edita la tabla pivote)
    public function detallesPrecios()
    {
        return $this->hasMany(ProductoAtributoValor::class, 'producto_atributo_id');
    }


    public function detallesExclusiones()
    {
        return $this->hasMany(Exclusion::class, 'producto_atributo_id');
    }
}
