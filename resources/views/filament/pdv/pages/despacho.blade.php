<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">
<link rel="stylesheet" href="{{ asset('css/despacho.css') }}?v={{ filemtime(public_path('css/despacho.css')) }}">
<link rel="stylesheet" href="{{ asset('css/venta-detalle-modal.css') }}?v={{ filemtime(public_path('css/venta-detalle-modal.css')) }}">

@php
    $resumen = $this->getResumen();
    $ventas  = $this->getVentas();
@endphp

<div class="vs-root">

    {{-- ══ TÍTULO ══ --}}
    <div class="vs-title">
        <div>
            <h1>Despachos Pendientes</h1>
            <p>Seguimiento de envíos por estado de entrega</p>
        </div>
        @if($resumen['total'] > 0)
            <span class="dsp-total-badge">
                <span class="dsp-total-badge__dot"></span>
                {{ $resumen['total'] }} pendiente{{ $resumen['total'] !== 1 ? 's' : '' }}
            </span>
        @endif
    </div>

    {{-- ══ KPIs ══ --}}
    <div class="rg-kpis" style="grid-template-columns: repeat(3, 1fr)">
        <div class="rg-kpi rg-kpi--orange">
            <span class="rg-kpi__label">Total pendientes</span>
            <span class="rg-kpi__value">{{ number_format($resumen['total']) }}</span>
            <span class="rg-kpi__sub">en toda la empresa</span>
        </div>
        <div class="rg-kpi rg-kpi--blue">
            <span class="rg-kpi__label">Pendientes hoy</span>
            <span class="rg-kpi__value">{{ number_format($resumen['hoy']) }}</span>
            <span class="rg-kpi__sub">registradas hoy</span>
        </div>
        <div class="rg-kpi rg-kpi--purple">
            <span class="rg-kpi__label">Últimos 7 días</span>
            <span class="rg-kpi__value">{{ number_format($resumen['semana']) }}</span>
            <span class="rg-kpi__sub">acumuladas esta semana</span>
        </div>
    </div>

    {{-- ══ FILTROS ══ --}}
    <div class="rg-form-wrap">
        {{ $this->form }}
        @if($this->hayFiltros())
            <div class="rg-form-limpiar">
                <button wire:click="limpiarFiltros" class="vs-filter-reset">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke-width="1.5" stroke="currentColor" width="14" height="14"
                         style="display:inline;vertical-align:middle;margin-right:.3rem">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    {{-- ══ TABLA ══ --}}
    <div class="vs-panel">
        @if($ventas->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3
                             7.5v9l9 5.25m0-9v9"/>
                </svg>
                <p>No hay despachos pendientes</p>
                <span>Todos los pedidos han sido entregados</span>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Productos</th>
                            <th class="rg-th-right">Total</th>
                            <th class="rg-th-right">Pago</th>
                            <th class="rg-td-center">Estado</th>
                            <th style="width:7rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ventas as $venta)
                            @php
                                $serie        = $venta->serie->serie ?? '??';
                                $corr         = str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
                                $cliente      = $venta->cliente_nombre ?: 'Cliente general';
                                $items        = $venta->detalles->take(3);
                                $resto        = $venta->detalles->count() - 3;
                                $tel          = preg_replace('/\D/', '', $venta->cliente?->telefono ?? '');
                                $wspUrl       = $tel ? 'https://wa.me/51' . ltrim($tel, '0') : null;
                                $estadoActual = $venta->estado_despacho ?? 'pendiente_envio';
                                $metaActual   = \App\Filament\Pdv\Pages\DespachoPage::metaEstado($estadoActual);
                                $siguientes   = \App\Filament\Pdv\Pages\DespachoPage::siguientesEstados($estadoActual);
                            @endphp
                            <tr wire:key="dsp-{{ $venta->id }}">

                                {{-- Comprobante --}}
                                <td>
                                    <span class="rg-comprobante">{{ $serie }}-{{ $corr }}</span>
                                    @if($venta->orden)
                                        <a href="{{ \App\Filament\Pdv\Resources\Ordenes\OrdenResource::getUrl('edit', ['record' => $venta->orden->id, 'tenant' => \Filament\Facades\Filament::getTenant()]) }}"
                                           class="dsp-orden-link">
                                            {{ $venta->orden->codigo }}
                                        </a>
                                    @endif
                                </td>

                                {{-- Fecha --}}
                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y') }}</span>
                                        <span class="rg-fecha__hora">{{ \Carbon\Carbon::parse($venta->fecha_emision)->format('H:i') }}</span>
                                    </div>
                                </td>

                                {{-- Cliente --}}
                                <td>
                                    <span class="dsp-cliente-nombre">{{ $cliente }}</span>
                                    @if($venta->cliente_num_doc)
                                        <span class="dsp-cliente-doc">{{ $venta->cliente_num_doc }}</span>
                                    @endif
                                    @if($wspUrl)
                                        <a href="{{ $wspUrl }}" target="_blank" rel="noopener"
                                           class="dsp-wsp-link">
                                            <svg viewBox="0 0 24 24" fill="currentColor" width="12" height="12">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                                            </svg>
                                            +51 {{ $tel }}
                                        </a>
                                    @endif
                                </td>

                                {{-- Productos --}}
                                <td>
                                    <ul class="dsp-items-list">
                                        @foreach($items as $d)
                                            <li>
                                                <span class="dsp-cant">×&thinsp;{{ rtrim(rtrim(number_format((float)$d->cantidad, 4, '.', ''), '0'), '.') }}</span>
                                                {{ $d->descripcion }}
                                            </li>
                                        @endforeach
                                        @if($resto > 0)
                                            <li class="dsp-items-mas">+{{ $resto }} más…</li>
                                        @endif
                                    </ul>
                                </td>

                                {{-- Total --}}
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format($venta->total, 2) }}</span>
                                </td>

                                {{-- Estado pago --}}
                                <td class="rg-td-right">
                                    @if($venta->estado_pago === 'pendiente')
                                        <span class="dsp-badge dsp-badge--credito">Por cobrar</span>
                                    @elseif($venta->estado_pago === 'parcial')
                                        <span class="dsp-badge dsp-badge--credito">Pago parcial</span>
                                        <span class="dsp-saldo-inline">Saldo: S/ {{ number_format($venta->saldo_pendiente, 2) }}</span>
                                    @else
                                        <span class="dsp-badge dsp-badge--pagado">Pagado</span>
                                    @endif
                                </td>

                                {{-- Estado despacho (badge) --}}
                                <td class="rg-td-center">
                                    <span class="dsp-badge dsp-badge--{{ $metaActual['css'] }}">
                                        {{ $metaActual['label'] }}
                                    </span>
                                </td>

                                {{-- Acciones --}}
                                <td style="text-align:right;white-space:nowrap">

                                    {{-- Botón "Ver detalle" --}}
                                    <button class="vdm-btn-ver"
                                            wire:click="abrirModalDetalle({{ $venta->id }})"
                                            title="Ver detalle">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                             fill="currentColor" width="15" height="15">
                                            <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
                                            <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1
                                                 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66
                                                 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1
                                                 10 17c-4.257 0-7.893-2.66-9.336-6.41Z"
                                                 clip-rule="evenodd"/>
                                        </svg>
                                        Ver
                                    </button>

                                    {{-- Select "Cambiar estado" --}}
                                    @if(count($siguientes) > 0)
                                        <select class="dsp-select-estado"
                                                wire:change="seleccionarEstado({{ $venta->id }}, $event.target.value)"
                                                wire:key="sel-{{ $venta->id }}">
                                            <option value="">Cambiar…</option>
                                            @foreach($siguientes as $sig)
                                                @php $ms = \App\Filament\Pdv\Pages\DespachoPage::metaEstado($sig); @endphp
                                                <option value="{{ $sig }}">{{ $ms['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($ventas->hasPages())
                <div class="rg-pagination">{{ $ventas->links('vendor.pagination.pdv') }}</div>
            @endif
        @endif
    </div>

</div>{{-- /vs-root --}}

{{-- ══ Modal: detalle de venta (trait compartido) ══ --}}
@include('filament.pdv.partials.venta-detalle-modal')

{{-- ══ Modal: cambiar estado de despacho ══ --}}
@if($modalEstado && $estadoVenta && $nuevoEstado)
@php
    $metaActualModal  = \App\Filament\Pdv\Pages\DespachoPage::metaEstado($estadoVenta['estado_actual']);
    $metaNuevoModal   = \App\Filament\Pdv\Pages\DespachoPage::metaEstado($nuevoEstado);
@endphp
<div class="dsp-overlay" wire:click.self="cerrarModalEstado">
    <div class="dsp-modal">

        {{-- Cabecera --}}
        <div class="dsp-modal__head">
            <div class="dsp-modal__title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" width="22" height="22">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3
                             0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25
                             4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621
                             0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056
                             2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-
                             .568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106
                             1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                </svg>
                <div>
                    <span class="dsp-modal__label">Cambiar estado de despacho</span>
                    <span class="dsp-modal__sub">{{ $estadoVenta['comprobante'] }}</span>
                </div>
            </div>
            <button wire:click="cerrarModalEstado" class="dsp-modal__close" title="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="2" stroke="currentColor" width="18" height="18">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="dsp-modal__body">

            {{-- Transición de estados --}}
            <div class="dsp-estado-transicion">
                <div class="dsp-estado-pill dsp-estado-pill--{{ $metaActualModal['css'] }}">
                    {{ $metaActualModal['label'] }}
                </div>
                <div class="dsp-estado-arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                         fill="currentColor" width="16" height="16">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23
                             5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5
                             5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z"
                             clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="dsp-estado-pill dsp-estado-pill--{{ $metaNuevoModal['css'] }} dsp-estado-pill--nuevo">
                    {{ $metaNuevoModal['label'] }}
                </div>
            </div>

            {{-- Info cliente --}}
            <div class="dsp-entrega-card">
                <div class="dsp-entrega-card__row">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke-width="1.5" stroke="currentColor" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501
                                 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12
                                 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                    <div>
                        <span class="dsp-entrega-card__label">Cliente</span>
                        <span class="dsp-entrega-card__val">
                            {{ $estadoVenta['cliente'] }}
                            @if($estadoVenta['cliente_doc'])
                                <em>· {{ $estadoVenta['cliente_doc'] }}</em>
                            @endif
                        </span>
                        @if($estadoVenta['wsp_url'])
                            <a href="{{ $estadoVenta['wsp_url'] }}" target="_blank" rel="noopener"
                               class="dsp-wsp-link dsp-wsp-link--modal">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>
                                </svg>
                                {{ $estadoVenta['telefono'] }}
                            </a>
                        @endif
                    </div>
                </div>
                <div class="dsp-entrega-card__row">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke-width="1.5" stroke="currentColor" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <div>
                        <span class="dsp-entrega-card__label">Fecha de venta</span>
                        <span class="dsp-entrega-card__val">{{ $estadoVenta['fecha'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Advertencia crédito pendiente --}}
            @if($estadoVenta['es_cred_pend'])
                <div class="dsp-aviso-credito">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke-width="1.5" stroke="currentColor" width="18" height="18">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948
                                 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949
                                 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                    <div>
                        <strong>Tiene saldo pendiente de S/ {{ number_format($estadoVenta['saldo'], 2) }}</strong>
                        <span>¿Desea cambiar de estado aun teniendo un crédito por cancelar?</span>
                    </div>
                </div>
            @endif

            {{-- Aviso: no reversible --}}
            <div class="dsp-aviso-irreversible">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" width="15" height="15">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25
                             2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25
                             2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
                Este cambio de estado no se puede revertir.
            </div>

        </div>{{-- /body --}}

        {{-- Pie --}}
        <div class="dsp-modal__foot">
            <button type="button" class="dsp-btn-cancelar" wire:click="cerrarModalEstado">
                Cancelar
            </button>
            <button type="button"
                    class="dsp-btn-confirmar dsp-btn-confirmar--{{ $metaNuevoModal['css'] }}"
                    wire:click="confirmarCambioEstado"
                    wire:loading.attr="disabled"
                    wire:loading.class="dsp-btn-confirmar--loading">
                <span wire:loading.remove wire:target="confirmarCambioEstado">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                         fill="currentColor" width="15" height="15">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8
                             10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894
                             3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/>
                    </svg>
                    Confirmar: {{ $metaNuevoModal['label'] }}
                </span>
                <span wire:loading wire:target="confirmarCambioEstado">Guardando…</span>
            </button>
        </div>

    </div>
</div>
@endif

</x-filament-panels::page>
