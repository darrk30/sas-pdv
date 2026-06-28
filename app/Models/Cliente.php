<?php

namespace App\Models;

use App\Enums\TipoDocumento;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use BelongsToEmpresa, BelongsToUser;

    protected $fillable = [
        'empresa_id',
        'user_id',
        'tipo_documento',
        'numero_documento',
        'nombre',
        'apellidos',
        'direccion',
        'correo',
        'telefono',
        'departamento',
        'provincia',
        'distrito',
        'codigo_postal',
        'pais',
    ];

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
    ];

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellidos}");
    }
}
