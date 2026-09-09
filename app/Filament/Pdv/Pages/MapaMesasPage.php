<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMesa;
use App\Enums\EstadoOrden;
use App\Enums\TipoOrigenOrden;
use App\Filament\Pdv\Resources\OrdenRest\OrdenRestResource;
use App\Models\Mesa;
use App\Models\Orden;
use App\Models\Piso;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class MapaMesasPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-table-cells';
    protected static ?string                $navigationLabel = 'Mapa de Mesas';
    protected static string|UnitEnum|null   $navigationGroup = 'Restaurante';
    protected static ?int                   $navigationSort  = 2;
    protected static ?string                $title           = 'Mapa de Mesas';
    protected string                        $view            = 'filament.pdv.pages.mapa-mesas';

    // Piso activo en el tab (se persiste en la URL para que el SPA no lo pierda)
    public ?int $pisoActivoId = null;

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('restaurante')
            && (auth()->user()?->can('comandas.ver') ?? false);
    }

    public function mount(): void
    {
        $primerPiso = $this->getPisos()->first();
        if ($this->pisoActivoId === null && $primerPiso) {
            $this->pisoActivoId = $primerPiso->id;
        }
    }

    // ── Queries ──────────────────────────────────────────────────────────────

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

    public function iniciarPedido(int $mesaId): void
    {
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
}
