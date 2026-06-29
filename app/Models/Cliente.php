<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUser;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model implements AuthenticatableContract
{
    use BelongsToEmpresa, BelongsToUser, Authenticatable;

    protected $fillable = [
        'empresa_id',
        'user_id',
        'tipo_documento',
        'numero_documento',
        'nombre',
        'apellidos',
        'email',
        'password',
        'correo',
        'telefono',
        'direccion',
        'departamento',
        'provincia',
        'distrito',
        'codigo_postal',
        'pais',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
        'password'       => 'hashed',
    ];

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellidos}");
    }
}
