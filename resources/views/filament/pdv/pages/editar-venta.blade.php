<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/editar-venta.css') }}?v={{ filemtime(public_path('css/editar-venta.css')) }}">

@php
    $esSerieOriginal = $serieId == $venta?->serie_id;
    $correlativo     = $venta ? str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT) : '';
    $esTicketActual  = $this->esTiketActual();
@endphp

<div class="ev-layout" wire:key="ev-main">

    {{-- ══ COLUMNA PRINCIPAL ══════════════════════════════════════════════════ --}}
    <div class="ev-col-main">

        {{-- 1+2. COMPROBANTE + CLIENTE EN LA MISMA FILA ─────────────────────── --}}
        <div class="ev-top-row">

            <x-filament::section heading="Comprobante" icon="heroicon-o-document-text">
                <div class="ev-comp-grid">
                    <div>
                        <div class="ev-field-label">Serie / Tipo</div>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="serieId">
                                @foreach($seriesDisponibles as $s)
                                    <option value="{{ $s['id'] }}" @selected($serieId == $s['id'])>{{ $s['label'] }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <div class="ev-field-label">Correlativo</div>
                        <div class="ev-corr-display">
                            @if($esSerieOriginal)
                                {{ $correlativo }}
                            @else
                                <span class="ev-corr-nuevo">Se asignará al guardar</span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section heading="Cliente" icon="heroicon-o-user">
                <div class="ev-cliente-wrap">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            placeholder="Buscar por nombre, apellidos o documento…"
                            wire:model.live.debounce.300ms="clienteBusqueda"
                            autocomplete="off" />
                    </x-filament::input.wrapper>

                    @if($clienteId)
                    <div class="ev-cliente-sel">
                        <x-filament::badge color="primary" icon="heroicon-m-check-circle" size="lg">
                            {{ $clienteNombre }}
                        </x-filament::badge>
                        <button type="button" class="ev-badge-remove" wire:click="limpiarCliente" title="Quitar cliente">
                            <x-filament::icon icon="heroicon-m-x-mark" style="width:.875rem;height:.875rem;" />
                        </button>
                    </div>
                    @endif

                    @if(count($clienteSugs))
                    <div class="ev-sug-list">
                        @foreach($clienteSugs as $sug)
                        <div class="ev-sug-item" wire:click="seleccionarCliente({{ $sug['id'] }})">
                            <x-filament::icon icon="heroicon-m-user" style="width:.875rem;height:.875rem;flex-shrink:0;color:#9ca3af;" />
                            <span>{{ $sug['nombre'] }}</span>
                            @if($sug['doc'])<span class="ev-sug-doc">{{ $sug['doc'] }}</span>@endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </x-filament::section>

        </div>{{-- /ev-top-row --}}

        {{-- 3. BÚSQUEDA DE PRODUCTOS ────────────────────────────────────────── --}}
        <x-filament::section heading="Productos" icon="heroicon-o-shopping-bag">
            <div class="ev-search-wrap">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        placeholder="Buscar por nombre, código de barras o código interno…"
                        wire:model.live.debounce.300ms="busquedaProd"
                        autocomplete="off"
                        prefix-icon="heroicon-m-magnifying-glass" />
                </x-filament::input.wrapper>

                @if(count($resultadosProd))
                <div class="ev-search-results">
                    @foreach($resultadosProd as $prod)
                        @if($prod['tiene_variantes'])
                            <div class="ev-prod-header">
                                <x-filament::icon icon="heroicon-m-squares-2x2" style="width:.8rem;height:.8rem;" />
                                {{ $prod['nombre'] }}
                            </div>
                            @foreach($prod['variantes'] as $var)
                            <div class="ev-var-row"
                                wire:click="agregarProducto({{ $prod['id'] }}, {{ $var['id'] }})"
                                wire:key="var-{{ $var['id'] }}">
                                <div class="ev-prod-info">
                                    <div class="ev-prod-name">{{ $var['nombre'] }}</div>
                                    <div class="ev-prod-price">S/ {{ number_format($var['precio'], 2) }}</div>
                                </div>
                                <div class="ev-prod-stock">
                                    {{ $var['stock_real'] !== null ? number_format($var['stock_real'], 0).' uds' : '—' }}
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="ev-prod-row"
                                wire:click="agregarProducto({{ $prod['id'] }})"
                                wire:key="prod-{{ $prod['id'] }}">
                                <div class="ev-prod-info">
                                    <div class="ev-prod-name">{{ $prod['nombre'] }}</div>
                                    <div class="ev-prod-price">S/ {{ number_format($prod['precio'], 2) }}</div>
                                </div>
                                <div class="ev-prod-stock">
                                    {{ $prod['stock_real'] !== null ? number_format($prod['stock_real'], 0).' uds' : '—' }}
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
                @endif
            </div>
        </x-filament::section>

        {{-- 4. TABLA DE ÍTEMS (Filament nativo) ──────────────────────────────── --}}
        {{ $this->table }}

    </div>{{-- /ev-col-main --}}

    {{-- ══ COLUMNA LATERAL (sticky) ═══════════════════════════════════════════ --}}
    <div class="ev-col-side">

        {{-- 5. MÉTODOS DE PAGO ───────────────────────────────────────────────── --}}
        <x-filament::section heading="Métodos de pago" icon="heroicon-o-credit-card">
            @foreach($pagos as $idx => $pago)
            <div class="ev-pago-row" wire:key="pago-{{ $idx }}">
                <div class="ev-pago-metodo">
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:change="actualizarMetodoPago({{ $idx }}, $event.target.value)">
                            @foreach($metodosPago as $m)
                                @php $esCredito = ($m['condicion_pago'] ?? 'contado') === 'credito'; @endphp
                                @if(! $esCredito || $esTicketActual)
                                <option value="{{ $m['id'] }}" @selected($pago['metodo_pago_id'] == $m['id'])>
                                    {{ $m['nombre'] }}
                                </option>
                                @endif
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="ev-pago-monto">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="number" min="0" step="0.01"
                            value="{{ $pago['monto'] }}"
                            placeholder="Monto"
                            wire:change="actualizarMontoPago({{ $idx }}, $event.target.value)" />
                    </x-filament::input.wrapper>
                </div>
                <div class="ev-pago-ref">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            value="{{ $pago['referencia'] }}"
                            placeholder="Referencia (opc.)"
                            wire:change="actualizarReferenciaPago({{ $idx }}, $event.target.value)" />
                    </x-filament::input.wrapper>
                </div>
                <button type="button" class="ev-del-btn ev-del-btn--sm"
                    wire:click="eliminarPago({{ $idx }})" title="Quitar pago">
                    <x-filament::icon icon="heroicon-m-x-mark" style="width:.875rem;height:.875rem;" />
                </button>
            </div>
            @endforeach

            <x-filament::button
                color="gray"
                icon="heroicon-m-plus"
                outlined
                size="sm"
                wire:click="agregarPago"
                class="ev-btn-add-pago">
                Agregar método de pago
            </x-filament::button>
        </x-filament::section>

        {{-- 6. RESUMEN ──────────────────────────────────────────────────────── --}}
        <x-filament::section heading="Resumen" icon="heroicon-o-calculator">
            {{-- Descuento --}}
            <div class="ev-descuento-row">
                <div class="ev-field-label">Descuento global (S/)</div>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="number" min="0" step="0.01"
                        wire:model.live.debounce.400ms="descuentoInput" />
                </x-filament::input.wrapper>
            </div>

            <div class="ev-totales">
                <div class="ev-total-line">
                    <span>Subtotal</span>
                    <span>S/ {{ number_format($this->getSubtotal(), 2) }}</span>
                </div>
                @if($this->getDescuento() > 0)
                <div class="ev-total-line ev-total-line--desc">
                    <span>Descuento</span>
                    <span>− S/ {{ number_format($this->getDescuento(), 2) }}</span>
                </div>
                @endif
                @if(! $esTicketActual)
                <div class="ev-total-line ev-total-line--muted">
                    <span>Op. gravadas</span>
                    <span>S/ {{ number_format($this->getOpGravadas(), 2) }}</span>
                </div>
                <div class="ev-total-line ev-total-line--muted">
                    <span>IGV ({{ number_format($venta?->empresa?->igv_porcentaje ?? 18, 0) }}%)</span>
                    <span>S/ {{ number_format($this->getIgv(), 2) }}</span>
                </div>
                @endif
                <div class="ev-total-line ev-grand">
                    <span>Total</span>
                    <span>S/ {{ number_format($this->getTotal(), 2) }}</span>
                </div>
                @if(count($pagos) > 0)
                <div class="ev-total-line ev-total-line--muted" style="margin-top:.25rem;">
                    <span>Pagado</span>
                    <span>S/ {{ number_format($this->getTotalPagado(), 2) }}</span>
                </div>
                @endif
                @if($this->getVuelto() > 0)
                <div class="ev-vuelto-box">
                    <span class="ev-vuelto-label">
                        <x-filament::icon icon="heroicon-m-arrow-uturn-left" style="width:.9rem;height:.9rem;" />
                        Vuelto
                    </span>
                    <span class="ev-vuelto-monto">S/ {{ number_format($this->getVuelto(), 2) }}</span>
                </div>
                @endif
            </div>

            {{-- Acciones --}}
            <div class="ev-actions">
                <x-filament::button
                    color="gray"
                    outlined
                    wire:click="cancelar"
                    wire:loading.attr="disabled"
                    wire:target="cancelar,guardar,guardarConfirmado">
                    <x-filament::loading-indicator wire:loading wire:target="cancelar" class="h-4 w-4" />
                    <span wire:loading.remove wire:target="cancelar">Cancelar</span>
                    <span wire:loading wire:target="cancelar">Cancelando…</span>
                </x-filament::button>

                <x-filament::button
                    color="primary"
                    icon="heroicon-m-check"
                    wire:click="guardar"
                    wire:loading.attr="disabled"
                    wire:target="guardar,guardarConfirmado"
                    :disabled="empty($items)">
                    <x-filament::loading-indicator wire:loading wire:target="guardar,guardarConfirmado" class="h-4 w-4" />
                    <span wire:loading.remove wire:target="guardar,guardarConfirmado">Guardar cambios</span>
                    <span wire:loading wire:target="guardar,guardarConfirmado">Guardando…</span>
                </x-filament::button>
            </div>
        </x-filament::section>

    </div>{{-- /ev-col-side --}}

</div>{{-- /ev-layout --}}

{{-- ══ MODAL CONFIRMACIÓN ═════════════════════════════════════════════════════ --}}
@if($mostrarConfirmacion)
<div class="ev-modal-backdrop">
    <div class="ev-confirm-box">
        <div class="ev-confirm-header">
            <div class="ev-confirm-icon">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width:1.25rem;height:1.25rem;color:#d97706;" />
            </div>
            <div>
                <p class="ev-confirm-title">Pagos incompletos</p>
                <p class="ev-confirm-sub">{{ $mensajeConfirmacion }}</p>
            </div>
        </div>
        <div class="ev-actions">
            <x-filament::button
                color="gray"
                outlined
                wire:click="cancelarConfirmacion"
                wire:loading.attr="disabled"
                wire:target="guardarConfirmado">
                Revisar pagos
            </x-filament::button>
            <x-filament::button
                color="warning"
                icon="heroicon-m-check"
                wire:click="guardarConfirmado"
                wire:loading.attr="disabled"
                wire:target="guardarConfirmado">
                <x-filament::loading-indicator wire:loading wire:target="guardarConfirmado" class="h-4 w-4" />
                <span wire:loading.remove wire:target="guardarConfirmado">Guardar de todas formas</span>
                <span wire:loading wire:target="guardarConfirmado">Guardando…</span>
            </x-filament::button>
        </div>
    </div>
</div>
@endif

</x-filament-panels::page>
