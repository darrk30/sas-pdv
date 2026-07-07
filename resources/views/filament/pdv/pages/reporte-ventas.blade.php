<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ventas.css') }}?v={{ filemtime(public_path('css/reporte-ventas.css')) }}">

@php
    $resumen = $this->getResumen();
    $ventas  = $this->getVentas();
@endphp

<div class="vs-root">

    {{-- ══ TÍTULO ══ --}}
    <div class="vs-title">
        <div>
            <h1>Reporte de Ventas</h1>
            <p>Todas las ventas de la empresa</p>
        </div>
    </div>

    {{-- ══ TARJETAS RESUMEN ══ --}}
    <div class="vs-cards">

        <div class="vs-card vs-card--green">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Completadas</span>
                <span class="vs-card__value">{{ $resumen['count'] }}</span>
            </div>
        </div>

        <div class="vs-card vs-card--blue">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Total cobrado</span>
                <span class="vs-card__value">S/ {{ number_format($resumen['total'], 2) }}</span>
            </div>
        </div>

        @if(($resumen['creditoPendiente'] ?? 0) > 0)
        <div class="vs-card vs-card--amber">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Crédito pendiente</span>
                <span class="vs-card__value">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</span>
            </div>
        </div>
        @endif

        @if($resumen['anuladas'] > 0)
        <div class="vs-card vs-card--red">
            <div class="vs-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <div class="vs-card__body">
                <span class="vs-card__label">Anuladas</span>
                <span class="vs-card__value">{{ $resumen['anuladas'] }}</span>
            </div>
        </div>
        @endif

    </div>

    {{-- ══ MÉTODOS DE PAGO ══ --}}
    @if(! empty($resumen['porMetodo']))
    <div class="vs-metodos">
        <span class="vs-metodos__titulo">Por método de pago</span>
        <div class="vs-metodos__lista">
            @foreach($resumen['porMetodo'] as $m)
                <div class="vs-metodo-item">
                    <span class="vs-metodo-item__nombre">{{ $m['nombre'] }}</span>
                    <span class="vs-metodo-item__monto">S/ {{ number_format($m['total'], 2) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ══ FILTROS (componentes Filament) ══ --}}
    <div class="rv-form-wrap">
        {{ $this->form }}
        @if($this->hayFiltros())
            <div class="rv-form-limpiar">
                <button wire:click="limpiarFiltros" class="vs-filter-reset">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    {{-- ══ TABLA DE VENTAS ══ --}}
    <div class="vs-panel">

        @if($ventas->isEmpty())
            <div class="vs-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z"/>
                </svg>
                <p>No se encontraron ventas</p>
            </div>
        @else

            <div class="vs-table-scroll">
                <table class="vs-vtable">
                    <colgroup>
                        <col class="vs-col-comprobante">
                        <col class="rv-col-fecha">
                        <col>{{-- cliente: espacio libre --}}
                        <col class="vs-col-items">
                        <col class="vs-col-metodo">
                        <col class="vs-col-total">
                        <col class="vs-col-estado">
                        <col class="vs-col-accion">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Ítems</th>
                            <th>Método de pago</th>
                            <th class="vs-th-right">Total</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ventas as $venta)
                            @php
                                $esAnulada   = $venta->estado === \App\Enums\EstadoVenta::Anulada;
                                $esDespacho  = $venta->estado_despacho === \App\Enums\EstadoVenta::PendienteEnvio;
                                $metodosTxt  = $venta->pagos
                                    ->filter(fn($p) =>
                                        $p->metodoPago?->condicion_pago !== \App\Enums\CondicionPago::Credito
                                        || $venta->estado_pago === 'pendiente')
                                    ->map(fn($p) => $p->metodoPago?->nombre)
                                    ->filter()->unique()->implode(', ');
                                $comprobante = ($venta->serie?->serie ?? '---') . '-' . $venta->correlativo;
                            @endphp
                            <tr class="vs-vrow {{ $esAnulada ? 'vs-vrow--anulada' : '' }}" wire:key="rv-{{ $venta->id }}">

                                {{-- Comprobante --}}
                                <td>
                                    <div class="vs-cell-comprobante">
                                        <span class="vs-comprobante">{{ $comprobante }}</span>
                                        @if($esDespacho)
                                            <span class="vs-badge vs-badge--despacho" style="margin-top:.2rem">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="10" height="10">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                                </svg>
                                                Envío
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Fecha --}}
                                <td>
                                    <div class="rv-fecha">
                                        <span class="rv-fecha__dia">{{ $venta->created_at->format('d/m/Y') }}</span>
                                        <span class="rv-fecha__hora">{{ $venta->created_at->format('H:i') }}</span>
                                    </div>
                                </td>

                                {{-- Cliente --}}
                                <td>
                                    <div class="vs-cell-cliente">
                                        <span class="vs-cliente-nombre">{{ $venta->cliente_nombre }}</span>
                                        <span class="vs-cliente-doc">{{ strtoupper($venta->cliente_tipo_doc) }} {{ $venta->cliente_num_doc }}</span>
                                    </div>
                                </td>

                                {{-- Ítems --}}
                                <td class="vs-td-center">
                                    <span class="vs-items-count">{{ $venta->detalles_count }}</span>
                                </td>

                                {{-- Método --}}
                                <td>
                                    <span class="vs-metodo-text">{{ $metodosTxt ?: '—' }}</span>
                                </td>

                                {{-- Total --}}
                                <td class="vs-td-right">
                                    <span class="vs-total {{ $esAnulada ? 'vs-total--anulada' : '' }}">
                                        S/ {{ number_format($venta->total, 2) }}
                                    </span>
                                </td>

                                {{-- Estado --}}
                                <td>
                                    @if($esAnulada)
                                        <span class="vs-badge vs-badge--anulada">Anulada</span>
                                    @else
                                        <span class="vs-badge vs-badge--ok">Completada</span>
                                        @if(($venta->estado_pago ?? 'pagado') === 'pendiente')
                                            <span class="vs-badge vs-badge--credito" style="display:block;margin-top:.2rem">Crédito</span>
                                        @endif
                                    @endif
                                </td>

                                {{-- Acciones --}}
                                <td class="vs-td-right">
                                    <div class="vs-acciones">
                                        <button class="vs-btn-detalle" wire:click="abrirDetalle({{ $venta->id }})">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.573-3.007-9.964-7.178Z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                            </svg>
                                            Ver
                                        </button>
                                        <a href="{{ route('pdv.ticket.venta', $venta->id) }}?print=1"
                                           target="_blank"
                                           class="vs-btn-ticket"
                                           title="Ver e imprimir ticket">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('pdv.ticket.venta.pdf', $venta->id) }}"
                                           class="vs-btn-ticket-pdf"
                                           title="Descargar PDF">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                            </svg>
                                        </a>
                                        @if(! $esAnulada)
                                            <button class="vs-btn-anular" wire:click="abrirAnular({{ $venta->id }})">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>{{-- /vs-table-scroll --}}

            @if($ventas->hasPages())
                <div class="vs-pagination">
                    {{ $ventas->links('vendor.pagination.pdv') }}
                </div>
            @endif

        @endif
    </div>

