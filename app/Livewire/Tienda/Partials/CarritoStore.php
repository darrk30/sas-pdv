<?php

namespace App\Livewire\Tienda\Partials;

use App\Models\Carrito;
use App\Models\CarritoItem;
use App\Models\ListaDeseo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class CarritoStore extends Component
{
    public int $empresaId = 0;

    public function mount(): void
    {
        // Solo se ejecuta en la carga inicial (con TiendaEmpresa middleware activo)
        $this->empresaId = app('tienda.empresa')->id;

        if (Auth::guard('cliente')->check()) {
            $userId = Auth::guard('cliente')->id();

            // Count carrito
            $carrito = Carrito::where('empresa_id', $this->empresaId)
                ->where('user_id', $userId)
                ->first();

            if ($carrito) {
                $this->dispatch('carrito-count-actualizado', count: (int) $carrito->items()->sum('cantidad'));
            }

            // Estado inicial de lista de deseos
            $deseoItems = ListaDeseo::where('empresa_id', $this->empresaId)
                ->where('user_id', $userId)
                ->whereNotNull('producto_id')
                ->get(['id', 'producto_id']);

            $deseos = $deseoItems
                ->pluck('producto_id')
                ->unique()
                ->mapWithKeys(fn($id) => [$id => true])
                ->toArray();

            if ($deseos) {
                $this->dispatch('deseos-cargados', deseos: $deseos);
            }

            // Conteo real de entradas (no productos únicos)
            $this->dispatch('deseo-count-actualizado', count: $deseoItems->count());
        }
    }

    #[On('browser:carrito-agregar')]
    public function agregarItem(array $item): void
    {
        if (! Auth::guard('cliente')->check()) return;

        $carrito = Carrito::firstOrCreate([
            'empresa_id' => $this->empresaId,
            'user_id'    => Auth::guard('cliente')->id(),
        ]);

        $existing = $this->buscarItem($carrito, $item);

        try {
            if ($existing) {
                $existing->increment('cantidad', (int) ($item['cantidad'] ?? 1));
            } else {
                $carrito->items()->create([
                    'promocion_id'    => $item['promocion_id']   ?? null,
                    'producto_id'     => $item['producto_id']    ?? null,
                    'variante_id'     => $item['variante_id']    ?? null,
                    'cantidad'        => (int) ($item['cantidad'] ?? 1),
                    'precio_unitario' => (float) ($item['precio_unitario'] ?? 0),
                ]);
            }
        } catch (\Exception) {
            // Producto/variante ya no existe — ignorar
        }

        $count = $carrito->items()->sum('cantidad');
        $this->dispatch('carrito-count-actualizado', count: $count);
    }

    #[On('browser:carrito-sincronizar')]
    public function sincronizarItems(array $items): void
    {
        if (! Auth::guard('cliente')->check()) return;

        $carrito = Carrito::firstOrCreate([
            'empresa_id' => $this->empresaId,
            'user_id'    => Auth::guard('cliente')->id(),
        ]);

        foreach ($items as $item) {
            $existing = $this->buscarItem($carrito, $item);

            try {
                if ($existing) {
                    if (($item['cantidad'] ?? 1) > $existing->cantidad) {
                        $existing->update(['cantidad' => $item['cantidad']]);
                    }
                } else {
                    $carrito->items()->create([
                        'promocion_id'    => $item['promocion_id']   ?? null,
                        'producto_id'     => $item['producto_id']    ?? null,
                        'variante_id'     => $item['variante_id']    ?? null,
                        'cantidad'        => (int) ($item['cantidad'] ?? 1),
                        'precio_unitario' => (float) ($item['precio_unitario'] ?? 0),
                    ]);
                }
            } catch (\Exception) {
                // Producto/promoción ya no existe — omitir este ítem
            }
        }

        $count = $carrito->items()->sum('cantidad');
        $this->dispatch('carrito-count-actualizado', count: $count);
        $this->dispatch('carrito-limpiar-local'); // ya está en DB, limpiar localStorage
    }

    #[On('browser:lista-deseos-agregar')]
    public function agregarDeseo(int $productoId, ?int $varianteId = null, int $cantidad = 1): void
    {
        if (! Auth::guard('cliente')->check()) return;

        $userId = Auth::guard('cliente')->id();

        $item = ListaDeseo::where('empresa_id', $this->empresaId)
            ->where('user_id', $userId)
            ->where('producto_id', $productoId)
            ->where(fn($q) => is_null($varianteId) ? $q->whereNull('variante_id') : $q->where('variante_id', $varianteId))
            ->first();

        if ($item) {
            $item->increment('cantidad', $cantidad);
        } else {
            ListaDeseo::create([
                'empresa_id'  => $this->empresaId,
                'user_id'     => $userId,
                'producto_id' => $productoId,
                'variante_id' => $varianteId,
                'cantidad'    => $cantidad,
            ]);
        }

        $count = ListaDeseo::where('empresa_id', $this->empresaId)
            ->where('user_id', $userId)
            ->whereNotNull('producto_id')
            ->count();

        $this->dispatch('lista-deseos-actualizada', productoId: $productoId, enDeseos: true);
        $this->dispatch('deseo-count-actualizado', count: $count);
        $this->dispatch('toast', mensaje: 'Agregado a tu lista de deseos', tipo: 'success');
    }

    private function buscarItem(Carrito $carrito, array $item): ?CarritoItem
    {
        $q = $carrito->items();

        if (! empty($item['promocion_id'])) {
            return $q->where('promocion_id', $item['promocion_id'])->first();
        }

        $q->whereNull('promocion_id')->where('producto_id', $item['producto_id'] ?? null);

        empty($item['variante_id'])
            ? $q->whereNull('variante_id')
            : $q->where('variante_id', $item['variante_id']);

        return $q->first();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.tienda.partials.carrito-store');
    }
}
