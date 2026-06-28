<?php

namespace App\Models;

use App\Enums\EstadoSesion;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesionCaja extends Model
{
    use BelongsToEmpresa, BelongsToUser;

    protected $table = 'sesion_cajas';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'caja_id',
        'fecha_apertura',
        'fecha_cierre',
        'estado',
        'notas_cierre',
        'monto_apertura',
        'total_sistema',
        'total_cajero',
        'diferencia_total',
        'total_creditos',
    ];

    protected $casts = [
        'estado'          => EstadoSesion::class,
        'fecha_apertura'  => 'datetime',
        'fecha_cierre'    => 'datetime',
        'monto_apertura'  => 'decimal:2',
        'total_sistema'    => 'decimal:2',
        'total_cajero'     => 'decimal:2',
        'diferencia_total' => 'decimal:2',
        'total_creditos'   => 'decimal:2',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(SesionCajaPago::class);
    }

    public function estaAbierta(): bool
    {
        return $this->estado === EstadoSesion::Abierta;
    }
}
