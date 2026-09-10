<?php

namespace App\Filament\Pdv\Resources\OrdenRest\Pages;

use App\Enums\EstadoMesa;
use App\Enums\EstadoOrden;
use App\Enums\TipoItem;
use App\Enums\TipoOrigenOrden;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Pages\MapaMesasPage;
use App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource;
use App\Models\Mesa;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Services\ImpresionDirectaService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class NuevoPedido extends Page
{
    use HasFullWidthPage;

    protected static string $resource = OrdenRestResource::class;

    protected string $view = 'filament.pdv.pages.nuevo-pedido';

    // ── Props ─────────────────────────────────────────────────────────────────

    public ?int  $mesaId          = null;
    public array $carrito         = [];
    public bool  $enviando        = false;
    public array $lastComandaData = [];

    public function mount(): void
    {
        $mesaId = (int) request()->query('mesa_id', 0);

        if (! $mesaId) {
            Notification::make()->title('Falta seleccionar una mesa')->warning()->send();
            $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
            return;
        }

        $mesa = Mesa::where('empresa_id', Filament::getTenant()->id)->find($mesaId);

        if (! $mesa) {
            Notification::make()->title('Mesa no encontrada')->danger()->send();
            $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
            return;
        }

        if (! $mesa->estaLibre()) {
            // Mesa ya ocupada → redirigir a editar el pedido existente
            $orden = $mesa->ordenActiva;
            if ($orden) {
                $this->redirect(OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: Filament::getTenant()));
            } else {
                $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
            }
            return;
        }

        $this->mesaId = $mesaId;
    }

    // ── Carrito (escucha el evento del ProductCatalog) ────────────────────────

    #[On('product-selected')]
    public function agregarAlCarrito(array $payload): void
    {
        $tipo       = $payload['tipo'];
        $id         = (int) $payload['id'];
        $nombre     = $payload['nombre'];
        $precio     = (float) $payload['precio'];
        $precioNorm = (float) ($payload['precio_normal'] ?? $precio);
        $cortesia   = (bool) ($payload['es_cortesia'] ?? false);
        $cantidad   = (float) ($payload['cantidad'] ?? 1);
        $productoId = (int) ($payload['producto_id'] ?? $id);

        $baseKey     = "{$tipo}_{$id}";
        $cortesiaSfx = $cortesia ? '_cortesia' : '';
        $key         = $baseKey . $cortesiaSfx;

        $carrito = $this->carrito;

        if (isset($carrito[$key])) {
            $carrito[$key]['cantidad'] += $cantidad;
        } else {
            $carrito[$key] = [
                'tipo'          => $tipo,
                'id'            => $id,
                'nombre'        => $nombre,
                'precio'        => $precio,
                'precio_normal' => $precioNorm,
                'es_cortesia'   => $cortesia,
                'cantidad'      => $cantidad,
                'producto_id'   => $productoId,
            ];
        }

        $this->carrito = $carrito;
    }

    public function incrementar(string $key): void
    {
        if (isset($this->carrito[$key])) {
            $this->carrito[$key]['cantidad'] += 1;
        }
    }

    public function decrementar(string $key): void
    {
        if (! isset($this->carrito[$key])) return;

        if ($this->carrito[$key]['cantidad'] <= 1) {
            $this->eliminarItem($key);
            return;
        }

        $this->carrito[$key]['cantidad'] -= 1;
    }

    public function eliminarItem(string $key): void
    {
        $carrito = $this->carrito;
        unset($carrito[$key]);
        $this->carrito = $carrito;
    }

    public function setCantidad(string $key, float $qty): void
    {
        if (! isset($this->carrito[$key])) return;
        $this->carrito[$key]['cantidad'] = max(0.01, $qty);
    }

    public function vaciarCarrito(): void
    {
        $this->carrito = [];
    }

    // ── Totales ───────────────────────────────────────────────────────────────

    public function getTotal(): float
    {
        return collect($this->carrito)->sum(fn ($i) => $i['precio'] * $i['cantidad']);
    }

    public function getCarritoResumen(): array
    {
        $resumen = [];
        foreach ($this->carrito as $key => $item) {
            $rKey = "{$item['tipo']}_{$item['id']}";
            $resumen[$rKey] = ($resumen[$rKey] ?? 0) + $item['cantidad'];
        }
        return $resumen;
    }

    public function getItemCount(): int
    {
        return (int) collect($this->carrito)->sum('cantidad');
    }

    // ── Enviar pedido ─────────────────────────────────────────────────────────

    public function enviarPedido(): void
    {
        if (empty($this->carrito)) {
            Notification::make()->title('El pedido está vacío')->warning()->send();
            return;
        }

        $empresa = Filament::getTenant();
        $mesa    = Mesa::where('empresa_id', $empresa->id)->findOrFail($this->mesaId);

        if (! $mesa->estaLibre()) {
            Notification::make()->title('La mesa ya está ocupada')->warning()->send();
            $this->redirect(MapaMesasPage::getUrl(tenant: $empresa));
            return;
        }

        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;
        $orden   = null;

        try {
            DB::transaction(function () use ($empresa, $mesa, $igvRate, &$orden) {
                // Crear Orden
                $orden = Orden::create([
                    'empresa_id'   => $empresa->id,
                    'tipo_origen'  => TipoOrigenOrden::Restaurante->value,
                    'mesa_id'      => $mesa->id,
                    'estado'       => EstadoOrden::PendientePago->value,
                    'fecha_orden'  => now(),
                    'tipo_entrega' => 'local',
                    'igv'          => 0,
                    'subtotal'     => 0,
                    'total'        => 0,
                ]);

                // Crear OrdenDetalles
                foreach ($this->carrito as $item) {
                    $tipo   = $item['tipo'];
                    $precio = (float) $item['precio'];
                    $costo  = 0.0;

                    if ($tipo === 'producto') {
                        $prod  = Producto::find($item['id']);
                        $costo = (float) ($prod?->precio_costo ?? 0);
                    } elseif ($tipo === 'variante') {
                        $var   = \App\Models\Variante::find($item['id']);
                        $costo = (float) ($var?->costo ?? $var?->producto?->precio_costo ?? 0);
                    }

                    $tipoItem = match ($tipo) {
                        'variante'  => TipoItem::Variante,
                        'promocion' => TipoItem::Promocion,
                        default     => TipoItem::Producto,
                    };

                    $cantidad = (float) $item['cantidad'];
                    $calc     = OrdenDetalle::calcular($cantidad, $precio, $costo, 0, $igvRate);

                    $orden->detalles()->create([
                        'tipo_item'       => $tipoItem,
                        'producto_id'     => $tipo === 'producto' ? $item['id'] : ($tipo === 'variante' ? ($item['producto_id'] ?? null) : null),
                        'variante_id'     => $tipo === 'variante'  ? $item['id'] : null,
                        'promocion_id'    => $tipo === 'promocion' ? $item['id'] : null,
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
                    ]);
                }

                $orden->recalcularTotales();

                // Marcar mesa ocupada
                $mesa->marcarOcupada();
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al crear el pedido')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        // Vaciar carrito inmediatamente para evitar re-envíos accidentales
        $this->carrito = [];

        // Enviar a cocina
        $config = $empresa->cachedConfigImpresion();

        // Impresión directa (si está habilitada en el plan) — no excluye el modal browser
        if ($config['tiene_impresion_directa']) {
            app(ImpresionDirectaService::class)->imprimirComandaOrden($orden, $empresa);
        }

        $orden->detalles()->update([
            'enviado_cocina'          => true,
            'cantidad_enviada_cocina' => \Illuminate\Support\Facades\DB::raw('cantidad'),
        ]);

        // Agrupar por área de producción para el modal browser
        $orden->loadMissing(['detalles.producto.produccion']);
        $itemsPorArea = [];
        foreach ($orden->detalles as $det) {
            $produccion = $det->producto?->produccion;
            if (! $produccion) continue;
            $key    = 'a' . $produccion->id;
            $nombre = $produccion->nombre;
            if (! isset($itemsPorArea[$key])) {
                $itemsPorArea[$key] = ['nombre' => $nombre, 'nuevos' => [], 'cancelados' => []];
            }
            $itemsPorArea[$key]['nuevos'][] = [
                'cant'   => (int) $det->cantidad,
                'nombre' => $det->descripcion ?? '—',
                'nota'   => $det->notas_item ?? '',
            ];
        }
        $areas = array_values($itemsPorArea);

        // Sin áreas de producción → redirigir directo al mapa
        if (empty($areas)) {
            Notification::make()
                ->title('Pedido enviado ✓')
                ->body("Mesa {$mesa->nombre} — pedido registrado.")
                ->success()
                ->send();
            $this->redirect(MapaMesasPage::getUrl(tenant: $empresa));
            return;
        }

        $this->lastComandaData = [
            'ordenId'   => $orden->id,
            'areasJson' => json_encode($areas),
            'mesa'      => $mesa->nombre,
            'cajero'    => auth()->user()->name,
            'parcial'   => false,
        ];
        $this->dispatch('cerrar-carrito');
        $this->dispatch('imprimir-comanda-browser',
            ordenId:   $orden->id,
            areasJson: json_encode($areas),
            mesa:      $mesa->nombre,
            cajero:    auth()->user()->name,
            parcial:   false,
        );
        // onComandaModalCerrada() hará el redirect al mapa
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
        $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
    }

    #[On('comanda-modal-cerrada')]
    public function onComandaModalCerrada(): void
    {
        $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
    }

    public function confirmarEnvio(int $ordenId): void
    {
        // Fallback cuando no hay áreas configuradas y se usó el modal pedido-creado
        Notification::make()
            ->title('Pedido realizado ✓')
            ->body('El pedido fue registrado correctamente.')
            ->success()
            ->send();

        $this->redirect(MapaMesasPage::getUrl(tenant: Filament::getTenant()));
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
