<?php

namespace App\Models;

use App\Enums\EstadoOrden;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orden extends Model
{
    use BelongsToEmpresa;

    protected $table = 'ordenes';

    protected $fillable = [
        'empresa_id',
        'vendedor_id',
        'cliente_id',
        'numero',
        'cliente_nombre',
        'cliente_tipo_doc',
        'cliente_num_doc',
        'fecha_orden',
        'tipo_entrega',
        'metodo_envio_id',
        'direccion_agencia',
        'costo_envio',
        'descuento_total',
        'igv',
        'subtotal',
        'total',
        'estado',
        'venta_id',
        'notas',
        'notas_internas',
    ];

    protected $casts = [
        'fecha_orden'     => 'datetime',
        'estado'          => EstadoOrden::class,
        'costo_envio'     => 'decimal:2',
        'descuento_total' => 'decimal:2',
        'igv'             => 'decimal:2',
        'subtotal'        => 'decimal:2',
        'total'           => 'decimal:2',
    ];

    // ── Hooks ────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Orden $orden): void {
            if (empty($orden->vendedor_id) && auth()->check()) {
                $orden->vendedor_id = auth()->id();
            }
            if (empty($orden->fecha_orden)) {
                $orden->fecha_orden = now();
            }
            if (empty($orden->numero)) {
                $orden->numero = static::where('empresa_id', $orden->empresa_id)->max('numero') + 1;
            }
            if (empty($orden->estado)) {
                $orden->estado = EstadoOrden::Borrador;
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function metodoEnvio(): BelongsTo
    {
        return $this->belongsTo(MetodoEnvio::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(OrdenDetalle::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function getCodigoAttribute(): string
    {
        return 'ORD-' . str_pad($this->numero, 8, '0', STR_PAD_LEFT);
    }

    public function esEnvio(): bool
    {
        return $this->tipo_entrega === 'envio';
    }

    public function puedeTransicionarA(EstadoOrden $nuevoEstado): bool
    {
        return in_array($nuevoEstado, $this->estado->transicionesPosibles());
    }

    /** Recalcula igv, subtotal y total a partir de los detalles + costo de envío. */
    public function recalcularTotales(): void
    {
        $this->igv      = $this->detalles()->sum('igv');
        $this->subtotal = $this->detalles()->sum('total');
        $this->total    = $this->subtotal + ($this->costo_envio ?? 0);
        $this->save();
    }
}
