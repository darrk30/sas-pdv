<?php

namespace App\Livewire\Pdv;

use App\Enums\EstadoGeneral;
use App\Models\Atributo;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\UnidadesMedida;
use App\Models\Valor;
use App\Services\InventarioCoreService;
use App\Services\ProductoAtributoService;
use App\Services\VarianteService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Component;

class CrearProductoRapido extends Component
{
    public bool   $abierto       = false;
    public string $nombre        = '';
    public string $precioVenta   = '';
    public string $precioCosto   = '';
    public bool   $controlStock  = false;
    public bool   $ventaSinStock = false;
    public string $stockInicial  = '0';

    /**
     * [
     *   'atributo_id'        => int|'',
     *   'nombre_nuevo'       => string,
     *   'valoresDisponibles' => [{id, nombre}],   // cargados al elegir atributo
     *   'valores' => [
     *     ['valor_id' => int|'', 'nombre_nuevo' => string, 'precio_extra' => string]
     *   ]
     * ]
     */
    public array $atributosVariantes = [];

    public bool  $tieneVariantesModulo  = false;
    public array $atributosDisponibles  = [];  // [{id, nombre}] para el select

    public function mount(): void
    {
        $empresa = Filament::getTenant();
        $this->tieneVariantesModulo = (bool) $empresa?->tieneVariantes();

        if ($this->tieneVariantesModulo) {
            $this->atributosDisponibles = Atributo::where('empresa_id', $empresa->id)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->toArray();
        }
    }

