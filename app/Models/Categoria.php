<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use BelongsToEmpresa;

    protected $fillable = ['nombre', 'imagen_url', 'estado', 'empresa_id', 'orden',];
}
