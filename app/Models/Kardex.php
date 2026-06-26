<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Kardex extends Model
{
    protected $table = 'kardex';

    protected $fillable = [
        'empresa_id',
        'user_id',
        'movible_type',
        'movible_id',
        'producto_id',
        'variante_id',
        'producto_nombre',
        'variante_nombre',
        'tipo',
        'concepto',
        'notas',
        'cantidad',
        'unidad',
        'factor_conversion',
        'cantidad_base',
        'costo_unitario',
        'costo_total',
        'precio_unitario',
        'precio_total',
        'stock_antes',
        'stock_despues',
        'fecha',
    ];

    protected $casts = [
        'cantidad'          => 'decimal:4',
        'factor_conversion' => 'decimal:4',
        'cantidad_base'     => 'decimal:4',
        'costo_unitario'    => 'decimal:4',
        'costo_total'       => 'decimal:4',
        'precio_unitario'   => 'decimal:4',
        'precio_total'      => 'decimal:4',
        'stock_antes'       => 'decimal:4',
        'stock_despues'     => 'decimal:4',
        'fecha'             => 'datetime',
    ];

    // ── Relaciones ─────────────────────────────────────────────────────────────

    /** Origen del movimiento: Compra, Ajuste, Venta, etc. */
    public function movible(): MorphTo
    {
        return $this->morphTo();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Signo del movimiento: +1 para entrada, -1 para salida */
    public function signo(): int
    {
        return $this->tipo === 'entrada' ? 1 : -1;
    }

    /** Impacto real en el stock (cantidad_base con signo) */
    public function impacto(): float
    {
        return (float) $this->cantidad_base * $this->signo();
    }
}
