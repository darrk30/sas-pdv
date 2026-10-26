<?php

namespace App\Models;

use App\Enums\TipoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalle extends Model
{
    protected $table = 'venta_detalles';

    protected $fillable = [
        'venta_id',
        'tipo_item',
        'producto_id',
        'variante_id',
        'promocion_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'valor_unitario',
        'costo_unitario',
        'descuento',
        'subtotal',
        'valor_total',
        'igv',
        'tip_afe_igv',
        'unidad',
        'total',
        'costo_total',
    ];

    protected $casts = [
        'tipo_item'       => TipoItem::class,
        'cantidad'        => 'decimal:3',
        'precio_unitario' => 'decimal:4',
        'valor_unitario'  => 'decimal:4',
        'costo_unitario'  => 'decimal:4',
        'descuento'   => 'decimal:4',
        'subtotal'    => 'decimal:4',
        'valor_total' => 'decimal:4',
        'igv'         => 'decimal:4',
        'total'       => 'decimal:4',
        'costo_total' => 'decimal:4',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class);
    }

    public function promocion(): BelongsTo
    {
        return $this->belongsTo(Promocion::class);
    }

    // ── Helpers de cálculo ───────────────────────────────────────────────

    public static function calcular(
        float $cantidad,
        float $precioUnitario,
        float $costoUnitario = 0,
        float $descuento = 0,
        float $tasaIgv = 0.18,
        int   $tDec = 4,
    ): array {
        $total         = round($precioUnitario * $cantidad, $tDec);
        $valorUnitario = round($precioUnitario / (1 + $tasaIgv), 4);
        $subtotal      = round($cantidad * $valorUnitario, 4);
        $valorTotal    = round($subtotal - $descuento, 4);
        $igv           = round($total - $valorTotal, 4);
        $costoTotal    = round($cantidad * $costoUnitario, 4);

        return compact('valorUnitario', 'subtotal', 'valorTotal', 'igv', 'total', 'costoTotal');
    }
}
