<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/editar-venta.css') }}?v={{ filemtime(public_path('css/editar-venta.css')) }}">

@php
    $esSerieOriginal = $serieId == $venta?->serie_id;
    $correlativo     = $venta ? str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT) : '';
    $esTicketActual  = $this->esTiketActual();
@endphp

<div class="ev-wrap" wire:key="ev-main">

    {{-- 1. COMPROBANTE ──────────────────────────────────────────────────────── --}}
    <div class="ev-card">
        <div class="ev-card-title">Comprobante</div>
        <div class="ev-comp-grid">
            <div>
                <div class="ev-field-label">Serie / Tipo</div>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="serieId">
                        @foreach($seriesDisponibles as $s)
                            <option value="{{ $s['id'] }}" @selected($serieId == $s['id'])>
                                {{ $s['label'] }}
                            </option>
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
    </div>

    {{-- 2. CLIENTE ────────────────────────────────────────────────────────────── --}}
    <div class="ev-card">
        <div class="ev-card-title">Cliente</div>
        <div class="ev-cliente-wrap">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    placeholder="Buscar por nombre, apellidos o documento…"
                    wire:model.live.debounce.300ms="clienteBusqueda"
                    autocomplete="off" />
            </x-filament::input.wrapper>

            @if($clienteId)
                <div class="ev-cliente-badge">
                    <span>✓ {{ $clienteNombre }}</span>
                    <button type="button" wire:click="limpiarCliente" title="Quitar cliente">✕</button>
                </div>
            @endif

            @if(count($clienteSugs))
            <div class="ev-sug-list">
                @foreach($clienteSugs as $sug)
                <div class="ev-sug-item" wire:click="seleccionarCliente({{ $sug['id'] }})">
                    {{ $sug['nombre'] }}
                    @if($sug['doc'])<span class="ev-sug-doc"> · {{ $sug['doc'] }}</span>@endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- 3. PRODUCTOS ──────────────────────────────────────────────────────────── --}}
    <div class="ev-card">
        <div class="ev-card-title">Productos</div>

        {{-- Búsqueda --}}
        <div class="ev-search-wrap">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    placeholder="Buscar producto por nombre, código de barras o código interno…"
                    wire:model.live.debounce.300ms="busquedaProd"
                    autocomplete="off" />
            </x-filament::input.wrapper>

            @if(count($resultadosProd))
            <div class="ev-search-results">
                @foreach($resultadosProd as $prod)
                    @if($prod['tiene_variantes'])
                        <div class="ev-prod-header">{{ $prod['nombre'] }}</div>
                        @foreach($prod['variantes'] as $var)
                        <div class="ev-var-row"
                            wire:click="agregarProducto({{ $prod['id'] }}, {{ $var['id'] }})"
                            wire:key="var-{{ $var['id'] }}">
                            <div class="ev-prod-info">
                                <div class="ev-prod-name">↳ {{ $var['nombre'] }}</div>
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

        {{-- Tabla --}}
        @if(empty($items))
            <p style="font-size:.875rem;color:#9ca3af;text-align:center;padding:1.25rem 0;margin-top:.75rem;border-top:1px dashed #e5e7eb;">
                Sin productos. Busca y haz clic para agregar.
            </p>
        @else
        <table class="ev-items-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align:right">Precio</th>
                    <th style="text-align:center">Cantidad</th>
                    <th style="text-align:right">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $key => $item)
                <tr wire:key="item-{{ $key }}">
                    <td><div class="ev-item-name">{{ $item['nombre'] }}</div></td>
                    <td style="text-align:right">
                        <x-filament::input.wrapper style="max-width:90px;margin-left:auto;">
                            <x-filament::input
                                type="number" min="0" step="0.01"
                                value="{{ $item['precio'] }}"
                                style="text-align:right"
                                wire:change="actualizarPrecio('{{ $key }}', $event.target.value)" />
                        </x-filament::input.wrapper>
                    </td>
                    <td style="text-align:center">
                        <div class="ev-qty">
                            <button type="button" class="ev-qty-btn"
                                wire:click="decrementarCantidad('{{ $key }}')">−</button>
                            <input type="number" class="ev-qty-val fi-input"
                                value="{{ $item['cantidad'] }}" min="0.001" step="1"
                                wire:change="actualizarCantidad('{{ $key }}', $event.target.value)"
                                style="border:none;border-radius:0;width:2.75rem;padding:.3rem .2rem;text-align:center;">
                            <button type="button" class="ev-qty-btn"
                                wire:click="incrementarCantidad('{{ $key }}')">+</button>
                        </div>
                    </td>
                    <td style="text-align:right">
                        <span class="ev-item-name">
                            S/ {{ number_format($item['precio'] * $item['cantidad'], 2) }}
                        </span>
                    </td>
                    <td style="text-align:center">
                        <button type="button" class="ev-del-btn"
                            wire:click="eliminarItem('{{ $key }}')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- 4. MÉTODOS DE PAGO ───────────────────────────────────────────────────── --}}
    <div class="ev-card">
        <div class="ev-card-title">Métodos de pago</div>

        @foreach($pagos as $idx => $pago)
        <div class="ev-pago-row" wire:key="pago-{{ $idx }}">
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

            <x-filament::input.wrapper>
                <x-filament::input
                    type="number" min="0" step="0.01"
                    value="{{ $pago['monto'] }}"
                    placeholder="Monto"
                    wire:change="actualizarMontoPago({{ $idx }}, $event.target.value)" />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    value="{{ $pago['referencia'] }}"
                    placeholder="Referencia (opc.)"
                    wire:change="actualizarReferenciaPago({{ $idx }}, $event.target.value)" />
            </x-filament::input.wrapper>

            <button type="button" class="ev-del-btn" wire:click="eliminarPago({{ $idx }})">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endforeach

        <button type="button" class="ev-btn ev-btn-ghost" wire:click="agregarPago">
            + Agregar método de pago
        </button>
    </div>

    {{-- 5. RESUMEN + ACCIONES ─────────────────────────────────────────────────── --}}
    <div class="ev-card">
        <div class="ev-card-title">Resumen</div>

        {{-- Descuento --}}
        <div style="margin-bottom:1rem;">
            <div class="ev-field-label">Descuento global</div>
            <div class="ev-desc-row">
                <span class="ev-desc-prefix">S/</span>
                <x-filament::input.wrapper style="max-width:160px;">
                <x-filament::input
                    type="number" min="0" step="0.01"
                    wire:model.live.debounce.400ms="descuentoInput" />
            </x-filament::input.wrapper>
            </div>
        </div>

        <div class="ev-total-line">
            <span>Subtotal</span>
            <span>S/ {{ number_format($this->getSubtotal(), 2) }}</span>
        </div>
        @if($this->getDescuento() > 0)
        <div class="ev-total-line">
            <span>Descuento</span>
            <span style="color:#ef4444;">− S/ {{ number_format($this->getDescuento(), 2) }}</span>
        </div>
        @endif
        @if(! $esTicketActual)
        <div class="ev-total-line" style="color:#6b7280;font-size:.82rem;">
            <span>Op. gravadas</span>
            <span>S/ {{ number_format($this->getOpGravadas(), 2) }}</span>
        </div>
        <div class="ev-total-line" style="color:#6b7280;font-size:.82rem;">
            <span>IGV ({{ number_format($venta?->empresa?->igv_porcentaje ?? 18, 0) }}%)</span>
            <span>S/ {{ number_format($this->getIgv(), 2) }}</span>
        </div>
        @endif
        <div class="ev-total-line ev-grand">
            <span>Total</span>
            <span>S/ {{ number_format($this->getTotal(), 2) }}</span>
        </div>
        @if(count($pagos) > 0)
        <div class="ev-total-line" style="margin-top:.5rem;font-size:.82rem;color:#9ca3af;">
            <span>Total pagos ingresados</span>
            <span>S/ {{ number_format($this->getTotalPagado(), 2) }}</span>
        </div>
        @endif
        @if($this->getVuelto() > 0)
        <div class="ev-vuelto-box">
            <span class="ev-vuelto-label">Vuelto a entregar</span>
            <span class="ev-vuelto-monto">S/ {{ number_format($this->getVuelto(), 2) }}</span>
        </div>
        @endif

        <div class="ev-actions" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid #f3f4f6;">
            <button type="button" class="ev-btn ev-btn-secondary"
                wire:click="cancelar"
                wire:loading.attr="disabled"
                wire:target="cancelar,guardar,guardarConfirmado"
                @disabled($guardando)>
                <x-filament::loading-indicator
                    wire:loading wire:target="cancelar"
                    class="h-4 w-4" />
                <span wire:loading.remove wire:target="cancelar">Cancelar</span>
                <span wire:loading wire:target="cancelar">Cancelando…</span>
            </button>
            <button type="button" class="ev-btn ev-btn-primary"
                wire:click="guardar"
                wire:loading.attr="disabled"
                wire:target="guardar,guardarConfirmado"
                @if(empty($items)) disabled @endif>
                <x-filament::loading-indicator
                    wire:loading wire:target="guardar,guardarConfirmado"
                    class="h-4 w-4" />
                <span wire:loading.remove wire:target="guardar,guardarConfirmado">Guardar cambios</span>
                <span wire:loading wire:target="guardar,guardarConfirmado">Guardando…</span>
            </button>
        </div>
    </div>

    {{-- Modal de confirmación de pagos ─────────────────────────────────────── --}}
    @if($mostrarConfirmacion)
    <div style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);">
        <div class="ev-confirm-box">

            <div style="display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.25rem;">
                <div style="flex-shrink:0;width:2.5rem;height:2.5rem;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                        viewBox="0 0 24 24" stroke="#d97706" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <div>
                    <p style="font-weight:700;font-size:.9375rem;margin:0 0 .375rem;">
                        Pagos incompletos
                    </p>
                    <p class="ev-confirm-sub" style="font-size:.875rem;margin:0;line-height:1.5;">
                        {{ $mensajeConfirmacion }}
                    </p>
                </div>
            </div>

            <div class="ev-actions">
                <button type="button" class="ev-btn ev-btn-secondary"
                    wire:click="cancelarConfirmacion"
                    wire:loading.attr="disabled" wire:target="guardarConfirmado">
                    Revisar pagos
                </button>
                <button type="button" class="ev-btn ev-btn-primary"
                    wire:click="guardarConfirmado"
                    wire:loading.attr="disabled" wire:target="guardarConfirmado">
                    <x-filament::loading-indicator
                        wire:loading wire:target="guardarConfirmado"
                        class="h-4 w-4" />
                    <span wire:loading.remove wire:target="guardarConfirmado">Guardar de todas formas</span>
                    <span wire:loading wire:target="guardarConfirmado">Guardando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
</x-filament-panels::page>
