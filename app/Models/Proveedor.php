<?php

namespace App\Models;

use App\Enums\EstadoGeneral;
use App\Enums\TipoDocumento;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use BelongsToEmpresa;

    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'nombre',
        'tipo_documento',
        'numero_documento',
        'correo',
        'telefono',
        'direccion',
        'departamento',
        'estado',
    ];

    protected $casts = [
        'tipo_documento' => TipoDocumento::class,
        'estado'         => EstadoGeneral::class,
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