    public function abrir(): void
    {
        $this->reset(['nombre', 'precioVenta', 'precioCosto', 'stockInicial', 'atributosVariantes']);
        $this->controlStock  = false;
        $this->ventaSinStock = false;
        $this->abierto       = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function updatedControlStock(bool $value): void
    {
        if (! $value) {
            $this->ventaSinStock = false;
            $this->stockInicial  = '0';
        }
    }

    // ── Reactivo ──────────────────────────────────────────────────────────────

    public function updatedAtributosVariantes($value, $key): void
    {
        $parts = explode('.', $key);

        // Cambió el atributo → recargar valores disponibles y resetear lista
        if (str_ends_with($key, '.atributo_id')) {
            $ai = (int) $parts[0];

            $disponibles = $value
                ? Valor::where('atributo_id', $value)->orderBy('nombre')->get(['id', 'nombre'])->toArray()
                : [];

            $this->atributosVariantes[$ai]['valoresDisponibles'] = $disponibles;
            $this->atributosVariantes[$ai]['nombre_nuevo']       = '';
            $this->atributosVariantes[$ai]['valores']            = [
                ['valor_id' => '', 'nombre_nuevo' => '', 'precio_extra' => '0'],
            ];
            return;
        }

        // Cambió un valor_id → limpiar nombre_nuevo de ese valor
        if (str_ends_with($key, '.valor_id') && $value) {
            $ai = (int) $parts[0];
            $vi = (int) $parts[2];
            $this->atributosVariantes[$ai]['valores'][$vi]['nombre_nuevo'] = '';
        }
    }

    // ── Atributos ────────────────────────────────────────────────────────────

    public function agregarAtributo(): void
    {
        $this->atributosVariantes[] = [
            'atributo_id'        => '',
            'nombre_nuevo'       => '',
            'valoresDisponibles' => [],
            'valores'            => [['valor_id' => '', 'nombre_nuevo' => '', 'precio_extra' => '0']],
        ];
    }

    public function eliminarAtributo(int $ai): void
    {
        array_splice($this->atributosVariantes, $ai, 1);
        $this->atributosVariantes = array_values($this->atributosVariantes);
    }

    // ── Valores ───────────────────────────────────────────────────────────────

    public function agregarValor(int $ai): void
    {
        $this->atributosVariantes[$ai]['valores'][] = ['valor_id' => '', 'nombre_nuevo' => '', 'precio_extra' => '0'];
    }

    public function eliminarValor(int $ai, int $vi): void
    {
        array_splice($this->atributosVariantes[$ai]['valores'], $vi, 1);
        $this->atributosVariantes[$ai]['valores'] = array_values($this->atributosVariantes[$ai]['valores']);
    }

    // ── Guardar ───────────────────────────────────────────────────────────────

    public function guardar(): void
    {
        $rules = [
            'nombre'      => 'required|string|max:255',
            'precioVenta' => 'required|numeric|min:0',
            'precioCosto' => 'nullable|numeric|min:0',
            'stockInicial'=> 'nullable|numeric|min:0',
        ];

        foreach ($this->atributosVariantes as $ai => $attr) {
            foreach ($attr['valores'] as $vi => $vFila) {
                if (empty($vFila['valor_id'])) {
                    $rules["atributosVariantes.{$ai}.valores.{$vi}.nombre_nuevo"] = 'required|string|max:100';
                }
            }
        }

        $this->validate($rules, [
            'nombre.required'      => 'El nombre es obligatorio.',
            'precioVenta.required' => 'El precio de venta es obligatorio.',
            'precioVenta.numeric'  => 'El precio debe ser un número.',
            'atributosVariantes.*.valores.*.nombre.required' => 'Cada valor necesita un nombre.',
        ]);

        $empresa  = Filament::getTenant();
        $tieneVar = ! empty($this->atributosVariantes);

        if ($empresa->limiteProductosAlcanzado()) {
            Notification::make()->danger()->title('Límite de productos alcanzado')->send();
            return;
        }

        $precio       = (float) $this->precioVenta;
        $costo        = (float) ($this->precioCosto ?: 0);
        $stockInicial = (float) ($this->stockInicial ?: 0);

        $unidadId = UnidadesMedida::where('empresa_id', $empresa->id)
            ->where('nombre', 'Unidad')
            ->value('id');

        $producto = Producto::create([
            'empresa_id'           => $empresa->id,
            'nombre'               => $this->nombre,
            'slug'                 => Str::slug($this->nombre),
            'precio_venta'         => $precio,
            'precio_costo'         => $costo,
            'precio_con_descuento' => $precio,
            'porcentaje_descuento' => 0,
            'control_de_stock'     => $this->controlStock,
            'venta_sin_stock'      => $this->ventaSinStock,
            'unidad_medida_id'     => $unidadId,
            'estado'               => EstadoGeneral::Activo,
            'codigo_interno'       => 'PROD-' . strtoupper(substr(uniqid(), -6)),
            'vendible'             => true,
            'tiene_variantes'      => false,
        ]);

        $producto->update(['slug' => Str::slug($producto->nombre) . '-' . $producto->id]);

        Inventario::create([
            'empresa_id'        => $empresa->id,
            'producto_id'       => $producto->id,
            'variante_id'       => null,
            'stock_real'        => 0,
            'stock_reserva'     => 0,
            'stock_minimo'      => 0,
            'estado_almacen'    => $tieneVar ? 'inactivo' : 'activo',
            'estado_inventario' => 'agotado',
        ]);

        if ($tieneVar) {
            $this->crearVariantes($producto, $empresa->id);
        } elseif ($stockInicial > 0) {
            $this->aplicarStockInicial($producto, $stockInicial, $precio, $costo, $unidadId);
        }

        $producto->update(['tiene_variantes' => $tieneVar]);

        Notification::make()
            ->success()
            ->title('Producto creado')
            ->body("\"{$producto->nombre}\" creado correctamente.")
            ->send();

        $this->dispatch('producto-rapido-creado', id: $producto->id);
        $this->cerrar();
    }

    private function crearVariantes(Producto $producto, int $empresaId): void
    {
        $atributosForm = [];

        foreach ($this->atributosVariantes as $fila) {
            // Resolver o crear el atributo
            $atributoId = (int) ($fila['atributo_id'] ?? 0);

            if (! $atributoId) {
                $nombreNuevo = trim($fila['nombre_nuevo'] ?? '');
                if ($nombreNuevo === '') continue;

                $atributo = Atributo::firstOrCreate(
                    ['empresa_id' => $empresaId, 'nombre' => $nombreNuevo],
                    ['tipo' => 'texto']
                );
                $atributoId = $atributo->id;
            }

            $valoresSeleccionados = [];
            $extraPrices          = [];

            foreach ($fila['valores'] ?? [] as $vFila) {
                $valorId = (int) ($vFila['valor_id'] ?? 0);

                if ($valorId) {
                    $valoresSeleccionados[]  = $valorId;
                    $extraPrices[$valorId]   = (float) ($vFila['precio_extra'] ?? 0);
                } else {
                    $nombre = trim($vFila['nombre_nuevo'] ?? '');
                    if ($nombre === '') continue;

                    $valor = Valor::firstOrCreate(
                        ['atributo_id' => $atributoId, 'nombre' => $nombre],
                        ['valor' => $nombre]
                    );
                    $valoresSeleccionados[] = $valor->id;
                    $extraPrices[$valor->id] = (float) ($vFila['precio_extra'] ?? 0);
                }
            }

            if (empty($valoresSeleccionados)) continue;

            $atributosForm[] = [
                'atributo_id'           => $atributoId,
                'valores_seleccionados' => $valoresSeleccionados,
                'extra_prices'          => $extraPrices,
                'exclusiones_guardadas' => [],
                'valor_imagenes'        => [],
            ];
        }

        if (empty($atributosForm)) return;

        app(ProductoAtributoService::class)->sincronizarAtributos($producto, $atributosForm);
        app(VarianteService::class)->syncVariantes($producto, $atributosForm);

        foreach ($producto->variantes()->where('estado', 'activo')->get() as $variante) {
            Inventario::firstOrCreate(
                ['empresa_id' => $empresaId, 'producto_id' => $producto->id, 'variante_id' => $variante->id],
                ['stock_real' => 0, 'stock_reserva' => 0, 'stock_minimo' => 0,
                 'estado_almacen' => 'activo', 'estado_inventario' => 'agotado']
            );
        }
    }

    private function aplicarStockInicial(Producto $producto, float $cantidad, float $precio, float $costo, ?int $unidadId): void
    {
        if (! $unidadId) {
            Notification::make()->warning()
                ->title('Stock inicial no aplicado')
                ->body('No se encontró la unidad "Unidad". Agrégala desde Configuración.')
                ->send();
            return;
        }

        app(InventarioCoreService::class)->aplicarDetalles(
            empresaId: $producto->empresa_id,
            tipo: 'entrada',
            detalles: collect([[
                'producto_id'    => $producto->id,
                'variante_id'    => null,
                'unidad_id'      => $unidadId,
                'cantidad'       => $cantidad,
                'costo_unitario' => $costo,
                'costo_total'    => round($costo * $cantidad, 2),
                'precio_unitario'=> $precio,
                'precio_total'   => round($precio * $cantidad, 2),
            ]]),
            movible: $producto,
            concepto: 'Stock inicial',
            userId: auth()->id(),
        );
    }

    public function render()
    {
        return view('livewire.pdv.crear-producto-rapido');
    }
}
