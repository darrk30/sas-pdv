<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-compras.css') }}?v={{ filemtime(public_path('css/reporte-compras.css')) }}">

@php
    $resumen   = $this->getResumen();
    $labelTipo = fn(string $t) => match($t) {
        'factura'        => 'Factura',
        'boleta'         => 'Boleta',
        'ticket'         => 'Ticket',
        'sin_comprobante'=> 'Sin comprob.',
        default          => $t,
    };
@endphp

<div class="vs-root">

    {{-- ══ TÍTULO ══ --}}
    <div class="vs-title">
        <div>
            <h1>Reporte de Compras</h1>
            <p>Historial de compras registradas</p>
        </div>
    </div>

    {{-- ══ KPIs ══ --}}
    <div class="rg-kpis">
        <div class="rg-kpi rg-kpi--gray">
            <span class="rg-kpi__label">Compras</span>
            <span class="rg-kpi__value">{{ number_format($resumen['cantidad']) }}</span>
            <span class="rg-kpi__sub">en el período</span>
        </div>
        <div class="rg-kpi rg-kpi--blue">
            <span class="rg-kpi__label">Total comprado</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['total'], 2) }}</span>
            <span class="rg-kpi__sub">importe total</span>
        </div>
        <div class="rg-kpi rg-kpi--green">
            <span class="rg-kpi__label">Total pagado</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['pagado'], 2) }}</span>
            <span class="rg-kpi__sub">pagos registrados</span>
        </div>
        <div class="rg-kpi {{ $resumen['saldo'] > 0 ? 'rg-kpi--orange' : 'rg-kpi--teal' }}">
            <span class="rg-kpi__label">Saldo pendiente</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['saldo'], 2) }}</span>
            <span class="rg-kpi__sub">{{ $resumen['pendiente'] }} compras por pagar</span>
        </div>
    </div>

    {{-- ══ FILTROS ══ --}}
    <div class="rg-form-wrap" x-data="{ open: {{ $this->hayFiltros() ? 'true' : 'false' }} }">

        {{-- Botón toggle solo visible en móvil --}}
        <button type="button" class="rc-filtros-toggle" @click="open = !open">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor" width="15" height="15">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/>
            </svg>
            <span>Filtros</span>
            @if($this->hayFiltros())
                <span class="rc-filtros-activo-dot" title="Hay filtros activos"></span>
            @endif
            <svg class="rc-filtros-chevron" :class="{ 'rc-filtros-chevron--open': open }"
                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="2" stroke="currentColor" width="14" height="14">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </button>

        <div class="rc-filtros-body" :class="{ 'rc-filtros-body--open': open }">
            {{ $this->form }}
            @if($this->hayFiltros())
                <div class="rc-form-limpiar">
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

    {{-- ══ TABLA FILAMENT ══ --}}
    {{ $this->table }}

</div>{{-- /vs-root --}}


{{-- ══ MODAL DETALLE ══ --}}
@if($compraDetalleId)
    @php $cd = $this->getCompraDetalle(); @endphp
    @if($cd)
    <div class="vs-overlay" wire:key="rc-modal-detalle-{{ $cd->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarDetalle"></div>
        <div class="vs-modal" style="max-width:42rem">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">
                        @if($cd->serie && $cd->correlativo)
                            {{ $cd->serie }}-{{ $cd->correlativo }}
                        @else
                            {{ $cd->codigo ?? 'Compra #' . $cd->id }}
                        @endif
                    </h3>
                    <p class="vs-modal__subtitulo">
                        {{ \Carbon\Carbon::parse($cd->fecha_compra)->format('d/m/Y') }}
                        · {{ $cd->registradoPor?->name ?? '—' }}
                    </p>
                </div>
                <div class="vs-modal__badges">
                    <span class="rc-badge rc-badge--{{ $cd->tipo_comprobante === 'sin_comprobante' ? 'sin' : $cd->tipo_comprobante }}">
                        {{ $labelTipo($cd->tipo_comprobante) }}
                    </span>
                    <span class="rc-badge rc-badge--{{ $cd->estado }}">
                        {{ match($cd->estado) { 'borrador' => 'Borrador', 'confirmado' => 'Confirmado', 'anulado' => 'Anulado', default => $cd->estado } }}
                    </span>
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarDetalle">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            @if($cd->proveedor)
            <div class="vs-modal__cliente">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72"/>
                </svg>
                <span><strong>{{ $cd->proveedor->nombre }}</strong></span>
            </div>
            @endif

            <div class="vs-modal__body">
                <p class="rc-modal-section-label">Ítems ({{ $cd->detalles->count() }})</p>
                <div style="overflow-x:auto">
                    <table class="rc-modal-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="rc-ta-right">Cant.</th>
                                <th class="rc-ta-right">C. Unitario</th>
                                <th class="rc-ta-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cd->detalles as $d)
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
                    @if($cd->costo_envio > 0)
                        <div class="rc-modal-total-fila">
                            <span>Costo de envío</span>
                            <span>S/ {{ number_format($cd->costo_envio, 2) }}</span>
                        </div>
                    @endif
                    @if($cd->descuento > 0)
                        <div class="rc-modal-total-fila">
                            <span>Descuento</span>
                            <span>− S/ {{ number_format($cd->descuento, 2) }}</span>
                        </div>
                    @endif
                    <div class="rc-modal-total-fila">
                        <span>Subtotal</span>
                        <span>S/ {{ number_format($cd->subtotal, 2) }}</span>
                    </div>
                    <div class="rc-modal-total-fila">
                        <span>IGV</span>
                        <span>S/ {{ number_format($cd->igv, 2) }}</span>
                    </div>
                    <div class="rc-modal-total-fila rc-modal-total-fila--grande">
                        <span>Total</span>
                        <span>S/ {{ number_format($cd->total, 2) }}</span>
                    </div>
                </div>

                @if($cd->observaciones)
                    <div style="margin-top:1rem;padding:.75rem;background:var(--vs-bg-subtle);border-radius:.375rem;font-size:.8rem;color:var(--vs-text-muted)">
                        <strong>Observaciones:</strong> {{ $cd->observaciones }}
                    </div>
                @endif
            </div>

        </div>
    </div>
    @endif
