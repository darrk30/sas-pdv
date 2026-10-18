<?php

namespace App\Models;

use App\Observers\VarianteObserver;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([VarianteObserver::class])]
class Variante extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'codigo',
        'codigo_barras',
        'estado',
        'precio_final',
        'precio_costo',
        'factor_presentacion',
        'imagen',
    ];

    protected $casts = [
        'precio_final'        => 'decimal:2',
        'precio_costo'        => 'decimal:4',
        'factor_presentacion' => 'decimal:4',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function valores()
    {
        return $this->belongsToMany(ProductoAtributoValor::class, 'variante_valores', 'variante_id', 'producto_atributo_valors_id')
            ->where('producto_atributo_valors.estado', 'activo');
    }

    public function inventario()
    {
        return $this->hasOne(Inventario::class);
    }

    /** Factor numérico de esta presentación (mínimo 0.0001 para evitar división por 0). */
    public function factor(): float
    {
        return max(0.0001, (float) ($this->factor_presentacion ?? 1.0));
    }

    /**
     * Devuelve el registro Inventario correcto según si el producto comparte stock.
     * Con $lock=true aplica lockForUpdate (usar solo dentro de transacciones DB).
     */
    public function resolverInventario(int $empresaId, bool $lock = false): ?Inventario
    {
        $this->loadMissing('producto');
        $compartido = (bool) ($this->producto?->comparte_stock ?? false);

        $q = $compartido
            ? Inventario::where('empresa_id', $empresaId)
                ->where('producto_id', $this->producto_id)
                ->whereNull('variante_id')
            : Inventario::where('empresa_id', $empresaId)
                ->where('variante_id', $this->id);

        return $lock ? $q->lockForUpdate()->first() : $q->first();
    }

    /**
     * Stock disponible (stock_reserva) expresado en las unidades de ESTA presentación.
     * Para comparte_stock: floor(stock_producto / factor).
     */
    public function stockDisponibleReserva(int $empresaId): float
    {
        $this->loadMissing('producto');
        $inv = $this->resolverInventario($empresaId);
        $stock = (float) ($inv?->stock_reserva ?? 0);
        if ($this->producto?->comparte_stock) {
            return floor($stock / $this->factor());
        }
        return $stock;
    }

    /**
     * Intercepta cualquier llamada $variante->delete() y la redirige a
     * desactivar en lugar de borrar, preservando el stock_real del inventario.
     * Los DELETE a nivel DB (cascade de producto) siguen funcionando normalmente.
     */
    public function delete(): bool
    {
        $this->update(['estado' => 'inactivo']);

        Inventario::where('variante_id', $this->id)
            ->update(['estado_almacen' => 'inactivo']);

        return true;
    }
}
