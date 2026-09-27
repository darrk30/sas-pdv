<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\CondicionPago;
use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\Variante;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\FacturadorService;
use App\Services\VentaService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action as FilamentAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class EditarVentaPage extends Page implements HasTable
{
    use HasFullWidthPage;
    use InteractsWithTable;

    protected string $view = 'filament.pdv.pages.editar-venta';
    protected static string|BackedEnum|null $navigationIcon = null;
    protected static bool $shouldRegisterNavigation = false;

    // ── Venta cargada ─────────────────────────────────────────────────────────

    public ?Venta $venta = null;

    // ── Serie / comprobante ───────────────────────────────────────────────────

    public ?int $serieId           = null;
    public array $seriesDisponibles = [];

    // ── Items del carrito editable ────────────────────────────────────────────

    public array $items = [];

    // ── Cliente ───────────────────────────────────────────────────────────────

    public ?int    $clienteId       = null;
    public ?string $clienteNombre   = null;
    public string  $clienteBusqueda = '';
    public array   $clienteSugs     = [];

    // ── Descuento ─────────────────────────────────────────────────────────────

    public string $descuentoInput = '0';

    // ── Pagos ─────────────────────────────────────────────────────────────────

    public array $pagos       = [];
    public array $metodosPago = [];

    // ── Búsqueda de productos ─────────────────────────────────────────────────

    public string $busquedaProd   = '';
    public array  $resultadosProd = [];

    // ── Estado ────────────────────────────────────────────────────────────────

    public bool   $guardando           = false;
    public bool   $mostrarConfirmacion = false;
    public string $mensajeConfirmacion = '';

    // ─────────────────────────────────────────────────────────────────────────

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->getTitle();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        if ($this->venta) {
            $comp = ($this->venta->serie?->serie ?? '---') . '-' . str_pad((string) $this->venta->correlativo, 8, '0', STR_PAD_LEFT);
            return "Editar venta {$comp}";
        }
        return 'Editar venta';
    }

    public function getBreadcrumbs(): array
    {
        return [
            VentasSesionPage::getUrl(tenant: Filament::getTenant()) => 'Ventas del Turno',
            '#' => 'Editar',
        ];
    }

    // ── Mount ─────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $ventaId = (int) request()->query('venta_id', 0);

        if (! $ventaId) {
            $this->redirect(VentasSesionPage::getUrl(tenant: Filament::getTenant()));
            return;
        }

        $empresa = Filament::getTenant();

        $venta = Venta::where('empresa_id', $empresa->id)
            ->with(['serie', 'detalles.producto', 'detalles.variante.producto', 'cliente', 'pagos.metodoPago'])
            ->find($ventaId);

        if (! $venta || $venta->estaAnulada()) {
            Notification::make()->title('Venta no encontrada o anulada')->danger()->send();
            $this->redirect(VentasSesionPage::getUrl(tenant: Filament::getTenant()));
            return;
        }

        $estadosEditables = [
            EstadoSunat::NoAplica->value,
            EstadoSunat::PorEnviar->value,
            EstadoSunat::Error->value,
        ];

        $estadoSunatVal = $venta->estado_sunat instanceof EstadoSunat
            ? $venta->estado_sunat->value
            : (string) $venta->estado_sunat;

        if (! in_array($estadoSunatVal, $estadosEditables)) {
            Notification::make()->title('Esta venta ya fue enviada a SUNAT y no se puede editar')->warning()->send();
            $this->redirect(VentasSesionPage::getUrl(tenant: Filament::getTenant()));
            return;
        }

        $this->venta = $venta;

        // Series disponibles de la empresa
        $this->seriesDisponibles = Serie::where('empresa_id', $empresa->id)
            ->where('estado', true)
            ->orderBy('tipo')
            ->orderBy('serie')
            ->get()
            ->map(fn ($s) => [
                'id'    => $s->id,
                'label' => $s->serie . ' (' . ($s->tipo instanceof TipoComprobante ? ucfirst($s->tipo->value) : $s->tipo) . ')',
                'tipo'  => $s->tipo instanceof TipoComprobante ? $s->tipo->value : (string) $s->tipo,
            ])
            ->toArray();

        $this->serieId = $venta->serie_id;

        // Métodos de pago
        $this->metodosPago = MetodoPago::where('empresa_id', $empresa->id)
            ->where('estado', \App\Enums\EstadoGeneral::Activo->value)
            ->get()
            ->map(fn ($m) => [
                'id'             => $m->id,
                'nombre'         => $m->nombre,
                'condicion_pago' => $m->condicion_pago instanceof CondicionPago ? $m->condicion_pago->value : (string) ($m->condicion_pago ?? 'contado'),
            ])
            ->toArray();

        // Inicializar pagos desde los registros existentes
        $this->pagos = $venta->pagos->map(fn ($p) => [
            'metodo_pago_id' => $p->metodo_pago_id,
            'monto'          => (float) $p->monto,
            'referencia'     => $p->referencia ?? '',
            'condicion_pago' => $p->metodoPago?->condicion_pago instanceof CondicionPago
                ? $p->metodoPago->condicion_pago->value
                : (string) ($p->metodoPago?->condicion_pago ?? 'contado'),
        ])->values()->toArray();

        // Inicializar cliente
        $this->clienteId       = $venta->cliente_id;
        $this->clienteNombre   = $venta->cliente_nombre;
        $this->clienteBusqueda = $venta->cliente_nombre ?? '';

        // Inicializar descuento
        $this->descuentoInput = number_format((float) $venta->descuento_total, 2, '.', '');

        // Inicializar items desde detalles
        $this->items = [];
        foreach ($venta->detalles as $det) {
            $tipo = match ($det->tipo_item->value ?? $det->tipo_item) {
                'variante'  => 'variante',
                'promocion' => 'promocion',
                default     => 'producto',
            };
            $key = match ($tipo) {
                'variante'  => "v:{$det->variante_id}",
                'promocion' => "promo:{$det->promocion_id}",
                default     => "p:{$det->producto_id}",
            };
            $this->items[$key] = [
                'key'         => $key,
                'tipo'        => $tipo,
                'id'          => $tipo === 'variante' ? $det->variante_id : ($tipo === 'promocion' ? $det->promocion_id : $det->producto_id),
                'nombre'      => str_replace(' (Cortesía)', '', $det->descripcion),
                'precio'      => (float) $det->precio_unitario,
                'cantidad'    => (float) $det->cantidad,
                'costo'       => (float) $det->costo_unitario,
                'cortesia'    => str_contains($det->descripcion, '(Cortesía)'),
                'producto_id' => $det->producto_id,
                'variante_id' => $det->variante_id,
            ];
        }
    }

    // ── Serie ─────────────────────────────────────────────────────────────────

    public function updatedSerieId(): void
    {
        if (! $this->serieId) { return; }

        $serie = collect($this->seriesDisponibles)->firstWhere('id', $this->serieId);
        $tipoVal = $serie['tipo'] ?? null;

        // Si la nueva serie NO es Ticket, eliminar pagos de crédito
        if ($tipoVal !== TipoComprobante::Ticket->value) {
            $this->pagos = array_values(array_filter(
                $this->pagos,
                fn ($p) => ($p['condicion_pago'] ?? 'contado') !== 'credito'
            ));
        }
    }

    public function esTiketActual(): bool
    {
        $serie = collect($this->seriesDisponibles)->firstWhere('id', $this->serieId);
        return ($serie['tipo'] ?? '') === TipoComprobante::Ticket->value;
    }

    // ── Cliente ───────────────────────────────────────────────────────────────

    public function updatedClienteBusqueda(): void
    {
        if (strlen($this->clienteBusqueda) < 2) {
            $this->clienteSugs = [];
            return;
        }
        $b = $this->clienteBusqueda;
        $this->clienteSugs = Cliente::where('empresa_id', Filament::getTenant()->id)
            ->where(function ($q) use ($b) {
                $q->where('nombre', 'like', "%{$b}%")
                  ->orWhere('apellidos', 'like', "%{$b}%")
                  ->orWhere('numero_documento', 'like', "%{$b}%");
            })
            ->limit(8)
            ->get()
            ->map(fn ($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre_completo,
                'doc'    => $c->numero_documento ?? '',
            ])
            ->toArray();
    }

    public function seleccionarCliente(int $id): void
    {
        $c = Cliente::find($id);
        if (! $c) { return; }
        $this->clienteId       = $c->id;
        $this->clienteNombre   = $c->nombre_completo;
        $this->clienteBusqueda = $c->nombre_completo;
        $this->clienteSugs     = [];
    }

    public function limpiarCliente(): void
    {
        $this->clienteId       = null;
        $this->clienteNombre   = null;
        $this->clienteBusqueda = '';
        $this->clienteSugs     = [];
    }

    // ── Búsqueda de productos ─────────────────────────────────────────────────

    public function updatedBusquedaProd(): void
    {
        if (strlen($this->busquedaProd) < 2) {
            $this->resultadosProd = [];
            return;
        }
        $b       = $this->busquedaProd;
        $empresa = Filament::getTenant();

        $this->resultadosProd = Producto::where('empresa_id', $empresa->id)
            ->where('vendible', true)
            ->where(function ($q) use ($b) {
                $q->where('nombre', 'like', "%{$b}%")
                  ->orWhere('codigo_barras', 'like', "%{$b}%")
                  ->orWhere('codigo_interno', 'like', "%{$b}%");
            })
            ->with(['variantes', 'inventario'])
            ->limit(10)
            ->get()
            ->map(function (Producto $p) use ($empresa) {
                $inv   = $p->inventario;
                $stock = $inv ? (float) $inv->stock_real : null;
                return [
                    'id'              => $p->id,
                    'nombre'          => $p->nombre,
                    'precio'          => (float) $p->precio_venta,
                    'costo'           => (float) ($p->precio_costo ?? 0),
                    'control_stock'   => (bool) $p->control_de_stock,
                    'venta_sin_stock' => (bool) $p->venta_sin_stock,
                    'stock_real'      => $stock,
                    'tiene_variantes' => $p->variantes->count() > 0,
                    'variantes'       => $p->variantes->map(fn ($v) => [
                        'id'         => $v->id,
                        'nombre'     => $v->nombre ?? 'Variante',
                        'precio'     => (float) ($v->precio_final ?? $p->precio_venta),
                        'costo'      => (float) ($v->precio_costo ?? 0),
                        'stock_real' => (float) (Inventario::where('empresa_id', $empresa->id)->where('variante_id', $v->id)->first()?->stock_real ?? 0),
                    ])->values()->toArray(),
                ];
            })
            ->toArray();
    }

    public function agregarProducto(int $productoId, ?int $varianteId = null): void
    {
        if (! $this->venta) { return; }

        if ($varianteId) {
            $variante = Variante::find($varianteId);
            $producto = $variante ? Producto::find($variante->producto_id) : null;
            if (! $variante || ! $producto) { return; }

            $existing = VentaDetalle::where('venta_id', $this->venta->id)
                ->where('variante_id', $varianteId)->first();

            if ($existing) {
                $existing->update(['cantidad' => $existing->cantidad + 1]);
            } else {
                $this->crearDetalleEnDb([
                    'tipo'        => 'variante',
                    'id'          => $varianteId,
                    'nombre'      => $producto->nombre . ' — ' . ($variante->nombre ?? ''),
                    'precio'      => (float) ($variante->precio_final ?? $producto->precio_venta),
                    'cantidad'    => 1,
                    'costo'       => (float) ($variante->precio_costo ?? 0),
                    'producto_id' => $producto->id,
                    'variante_id' => $varianteId,
                ]);
            }
        } else {
            $producto = Producto::find($productoId);
            if (! $producto) { return; }

            $existing = VentaDetalle::where('venta_id', $this->venta->id)
                ->where('producto_id', $productoId)->whereNull('variante_id')->first();

            if ($existing) {
                $existing->update(['cantidad' => $existing->cantidad + 1]);
            } else {
                $this->crearDetalleEnDb([
                    'tipo'        => 'producto',
                    'id'          => $productoId,
                    'nombre'      => $producto->nombre,
                    'precio'      => (float) $producto->precio_venta,
                    'cantidad'    => 1,
                    'costo'       => (float) ($producto->precio_costo ?? 0),
                    'producto_id' => $productoId,
                    'variante_id' => null,
                ]);
            }
        }

        $this->recargarItems();
        $this->busquedaProd   = '';
        $this->resultadosProd = [];
    }

    private function crearDetalleEnDb(array $item): void
    {
        $igvPct = (float) (Filament::getTenant()->igv_porcentaje ?? 18) / 100;
        $calcs  = VentaDetalle::calcular(
            (float) $item['cantidad'],
            (float) $item['precio'],
            (float) ($item['costo'] ?? 0),
            0,
            $igvPct
        );
        VentaDetalle::create([
            'venta_id'        => $this->venta->id,
            'tipo_item'       => $item['tipo'],
            'producto_id'     => $item['producto_id'],
            'variante_id'     => $item['variante_id'] ?? null,
            'descripcion'     => $item['nombre'],
            'cantidad'        => $item['cantidad'],
            'precio_unitario' => $item['precio'],
            'valor_unitario'  => $calcs['valorUnitario'],
            'costo_unitario'  => $item['costo'] ?? 0,
            'descuento'       => 0,
            'subtotal'        => $calcs['subtotal'],
            'valor_total'     => $calcs['valorTotal'],
            'igv'             => $calcs['igv'],
            'tip_afe_igv'     => '10',
            'unidad'          => 'NIU',
            'total'           => $calcs['total'],
            'costo_total'     => $calcs['costoTotal'],
        ]);
    }

    // ── Cantidad ──────────────────────────────────────────────────────────────

    public function incrementarCantidad(string $key): void
    {
        if (! isset($this->items[$key])) { return; }
        $items = $this->items;
        $items[$key]['cantidad'] = (float) $items[$key]['cantidad'] + 1;
        $this->items = $items;
    }

    public function decrementarCantidad(string $key): void
    {
        if (! isset($this->items[$key])) { return; }
        $items = $this->items;
        $nueva = (float) $items[$key]['cantidad'] - 1;
        if ($nueva <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['cantidad'] = $nueva;
        }
        $this->items = $items;
    }

    public function actualizarCantidad(string $key, string $val): void
    {
        $cant = max(0.001, round((float) $val, 3));
        if (! isset($this->items[$key])) { return; }
        $items = $this->items;
        $items[$key]['cantidad'] = $cant;
        $this->items = $items;
    }

    public function actualizarPrecio(string $key, string $val): void
    {
        $precio = max(0, round((float) $val, 2));
        if (! isset($this->items[$key])) { return; }
        $items = $this->items;
        $items[$key]['precio'] = $precio;
        // Si tenía precio 0 (cortesía) y se le pone un precio real, ya no es cortesía
        if ($precio > 0 && ($items[$key]['cortesia'] ?? false)) {
            $items[$key]['cortesia'] = false;
        }
        $this->items = $items;
    }

    public function quitarCortesia(string $key): void
    {
        if (! isset($this->items[$key])) { return; }
        $items = $this->items;
        $items[$key]['cortesia'] = false;
        $this->items = $items;
    }

    public function eliminarItem(string $key): void
    {
        $items = $this->items;
        unset($items[$key]);
        $this->items = $items;
    }

    // ── Pagos ─────────────────────────────────────────────────────────────────

    public function agregarPago(): void
    {
        $esTicket = $this->esTiketActual();

        $primerMetodo = collect($this->metodosPago)->first(function ($m) use ($esTicket) {
            $esCredito = ($m['condicion_pago'] ?? 'contado') === 'credito';
            return $esTicket || ! $esCredito;
        });

        $this->pagos[] = [
            'metodo_pago_id' => $primerMetodo['id'] ?? null,
            'monto'          => 0.0,
            'referencia'     => '',
            'condicion_pago' => $primerMetodo['condicion_pago'] ?? 'contado',
        ];
    }

    public function eliminarPago(int $idx): void
    {
        $pagos = $this->pagos;
        array_splice($pagos, $idx, 1);
        $this->pagos = array_values($pagos);
    }

    public function actualizarMetodoPago(int $idx, int $metodoPagoId): void
    {
        $metodo = collect($this->metodosPago)->firstWhere('id', $metodoPagoId);
        if (! $metodo) { return; }

        // Crédito solo para Ticket
        if (($metodo['condicion_pago'] ?? 'contado') === 'credito' && ! $this->esTiketActual()) {
            Notification::make()
                ->title('El crédito solo está disponible para comprobantes tipo Ticket')
                ->warning()
                ->send();
            return;
        }

        $pagos = $this->pagos;
        $pagos[$idx]['metodo_pago_id'] = $metodoPagoId;
        $pagos[$idx]['condicion_pago'] = $metodo['condicion_pago'] ?? 'contado';
        $this->pagos = $pagos;
    }

    public function actualizarMontoPago(int $idx, string $val): void
    {
        $monto = max(0, round((float) $val, 2));
        $pagos = $this->pagos;
        $pagos[$idx]['monto'] = $monto;
        $this->pagos = $pagos;
    }

    public function actualizarReferenciaPago(int $idx, string $val): void
    {
        $pagos = $this->pagos;
        $pagos[$idx]['referencia'] = $val;
        $this->pagos = $pagos;
    }

    // ── Totales ───────────────────────────────────────────────────────────────

    public function getSubtotal(): float
    {
        return round(collect($this->items)->sum(fn ($i) => (float) $i['precio'] * (float) $i['cantidad']), 2);
    }

    public function getDescuento(): float
    {
        return round(max(0, (float) $this->descuentoInput), 2);
    }

    public function getTotal(): float
    {
        return round(max(0, $this->getSubtotal() - $this->getDescuento()), 2);
    }

    public function getTotalPagado(): float
    {
        return round(collect($this->pagos)->sum(fn ($p) => (float) $p['monto']), 2);
    }

    public function getOpGravadas(): float
    {
        if ($this->esTiketActual()) { return 0.0; }
        $igvPct = (float) ($this->venta?->empresa?->igv_porcentaje ?? 18);
        $tasa   = $igvPct / 100;
        return round($this->getTotal() / (1 + $tasa), 2);
    }

    public function getIgv(): float
    {
        if ($this->esTiketActual()) { return 0.0; }
        return round($this->getTotal() - $this->getOpGravadas(), 2);
    }

    public function getVuelto(): float
    {
        return round(max(0.0, $this->getTotalPagado() - $this->getTotal()), 2);
    }

    // ── Guardar ───────────────────────────────────────────────────────────────

    public function guardar(): void
    {
        if (empty($this->items)) {
            Notification::make()->title('Debe haber al menos un producto')->warning()->send();
            return;
        }

        if (! $this->venta) { return; }

        // Verificar cobertura de pagos antes de proceder
        $total   = $this->getTotal();
        $pagado  = $this->getTotalPagado();
        $diff    = round($total - $pagado, 2);

        if ($diff > 0.01) {
            if (empty($this->pagos)) {
                $this->mensajeConfirmacion = "No se ha registrado ningún método de pago para esta venta (total: S/ " . number_format($total, 2) . "). ¿Desea guardar de todas formas?";
            } else {
                $this->mensajeConfirmacion = "Los pagos ingresados (S/ " . number_format($pagado, 2) . ") no cubren el total de la venta (S/ " . number_format($total, 2) . "). Quedaría un saldo pendiente de S/ " . number_format($diff, 2) . ". ¿Desea guardar de todas formas?";
            }
            $this->mostrarConfirmacion = true;
            return;
        }

        $this->ejecutarGuardado();
    }

    public function cancelarConfirmacion(): void
    {
        $this->mostrarConfirmacion = false;
        $this->mensajeConfirmacion = '';
    }

    public function guardarConfirmado(): void
    {
        $this->mostrarConfirmacion = false;
        $this->mensajeConfirmacion = '';
        $this->ejecutarGuardado();
    }

    private function ejecutarGuardado(): void
    {
        if (! $this->venta) { return; }

        $this->guardando = true;

        try {
            $nuevosItems = array_values(array_map(fn ($i) => [
                'tipo'        => $i['tipo'],
                'id'          => $i['id'],
                'nombre'      => $i['nombre'],
                'precio'      => $i['precio'],
                'cantidad'    => $i['cantidad'],
                'costo'       => $i['costo'] ?? 0,
                'cortesia'    => $i['cortesia'] ?? false,
                'producto_id' => $i['producto_id'] ?? null,
                'variante_id' => $i['variante_id'] ?? null,
            ], $this->items));

            $nuevoSerieId = ($this->serieId !== $this->venta->serie_id) ? $this->serieId : null;

            $ventaActualizada = app(VentaService::class)->editar(
                venta:          $this->venta,
                empresaId:      Filament::getTenant()->id,
                clienteId:      $this->clienteId,
                clienteNombre:  $this->clienteNombre ?: $this->clienteBusqueda ?: null,
                clienteTipoDoc: null,
                clienteNumDoc:  null,
                nuevosItems:    $nuevosItems,
                descuento:      $this->getDescuento(),
                pagos:          $this->pagos,
                nuevoSerieId:   $nuevoSerieId,
            );

            $this->emitirFeSiCorresponde($ventaActualizada);

            Notification::make()->title('Venta actualizada correctamente')->success()->send();
            $this->redirect(VentasSesionPage::getUrl(tenant: Filament::getTenant()));
        } catch (\RuntimeException $e) {
            Notification::make()->title('Error al guardar')->body($e->getMessage())->danger()->send();
        } finally {
            $this->guardando = false;
        }
    }

    private function emitirFeSiCorresponde(Venta $venta): void
    {
        $venta->loadMissing(['serie', 'empresa', 'detalles.producto']);

        $tipo = $venta->serie?->tipo;

        if (! in_array($tipo, [TipoComprobante::Boleta, TipoComprobante::Factura])) {
            return;
        }

        $empresa = $venta->empresa;

        if (! $empresa->tieneFacturacionElectronica()) {
            return;
        }

        // Respetar configuración de envío directo igual que el listener VentaCompletada
        $enviarSunat = match ($tipo) {
            TipoComprobante::Boleta  => (bool) $empresa->fe_envio_directo_boleta,
            TipoComprobante::Factura => (bool) $empresa->fe_envio_directo_factura,
        };

        try {
            $response = app(FacturadorService::class)->enviarComprobante($venta, enviarSunat: $enviarSunat);

            $serie       = $venta->serie->serie;
            $correlativo = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
            $base        = "empresas/{$venta->empresa_id}/comprobantes/{$serie}-{$correlativo}";

            if ($response->ok) {
                $pathXml = $pathCdr = null;

                if ($response->xmlBase64) {
                    $pathXml = "{$base}.xml";
                    Storage::disk('local')->put($pathXml, base64_decode($response->xmlBase64));
                }
                if ($response->cdrZip) {
                    $pathCdr = "{$base}-CDR.zip";
                    Storage::disk('local')->put($pathCdr, base64_decode($response->cdrZip));
                }

                if ($enviarSunat) {
                    $venta->update(array_filter([
                        'path_xml'          => $pathXml,
                        'path_cdr_zip'      => $pathCdr,
                        'hash'              => $response->hash,
                        'qr_data'           => $response->qrData,
                        'total_letras'      => $response->totalLetras,
                        'sunat_success'     => true,
                        'estado_sunat'      => EstadoSunat::Aceptado->value,
                        'sunat_codigo'      => $response->sunatCode,
                        'sunat_descripcion' => $response->sunatDescription,
                        'sunat_notas'       => $response->sunatNotes ?: null,
                    ], fn ($v) => $v !== null));
                } else {
                    $venta->update(array_filter([
                        'path_xml'     => $pathXml,
                        'hash'         => $response->hash,
                        'qr_data'      => $response->qrData,
                        'total_letras' => $response->totalLetras,
                        'estado_sunat' => EstadoSunat::PorEnviar->value,
                    ], fn ($v) => $v !== null));
                }
            } else {
                $venta->update([
                    'sunat_success'     => false,
                    'sunat_mensaje'     => $response->mensajeError(),
                    'sunat_descripcion' => $response->mensajeError(),
                    'estado_sunat'      => EstadoSunat::Error->value,
                ]);
            }
        } catch (\Throwable $e) {
            // La venta ya fue guardada — el XML se puede regenerar luego
            \Illuminate\Support\Facades\Log::error('EditarVentaPage: error al emitir FE', [
                'venta_id' => $venta->id,
                'error'    => $e->getMessage(),
            ]);
            $venta->update([
                'sunat_success' => false,
                'sunat_mensaje' => $e->getMessage(),
                'estado_sunat'  => EstadoSunat::Error->value,
            ]);
        }
    }

    // ── Tabla Filament de ítems ───────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => VentaDetalle::query()->where('venta_id', $this->venta?->id ?? 0))
            ->columns([
                TextColumn::make('descripcion')
                    ->label('Producto')
                    ->formatStateUsing(fn ($state) => str_replace(' (Cortesía)', '', $state))
                    ->description(function (VentaDetalle $record) {
                        $key = $this->itemKey($record);
                        $esCortesia = $this->items[$key]['cortesia'] ?? str_contains($record->descripcion, '(Cortesía)');
                        return $esCortesia
                            ? new \Illuminate\Support\HtmlString('<span class="ev-cortesia-badge">🎁 Cortesía &nbsp;<button wire:click="quitarCortesiaDetalle('.$record->id.')" class="ev-cortesia-badge__remove" title="Quitar cortesía">×</button></span>')
                            : null;
                    })
                    ->wrap()
                    ->grow()
                    ->extraCellAttributes(['style' => 'min-width:370px']),

                TextInputColumn::make('precio_unitario')
                    ->label('Precio')
                    ->type('number')
                    ->step('0.01')
                    ->getStateUsing(function (VentaDetalle $record) {
                        $key = $this->itemKey($record);
                        $precio = (float) ($this->items[$key]['precio'] ?? $record->precio_unitario);
                        return number_format($precio, 2, '.', '');
                    })
                    ->extraInputAttributes(['style' => 'text-align:center;'])
                    ->updateStateUsing(function (VentaDetalle $record, $state) {
                        $key = $this->itemKey($record);
                        if (! isset($this->items[$key])) { return; }
                        $precio = max(0.01, round((float) $state, 2));
                        $items = $this->items;
                        $items[$key]['precio'] = $precio;
                        if ($precio > 0 && ($items[$key]['cortesia'] ?? false)) {
                            $items[$key]['cortesia'] = false;
                        }
                        $this->items = $items;
                    }),

                TextInputColumn::make('cantidad')
                    ->label('Cantidad')
                    ->type('number')
                    ->step('1')
                    ->getStateUsing(function (VentaDetalle $record) {
                        $key = $this->itemKey($record);
                        $cantidad = (float) ($this->items[$key]['cantidad'] ?? $record->cantidad);
                        return $cantidad == (int) $cantidad
                            ? (string) (int) $cantidad
                            : rtrim(number_format($cantidad, 3, '.', ''), '0');
                    })
                    ->extraInputAttributes(['style' => 'text-align:center;'])
                    ->updateStateUsing(function (VentaDetalle $record, $state) {
                        $key = $this->itemKey($record);
                        if (! isset($this->items[$key])) { return; }
                        $items = $this->items;
                        $items[$key]['cantidad'] = max(0.001, round((float) $state, 3));
                        $this->items = $items;
                    }),

                TextColumn::make('total_calculado')
                    ->label('Subtotal')
                    ->getStateUsing(function (VentaDetalle $record) {
                        $key = $this->itemKey($record);
                        $precio   = (float) ($this->items[$key]['precio']   ?? $record->precio_unitario);
                        $cantidad = (float) ($this->items[$key]['cantidad'] ?? $record->cantidad);
                        return number_format($precio * $cantidad, 2);
                    })
                    ->prefix('S/ ')
                    ->alignEnd()
                    ->fontFamily('mono'),
            ])
            ->recordActions([
                FilamentAction::make('eliminar')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->iconButton()
                    ->requiresConfirmation(false)
                    ->action(function (VentaDetalle $record) {
                        $record->delete();
                        $this->recargarItems();
                    }),
            ])
            ->paginated(false)
            ->striped(false)
            ->emptyStateHeading('Sin productos')
            ->emptyStateDescription('Usa el buscador de arriba para agregar productos.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    private function itemKey(VentaDetalle $det): string
    {
        $tipo = $det->tipo_item instanceof \App\Enums\TipoItem
            ? $det->tipo_item->value
            : (string) $det->tipo_item;
        return match ($tipo) {
            'variante'  => "v:{$det->variante_id}",
            'promocion' => "promo:{$det->promocion_id}",
            default     => "p:{$det->producto_id}",
        };
    }

    public function recargarItems(): void
    {
        if (! $this->venta) { return; }

        $this->items = [];
        foreach (VentaDetalle::where('venta_id', $this->venta->id)->get() as $det) {
            $tipo = $det->tipo_item instanceof \App\Enums\TipoItem
                ? $det->tipo_item->value
                : (string) $det->tipo_item;
            $key = match ($tipo) {
                'variante'  => "v:{$det->variante_id}",
                'promocion' => "promo:{$det->promocion_id}",
                default     => "p:{$det->producto_id}",
            };
            $this->items[$key] = [
                'key'         => $key,
                'tipo'        => $tipo,
                'id'          => $tipo === 'variante' ? $det->variante_id : ($tipo === 'promocion' ? $det->promocion_id : $det->producto_id),
                'nombre'      => str_replace(' (Cortesía)', '', $det->descripcion),
                'precio'      => (float) $det->precio_unitario,
                'cantidad'    => (float) $det->cantidad,
                'costo'       => (float) $det->costo_unitario,
                'cortesia'    => str_contains($det->descripcion, '(Cortesía)'),
                'producto_id' => $det->producto_id,
                'variante_id' => $det->variante_id,
            ];
        }
    }

    public function quitarCortesiaDetalle(int $detalleId): void
    {
        $det = VentaDetalle::find($detalleId);
        if (! $det) { return; }

        $tipo = $det->tipo_item instanceof \App\Enums\TipoItem
            ? $det->tipo_item->value
            : (string) $det->tipo_item;

        $precioRestaurado = match ($tipo) {
            'variante' => (float) (\App\Models\Variante::find($det->variante_id)?->precio_final ?? $det->precio_unitario),
            default    => (float) (\App\Models\Producto::find($det->producto_id)?->precio_venta ?? $det->precio_unitario),
        };

        $precioFinal = max(0.01, $precioRestaurado);

        $key = $this->itemKey($det);
        if (isset($this->items[$key])) {
            $items = $this->items;
            $items[$key]['cortesia'] = false;
            $items[$key]['precio']   = $precioFinal;
            $this->items = $items;
        }
    }

    public function cancelar(): void
    {
        $this->redirect(VentasSesionPage::getUrl(tenant: Filament::getTenant()));
    }
}
