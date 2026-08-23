<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/reporte-productos.css') }}?v={{ filemtime(public_path('css/reporte-productos.css')) }}">

@php $resumen = $this->getResumen(); @endphp

<div class="rp-root">

    {{-- ══ KPIs ══ --}}
    <div class="rp-kpis">

        <div class="rp-kpi rp-kpi--gray">
            <span class="rp-kpi__label">Productos</span>
            <span class="rp-kpi__value">{{ number_format($resumen['productos']) }}</span>
            <span class="rp-kpi__sub">{{ number_format($resumen['unidades'], 2) }} unidades vendidas</span>
        </div>

        <div class="rp-kpi rp-kpi--blue">
            <span class="rp-kpi__label">Ingresos totales</span>
            <span class="rp-kpi__value">S/ {{ number_format($resumen['ingresos'], 2) }}</span>
            <span class="rp-kpi__sub">costo: S/ {{ number_format($resumen['costo'], 2) }}</span>
        </div>

        <div class="rp-kpi rp-kpi--green">
            <span class="rp-kpi__label">Utilidad total</span>
            <span class="rp-kpi__value">S/ {{ number_format($resumen['utilidad'], 2) }}</span>
            <span class="rp-kpi__sub">margen: {{ number_format($resumen['margenPct'], 1) }}%</span>
        </div>

    </div>

    {{-- ══ FILTROS ══ --}}
    <div class="rp-filtros">
        {{ $this->form }}
        @if($this->hayFiltros())
            <div class="rp-filtros__limpiar">
                <button wire:click="limpiarFiltros" class="rp-btn-limpiar">
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

</div>{{-- /rp-root --}}

</x-filament-panels::page>
