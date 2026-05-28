<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $fillable = [
        'nombre',
        'ruc',
        'direccion',
        'telefono',
        'email',
        'departamento',
        'distrito',
        'provincia',
        'ubigeo',
        'estado',
        'logo',
        'slug',
        'carta_activa_cliente',
        'carta_activa_admin',
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
