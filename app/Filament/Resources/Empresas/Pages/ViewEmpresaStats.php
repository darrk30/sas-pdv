<?php

namespace App\Filament\Resources\Empresas\Pages;

use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Venta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

class ViewEmpresaStats extends Page
{
    protected static string $resource = EmpresaResource::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected string $view = 'filament.pages.empresa-stats';

    public ?Empresa $record = null;

    public function mount(Empresa $record): void
    {
        $record->load('suscripcion.plan');
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return 'Estadísticas — ' . $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editar')
                ->label('Editar empresa')
                ->icon('heroicon-o-pencil')
                ->url(EmpresaResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    // ── Datos para la vista ───────────────────────────────────────────────────

    public function getViewData(): array
    {
        $id  = $this->record->id;
        $hoy = now();

        // ── Ventas ────────────────────────────────────────────────────────────
        $ventasHoy = Venta::where('empresa_id', $id)
            ->where('estado', 'completada')
            ->whereDate('created_at', $hoy)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as monto')
            ->first();

        $ventasMes = Venta::where('empresa_id', $id)
            ->where('estado', 'completada')
            ->whereMonth('created_at', $hoy->month)
            ->whereYear('created_at', $hoy->year)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as monto')
            ->first();

        $ventasAnio = Venta::where('empresa_id', $id)
            ->where('estado', 'completada')
            ->whereYear('created_at', $hoy->year)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as monto')
            ->first();

        $ticketPromedio = $ventasMes->n > 0
            ? round($ventasMes->monto / $ventasMes->n, 2)
            : 0;

        // ── Últimas 10 ventas ─────────────────────────────────────────────────
        $ultimasVentas = Venta::where('ventas.empresa_id', $id)
            ->where('ventas.estado', 'completada')
            ->leftJoin('series', 'ventas.serie_id', '=', 'series.id')
            ->latest('ventas.created_at')
            ->limit(10)
            ->get(['ventas.id', 'ventas.tipo', 'series.serie as serie_codigo', 'ventas.correlativo', 'ventas.total', 'ventas.created_at']);

        // ── Suscripción ───────────────────────────────────────────────────────
        $sus       = $this->record->suscripcion;
        $diasRestantes = $sus?->fecha_fin
            ? max(0, (int) now()->startOfDay()->diffInDays($sus->fecha_fin, false))
            : null;

        return [
            'empresa'        => $this->record,
            'sus'            => $sus,
            'diasRestantes'  => $diasRestantes,
            'totalProductos' => Producto::where('empresa_id', $id)->count(),
            'totalClientes'  => Cliente::where('empresa_id', $id)->count(),
            'totalUsuarios'  => $this->record->usuarios()->count(),
            'ventasHoy'      => $ventasHoy,
            'ventasMes'      => $ventasMes,
            'ventasAnio'     => $ventasAnio,
            'ticketPromedio' => $ticketPromedio,
            'ultimasVentas'  => $ultimasVentas,
            'modulosActivos' => array_keys(array_filter($this->record->modulos_activos ?? [])),
        ];
    }
}
