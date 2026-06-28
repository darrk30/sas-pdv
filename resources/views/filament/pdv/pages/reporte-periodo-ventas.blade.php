<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">

@php
    $resumen = $this->getResumen();
    $ventas  = $this->getVentas();
    $label   = $this->getPeriodoLabel();
@endphp

<div class="vs-root">

    <div class="vs-title">
        <div>
            <h1>{{ $label }}</h1>
            <p>Ventas completadas del período</p>
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

    {{-- Tabla --}}
    <div class="vs-panel">
        @if($ventas->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <p>No se encontraron ventas con los filtros seleccionados</p>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha / Hora</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Pago</th>
                            <th class="rg-th-right">Total</th>
                            <th class="rg-th-right">IGV</th>
                            <th class="rg-th-right">Costo</th>
                            <th class="rg-th-right">Utilidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ventas as $v)
                            @php
                                $util     = (float) $v->utilidad;
                                $esCredit = ($v->estado_pago ?? '') === 'pendiente';
                            @endphp
                            <tr wire:key="pv-{{ $v->id }}">
                                <td>
                                    <span class="rg-comprobante">{{ $v->serie }}-{{ $v->correlativo }}</span>
                                </td>
                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ \Carbon\Carbon::parse($v->created_at)->format('d/m/Y') }}</span>
                                        <span class="rg-fecha__hora">{{ \Carbon\Carbon::parse($v->created_at)->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:.8rem;color:var(--vs-text)">{{ $v->cliente_nombre ?: '—' }}</span>
                                </td>
                                <td>
                                    <span style="font-size:.8rem;color:var(--vs-text-muted)">{{ $v->vendedor ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($esCredit)
                                        <div style="display:flex;flex-direction:column;gap:.15rem">
                                            <span class="vs-badge vs-badge--credito">Crédito</span>
                                            <span style="font-size:.7rem;color:#d97706">pend. S/ {{ number_format((float)$v->saldo_pendiente, 2) }}</span>
                                        </div>
                                    @else
                                        <span style="font-size:.75rem;color:var(--vs-text-muted)">Contado</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format((float)$v->total, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    @if((float)$v->igv > 0)
                                        <span class="rg-monto" style="color:var(--vs-text-muted)">S/ {{ number_format((float)$v->igv, 2) }}</span>
                                    @else
                                        <span style="color:var(--vs-text-faint)">—</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--costo">S/ {{ number_format((float)$v->costo_total, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--util {{ $util >= 0 ? 'rg-monto--pos' : 'rg-monto--neg' }}">
                                        S/ {{ number_format($util, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($ventas->hasPages())
                <div class="rg-pagination">{{ $ventas->links() }}</div>
            @endif
        @endif
    </div>

</div>
</x-filament-panels::page>
