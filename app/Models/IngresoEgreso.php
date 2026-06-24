<?php

namespace App\Models;

use App\Enums\CategoriaEgreso;
use App\Enums\EstadoMovimiento;
use App\Enums\TipoMovimiento;
use App\Observers\IngresoEgresoObserver;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[ObservedBy(IngresoEgresoObserver::class)]
class IngresoEgreso extends Model
{
    use BelongsToEmpresa, BelongsToUser;

    protected $table = 'ingresos_egresos';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'sesion_caja_id',
        'fecha_hora',
        'tipo',
        'categoria',
        'entregado_a',
        'user_receptor_id',
        'monto',
        'motivo',
        'estado',
    ];

    protected $casts = [
        'tipo'       => TipoMovimiento::class,
        'categoria'  => CategoriaEgreso::class,
        'estado'     => EstadoMovimiento::class,
        'fecha_hora' => 'datetime',
        'monto'      => 'decimal:2',
    ];

    public function transaccion(): MorphOne
    {
        return $this->morphOne(Transaccion::class, 'transaccionable');
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_receptor_id');
    }

    public function esIngreso(): bool
    {
        return $this->tipo === TipoMovimiento::Ingreso;
    }

    public function esEgreso(): bool
    {
        return $this->tipo === TipoMovimiento::Egreso;
    }

    public function esRemuneracion(): bool
    {
        return $this->categoria === CategoriaEgreso::Remuneracion;
    }
}
