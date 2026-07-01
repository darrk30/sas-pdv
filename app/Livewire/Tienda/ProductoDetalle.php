<?php

namespace App\Livewire\Tienda;

use App\Enums\EstadoGeneral;
use App\Models\Producto;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::tienda')]
class ProductoDetalle extends Component
{
    public int $empresaId = 0;
    public Producto $producto;

    public function mount(int $id): void
    {
        $this->empresaId = app('tienda.empresa')->id;

        $this->producto = Producto::where('empresa_id', $this->empresaId)
            ->where('id', $id)
            ->where('estado', EstadoGeneral::Activo)
            ->with([
                'atributos.atributo',
                'atributos.valores',
                'variantes' => fn($q) => $q->where('estado', 'activo')->with(['valores', 'inventario']),
                'galeriaProductos',
                'inventario',
                'categoria',
            ])
            ->firstOrFail();
    }

    public function render()
    {
        $producto = $this->producto;

        // ── Imágenes ──────────────────────────────────────────────
        $imagenes = collect();
        if ($producto->logo) {
            $imagenes->push(Storage::url($producto->logo));
        }
        foreach ($producto->galeriaProductos ?? [] as $g) {
            if ($g->imagen_path) {
                $imagenes->push(Storage::url($g->imagen_path));
            }
        }

        // ── Mapa valor_id → imagen (desde ProductoAtributoValor.imagen) ──
        $colorImagenMap = [];
        foreach ($producto->atributos as $pa) {
            foreach ($pa->valores as $valor) {
                $img = $valor->pivot->imagen ?? null;
                if ($img && ! isset($colorImagenMap[$valor->id])) {
                    $colorImagenMap[$valor->id] = Storage::url($img);
                }
            }
        }

        // ── Precios / descuento ───────────────────────────────────
        $tieneDescuento = ($producto->porcentaje_descuento ?? 0) > 0 && $producto->precio_con_descuento;
        $precioFinal    = $tieneDescuento ? (float) $producto->precio_con_descuento : (float) $producto->precio_venta;
        $pct            = (float) ($producto->porcentaje_descuento ?? 0);
        $pctFormateado  = rtrim(rtrim(number_format($pct, 2), '0'), '.');

        $tieneExtra = $producto->atributos
            ->flatMap(fn($pa) => $pa->valores)
            ->some(fn($v) => (float) ($v->pivot->precio_adicional ?? 0) > 0);

        // ── Variantes ─────────────────────────────────────────────
        $variantesActivas = $producto->variantes ?? collect();
        $tieneVariantes   = $variantesActivas->isNotEmpty();

        $productoAgotado = false;
        if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
            if ($tieneVariantes) {
                $productoAgotado = $variantesActivas->every(
                    fn($v) => (float) ($v->inventario?->stock_reserva ?? 0) <= 0
                );
            } else {
                $productoAgotado = (float) ($producto->inventario?->stock_reserva ?? 0) <= 0;
            }
        }

        // ── Datos para Alpine ─────────────────────────────────────
        $atributosData = $producto->atributos->map(fn($pa) => [
            'id'     => $pa->atributo_id,
            'nombre' => $pa->atributo?->nombre ?? '',
            'tipo'   => strtolower($pa->atributo?->tipo ?? ''),
            'valores' => $pa->valores->map(fn($v) => [
                'id'               => $v->id,
                'label'            => $v->nombre ?? $v->valor ?? '',
                'valor'            => $v->valor ?? '',
                'precio_adicional' => (float) ($v->pivot->precio_adicional ?? 0),
                'imagen'           => ($v->pivot->imagen ?? null) ? Storage::url($v->pivot->imagen) : null,
            ])->values()->all(),
        ])->filter(fn($a) => $a['id'] && ! empty($a['valores']))->values()->all();

        $variantesData = $variantesActivas->map(fn($var) => [
            'id'          => $var->id,
            'imagen'      => $var->imagen ? Storage::url($var->imagen) : null,
            'valores_ids' => $var->valores->pluck('valor_id')->sort()->values()->all(),
            'sin_stock'   => $producto->control_de_stock && ! $producto->venta_sin_stock
                             && (float) ($var->inventario?->stock_reserva ?? 0) <= 0,
        ])->values()->all();

        $productoData = [
            'id'           => $producto->id,
            'nombre'       => $producto->nombre,
            'imagen'       => $imagenes->first(),
            'precioBase'   => $precioFinal,
            'atributos'    => $atributosData,
            'variantes'    => $variantesData,
            'agotado'      => $productoAgotado,
            'promocion_id' => null,
        ];

        return view('livewire.tienda.producto-detalle', [
            'imagenes'       => $imagenes,
            'colorImagenMap' => $colorImagenMap,
            'tieneDescuento' => $tieneDescuento,
            'precioFinal'    => $precioFinal,
            'pct'            => $pct,
            'pctFormateado'  => $pctFormateado,
            'tieneExtra'     => $tieneExtra,
            'tieneVariantes' => $tieneVariantes,
            'productoAgotado' => $productoAgotado,
            'productoData'   => $productoData,
        ])->title($producto->nombre);
    }
}
