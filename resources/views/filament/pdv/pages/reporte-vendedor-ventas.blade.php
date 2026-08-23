<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">

@php
    $resumen = $this->getResumen();
@endphp

<div class="vs-root">

    <div class="vs-title">
        <div>
            <h1>{{ $this->vendedorNombre ?? 'Vendedor' }}</h1>
            <p>
                Ventas completadas
                @if($this->fechaDesde || $this->fechaHasta)
                    · {{ $this->fechaDesde ? \Carbon\Carbon::parse($this->fechaDesde)->format('d/m/Y') : '…' }}
                    – {{ $this->fechaHasta ? \Carbon\Carbon::parse($this->fechaHasta)->format('d/m/Y') : 'hoy' }}
                @endif
            </p>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <div class="rg-kpi rg-kpi--blue" style="min-width:120px">
                <span class="rg-kpi__label">Ventas</span>
                <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            </div>
            <div class="rg-kpi rg-kpi--purple" style="min-width:130px">
                <span class="rg-kpi__label">Cobrado</span>
                <span class="rg-kpi__value">S/ {{ number_format($resumen['cobrado'], 2) }}</span>
            </div>
            @if(($resumen['creditoPendiente'] ?? 0) > 0)
            <div class="rg-kpi rg-kpi--amber" style="min-width:130px">
                <span class="rg-kpi__label">Crédito pend.</span>
                <span class="rg-kpi__value">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</span>
            </div>
            @endif
            <div class="rg-kpi rg-kpi--green" style="min-width:130px">
                <span class="rg-kpi__label">Utilidad</span>
                <span class="rg-kpi__value">S/ {{ number_format($resumen['utilidad'], 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="rg-form-wrap" x-data="{ open: {{ $this->hayFiltros() ? 'true' : 'false' }} }">

        <button type="button" class="rg-filtros-toggle" @click="open = !open">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
            </svg>
            <span>Filtros</span>
            @if($this->hayFiltros())
                <span class="rg-filtros-activo-dot" title="Hay filtros activos"></span>
            @endif
            <svg class="rg-filtros-chevron" :class="{ 'rg-filtros-chevron--open': open }"
                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="2" stroke="currentColor" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        <div class="rg-filtros-body" :class="{ 'rg-filtros-body--open': open }">
            {{ $this->form }}
            @if($this->hayFiltros())
                <div class="rg-form-limpiar">
                    <button wire:click="limpiarFiltros" class="vs-filter-reset">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                             stroke-width="1.5" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                        Limpiar filtros
                    </button>
                </div>
            @endif
        </div>

    </div>

    {{-- Tabla Filament --}}
    {{ $this->table }}

</div>
</x-filament-panels::page>
