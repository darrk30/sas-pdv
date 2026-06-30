<?php

namespace App\Livewire\Tienda;

use App\Enums\EstadoGeneral;
use App\Models\Carrito;
use App\Models\ListaDeseo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::tienda')]
#[Title('Lista de deseos')]
class ListaDeseos extends Component
{
    public int  $empresaId = 0;
    public bool $esGuest   = false;

    public function mount(): void
    {
        $this->empresaId = app('tienda.empresa')->id;
        $this->esGuest   = ! Auth::guard('cliente')->check();
    }

    #[On('lista-deseos-actualizada')]
    public function refrescar(): void {}

    public function incrementar(int $id): void
    {
        $this->itemDelUsuario($id)?->increment('cantidad');
    }

    public function decrementar(int $id): void
    {
        $item = $this->itemDelUsuario($id);
        if (! $item) return;

        if ($item->cantidad <= 1) {
            $this->eliminarItem($id);
        } else {
            $item->decrement('cantidad');
        }
    }

    public function eliminarItem(int $id): void
    {
        $item = $this->itemDelUsuario($id);
        if (! $item) return;

        $userId     = Auth::guard('cliente')->id();
        $productoId = $item->producto_id;
        $nombre     = $item->producto?->nombre ?? 'Producto';
        $item->delete();

        $quedan = ListaDeseo::where('empresa_id', $this->empresaId)
            ->where('user_id', $userId)
            ->where('producto_id', $productoId)
            ->exists();

        $count = $this->deseoCountActual($userId);

        $this->dispatch('lista-deseos-actualizada', productoId: $productoId, enDeseos: $quedan);
        $this->dispatch('deseo-count-actualizado', count: $count);
        $this->dispatch('toast', mensaje: "\"$nombre\" eliminado de tu lista de deseos", tipo: 'info');
    }

    public function moverSeleccionadosAlCarrito(array $ids): void
    {
        if (empty($ids)) return;

        $userId  = Auth::guard('cliente')->id();
        $carrito = Carrito::firstOrCreate([
            'empresa_id' => $this->empresaId,
            'user_id'    => $userId,
        ]);

        $movidos              = 0;
        $productoIdsAfectados = [];

        foreach ($ids as $rawId) {
            $item = $this->itemDelUsuario((int) $rawId);
            if (! $item) continue;

            $precio = $item->variante
                ? (float) $item->variante->precio_final
                : $this->calcularPrecioProducto($item->producto);

            try {
                $carritoItem = $carrito->items()
                    ->whereNull('promocion_id')
                    ->where('producto_id', $item->producto_id)
                    ->where(fn($q) => is_null($item->variante_id)
                        ? $q->whereNull('variante_id')
                        : $q->where('variante_id', $item->variante_id))
                    ->first();

                if ($carritoItem) {
                    $carritoItem->increment('cantidad', $item->cantidad);
                } else {
                    $carrito->items()->create([
                        'promocion_id'    => null,
                        'producto_id'     => $item->producto_id,
                        'variante_id'     => $item->variante_id,
                        'cantidad'        => $item->cantidad,
                        'precio_unitario' => $precio,
                    ]);
                }

                $productoIdsAfectados[$item->producto_id] = true;
                $item->delete();
                $movidos++;
            } catch (\Exception) {}
        }

        $carritoCount = $carrito->items()->sum('cantidad');
        $this->dispatch('carrito-count-actualizado', count: (int) $carritoCount);

        foreach (array_keys($productoIdsAfectados) as $productoId) {
            $quedan = ListaDeseo::where('empresa_id', $this->empresaId)
                ->where('user_id', $userId)
                ->where('producto_id', $productoId)
                ->exists();
            $this->dispatch('lista-deseos-actualizada', productoId: $productoId, enDeseos: $quedan);
        }

        $deseoCount = $this->deseoCountActual($userId);
        $this->dispatch('deseo-count-actualizado', count: $deseoCount);
        $this->dispatch('lista-deseos-reset-seleccion');

        if ($movidos > 0) {
            $msg = $movidos === 1 ? '1 producto movido al carrito' : "$movidos productos movidos al carrito";
            $this->dispatch('toast', mensaje: $msg, tipo: 'success');
        }
    }

    public function render()
    {
        if ($this->esGuest) {
            return view('livewire.tienda.lista-deseos', [
                'items'           => collect(),
                'datosParaAlpine' => [],
                'disponibilidad'  => [],
            ]);
        }

        $userId = Auth::guard('cliente')->id();

        $todos = ListaDeseo::where('empresa_id', $this->empresaId)
            ->where('user_id', $userId)
            ->whereNotNull('producto_id')
            ->with([
                'producto.galeriaProductos',
                'producto.inventario',
                'variante' => fn($q) => $q->with([
                    'inventario',
                    'valores' => fn($vq) => $vq->with([
                        'valor',
                        'productoAtributo.atributo',
                    ]),
                ]),
            ])
            ->get();

        $disponibilidad  = $todos->mapWithKeys(fn($i) => [$i->id => $this->esDisponible($i)]);
        $items           = $todos->sortBy(fn($i) => $disponibilidad[$i->id] ? 0 : 1)->values();
        $datosParaAlpine = $this->computarDatosParaAlpine($todos);

        // Mantiene Alpine sincronizado tras cada re-render
        $this->dispatch('lista-deseos-datos', datos: $datosParaAlpine);

        return view('livewire.tienda.lista-deseos', compact('items', 'datosParaAlpine', 'disponibilidad'));
    }

    private function esDisponible(ListaDeseo $item): bool
    {
        $producto = $item->producto;
        if ($producto?->estado !== EstadoGeneral::Activo) return false;

        if ($item->variante_id !== null) {
            $variante = $item->variante;
            if ($variante === null) return false;
            if ($variante->estado !== 'activo') return false;
            if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                if ((float)($variante->inventario?->stock_real ?? 0) <= 0) return false;
            }
        } else {
            if ($producto->control_de_stock && ! $producto->venta_sin_stock) {
                if ((float)($producto->inventario?->stock_real ?? 0) <= 0) return false;
            }
        }

        return true;
    }

    private function calcularPrecioProducto($producto): float
    {
        if (! $producto) return 0.0;
        if (($producto->porcentaje_descuento ?? 0) > 0 && $producto->precio_con_descuento) {
            return (float) $producto->precio_con_descuento;
        }
        return (float) ($producto->precio_venta ?? 0);
    }

    private function computarDatosParaAlpine(Collection $items): array
    {
        $datos = [];
        foreach ($items as $item) {
            if (! $this->esDisponible($item)) continue;
            $precioUnit = $item->variante
                ? (float) $item->variante->precio_final
                : $this->calcularPrecioProducto($item->producto);
            $datos[(string) $item->id] = round($precioUnit * $item->cantidad, 2);
        }
        return $datos;
    }

    private function deseoCountActual(int $userId): int
    {
        return ListaDeseo::where('empresa_id', $this->empresaId)
            ->where('user_id', $userId)
            ->whereNotNull('producto_id')
            ->count();
    }

    private function itemDelUsuario(int $id): ?ListaDeseo
    {
        return ListaDeseo::where('id', $id)
            ->where('empresa_id', $this->empresaId)
            ->where('user_id', Auth::guard('cliente')->id())
            ->with(['variante', 'producto'])
            ->first();
    }
}
