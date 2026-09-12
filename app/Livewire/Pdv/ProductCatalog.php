<?php

namespace App\Livewire\Pdv;

use App\Enums\EstadoPromocion;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAtributoValor;
use App\Models\Promocion;
use App\Models\Variante;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProductCatalog extends Component
{
    // ── Props reactivas desde el padre ───────────────────────────────────────
    #[Reactive]
    public array $carritoResumen = []; // ['producto_123' => 2, 'variante_456' => 1]

    /** Solo ítems aún no persistidos en BD (para descontar de stock_reserva sin doble conteo) */
    #[Reactive]
    public array $pendienteResumen = [];

    #[Reactive]
    public ?bool $showPromociones = false; // true in PDV, false in restaurant pages

    // ── Filtros ───────────────────────────────────────────────────────────────
    public string $busqueda    = '';
    public ?int   $categoriaId = null;
    public int    $perPage     = 48;

    // ── Modal variantes ───────────────────────────────────────────────────────
    public bool   $modalAbierto          = false;
    public ?int   $productoModalId       = null;
    public string $productoModalNombre   = '';
    public float  $precioBase            = 0.0;
    public float  $precioBaseOriginal    = 0.0;
    public array  $atributosModal        = [];
    public array  $seleccionados         = [];
    public float  $precioAdicionalTotal  = 0.0;
    public bool   $productoControlStock  = false;
    public bool   $productoVentaSinStock = false;
    public bool   $productoEsCortesia    = false;
    public bool   $productoEsDecimal     = false;
    public array  $variantesInfo         = [];
    public array  $exclusionesMap        = [];
    public array  $valoresDeshabilitados = [];
    public float  $modalCantidad         = 1.0;
    public bool   $modalCortesia         = false;

    // ── Filtros ───────────────────────────────────────────────────────────────

    public function seleccionarCategoria(?int $id): void
    {
        $this->categoriaId = $id;
        $this->perPage     = 48;
    }

    public function updatedBusqueda(): void
    {
        $this->perPage = 48;
    }

    public function cargarMas(): void
    {
        $this->perPage += 48;
    }

    public function limpiarBusqueda(): void
    {
        $this->busqueda = '';
    }

    #[On('pdv-barcode')]
    public function recibirBarcode(string $code): void
    {
        $empresaId = Filament::getTenant()->id;

        $producto = Producto::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where(function ($q) use ($code) {
                $q->where('codigo_barras', $code)
                  ->orWhere('codigo_interno', $code);
            })
            ->with(['inventario', 'variantesActivas', 'unidadMedida.dimension'])
            ->first();

        if (! $producto) {
            Notification::make()
                ->title('Código no encontrado')
                ->body("No hay producto activo con código: {$code}")
                ->warning()
                ->send();
            return;
        }

        if ($producto->variantesActivas->isNotEmpty()) {
            $this->busqueda = $code;
            return;
        }

        $this->abrirModalProducto($producto->id);
    }

    #[On('camera-not-available')]
    public function cameraNoDiponible(): void
    {
        Notification::make()
            ->title('Cámara no disponible')
            ->body('Activa los permisos de cámara en el navegador o usa un escáner USB.')
            ->warning()
            ->send();
    }

    // ── Promociones (solo cuando $showPromociones = true) ────────────────────

    public function getHayPromociones(): bool
    {
        if (! ($this->showPromociones ?? false)) return false;

        return Promocion::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoPromocion::Activo->value)
            ->exists();
    }

    public function getPromociones(): Collection
    {
        if (! ($this->showPromociones ?? false) || $this->categoriaId !== -1) {
            return collect();
        }

        $query = Promocion::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoPromocion::Activo->value)
            ->withCount('detalles')
            ->with([
                'detalles.producto.inventario',
                'detalles.variante.producto',
                'detalles.variante.inventario',
            ]);

        if ($this->busqueda !== '') {
            $query->where('nombre', 'like', "%{$this->busqueda}%");
        }

        return $query->orderBy('nombre')->get();
    }

    public function seleccionarPromocion(int $promocionId): void
    {
        $promo = Promocion::with(['detalles.producto', 'detalles.variante'])->find($promocionId);
        if (! $promo) return;

        $detallesResumen = $promo->detalles->map(fn ($d) => [
            'nombre'      => $d->variante?->nombre ?? $d->producto?->nombre ?? '—',
            'cantidad'    => (float) ($d->cantidad ?? 1),
            'producto_id' => $d->producto_id,
            'variante_id' => $d->variante_id,
        ])->values()->all();

        $this->dispatch('product-selected', [
            'tipo'             => 'promocion',
            'id'               => $promo->id,
            'nombre'           => $promo->nombre,
            'precio'           => (float) $promo->precio,
            'precio_normal'    => (float) $promo->precio,
            'es_cortesia'      => false,
            'cantidad'         => 1,
            'producto_id'      => $promo->id,
            'puede_cortesia'   => false,
            'es_decimal'       => false,
            'detalles_resumen' => $detallesResumen,
        ]);
    }

    // ── Datos para la vista ───────────────────────────────────────────────────

    public function getCategorias(): Collection
    {
        return Categoria::where('empresa_id', Filament::getTenant()->id)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function getProductos(): Collection
    {
        if ($this->categoriaId === -1) {
            return collect();
        }

        $empresaId = Filament::getTenant()->id;

        $query = Producto::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where('visible_en_carta', true)
            ->where('vendible', true)
            ->with([
                'variantesActivas' => fn ($q) => $q->with('inventario'),
                'inventario',
                'unidadMedida',
            ]);

        if ($this->busqueda !== '') {
            $b = $this->busqueda;
            $query->where(function ($q) use ($b) {
                $q->where('nombre', 'like', "%{$b}%")
                  ->orWhere('codigo_interno', 'like', "%{$b}%")
                  ->orWhere('codigo_barras', 'like', "%{$b}%");
            });
        }

        if ($this->categoriaId !== null) {
            $query->where('categoria_id', $this->categoriaId);
        }

        return $query
            ->orderByRaw("
                CASE
                    WHEN productos.control_de_stock = 0 THEN 0
                    WHEN productos.venta_sin_stock  = 1 THEN 0
                    WHEN EXISTS (
                        SELECT 1 FROM variantes v
                        WHERE v.producto_id = productos.id AND v.estado = ?
                    ) THEN
                        CASE WHEN (
                            SELECT COALESCE(SUM(i.stock_reserva), 0)
                            FROM inventarios i
                            INNER JOIN variantes v ON i.variante_id = v.id
                            WHERE v.producto_id = productos.id
                              AND v.estado      = ?
                              AND i.empresa_id  = productos.empresa_id
                        ) > 0 THEN 0 ELSE 1 END
                    ELSE
                        CASE WHEN (
                            SELECT COALESCE(SUM(i.stock_reserva), 0)
                            FROM inventarios i
                            WHERE i.producto_id = productos.id
                              AND i.variante_id IS NULL
                              AND i.empresa_id  = productos.empresa_id
                        ) > 0 THEN 0 ELSE 1 END
                END ASC
            ", ['activo', 'activo'])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->take($this->perPage)
            ->get();
    }

    // ── Modal variantes ───────────────────────────────────────────────────────

    public function abrirModalProducto(int $productoId): void
    {
        $producto = Producto::with([
            'variantesActivas' => fn ($q) => $q->with(['inventario', 'valores']),
            'atributos.atributo',
            'atributos.detallesPrecios.valor',
            'atributos.detallesExclusiones',
            'unidadMedida.dimension',
        ])->findOrFail($productoId);

        $this->productoControlStock  = (bool) $producto->control_de_stock;
        $this->productoVentaSinStock = (bool) $producto->venta_sin_stock;
        $this->productoEsCortesia    = (bool) $producto->es_cortesia;
        $this->productoEsDecimal     = $producto->unidadMedida?->esContinua() ?? false;

        if ($producto->variantesActivas->isEmpty()) {
            $puedeCortesia = (bool) $producto->es_cortesia;
            $precioNormal  = (float) $producto->precio_venta;
            $precio        = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
                ? (float) $producto->precio_con_descuento
                : $precioNormal;

            $this->emitirProductoSeleccionado('producto', $productoId, $producto->nombre, $precio, $precioNormal, false, 1.0, $productoId, $puedeCortesia, $this->productoEsDecimal);
            return;
        }

        $this->productoModalId     = $productoId;
        $this->productoModalNombre = $producto->nombre;
        $this->precioBaseOriginal  = (float) $producto->precio_venta;
        $this->precioBase          = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
            ? (float) $producto->precio_con_descuento
            : (float) $producto->precio_venta;
        $this->seleccionados       = [];
        $this->precioAdicionalTotal = 0;

        $pavIdsUsados = $producto->variantesActivas
            ->flatMap(fn ($v) => $v->valores->pluck('id'))
            ->unique()->flip()->toArray();

        $this->variantesInfo = $producto->variantesActivas
            ->map(fn ($v) => [
                'pav_ids' => $v->valores->pluck('id')->toArray(),
                'stock'   => (float) ($v->inventario?->stock_reserva ?? 0),
            ])
            ->values()->toArray();

        $this->atributosModal = $producto->atributos
            ->map(fn ($pa) => [
                'id'     => $pa->id,
                'nombre' => $pa->atributo->nombre,
                'valores' => $pa->detallesPrecios
                    ->filter(fn ($pav) => isset($pavIdsUsados[$pav->id]))
                    ->map(fn ($pav) => [
                        'id'               => $pav->id,
                        'valor_id'         => $pav->valor_id,
                        'nombre'           => $pav->valor->nombre,
                        'precio_adicional' => (float) $pav->precio_adicional,
                    ])
                    ->values()->toArray(),
            ])
            ->filter(fn ($a) => count($a['valores']) > 0)
            ->values()->toArray();

        $exclusionesMap = [];
        foreach ($producto->atributos as $pa) {
            foreach ($pa->detallesExclusiones as $ex) {
                $exclusionesMap[$ex->valor_base_id][] = $ex->valor_exluido_id;
            }
        }
        $this->exclusionesMap = $exclusionesMap;

        $this->calcularDeshabilitados();
        $this->modalCantidad = 1.0;
        $this->modalCortesia = false;
        $this->modalAbierto  = true;
    }

    public function seleccionarValor(int $productoAtributoId, int $productoAtributoValorId): void
    {
        if (isset($this->seleccionados[$productoAtributoId])
            && (int) $this->seleccionados[$productoAtributoId] === $productoAtributoValorId) {
            unset($this->seleccionados[$productoAtributoId]);
        } else {
            $this->seleccionados[$productoAtributoId] = $productoAtributoValorId;
        }
        $this->recalcularPrecioAdicional();
        $this->calcularDeshabilitados();
    }

    private function calcularDeshabilitados(): void
    {
        $deshabilitados = [];

        foreach ($this->seleccionados as $paId => $pavId) {
            $valorId = null;
            foreach ($this->atributosModal as $atributo) {
                if ((int) $atributo['id'] === (int) $paId) {
                    foreach ($atributo['valores'] as $v) {
                        if ((int) $v['id'] === (int) $pavId) {
                            $valorId = $v['valor_id'];
                            break;
                        }
                    }
                    break;
                }
            }

            if ($valorId && isset($this->exclusionesMap[$valorId])) {
                foreach ($this->exclusionesMap[$valorId] as $exclValorId) {
                    foreach ($this->atributosModal as $atributo) {
                        foreach ($atributo['valores'] as $v) {
                            if ((int) $v['valor_id'] === (int) $exclValorId) {
                                $deshabilitados[] = (int) $v['id'];
                            }
                        }
                    }
                }
            }
        }

        if ($this->productoControlStock && ! $this->productoVentaSinStock) {
            foreach ($this->atributosModal as $atributo) {
                $paId = $atributo['id'];

                foreach ($atributo['valores'] as $valor) {
                    $pavId = (int) $valor['id'];

                    if (isset($this->seleccionados[$paId])
                        && (int) $this->seleccionados[$paId] === $pavId) {
                        continue;
                    }

                    $tieneStock = false;

                    foreach ($this->variantesInfo as $varDatos) {
                        if (! in_array($pavId, $varDatos['pav_ids'])) {
                            continue;
                        }
                        $compatible = true;
                        foreach ($this->seleccionados as $selPaId => $selPavId) {
                            if ((int) $selPaId === (int) $paId) {
                                continue;
                            }
                            if (! in_array((int) $selPavId, $varDatos['pav_ids'])) {
                                $compatible = false;
                                break;
                            }
                        }
                        if ($compatible && $varDatos['stock'] > 0) {
                            $tieneStock = true;
                            break;
                        }
                    }

                    if (! $tieneStock) {
                        $deshabilitados[] = $pavId;
                    }
                }
            }
        }

        $this->valoresDeshabilitados = array_values(array_unique($deshabilitados));
    }

    private function recalcularPrecioAdicional(): void
    {
        if (empty($this->seleccionados)) {
            $this->precioAdicionalTotal = 0;
            return;
        }

        $this->precioAdicionalTotal = (float) ProductoAtributoValor::whereIn('id', array_values($this->seleccionados))
            ->sum('precio_adicional');
    }

    public function confirmarModalConParams(float $cantidad, bool $esCortesia): void
    {
        $min = $this->productoEsDecimal ? 0.001 : 1;
        $this->modalCantidad = max($min, $cantidad);
        $this->modalCortesia = $esCortesia && $this->productoEsCortesia;
        $this->confirmarModal();
    }

    public function confirmarModal(): void
    {
        if (! $this->productoModalId || count($this->seleccionados) < count($this->atributosModal)) {
            return;
        }

        $selectedPavIds = array_values($this->seleccionados);

        $variante = Variante::where('producto_id', $this->productoModalId)
            ->where(function ($q) use ($selectedPavIds) {
                foreach ($selectedPavIds as $pavId) {
                    $q->whereHas('valores', fn ($vq) => $vq->where('producto_atributo_valors_id', $pavId));
                }
            })
            ->first();

        if (! $variante) {
            Notification::make()
                ->title('Combinación no disponible')
                ->body('No existe una variante con la combinación seleccionada.')
                ->warning()
                ->send();
            return;
        }

        $sufijo = collect($this->atributosModal)
            ->map(fn ($a) => collect($a['valores'])->firstWhere('id', $this->seleccionados[$a['id']] ?? null)['nombre'] ?? null)
            ->filter()
            ->implode(' - ');

        $nombre = $sufijo ? "{$this->productoModalNombre} ({$sufijo})" : $this->productoModalNombre;

        $esCortesiaFinal = $this->productoEsCortesia && $this->modalCortesia;
        $precioNormal    = $this->precioBaseOriginal + $this->precioAdicionalTotal;
        $precioFinal     = $esCortesiaFinal ? 0.0 : ($this->precioBase + $this->precioAdicionalTotal);

        if ($this->productoControlStock && ! $this->productoVentaSinStock) {
            $inv   = Inventario::where('variante_id', $variante->id)->first();
            $stock = (float) ($inv?->stock_reserva ?? 0);
            if ($this->modalCantidad > $stock) {
                Notification::make()
                    ->title('Stock insuficiente')
                    ->body("Disponible: {$stock}.")
                    ->warning()->send();
                return;
            }
        }

        $this->emitirProductoSeleccionado(
            'variante',
            $variante->id,
            $nombre,
            $precioFinal,
            $precioNormal,
            $esCortesiaFinal,
            $this->modalCantidad,
            $this->productoModalId,
            $this->productoEsCortesia,
            $this->productoEsDecimal,
        );

        $this->cerrarModal();
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto          = false;
        $this->productoModalId       = null;
        $this->productoModalNombre   = '';
        $this->atributosModal        = [];
        $this->seleccionados         = [];
        $this->precioAdicionalTotal  = 0;
        $this->productoControlStock  = false;
        $this->productoVentaSinStock = false;
        $this->productoEsCortesia    = false;
        $this->productoEsDecimal     = false;
        $this->variantesInfo         = [];
        $this->exclusionesMap        = [];
        $this->valoresDeshabilitados = [];
        $this->modalCantidad         = 1.0;
        $this->modalCortesia         = false;
    }

    // ── Emitir evento al padre ────────────────────────────────────────────────

    private function emitirProductoSeleccionado(
        string $tipo,
        int    $id,
        string $nombre,
        float  $precio,
        float  $precioNormal,
        bool   $esCortesia,
        float  $cantidad,
        int    $productoId,
        bool   $puedeCortesia = false,
        bool   $esDecimal = false,
    ): void {
        $this->dispatch('product-selected', [
            'tipo'          => $tipo,
            'id'            => $id,
            'nombre'        => $nombre,
            'precio'        => $precio,
            'precio_normal' => $precioNormal,
            'es_cortesia'   => $esCortesia,
            'cantidad'      => $cantidad,
            'producto_id'   => $productoId,
            'puede_cortesia'=> $puedeCortesia,
            'es_decimal'    => $esDecimal,
        ]);
    }

    public function render()
    {
        return view('livewire.pdv.product-catalog', [
            'categorias'    => $this->getCategorias(),
            'productos'     => $this->getProductos(),
            'promociones'   => $this->getPromociones(),
            'hayPromociones'=> $this->getHayPromociones(),
        ]);
    }
}
