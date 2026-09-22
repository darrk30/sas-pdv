<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMesa;
use App\Enums\EstadoOrden;
use App\Enums\EstadoSunat;
use App\Enums\TipoOrigenOrden;
use App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource;
use App\Models\Mesa;
use App\Models\Orden;
use App\Models\Piso;
use App\Models\User;
use App\Models\Venta;
use App\Services\ImpresionDirectaService;
use App\Services\VentaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use UnitEnum;

class MapaMesasPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-building-storefront';
    protected static ?string                $navigationLabel = 'Restaurante';
    protected static string|UnitEnum|null   $navigationGroup = 'Restaurante';
    protected static ?int                   $navigationSort  = 2;
    protected static ?string                $title           = '';
    protected string                        $view            = 'filament.pdv.pages.mapa-mesas';

    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [
            static::getRouteName(),
            'filament.pdv.resources.orden-rest.*',
        ];
    }

    // Piso activo en el tab (se persiste en la URL para que el SPA no lo pierda)
    public ?int $pisoActivoId = null;

    // 'mesas' | 'llevar' | 'delivery'
    public string $vistaActual = 'mesas';

    // ID de empresa para el canal privado de Reverb (aislado por tenant)
    public int $empresaId = 0;

    // ── Modal detalle orden pagada ────────────────────────────────────────────
    public ?int    $detalleOrdenId           = null;
    public ?string $detalleOrdenTipo         = null;
    public ?string $detalleDeliveryNombre    = null;
    public ?string $detalleDeliveryTelefono  = null;
    public ?string $detalleDeliveryDireccion = null;
    public ?int    $detalleRepartidorId      = null;

    // ── Comanda (para el modal JS) ────────────────────────────────────────────
    public array  $lastComandaData   = [];
    public string $comandaRedirectUrl = '';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('restaurante')
            && (auth()->user()?->can('restaurante.ver') ?? false);
    }

    public function mount(): void
    {
        $this->empresaId = (int) Filament::getTenant()->id;

        $primerPiso = $this->getPisos()->first();
        if ($this->pisoActivoId === null && $primerPiso) {
            $this->pisoActivoId = $primerPiso->id;
        }

        $vista = request()->query('vista', '');
        if (in_array($vista, ['mesas', 'llevar', 'delivery'])) {
            $this->vistaActual = $vista;
        }
    }

    /** Recibe el broadcast de Reverb cuando cambia el estado de alguna mesa del tenant */
    #[On('echo-private:mesas.{empresaId},.MesaActualizada')]
    public function refrescarMesas(): void
    {
        // Livewire re-renderiza el componente automáticamente al ejecutar este método
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    public function getLlevarOrdenes(): Collection
    {
        return Orden::where('empresa_id', Filament::getTenant()->id)
            ->where('tipo_origen', TipoOrigenOrden::Llevar->value)
            ->whereIn('estado', [
                EstadoOrden::PendientePago->value,
                EstadoOrden::EnPreparacion->value,
                EstadoOrden::EnCamino->value,
            ])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getDeliveryOrdenes(): Collection
    {
        return Orden::where('empresa_id', Filament::getTenant()->id)
            ->where('tipo_origen', TipoOrigenOrden::Delivery->value)
            ->whereIn('estado', [
                EstadoOrden::PendientePago->value,
                EstadoOrden::EnPreparacion->value,
                EstadoOrden::EnCamino->value,
            ])
            ->with('repartidor:id,name')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function iniciarPedidoDelivery(): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.crear'), 403);

        $this->redirect(
            OrdenRestResource::getUrl('create', tenant: Filament::getTenant()) . '?tipo=delivery'
        );
    }

    public function getPisos(): Collection
    {
        return Piso::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', true)
            ->orderBy('orden')
            ->with([
                'mesas' => fn ($q) => $q
                    ->where('estado', true)
                    ->orderBy('orden')
                    ->with([
                        'ordenActiva' => fn ($q2) => $q2->with([]),
                    ]),
            ])
            ->get();
    }

    // ── Acciones Livewire ────────────────────────────────────────────────────

    public function cambiarVista(string $vista): void
    {
        $this->vistaActual = $vista;
    }

    public function iniciarPedidoLlevar(): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.crear'), 403);

        $this->redirect(
            OrdenRestResource::getUrl('create', tenant: Filament::getTenant()) . '?tipo=llevar'
        );
    }

    public function iniciarPedido(int $mesaId): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.crear'), 403);

        $empresa = Filament::getTenant();
        $mesa    = Mesa::where('empresa_id', $empresa->id)->findOrFail($mesaId);

        if (! $mesa->estaLibre()) {
            // Mesa ya ocupada → ir al pedido existente
            $orden = $mesa->ordenActiva;
            if ($orden) {
                $this->redirect(
                    OrdenRestResource::getUrl('edit', ['record' => $orden->id], tenant: $empresa)
                );
            }
            return;
        }

        // Redirigir al formulario de nuevo pedido con la mesa preseleccionada
        $this->redirect(
            OrdenRestResource::getUrl('create', tenant: $empresa) . '?mesa_id=' . $mesaId
        );
    }

    /** @deprecated Se mantiene por compatibilidad con blade viejo */
    public function abrirMesa(int $mesaId): void
    {
        $this->iniciarPedido($mesaId);
    }

    public function marcarPagando(int $mesaId): void
    {
        $empresa = Filament::getTenant();
        $mesa    = Mesa::where('empresa_id', $empresa->id)->findOrFail($mesaId);

        if ($mesa->estado_ocupacion !== EstadoMesa::Ocupada) {
            return;
        }

        $mesa->marcarPagando();

        Notification::make()->title("Mesa {$mesa->nombre} marcada como pagando")->success()->send();
    }

    public function liberarMesa(int $mesaId): void
    {
        $empresa = Filament::getTenant();
        $mesa    = Mesa::where('empresa_id', $empresa->id)
            ->with('ordenActiva')
            ->findOrFail($mesaId);

        // Cancelar la orden activa si existe y está vacía
        if ($orden = $mesa->ordenActiva) {
            if ($orden->detalles()->count() === 0) {
                $orden->update(['estado' => EstadoOrden::Cancelada->value]);
            }
        }

        $mesa->marcarLibre();

        Notification::make()->title("Mesa {$mesa->nombre} liberada")->success()->send();
    }

    public function seleccionarPiso(int $pisoId): void
    {
        $this->pisoActivoId = $pisoId;
    }

    public function marcarEnCamino(int $ordenId): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.editar'), 403);

        $orden = Orden::where('empresa_id', Filament::getTenant()->id)
            ->whereIn('estado', [EstadoOrden::PendientePago->value, EstadoOrden::EnPreparacion->value])
            ->findOrFail($ordenId);

        $orden->update(['estado' => EstadoOrden::EnCamino->value]);

        Notification::make()->title('Pedido en camino')->success()->send();
    }

    public function marcarEntregado(int $ordenId): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.editar'), 403);

        $orden = Orden::where('empresa_id', Filament::getTenant()->id)
            ->whereIn('estado', [
                EstadoOrden::PendientePago->value,
                EstadoOrden::EnPreparacion->value,
                EstadoOrden::EnCamino->value,
            ])
            ->findOrFail($ordenId);

        $orden->update(['estado' => EstadoOrden::Entregado->value]);

        Notification::make()->title('Pedido entregado')->success()->send();
    }

    // ── Modal detalle orden pagada ────────────────────────────────────────────

    public function abrirDetalleOrden(int $ordenId): void
    {
        $orden = Orden::where('empresa_id', Filament::getTenant()->id)
            ->whereNotNull('venta_id')
            ->findOrFail($ordenId);

        $this->detalleOrdenId           = $orden->id;
        $this->detalleOrdenTipo         = $orden->tipo_origen instanceof TipoOrigenOrden
            ? $orden->tipo_origen->value
            : (string) $orden->tipo_origen;
        $this->detalleDeliveryNombre    = $orden->cliente_nombre;
        $this->detalleDeliveryTelefono  = $orden->cliente_telefono;
        $this->detalleDeliveryDireccion = $orden->cliente_direccion;
        $this->detalleRepartidorId      = $orden->repartidor_id;
    }

    public function cerrarDetalleOrden(): void
    {
        $this->detalleOrdenId           = null;
        $this->detalleOrdenTipo         = null;
        $this->detalleDeliveryNombre    = null;
        $this->detalleDeliveryTelefono  = null;
        $this->detalleDeliveryDireccion = null;
        $this->detalleRepartidorId      = null;
    }

    public function guardarDatosDelivery(): void
    {
        abort_unless(auth()->user()?->can('restaurante.pedido.editar'), 403);

        if (! $this->detalleOrdenId) return;

        $orden = Orden::where('empresa_id', Filament::getTenant()->id)->findOrFail($this->detalleOrdenId);

        $orden->update([
            'cliente_nombre'    => $this->detalleDeliveryNombre,
            'cliente_telefono'  => $this->detalleDeliveryTelefono,
            'cliente_direccion' => $this->detalleDeliveryDireccion,
            'repartidor_id'     => $this->detalleRepartidorId ?: null,
        ]);

        Notification::make()->title('Datos de delivery actualizados')->success()->send();
    }

    public function getUsuariosRepartidor(): Collection
    {
        return User::where('empresa_id', Filament::getTenant()->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    // ── Cancelar orden pagada (anula también la venta) ────────────────────────

    public function cancelarOrdenPagadaAction(): Action
    {
        return Action::make('cancelarOrdenPagada')
            ->requiresConfirmation()
            ->modalHeading('Cancelar pedido cobrado')
            ->modalDescription(function (array $arguments): string {
                $orden = Orden::with('venta.serie')
                    ->where('empresa_id', Filament::getTenant()->id)
                    ->find($arguments['ordenId'] ?? 0);
                $comprobante = $orden?->venta
                    ? (($orden->venta->serie?->serie ?? '---') . '-' . $orden->venta->correlativo)
                    : 'la venta asociada';
                return "Se anulará el pedido y el comprobante {$comprobante}. El stock se revertirá. Esta acción no se puede deshacer.";
            })
            ->modalSubmitActionLabel('Sí, cancelar y anular venta')
            ->modalCancelActionLabel('No, volver')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->action(function (array $arguments): void {
                abort_unless(auth()->user()?->can('restaurante.pedido.eliminar'), 403);

                $empresa = Filament::getTenant();
                $orden   = Orden::with(['venta.serie', 'detalles.producto.produccion'])
                    ->where('empresa_id', $empresa->id)
                    ->whereNotNull('venta_id')
                    ->findOrFail($arguments['ordenId']);

                $venta = $orden->venta;

                // Bloquear solo si ya fue tramitado ante SUNAT (no se puede revertir en el sistema)
                $estadosSunatBloqueantes = [
                    EstadoSunat::Enviado,
                    EstadoSunat::EnResumen,
                    EstadoSunat::Aceptado,
                    EstadoSunat::Observado,
                    EstadoSunat::PorDarBaja,
                    EstadoSunat::DadoDeBaja,
                ];
                if ($venta && $venta->estado_sunat !== null && in_array($venta->estado_sunat, $estadosSunatBloqueantes)) {
                    Notification::make()
                        ->title('No se puede anular')
                        ->body('El comprobante ya fue enviado a SUNAT. Emite una Nota de Crédito.')
                        ->danger()
                        ->send();
                    return;
                }

                // Anular venta (stock + kardex + transacciones)
                if ($venta) {
                    app(VentaService::class)->anular($venta, $empresa->id);
                }

                // Construir datos de comanda ANTES de cancelar
                $itemsPorArea = [];
                foreach ($orden->detalles as $d) {
                    $produccion = $d->producto?->produccion;
                    if (! $produccion) continue;
                    $key = 'a' . $produccion->id;
                    if (! isset($itemsPorArea[$key])) {
                        $itemsPorArea[$key] = ['nombre' => $produccion->nombre, 'nuevos' => [], 'cancelados' => []];
                    }
                    $itemsPorArea[$key]['cancelados'][] = [
                        'cant'   => (int) $d->cantidad,
                        'nombre' => $d->descripcion ?? '—',
                        'nota'   => '',
                    ];
                }

                // Cancelar la orden
                $orden->update(['estado' => EstadoOrden::Cancelada->value]);
                $orden->mesa?->marcarLibre();
                $this->cerrarDetalleOrden();

                Notification::make()->title('Pedido cancelado y venta anulada')->success()->send();

                // Dispatch comanda si hay áreas de producción
                if (! empty($itemsPorArea)) {
                    $user      = auth()->user();
                    $rolNombre = $user->roles()->where('roles.empresa_id', $empresa->id)->value('name') ?? '';
                    $mesaNombre = match ($orden->tipo_origen) {
                        TipoOrigenOrden::Delivery => 'Delivery · ' . ($orden->cliente_nombre ?? ''),
                        TipoOrigenOrden::Llevar   => 'Para llevar · ' . ($orden->cliente_nombre ?? ''),
                        default                   => $orden->mesa?->nombre ?? '',
                    };
                    $areasJson = json_encode(array_values($itemsPorArea));

                    // Intentar impresión directa primero
                    try {
                        app(ImpresionDirectaService::class)->imprimirComandaCancelacion(
                            $orden, $empresa, array_values($itemsPorArea)
                        );
                    } catch (\Throwable) {}

                    $this->lastComandaData = [
                        'ordenId'     => $orden->id,
                        'areasJson'   => $areasJson,
                        'mesa'        => $mesaNombre,
                        'cajero'      => $user->name,
                        'rol'         => $rolNombre,
                        'numero'      => $orden->codigo,
                        'parcial'     => false,
                        'descripcion' => 'Pedido cancelado',
                    ];
                    $this->dispatch('imprimir-comanda-browser',
                        ordenId:     $orden->id,
                        areasJson:   $areasJson,
                        mesa:        $mesaNombre,
                        cajero:      $user->name,
                        rol:         $rolNombre,
                        numero:      $orden->codigo,
                        parcial:     false,
                        descripcion: 'Pedido cancelado',
                    );
                }
            });
    }

    public function cancelarOrdenAction(): Action
    {
        return Action::make('cancelarOrden')
            ->requiresConfirmation()
            ->modalHeading('Cancelar orden')
            ->modalDescription('¿Estás seguro de que deseas cancelar esta orden? Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Sí, cancelar')
            ->modalCancelActionLabel('No, volver')
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->action(function (array $arguments): void {
                abort_unless(auth()->user()?->can('restaurante.pedido.eliminar'), 403);

                $orden = Orden::where('empresa_id', Filament::getTenant()->id)
                    ->whereIn('estado', [
                        EstadoOrden::PendientePago->value,
                        EstadoOrden::EnPreparacion->value,
                        EstadoOrden::EnCamino->value,
                    ])
                    ->whereNull('venta_id')
                    ->findOrFail($arguments['ordenId']);

                $orden->update(['estado' => EstadoOrden::Cancelada->value]);
                $orden->mesa?->marcarLibre();

                Notification::make()->title('Orden cancelada')->success()->send();
            });
    }
}
