<x-filament-panels::page>

<link rel="stylesheet" href="{{ asset('css/punto-de-venta.css') }}?v={{ filemtime(public_path('css/punto-de-venta.css')) }}">

@php
    $orden    = $record->load(['detalles', 'mesa.piso', 'vendedor']);
    $mesa     = $orden->mesa;
    $series   = $this->getSeries();
    $metodos  = $this->getMetodosPago();
    $totalDesc = $this->getTotalConDescuento();
    $totalPagado = collect($pagosAgregados)->sum('monto');
    $vuelto      = max(0, $totalPagado - $totalDesc);
    $metodoActual = $metodos->firstWhere('id', $metodoPagoId);
    $requiereRef  = (bool) ($metodoActual?->requiere_referencia ?? false);
    $empresa  = \Filament\Facades\Filament::getTenant();
    $tieneFE  = $empresa->tieneFacturacionElectronica();
@endphp

{{-- Modal nuevo cliente (componente compartido con PDV) --}}
<livewire:pdv.nuevo-cliente-modal wire:key="nuevo-cliente-modal" />

{{-- Modal venta completada / impresión (componente compartido con PDV) --}}
<livewire:pdv.venta-completada-modal wire:key="venta-completada-modal" />

<div class="cobrar-root">

    {{-- Header --}}
    <div class="pdv-header">
        <div class="pdv-header__left">
            <p class="pdv-header__titulo">Cobrar Pedido #{{ $orden->numero }}</p>
            <p class="pdv-header__sub">
                @if($mesa)Mesa: <strong>{{ $mesa->nombre }}</strong>@if($mesa->piso) · {{ $mesa->piso->nombre }}@endif &nbsp;·&nbsp;@endif
                Total: <strong>S/ {{ number_format($orden->total, 2) }}</strong>
            </p>
        </div>
        <div class="pdv-header__right">
            <button wire:click="volverAlPedido" class="mm-btn">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:.9rem;height:.9rem;flex-shrink:0;" aria-hidden="true"><path fill-rule="evenodd" d="M11.03 3.97a.75.75 0 0 1 0 1.06l-6.22 6.22H21a.75.75 0 0 1 0 1.5H4.81l6.22 6.22a.75.75 0 1 1-1.06 1.06l-7.5-7.5a.75.75 0 0 1 0-1.06l7.5-7.5a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                Volver al pedido
            </button>
        </div>
    </div>

    <div class="cobrar-wrap">

        {{-- ── Columna izquierda: resumen + descuento ── --}}
        <div class="cobrar-left">
            <div class="cobrar-card">
                <p class="cobrar-sec-title">Detalle del pedido</p>
                <div class="cobrar-items">
                    @foreach($orden->detalles as $det)
                    <div class="cobrar-item">
                        <span class="cobrar-item__cant">{{ (int) $det->cantidad }}×</span>
                        <span class="cobrar-item__nombre">{{ $det->descripcion }}</span>
                        <span class="cobrar-item__total">S/ {{ number_format($det->total, 2) }}</span>
                    </div>
                    @endforeach
                </div>

                <div class="cobrar-total-row">
                    <span>Subtotal</span>
                    <span>S/ {{ number_format($orden->subtotal, 2) }}</span>
                </div>

                {{-- Descuento inline --}}
                <div class="cobrar-desc-row">
                    <label class="cobrar-desc-label">Descuento S/</label>
                    <input
                        type="number"
                        wire:model.live.debounce.500ms="descuentoInput"
                        class="cobrar-desc-input"
                        min="0"
                        max="{{ $orden->total }}"
                        step="0.01"
                        placeholder="0.00"
                    />
                </div>

                @if($this->getDescuento() > 0)
                <div class="cobrar-total-row cobrar-total-row--descuento">
                    <span>Descuento</span>
                    <span>−S/ {{ number_format($this->getDescuento(), 2) }}</span>
                </div>
                @endif

                <div class="cobrar-total-row cobrar-total-row--final">
                    <span>Total a cobrar</span>
                    <span>S/ {{ number_format($totalDesc, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- ── Columna derecha: comprobante + cliente + pago ── --}}
        <div class="cobrar-right">
            <div class="cobrar-card">

                {{-- ── Comprobante ── --}}
                <p class="cobrar-sec-title">Comprobante</p>
                <div class="pdv-comprobante" style="margin-bottom:.75rem;">
                    @foreach([
                        ['tipo' => 'factura', 'label' => 'Factura',  'color' => 'info',    'soloFE' => true],
                        ['tipo' => 'boleta',  'label' => 'Boleta',   'color' => 'success', 'soloFE' => true],
                        ['tipo' => 'ticket',  'label' => 'Ticket',   'color' => 'warning', 'soloFE' => false],
                    ] as $c)
                        @if($c['soloFE'] && ! $tieneFE) @continue @endif
                        @php
                            $serieTipo = $series->firstWhere('tipo.value', $c['tipo']);
                            $activo    = $tipoComprobante === $c['tipo'];
                            $invalido  = $activo && $c['tipo'] === 'factura' && $clienteTipoDoc !== 'ruc';
                        @endphp
                        @if(! $serieTipo) @continue @endif
                        <button
                            class="pdv-comp-btn pdv-comp-btn--{{ $c['color'] }} {{ $activo ? 'pdv-comp-btn--activo' : '' }}"
                            wire:click="seleccionarComprobante('{{ $c['tipo'] }}')"
                        >
                            <span class="pdv-comp-btn__label">{{ $c['label'] }}</span>
                            <span class="pdv-comp-btn__serie">{{ $serieTipo->serie }}</span>
                            @if($invalido)
                                <span class="pdv-comp-btn__alerta">Requiere RUC</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="cobrar-divider"></div>

                {{-- ── Cliente ── --}}
                <p class="cobrar-sec-title" style="margin-top:.75rem;">
                    Cliente <span style="font-weight:400;font-size:.73rem;color:var(--pdv-text-muted,#64748b);">(opcional)</span>
                </p>
                <div class="pdv-cliente" style="margin-bottom:.75rem;">
                    <div class="pdv-cliente__row">
                        <div class="pdv-cliente__search-wrap">
                            <svg class="pdv-cliente__icono" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            <input
                                type="text"
                                class="pdv-cliente__input"
                                wire:model.live.debounce.300ms="clienteBusqueda"
                                placeholder="Buscar por nombre o documento..."
                            />
                            @if($clienteId)
                                <button class="pdv-cliente__clear" wire:click="limpiarCliente">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                        </div>
                        <button class="pdv-cliente__nuevo-btn" wire:click="abrirModalNuevoCliente" title="Nuevo cliente">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </button>
                    </div>

                    @if($mostrarSugerencias)
                        @php $sugeridos = $this->getClientesSugeridos(); @endphp
                        @if($sugeridos->isNotEmpty())
                            <div class="pdv-cliente__dropdown">
                                @foreach($sugeridos as $c)
                                    <button class="pdv-cliente__opcion" wire:click="seleccionarCliente({{ $c->id }})">
                                        <span class="pdv-cliente__opcion-nombre">{{ $c->nombre_completo }}</span>
                                        <span class="pdv-cliente__opcion-doc">{{ strtoupper($c->tipo_documento->value) }} {{ $c->numero_documento }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="pdv-cliente__dropdown">
                                <p class="pdv-cliente__no-result">Sin resultados</p>
                            </div>
                        @endif
                    @endif

                    @if($clienteId)
                    <div class="pdv-cliente__seleccionado">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:.9rem;height:.9rem;flex-shrink:0;color:#16a34a;" aria-hidden="true"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/></svg>
                        {{ $clienteNombre }} ({{ strtoupper($clienteTipoDoc) }})
                    </div>
                    @endif
                </div>

                <div class="cobrar-divider"></div>

                {{-- ── Pago ── --}}
                <p class="cobrar-sec-title" style="margin-top:.75rem;">Método de pago</p>

                {{-- Fila: método | monto | botón agregar --}}
                <div class="cobrar-pago-row">
                    <select wire:model.live="metodoPagoId" class="pdv-form-input cobrar-pago-metodo">
                        @foreach($metodos as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                    <input
                        type="number"
                        wire:model.live.debounce.300ms="montoPagoInput"
                        class="pdv-form-input cobrar-pago-monto"
                        min="0"
                        step="0.01"
                        placeholder="{{ number_format($totalDesc, 2) }}"
                    />
                    <button wire:click="agregarPago" class="cobrar-pago-agregar" title="Agregar pago">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:.95rem;height:.95rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Agregar
                    </button>
                </div>

                @if(empty($pagosAgregados))
                <p class="cobrar-pago-hint">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:.8rem;height:.8rem;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    Haz clic en <strong>Agregar</strong> para registrar el pago antes de confirmar
                </p>
                @endif

                @if($requiereRef)
                <div style="margin-top:.5rem;">
                    <input type="text" wire:model="pagoReferencia" class="pdv-form-input" placeholder="Referencia / N° operación" />
                </div>
                @endif

                {{-- Pagos agregados DEBAJO del input --}}
                @if(! empty($pagosAgregados))
                <div class="cobrar-pagos-lista">
                    @foreach($pagosAgregados as $i => $pago)
                    <div class="cobrar-pago-item">
                        <span class="cobrar-pago-item__nombre">{{ $pago['nombre'] }}</span>
                        <span class="cobrar-pago-item__monto">S/ {{ number_format($pago['monto'], 2) }}</span>
                        <button wire:click="eliminarPago({{ $i }})" class="cobrar-pago-del" title="Eliminar">×</button>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Resumen de pago --}}
                <div class="cobrar-resumen">
                    @if(! empty($pagosAgregados))
                    <div class="cobrar-resumen__fila">
                        <span>Pagado</span>
                        <span>S/ {{ number_format($totalPagado, 2) }}</span>
                    </div>
                    @endif

                    <div class="cobrar-totales-row">
                        @if($vuelto > 0.005)
                        <div class="cobrar-vuelto-box">
                            <div class="cobrar-vuelto-box__label">Vuelto</div>
                            <div class="cobrar-vuelto-box__valor">S/ {{ number_format($vuelto, 2) }}</div>
                        </div>
                        @endif
                        <div class="cobrar-total-box {{ $vuelto <= 0.005 ? 'cobrar-total-box--solo' : '' }}">
                            <div class="cobrar-total-box__label">Total a cobrar</div>
                            <div class="cobrar-total-box__valor">S/ {{ number_format($totalDesc, 2) }}</div>
                        </div>
                    </div>
                </div>

            </div>{{-- /cobrar-card --}}

            {{-- Botón confirmar --}}
            <button
                class="pdv-btn-cobrar"
                wire:click="procesarCobro"
                wire:loading.attr="disabled"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1.1rem;height:1.1rem;flex-shrink:0;" aria-hidden="true"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/></svg>
                <span wire:loading.remove wire:target="procesarCobro">Confirmar cobro — S/ {{ number_format($totalDesc, 2) }}</span>
                <span wire:loading wire:target="procesarCobro">Procesando…</span>
            </button>

        </div>{{-- /cobrar-right --}}
    </div>{{-- /cobrar-wrap --}}
</div>{{-- /cobrar-root --}}

<style>
/* ── Quitar espacio que agrega Filament al contenedor de página ─────── */
.fi-page-header-main-ctn { padding-block: 1rem !important; gap: 0 !important; }
.fi-page-content { gap: 0 !important; }

/* ── Layout ──────────────────────────────────────────────────────────── */
.cobrar-root { display: flex; flex-direction: column; gap: .75rem; }
.cobrar-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; align-items: start; }
@media (max-width: 900px) { .cobrar-wrap { grid-template-columns: 1fr; } }
.cobrar-left, .cobrar-right { display: flex; flex-direction: column; gap: .5rem; }

