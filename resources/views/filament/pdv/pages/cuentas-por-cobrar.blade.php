<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/cuentas-por-cobrar.css') }}?v={{ filemtime(public_path('css/cuentas-por-cobrar.css')) }}">
<link rel="stylesheet" href="{{ asset('css/venta-detalle-modal.css') }}?v={{ filemtime(public_path('css/venta-detalle-modal.css')) }}">

@php
    $resumen      = $this->getResumen();
    $ventas       = $this->getVentas();
    $metodosPago  = $this->getMetodosPago();
    $hoy          = now()->toDateString();
@endphp

{{-- ── Título ─────────────────────────────────────────────────────────────── --}}
<div class="cpc-title">
    <h1>{{ $this->filtroClienteNombre ? "Créditos — {$this->filtroClienteNombre}" : 'Cuentas por Cobrar' }}</h1>
    <p>{{ $this->filtroClienteNombre ? 'Comprobantes a crédito con saldo pendiente' : 'Ventas a crédito con saldo pendiente de cobro' }}</p>
</div>

{{-- ── KPIs ────────────────────────────────────────────────────────────────── --}}
<div class="cpc-kpis">

    <div class="cpc-kpi">
        <span class="cpc-kpi__label">Total créditos</span>
        <span class="cpc-kpi__value">{{ $resumen['total_creditos'] }}</span>
        <span class="cpc-kpi__sub">S/ {{ number_format($resumen['total_facturado'], 2) }} facturado</span>
    </div>

    <div class="cpc-kpi">
        <span class="cpc-kpi__label">Cobrado</span>
        <span class="cpc-kpi__value" style="color:var(--cpc-green)">S/ {{ number_format($resumen['total_cobrado'], 2) }}</span>
        <span class="cpc-kpi__sub">pagos recibidos</span>
    </div>

    <div class="cpc-kpi {{ $resumen['total_pendiente'] > 0 ? 'cpc-kpi--amber' : '' }}">
        <span class="cpc-kpi__label">Por cobrar</span>
        <span class="cpc-kpi__value">S/ {{ number_format($resumen['total_pendiente'], 2) }}</span>
        <span class="cpc-kpi__sub">saldo pendiente</span>
    </div>

    @if($resumen['cuentas_vencidas'] > 0)
    <div class="cpc-kpi cpc-kpi--red">
        <span class="cpc-kpi__label">Vencidas</span>
        <span class="cpc-kpi__value">{{ $resumen['cuentas_vencidas'] }}</span>
        <span class="cpc-kpi__sub">S/ {{ number_format($resumen['monto_vencido'], 2) }} en riesgo</span>
    </div>
    @endif

</div>

{{-- ── Filtros ──────────────────────────────────────────────────────────────── --}}
<div class="cpc-form-wrap">
    {{ $this->form }}
    @if($this->hayFiltros())
        <div class="cpc-form-limpiar">
            <button wire:click="limpiarFiltros" class="cpc-btn-limpiar">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Limpiar filtros
            </button>
        </div>
    @endif
</div>

