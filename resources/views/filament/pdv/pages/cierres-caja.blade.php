<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/cierres-caja.css') }}?v={{ filemtime(public_path('css/cierres-caja.css')) }}">

@php $sesiones = $this->getSesiones(); @endphp

<div class="vs-root">

    {{-- ══ TÍTULO ══ --}}
    <div class="vs-title">
        <div>
            <h1>Cierres de Caja</h1>
            <p>Historial de sesiones y reportes de cierre</p>
        </div>
    </div>

    {{-- ══ FILTROS ══ --}}
    <div class="cc-form-wrap">
        {{ $this->form }}
        @if($this->hayFiltros())
            <div class="cc-form-limpiar">
                <button wire:click="limpiarFiltros" class="vs-filter-reset">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    {{-- ══ TABLA DE SESIONES ══ --}}
    <div class="vs-panel">

        @if($sesiones->isEmpty())
            <div class="cc-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75M3.75 10.125v3.75"/>
                </svg>
                <p>No se encontraron sesiones</p>
            </div>
        @else

            <div class="cc-table-scroll">
                <table class="cc-table">
                    <thead>
                        <tr>
                            <th>Cajero / Caja</th>
                            <th>Apertura</th>
                            <th>Cierre</th>
                            <th>Duración</th>
                            <th>Estado</th>
                            <th class="cc-td-right">Sistema</th>
                            <th class="cc-td-right">Cajero</th>
                            <th class="cc-td-right">Diferencia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sesiones as $ses)
                            @php
                                $cerrada   = $ses->estado === \App\Enums\EstadoSesion::Cerrada;
                                $duracion  = $ses->fecha_apertura
                                    ? $ses->fecha_apertura->diffForHumans($ses->fecha_cierre ?? now(), true)
                                    : '—';
                                $dif       = (float) $ses->diferencia_total;
                                $tieneCuadre = $ses->tiene_cuadre > 0;
                            @endphp
                            <tr wire:key="ses-{{ $ses->id }}">
                                <td>
                                    <div class="cc-cajero">
                                        <span class="cc-cajero__nombre">{{ $ses->cajero?->name ?? '—' }}</span>
                                        <span class="cc-cajero__caja">{{ $ses->caja?->nombre ?? '—' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="cc-fecha">
                                        <span class="cc-fecha__valor">{{ $ses->fecha_apertura?->format('d/m/Y') ?? '—' }}</span>
                                        <span class="cc-fecha__hora">{{ $ses->fecha_apertura?->format('H:i') ?? '' }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if($ses->fecha_cierre)
                                        <div class="cc-fecha">
                                            <span class="cc-fecha__valor">{{ $ses->fecha_cierre->format('d/m/Y') }}</span>
                                            <span class="cc-fecha__hora">{{ $ses->fecha_cierre->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span style="color:var(--vs-text-muted);font-size:.75rem">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-size:.78rem;color:var(--vs-text-muted)">{{ $duracion }}</span>
                                </td>
                                <td>
                                    <span class="cc-badge {{ $cerrada ? 'cc-badge-cerrada' : 'cc-badge-abierta' }}">
                                        {{ $cerrada ? 'Cerrada' : 'Abierta' }}
                                    </span>
                                </td>
                                <td class="cc-td-right">
                                    <span class="cc-monto">S/ {{ number_format($ses->total_sistema ?? 0, 2) }}</span>
                                </td>
                                <td class="cc-td-right">
                                    @if($tieneCuadre)
                                        <span class="cc-monto">S/ {{ number_format($ses->total_cajero ?? 0, 2) }}</span>
                                    @else
                                        <span style="color:var(--vs-text-faint);font-size:.75rem">—</span>
                                    @endif
                                </td>
                                <td class="cc-td-right">
                                    @if($tieneCuadre)
                                        <span class="cc-dif-badge {{ abs($dif) < 0.01 ? 'cc-dif-badge--ok' : 'cc-dif-badge--mal' }}">
                                            {{ $dif >= 0 ? '+' : '' }}S/ {{ number_format($dif, 2) }}
                                        </span>
                                    @else
                                        <span class="cc-dif-badge cc-dif-badge--nd">N/D</span>
                                    @endif
                                </td>
                                <td class="cc-td-right">
                                    <button class="cc-btn-ver" wire:click="abrirReporte({{ $ses->id }})">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                        </svg>
                                        Ver reporte
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($sesiones->hasPages())
                <div class="cc-pagination">{{ $sesiones->links() }}</div>
            @endif

        @endif
    </div>

</div>{{-- /vs-root --}}


{{-- ══ MODAL REPORTE ══════════════════════════════════════════ --}}
@if($sesionId)
    @php $r = $this->getReporte(); @endphp
    @if($r)
    @php
        extract($r);
        // $sesion, $comp, $anu, $despCount,
        // $porComprobante, $metodosPago,
        // $ingresosManuales, $egresosManuales,
        // $cortesias, $totalDescuentos,
        // $topProductos,
        // $ventasNetas, $costoTurno, $utilidadTurno, $margenTurno,
        // $ventas
        $cerrada  = $sesion->estado === \App\Enums\EstadoSesion::Cerrada;
        $duracion = $sesion->fecha_apertura
            ? $sesion->fecha_apertura->diffForHumans($sesion->fecha_cierre ?? now(), true)
            : '—';
        $totalSistemaPago = collect($metodosPago)->sum('sistema');
        $totalCajeroPago  = collect($metodosPago)->whereNotNull('cajero')->sum('cajero');
        $totalDifPago     = $totalCajeroPago > 0 ? $totalCajeroPago - $totalSistemaPago : null;
    @endphp
    <div class="cc-overlay" wire:key="modal-reporte-{{ $sesion->id }}">
        <div class="cc-overlay__backdrop" wire:click="cerrarReporte"></div>

        <div class="cc-modal" id="cc-reporte-modal">

            {{-- ── HEADER ── --}}
            <div class="cc-modal__header">
                <div>
                    <p class="cc-modal__titulo">
                        Reporte de Cierre — {{ $sesion->caja?->nombre ?? 'Caja' }}
                    </p>
                    <p class="cc-modal__subtitulo">
                        <span>Cajero: <strong>{{ $sesion->cajero?->name ?? '—' }}</strong></span>
                        <span>·</span>
                        <span>{{ $sesion->fecha_apertura?->format('d/m/Y') }}</span>
                        <span class="cc-badge {{ $cerrada ? 'cc-badge-cerrada' : 'cc-badge-abierta' }}">
                            {{ $cerrada ? 'Cerrada' : 'Abierta' }}
                        </span>
                    </p>
                </div>
                <div class="cc-modal__actions">
                    <button class="cc-modal__btn-print" onclick="window.print()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                        </svg>
                        Imprimir
                    </button>
                    <button class="cc-modal__btn-cerrar" wire:click="cerrarReporte">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ── CUERPO ── --}}
            <div class="cc-modal__body">

                {{-- ① Info de sesión --}}
                <div class="cc-section">
                    <p class="cc-section__title">Información de la sesión</p>
                    <div class="cc-info-grid">
                        <div class="cc-info-item">
                            <span class="cc-info-item__label">Caja</span>
                            <span class="cc-info-item__value">{{ $sesion->caja?->nombre ?? '—' }}</span>
                        </div>
                        <div class="cc-info-item">
                            <span class="cc-info-item__label">Cajero</span>
                            <span class="cc-info-item__value">{{ $sesion->cajero?->name ?? '—' }}</span>
                        </div>
                        <div class="cc-info-item">
                            <span class="cc-info-item__label">Apertura</span>
                            <span class="cc-info-item__value">{{ $sesion->fecha_apertura?->format('d/m/Y H:i') ?? '—' }}</span>
                        </div>
                        <div class="cc-info-item">
                            <span class="cc-info-item__label">Cierre</span>
                            <span class="cc-info-item__value">{{ $sesion->fecha_cierre?->format('d/m/Y H:i') ?? 'En curso' }}</span>
                        </div>
                        <div class="cc-info-item">
                            <span class="cc-info-item__label">Duración</span>
                            <span class="cc-info-item__value">{{ $duracion }}</span>
                        </div>
                        <div class="cc-info-item">
                            <span class="cc-info-item__label">Sesión #</span>
                            <span class="cc-info-item__value cc-info-item__value--mono">{{ $sesion->id }}</span>
                        </div>
                    </div>
                    @if($sesion->notas_cierre)
                        <div class="cc-notas">
                            <strong>Notas de cierre:</strong> {{ $sesion->notas_cierre }}
                        </div>
                    @endif
                </div>

                {{-- ② Resumen ventas --}}
                <div class="cc-section">
                    <p class="cc-section__title">Resumen de ventas</p>
                    <div class="cc-chips">
                        <div class="cc-chip">
                            <div class="cc-chip__icon cc-chip__icon--green">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </div>
                            <div class="cc-chip__body">
                                <span class="cc-chip__label">Completadas</span>
                                <span class="cc-chip__value">{{ (int)$comp->cnt }}</span>
                                <span class="cc-chip__sub">S/ {{ number_format($comp->tot, 2) }}</span>
                            </div>
                        </div>
                        <div class="cc-chip">
                            <div class="cc-chip__icon cc-chip__icon--red">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </div>
                            <div class="cc-chip__body">
                                <span class="cc-chip__label">Anuladas</span>
                                <span class="cc-chip__value">{{ (int)$anu->cnt }}</span>
                                <span class="cc-chip__sub">S/ {{ number_format($anu->tot, 2) }}</span>
                            </div>
                        </div>
                        @if($despCount > 0)
                        <div class="cc-chip">
                            <div class="cc-chip__icon cc-chip__icon--yellow">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                            </div>
                            <div class="cc-chip__body">
                                <span class="cc-chip__label">Pendiente envío</span>
                                <span class="cc-chip__value">{{ $despCount }}</span>
                            </div>
                        </div>
                        @endif
                        @if((float)$totalDescuentos > 0)
                        <div class="cc-chip">
                            <div class="cc-chip__icon cc-chip__icon--blue">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z"/></svg>
                            </div>
                            <div class="cc-chip__body">
                                <span class="cc-chip__label">Descuentos</span>
                                <span class="cc-chip__value">S/ {{ number_format($totalDescuentos, 2) }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ③ Por tipo de comprobante --}}
                @if($porComprobante->isNotEmpty())
                <div class="cc-section">
                    <p class="cc-section__title">Por tipo de comprobante</p>
                    <table class="cc-inner-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th class="cc-td-right">Cantidad</th>
                                <th class="cc-td-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($porComprobante as $pc)
                                <tr>
                                    <td>
                                        <span class="cc-tipo-badge cc-tipo-badge--{{ $pc['tipo'] }}">
                                            {{ ucfirst($pc['tipo']) }}
                                        </span>
                                    </td>
                                    <td class="cc-td-right">{{ $pc['count'] }}</td>
                                    <td class="cc-td-right" style="font-weight:700">S/ {{ number_format($pc['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                {{-- ④ Cuadre por método de pago --}}
                @if($metodosPago->isNotEmpty())
                <div class="cc-section">
                    <p class="cc-section__title">Cuadre por método de pago</p>
                    <table class="cc-inner-table">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th class="cc-td-right">Sistema</th>
                                @if(collect($metodosPago)->whereNotNull('cajero')->isNotEmpty())
                                    <th class="cc-td-right">Cajero</th>
                                    <th class="cc-td-right">Diferencia</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metodosPago as $mp)
                                <tr>
                                    <td style="font-weight:600">{{ $mp['nombre'] }}</td>
                                    <td class="cc-td-right">S/ {{ number_format($mp['sistema'], 2) }}</td>
                                    @if(collect($metodosPago)->whereNotNull('cajero')->isNotEmpty())
                                        <td class="cc-td-right">
                                            {{ $mp['cajero'] !== null ? 'S/ '.number_format($mp['cajero'], 2) : '—' }}
                                        </td>
                                        <td class="cc-td-right">
                                            @if($mp['diferencia'] !== null)
                                                @php $d = $mp['diferencia']; @endphp
                                                <span class="{{ abs($d) < 0.01 ? 'cc-cuadre-dif--ok' : 'cc-cuadre-dif--mal' }}">
                                                    {{ $d >= 0 ? '+' : '' }}S/ {{ number_format($d, 2) }}
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                        <div class="cc-total-row" style="flex:1">
                            <span>Total sistema</span>
                            <span>S/ {{ number_format($totalSistemaPago, 2) }}</span>
                        </div>
                        @if($totalDifPago !== null)
                            <div class="cc-total-row {{ abs($totalDifPago) < 0.01 ? 'cc-total-row--green' : 'cc-total-row--red' }}" style="flex:1">
                                <span>Diferencia total</span>
                                <span>{{ $totalDifPago >= 0 ? '+' : '' }}S/ {{ number_format($totalDifPago, 2) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ⑤ Movimientos manuales --}}
                @if($ingresosManuales->isNotEmpty() || $egresosManuales->isNotEmpty())
                <div class="cc-section">
                    <p class="cc-section__title">Movimientos manuales de caja</p>
                    @if($ingresosManuales->isNotEmpty())
                        <p style="font-size:.75rem;font-weight:700;color:#15803d;margin:0">Ingresos</p>
                        <table class="cc-inner-table">
                            <thead><tr><th>Concepto</th><th>Método</th><th class="cc-td-right">Monto</th></tr></thead>
                            <tbody>
                                @foreach($ingresosManuales as $mov)
                                    <tr>
                                        <td>{{ $mov->concepto }}</td>
                                        <td style="color:var(--vs-text-muted)">{{ $mov->metodoPago?->nombre ?? '—' }}</td>
                                        <td class="cc-td-right" style="color:#15803d;font-weight:700">S/ {{ number_format($mov->monto, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if($egresosManuales->isNotEmpty())
                        <p style="font-size:.75rem;font-weight:700;color:#dc2626;margin:0">Egresos</p>
                        <table class="cc-inner-table">
                            <thead><tr><th>Concepto</th><th>Método</th><th class="cc-td-right">Monto</th></tr></thead>
                            <tbody>
                                @foreach($egresosManuales as $mov)
                                    <tr>
                                        <td>{{ $mov->concepto }}</td>
                                        <td style="color:var(--vs-text-muted)">{{ $mov->metodoPago?->nombre ?? '—' }}</td>
                                        <td class="cc-td-right" style="color:#dc2626;font-weight:700">- S/ {{ number_format($mov->monto, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                @endif

                {{-- ⑥ Cortesías --}}
                @if($cortesias->isNotEmpty())
                <div class="cc-section">
                    <p class="cc-section__title">Cortesías entregadas</p>
                    <table class="cc-inner-table">
                        <thead><tr><th>Producto</th><th class="cc-td-right">Cantidad</th><th class="cc-td-right">Veces</th></tr></thead>
                        <tbody>
                            @foreach($cortesias as $c)
                                <tr>
                                    <td>{{ $c->descripcion }}</td>
                                    <td class="cc-td-right">{{ number_format($c->qty, 0) }}</td>
                                    <td class="cc-td-right" style="color:var(--vs-text-muted)">{{ $c->veces }}x</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                {{-- ⑦ Top productos vendidos --}}
                @if($topProductos->isNotEmpty())
                <div class="cc-section">
                    <p class="cc-section__title">Top productos vendidos</p>
                    <table class="cc-inner-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th class="cc-td-right">Cantidad</th>
                                <th class="cc-td-right">Total S/</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topProductos as $i => $tp)
                                <tr>
                                    <td style="color:var(--vs-text-muted);font-size:.75rem">{{ $i + 1 }}</td>
                                    <td style="font-weight:600">{{ $tp->descripcion }}</td>
                                    <td class="cc-td-right">{{ number_format($tp->qty, 0) }}</td>
                                    <td class="cc-td-right" style="font-weight:700">S/ {{ number_format($tp->tot, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                {{-- ⑧ Ganancia del turno --}}
                <div class="cc-section">
                    <p class="cc-section__title">Ganancia del turno</p>
                    <div class="cc-gan-grid">
                        <div class="cc-gan-item">
                            <span class="cc-gan-item__label">Ventas netas (sin IGV)</span>
                            <span class="cc-gan-item__value">S/ {{ number_format($ventasNetas, 2) }}</span>
                        </div>
                        <div class="cc-gan-item cc-gan-item--orange">
                            <span class="cc-gan-item__label">Costo de ventas</span>
                            <span class="cc-gan-item__value">S/ {{ number_format($costoTurno, 2) }}</span>
                        </div>
                        <div class="cc-gan-item cc-gan-item--green">
                            <span class="cc-gan-item__label">Utilidad bruta</span>
                            <span class="cc-gan-item__value">S/ {{ number_format($utilidadTurno, 2) }}</span>
                        </div>
                        <div class="cc-gan-item">
                            <span class="cc-gan-item__label">Margen bruto</span>
                            <span class="cc-gan-item__value">{{ number_format($margenTurno, 1) }}%</span>
                        </div>
                        <div class="cc-gan-item">
                            <span class="cc-gan-item__label">IGV cobrado</span>
                            <span class="cc-gan-item__value">S/ {{ number_format($comp->igv, 2) }}</span>
                        </div>
                        <div class="cc-gan-item">
                            <span class="cc-gan-item__label">Total ingresado</span>
                            <span class="cc-gan-item__value">S/ {{ number_format($comp->tot, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- ⑨ Lista de ventas --}}
                @if($ventas->isNotEmpty())
                <div class="cc-section">
                    <p class="cc-section__title">Lista de ventas del turno ({{ $ventas->count() }})</p>
                    <div class="cc-table-scroll">
                        <table class="cc-inner-table">
                            <thead>
                                <tr>
                                    <th>Comprobante</th>
                                    <th>Hora</th>
                                    <th>Cliente</th>
                                    <th>Método</th>
                                    <th class="cc-td-right">Total</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ventas as $v)
                                    @php
                                        $vAnulada  = $v->estado === \App\Enums\EstadoVenta::Anulada;
                                        $vDespacho = $v->estado_despacho === \App\Enums\EstadoVenta::PendienteEnvio;
                                        $metTxt    = $v->pagos->map(fn($p) => $p->metodoPago?->nombre)->filter()->unique()->implode(', ');
                                        $comp      = ($v->serie?->serie ?? '---') . '-' . $v->correlativo;
                                    @endphp
                                    <tr class="{{ $vAnulada ? 'cc-venta-row--anulada' : '' }}" wire:key="cv-{{ $v->id }}">
                                        <td style="font-family:ui-monospace,monospace;font-size:.75rem;font-weight:700">{{ $comp }}</td>
                                        <td style="color:var(--vs-text-muted);font-size:.75rem">{{ $v->created_at->format('H:i') }}</td>
                                        <td style="font-size:.78rem">{{ $v->cliente_nombre ?: '—' }}</td>
                                        <td style="font-size:.75rem;color:var(--vs-text-muted)">{{ $metTxt ?: '—' }}</td>
                                        <td class="cc-td-right" style="font-weight:700">S/ {{ number_format($v->total, 2) }}</td>
                                        <td>
                                            @if($vAnulada)
                                                <span class="cc-badge-estado cc-badge-estado--anu">Anulada</span>
                                            @elseif($vDespacho)
                                                <span class="cc-badge-estado cc-badge-estado--dep">Despacho</span>
                                            @else
                                                <span class="cc-badge-estado cc-badge-estado--ok">OK</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

            </div>{{-- /cc-modal__body --}}
        </div>{{-- /cc-modal --}}
    </div>{{-- /cc-overlay --}}
    @endif
@endif

</x-filament-panels::page>
