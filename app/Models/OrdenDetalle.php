<?php

namespace App\Models;

use App\Enums\TipoItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenDetalle extends Model
{
    protected $table = 'orden_detalles';

    protected $fillable = [
        'orden_id',
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
        'igv',
        'total',
        'costo_total',
    ];

    protected $casts = [
        'tipo_item'       => TipoItem::class,
        'cantidad'        => 'decimal:3',
        'precio_unitario' => 'decimal:4',
        'valor_unitario'  => 'decimal:4',
        'costo_unitario'  => 'decimal:4',
        'descuento'       => 'decimal:2',
        'subtotal'        => 'decimal:2',
        'igv'             => 'decimal:2',
        'total'           => 'decimal:2',
        'costo_total'     => 'decimal:2',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
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

    // ── Helper de cálculo (igual que VentaDetalle) ───────────────────────

    public static function calcular(
        float $cantidad,
        float $precioUnitario,
        float $costoUnitario = 0,
        float $descuento = 0,
        float $tasaIgv = 0.18,
    ): array {
        $total         = round($precioUnitario * $cantidad - $descuento, 2);
        $valorUnitario = round($precioUnitario / (1 + $tasaIgv), 4);
        $subtotal      = round($cantidad * $valorUnitario, 2);
        $igv           = round($total - $subtotal + $descuento / (1 + $tasaIgv), 2);
        $costoTotal    = round($cantidad * $costoUnitario, 2);

        return compact('valorUnitario', 'subtotal', 'igv', 'total', 'costoTotal');
    }
}
