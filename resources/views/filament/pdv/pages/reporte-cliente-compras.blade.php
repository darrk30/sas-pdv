<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">
<link rel="stylesheet" href="{{ asset('css/venta-detalle-modal.css') }}?v={{ filemtime(public_path('css/venta-detalle-modal.css')) }}">

@php
    $resumen = $this->getResumen();
    $compras = $this->getCompras();
@endphp

<div class="vs-root">

    <div class="vs-title">
        <div>
            <h1>{{ $this->clienteNombre ?? 'Cliente' }}</h1>
            @if($this->clienteNumDoc)
                <p>Documento: {{ $this->clienteNumDoc }}</p>
            @else
                <p>Historial de compras</p>
            @endif
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <div class="rg-kpi rg-kpi--blue" style="min-width:130px">
                <span class="rg-kpi__label">Compras</span>
                <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            </div>
            <div class="rg-kpi rg-kpi--green" style="min-width:130px">
                <span class="rg-kpi__label">Total facturado</span>
                <span class="rg-kpi__value">S/ {{ number_format($resumen['totalGastado'], 2) }}</span>
            </div>
            @if(($resumen['creditoPendiente'] ?? 0) > 0)
            <div class="rg-kpi rg-kpi--amber" style="min-width:130px">
                <span class="rg-kpi__label">Crédito pend.</span>
                <span class="rg-kpi__value">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</span>
            </div>
            @endif
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
        @if($compras->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <p>No se encontraron compras con los filtros seleccionados</p>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha</th>
                            <th>Vendedor</th>
                            <th>Pago</th>
                            <th class="rg-th-right">Total</th>
                            <th class="rg-th-right">IGV</th>
                            <th class="rg-th-right">Costo</th>
                            <th class="rg-th-right">Utilidad</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($compras as $c)
                            @php
                                $util     = (float) $c->utilidad;
                                $esCredit = ($c->estado_pago ?? '') === 'pendiente';
                            @endphp
                            <tr wire:key="compra-{{ $c->id }}">
                                <td>
                                    <span class="rg-comprobante">{{ $c->serie }}-{{ $c->correlativo }}</span>
                                </td>
                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y') }}</span>
                                        <span class="rg-fecha__hora">{{ \Carbon\Carbon::parse($c->created_at)->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:.8rem;color:var(--vs-text-muted)">{{ $c->vendedor ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($esCredit)
                                        <div style="display:flex;flex-direction:column;gap:.15rem">
                                            <span class="vs-badge vs-badge--credito">Crédito</span>
                                            <span style="font-size:.7rem;color:#d97706">pend. S/ {{ number_format((float)$c->saldo_pendiente, 2) }}</span>
                                        </div>
                                    @else
                                        <span style="font-size:.75rem;color:var(--vs-text-muted)">Contado</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format((float)$c->total, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    @if((float)$c->igv > 0)
                                        <span class="rg-monto" style="color:var(--vs-text-muted)">S/ {{ number_format((float)$c->igv, 2) }}</span>
                                    @else
                                        <span style="color:var(--vs-text-faint)">—</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--costo">S/ {{ number_format((float)$c->costo_total, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--util {{ $util >= 0 ? 'rg-monto--pos' : 'rg-monto--neg' }}">
                                        S/ {{ number_format($util, 2) }}
                                    </span>
                                </td>
                                <td>
                                    <button wire:click="abrirModalDetalle({{ $c->id }})" class="vdm-btn-ver">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Ver
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($compras->hasPages())
                <div class="rg-pagination">{{ $compras->links() }}</div>
            @endif
        @endif
    </div>

</div>

@include('filament.pdv.partials.venta-detalle-modal')
</x-filament-panels::page>
