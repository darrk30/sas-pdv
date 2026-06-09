<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exclusion extends Model
{
    protected $fillable = [
        'valor_base_id', 
        'valor_exluido_id',
        'producto_atributo_id'
    ];

    // El valor que actúa como disparador de la exclusión
    public function valorBase()
    {
        return $this->belongsTo(Valor::class, 'valor_base_id');
    }

    // El valor que será bloqueado o excluido
    public function valorExcluido()
    {
        return $this->belongsTo(Valor::class, 'valor_exluido_id');
    }

    // El atributo del producto al que pertenece esta exclusión
    public function productoAtributo()
    {
        return $this->belongsTo(ProductoAtributo::class, 'producto_atributo_id');
    }
}
