<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/gestion-inventario.css') }}?v={{ filemtime(public_path('css/gestion-inventario.css')) }}">

@php $stats = $this->getStats(); @endphp

<div class="gi-root">

    {{-- ══ KPIs ══ --}}
    <div class="gi-kpis">

        <div class="gi-kpi gi-kpi--gray">
            <span class="gi-kpi__label">Total productos</span>
            <span class="gi-kpi__value">{{ number_format($stats['total']) }}</span>
            <span class="gi-kpi__sub">en inventario activo</span>
            <svg class="gi-kpi__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
            </svg>
        </div>

        <div class="gi-kpi gi-kpi--green">
            <span class="gi-kpi__label">Disponible</span>
            <span class="gi-kpi__value">{{ number_format($stats['disponible']) }}</span>
            <span class="gi-kpi__sub">con stock suficiente</span>
            <svg class="gi-kpi__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <div class="gi-kpi gi-kpi--amber">
            <span class="gi-kpi__label">Por agotarse</span>
            <span class="gi-kpi__value">{{ number_format($stats['por_agotarse']) }}</span>
            <span class="gi-kpi__sub">cerca del stock mínimo</span>
            <svg class="gi-kpi__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>

        <div class="gi-kpi gi-kpi--red">
            <span class="gi-kpi__label">Agotado</span>
            <span class="gi-kpi__value">{{ number_format($stats['agotado']) }}</span>
            <span class="gi-kpi__sub">sin stock disponible</span>
            <svg class="gi-kpi__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

    </div>

    {{-- ══ TABLA ══ --}}
    {{ $this->table }}

</div>

</x-filament-panels::page>
