<?php

namespace App\View\Components\Tienda;

use App\Models\OrdenDetalle;
use App\Models\Producto;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class CarruselProductos extends Component
{
    public $productos;
    public string $titulo;

    public function __construct(int $empresaId, string $titulo = 'Más pedidos', int $limite = 16, int $excluirId = 0)
    {
        $this->titulo = $titulo;

        $this->productos = Producto::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where('visible_en_carta', true)
            ->when($excluirId, fn($q) => $q->where('id', '!=', $excluirId))
            ->where(function ($q) use ($empresaId) {
                $q->where('control_de_stock', false)
                  ->orWhere('venta_sin_stock', true)
                  ->orWhereExists(fn($sub) => $sub
                      ->from('inventarios as i')
                      ->whereColumn('i.producto_id', 'productos.id')
                      ->where('i.empresa_id', $empresaId)
                      ->where('i.stock_real', '>', 0)
                      ->where('i.estado_almacen', '!=', 'inactivo')
                  );
            })
            ->addSelect([
                'total_vendido' => OrdenDetalle::selectRaw('COALESCE(SUM(od.cantidad), 0)')
                    ->from('orden_detalles as od')
                    ->join('ordenes as o', 'o.id', '=', 'od.orden_id')
                    ->whereColumn('od.producto_id', 'productos.id')
                    ->where('o.empresa_id', $empresaId),
            ])
            ->having('total_vendido', '>', 0)
            ->orderByDesc('total_vendido')
            ->limit($limite)
            ->with([
                'atributos.atributo',
                'atributos.valores',
                'galeriaProductos',
                'inventario',
                'variantes' => fn($q) => $q->where('estado', 'activo')->with(['valores', 'inventario']),
            ])
            ->get();
    }

    public function render(): View|Closure|string
    {
        return view('components.tienda.carrusel-productos');
    }
}
