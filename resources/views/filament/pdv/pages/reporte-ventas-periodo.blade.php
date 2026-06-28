<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">

@php
    $resumen    = $this->getResumen();
    $periodos   = $this->getPeriodos();
    $agrupacion = $this->filtroAgrupacion ?? 'dia';

    $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
              '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];

    $formatPeriodo = function(string $p) use ($agrupacion, $meses): string {
        if ($agrupacion === 'mes') {
            [$year, $month] = explode('-', $p);
            return ($meses[$month] ?? $month) . ' ' . $year;
        }
        return \Carbon\Carbon::parse($p)->format('d/m/Y');
    };

    $urlDetalle = fn(string $periodo) =>
        \App\Filament\Pdv\Pages\ReportePeriodoVentasPage::getUrl() . '?' .
        http_build_query(['periodo' => $periodo, 'agrupacion' => $agrupacion]);
@endphp

<div class="vs-root">

    <div class="vs-title">
        <div>
            <h1>Ventas por período</h1>
            <p>Resumen de ventas agrupadas por {{ $agrupacion === 'mes' ? 'mes' : 'día' }}</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="rg-kpis">
        <div class="rg-kpi rg-kpi--gray">
            <span class="rg-kpi__label">Ventas</span>
            <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            <span class="rg-kpi__sub">completadas</span>
        </div>
        <div class="rg-kpi rg-kpi--blue">
            <span class="rg-kpi__label">Total facturado</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['ingresosBrutos'], 2) }}</span>
            <span class="rg-kpi__sub">suma de todas las ventas</span>
        </div>
        <div class="rg-kpi rg-kpi--purple">
            <span class="rg-kpi__label">Total sin IGV</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['ventasNetas'], 2) }}</span>
            <span class="rg-kpi__sub">total − IGV (tickets incluidos)</span>
        </div>
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
        @if($periodos->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                <p>No hay ventas en el período seleccionado</p>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>{{ $agrupacion === 'mes' ? 'Mes' : 'Fecha' }}</th>
                            <th class="rg-th-right">N° Ventas</th>
                            <th class="rg-th-right">Total facturado</th>
                            <th class="rg-th-right">IGV</th>
                            <th class="rg-th-right">Costo</th>
                            <th class="rg-th-right">Utilidad</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($periodos as $p)
                            @php $util = (float) $p->utilidad; @endphp
                            <tr wire:key="periodo-{{ $p->periodo }}">
                                <td>
                                    <span style="font-size:.8125rem;font-weight:600;color:var(--vs-text)">{{ $formatPeriodo($p->periodo) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">{{ number_format($p->cantidad) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format((float)$p->ingresos, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto" style="color:var(--vs-text-muted)">S/ {{ number_format((float)$p->igv, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--costo">S/ {{ number_format((float)$p->costo, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--util {{ $util >= 0 ? 'rg-monto--pos' : 'rg-monto--neg' }}">
                                        S/ {{ number_format($util, 2) }}
                                    </span>
                                </td>
                                <td class="rg-td-right">
                                    <a href="{{ $urlDetalle($p->periodo) }}" class="rg-btn-link">
                                        Ver ventas →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($periodos->hasPages())
                <div class="rg-pagination">{{ $periodos->links() }}</div>
            @endif
        @endif
    </div>

</div>
</x-filament-panels::page>
