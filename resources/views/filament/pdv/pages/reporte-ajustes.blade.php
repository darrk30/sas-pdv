<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-compras.css') }}?v={{ filemtime(public_path('css/reporte-compras.css')) }}">

@php
    $resumen  = $this->getResumen();
    $ajustes  = $this->getAjustes();
@endphp

<div class="vs-root">

    {{-- TÍTULO --}}
    <div class="vs-title">
        <div>
            <h1>Ajustes de Stock</h1>
            <p>Historial de ajustes de inventario</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="rg-kpis">
        <div class="rg-kpi rg-kpi--gray">
            <span class="rg-kpi__label">Total ajustes</span>
            <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            <span class="rg-kpi__sub">en el período</span>
        </div>
        <div class="rg-kpi rg-kpi--green">
            <span class="rg-kpi__label">Entradas</span>
            <span class="rg-kpi__value">{{ number_format($resumen['entradas']) }}</span>
            <span class="rg-kpi__sub">incrementos de stock</span>
        </div>
        <div class="rg-kpi rg-kpi--orange">
            <span class="rg-kpi__label">Salidas</span>
            <span class="rg-kpi__value">{{ number_format($resumen['salidas']) }}</span>
            <span class="rg-kpi__sub">reducciones de stock</span>
        </div>
        <div class="rg-kpi rg-kpi--blue">
            <span class="rg-kpi__label">Valor total</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['valorTotal'], 2) }}</span>
            <span class="rg-kpi__sub">costo ajustado</span>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="rg-form-wrap">{{ $this->form }}</div>

    {{-- TABLA --}}
    <div class="vs-panel">
        @if($ajustes->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75M3.75 13.5v3.75"/>
                </svg>
                <p>No se encontraron ajustes en el período seleccionado</p>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide" style="min-width:680px">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th class="rg-th-right">Ítems</th>
                            <th class="rg-th-right">Valor total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ajustes as $a)
                            <tr wire:key="raj-{{ $a->id }}">

                                <td>
                                    <span class="rc-comprobante">{{ $a->codigo ?? '—' }}</span>
                                </td>

                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ $a->created_at->format('d/m/Y') }}</span>
                                        <span class="rg-fecha__hora">{{ $a->created_at->format('H:i') }}</span>
                                    </div>
                                </td>

                                <td>
                                    <span class="rc-badge rc-badge--{{ $a->tipo }}">
                                        {{ $a->tipo === 'entrada' ? 'Entrada' : 'Salida' }}
                                    </span>
                                </td>

                                <td>
                                    <span style="font-size:.8rem;color:var(--vs-text)">{{ $a->motivo }}</span>
                                </td>

                                <td>
                                    <span class="rc-proveedor__user" style="font-size:.8rem">
                                        {{ $a->responsable?->name ?? '—' }}
                                    </span>
                                </td>

                                <td>
                                    <span class="rc-badge rc-badge--{{ $a->estado }}">
                                        {{ $a->estado === 'confirmado' ? 'Confirmado' : 'Borrador' }}
                                    </span>
                                </td>

                                <td class="rg-td-right">
                                    <span style="font-size:.8rem;font-weight:600;color:var(--vs-text)">
                                        {{ $a->detalles_count }}
                                    </span>
                                </td>

                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format((float)$a->valor_total, 2) }}</span>
                                </td>

                                <td>
                                    <div class="rc-acciones">
                                        <button class="rc-btn rc-btn--detalle"
                                            wire:click="abrirDetalle({{ $a->id }})"
                                            wire:loading.class="rc-btn--cargando"
                                            wire:loading.attr="disabled"
                                            wire:target="abrirDetalle({{ $a->id }})">
                                            Ver detalle
                                        </button>
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($ajustes->hasPages())
                <div class="rg-pagination">{{ $ajustes->links() }}</div>
            @endif
        @endif
    </div>

</div>{{-- /vs-root --}}


{{-- ══ MODAL DETALLE ══ --}}
@if($ajusteDetalleId)
    @php $ad = $this->getAjusteDetalle(); @endphp
    @if($ad)
    <div class="vs-overlay" wire:key="raj-modal-{{ $ad->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarDetalle"></div>
        <div class="vs-modal" style="max-width:40rem">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">{{ $ad->codigo ?? 'Ajuste #' . $ad->id }}</h3>
                    <p class="vs-modal__subtitulo">
                        {{ $ad->created_at->format('d/m/Y H:i') }}
                        · {{ $ad->responsable?->name ?? '—' }}
                    </p>
                </div>
                <div class="vs-modal__badges">
                    <span class="rc-badge rc-badge--{{ $ad->tipo }}">
                        {{ $ad->tipo === 'entrada' ? 'Entrada' : 'Salida' }}
                    </span>
                    <span class="rc-badge rc-badge--{{ $ad->estado }}">
                        {{ $ad->estado === 'confirmado' ? 'Confirmado' : 'Borrador' }}
                    </span>
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarDetalle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Motivo --}}
            <div class="vs-modal__cliente">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                </svg>
                <span><strong>Motivo:</strong> {{ $ad->motivo }}</span>
            </div>

            <div class="vs-modal__body">
                <p class="rc-modal-section-label">Productos ajustados ({{ $ad->detalles->count() }})</p>
                <div style="overflow-x:auto">
                    <table class="rc-modal-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="rc-ta-right">Cantidad</th>
                                <th class="rc-ta-right">Costo unit.</th>
                                <th class="rc-ta-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ad->detalles as $d)
                                <tr>
                                    <td>{{ $d->nombre_producto }}</td>
                                    <td class="rc-ta-right">
                                        {{ number_format($d->cantidad, 2) }}
                                        <span style="font-size:.65rem;color:var(--vs-text-muted)">{{ $d->unidad?->simbolo }}</span>
                                    </td>
                                    <td class="rc-ta-right">S/ {{ number_format($d->costo_unitario, 4) }}</td>
                                    <td class="rc-ta-right">S/ {{ number_format($d->costo_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="rc-modal-totales">
                    <div class="rc-modal-total-fila rc-modal-total-fila--grande">
                        <span>Valor total</span>
                        <span>S/ {{ number_format((float)$ad->valor_total, 2) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
    @endif
@endif

</x-filament-panels::page>
