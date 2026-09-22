<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/cuentas-por-cobrar.css') }}?v={{ filemtime(public_path('css/cuentas-por-cobrar.css')) }}">
<link rel="stylesheet" href="{{ asset('css/venta-detalle-modal.css') }}?v={{ filemtime(public_path('css/venta-detalle-modal.css')) }}">

@php
    $metodosPago = $this->getMetodosPago();
@endphp

{{-- ── Tabla Filament ──────────────────────────────────────────────────────── --}}
{{ $this->table }}

{{-- ── Modal de detalle de venta (HasVentaDetalleModal) ───────────────────── --}}
@include('filament.pdv.partials.venta-detalle-modal')

{{-- ── Modal historial de pagos ───────────────────────────────────────────── --}}
<x-filament::modal
    id="historial-modal"
    :heading="$historialVenta ? 'Historial — ' . ($historialVenta['comprobante'] ?? '') : 'Historial de pagos'"
    :description="$historialVenta ? ($historialVenta['cliente'] ?? '') : null"
    width="lg"
    :close-button="true"
>
    @if($modalHistorial && $historialVenta)

        {{-- Resumen ─────────────────────────────────────────────────── --}}
        <div class="cpc-hist-resumen">
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
            <x-filament::icon icon="heroicon-o-banknotes" style="width:1rem;height:1rem;display:inline-block;vertical-align:middle;margin-right:.25rem;"/>
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
                                    <x-filament::icon icon="heroicon-o-hashtag" style="width:.7rem;height:.7rem;display:inline;"/>
                                    {{ $pago['referencia'] }}
                                </span>
                            @endif
                            <span class="cpc-hist-item__cajero">
                                <x-filament::icon icon="heroicon-o-user" style="width:.7rem;height:.7rem;display:inline;"/>
                                {{ $pago['cajero'] }}
                            </span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @else
            <div class="cpc-hist-empty">
                <x-filament::icon icon="heroicon-o-inbox" style="width:2rem;height:2rem;margin:0 auto .5rem;display:block;opacity:.4;"/>
                <p>Sin pagos registrados aún</p>
            </div>
        @endif

    @endif

    <x-slot name="footer">
        <div style="display:flex;gap:.625rem;justify-content:flex-end;width:100%;">
            <x-filament::button color="gray" wire:click="cerrarModalHistorial" type="button" outlined>
                Cerrar
            </x-filament::button>
            @if($historialVenta && ($historialVenta['saldo_pendiente'] ?? 0) > 0)
                <x-filament::button
                    color="warning"
                    icon="heroicon-o-banknotes"
                    wire:click="cobrarDesdeHistorial({{ $historialVentaId }})"
                    type="button"
                >
                    Registrar cobro
                </x-filament::button>
            @endif
        </div>
    </x-slot>
</x-filament::modal>

{{-- ── Modal de cobro ──────────────────────────────────────────────────────── --}}
<x-filament::modal
    id="cobro-modal"
    :heading="$ventaModal ? 'Registrar cobro' : 'Cobro'"
    :description="$ventaModal ? (($ventaModal['comprobante'] ?? '') . ' — ' . ($ventaModal['cliente'] ?? '')) : null"
    width="md"
    :close-button="true"
>
    @if($modalCobro && $ventaModal)

        {{-- Resumen de la venta --}}
        <div class="cpc-modal-info">
            <div class="cpc-modal-info__row">
                <span class="cpc-modal-info__label">Total venta</span>
                <span class="cpc-modal-info__val">S/ {{ number_format($ventaModal['total'], 2) }}</span>
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
            <div class="cpc-modal-info__row">
                <span class="cpc-modal-info__label">Vencimiento</span>
                <span class="cpc-modal-info__val {{ $ventaModal['es_vencida'] ? 'cpc-modal-info__val--vencida' : '' }}">
                    {{ $ventaModal['vencimiento'] }}
                    @if($ventaModal['es_vencida']) <strong>(VENCIDA)</strong> @endif
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

    @endif

    <x-slot name="footer">
        <div style="display:flex;gap:.625rem;justify-content:flex-end;width:100%;">
            <x-filament::button color="gray" wire:click="cerrarModal" type="button" outlined>
                Cancelar
            </x-filament::button>
            <x-filament::button
                color="success"
                icon="heroicon-o-check-circle"
                wire:click="registrarCobro"
                wire:loading.attr="disabled"
                wire:target="registrarCobro"
                type="button"
            >
                <span wire:loading.remove wire:target="registrarCobro">Confirmar cobro</span>
                <span wire:loading wire:target="registrarCobro">Procesando…</span>
            </x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>

</x-filament-panels::page>
