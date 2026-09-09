<?php

namespace App\Filament\Pdv\Resources\OrdenRest\Pages;

use App\Enums\EstadoOrden;
use App\Enums\TipoItem;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Pages\MapaMesasPage;
use App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Services\ImpresionDirectaService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class EditPedido extends EditRecord
{
    use HasFullWidthPage;

    protected static string $resource = OrdenRestResource::class;

    protected string $view = 'filament.pdv.pages.edit-pedido';

    // ── Estado ────────────────────────────────────────────────────────────────

    /** Detalles eliminados en sesión: nombre, cantidad, produccion_id, produccion_nombre */
    public array $eliminadosEnSesion = [];

    /** Redirigir al mapa cuando el ComandaModal cierre (caso: anular pedido) */
    public bool $pendingRedirectAfterComanda = false;

    /** Última comanda enviada — para el botón Reenviar */
    public array $lastComandaData = [];

    // ── Filament override ─────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    // ── Datos para la vista ───────────────────────────────────────────────────

    public function getOrden(): \App\Models\Orden
    {
        return $this->record->load(['detalles.producto', 'detalles.variante', 'mesa.piso', 'vendedor']);
    }

    public function getCarritoResumen(): array
    {
        $resumen = [];
        foreach ($this->record->detalles as $det) {
            if ($det->promocion_id) {
                $key = "promocion_{$det->promocion_id}";
            } elseif ($det->variante_id) {
                $key = "variante_{$det->variante_id}";
            } elseif ($det->producto_id) {
                $key = "producto_{$det->producto_id}";
            } else {
                continue;
            }
            $resumen[$key] = ($resumen[$key] ?? 0) + (float) $det->cantidad;
        }
        return $resumen;
    }

    public function hayItemsNuevos(): bool
    {
        return $this->record->detalles()->where('enviado_cocina', false)->exists();
    }

    public function hayItemsEliminados(): bool
    {
        return ! empty($this->eliminadosEnSesion);
    }

    // ── Agregar producto (evento del ProductCatalog) ──────────────────────────

    #[On('product-selected')]
    public function agregarProducto(array $payload): void
    {
        $orden = $this->record;

        if ($orden->estado === EstadoOrden::PagoConfirmado) {
            Notification::make()->title('Pedido ya cobrado')->danger()->send();
            return;
        }

        $tipo       = $payload['tipo'];
        $id         = (int) $payload['id'];
        $nombre     = $payload['nombre'];
        $precio     = (float) $payload['precio'];
        $cantidad   = (float) ($payload['cantidad'] ?? 1);
        $productoId = (int) ($payload['producto_id'] ?? $id);

        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        // Buscar costo (promociones no tienen costo propio; lo maneja VentaService en el cobro)
        $costo = 0.0;
        if ($tipo === 'producto') {
            $prod  = Producto::find($id);
            $costo = (float) ($prod?->precio_costo ?? 0);
        } elseif ($tipo === 'variante') {
            $var   = \App\Models\Variante::find($id);
            $costo = (float) ($var?->costo ?? $var?->producto?->precio_costo ?? 0);
        }

        $tipoItem = match ($tipo) {
            'variante'  => TipoItem::Variante,
            'promocion' => TipoItem::Promocion,
            default     => TipoItem::Producto,
        };

        // Buscar detalle existente no enviado del mismo ítem
        $existente = $orden->detalles()
            ->where('enviado_cocina', false)
            ->when($tipo === 'producto',  fn ($q) => $q->where('producto_id', $id)->whereNull('variante_id')->whereNull('promocion_id'))
            ->when($tipo === 'variante',  fn ($q) => $q->where('variante_id', $id))
            ->when($tipo === 'promocion', fn ($q) => $q->where('promocion_id', $id))
            ->first();

        if ($existente) {
            $nuevaCantidad = (float) $existente->cantidad + $cantidad;
            $calc = OrdenDetalle::calcular($nuevaCantidad, $precio, $costo, 0, $igvRate);
            $existente->update([
                'cantidad'       => $nuevaCantidad,
                'valor_unitario' => $calc['valorUnitario'],
                'subtotal'       => $calc['subtotal'],
                'igv'            => $calc['igv'],
                'total'          => $calc['total'],
                'costo_total'    => $calc['costoTotal'],
            ]);
        } else {
            $calc = OrdenDetalle::calcular($cantidad, $precio, $costo, 0, $igvRate);
            $orden->detalles()->create([
                'tipo_item'       => $tipoItem,
                'producto_id'     => $tipo === 'producto' ? $id : ($tipo === 'variante' ? $productoId : null),
                'variante_id'     => $tipo === 'variante'  ? $id : null,
                'promocion_id'    => $tipo === 'promocion' ? $id : null,
                'descripcion'     => $nombre,
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio,
                'valor_unitario'  => $calc['valorUnitario'],
                'costo_unitario'  => $costo,
                'descuento'       => 0,
                'subtotal'        => $calc['subtotal'],
                'igv'             => $calc['igv'],
                'total'           => $calc['total'],
                'costo_total'     => $calc['costoTotal'],
                'enviado_cocina'  => false,
            ]);
        }

        $orden->recalcularTotales();
        $this->record->refresh();
    }

    // ── Gestión de items ──────────────────────────────────────────────────────

    public function incrementar(int $detalleId): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->findOrFail($detalleId);
        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        $nuevaCantidad = (float) $detalle->cantidad + 1;
        $calc = OrdenDetalle::calcular($nuevaCantidad, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'       => $nuevaCantidad,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'       => $calc['subtotal'],
            'igv'            => $calc['igv'],
            'total'          => $calc['total'],
            'costo_total'    => $calc['costoTotal'],
        ]);

        $this->record->recalcularTotales();
        $this->record->refresh();
    }

    public function decrementar(int $detalleId): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->findOrFail($detalleId);

        if ((float) $detalle->cantidad <= 1) {
            $this->eliminarItem($detalleId);
            return;
        }

        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        $nuevaCantidad = (float) $detalle->cantidad - 1;
        $calc = OrdenDetalle::calcular($nuevaCantidad, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'       => $nuevaCantidad,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'       => $calc['subtotal'],
            'igv'            => $calc['igv'],
            'total'          => $calc['total'],
            'costo_total'    => $calc['costoTotal'],
        ]);

        $this->record->recalcularTotales();
        $this->record->refresh();
    }

    public function eliminarItem(int $detalleId): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->find($detalleId);

        if (! $detalle) return;

        // Si ya fue enviado a cocina → agregar a lista de eliminados para la comanda
        if ($detalle->enviado_cocina) {
            $detalle->loadMissing('producto.produccion');
            $this->eliminadosEnSesion[] = [
                'nombre'           => $detalle->descripcion ?? $detalle->producto?->nombre ?? '—',
                'cantidad'         => (int) $detalle->cantidad,
                'produccion_id'    => $detalle->producto?->produccion?->id,
                'produccion_nombre' => $detalle->producto?->produccion?->nombre,
            ];
        }

        $detalle->delete();
        $this->record->recalcularTotales();
        $this->record->refresh();
    }

    public function setCantidadDetalle(int $detalleId, float $qty): void
    {
        $qty     = max(0.01, $qty);
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->findOrFail($detalleId);
        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        $calc = OrdenDetalle::calcular($qty, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'       => $qty,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'       => $calc['subtotal'],
            'igv'            => $calc['igv'],
            'total'          => $calc['total'],
            'costo_total'    => $calc['costoTotal'],
        ]);

        $this->record->recalcularTotales();
        $this->record->refresh();
    }

    // ── Enviar actualización a cocina ─────────────────────────────────────────

    public function enviarActualizacion(): void
    {
        $orden   = $this->record;
        $empresa = Filament::getTenant();

        $tieneNuevos    = $orden->detalles()->where('enviado_cocina', false)->exists();
        $tieneEliminados = ! empty($this->eliminadosEnSesion);

        if (! $tieneNuevos && ! $tieneEliminados) {
            Notification::make()->title('No hay cambios para enviar')->warning()->send();
            return;
        }

        $config = $empresa->cachedConfigImpresion();

        if ($config['tiene_impresion_directa']) {
            // Enviar productos nuevos
            if ($tieneNuevos) {
                app(ImpresionDirectaService::class)->imprimirComandaOrden($orden, $empresa);
                $orden->detalles()->where('enviado_cocina', false)->update(['enviado_cocina' => true]);
            }

            // Enviar productos eliminados
            if ($tieneEliminados) {
                app(ImpresionDirectaService::class)->imprimirEliminados($orden, $empresa, $this->eliminadosEnSesion);
                $this->eliminadosEnSesion = [];
            }

            Notification::make()->title('Actualización enviada a cocina ✓')->success()->send();
        } else {
            // Agrupar por área ANTES de marcar/limpiar para la comanda browser
            $itemsPorArea = [];

            if ($tieneNuevos) {
                $nuevosDets = $orden->detalles()
                    ->where('enviado_cocina', false)
                    ->with('producto.produccion')
                    ->get();

                foreach ($nuevosDets as $d) {
                    $produccion = $d->producto?->produccion;
                    if (! $produccion) continue;
                    $key    = 'a' . $produccion->id;
                    $nombre = $produccion->nombre;
                    if (! isset($itemsPorArea[$key])) {
                        $itemsPorArea[$key] = ['nombre' => $nombre, 'nuevos' => [], 'cancelados' => []];
                    }
                    $itemsPorArea[$key]['nuevos'][] = [
                        'cant'   => (int) $d->cantidad,
                        'nombre' => $d->descripcion ?? '—',
                        'nota'   => $d->notas_item ?? '',
                    ];
                }
                $orden->detalles()->where('enviado_cocina', false)->update(['enviado_cocina' => true]);
            }

            foreach ($this->eliminadosEnSesion as $item) {
                $pid = $item['produccion_id'] ?? null;
                if (! $pid) continue;
                $key    = 'a' . $pid;
                $nombre = $item['produccion_nombre'] ?: 'Área';
                if (! isset($itemsPorArea[$key])) {
                    $itemsPorArea[$key] = ['nombre' => $nombre, 'nuevos' => [], 'cancelados' => []];
                }
                $itemsPorArea[$key]['cancelados'][] = [
                    'cant'   => $item['cantidad'],
                    'nombre' => $item['nombre'],
                    'nota'   => '',
                ];
            }
            $this->eliminadosEnSesion = [];

            $areas = array_values($itemsPorArea);
            if (! empty($areas)) {
                $this->lastComandaData = [
                    'ordenId'   => $orden->id,
                    'areasJson' => json_encode($areas),
                    'mesa'      => $orden->mesa?->nombre ?? '',
                    'cajero'    => auth()->user()->name,
                    'parcial'   => true,
                ];
                $this->dispatch('cerrar-carrito');
                $this->dispatch('imprimir-comanda-browser',
                    ordenId:   $orden->id,
                    areasJson: json_encode($areas),
                    mesa:      $orden->mesa?->nombre ?? '',
                    cajero:    auth()->user()->name,
                    parcial:   true,
                );
            }

            Notification::make()->title('Pedido actualizado')->success()->send();
        }

        $this->record->refresh();
    }

    // ── Anular pedido ─────────────────────────────────────────────────────────

    public function anularPedido(): void
    {
        $orden   = $this->record;
        $empresa = Filament::getTenant();

        // Agrupar por área ANTES de cancelar (solo productos con área asignada)
        $orden->loadMissing(['detalles.producto.produccion']);
        $itemsPorArea = [];
        foreach ($orden->detalles as $d) {
            $produccion = $d->producto?->produccion;
            if (! $produccion) continue;
            $key    = 'a' . $produccion->id;
            $nombre = $produccion->nombre;
            if (! isset($itemsPorArea[$key])) {
                $itemsPorArea[$key] = ['nombre' => $nombre, 'nuevos' => [], 'cancelados' => []];
            }
            $itemsPorArea[$key]['cancelados'][] = [
                'cant'   => (int) $d->cantidad,
                'nombre' => $d->descripcion ?? '—',
                'nota'   => '',
            ];
        }
        $areas = array_values($itemsPorArea);

        $orden->update(['estado' => EstadoOrden::Cancelada->value]);
        $orden->mesa?->marcarLibre();

        Notification::make()->title('Pedido anulado')->success()->send();

        $config = $empresa->cachedConfigImpresion();
        if (! $config['tiene_impresion_directa'] && ! empty($areas)) {
            $this->pendingRedirectAfterComanda = true;
            $this->lastComandaData = [
                'ordenId'   => $orden->id,
                'areasJson' => json_encode($areas),
                'mesa'      => $orden->mesa?->nombre ?? '',
                'cajero'    => auth()->user()->name,
                'parcial'   => false,
            ];
            $this->dispatch('imprimir-comanda-browser',
                ordenId:   $orden->id,
                areasJson: json_encode($areas),
                mesa:      $orden->mesa?->nombre ?? '',
                cajero:    auth()->user()->name,
                parcial:   false,
            );
        } else {
            $this->redirect(MapaMesasPage::getUrl(tenant: $empresa));
        }
    }

    public function reenviarComanda(): void
    {
        if (empty($this->lastComandaData)) return;
        $d = $this->lastComandaData;
        $this->dispatch('imprimir-comanda-browser',
            ordenId:   $d['ordenId'],
            areasJson: $d['areasJson'],
            mesa:      $d['mesa'],
            cajero:    $d['cajero'],
            parcial:   $d['parcial'],
        );
    }

    public function cerrarComanda(): void
    {
        if ($this->pendingRedirectAfterComanda) {
            $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
        }
    }

    #[On('comanda-modal-cerrada')]
    public function onComandaModalCerrada(): void
    {
        if ($this->pendingRedirectAfterComanda) {
            $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
        }
    }

    // ── Cobrar ────────────────────────────────────────────────────────────────

    public function irACobrar(): void
    {
        $this->redirect(
            OrdenRestResource::getUrl('cobrar', ['record' => $this->record->id], tenant: Filament::getTenant())
        );
    }

    public function volverAlMapa(): void
    {
        $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
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
