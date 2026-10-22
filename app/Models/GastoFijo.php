<?php

namespace App\Models;

use App\Enums\FrecuenciaGasto;
use App\Traits\BelongsToEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'proxima_fecha_pago',
        'dias_aviso',
    ];

    protected $casts = [
        'frecuencia'         => FrecuenciaGasto::class,
        'monto'              => 'decimal:2',
        'proxima_fecha_pago' => 'date',
        'dias_aviso'         => 'integer',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'gasto_fijo_id');
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /** Monto de este gasto normalizado al período de vista dado. */
    public function montoEnPeriodo(FrecuenciaGasto $periodoVista): float
    {
        return $this->frecuencia->montoEnPeriodo((float) $this->monto, $periodoVista);
    }

    /** Avanza proxima_fecha_pago según la frecuencia (calendario fijo). */
    public function avanzarProximaFecha(): void
    {
        if (! $this->proxima_fecha_pago) return;

        $nueva = match ($this->frecuencia) {
            FrecuenciaGasto::Diario    => $this->proxima_fecha_pago->copy()->addDay(),
            FrecuenciaGasto::Semanal   => $this->proxima_fecha_pago->copy()->addWeek(),
            FrecuenciaGasto::Quincenal => $this->proxima_fecha_pago->copy()->addDays(15),
            FrecuenciaGasto::Mensual   => $this->proxima_fecha_pago->copy()->addMonthNoOverflow(),
        };

        $this->update(['proxima_fecha_pago' => $nueva]);
    }

    /** Verdadero si el gasto vence dentro de los próximos $dias_aviso días. */
    public function venceProximo(): bool
    {
        if (! $this->proxima_fecha_pago || ! $this->estaActivo()) return false;

        return $this->proxima_fecha_pago->lte(now()->addDays($this->dias_aviso ?? 3));
    }
}
