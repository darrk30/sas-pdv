<?php

namespace App\Filament\Pdv\Resources\OrdenRest\Pages;

use App\Enums\EstadoOrden;
use App\Enums\TipoItem;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Pages\MapaMesasPage;
use App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource;
use App\Filament\Pdv\Resources\Ordenes\Concerns\ValidaStockOrden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Services\ImpresionDirectaService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class EditPedido extends EditRecord
{
    use HasFullWidthPage, ValidaStockOrden;

    protected static string $resource = OrdenRestResource::class;

    protected string $view = 'filament.pdv.pages.edit-pedido';

    // ── Estado ────────────────────────────────────────────────────────────────

    /** Detalles eliminados en sesión: nombre, cantidad, produccion_id, produccion_nombre */
    public array $eliminadosEnSesion = [];

    /** Detalles cuya nota fue modificada en sesión (solo los ya enviados a cocina): [detalleId => true] */
    public array $notasModificadas = [];

    /** Nuevos ítems pendientes — no persistidos en BD hasta enviarActualizacion() */
    public array $carritoNuevos = [];

    /** Flag reactivo para Alpine ($wire.hayCambiosPendientes) — se mantiene sincronizado */
    public bool $hayCambiosPendientes = false;

    /** Redirigir al mapa cuando el ComandaModal cierre (caso: anular pedido) */
    public bool $pendingRedirectAfterComanda = false;

    /** URL expuesta para que Alpine navegue directo sin re-render Livewire */
    public string $comandaRedirectUrl = '';

    /** Última comanda enviada — para el botón Reenviar */
    public array $lastComandaData = [];

    // ── Filament override ─────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return parent::canAccess($parameters) && (auth()->user()?->can('restaurante.pedido.editar') ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    // ── Helper: sincronizar flag Alpine ──────────────────────────────────────

    private function actualizarHayCambios(): void
    {
        $this->hayCambiosPendientes =
            ! empty($this->carritoNuevos) ||
            ! empty($this->eliminadosEnSesion) ||
            ! empty($this->notasModificadas) ||
            $this->record->detalles()->where('enviado_cocina', false)->exists();
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
        foreach ($this->carritoNuevos as $key => $item) {
            $resumen[$key] = ($resumen[$key] ?? 0) + $item['cantidad'];
        }
        return $resumen;
    }

    public function getPendienteResumen(): array
    {
        $resumen = [];
        foreach ($this->carritoNuevos as $item) {
            $rKey = "{$item['tipo']}_{$item['id']}";
            $resumen[$rKey] = ($resumen[$rKey] ?? 0) + $item['cantidad'];

            // Expandir componentes de promo para que las tarjetas de producto
            // también reflejen el stock consumido por promos pendientes
            if ($item['tipo'] === 'promocion' && ! empty($item['detalles_resumen'])) {
                foreach ($item['detalles_resumen'] as $d) {
                    if (! empty($d['variante_id'])) {
                        $k = "variante_{$d['variante_id']}";
                        $resumen[$k] = ($resumen[$k] ?? 0) + ($d['cantidad'] * $item['cantidad']);
                    } elseif (! empty($d['producto_id'])) {
                        $k = "producto_{$d['producto_id']}";
                        $resumen[$k] = ($resumen[$k] ?? 0) + ($d['cantidad'] * $item['cantidad']);
                    }
                }
            }
        }
        return $resumen;
    }

    public function hayItemsNuevos(): bool
    {
        return ! empty($this->carritoNuevos)
            || $this->record->detalles()->where('enviado_cocina', false)->exists();
    }

    public function hayItemsEliminados(): bool
    {
        return ! empty($this->eliminadosEnSesion);
    }

    public function hayNotasModificadas(): bool
    {
        return ! empty($this->notasModificadas);
    }

    // ── Agregar producto (evento del ProductCatalog) ──────────────────────────

    #[On('product-selected')]
    public function agregarProducto(array $payload): void
    {
        if ($this->record->estado === EstadoOrden::PagoConfirmado) {
            Notification::make()->title('Pedido ya cobrado')->danger()->send();
            return;
        }

        $tipo   = $payload['tipo'];
        $id     = (int) $payload['id'];
        $nombre = $payload['nombre'];
        $precio = (float) $payload['precio'];

        $key = match ($tipo) {
            'variante'  => "variante_{$id}",
            'promocion' => "promocion_{$id}",
            default     => "producto_{$id}",
        };

        if (isset($this->carritoNuevos[$key])) {
            $item             = $this->carritoNuevos[$key];
            $item['cantidad'] += 1;
            $this->carritoNuevos[$key] = $item;
        } else {
            $this->carritoNuevos[$key] = [
                'tipo'             => $tipo,
                'id'               => $id,
                'nombre'           => $nombre,
                'precio'           => $precio,
                'cantidad'         => (float) ($payload['cantidad'] ?? 1),
                'nota'             => '',
                'producto_id'      => (int) ($payload['producto_id'] ?? $id),
                'detalles_resumen' => $payload['detalles_resumen'] ?? [],
            ];
        }
        $this->hayCambiosPendientes = true;
    }

    public function incrementarNuevo(string $key): void
    {
        if (! isset($this->carritoNuevos[$key])) return;
        $item             = $this->carritoNuevos[$key];
        $item['cantidad'] += 1;
        $this->carritoNuevos[$key] = $item;
        $this->hayCambiosPendientes = true;
    }

    public function decrementarNuevo(string $key): void
    {
        if (! isset($this->carritoNuevos[$key])) return;
        if ($this->carritoNuevos[$key]['cantidad'] <= 1) {
            $this->eliminarNuevo($key);
            return;
        }
        $item             = $this->carritoNuevos[$key];
        $item['cantidad'] -= 1;
        $this->carritoNuevos[$key] = $item;
        $this->hayCambiosPendientes = true;
    }

    public function eliminarNuevo(string $key): void
    {
        unset($this->carritoNuevos[$key]);
        $this->actualizarHayCambios();
    }

    public function setNotaNuevo(string $key, string $nota): void
    {
        if (! isset($this->carritoNuevos[$key])) return;
        $item         = $this->carritoNuevos[$key];
        $item['nota'] = trim($nota);
        $this->carritoNuevos[$key] = $item;
    }

    public function setCantidadNuevo(string $key, float $qty): void
    {
        if (! isset($this->carritoNuevos[$key])) return;
        if ($qty <= 0) {
            $this->eliminarNuevo($key);
            return;
        }
        $item              = $this->carritoNuevos[$key];
        $item['cantidad']  = $qty;
        $this->carritoNuevos[$key] = $item;
        $this->hayCambiosPendientes = true;
    }

    // ── Gestión de items ──────────────────────────────────────────────────────

    public function incrementar(int $detalleId): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->findOrFail($detalleId);
        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        $nuevaCantidad      = (float) $detalle->cantidad + 1;
        $cantEnviada        = (float) ($detalle->cantidad_enviada_cocina ?? 0);
        $sinDelta           = abs($nuevaCantidad - $cantEnviada) < 0.001;
        $enviadoCocinaFinal = $cantEnviada > 0 && $sinDelta;

        $calc = OrdenDetalle::calcular($nuevaCantidad, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'       => $nuevaCantidad,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'       => $calc['subtotal'],
            'igv'            => $calc['igv'],
            'total'          => $calc['total'],
            'costo_total'    => $calc['costoTotal'],
            'enviado_cocina' => $enviadoCocinaFinal,
        ]);

        // Si la cantidad difiere de lo enviado → el ítem irá en AGREGAR con su nota actual;
        // eliminarlo de notasModificadas para evitar que también aparezca en NOTA ACTUALIZADA.
        if (! $enviadoCocinaFinal) {
            unset($this->notasModificadas[$detalleId]);
        }

        $this->record->recalcularTotales();
        $this->record->refresh();
        $this->actualizarHayCambios();
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

        $nuevaCantidad      = (float) $detalle->cantidad - 1;
        $cantEnviada        = (float) ($detalle->cantidad_enviada_cocina ?? 0);
        $sinDelta           = abs($nuevaCantidad - $cantEnviada) < 0.001;
        $enviadoCocinaFinal = $cantEnviada > 0 && $sinDelta;

        $calc = OrdenDetalle::calcular($nuevaCantidad, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'       => $nuevaCantidad,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'       => $calc['subtotal'],
            'igv'            => $calc['igv'],
            'total'          => $calc['total'],
            'costo_total'    => $calc['costoTotal'],
            'enviado_cocina' => $enviadoCocinaFinal,
        ]);

        if (! $enviadoCocinaFinal) {
            unset($this->notasModificadas[$detalleId]);
        }

        $this->record->recalcularTotales();
        $this->record->refresh();
        $this->actualizarHayCambios();
    }

    public function eliminarItem(int $detalleId): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->find($detalleId);

        if (! $detalle) return;

        // Si alguna vez se envió a cocina (cantidad_enviada_cocina > 0) → notificar cancelación.
        // Usar cantidad_enviada_cocina en lugar de enviado_cocina porque éste se pone false
        // cuando hay un delta pendiente, aunque la cocina ya tenga unidades en cola.
        $cantEnviada = (float) ($detalle->cantidad_enviada_cocina ?? 0);
        if ($cantEnviada > 0) {
            $detalle->loadMissing('producto.produccion');
            $this->eliminadosEnSesion[] = [
                'nombre'            => $detalle->descripcion ?? $detalle->producto?->nombre ?? '—',
                'cantidad'          => (int) $cantEnviada,
                'produccion_id'     => $detalle->producto?->produccion?->id,
                'produccion_nombre' => $detalle->producto?->produccion?->nombre,
            ];
        }

        // Liberar stock de las unidades que ya estaban reservadas
        if ($cantEnviada > 0) {
            $this->liberarStockDetalles([[
                'producto_id'  => $detalle->producto_id,
                'variante_id'  => $detalle->variante_id,
                'promocion_id' => $detalle->promocion_id,
                'cantidad'     => $cantEnviada,
            ]], Filament::getTenant()->id);
        }

        $detalle->delete();
        $this->record->recalcularTotales();
        $this->record->refresh();
        $this->actualizarHayCambios();
    }

    public function setCantidadDetalle(int $detalleId, float $qty): void
    {
        $qty     = max(0.01, $qty);
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->findOrFail($detalleId);
        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;

        $cantEnviada        = (float) ($detalle->cantidad_enviada_cocina ?? 0);
        $sinDelta           = abs($qty - $cantEnviada) < 0.001;
        $enviadoCocinaFinal = $cantEnviada > 0 && $sinDelta;

        $calc = OrdenDetalle::calcular($qty, (float) $detalle->precio_unitario, (float) $detalle->costo_unitario, 0, $igvRate);
        $detalle->update([
            'cantidad'       => $qty,
            'valor_unitario' => $calc['valorUnitario'],
            'subtotal'       => $calc['subtotal'],
            'igv'            => $calc['igv'],
            'total'          => $calc['total'],
            'costo_total'    => $calc['costoTotal'],
            'enviado_cocina' => $enviadoCocinaFinal,
        ]);

        if (! $enviadoCocinaFinal) {
            unset($this->notasModificadas[$detalleId]);
        }

        $this->record->recalcularTotales();
        $this->record->refresh();
        $this->actualizarHayCambios();
    }

    public function setNotaDetalle(int $detalleId, string $nota): void
    {
        $detalle = OrdenDetalle::where('orden_id', $this->record->id)->find($detalleId);
        if (! $detalle) return;

        $notaAnterior = $detalle->notas_item ?? '';
        $notaNueva    = trim($nota);

        $detalle->update(['notas_item' => $notaNueva !== '' ? $notaNueva : null]);

        // Solo trackear cambios en ítems ya enviados a cocina;
        // los no enviados van al bloque 'nuevos' cuando se confirme.
        if ($detalle->enviado_cocina) {
            if ($notaNueva !== $notaAnterior) {
                $this->notasModificadas[$detalleId] = true;
            } else {
                unset($this->notasModificadas[$detalleId]);
            }
        }

        $this->record->refresh();
        $this->actualizarHayCambios();
    }

    // ── Enviar actualización a cocina ─────────────────────────────────────────

    public function enviarActualizacion(): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.editar'), 403);

        $orden   = $this->record;
        $empresa = Filament::getTenant();

        $tieneCarritoNuevos    = ! empty($this->carritoNuevos);
        $tieneNuevosEnBd       = $orden->detalles()->where('enviado_cocina', false)->exists();
        $tieneEliminados       = ! empty($this->eliminadosEnSesion);
        $tieneNotasModificadas = ! empty($this->notasModificadas);

        if (! $tieneCarritoNuevos && ! $tieneNuevosEnBd && ! $tieneEliminados && ! $tieneNotasModificadas) {
            Notification::make()->title('No hay cambios para enviar')->warning()->send();
            return;
        }

        // Persistir carritoNuevos → crear OrdenDetalle con enviado_cocina = false
        if ($tieneCarritoNuevos) {
            $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;
            foreach ($this->carritoNuevos as $item) {
                $tipo       = $item['tipo'];
                $id         = $item['id'];
                $precio     = $item['precio'];
                $cantidad   = $item['cantidad'];
                $productoId = $item['producto_id'];
                $tipoItem   = match ($tipo) {
                    'variante'  => TipoItem::Variante,
                    'promocion' => TipoItem::Promocion,
                    default     => TipoItem::Producto,
                };
                $costo = 0.0;
                if ($tipo === 'producto') {
                    $prod  = Producto::find($id);
                    $costo = (float) ($prod?->precio_costo ?? 0);
                } elseif ($tipo === 'variante') {
                    $var   = \App\Models\Variante::find($id);
                    $costo = (float) ($var?->costo ?? $var?->producto?->precio_costo ?? 0);
                }
                $existente = $orden->detalles()
                    ->where('enviado_cocina', false)
                    ->when($tipo === 'producto',  fn ($q) => $q->where('producto_id', $id)->whereNull('variante_id')->whereNull('promocion_id'))
                    ->when($tipo === 'variante',  fn ($q) => $q->where('variante_id', $id))
                    ->when($tipo === 'promocion', fn ($q) => $q->where('promocion_id', $id))
                    ->first();
                if ($existente) {
                    $nuevaCantidad = (float) $existente->cantidad + $cantidad;
                    $calc          = OrdenDetalle::calcular($nuevaCantidad, $precio, $costo, 0, $igvRate);
                    $existente->update([
                        'cantidad'       => $nuevaCantidad,
                        'valor_unitario' => $calc['valorUnitario'],
                        'subtotal'       => $calc['subtotal'],
                        'igv'            => $calc['igv'],
                        'total'          => $calc['total'],
                        'costo_total'    => $calc['costoTotal'],
                        'notas_item'     => $item['nota'] !== '' ? $item['nota'] : $existente->notas_item,
                    ]);
                } else {
                    $calc = OrdenDetalle::calcular($cantidad, $precio, $costo, 0, $igvRate);
                    $orden->detalles()->create([
                        'tipo_item'       => $tipoItem,
                        'producto_id'     => $tipo === 'producto' ? $id : ($tipo === 'variante' ? $productoId : null),
                        'variante_id'     => $tipo === 'variante'  ? $id : null,
                        'promocion_id'    => $tipo === 'promocion' ? $id : null,
                        'descripcion'     => $item['nombre'],
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
                        'notas_item'      => $item['nota'] !== '' ? $item['nota'] : null,
                    ]);
                }
            }
            // Reservar/liberar stock según deltas en detalles pendientes
            $detallesPendientes = $orden->detalles()
                ->where('enviado_cocina', false)
                ->get();

            $paraReservar = [];
            $paraLiberar  = [];
            foreach ($detallesPendientes as $d) {
                $delta = (float) $d->cantidad - (float) ($d->cantidad_enviada_cocina ?? 0);
                if ($delta > 0.001) {
                    $paraReservar[] = [
                        'producto_id'  => $d->producto_id,
                        'variante_id'  => $d->variante_id,
                        'promocion_id' => $d->promocion_id,
                        'cantidad'     => $delta,
                    ];
                } elseif ($delta < -0.001) {
                    $paraLiberar[] = [
                        'producto_id'  => $d->producto_id,
                        'variante_id'  => $d->variante_id,
                        'promocion_id' => $d->promocion_id,
                        'cantidad'     => abs($delta),
                    ];
                }
            }
            if (! empty($paraReservar)) $this->reservarStockDetalles($paraReservar, $empresa->id);
            if (! empty($paraLiberar))  $this->liberarStockDetalles($paraLiberar, $empresa->id);

            $this->carritoNuevos = [];
            $orden->recalcularTotales();
            $this->record->refresh();
        } elseif ($tieneNuevosEnBd) {
            // Hay detalles en BD con enviado_cocina=false pero carritoNuevos vacío
            // (p.ej. decrementar/incrementar directo sobre detalles guardados)
            $detallesPendientes = $orden->detalles()
                ->where('enviado_cocina', false)
                ->get();

            $paraReservar = [];
            $paraLiberar  = [];
            foreach ($detallesPendientes as $d) {
                $delta = (float) $d->cantidad - (float) ($d->cantidad_enviada_cocina ?? 0);
                if ($delta > 0.001) {
                    $paraReservar[] = [
                        'producto_id'  => $d->producto_id,
                        'variante_id'  => $d->variante_id,
                        'promocion_id' => $d->promocion_id,
                        'cantidad'     => $delta,
                    ];
                } elseif ($delta < -0.001) {
                    $paraLiberar[] = [
                        'producto_id'  => $d->producto_id,
                        'variante_id'  => $d->variante_id,
                        'promocion_id' => $d->promocion_id,
                        'cantidad'     => abs($delta),
                    ];
                }
            }
            if (! empty($paraReservar)) $this->reservarStockDetalles($paraReservar, $empresa->id);
            if (! empty($paraLiberar))  $this->liberarStockDetalles($paraLiberar, $empresa->id);
        }

        $tieneNuevos = $tieneCarritoNuevos || $tieneNuevosEnBd;

        // $descripcion se computa después de construir $itemsPorArea para reflejar el contenido real
        $descripcion = '';

        $config = $empresa->cachedConfigImpresion();

        // Impresión directa (si está habilitada) — no excluye el modal browser
        if ($config['tiene_impresion_directa']) {
            if ($tieneNuevos) {
                app(ImpresionDirectaService::class)->imprimirComandaOrden($orden, $empresa);
            }
            if ($tieneEliminados) {
                app(ImpresionDirectaService::class)->imprimirEliminados($orden, $empresa, $this->eliminadosEnSesion);
            }
        }

        // Agrupar por área para el modal browser
        $itemsPorArea = [];

        if ($tieneNuevos) {
            $nuevosDets = $orden->detalles()
                ->where('enviado_cocina', false)
                ->with([
                    'producto.produccion',
                    'promocion.detalles.producto.produccion',
                    'promocion.detalles.variante.producto.produccion',
                ])
                ->get();

            foreach ($nuevosDets as $d) {
                $delta = (float) $d->cantidad - (float) $d->cantidad_enviada_cocina;
                if ($delta === 0.0) continue;

                if ($d->promocion_id && $d->promocion) {
                    foreach ($d->promocion->detalles as $pd) {
                        $produccion = $pd->variante_id
                            ? $pd->variante?->producto?->produccion
                            : $pd->producto?->produccion;
                        if (! $produccion) continue;
                        $key = 'a' . $produccion->id;
                        if (! isset($itemsPorArea[$key])) {
                            $itemsPorArea[$key] = ['nombre' => $produccion->nombre, 'nuevos' => [], 'cancelados' => [], 'notas' => []];
                        }
                        $subNombre = $pd->variante?->nombre ?? $pd->producto?->nombre ?? $d->descripcion;
                        $nota = trim(($d->notas_item ?? '') . ' [' . $d->descripcion . ']');
                        if ($delta > 0) {
                            $itemsPorArea[$key]['nuevos'][] = [
                                'cant'   => (int) $delta,
                                'nombre' => $subNombre,
                                'nota'   => $nota,
                            ];
                        } else {
                            $itemsPorArea[$key]['cancelados'][] = [
                                'cant'   => (int) abs($delta),
                                'nombre' => $subNombre,
                                'nota'   => '',
                            ];
                        }
                    }
                } else {
                    $produccion = $d->producto?->produccion;
                    if (! $produccion) continue;
                    $key    = 'a' . $produccion->id;
                    $nombre = $produccion->nombre;
                    if (! isset($itemsPorArea[$key])) {
                        $itemsPorArea[$key] = ['nombre' => $nombre, 'nuevos' => [], 'cancelados' => [], 'notas' => []];
                    }
                    if ($delta > 0) {
                        $itemsPorArea[$key]['nuevos'][] = [
                            'cant'   => (int) $delta,
                            'nombre' => $d->descripcion ?? '—',
                            'nota'   => $d->notas_item ?? '',
                        ];
                    } elseif ($delta < 0) {
                        $itemsPorArea[$key]['cancelados'][] = [
                            'cant'   => (int) abs($delta),
                            'nombre' => $d->descripcion ?? '—',
                            'nota'   => '',
                        ];
                    }
                }
            }
            $orden->detalles()->where('enviado_cocina', false)->update([
                'enviado_cocina'          => true,
                'cantidad_enviada_cocina' => DB::raw('cantidad'),
            ]);
        }

        // Ítems con nota actualizada (ya enviados a cocina, solo cambió la nota)
        if ($tieneNotasModificadas) {
            $notasDets = OrdenDetalle::whereIn('id', array_keys($this->notasModificadas))
                ->where('orden_id', $orden->id)
                ->with('producto.produccion')
                ->get();

            foreach ($notasDets as $d) {
                $produccion = $d->producto?->produccion;
                if (! $produccion) continue;
                $key    = 'a' . $produccion->id;
                $nombre = $produccion->nombre;
                if (! isset($itemsPorArea[$key])) {
                    $itemsPorArea[$key] = ['nombre' => $nombre, 'nuevos' => [], 'cancelados' => [], 'notas' => []];
                }
                $itemsPorArea[$key]['notas'][] = [
                    'cant'   => (int) $d->cantidad,
                    'nombre' => $d->descripcion ?? '—',
                    'nota'   => $d->notas_item ?? '',
                ];
            }
            $this->notasModificadas = [];
        }

        foreach ($this->eliminadosEnSesion as $item) {
            $pid = $item['produccion_id'] ?? null;
            if (! $pid) continue;
            $key    = 'a' . $pid;
            $nombre = $item['produccion_nombre'] ?: 'Área';
            if (! isset($itemsPorArea[$key])) {
                $itemsPorArea[$key] = ['nombre' => $nombre, 'nuevos' => [], 'cancelados' => [], 'notas' => []];
            }
            $itemsPorArea[$key]['cancelados'][] = [
                'cant'   => $item['cantidad'],
                'nombre' => $item['nombre'],
                'nota'   => '',
            ];
        }
        $this->eliminadosEnSesion = [];

        $areas = array_values($itemsPorArea);

        // Descripción basada en contenido real de las áreas (no en flags que pueden ser imprecisos)
        $hayNuevosReales    = false;
        $hayCanceladosReales = false;
        $hayNotasReales     = false;
        foreach ($areas as $area) {
            if (! empty($area['nuevos']))    $hayNuevosReales    = true;
            if (! empty($area['cancelados'])) $hayCanceladosReales = true;
            if (! empty($area['notas']))     $hayNotasReales     = true;
        }
        $partes = array_values(array_filter([
            $hayNuevosReales    ? 'nuevos productos'   : null,
            $hayCanceladosReales ? 'eliminaciones'     : null,
            $hayNotasReales     ? 'notas actualizadas' : null,
        ]));
        $descripcion = match (count($partes)) {
            0       => 'Actualización de pedido',
            1       => ucfirst($partes[0]),
            2       => ucfirst("{$partes[0]} y {$partes[1]}"),
            default => ucfirst(implode(', ', array_slice($partes, 0, -1)) . ' y ' . end($partes)),
        };

        if (! empty($areas)) {
            $user         = auth()->user();
            $rolNombre    = $user->roles()->where('roles.empresa_id', $empresa->id)->value('name') ?? '';
            $numeroPedido = $orden->codigo;
            $areasJson    = json_encode($areas);
            $mesaNombre   = $orden->mesa?->nombre ?? '';

            $this->lastComandaData = [
                'ordenId'     => $orden->id,
                'areasJson'   => $areasJson,
                'mesa'        => $mesaNombre,
                'cajero'      => $user->name,
                'rol'         => $rolNombre,
                'numero'      => $numeroPedido,
                'parcial'     => true,
                'descripcion' => $descripcion,
            ];
            $this->dispatch('cerrar-carrito');
            $this->dispatch('imprimir-comanda-browser',
                ordenId:     $orden->id,
                areasJson:   $areasJson,
                mesa:        $mesaNombre,
                cajero:      $user->name,
                rol:         $rolNombre,
                numero:      $numeroPedido,
                parcial:     true,
                descripcion: $descripcion,
            );
        }

        $this->dispatch('ep-cambios-enviados');
        $this->hayCambiosPendientes = false;

        Notification::make()->title('Pedido actualizado')->success()->send();

        $this->record->refresh();
    }

    // ── Anular pedido ─────────────────────────────────────────────────────────

    public function anularPedido(): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.eliminar'), 403);

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

        // Liberar stock reservado por todos los ítems enviados a cocina
        $detallesParaLiberar = $orden->detalles
            ->filter(fn ($d) => (float) ($d->cantidad_enviada_cocina ?? 0) > 0)
            ->map(fn ($d) => [
                'producto_id'  => $d->producto_id,
                'variante_id'  => $d->variante_id,
                'promocion_id' => $d->promocion_id,
                'cantidad'     => (float) ($d->cantidad_enviada_cocina ?? 0),
            ])->values()->all();

        if (! empty($detallesParaLiberar)) {
            $this->liberarStockDetalles($detallesParaLiberar, $empresa->id);
        }

        $orden->update(['estado' => EstadoOrden::Cancelada->value]);
        $orden->mesa?->marcarLibre();

        Notification::make()->title('Pedido anulado')->success()->send();

        if (! empty($areas)) {
            $user      = auth()->user();
            $rolNombre = $user->roles()->where('roles.empresa_id', $empresa->id)->value('name') ?? '';
            $areasJson  = json_encode($areas);
            $mesaNombre = $orden->mesa?->nombre ?? '';

            $this->pendingRedirectAfterComanda = true;
            $this->comandaRedirectUrl = MapaMesasPage::getUrl(tenant: $empresa);
            $this->lastComandaData = [
                'ordenId'     => $orden->id,
                'areasJson'   => $areasJson,
                'mesa'        => $mesaNombre,
                'cajero'      => $user->name,
                'rol'         => $rolNombre,
                'numero'      => $orden->codigo,
                'parcial'     => false,
                'descripcion' => 'Pedido anulado',
            ];
            $this->dispatch('imprimir-comanda-browser',
                ordenId:     $orden->id,
                areasJson:   $areasJson,
                mesa:        $mesaNombre,
                cajero:      $user->name,
                rol:         $rolNombre,
                numero:      $orden->codigo,
                parcial:     false,
                descripcion: 'Pedido anulado',
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
            ordenId:     $d['ordenId'],
            areasJson:   $d['areasJson'],
            mesa:        $d['mesa'],
            cajero:      $d['cajero'],
            rol:         $d['rol']    ?? '',
            numero:      $d['numero'] ?? '',
            parcial:     $d['parcial'],
            descripcion: $d['descripcion'] ?? '',
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

    // ── Pre-cuenta ────────────────────────────────────────────────────────────

    public function solicitarPreCuenta(): void
    {
        $orden   = $this->record;
        $empresa = Filament::getTenant();

        $orden->mesa?->marcarPagando();

        $config = $empresa->cachedConfigImpresion();

        if ($config['tiene_impresion_directa']) {
            app(ImpresionDirectaService::class)->imprimirPreCuentaMesa($orden, $empresa);
        } else {
            $this->dispatch('imprimir-precuenta-browser', ordenId: $orden->id);
        }

        Notification::make()->title('Pre-cuenta solicitada')->success()->send();
    }

    // ── Cobrar ────────────────────────────────────────────────────────────────

    public function irACobrar(): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.cobrar'), 403);

        if (! empty($this->carritoNuevos)) {
            Notification::make()
                ->title('Productos sin enviar')
                ->body('Envía la actualización a cocina antes de cobrar, o elimina los productos del carrito.')
                ->warning()
                ->send();
            return;
        }

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
