<?php

namespace App\Models;

use App\Enums\FrecuenciaGasto;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class GastoFijo extends Model
{
    use BelongsToEmpresa;

    protected $table = 'gastos_fijos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'monto',
        'frecuencia',
        'notas',
        'estado',
    ];

    protected $casts = [
        'frecuencia' => FrecuenciaGasto::class,
        'monto'      => 'decimal:2',
    ];

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /** Monto de este gasto normalizado al período de vista dado. */
    public function montoEnPeriodo(FrecuenciaGasto $periodoVista): float
    {
        return $this->frecuencia->montoEnPeriodo((float) $this->monto, $periodoVista);
    }
}
