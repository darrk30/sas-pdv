<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/cuentas-por-cobrar.css') }}?v={{ filemtime(public_path('css/cuentas-por-cobrar.css')) }}">

@php
    $metodosPago = $this->getMetodosPago();
@endphp

{{-- ── Tabla Filament ──────────────────────────────────────────────────────── --}}
{{ $this->table }}

{{-- ── Modal historial de pagos ───────────────────────────────────────────── --}}
<x-filament::modal
    id="historial-pagar-modal"
    :heading="$historialCompra ? 'Historial — ' . ($historialCompra['comprobante'] ?? '') : 'Historial de pagos'"
    :description="$historialCompra ? ($historialCompra['proveedor'] ?? '') : null"
    width="lg"
    :close-button="true"
>
    @if($modalHistorial && $historialCompra)

        {{-- Resumen ─────────────────────────────────────────────────── --}}
        <div class="cpc-hist-resumen">
            <div class="cpc-hist-resumen__fila">
                <span>Fecha compra</span>
                <span>{{ $historialCompra['fecha_compra'] }}</span>
            </div>
            <div class="cpc-hist-resumen__fila">
                <span>Total</span>
                <span class="cpc-hist-resumen__monto">S/ {{ number_format($historialCompra['total'], 2) }}</span>
            </div>
        </div>

        {{-- Barra de progreso ────────────────────────────────────────── --}}
        <div class="cpc-hist-progress">
            <div class="cpc-hist-progress__labels">
                <span>Pagado: <strong>S/ {{ number_format($historialCompra['monto_pagado'], 2) }}</strong></span>
                @if($historialCompra['saldo_pendiente'] > 0)
                    <span>Pendiente: <strong style="color:var(--cpc-amber)">S/ {{ number_format($historialCompra['saldo_pendiente'], 2) }}</strong></span>
                @else
                    <span style="color:var(--cpc-green)"><strong>Pagado completo</strong></span>
                @endif
            </div>
            <div class="cpc-hist-progress__bar">
                <div class="cpc-hist-progress__fill {{ $historialCompra['saldo_pendiente'] <= 0 ? 'cpc-hist-progress__fill--done' : '' }}"
                     style="width: {{ $historialCompra['porcentaje'] }}%"></div>
            </div>
            <div class="cpc-hist-progress__pct">{{ $historialCompra['porcentaje'] }}% pagado</div>
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
                        @if($pago['referencia'])
                        <div class="cpc-hist-item__bottom">
                            <span class="cpc-hist-item__ref">
                                <x-filament::icon icon="heroicon-o-hashtag" style="width:.7rem;height:.7rem;display:inline;"/>
                                {{ $pago['referencia'] }}
                            </span>
                        </div>
                        @endif
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
            @if($historialCompra && ($historialCompra['saldo_pendiente'] ?? 0) > 0)
                <x-filament::button
                    color="warning"
                    icon="heroicon-o-banknotes"
                    wire:click="pagarDesdeHistorial({{ $historialCompraId }})"
                    type="button"
                >
                    Registrar pago
                </x-filament::button>
            @endif
        </div>
    </x-slot>
</x-filament::modal>

{{-- ── Modal de pago ────────────────────────────────────────────────────────── --}}
<x-filament::modal
    id="pago-modal"
    :heading="$compraModal ? 'Registrar pago' : 'Pago'"
    :description="$compraModal ? (($compraModal['comprobante'] ?? '') . ' — ' . ($compraModal['proveedor'] ?? '')) : null"
    width="md"
    :close-button="true"
>
    @if($modalPago && $compraModal)

        {{-- Resumen de la compra --}}
        <div class="cpc-modal-info">
            <div class="cpc-modal-info__row">
                <span class="cpc-modal-info__label">Total compra</span>
                <span class="cpc-modal-info__val">S/ {{ number_format($compraModal['total'], 2) }}</span>
            </div>
            <div class="cpc-modal-info__row">
                <span class="cpc-modal-info__label">Ya pagado</span>
                <span class="cpc-modal-info__val">S/ {{ number_format($compraModal['monto_pagado'], 2) }}</span>
            </div>
            <div class="cpc-modal-info__row">
                <span class="cpc-modal-info__label">Saldo pendiente</span>
                <span class="cpc-modal-info__val cpc-modal-info__val--saldo">
                    S/ {{ number_format($compraModal['saldo_pendiente'], 2) }}
                </span>
            </div>
        </div>

        {{-- Campos del pago --}}
        <div class="cpc-modal-campos">

            <div class="cpc-modal-campo">
                <label>Método de pago <span style="color:var(--cpc-red)">*</span></label>
                <select wire:model="pagoMetodo">
                    <option value="">Seleccionar…</option>
                    @foreach($metodosPago as $mp)
                        <option value="{{ $mp->id }}">{{ $mp->nombre }}</option>
                    @endforeach
                </select>
                @error('pagoMetodo') <div class="cpc-error">{{ $message }}</div> @enderror
            </div>

            <div class="cpc-modal-campo">
                <label>Monto a pagar (S/) <span style="color:var(--cpc-red)">*</span></label>
                <input
                    type="number"
                    wire:model="pagoMonto"
                    min="0.01"
                    max="{{ $compraModal['saldo_pendiente'] }}"
                    step="0.01"
                    placeholder="0.00"
                />
                @error('pagoMonto') <div class="cpc-error">{{ $message }}</div> @enderror
            </div>

            <div class="cpc-modal-campo">
                <label>Referencia / Nro. operación (opcional)</label>
                <input
                    type="text"
                    wire:model="pagoRef"
                    placeholder="Ej: transferencia #12345"
                    maxlength="120"
                />
            </div>

        </div>

    @endif

    <x-slot name="footer">
        <div style="display:flex;gap:.625rem;justify-content:flex-end;width:100%;">
            <x-filament::button color="gray" wire:click="cerrarModalPago" type="button" outlined>
                Cancelar
            </x-filament::button>
            <x-filament::button
                color="success"
                icon="heroicon-o-check-circle"
                wire:click="registrarPago"
                wire:loading.attr="disabled"
                wire:target="registrarPago"
                type="button"
            >
                <span wire:loading.remove wire:target="registrarPago">Confirmar pago</span>
                <span wire:loading wire:target="registrarPago">Procesando…</span>
            </x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>

</x-filament-panels::page>