</div>{{-- /vs-root --}}


{{-- ══ MODAL DETALLE ══ --}}
@if($ventaModalId)
    @php $vm = $this->getVentaModal(); @endphp
    @if($vm)
    <div class="vs-overlay" wire:key="rv-modal-detalle-{{ $vm->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarDetalle"></div>
        <div class="vs-modal">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">
                        {{ ($vm->serie?->serie ?? '---') . '-' . $vm->correlativo }}
                    </h3>
                    <p class="vs-modal__subtitulo">{{ $vm->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <div class="vs-modal__badges">
                    @if($vm->estado === \App\Enums\EstadoVenta::Anulada)
                        <span class="vs-badge vs-badge--anulada">Anulada</span>
                    @else
                        <span class="vs-badge vs-badge--ok">Completada</span>
                    @endif
                    @if($vm->estado_despacho === \App\Enums\EstadoVenta::PendienteEnvio)
                        <span class="vs-badge vs-badge--despacho">Pendiente de envío</span>
                    @endif
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarDetalle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="vs-modal__cliente">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                <span><strong>{{ $vm->cliente_nombre }}</strong> — {{ strtoupper($vm->cliente_tipo_doc) }} {{ $vm->cliente_num_doc }}</span>
            </div>

            <div class="vs-modal__body">

                <p class="vs-modal__section-label">Ítems</p>
                <div class="vs-modal__table-wrap">
                    <table class="vs-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="vs-ta-right">Cant.</th>
                                <th class="vs-ta-right">P. Unit.</th>
                                <th class="vs-ta-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vm->detalles as $d)
                                @php
                                    $cant    = (float) $d->cantidad;
                                    $cantFmt = $cant == floor($cant)
                                        ? number_format($cant, 0)
                                        : rtrim(rtrim(number_format($cant, 3), '0'), '.');
                                    $simbolo = $d->producto?->unidadMedida?->simbolo
                                        ?? $d->variante?->producto?->unidadMedida?->simbolo
                                        ?? null;
                                @endphp
                                <tr>
                                    <td>{{ $d->descripcion }}</td>
                                    <td class="vs-ta-right" style="white-space:nowrap">
                                        {{ $cantFmt }}
                                        @if($simbolo)
                                            <span style="font-size:.72rem;color:var(--vs-text-muted);margin-left:.15rem">{{ $simbolo }}</span>
                                        @endif
                                    </td>
                                    <td class="vs-ta-right">S/ {{ number_format($d->precio_unitario, 2) }}</td>
                                    <td class="vs-ta-right">S/ {{ number_format($d->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="vs-modal__totales">
                    @if($vm->descuento_total > 0)
                        <div class="vs-modal__total-fila">
                            <span>Descuento</span>
                            <span>- S/ {{ number_format($vm->descuento_total, 2) }}</span>
                        </div>
                    @endif
                    @if($vm->op_gravadas > 0)
                    <div class="vs-modal__total-fila">
                        <span>Op. Gravada</span>
                        <span>S/ {{ number_format($vm->op_gravadas, 2) }}</span>
                    </div>
                    @endif
                    @if($vm->igv > 0)
                    <div class="vs-modal__total-fila">
                        <span>IGV (18%)</span>
                        <span>S/ {{ number_format($vm->igv, 2) }}</span>
                    </div>
                    @endif
                    <div class="vs-modal__total-fila vs-modal__total-fila--grande">
                        <span>Total</span>
                        <span>S/ {{ number_format($vm->total, 2) }}</span>
                    </div>
                </div>

                <p class="vs-modal__section-label" style="margin-top:1rem">Pagos</p>
                <div class="vs-modal__pagos">
                    @foreach($vm->pagos->filter(fn($p) => $p->metodoPago?->condicion_pago !== \App\Enums\CondicionPago::Credito || $vm->estado_pago === 'pendiente') as $pago)
                        <div class="vs-modal__pago-item">
                            <div class="vs-modal__pago-info">
                                <span class="vs-modal__pago-metodo">{{ $pago->metodoPago?->nombre ?? '—' }}</span>
                                @if($pago->referencia)
                                    <span class="vs-modal__pago-ref">{{ $pago->referencia }}</span>
                                @endif
                            </div>
                            <span class="vs-modal__pago-monto">S/ {{ number_format($pago->monto, 2) }}</span>
                        </div>
                    @endforeach
                    @if($vm->saldo_pendiente > 0)
                        <div class="vs-modal__pago-item vs-modal__pago-item--pendiente">
                            <span>Saldo pendiente</span>
                            <span>S/ {{ number_format($vm->saldo_pendiente, 2) }}</span>
                        </div>
                    @endif
                </div>

            </div>

            <div class="vs-modal__footer">
                <button class="vs-modal__btn-cerrar" wire:click="cerrarDetalle">Cerrar</button>
            </div>

        </div>
    </div>
    @endif
@endif


{{-- ══ MODAL ANULAR ══ --}}
@if($modalAnular)
    @php
        $va         = $this->getVentaAnular();
        $tieneStock = $va ? $this->tieneItemsConStock() : false;
        $codAnular  = $va ? (($va->serie?->serie ?? '---') . '-' . $va->correlativo) : '';
    @endphp
    @if($va)
    <div class="vs-overlay" wire:key="rv-modal-anular-{{ $va->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarAnular"></div>
        <div class="vs-modal vs-modal--anular">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">Anular venta</h3>
                    <p class="vs-modal__subtitulo">{{ $codAnular }} · S/ {{ number_format($va->total, 2) }}</p>
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarAnular">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="vs-modal__body">

                <div class="vs-anular-alerta">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                    <div>
                        <p class="vs-anular-alerta__titulo">Esta acción no se puede deshacer</p>
                        <p class="vs-anular-alerta__texto">Se registrará una devolución de <strong>S/ {{ number_format($va->total, 2) }}</strong> y la venta quedará marcada como anulada.</p>
                    </div>
                </div>

                <p class="vs-modal__section-label" style="margin-bottom:.5rem">Devolución a registrar</p>
                <div class="vs-modal__pagos" style="margin-bottom:1rem">
                    @foreach($va->pagos as $pago)
                        <div class="vs-modal__pago-item">
                            <span class="vs-modal__pago-metodo">{{ $pago->metodoPago?->nombre ?? '—' }}</span>
                            <span class="vs-modal__pago-monto">S/ {{ number_format($pago->monto, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                @if($tieneStock)
                    <label class="vs-anular-check-label">
                        <input type="checkbox" class="vs-anular-check" wire:model.live="revertirStock">
                        <span class="vs-anular-check-text">
                            <strong>Revertir inventario</strong><br>
                            <span>Se devolverá el stock de los productos con control de inventario y se registrará en el kardex.</span>
                        </span>
                    </label>
                @else
                    <p class="vs-anular-sin-stock">Ningún producto de esta venta tiene control de stock.</p>
                @endif

            </div>

            <div class="vs-modal__footer vs-modal__footer--anular">
                <button class="vs-modal__btn-cerrar" wire:click="cerrarAnular">Cancelar</button>
                <button class="vs-btn-confirmar-anular" wire:click="confirmarAnular" wire:loading.attr="disabled">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    <span wire:loading.remove wire:target="confirmarAnular">Confirmar anulación</span>
                    <span wire:loading wire:target="confirmarAnular">Procesando…</span>
                </button>
            </div>

        </div>
    </div>
    @endif
@endif

</x-filament-panels::page>
