<x-filament-panels::page>
@php $resumen = $this->getResumen(); @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

    <div class="fi-wi-stats-overview-stat">
        <div class="fi-wi-stats-overview-stat-content">
            <div class="fi-wi-stats-overview-stat-label-ctn">
                <span class="fi-wi-stats-overview-stat-label">Ventas</span>
            </div>
            <div class="fi-wi-stats-overview-stat-value">{{ number_format($resumen['cantidad']) }}</div>
            <div class="fi-wi-stats-overview-stat-description">completadas</div>
        </div>
    </div>

    <div class="fi-wi-stats-overview-stat">
        <div class="fi-wi-stats-overview-stat-content">
            <div class="fi-wi-stats-overview-stat-label-ctn">
                <span class="fi-wi-stats-overview-stat-label">Cobrado</span>
            </div>
            <div class="fi-wi-stats-overview-stat-value">S/ {{ number_format($resumen['cobrado'], 2) }}</div>
            <div class="fi-wi-stats-overview-stat-description">monto recibido</div>
        </div>
    </div>

    <div class="fi-wi-stats-overview-stat">
        <div class="fi-wi-stats-overview-stat-content">
            <div class="fi-wi-stats-overview-stat-label-ctn">
                <span class="fi-wi-stats-overview-stat-label">Utilidad</span>
            </div>
            <div class="fi-wi-stats-overview-stat-value">S/ {{ number_format($resumen['utilidad'], 2) }}</div>
            <div class="fi-wi-stats-overview-stat-description">venta neta − costo</div>
        </div>
    </div>

    @if(($resumen['creditoPendiente'] ?? 0) > 0)
    <div class="fi-wi-stats-overview-stat">
        <div class="fi-wi-stats-overview-stat-content">
            <div class="fi-wi-stats-overview-stat-label-ctn">
                <span class="fi-wi-stats-overview-stat-label">Crédito pendiente</span>
            </div>
            <div class="fi-wi-stats-overview-stat-value">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</div>
            <div class="fi-wi-stats-overview-stat-description">por cobrar</div>
        </div>
    </div>
    @endif

</div>

{{ $this->table }}

</x-filament-panels::page>
