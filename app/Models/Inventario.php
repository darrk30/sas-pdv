<?php

namespace App\Models;

use App\Enums\EstadoGeneral;
use App\Enums\EstadoStock;
use App\Services\EtiquetaStockService;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventario extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'variante_id',
        'stock_real',
        'stock_reserva',
        'stock_minimo',
        'estado_almacen',
        'estado_inventario',
    ];

    // DEFINICIÓN DE CASTS
    protected $casts = [
        'stock_real' => 'decimal:2',
        'stock_reserva' => 'decimal:2',
        'estado_almacen' => EstadoGeneral::class, 
        'estado_inventario' => EstadoStock::class,
    ];

    // Relación obligatoria con el producto
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    // Relación opcional con la variante
    public function variante(): BelongsTo
    {
        return $this->belongsTo(Variante::class, 'variante_id');
    }

    protected static function booted(): void
    {
        static::updated(function (Inventario $inventario): void {
            if ($inventario->wasChanged(['stock_real', 'stock_reserva']) && $inventario->producto_id) {
                app(EtiquetaStockService::class)
                    ->sincronizar($inventario->producto_id, $inventario->empresa_id);
            }
        });
    }

    // Accesor para el estado dinámico (Laravel lo reconoce como $inventario->estado_stock)
    public function getEstadoStockAttribute(): string
    {
        if ($this->stock_real <= 0) {
            return 'agotado';
        }

        if ($this->stock_real <= ($this->stock_minimo ?? 5)) {
            return 'por_agotarse';
        }

        return 'disponible';
    }
}