<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">

@php
    $resumen = $this->getResumen();
    $ventas  = $this->getVentas();
    $top     = $this->getTopProductos();

    $margenColor = fn(float $m) => match(true) {
        $m >= 30  => 'alto',
        $m >= 10  => 'medio',
        $m > 0    => 'bajo',
        default   => 'cero',
    };
@endphp

<div class="vs-root">

    {{-- ══ TÍTULO ══ --}}
    <div class="vs-title">
        <div>
            <h1>Reporte de Ganancias</h1>
            <p>Utilidad bruta por ventas completadas</p>
        </div>
    </div>

    {{-- ══ KPIs ══ --}}
    <div class="rg-kpis">

        <div class="rg-kpi rg-kpi--gray">
            <span class="rg-kpi__label">Ventas</span>
            <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            <span class="rg-kpi__sub">completadas</span>
        </div>

        <div class="rg-kpi rg-kpi--blue">
            <span class="rg-kpi__label">Ingresos brutos</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['ingresosBrutos'], 2) }}</span>
            <span class="rg-kpi__sub">total facturado</span>
        </div>

        <div class="rg-kpi rg-kpi--purple">
            <span class="rg-kpi__label">Ventas netas</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['ventasNetas'], 2) }}</span>
            <span class="rg-kpi__sub">total − IGV</span>
        </div>

        <div class="rg-kpi rg-kpi--orange">
            <span class="rg-kpi__label">Costo de ventas</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['costoTotal'], 2) }}</span>
            <span class="rg-kpi__sub">costo de los productos</span>
        </div>

        <div class="rg-kpi rg-kpi--green">
            <span class="rg-kpi__label">Utilidad cobrada</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['utilidadRealizada'], 2) }}</span>
            <span class="rg-kpi__sub">ventas netas − costo (solo cobradas)</span>
        </div>

        <div class="rg-kpi rg-kpi--teal">
            <span class="rg-kpi__label">Margen bruto</span>
            <span class="rg-kpi__value">{{ number_format($resumen['margenPct'], 1) }}%</span>
            <span class="rg-kpi__sub">utilidad cobrada / ventas netas</span>
        </div>

        @if(($resumen['creditoPendiente'] ?? 0) > 0)
        <div class="rg-kpi rg-kpi--amber">
            <span class="rg-kpi__label">Crédito pendiente</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</span>
            <span class="rg-kpi__sub">utilidad en riesgo: S/ {{ number_format($resumen['utilidadEnRiesgo'], 2) }}</span>
        </div>
        @endif

    </div>

    {{-- ══ FILTROS ══ --}}
    <div class="rg-form-wrap">
        {{ $this->form }}
        @if($this->hayFiltros())
            <div class="rg-form-limpiar">
                <button wire:click="limpiarFiltros" class="vs-filter-reset">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    {{-- ══ LAYOUT: tabla + top productos ══ --}}
    <div class="rg-layout">

        {{-- ── TABLA DE VENTAS ── --}}
        <div class="vs-panel rg-layout__main">

            @if($ventas->isEmpty())
                <div class="rg-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <p>No hay ventas en el periodo seleccionado</p>
                </div>
            @else

                <div class="rg-table-scroll">
                    <table class="rg-table rg-table--wide">
                        <colgroup>
                            <col class="rg-col-comp">
                            <col class="rg-col-fecha">
                            <col>{{-- cliente: espacio libre --}}
                            <col>{{-- pago --}}
                            <col class="rg-col-monto">
                            <col class="rg-col-monto">
                            <col class="rg-col-monto">
                            <col class="rg-col-monto">
                            <col class="rg-col-margen">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Comprobante</th>
                                <th>Fecha</th>
                                <th>Cliente / Vendedor</th>
                                <th>Pago</th>
                                <th class="rg-th-right">Total</th>
                                <th class="rg-th-right">Venta neta</th>
                                <th class="rg-th-right">Costo</th>
                                <th class="rg-th-right">Utilidad</th>
                                <th class="rg-th-right">Margen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ventas as $venta)
                                @php
                                    $ventaNeta = (float) $venta->venta_neta;
                                    $costo     = (float) $venta->costo_total;
                                    $utilidad  = $ventaNeta - $costo;
                                    $margen    = $ventaNeta > 0
                                        ? round($utilidad / $ventaNeta * 100, 1)
                                        : 0.0;
                                    $comp      = ($venta->serie?->serie ?? '---') . '-' . $venta->correlativo;
                                    $esCredit  = ($venta->estado_pago ?? '') === 'pendiente';
                                @endphp
                                <tr wire:key="rg-{{ $venta->id }}">

                                    <td>
                                        <span class="rg-comprobante">{{ $comp }}</span>
                                    </td>

                                    <td>
                                        <div class="rg-fecha">
                                            <span class="rg-fecha__dia">{{ $venta->created_at->format('d/m/Y') }}</span>
                                            <span class="rg-fecha__hora">{{ $venta->created_at->format('H:i') }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div style="display:flex;flex-direction:column;gap:.1rem">
                                            <span style="font-size:.8rem;font-weight:600;color:var(--vs-text)">{{ $venta->cliente_nombre ?: '—' }}</span>
                                            <span style="font-size:.7rem;color:var(--vs-text-muted)">{{ $venta->vendedor?->name ?? '—' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        @if($esCredit)
                                            <div style="display:flex;flex-direction:column;gap:.15rem">
                                                <span class="vs-badge vs-badge--credito">Crédito</span>
                                                <span style="font-size:.7rem;color:#d97706">pend. S/ {{ number_format((float)$venta->saldo_pendiente, 2) }}</span>
                                            </div>
                                        @else
                                            <span style="font-size:.75rem;color:var(--vs-text-muted)">Contado</span>
                                        @endif
                                    </td>

                                    <td class="rg-td-right">
                                        <span class="rg-monto">S/ {{ number_format($venta->total, 2) }}</span>
                                    </td>

                                    <td class="rg-td-right">
                                        <span class="rg-monto rg-monto--neta">S/ {{ number_format($ventaNeta, 2) }}</span>
                                    </td>

                                    <td class="rg-td-right">
                                        <span class="rg-monto rg-monto--costo">S/ {{ number_format($costo, 2) }}</span>
                                    </td>

                                    <td class="rg-td-right">
                                        @if($esCredit)
                                            <span class="rg-monto" style="color:#d97706">
                                                S/ {{ number_format(abs($utilidad), 2) }}
                                            </span>
                                            <span style="font-size:.65rem;color:#d97706;display:block">en riesgo</span>
                                        @else
                                            <span class="rg-monto rg-monto--util {{ $utilidad >= 0 ? 'rg-monto--pos' : 'rg-monto--neg' }}">
                                                {{ $utilidad >= 0 ? '' : '- ' }}S/ {{ number_format(abs($utilidad), 2) }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="rg-td-right">
                                        <span class="rg-margen rg-margen--{{ $esCredit ? 'cero' : $margenColor($margen) }}">
                                            {{ $esCredit ? '—' : number_format($margen, 1).'%' }}
                                        </span>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($ventas->hasPages())
                    <div class="rg-pagination">
                        {{ $ventas->links() }}
                    </div>
                @endif

            @endif
        </div>{{-- /vs-panel --}}

        {{-- ── TOP PRODUCTOS ── --}}
        @if($top->isNotEmpty())
        <div class="rg-top rg-layout__side">
            <div class="rg-top__header">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                </svg>
                Top productos por utilidad
            </div>
            <div class="rg-top__list">
                @foreach($top as $i => $prod)
                    @php
                        $ingrProd  = (float) $prod->ingresos;
                        $utilProd  = (float) $prod->utilidad;
                        $margenProd = $ingrProd > 0 ? round($utilProd / $ingrProd * 100, 1) : 0;
                    @endphp
                    <div class="rg-top__item">
                        <span class="rg-top__rank">{{ $i + 1 }}</span>
                        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:.1rem">
                            <span class="rg-top__nombre" title="{{ $prod->descripcion }}">{{ $prod->descripcion }}</span>
                            <span class="rg-top__veces">{{ number_format($prod->total_qty, 0) }} uds · {{ $prod->veces }}x · {{ $margenProd }}%</span>
                        </div>
                        <span class="rg-top__util">S/ {{ number_format($utilProd, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>{{-- /layout grid --}}

</div>{{-- /vs-root --}}

</x-filament-panels::page>
