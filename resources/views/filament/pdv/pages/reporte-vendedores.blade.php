<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">

@php
    $resumen    = $this->getResumen();
    $vendedores = $this->getVendedores();

    $margenColor = fn(float $util, float $ingresos) => match(true) {
        $ingresos <= 0 => 'cero',
        ($util / $ingresos * 100) >= 30 => 'alto',
        ($util / $ingresos * 100) >= 10 => 'medio',
        ($util / $ingresos * 100) >  0  => 'bajo',
        default => 'cero',
    };

    $urlDetalle = fn($v) =>
        \App\Filament\Pdv\Pages\ReporteVendedorVentasPage::getUrl() . '?' .
        http_build_query([
            'vendedorId'     => $v->vendedor_id,
            'vendedorNombre' => $v->vendedor,
            'fechaDesde'     => $this->filtroFechaDesde,
            'fechaHasta'     => $this->filtroFechaHasta,
        ]);
@endphp

<div class="vs-root">

    <div class="vs-title">
        <div>
            <h1>Reporte de Vendedores</h1>
            <p>Rendimiento de ventas por usuario</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="rg-kpis">
        <div class="rg-kpi rg-kpi--gray">
            <span class="rg-kpi__label">Vendedores</span>
            <span class="rg-kpi__value">{{ number_format($resumen['totalVendedores']) }}</span>
            <span class="rg-kpi__sub">activos</span>
        </div>
        <div class="rg-kpi rg-kpi--blue">
            <span class="rg-kpi__label">Total ventas</span>
            <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            <span class="rg-kpi__sub">completadas</span>
        </div>
        <div class="rg-kpi rg-kpi--purple">
            <span class="rg-kpi__label">Ingresos brutos</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['ingresosBrutos'], 2) }}</span>
            <span class="rg-kpi__sub">total facturado</span>
        </div>
        @if(($resumen['creditoPendiente'] ?? 0) > 0)
        <div class="rg-kpi rg-kpi--amber">
            <span class="rg-kpi__label">Crédito pendiente</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</span>
            <span class="rg-kpi__sub">por cobrar</span>
        </div>
        @endif
        <div class="rg-kpi rg-kpi--orange">
            <span class="rg-kpi__label">Costo de ventas</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['costoTotal'], 2) }}</span>
            <span class="rg-kpi__sub">costo productos</span>
        </div>
        <div class="rg-kpi rg-kpi--green">
            <span class="rg-kpi__label">Utilidad bruta</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['utilidadBruta'], 2) }}</span>
            <span class="rg-kpi__sub">ventas netas − costo</span>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="rg-form-wrap">{{ $this->form }}</div>

    {{-- Tabla --}}
    <div class="vs-panel">
        @if($vendedores->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
                <p>No hay ventas en el período seleccionado</p>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Vendedor</th>
                            <th class="rg-th-right">N° Ventas</th>
                            <th class="rg-th-right">Total facturado</th>
                            <th class="rg-th-right">Cobrado</th>
                            <th class="rg-th-right">Crédito pend.</th>
                            <th class="rg-th-right">Costo</th>
                            <th class="rg-th-right">Utilidad</th>
                            <th class="rg-th-right">Margen</th>
                            <th class="rg-th-right">Última venta</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $offset = ($vendedores->currentPage() - 1) * $vendedores->perPage(); @endphp
                        @foreach($vendedores as $i => $v)
                            @php
                                $util             = (float) $v->utilidad;
                                $ingresos         = (float) $v->ingresos;
                                $cobrado          = (float) $v->cobrado;
                                $creditoPendiente = (float) $v->credito_pendiente;
                                $margen           = $ingresos > 0 ? round($util / $ingresos * 100, 1) : 0.0;
                            @endphp
                            <tr wire:key="vend-{{ $v->vendedor_id }}">
                                <td class="rg-td-center" style="width:2.5rem;color:var(--vs-text-muted);font-size:.75rem;font-weight:700">
                                    {{ $offset + $i + 1 }}
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;font-weight:700;color:var(--vs-text)">{{ $v->vendedor }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">{{ number_format($v->cantidad) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format($ingresos, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--pos">S/ {{ number_format($cobrado, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    @if($creditoPendiente > 0)
                                        <span class="vs-badge vs-badge--credito">S/ {{ number_format($creditoPendiente, 2) }}</span>
                                    @else
                                        <span style="color:var(--vs-text-faint)">—</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--costo">S/ {{ number_format((float)$v->costo, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--util {{ $util >= 0 ? 'rg-monto--pos' : 'rg-monto--neg' }}">
                                        S/ {{ number_format($util, 2) }}
                                    </span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-margen rg-margen--{{ $margenColor($util, $ingresos) }}">
                                        {{ number_format($margen, 1) }}%
                                    </span>
                                </td>
                                <td class="rg-td-right">
                                    <span style="font-size:.75rem;color:var(--vs-text-muted)">
                                        {{ $v->ultima_venta ? \Carbon\Carbon::parse($v->ultima_venta)->format('d/m/Y') : '—' }}
                                    </span>
                                </td>
                                <td class="rg-td-right">
                                    <a href="{{ $urlDetalle($v) }}" class="rg-btn-link">
                                        Ver ventas →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($vendedores->hasPages())
                <div class="rg-pagination">{{ $vendedores->links() }}</div>
            @endif
        @endif
    </div>

</div>
</x-filament-panels::page>