@endif


{{-- ══ MODAL PAGOS ══ --}}
@if($compraPagosId)
    @php $cp = $this->getCompraPagos(); @endphp
    @if($cp)
    @php
        $totalPagado = $cp->pagos->sum('monto');
        $saldoModal  = (float) $cp->total - $totalPagado;
    @endphp
    <div class="vs-overlay" wire:key="rc-modal-pagos-{{ $cp->id }}">
        <div class="vs-overlay__backdrop" wire:click="cerrarPagos"></div>
        <div class="vs-modal" style="max-width:36rem">

            <div class="vs-modal__header">
                <div>
                    <h3 class="vs-modal__titulo">Pagos de compra</h3>
                    <p class="vs-modal__subtitulo">
                        @if($cp->serie && $cp->correlativo)
                            {{ $cp->serie }}-{{ $cp->correlativo }} ·
                        @endif
                        {{ $cp->proveedor?->nombre ?? '—' }}
                    </p>
                </div>
                <button class="vs-modal__cerrar" wire:click="cerrarPagos">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="vs-modal__body">

                <div class="rc-saldo-pendiente {{ $saldoModal > 0.01 ? 'rc-saldo-pendiente--deuda' : 'rc-saldo-pendiente--ok' }}">
                    <div style="flex:1">
                        <div class="rc-saldo-pendiente__label">
                            {{ $saldoModal > 0.01 ? 'Saldo pendiente por pagar' : 'Compra completamente pagada' }}
                        </div>
                        <div style="font-size:.72rem;color:var(--vs-text-muted);margin-top:.15rem">
                            Total: S/ {{ number_format($cp->total, 2) }} · Pagado: S/ {{ number_format($totalPagado, 2) }}
                        </div>
                    </div>
                    <div class="rc-saldo-pendiente__monto">
                        @if($saldoModal > 0.01)
                            S/ {{ number_format($saldoModal, 2) }}
                        @else
                            ✓ Saldado
                        @endif
                    </div>
                </div>

                @if($cp->pagos->isEmpty())
                    <p style="font-size:.8rem;color:var(--vs-text-muted);text-align:center;padding:1.5rem 0">
                        No hay pagos registrados para esta compra.
                    </p>
                @else
                    <p class="rc-modal-section-label">Pagos registrados ({{ $cp->pagos->count() }})</p>
                    <table class="rc-modal-table">
                        <thead>
                            <tr>
                                <th>Método de pago</th>
                                <th>Referencia</th>
                                <th class="rc-ta-right">Monto</th>
                                <th class="rc-ta-right">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cp->pagos as $pago)
                                <tr>
                                    <td><span style="font-weight:600">{{ $pago->metodoPago?->nombre ?? '—' }}</span></td>
                                    <td><span style="font-size:.75rem;color:var(--vs-text-muted)">{{ $pago->referencia ?: '—' }}</span></td>
                                    <td class="rc-ta-right"><span style="font-weight:700;color:#15803d">S/ {{ number_format($pago->monto, 2) }}</span></td>
                                    <td class="rc-ta-right"><span style="font-size:.72rem;color:var(--vs-text-muted)">{{ $pago->created_at ? \Carbon\Carbon::parse($pago->created_at)->format('d/m/Y') : '—' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="padding:.5rem;font-weight:700;font-size:.8rem;color:var(--vs-text)">Total pagado</td>
                                <td class="rc-ta-right" style="padding:.5rem;font-weight:800;color:#15803d">S/ {{ number_format($totalPagado, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                @endif

            </div>

        </div>
    </div>
    @endif
@endif

</x-filament-panels::page>
