<?php

namespace App\Filament\Pdv\Resources\OrdenRest\Pages;

use App\Enums\EstadoOrden;
use App\Enums\EstadoSesion;
use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Events\VentaCompletada;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Pages\MapaMesasPage;
use App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource;
use App\Models\Cliente;
use Livewire\Attributes\On;
use App\Models\MetodoPago;
use App\Models\Serie;
use App\Models\SesionCaja;
use App\Models\Venta;
use App\Services\ImpresionDirectaService;
use App\Services\VentaService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use App\Models\Orden;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CobrarPedido extends Page
{
    use HasFullWidthPage;

    protected static string $resource = OrdenRestResource::class;

    protected string $view = 'filament.pdv.pages.cobrar-pedido';

    // ── Record ────────────────────────────────────────────────────────────────

    public ?Orden $orden = null;

    // ── Comprobante ───────────────────────────────────────────────────────────

    public ?int    $serieId         = null;
    public ?string $tipoComprobante = null;

    // ── Cliente ───────────────────────────────────────────────────────────────

    public ?int    $clienteId          = null;
    public ?string $clienteNombre      = null;
    public ?string $clienteTipoDoc     = null;
    public string  $clienteBusqueda    = '';
    public bool    $mostrarSugerencias = false;


    // ── Pago ──────────────────────────────────────────────────────────────────

    public ?int   $metodoPagoId   = null;
    public string $montoPagoInput = '';
    public string $pagoReferencia = '';
    public string $descuentoInput = '0';
    public array  $pagosAgregados = [];


    public function mount(int|string $record): void
    {
        $this->orden = Orden::where('empresa_id', \Filament\Facades\Filament::getTenant()->id)
            ->with(['detalles', 'mesa.piso'])
            ->findOrFail($record);

        if ($this->orden->estado === EstadoOrden::PagoConfirmado) {
            Notification::make()->title('Pedido ya cobrado')->warning()->send();
            $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
            return;
        }

        if ($this->orden->detalles->isEmpty()) {
            Notification::make()->title('El pedido está vacío')->warning()->send();
            $this->redirect(OrdenRestResource::getUrl('edit', ['record' => $this->orden->id], tenant: Filament::getTenant()));
            return;
        }

        $this->autoSeleccionarComprobante();
        $this->autoSeleccionarClienteGeneral();
        $this->montoPagoInput  = number_format((float) $this->orden->total, 2, '.', '');
        $this->metodoPagoId    = $this->getMetodosPago()->first()?->id;
    }

    // ── Series / Métodos de pago ──────────────────────────────────────────────

    public function getSeries(): Collection
    {
        return Serie::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', true)
            ->whereIn('tipo', [
                TipoComprobante::Ticket->value,
                TipoComprobante::Boleta->value,
                TipoComprobante::Factura->value,
            ])
            ->orderByRaw("FIELD(tipo, 'ticket', 'boleta', 'factura')")
            ->get();
    }

    public function getMetodosPago(): Collection
    {
        return MetodoPago::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get();
    }

    private function autoSeleccionarComprobante(): void
    {
        foreach ([TipoComprobante::Ticket->value, TipoComprobante::Boleta->value, TipoComprobante::Factura->value] as $tipo) {
            $serie = $this->getSeries()->firstWhere('tipo.value', $tipo);
            if ($serie) {
                $this->tipoComprobante = $tipo;
                $this->serieId         = $serie->id;
                return;
            }
        }
    }

    private function autoSeleccionarClienteGeneral(): void
    {
        $cliente = Cliente::where('empresa_id', Filament::getTenant()->id)
            ->where('numero_documento', '99999999')
            ->first();

        if ($cliente) {
            $this->clienteId       = $cliente->id;
            $this->clienteNombre   = $cliente->nombre_completo;
            $this->clienteTipoDoc  = $cliente->tipo_documento->value;
            $this->clienteBusqueda = $cliente->nombre_completo;
        }
    }

    public function seleccionarComprobante(string $tipo): void
    {
        $serie = $this->getSeries()->firstWhere('tipo.value', $tipo);
        if ($serie) {
            $this->tipoComprobante = $tipo;
            $this->serieId         = $serie->id;
        }
    }

    // ── Cliente ───────────────────────────────────────────────────────────────

    public function updatedClienteBusqueda(): void
    {
        $this->mostrarSugerencias = strlen($this->clienteBusqueda) >= 2;
        if ($this->clienteId && $this->clienteBusqueda !== $this->clienteNombre) {
            $this->clienteId      = null;
            $this->clienteNombre  = null;
            $this->clienteTipoDoc = null;
        }
    }

    public function getClientesSugeridos(): Collection
    {
        if (strlen($this->clienteBusqueda) < 2) return collect();

        return Cliente::where('empresa_id', Filament::getTenant()->id)
            ->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->clienteBusqueda}%")
                  ->orWhere('apellidos', 'like', "%{$this->clienteBusqueda}%")
                  ->orWhere('numero_documento', 'like', "%{$this->clienteBusqueda}%");
            })
            ->limit(8)
            ->get();
    }

    public function seleccionarCliente(int $id): void
    {
        $cliente = Cliente::find($id);
        if (! $cliente) return;

        $this->clienteId       = $id;
        $this->clienteNombre   = $cliente->nombre_completo;
        $this->clienteTipoDoc  = $cliente->tipo_documento->value;
        $this->clienteBusqueda = $cliente->nombre_completo;
        $this->mostrarSugerencias = false;

        if ($cliente->tipo_documento === TipoDocumento::RUC) {
            $this->seleccionarComprobante(TipoComprobante::Factura->value);
        }
    }

    public function limpiarCliente(): void
    {
        $this->clienteId          = null;
        $this->clienteNombre      = null;
        $this->clienteTipoDoc     = null;
        $this->clienteBusqueda    = '';
        $this->mostrarSugerencias = false;
    }

    // ── Modal: nuevo cliente (delegado a NuevoClienteModal) ─────────────────

    public function abrirModalNuevoCliente(): void
    {
        $this->dispatch('abrir-modal-nuevo-cliente');
    }

    #[On('cliente-creado')]
    public function onClienteCreado(int $id): void
    {
        $this->seleccionarCliente($id);
    }

    // ── Descuento ────────────────────────────────────────────────────────────

    public function getDescuento(): float
    {
        return max(0, min((float) $this->descuentoInput, (float) $this->orden->total));
    }

    public function getTotalConDescuento(): float
    {
        return max(0, (float) $this->orden->total - $this->getDescuento());
    }

    // ── Pagos múltiples ───────────────────────────────────────────────────────

    public function agregarPago(): void
    {
        $monto = (float) $this->montoPagoInput;
        if ($monto <= 0 || ! $this->metodoPagoId) return;

        $totalAcumulado = collect($this->pagosAgregados)->sum('monto');
        $pendiente      = max(0, $this->getTotalConDescuento() - $totalAcumulado);

        if ($monto > $pendiente + 0.01) {
            $monto = $pendiente;
        }

        if ($monto <= 0) return;

        $metodo = MetodoPago::find($this->metodoPagoId);

        $this->pagosAgregados[] = [
            'metodo_pago_id' => $this->metodoPagoId,
            'nombre'         => $metodo?->nombre ?? 'Pago',
            'monto'          => $monto,
            'referencia'     => $this->pagoReferencia,
        ];

        $this->montoPagoInput = number_format(max(0, $pendiente - $monto), 2, '.', '');
        $this->pagoReferencia = '';
    }

    public function eliminarPago(int $index): void
    {
        $pagos = $this->pagosAgregados;
        unset($pagos[$index]);
        $this->pagosAgregados = array_values($pagos);
    }

    public function getTotalPagado(): float
    {
        return collect($this->pagosAgregados)->sum('monto') + (float) $this->montoPagoInput;
    }

    public function getVuelto(): float
    {
        return max(0, $this->getTotalPagado() - $this->getTotalConDescuento());
    }

    // ── Procesar cobro ────────────────────────────────────────────────────────

    public function procesarCobro(): void
    {
        $this->validate([
            'serieId' => 'required|integer',
        ], ['serieId.required' => 'Selecciona un comprobante.']);

        if ($this->tipoComprobante === TipoComprobante::Factura->value && $this->clienteTipoDoc !== 'ruc') {
            Notification::make()
                ->title('Cliente con RUC requerido')
                ->body('Para emitir factura el cliente debe tener tipo de documento RUC.')
                ->warning()
                ->send();
            return;
        }

        $orden     = $this->orden;
        $empresa   = Filament::getTenant();
        $empresaId = $empresa->id;

        $totalDescuento = $this->getTotalConDescuento();
        $pagosLista     = $this->pagosAgregados;

        // Si no hay pagos agregados, usar el input actual como único pago
        if (empty($pagosLista)) {
            $montoPago = (float) $this->montoPagoInput;
            if (! $this->metodoPagoId) {
                Notification::make()->title('Selecciona un método de pago')->warning()->send();
                return;
            }
            if ($montoPago < $totalDescuento - 0.01) {
                Notification::make()
                    ->title('Monto insuficiente')
                    ->body('Total: S/ ' . number_format($totalDescuento, 2))
                    ->warning()
                    ->send();
                return;
            }
            $pagosLista = [[
                'metodo_pago_id' => $this->metodoPagoId,
                'monto'          => min($montoPago, $totalDescuento),
                'referencia'     => $this->pagoReferencia,
                'condicion_pago' => 'contado',
            ]];
        } else {
            $totalPagado = collect($pagosLista)->sum('monto');
            if ($totalPagado < $totalDescuento - 0.01) {
                Notification::make()
                    ->title('Monto insuficiente')
                    ->body('Total: S/ ' . number_format($totalDescuento, 2) . ' — Pagado: S/ ' . number_format($totalPagado, 2))
                    ->warning()
                    ->send();
                return;
            }
        }

        $sesionActiva = SesionCaja::where('empresa_id', $empresaId)
            ->where('user_id', auth()->user()?->getAuthIdentifier())
            ->where('estado', EstadoSesion::Abierta->value)
            ->exists();

        if (! $sesionActiva) {
            Notification::make()->title('Sin sesión de caja activa')->warning()->send();
            return;
        }

        $venta      = null;
        $mesaNombre = $orden->mesa?->nombre ?? '—';

        try {
            DB::transaction(function () use ($orden, $empresa, $empresaId, $pagosLista, &$venta) {
                $orden->loadMissing(['detalles.producto.unidadMedida', 'detalles.variante.producto.unidadMedida', 'mesa']);

                // Normalizar ítems del pedido al formato que acepta VentaService
                $items = [];
                foreach ($orden->detalles as $det) {
                    if ($det->promocion_id) {
                        $items[] = [
                            'tipo'     => 'promocion',
                            'id'       => $det->promocion_id,
                            'nombre'   => $det->descripcion,
                            'precio'   => (float) $det->precio_unitario,
                            'cantidad' => (float) $det->cantidad,
                            'costo'    => 0.0,
                            'cortesia' => false,
                        ];
                    } elseif ($det->variante_id) {
                        $items[] = [
                            'tipo'        => 'variante',
                            'id'          => $det->variante_id,
                            'nombre'      => $det->descripcion,
                            'precio'      => (float) $det->precio_unitario,
                            'cantidad'    => (float) $det->cantidad,
                            'costo'       => (float) ($det->costo_unitario ?? 0),
                            'cortesia'    => false,
                            'producto_id' => $det->producto_id,
                        ];
                    } else {
                        $items[] = [
                            'tipo'        => 'producto',
                            'id'          => $det->producto_id,
                            'nombre'      => $det->descripcion,
                            'precio'      => (float) $det->precio_unitario,
                            'cantidad'    => (float) $det->cantidad,
                            'costo'       => (float) ($det->costo_unitario ?? 0),
                            'cortesia'    => false,
                            'producto_id' => $det->producto_id,
                        ];
                    }
                }

                $venta = app(VentaService::class)->procesar(
                    empresaId:         $empresaId,
                    serieId:           $this->serieId,
                    clienteId:         $this->clienteId,
                    clienteNombre:     $this->clienteNombre ?? ($orden->cliente_nombre ?? 'Cliente General'),
                    clienteTipoDoc:    $this->clienteTipoDoc,
                    items:             $items,
                    pagos:             $pagosLista,
                    descuento:         $this->getDescuento(),
                    despachoRequerido: false,
                    despachoDireccion: '',
                    conceptoPrefix:    'Mesa ' . ($orden->mesa?->nombre ?? '—'),
                    igvPct:            (float) ($empresa->igv_porcentaje ?? 18),
                );

                // Post-procesado propio del módulo restaurante
                $orden->update([
                    'venta_id' => $venta->id,
                    'estado'   => EstadoOrden::PagoConfirmado,
                ]);
                $orden->mesa?->marcarLibre();
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === '__SIN_SESION__') {
                Notification::make()->title('Sin sesión de caja activa')->warning()->send();
                return;
            }
            Notification::make()
                ->title('Error al procesar el cobro')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        // Emisión electrónica (fuera de la transacción)
        if ($venta) {
            $venta->loadMissing('serie');
            if (in_array($venta->serie?->tipo, [TipoComprobante::Boleta, TipoComprobante::Factura])) {
                VentaCompletada::dispatch($venta);
            }

            $ventaNumero = ($venta->serie?->serie ?? '---') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
            $shareUrl    = URL::temporarySignedRoute(
                'pdv.ticket.venta.compartir',
                now()->addHours(24),
                ['id' => $venta->id]
            );

            $imprimioDirecto = false;
            try {
                $imprimioDirecto = app(ImpresionDirectaService::class)->imprimirComprobante($venta, $empresa);
            } catch (\Throwable) {}

            Notification::make()
                ->title('Cobro realizado ✓')
                ->body("Mesa {$mesaNombre} liberada.")
                ->success()
                ->send();

            $this->dispatch('abrir-modal-impresion',
                ventaId:      $venta->id,
                ventaNumero:  $ventaNumero,
                ventaTotal:   (float) $venta->total,
                autoImprimir: ! $imprimioDirecto,
                shareUrl:     $shareUrl,
            );
            return;
        }

        $this->redirect(MapaMesasPage::getUrl(tenant: $empresa), navigate: true);
    }

    public function volverAlPedido(): void
    {
        $this->redirect(
            OrdenRestResource::getUrl('edit', ['record' => $this->orden->id], tenant: Filament::getTenant())
        );
    }

    protected function getViewData(): array
    {
        return ['record' => $this->orden];
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
