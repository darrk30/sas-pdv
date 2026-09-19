<?php

namespace App\Filament\Pdv\Resources\OrdenRest\Pages;

use App\Enums\EstadoMesa;
use App\Enums\EstadoOrden;
use App\Enums\TipoItem;
use App\Enums\TipoOrigenOrden;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use App\Filament\Pdv\Pages\MapaMesasPage;
use App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource;
use App\Filament\Pdv\Resources\Ordenes\Concerns\ValidaStockOrden;
use App\Models\Mesa;
use App\Models\Orden;
use App\Models\OrdenDetalle;
use App\Models\Producto;
use App\Models\User;
use App\Services\ImpresionDirectaService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class NuevoPedido extends Page
{
    use HasFullWidthPage, ValidaStockOrden;

    protected static string $resource = OrdenRestResource::class;

    protected string $view = 'filament.pdv.pages.nuevo-pedido';

    public static function canAccess(array $parameters = []): bool
    {
        return parent::canAccess($parameters) && (auth()->user()?->can('restaurante.pedido.crear') ?? false);
    }

    // ── Props ─────────────────────────────────────────────────────────────────

    public ?int   $mesaId              = null;
    public bool   $esLlevar            = false;
    public bool   $esDelivery          = false;
    public string $clienteConcepto     = '';
    // Campos delivery
    public string $deliveryNombre      = '';
    public string $deliveryTelefono    = '';
    public string $deliveryDireccion   = '';
    public ?int   $repartidorId        = null;
    public string $repartidorTexto     = '';

    public array  $carrito             = [];
    public bool   $enviando            = false;
    public array  $lastComandaData     = [];
    /** URL a la que Alpine navega al cerrar el modal de comanda (evita re-render Livewire) */
    public string $comandaRedirectUrl  = '';

    public function mount(): void
    {
        $tipo   = request()->query('tipo', '');
        $mesaId = (int) request()->query('mesa_id', 0);

        // ── Modo Para Llevar ──────────────────────────────────────────────────
        if ($tipo === 'llevar') {
            $this->esLlevar = true;
            return;
        }

        // ── Modo Delivery ─────────────────────────────────────────────────────
        if ($tipo === 'delivery') {
            $this->esDelivery = true;
            return;
        }

        // ── Modo Mesa ─────────────────────────────────────────────────────────
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

    // Carrito gestionado 100 % en Alpine.js (sin round-trips al server)
    // enviarPedido() recibe $this->carrito sincronizado por Alpine antes de llamar.

    // ── Totales ───────────────────────────────────────────────────────────────

    public function getTotal(): float
    {
        return collect($this->carrito)->sum(fn ($i) => $i['precio'] * $i['cantidad']);
    }

    public function updatedCarrito(): void
    {
        $this->js('Alpine.store("carritoResumen",' . json_encode($this->getCarritoResumen()) . ')');
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

    public function getPendienteResumen(): array
    {
        $resumen = [];
        foreach ($this->carrito as $item) {
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

    // ── Enviar pedido ─────────────────────────────────────────────────────────

    public function enviarPedido(): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.crear'), 403);

        if (empty($this->carrito)) {
            Notification::make()->title('El pedido está vacío')->warning()->send();
            return;
        }

        $empresa = Filament::getTenant();
        $igvRate = ($empresa->igv_porcentaje ?? 18) / 100;
        $orden   = null;

        // ── Delivery ──────────────────────────────────────────────────────────
        if ($this->esDelivery) {
            if (empty(trim($this->deliveryNombre))) {
                Notification::make()->title('El nombre del cliente es obligatorio')->warning()->send();
                return;
            }
            try {
                DB::transaction(function () use ($empresa, $igvRate, &$orden) {
                    $notasInternas = null;
                    $repartidor    = trim($this->repartidorTexto);
                    if ($this->repartidorId) {
                        $notasInternas = json_encode(['repartidor_id' => $this->repartidorId]);
                    } elseif ($repartidor) {
                        $notasInternas = json_encode(['repartidor' => $repartidor]);
                    }

                    $orden = Orden::create([
                        'empresa_id'        => $empresa->id,
                        'tipo_origen'       => TipoOrigenOrden::Delivery->value,
                        'mesa_id'           => null,
                        'repartidor_id'     => $this->repartidorId ?: null,
                        'estado'            => EstadoOrden::EnPreparacion->value,
                        'fecha_orden'       => now(),
                        'tipo_entrega'      => 'delivery',
                        'cliente_nombre'    => trim($this->deliveryNombre),
                        'cliente_telefono'  => trim($this->deliveryTelefono) ?: null,
                        'cliente_direccion' => trim($this->deliveryDireccion) ?: null,
                        'notas_internas'    => $notasInternas,
                        'igv'               => 0,
                        'subtotal'          => 0,
                        'total'             => 0,
                    ]);
                    $this->insertarDetalles($orden, $igvRate);
                    $orden->recalcularTotales();
                });
            } catch (\Throwable $e) {
                Notification::make()->title('Error al crear el pedido')->body($e->getMessage())->danger()->send();
                return;
            }

            $etiqueta    = 'Delivery · ' . trim($this->deliveryNombre);
            $redirectUrl = OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: $empresa);
            $this->postEnviar($orden, $empresa, $etiqueta, $redirectUrl);
            return;
        }

        // ── Para llevar ───────────────────────────────────────────────────────
        if ($this->esLlevar) {
            try {
                DB::transaction(function () use ($empresa, $igvRate, &$orden) {
                    $concepto = trim($this->clienteConcepto) ?: 'Sin nombre';
                    $orden = Orden::create([
                        'empresa_id'     => $empresa->id,
                        'tipo_origen'    => TipoOrigenOrden::Llevar->value,
                        'mesa_id'        => null,
                        'estado'         => EstadoOrden::EnPreparacion->value,
                        'fecha_orden'    => now(),
                        'tipo_entrega'   => 'retiro',
                        'cliente_nombre' => $concepto,
                        'igv'            => 0,
                        'subtotal'       => 0,
                        'total'          => 0,
                    ]);
                    $this->insertarDetalles($orden, $igvRate);
                    $orden->recalcularTotales();
                });
            } catch (\Throwable $e) {
                Notification::make()->title('Error al crear el pedido')->body($e->getMessage())->danger()->send();
                return;
            }

            $etiqueta    = 'Para llevar · ' . (trim($this->clienteConcepto) ?: 'Sin nombre');
            $redirectUrl = OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: $empresa);
            $this->postEnviar($orden, $empresa, $etiqueta, $redirectUrl);
            return;
        }

        // ── Mesa ──────────────────────────────────────────────────────────────
        $mesa = Mesa::where('empresa_id', $empresa->id)->findOrFail($this->mesaId);

        if (! $mesa->estaLibre()) {
            Notification::make()->title('La mesa ya está ocupada')->warning()->send();
            $this->redirect(MapaMesasPage::getUrl(tenant: $empresa));
            return;
        }

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
                $this->insertarDetalles($orden, $igvRate);
                $orden->recalcularTotales();

                // Marcar mesa ocupada
                $mesa->marcarOcupada();
            });
        } catch (\Throwable $e) {
            Notification::make()->title('Error al crear el pedido')->body($e->getMessage())->danger()->send();
            return;
        }

        $this->postEnviar($orden, $empresa, $mesa->nombre);
    }

    // ── Repartidores disponibles en la empresa ────────────────────────────────

    public function getUsuariosRepartidor(): array
    {
        $empresa = Filament::getTenant();
        return User::whereHas('empresas', fn ($q) => $q->where('empresas.id', $empresa->id))
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->name])
            ->values()
            ->all();
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function insertarDetalles(Orden $orden, float $igvRate): void
    {
        $productoIdsBulk = collect($this->carrito)->where('tipo', 'producto')->pluck('id')->unique()->values()->all();
        $varianteIdsBulk = collect($this->carrito)->where('tipo', 'variante')->pluck('id')->unique()->values()->all();
        $costosProd  = $productoIdsBulk ? Producto::whereIn('id', $productoIdsBulk)->pluck('precio_costo', 'id') : collect();
        $variantesMap = $varianteIdsBulk ? \App\Models\Variante::with('producto:id,precio_costo')->whereIn('id', $varianteIdsBulk)->get()->keyBy('id') : collect();

        $now = now();
        $rows = [];
        foreach ($this->carrito as $item) {
            $tipo       = $item['tipo'];
            $esCortesia = (bool) ($item['es_cortesia'] ?? false);
            $precio     = $esCortesia ? 0.0 : (float) $item['precio'];
            $costo      = 0.0;
            if ($tipo === 'producto') {
                $costo = (float) ($costosProd[$item['id']] ?? 0);
            } elseif ($tipo === 'variante') {
                $var   = $variantesMap[$item['id']] ?? null;
                $costo = (float) ($var?->costo ?? $var?->producto?->precio_costo ?? 0);
            }
            $tipoItem = match ($tipo) {
                'variante'  => TipoItem::Variante,
                'promocion' => TipoItem::Promocion,
                default     => TipoItem::Producto,
            };
            $cantidad = (float) $item['cantidad'];
            $calc     = OrdenDetalle::calcular($cantidad, $precio, $costo, 0, $igvRate);
            $rows[]   = [
                'orden_id'        => $orden->id,
                'tipo_item'       => $tipoItem->value,
                'producto_id'     => $tipo === 'producto'  ? $item['id'] : ($tipo === 'variante' ? ($item['producto_id'] ?? null) : null),
                'variante_id'     => $tipo === 'variante'  ? $item['id'] : null,
                'promocion_id'    => $tipo === 'promocion' ? $item['id'] : null,
                'descripcion'     => $esCortesia ? $item['nombre'] . ' (Cortesía)' : $item['nombre'],
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
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }
        \Illuminate\Support\Facades\DB::table('orden_detalles')->insert($rows);
    }

    private function postEnviar(Orden $orden, $empresa, string $etiqueta, string $redirectUrl = ''): void
    {
        $detallesParaStock = collect($this->carrito)->map(fn ($item) => [
            'producto_id'  => $item['tipo'] === 'producto'  ? $item['id'] : ($item['tipo'] === 'variante' ? ($item['producto_id'] ?? null) : null),
            'variante_id'  => $item['tipo'] === 'variante'  ? $item['id'] : null,
            'promocion_id' => $item['tipo'] === 'promocion' ? $item['id'] : null,
            'cantidad'     => $item['cantidad'],
        ])->values()->all();

        $this->carrito = [];
        $this->reservarStockDetalles($detallesParaStock, $empresa->id, false);

        $config = $empresa->cachedConfigImpresion();
        if ($config['tiene_impresion_directa']) {
            app(ImpresionDirectaService::class)->imprimirComandaOrden($orden, $empresa);
        }

        $orden->detalles()->update([
            'enviado_cocina'          => true,
            'cantidad_enviada_cocina' => \Illuminate\Support\Facades\DB::raw('cantidad'),
        ]);

        $orden->loadMissing([
            'detalles.producto.produccion',
            'detalles.promocion.detalles.producto.produccion',
            'detalles.promocion.detalles.variante.producto.produccion',
            'detalles.promocion.detalles.variante.valores.valor',
        ]);
        $itemsPorArea = [];
        foreach ($orden->detalles as $det) {
            if ($det->promocion_id && $det->promocion) {
                $subsByArea = [];
                foreach ($det->promocion->detalles as $pd) {
                    $produccion = $pd->variante_id ? $pd->variante?->producto?->produccion : $pd->producto?->produccion;
                    if (! $produccion || ! $produccion->impresora_id) continue;
                    $aKey = 'a' . $produccion->id;
                    if (! isset($subsByArea[$aKey])) {
                        $subsByArea[$aKey] = ['produccion' => $produccion, 'subs' => []];
                    }
                    if ($pd->variante_id && $pd->variante) {
                        $vals = $pd->variante->valores->map(fn($pav) => $pav->valor?->nombre)->filter()->join(' / ');
                        $sn   = ($pd->variante->producto?->nombre ?? $det->descripcion) . ($vals ? " ($vals)" : '');
                    } else {
                        $sn = $pd->producto?->nombre ?? $det->descripcion;
                    }
                    $subsByArea[$aKey]['subs'][] = $sn;
                }
                foreach ($subsByArea as $aKey => $aData) {
                    if (! isset($itemsPorArea[$aKey])) {
                        $itemsPorArea[$aKey] = ['nombre' => $aData['produccion']->nombre, 'nuevos' => [], 'cancelados' => []];
                    }
                    $itemsPorArea[$aKey]['nuevos'][] = [
                        'cant'   => (int) $det->cantidad,
                        'nombre' => $det->descripcion,
                        'sub'    => $aData['subs'],
                        'nota'   => $det->notas_item ?? '',
                    ];
                }
            } else {
                $produccion = $det->producto?->produccion;
                if (! $produccion) continue;
                $key = 'a' . $produccion->id;
                if (! isset($itemsPorArea[$key])) {
                    $itemsPorArea[$key] = ['nombre' => $produccion->nombre, 'nuevos' => [], 'cancelados' => []];
                }
                $itemsPorArea[$key]['nuevos'][] = ['cant' => (int) $det->cantidad, 'nombre' => $det->descripcion ?? '—', 'nota' => $det->notas_item ?? ''];
            }
        }
        $areas = array_values($itemsPorArea);

        if (empty($areas)) {
            Notification::make()->title('Pedido enviado ✓')->body("{$etiqueta} — pedido registrado.")->success()->send();
            $this->redirect($redirectUrl ?: MapaMesasPage::getUrl(tenant: $empresa));
            return;
        }

        $user      = auth()->user();
        $rolNombre = $user->roles()->where('roles.empresa_id', $empresa->id)->value('name') ?? '';
        $areasJson = json_encode($areas);

        $this->comandaRedirectUrl = $redirectUrl ?: MapaMesasPage::getUrl(tenant: $empresa);
        $this->lastComandaData = [
            'ordenId'   => $orden->id,
            'areasJson' => $areasJson,
            'mesa'      => $etiqueta,
            'cajero'    => $user->name,
            'rol'       => $rolNombre,
            'numero'    => $orden->codigo,
            'parcial'   => false,
        ];
        $this->dispatch('cerrar-carrito');
        $this->dispatch('imprimir-comanda-browser',
            ordenId:   $orden->id,
            areasJson: $areasJson,
            mesa:      $etiqueta,
            cajero:    $user->name,
            rol:       $rolNombre,
            numero:    $orden->codigo,
            parcial:   false,
        );
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
