<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $fillable = [
        'name',
        'ruc',
        'logo',
        'slug',
        'direccion',
        'telefono',
        'email',
        'departamento',
        'distrito',
        'provincia',
        'ubigeo',
        'estado',
        'carta_activa_cliente',
        'carta_activa_admin',
        'cod_local',
        'country_code',
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function getFilamentName(): string
    {
        return (string) ($this->nombre ?? 'Unnamed Tenant');
    }

    public function usuarios()
    {
        // Asegúrate de definir la tabla pivote y las claves foráneas
        return $this->belongsToMany(User::class, 'empresa_user', 'empresa_id', 'user_id')
            ->withPivot('id'); // Importante si el pivote tiene ID propio
    }
}
