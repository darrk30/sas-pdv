<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Valor extends Model
{
    // use BelongsToEmpresa;

    protected $fillable = ['nombre', 'atributo_id', 'estado', 'valor'];

    public function atributo()
    {
        return $this->belongsTo(Atributo::class);
    }

    // Valores que este valor excluye (Reglas activas)
    public function exclusiones()
    {
        return $this->hasMany(Exclusion::class, 'valor_base_id');
    }

    // Valores que excluyen a este valor (Reglas pasivas)
    public function excluidoPor()
    {
        return $this->hasMany(Exclusion::class, 'valor_exluido_id');
    }
}
