<?php

namespace App\Models;

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
        'afecto_igv',
        'codigo_promo',
        'limite_usos',
        'usos_actuales',
        'dias_semana',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'precio'        => 'decimal:2',
        'afecto_igv'    => 'boolean',
        'estado'        => 'boolean',
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
        if (! $this->estado) {
            return false;
        }

        $hoy = Carbon::today();

        if ($this->fecha_inicio && $hoy->lt($this->fecha_inicio)) {
            return false;
        }

        if ($this->fecha_fin && $hoy->gt($this->fecha_fin)) {
            return false;
        }

        if ($this->dias_semana !== null) {
            $diaSemana = Carbon::now()->dayOfWeekIso; // 1=lunes … 7=domingo
            if (! in_array($diaSemana, $this->dias_semana)) {
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
}
