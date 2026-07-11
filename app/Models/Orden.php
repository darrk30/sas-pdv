<?php

namespace App\Models;

use App\Enums\EstadoOrden;
use App\Observers\OrdenObserver;
use App\Services\EtiquetaStockService;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MetodoPago;
use App\Models\Promocion;
use Illuminate\Support\Facades\DB;

#[ObservedBy([OrdenObserver::class])]
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
        'cliente_telefono',
        'cliente_direccion',
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
        'metodo_pago_id',
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
                $orden->estado = EstadoOrden::PendientePago;
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

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
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

    public function estaPagoConfirmado(): bool
    {
        return $this->estado === EstadoOrden::PagoConfirmado;
    }

    public function estaCancelada(): bool
    {
        return $this->estado === EstadoOrden::Cancelada;
    }

    /**
     * Restaura stock_reserva de cada ítem al cancelar una orden pendiente.
     * Solo actúa sobre productos con control_de_stock habilitado.
     */
    public function restaurarStockReserva(): void
    {
        $this->loadMissing(['detalles.producto', 'detalles.variante.producto']);

        $productosAfectados = [];

        foreach ($this->detalles as $detalle) {
            $cantidad = (float) $detalle->cantidad;

            // ── Promoción ─────────────────────────────────────────────────
            if ($detalle->promocion_id) {
                $promo = Promocion::with([
                    'detalles.producto',
                    'detalles.variante.producto',
                ])->find($detalle->promocion_id);

                if (! $promo) continue;

                foreach ($promo->detalles as $pd) {
                    $cantDet = $cantidad * (float) $pd->cantidad;

                    if ($pd->variante_id && $pd->variante?->producto?->control_de_stock) {
                        DB::table('inventarios')
                            ->where('empresa_id', $this->empresa_id)
                            ->where('variante_id', $pd->variante_id)
                            ->update(['stock_reserva' => DB::raw("LEAST(stock_real, stock_reserva + {$cantDet})")]);
                        if ($pd->variante->producto_id) {
                            $productosAfectados[] = $pd->variante->producto_id;
                        }
                    } elseif ($pd->producto_id && $pd->producto?->control_de_stock) {
                        DB::table('inventarios')
                            ->where('empresa_id', $this->empresa_id)
                            ->where('producto_id', $pd->producto_id)
                            ->whereNull('variante_id')
                            ->update(['stock_reserva' => DB::raw("LEAST(stock_real, stock_reserva + {$cantDet})")]);
                        $productosAfectados[] = $pd->producto_id;
                    }
                }

            // ── Variante ──────────────────────────────────────────────────
            } elseif ($detalle->variante_id) {
                if (! $detalle->variante?->producto?->control_de_stock) continue;

                DB::table('inventarios')
                    ->where('empresa_id', $this->empresa_id)
                    ->where('variante_id', $detalle->variante_id)
                    ->update(['stock_reserva' => DB::raw("LEAST(stock_real, stock_reserva + {$cantidad})")]);
                $productosAfectados[] = $detalle->variante->producto_id;

            // ── Producto simple ───────────────────────────────────────────
            } elseif ($detalle->producto_id) {
                if (! $detalle->producto?->control_de_stock) continue;

                DB::table('inventarios')
                    ->where('empresa_id', $this->empresa_id)
                    ->where('producto_id', $detalle->producto_id)
                    ->whereNull('variante_id')
                    ->update(['stock_reserva' => DB::raw("LEAST(stock_real, stock_reserva + {$cantidad})")]);
                $productosAfectados[] = $detalle->producto_id;
            }
        }

        // Sincronizar etiqueta (AGOTADO ↔ null) para cada producto afectado
        if ($productosAfectados) {
            $service = app(EtiquetaStockService::class);
            foreach (array_unique($productosAfectados) as $productoId) {
                $service->sincronizar($productoId, $this->empresa_id);
            }
        }
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
