<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoEnvio extends Model
{
    use BelongsToEmpresa;

    protected $table = 'metodos_envio';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'costo',
        'estado',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
    ];

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class);
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }
}
