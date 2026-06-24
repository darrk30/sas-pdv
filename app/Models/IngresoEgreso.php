<?php

namespace App\Models;

use App\Enums\CategoriaEgreso;
use App\Enums\TipoMovimiento;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoEgreso extends Model
{
    use BelongsToEmpresa, BelongsToUser;

    protected $table = 'ingresos_egresos';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'fecha_hora',
        'tipo',
        'categoria',
        'entregado_a',
        'user_receptor_id',
        'monto',
        'motivo',
    ];

    protected $casts = [
        'tipo'       => TipoMovimiento::class,
        'categoria'  => CategoriaEgreso::class,
        'fecha_hora' => 'datetime',
        'monto'      => 'decimal:2',
    ];

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
