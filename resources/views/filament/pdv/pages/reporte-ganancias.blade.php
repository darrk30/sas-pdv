<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">

@php
    $resumen = $this->getResumen();

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
            <span class="rg-kpi__label">Ventas completadas</span>
            <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            <span class="rg-kpi__sub">S/ {{ number_format($resumen['ingresosBrutos'], 2) }} facturado</span>
        </div>

        <div class="rg-kpi rg-kpi--green">
            <span class="rg-kpi__label">Utilidad cobrada</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['utilidadRealizada'], 2) }}</span>
            <span class="rg-kpi__sub">costo: S/ {{ number_format($resumen['costoTotal'], 2) }}</span>
        </div>

        <div class="rg-kpi rg-kpi--teal">
            <span class="rg-kpi__label">Margen bruto</span>
            <span class="rg-kpi__value">{{ number_format($resumen['margenPct'], 1) }}%</span>
            <span class="rg-kpi__sub">neto: S/ {{ number_format($resumen['ventasNetas'], 2) }}</span>
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

    {{-- ══ TABLA FILAMENT ══ --}}
    {{ $this->table }}

</div>{{-- /vs-root --}}

</x-filament-panels::page>
