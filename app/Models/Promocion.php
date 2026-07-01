<?php

namespace App\Models;

use App\Enums\EstadoPromocion;
use App\Traits\BelongsToEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promocion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'promociones';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'descripcion',
        'imagen',
        'precio',
        'codigo_promo',
        'limite_usos',
        'usos_actuales',
        'dias_semana',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'precio'  => 'decimal:2',
        'estado'  => EstadoPromocion::class,
        'dias_semana'   => 'array',
        'fecha_inicio'  => 'date',
        'fecha_fin'     => 'date',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(PromocionDetalle::class);
    }

    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    // ── Validación de reglas ─────────────────────────────────────────────

    public function estaVigente(): bool
    {
        if ($this->estado !== EstadoPromocion::Activo) {
            return false;
        }

        $hoy = Carbon::today();

        if ($this->fecha_inicio && $hoy->lt($this->fecha_inicio)) {
            return false;
        }

        if ($this->fecha_fin && $hoy->gt($this->fecha_fin)) {
            return false;
        }

        // Array vacío = sin restricción de días; null también
        if (! empty($this->dias_semana)) {
            $diaSemana = (string) Carbon::now()->dayOfWeekIso; // 1=lunes … 7=domingo
            if (! in_array($diaSemana, array_map('strval', $this->dias_semana))) {
                return false;
            }
        }

        if ($this->limite_usos !== null && $this->usos_actuales >= $this->limite_usos) {
            return false;
        }

        return true;
    }

    public function validarCodigo(?string $codigo): bool
    {
        if ($this->codigo_promo === null) {
            return true;
        }

        return $this->codigo_promo === $codigo;
    }

    public function incrementarUso(): void
    {
        static::where('id', $this->id)->increment('usos_actuales');
    }

    /**
     * Stock predictivo: cuántas unidades de esta promo se pueden vender.
     * Devuelve null si es ilimitado, 0 si no hay disponibilidad, N si hay N unidades.
     */
    public function stockPredictivo(): ?int
    {
        if (! $this->estaVigente()) {
            return 0;
        }

        $max = PHP_INT_MAX;

        // Limitar por usos restantes
        if ($this->limite_usos !== null) {
            $max = min($max, max(0, $this->limite_usos - (int) $this->usos_actuales));
        }

        // Limitar por inventario de cada ítem del combo
        foreach ($this->detalles as $detalle) {
            if ($detalle->variante_id) {
                $variante = $detalle->variante;
                $producto = $variante?->producto;
                $inventario = $variante?->inventario;
            } else {
                $variante = null;
                $producto = $detalle->producto;
                $inventario = $producto?->inventario;
            }

            if (! $producto || ! $producto->control_de_stock || $producto->venta_sin_stock) {
                continue; // este ítem no restringe el stock
            }

            $stockDisp = max(0.0, (float) ($inventario?->stock_reserva ?? 0));
            $necesario = max(0.001, (float) $detalle->cantidad);
            $max       = min($max, (int) floor($stockDisp / $necesario));
        }

        return $max === PHP_INT_MAX ? null : $max;
    }
}
