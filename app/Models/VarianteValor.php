<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VarianteValor extends Model
{
    protected $table = 'variante_valores';

    protected $fillable = [
        'variante_id',
        'producto_atributo_valors_id',
    ];

    public function variante()
    {
        return $this->belongsTo(Variante::class);
    }

    public function valor()
    {
        return $this->belongsTo(ProductoAtributoValor::class, 'producto_atributo_valors_id');
    }
}