/* ── Tarjeta ─────────────────────────────────────────────────────────── */
.cobrar-card {
    background: var(--pdv-surface, #fff);
    border: 1px solid var(--pdv-border, #e2e8f0);
    border-radius: .625rem;
    padding: .875rem;
}

/* ── Título de sección (dentro de tarjeta) ───────────────────────────── */
.cobrar-sec-title {
    font-size: .75rem;
    font-weight: 700;
    color: var(--pdv-text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: .5rem;
}

/* ── Separador interno ───────────────────────────────────────────────── */
.cobrar-divider {
    height: 1px;
    background: var(--pdv-border, #e2e8f0);
    margin: .5rem -.875rem;
}

/* ── Detalle del pedido ──────────────────────────────────────────────── */
.cobrar-items { display: flex; flex-direction: column; gap: .2rem; margin-bottom: .5rem; }
.cobrar-item { display: flex; align-items: center; gap: .4rem; font-size: .83rem; color: var(--pdv-text, #1e293b); }
.cobrar-item__cant { color: var(--pdv-text-muted, #64748b); min-width: 1.5rem; }
.cobrar-item__nombre { flex: 1; }
.cobrar-item__total { font-weight: 600; }

.cobrar-total-row {
    display: flex; justify-content: space-between;
    font-size: .83rem; padding: .28rem 0;
    border-top: 1px solid var(--pdv-border, #e2e8f0);
    color: var(--pdv-text-muted, #64748b);
}
.cobrar-total-row--descuento { color: #dc2626; }
.dark .cobrar-total-row--descuento { color: #f87171; }
.cobrar-total-row--final {
    font-weight: 700; font-size: .95rem;
    color: var(--pdv-text, #0f172a);
    border-top: 2px solid var(--pdv-border, #cbd5e1);
}

/* ── Descuento inline ────────────────────────────────────────────────── */
.cobrar-desc-row {
    display: flex; align-items: center; gap: .5rem;
    padding: .4rem 0;
    border-top: 1px solid var(--pdv-border, #e2e8f0);
}
.cobrar-desc-label {
    font-size: .78rem; color: var(--pdv-text-muted, #64748b); white-space: nowrap; flex-shrink: 0;
}
.cobrar-desc-input {
    flex: 1; min-width: 0;
    padding: .3rem .5rem;
    border: 1px solid var(--pdv-border, #e2e8f0);
    border-radius: .375rem;
    font-size: .83rem;
    background: var(--pdv-bg, #fff);
    color: var(--pdv-text, #0f172a);
    box-sizing: border-box;
    max-width: 110px;
}
.cobrar-desc-input:focus { outline: none; border-color: var(--pdv-primary, #6366f1); }

/* ── Fila de pago: método | monto | agregar ──────────────────────────── */
.cobrar-pago-row {
    display: flex; gap: .4rem; align-items: center;
    margin-bottom: 0;
}
.cobrar-pago-metodo { flex: 2; min-width: 0; }
.cobrar-pago-monto  { flex: 1; min-width: 0; width: 80px; }
.cobrar-pago-agregar {
    display: inline-flex; align-items: center; gap: .3rem;
    flex-shrink: 0;
    padding: .45rem .7rem;
    background: #2563eb; color: #fff;
    border: none; border-radius: .4rem;
    font-size: .8rem; font-weight: 600; cursor: pointer;
    white-space: nowrap; transition: background .15s;
}
.cobrar-pago-agregar:hover { background: #1d4ed8; }

/* ── Lista de pagos agregados ────────────────────────────────────────── */
.cobrar-pagos-lista {
    margin-top: .5rem;
    border: 1px solid var(--pdv-border, #e2e8f0);
    border-radius: .4rem;
    overflow: hidden;
}
.cobrar-pago-item {
    display: flex; align-items: center; gap: .5rem;
    padding: .3rem .6rem;
    border-bottom: 1px solid var(--pdv-border, #e2e8f0);
    font-size: .83rem;
}
.cobrar-pago-item:last-child { border-bottom: none; }
.cobrar-pago-item__nombre { flex: 1; color: var(--pdv-text, #1e293b); }
.cobrar-pago-item__monto  { font-weight: 600; color: var(--pdv-text, #1e293b); }
.cobrar-pago-del {
    background: none; border: none; cursor: pointer;
    color: #ef4444; font-size: 1.1rem; padding: 0 .2rem; line-height: 1;
}
.cobrar-pago-del:hover { color: #dc2626; }

/* ── Resumen de pago ─────────────────────────────────────────────────── */
.cobrar-resumen {
    background: var(--pdv-bg, #f8fafc);
    border-radius: .4rem;
    padding: .6rem .75rem;
    margin-top: .5rem;
}
.cobrar-resumen__fila {
    display: flex; justify-content: space-between;
    font-size: .83rem; padding: .15rem 0;
    color: var(--pdv-text-muted, #64748b);
}
.cobrar-resumen__fila span:last-child { font-weight: 600; color: var(--pdv-text, #1e293b); }

/* ── Fila total + vuelto ─────────────────────────────────────────────── */
.cobrar-totales-row {
    display: flex; gap: .5rem; align-items: stretch;
    margin-top: .5rem;
}

/* ── Total a cobrar (verde, derecha) ─────────────────────────────────── */
.cobrar-total-box {
    flex: 1;
    background: #f0fdf4;
    border: 2px solid #16a34a;
    border-radius: .5rem;
    padding: .65rem .75rem;
    text-align: center;
}
.cobrar-total-box--solo { flex: 1; }
.dark .cobrar-total-box { background: #14532d40; border-color: #22c55e; }
.cobrar-total-box__label {
    font-size: .65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: #16a34a; margin-bottom: .1rem;
}
.dark .cobrar-total-box__label { color: #4ade80; }
.cobrar-total-box__valor {
    font-size: 1.75rem; font-weight: 800; line-height: 1.15;
    color: #15803d;
    font-variant-numeric: tabular-nums;
    letter-spacing: -.02em;
}
.dark .cobrar-total-box__valor { color: #4ade80; }

/* ── Vuelto (naranja, izquierda) ─────────────────────────────────────── */
.cobrar-vuelto-box {
    flex: 1;
    background: #fff7ed;
    border: 2px solid #f97316;
    border-radius: .5rem;
    padding: .65rem .75rem;
    text-align: center;
}
.dark .cobrar-vuelto-box { background: #7c2d1240; border-color: #fb923c; }
.cobrar-vuelto-box__label {
    font-size: .65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: #f97316; margin-bottom: .1rem;
}
.dark .cobrar-vuelto-box__label { color: #fb923c; }
.cobrar-vuelto-box__valor {
    font-size: 1.75rem; font-weight: 800; line-height: 1.15;
    color: #c2410c;
    font-variant-numeric: tabular-nums;
    letter-spacing: -.02em;
}
.dark .cobrar-vuelto-box__valor { color: #fb923c; }

/* ── Hint agregar pago ───────────────────────────────────────────────── */
.cobrar-pago-hint {
    display: flex; align-items: center; gap: .3rem;
    font-size: .75rem; color: #92400e;
    background: #fef3c7; border: 1px solid #fcd34d;
    border-radius: .35rem; padding: .35rem .6rem;
    margin-top: .4rem;
}
.dark .cobrar-pago-hint { color: #fde68a; background: #78350f40; border-color: #92400e; }

/* ── Botón volver ────────────────────────────────────────────────────── */
.mm-btn {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .4rem .75rem; border-radius: .375rem;
    border: 1px solid var(--pdv-border, #e2e8f0);
    background: var(--pdv-surface, #fff);
    color: var(--pdv-text, #334155);
    font-size: .78rem; font-weight: 600; cursor: pointer;
    white-space: nowrap; transition: opacity .15s;
}
.mm-btn:hover { opacity: .85; }

/* ── Botón confirmar cobro ───────────────────────────────────────────── */
.pdv-btn-cobrar {
    width: 100%; display: flex; align-items: center; justify-content: center; gap: .5rem;
    background: #16a34a; color: #fff; border: none;
    padding: .8rem 1rem; border-radius: .5rem;
    font-size: 1rem; font-weight: 700; cursor: pointer;
    transition: background .15s;
}
.pdv-btn-cobrar:hover { background: #15803d; }
.pdv-btn-cobrar:disabled { opacity: .55; cursor: not-allowed; }

/* ── Botones modal cliente ───────────────────────────────────────────── */
.pdv-btn-primario {
    background: #2563eb; color: #fff; border: none;
    padding: .5rem 1rem; border-radius: .5rem;
    font-weight: 600; cursor: pointer; font-size: .875rem;
}
.pdv-btn-primario:hover { opacity: .88; }
.pdv-btn-secundario {
    background: var(--pdv-surface, #f1f5f9);
    color: var(--pdv-text, #334155);
    border: 1px solid var(--pdv-border, #e2e8f0);
    padding: .5rem 1rem; border-radius: .5rem;
    font-weight: 600; cursor: pointer; font-size: .875rem;
}
.pdv-btn-secundario:hover { opacity: .85; }

/* ── Formulario ──────────────────────────────────────────────────────── */
.pdv-form-group { margin-bottom: .6rem; }
.pdv-form-label { display: block; font-size: .78rem; font-weight: 600; color: var(--pdv-text-muted, #64748b); margin-bottom: .25rem; }
.pdv-form-input {
    width: 100%; padding: .4rem .65rem;
    border: 1px solid var(--pdv-border, #e2e8f0);
    border-radius: .4rem; font-size: .85rem;
    background: var(--pdv-bg, #fff);
    color: var(--pdv-text, #0f172a);
    box-sizing: border-box;
}
.pdv-form-input:focus { outline: none; border-color: var(--pdv-primary, #6366f1); }
.pdv-form-error { font-size: .75rem; color: #dc2626; margin-top: .2rem; }
.dark .pdv-form-error { color: #f87171; }

/* ── Cliente seleccionado ────────────────────────────────────────────── */
.pdv-cliente__seleccionado { font-size: .8rem; color: #166534; display:flex; align-items:center; gap:.35rem; margin-top:.35rem; }
.dark .pdv-cliente__seleccionado { color: #4ade80; }
</style>

{{-- Navegar al mapa en el mismo round-trip que cierra el modal (sin segundo viaje al servidor) --}}
<script>
(function () {
    var mesasUrl = @js(\App\Filament\Pdv\Pages\MapaMesasPage::getUrl(tenant: \Filament\Facades\Filament::getTenant()));
    window.addEventListener('modal-impresion-cerrada', function () {
        if (window.Livewire && typeof Livewire.navigate === 'function') {
            Livewire.navigate(mesasUrl);
        } else {
            window.location.href = mesasUrl;
        }
    }, { once: true });
})();
</script>

</x-filament-panels::page>