{{-- ── Panel tabla ─────────────────────────────────────────────────────────── --}}
<div class="cpc-panel">
    <div class="cpc-table-wrap">
        <table class="cpc-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Comprobante</th>
                    <th>Cliente</th>
                    <th class="text-center">Estado</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Pagado</th>
                    <th class="text-right">Pendiente</th>
                    <th class="text-center">Vencimiento</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventas as $venta)
                    @php
                        $vencida = $venta->fecha_vencimiento && $venta->fecha_vencimiento->isPast();
                    @endphp
                    <tr class="{{ $vencida ? 'cpc-row--vencida' : '' }}">

                        {{-- Fecha --}}
                        <td>
                            <div class="cpc-fecha">{{ \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y') }}</div>
                            <div class="cpc-hora">{{ \Carbon\Carbon::parse($venta->fecha_emision)->format('H:i') }}</div>
                        </td>

                        {{-- Comprobante --}}
                        <td>
                            <span class="cpc-comp">{{ $venta->serie->serie }}-{{ $venta->correlativo }}</span>
                        </td>

                        {{-- Estado --}}
                        <td class="text-center">
                            @if($venta->estado_pago === 'pagado')
                                <span class="cpc-estado cpc-estado--pagado">Pagado</span>
                            @else
                                <span class="cpc-estado cpc-estado--pendiente">Pendiente</span>
                            @endif
                        </td>

                        {{-- Cliente --}}
                        <td>
                            <div class="cpc-cliente-nombre">{{ $venta->cliente_nombre ?: 'Cliente general' }}</div>
                            @if($venta->cliente_num_doc)
                                <div class="cpc-cliente-doc">{{ $venta->cliente_tipo_doc }} {{ $venta->cliente_num_doc }}</div>
                            @endif
                        </td>

                        {{-- Total --}}
                        <td class="text-right">
                            <div class="cpc-monto">S/ {{ number_format((float)$venta->total, 2) }}</div>
                        </td>

                        {{-- Pagado --}}
                        <td class="text-right">
                            <div class="cpc-monto-sub">S/ {{ number_format((float)$venta->monto_pagado, 2) }}</div>
                        </td>

                        {{-- Pendiente --}}
                        <td class="text-right">
                            @if((float)$venta->saldo_pendiente > 0)
                                <span class="cpc-saldo">S/ {{ number_format((float)$venta->saldo_pendiente, 2) }}</span>
                            @else
                                <span class="cpc-monto-sub">—</span>
                            @endif
                        </td>

                        {{-- Vencimiento --}}
                        <td class="text-center">
                            @if($venta->fecha_vencimiento)
                                @if($vencida)
                                    <span class="cpc-venc cpc-venc--vencida">
                                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                                        </svg>
                                        {{ $venta->fecha_vencimiento->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="cpc-venc cpc-venc--ok">
                                        {{ $venta->fecha_vencimiento->format('d/m/Y') }}
                                    </span>
                                @endif
                            @else
                                <span class="cpc-venc--sin">Sin fecha</span>
                            @endif
                        </td>

                        {{-- Acciones --}}
                        <td class="text-center">
                            <div class="cpc-acciones">
                                <button wire:click="abrirModalDetalle({{ $venta->id }})" class="vdm-btn-ver" title="Ver detalle de la venta">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Ver
                                </button>
                                <button
                                    wire:click="abrirModalHistorial({{ $venta->id }})"
                                    class="cpc-btn-historial"
                                    title="Ver historial de pagos"
                                >
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                                @if($venta->estado_pago === 'pendiente')
                                <button
                                    wire:click="abrirModalCobro({{ $venta->id }})"
                                    class="cpc-btn-cobrar"
                                >
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Cobrar
                                </button>
                                @endif
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="cpc-empty">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75"/>
                                </svg>
                                <p>No hay cuentas pendientes de cobro</p>
                                @if($this->hayFiltros())
                                    <button wire:click="limpiarFiltros" class="cpc-btn-limpiar" style="margin:0 auto;">
                                        Quitar filtros
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($ventas->hasPages())
        <div class="cpc-pagination">
            {{ $ventas->links('vendor.pagination.pdv') }}
        </div>
    @endif
</div>

{{-- ── Modal de historial de pagos ─────────────────────────────────────────── --}}
@include('filament.pdv.partials.venta-detalle-modal')

@if($modalHistorial && $historialVenta)
<div class="cpc-overlay" wire:click.self="cerrarModalHistorial">
    <div class="cpc-modal cpc-modal--historial" @click.stop>

        <div class="cpc-modal__head">
            <div>
                <h2 class="cpc-modal__titulo">Historial de pagos</h2>
                <span class="cpc-modal__sub">{{ $historialVenta['comprobante'] }}</span>
            </div>
            <button wire:click="cerrarModalHistorial" class="cpc-modal__cerrar" type="button">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="cpc-modal__body">

            {{-- Resumen ─────────────────────────────────────────────────── --}}
            <div class="cpc-hist-resumen">
                <div class="cpc-hist-resumen__fila">
                    <span>Cliente</span>
                    <span>
                        {{ $historialVenta['cliente'] }}
                        @if($historialVenta['cliente_doc'])
                            <small style="color:var(--cpc-text-faint)"> — {{ $historialVenta['cliente_doc'] }}</small>
                        @endif
                    </span>
                </div>
                <div class="cpc-hist-resumen__fila">
                    <span>Emisión</span>
                    <span>{{ $historialVenta['fecha_emision'] }}</span>
                </div>
                @if($historialVenta['fecha_vencimiento'])
                <div class="cpc-hist-resumen__fila">
                    <span>Vencimiento</span>
                    <span>{{ $historialVenta['fecha_vencimiento'] }}</span>
                </div>
                @endif
                <div class="cpc-hist-resumen__fila">
                    <span>Total</span>
                    <span class="cpc-hist-resumen__monto">S/ {{ number_format($historialVenta['total'], 2) }}</span>
                </div>
            </div>

            {{-- Barra de progreso ────────────────────────────────────────── --}}
            <div class="cpc-hist-progress">
                <div class="cpc-hist-progress__labels">
                    <span>Pagado: <strong>S/ {{ number_format($historialVenta['monto_pagado'], 2) }}</strong></span>
                    @if($historialVenta['saldo_pendiente'] > 0)
                        <span>Pendiente: <strong style="color:var(--cpc-amber)">S/ {{ number_format($historialVenta['saldo_pendiente'], 2) }}</strong></span>
                    @else
                        <span style="color:var(--cpc-green)"><strong>Pagado completo</strong></span>
                    @endif
                </div>
                <div class="cpc-hist-progress__bar">
                    <div class="cpc-hist-progress__fill {{ $historialVenta['saldo_pendiente'] <= 0 ? 'cpc-hist-progress__fill--done' : '' }}"
                         style="width: {{ $historialVenta['porcentaje'] }}%"></div>
                </div>
                <div class="cpc-hist-progress__pct">{{ $historialVenta['porcentaje'] }}% cobrado</div>
            </div>

            {{-- Lista de pagos ───────────────────────────────────────────── --}}
            <div class="cpc-hist-title">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Pagos registrados ({{ count($historialPagos) }})
            </div>

            @if(count($historialPagos) > 0)
                <div class="cpc-hist-lista">
                    @foreach($historialPagos as $i => $pago)
                    <div class="cpc-hist-item">
                        <div class="cpc-hist-item__num">{{ $i + 1 }}</div>
                        <div class="cpc-hist-item__body">
                            <div class="cpc-hist-item__top">
                                <span class="cpc-hist-item__monto">S/ {{ number_format($pago['monto'], 2) }}</span>
                                <span class="cpc-hist-item__metodo">{{ $pago['metodo'] }}</span>
                                <span class="cpc-hist-item__fecha">{{ $pago['fecha'] }} <small>{{ $pago['hora'] }}</small></span>
                            </div>
                            <div class="cpc-hist-item__bottom">
                                @if($pago['referencia'])
                                    <span class="cpc-hist-item__ref">
                                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                                        {{ $pago['referencia'] }}
                                    </span>
                                @endif
                                <span class="cpc-hist-item__cajero">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    {{ $pago['cajero'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="cpc-hist-empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <p>Sin pagos registrados aún</p>
                </div>
            @endif

        </div>

        <div class="cpc-modal__foot">
            <button wire:click="cerrarModalHistorial" class="cpc-btn-cancelar" type="button">Cerrar</button>
            @if($historialVenta['saldo_pendiente'] > 0)
            <button wire:click="cobrarDesdeHistorial({{ $historialVentaId }})" class="cpc-btn-cobrar-hist" type="button">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Registrar cobro
            </button>
            @endif
        </div>

    </div>
</div>
@endif

{{-- ── Modal de cobro ──────────────────────────────────────────────────────── --}}
@if($modalCobro && $ventaModal)
<div class="cpc-overlay" wire:click.self="cerrarModal">
    <div class="cpc-modal" @click.stop>

        {{-- Cabecera --}}
        <div class="cpc-modal__head">
            <h2 class="cpc-modal__titulo">Registrar cobro</h2>
            <button wire:click="cerrarModal" class="cpc-modal__cerrar" type="button">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Cuerpo --}}
        <div class="cpc-modal__body">

            {{-- Resumen de la venta --}}
            <div class="cpc-modal-info">
                <div class="cpc-modal-info__row">
                    <span class="cpc-modal-info__label">Comprobante</span>
                    <span class="cpc-modal-info__val">{{ $ventaModal['comprobante'] }}</span>
                </div>
                <div class="cpc-modal-info__row">
                    <span class="cpc-modal-info__label">Total venta</span>
                    <span class="cpc-modal-info__val">S/ {{ number_format($ventaModal['total'], 2) }}</span>
                </div>
                <div class="cpc-modal-info__row cpc-modal-info__row--full">
                    <span class="cpc-modal-info__label">Cliente</span>
                    <span class="cpc-modal-info__val">
                        {{ $ventaModal['cliente'] }}
                        @if($ventaModal['cliente_doc']) <small style="color:var(--cpc-text-faint)">&nbsp;— {{ $ventaModal['cliente_doc'] }}</small> @endif
                    </span>
                </div>
                <div class="cpc-modal-info__row">
                    <span class="cpc-modal-info__label">Ya pagado</span>
                    <span class="cpc-modal-info__val">S/ {{ number_format($ventaModal['monto_pagado'], 2) }}</span>
                </div>
                <div class="cpc-modal-info__row">
                    <span class="cpc-modal-info__label">Saldo pendiente</span>
                    <span class="cpc-modal-info__val cpc-modal-info__val--saldo {{ $ventaModal['es_vencida'] ? 'cpc-modal-info__val--vencida' : '' }}">
                        S/ {{ number_format($ventaModal['saldo_pendiente'], 2) }}
                    </span>
                </div>
                @if($ventaModal['vencimiento'])
                <div class="cpc-modal-info__row cpc-modal-info__row--full">
                    <span class="cpc-modal-info__label">Vencimiento</span>
                    <span class="cpc-modal-info__val {{ $ventaModal['es_vencida'] ? 'cpc-modal-info__val--vencida' : '' }}">
                        {{ $ventaModal['vencimiento'] }}
                        @if($ventaModal['es_vencida']) &nbsp;<strong>(VENCIDA)</strong> @endif
                    </span>
                </div>
                @endif
            </div>

            {{-- Campos del cobro --}}
            <div class="cpc-modal-campos">

                <div class="cpc-modal-campo">
                    <label>Método de pago <span style="color:var(--cpc-red)">*</span></label>
                    <select wire:model="cobroMetodo">
                        <option value="">Seleccionar…</option>
                        @foreach($metodosPago as $mp)
                            <option value="{{ $mp->id }}">{{ $mp->nombre }}</option>
                        @endforeach
                    </select>
                    @error('cobroMetodo') <div class="cpc-error">{{ $message }}</div> @enderror
                </div>

                <div class="cpc-modal-campo">
                    <label>Monto a cobrar (S/) <span style="color:var(--cpc-red)">*</span></label>
                    <input
                        type="number"
                        wire:model="cobroMonto"
                        min="0.01"
                        max="{{ $ventaModal['saldo_pendiente'] }}"
                        step="0.01"
                        placeholder="0.00"
                    />
                    @error('cobroMonto') <div class="cpc-error">{{ $message }}</div> @enderror
                </div>

                <div class="cpc-modal-campo">
                    <label>Referencia / Nro. operación (opcional)</label>
                    <input
                        type="text"
                        wire:model="cobroRef"
                        placeholder="Ej: transferencia #12345"
                        maxlength="120"
                    />
                </div>

            </div>
        </div>

        {{-- Pie --}}
        <div class="cpc-modal__foot">
            <button wire:click="cerrarModal" class="cpc-btn-cancelar" type="button">
                Cancelar
            </button>
            <button
                wire:click="registrarCobro"
                wire:loading.attr="disabled"
                wire:target="registrarCobro"
                class="cpc-btn-confirmar"
                type="button"
            >
                <span wire:loading.remove wire:target="registrarCobro">Confirmar cobro</span>
                <span wire:loading wire:target="registrarCobro">Procesando…</span>
            </button>
        </div>

    </div>
</div>
@endif

</x-filament-panels::page>
