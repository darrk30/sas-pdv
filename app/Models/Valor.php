<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Valor extends Model
{
    use BelongsToEmpresa;

    protected $fillable = ['nombre', 'atributo_id', 'estado'];

    public function atributo()
    {
        return $this->belongsTo(Atributo::class);
    }
}
