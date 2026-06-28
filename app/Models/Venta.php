<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use App\Enums\TipoPago;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Venta extends Model
{
    use BelongsToEmpresa;

    protected $table = 'ventas';

    protected $fillable = [
        'empresa_id',
        'vendedor_id',
        'sesion_caja_id',
        'cliente_id',
        'cliente_nombre',
        'cliente_tipo_doc',
        'cliente_num_doc',
        'serie_id',
        'correlativo',
        'fecha_emision',
        'tipo_pago',
        'fecha_vencimiento',
        'op_gravadas',
        'op_exoneradas',
        'op_inafectas',
        'descuento_total',
        'igv',
        'total',
        'costo_total',
        'monto_pagado',
        'saldo_pendiente',
        'estado_pago',
        'total_letras',
        'qr_data',
        'hash',
        'path_xml',
        'path_pdf',
        'path_cdr_zip',
        'sunat_success',
        'sunat_codigo',
        'sunat_descripcion',
        'sunat_mensaje',
        'estado',
        'estado_despacho',
        'notas',
    ];

    protected $casts = [
        'fecha_emision'    => 'datetime',
        'fecha_vencimiento'=> 'date',
        'tipo_pago'        => TipoPago::class,
        'estado'           => EstadoVenta::class,
        'estado_despacho'  => 'string',
        'op_gravadas'      => 'decimal:2',
        'op_exoneradas'    => 'decimal:2',
        'op_inafectas'     => 'decimal:2',
        'descuento_total'  => 'decimal:2',
        'igv'              => 'decimal:2',
        'total'            => 'decimal:2',
        'costo_total'      => 'decimal:2',
        'monto_pagado'     => 'decimal:2',
        'saldo_pendiente'  => 'decimal:2',
        'estado_pago'      => 'string',
        'sunat_success'    => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Venta $venta): void {
            if (empty($venta->vendedor_id) && auth()->check()) {
                $venta->vendedor_id = auth()->id();
            }
            if (empty($venta->fecha_emision)) {
                $venta->fecha_emision = now();
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function sesionCaja(): BelongsTo
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class);
    }

    public function transacciones(): MorphMany
    {
        return $this->morphMany(Transaccion::class, 'transaccionable');
    }

    public function kardex(): MorphMany
    {
        return $this->morphMany(Kardex::class, 'movible');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function estaCompletada(): bool
    {
        return $this->estado === EstadoVenta::Completada;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === EstadoVenta::Anulada;
    }

    public function tieneSaldoPendiente(): bool
    {
        return (float) $this->saldo_pendiente > 0;
    }

    public function getCodigoComprobanteAttribute(): string
    {
        return "{$this->serie->serie}-{$this->correlativo}";
    }
}
