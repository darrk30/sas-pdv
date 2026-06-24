<?php

namespace App\Models;

use App\Enums\EstadoMovimiento;
use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaccion extends Model
{
    protected $table = 'transacciones';

    protected $fillable = [
        'empresa_id',
        'sesion_caja_id',
        'transaccionable_type',
        'transaccionable_id',
        'tipo',
        'concepto',
        'monto',
        'metodo_pago_id',
        'estado',
        'fecha',
    ];

    protected $casts = [
        'tipo'   => TipoMovimiento::class,
        'estado' => EstadoMovimiento::class,
        'monto'  => 'decimal:2',
        'fecha'  => 'datetime',
    ];

    public function transaccionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
