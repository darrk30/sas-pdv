<?php

namespace App\Livewire\Pdv;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoAtributoValor;
use App\Models\Variante;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductVariantModal extends Component
{
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
    public string $productoUnidadSimbolo = '';

    #[On('open-variant-modal')]
    public function abrir(int $productoId, ?float $precioLista = null): void
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
        $this->productoUnidadSimbolo = $producto->unidadMedida?->simbolo ?? '';
        $this->productoModalId       = $productoId;
        $this->productoModalNombre   = $producto->nombre;
        $this->precioBaseOriginal    = (float) $producto->precio_venta;
        $this->precioBase            = $precioLista ?? (
            ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
                ? (float) $producto->precio_con_descuento
                : (float) $producto->precio_venta
        );
        $this->seleccionados         = [];
        $this->precioAdicionalTotal  = 0;

        $pavIdsUsados = $producto->variantesActivas
            ->flatMap(fn ($v) => $v->valores->pluck('id'))
            ->unique()->flip()->toArray();

        $this->variantesInfo = $producto->variantesActivas
            ->map(fn ($v) => [
                'id'      => $v->id,
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
                        if (! in_array($pavId, $varDatos['pav_ids'])) continue;
                        $compatible = true;
                        foreach ($this->seleccionados as $selPaId => $selPavId) {
                            if ((int) $selPaId === (int) $paId) continue;
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
                    if (! $tieneStock) $deshabilitados[] = $pavId;
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

        // Find variante from preloaded variantesInfo first (avoids DB query in simple case)
        $varianteId = null;
        foreach ($this->variantesInfo as $vInfo) {
            if (count($vInfo['pav_ids']) === count($selectedPavIds)
                && empty(array_diff($selectedPavIds, $vInfo['pav_ids']))
                && empty(array_diff($vInfo['pav_ids'], $selectedPavIds))) {
                $varianteId = $vInfo['id'];
                break;
            }
        }

        if (! $varianteId) {
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
            $varianteInfo = collect($this->variantesInfo)->firstWhere('id', $varianteId);
            $stock = (float) ($varianteInfo['stock'] ?? 0);
            if ($this->modalCantidad > $stock) {
                Notification::make()
                    ->title('Stock insuficiente')
                    ->body("Disponible: {$stock}.")
                    ->warning()->send();
                return;
            }
        }

        $stockMax = null;
        if ($this->productoControlStock) {
            $varianteInfo = collect($this->variantesInfo)->firstWhere('id', $varianteId);
            $stockMax = (float) ($varianteInfo['stock'] ?? 0);
        }

        $this->dispatch('product-selected', [
            'tipo'            => 'variante',
            'id'              => $varianteId,
            'nombre'          => $nombre,
            'precio'          => $precioFinal,
            'precio_normal'   => $precioNormal,
            'es_cortesia'     => $esCortesiaFinal,
            'cantidad'        => $this->modalCantidad,
            'producto_id'     => $this->productoModalId,
            'puede_cortesia'  => $this->productoEsCortesia,
            'es_decimal'      => $this->productoEsDecimal,
            'stock_max'       => $stockMax,
            'venta_sin_stock' => $this->productoVentaSinStock,
        ]);

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
        $this->productoUnidadSimbolo = '';
    }

    public function render()
    {
        return view('livewire.pdv.product-variant-modal');
    }
}
