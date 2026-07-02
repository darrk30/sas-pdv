<?php

namespace App\Models;

use App\Observers\EmpresaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([EmpresaObserver::class])]
class Empresa extends Model
{
    protected $fillable = [
        'name',
        'ruc',
        'logo',
        'icono',
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
        return $this->belongsToMany(User::class, 'empresa_user', 'empresa_id', 'user_id')
                    ->withPivot('id', 'estado'); // 👈 Solo agregamos 'estado' aquí, separado por coma
    }

    public function pagos()
    {
        return $this->hasManyThrough(
            PagosCliente::class, // Modelo destino
            Suscripcion::class,  // Modelo intermedio
            'empresa_id', // Foreign key en tabla Suscripciones
            'suscripcion_id', // Foreign key en tabla PagosCliente
            'id', // Local key en Empresa
            'id' // Local key en Suscripciones
        );
    }

    public function suscripcion()
    {
        return $this->hasOne(Suscripcion::class);
    }
}
