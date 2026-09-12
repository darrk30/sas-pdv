<x-filament-panels::page>

@php
    $comanda  = $this->getComanda();
    $mesa     = $comanda->mesa;
    $piso     = $mesa?->piso;
    $detalles = $comanda->detalles;
    $minutos  = (int) now()->diffInMinutes($comanda->created_at);
    $tiempo   = $minutos >= 60
        ? intdiv($minutos, 60) . 'h ' . ($minutos % 60) . 'm'
        : $minutos . 'm';
    $categorias  = $this->getCategorias();
    $productos   = $this->getProductos();
    $series      = $this->getSeries();
    $metodosPago = $this->getMetodosPago();

    $metodoPagoSeleccionado = $metodosPago->firstWhere('id', $this->cobroMetodoPagoId);
    $requiereReferencia = $metodoPagoSeleccionado?->requiere_referencia ?? false;
@endphp

<div class="ec-root">

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="ec-header">
        <div class="ec-header-left">
            <button wire:click="volverAlMapa" class="ec-back-btn" title="Volver al mapa">
                <x-heroicon-m-arrow-left class="ec-back-icon" />
            </button>
            <div>
                <div class="ec-header-mesa">
                    <x-heroicon-m-table-cells class="ec-header-icon" />
                    {{ $mesa?->nombre ?? 'Sin mesa' }}
                    @if($piso)
                        <span class="ec-header-piso">· {{ $piso->nombre }}</span>
                    @endif
                </div>
                <div class="ec-header-meta">
                    <span class="ec-header-tiempo">
                        <x-heroicon-m-clock class="ec-meta-icon" /> {{ $tiempo }}
                    </span>
                    <span class="ec-header-sep">·</span>
                    <span class="ec-header-orden">{{ $comanda->codigo }}</span>
                </div>
            </div>
        </div>
        <div class="ec-header-right">
            @if($detalles->count() > 0)
                <div class="ec-total-badge">
                    S/ {{ number_format($comanda->total, 2) }}
                </div>
            @endif
            {{-- Mobile: toggle panel --}}
            <button wire:click="toggleOrden" class="ec-toggle-btn">
                <x-heroicon-m-clipboard-document-list class="ec-toggle-icon" />
                @if($detalles->count() > 0)
                    <span class="ec-toggle-count">{{ $detalles->count() }}</span>
                @endif
            </button>
        </div>
    </div>

    {{-- ── Layout principal ────────────────────────────────────────────── --}}
    <div class="ec-layout">

        {{-- ══ Panel izquierdo: Productos ══════════════════════════════════ --}}
        <div class="ec-productos">

            {{-- Buscador --}}
            <div class="ec-search-wrap">
                <x-heroicon-m-magnifying-glass class="ec-search-icon" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar producto…"
                    class="ec-search-input"
                    autocomplete="off"
                />
                @if($busqueda)
                    <button wire:click="$set('busqueda','')" class="ec-search-clear">
                        <x-heroicon-m-x-mark class="ec-search-clear-icon" />
                    </button>
                @endif
            </div>

            {{-- Tabs de categorías --}}
            @if($categorias->isNotEmpty())
                <div class="ec-cats">
                    <button
                        wire:click="$set('categoriaId', null)"
                        class="ec-cat {{ $categoriaId === null ? 'ec-cat--active' : '' }}"
                    >Todas</button>
                    @foreach($categorias as $cat)
                        <button
                            wire:click="$set('categoriaId', {{ $cat->id }})"
                            class="ec-cat {{ $categoriaId === $cat->id ? 'ec-cat--active' : '' }}"
                        >{{ $cat->nombre }}</button>
                    @endforeach
                </div>
            @endif

            {{-- Grid de productos --}}
            @if($productos->isEmpty())
                <div class="ec-no-products">
                    <x-heroicon-o-face-frown class="ec-no-icon" />
                    <span>Sin resultados</span>
                </div>
            @else
                <div class="ec-prod-grid">
                    @foreach($productos as $prod)
                        <button
                            wire:click="agregarProducto({{ $prod->id }})"
                            wire:loading.attr="disabled"
                            wire:target="agregarProducto({{ $prod->id }})"
                            class="ec-prod-card"
                        >
                            @if($prod->logo)
                                <img
                                    src="{{ asset('storage/' . $prod->logo) }}"
                                    alt="{{ $prod->nombre }}"
                                    class="ec-prod-img"
                                />
                            @else
                                <div class="ec-prod-img ec-prod-placeholder">
                                    <x-heroicon-o-cube class="ec-prod-ph-icon" />
                                </div>
                            @endif
                            <div class="ec-prod-info">
                                <span class="ec-prod-nombre">{{ $prod->nombre }}</span>
                                <span class="ec-prod-precio">S/ {{ number_format($prod->precio_venta, 2) }}</span>
                            </div>
                            <div class="ec-prod-add" wire:loading.class="ec-prod-add--loading" wire:target="agregarProducto({{ $prod->id }})">
                                <x-heroicon-m-plus class="ec-prod-add-icon" />
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══ Panel derecho: Orden ════════════════════════════════════════ --}}
        <div class="ec-orden {{ $verOrden ? 'ec-orden--visible' : 'ec-orden--hidden' }}">

            {{-- Título del panel --}}
            <div class="ec-orden-header">
                <span class="ec-orden-title">Comanda</span>
                <span class="ec-orden-count">{{ $detalles->count() }} ítem{{ $detalles->count() !== 1 ? 's' : '' }}</span>
            </div>

            {{-- Lista de ítems --}}
            @if($detalles->isEmpty())
                <div class="ec-orden-empty">
                    <x-heroicon-o-clipboard-document-list class="ec-orden-empty-icon" />
                    <span>Agrega productos de la izquierda</span>
                </div>
            @else
                <div class="ec-items">
                    @foreach($detalles as $det)
                        @php
                            $nota = $this->notasTemp[$det->id] ?? $det->notas_item ?? '';
                        @endphp
                        <div class="ec-item {{ $det->enviado_cocina ? 'ec-item--enviado' : '' }}">

                            {{-- Nombre + enviado badge --}}
                            <div class="ec-item-top">
                                <span class="ec-item-nombre">{{ $det->descripcion }}</span>
                                @if($det->enviado_cocina)
                                    <span class="ec-enviado-badge">✓ enviado</span>
                                @endif
                            </div>

                            {{-- Controles de cantidad + precio + eliminar --}}
                            <div class="ec-item-controls">
                                <div class="ec-qty">
                                    <button wire:click="decrementar({{ $det->id }})" class="ec-qty-btn">
                                        <x-heroicon-m-minus class="ec-qty-icon" />
                                    </button>
                                    <span class="ec-qty-val">{{ (int) $det->cantidad }}</span>
                                    <button wire:click="incrementar({{ $det->id }})" class="ec-qty-btn">
                                        <x-heroicon-m-plus class="ec-qty-icon" />
                                    </button>
                                </div>
                                <span class="ec-item-precio">S/ {{ number_format($det->total, 2) }}</span>
                                <button wire:click="eliminarItem({{ $det->id }})" wire:confirm="¿Eliminar este ítem?" class="ec-item-del">
                                    <x-heroicon-m-trash class="ec-del-icon" />
                                </button>
                            </div>

                            {{-- Nota del ítem --}}
                            <div class="ec-nota-wrap"
                                x-data="{ open: {{ $nota ? 'true' : 'false' }} }">
                                <button
                                    type="button"
                                    x-on:click="open = !open"
                                    class="ec-nota-toggle"
                                    :class="open ? 'ec-nota-toggle--open' : ''"
                                >
                                    <x-heroicon-m-pencil-square class="ec-nota-toggle-icon" />
                                    {{ $nota ?: 'Añadir nota' }}
                                </button>
                                <div x-show="open" x-transition class="ec-nota-input-wrap">
                                    <input
                                        type="text"
                                        wire:model="notasTemp.{{ $det->id }}"
                                        wire:blur="guardarNota({{ $det->id }})"
                                        placeholder="Ej: sin cebolla, término medio…"
                                        class="ec-nota-input"
                                        maxlength="120"
                                    />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Totales --}}
                <div class="ec-totales">
                    <div class="ec-total-row">
                        <span>Subtotal</span>
                        <span>S/ {{ number_format($comanda->subtotal, 2) }}</span>
                    </div>
                    <div class="ec-total-row">
                        <span>IGV ({{ $comanda->igv > 0 ? number_format(($comanda->igv / max($comanda->subtotal, 0.01)) * 100, 0) : 18 }}%)</span>
                        <span>S/ {{ number_format($comanda->igv, 2) }}</span>
                    </div>
                    <div class="ec-total-row ec-total-final">
                        <span>Total</span>
                        <span>S/ {{ number_format($comanda->total, 2) }}</span>
                    </div>
                </div>

                {{-- Acciones principales --}}
                <div class="ec-acciones">
                    @if($this->hayItemsSinEnviar())
                        <button
                            wire:click="enviarACocina"
                            wire:loading.attr="disabled"
                            wire:target="enviarACocina"
                            class="ec-btn ec-btn--cocina"
                        >
                            <x-heroicon-m-bell-alert class="ec-btn-icon" />
                            <span wire:loading.remove wire:target="enviarACocina">Enviar a cocina</span>
                            <span wire:loading wire:target="enviarACocina">Enviando…</span>
                        </button>
                    @endif
                    <button
                        wire:click="imprimirPreCuenta"
                        wire:loading.attr="disabled"
                        wire:target="imprimirPreCuenta"
                        class="ec-btn ec-btn--precuenta"
                    >
                        <x-heroicon-m-printer class="ec-btn-icon" />
                        <span wire:loading.remove wire:target="imprimirPreCuenta">Pre-cuenta</span>
                        <span wire:loading wire:target="imprimirPreCuenta">Imprimiendo…</span>
                    </button>
                    <button
                        wire:click="cobrarMesa"
                        class="ec-btn ec-btn--cobrar"
                    >
                        <x-heroicon-m-banknotes class="ec-btn-icon" />
                        Cobrar mesa
                    </button>
                </div>
            @endif
        </div>

    </div>

</div>

{{-- ── Modal de cobro ──────────────────────────────────────────────────── --}}
@if($this->modalCobro)
<div class="ec-modal-overlay" wire:click.self="cerrarModalCobro">
    <div class="ec-modal" wire:keydown.escape.window="cerrarModalCobro">

        {{-- Header --}}
        <div class="ec-modal-header">
            <div class="ec-modal-title">
                <x-heroicon-m-banknotes class="ec-modal-title-icon" />
                Cobrar Mesa
                @if($mesa)
                    <span class="ec-modal-subtitle">· {{ $mesa->nombre }}</span>
                @endif
            </div>
            <button wire:click="cerrarModalCobro" class="ec-modal-close">
                <x-heroicon-m-x-mark class="ec-modal-close-icon" />
            </button>
        </div>

        {{-- Resumen --}}
        <div class="ec-modal-total-banner">
            <span>Total a cobrar</span>
            <span class="ec-modal-total-amount">S/ {{ number_format($comanda->total, 2) }}</span>
        </div>

        {{-- Comprobante --}}
        <div class="ec-modal-field">
            <label class="ec-modal-label">Comprobante</label>
            <div class="ec-modal-radio-group">
                @foreach($series as $ser)
                    <label class="ec-modal-radio-opt {{ $this->cobroSerieId === $ser->id ? 'ec-modal-radio-opt--active' : '' }}">
                        <input
                            type="radio"
                            wire:model.live="cobroSerieId"
                            value="{{ $ser->id }}"
                            class="ec-modal-radio-input"
                        />
                        {{ $ser->tipo->getLabel() }} · {{ $ser->serie }}
                    </label>
                @endforeach
                @if($series->isEmpty())
                    <p class="ec-modal-warn">No hay series activas. Configura una serie en el panel.</p>
                @endif
            </div>
            @error('cobroSerieId') <p class="ec-modal-error">{{ $message }}</p> @enderror
        </div>

        {{-- Método de pago --}}
        <div class="ec-modal-field">
            <label class="ec-modal-label">Método de pago</label>
            <select wire:model.live="cobroMetodoPagoId" class="ec-modal-select">
                <option value="">— Selecciona —</option>
                @foreach($metodosPago as $mp)
                    <option value="{{ $mp->id }}">{{ $mp->nombre }}</option>
                @endforeach
            </select>
            @error('cobroMetodoPagoId') <p class="ec-modal-error">{{ $message }}</p> @enderror
        </div>

        {{-- Referencia (si requiere) --}}
        @if($requiereReferencia)
        <div class="ec-modal-field">
            <label class="ec-modal-label">Referencia / Nro. operación</label>
            <input
                type="text"
                wire:model="cobroReferencia"
                class="ec-modal-input"
                placeholder="Ej: 00123456"
                maxlength="60"
            />
        </div>
        @endif

        {{-- Monto recibido --}}
        <div class="ec-modal-field">
            <label class="ec-modal-label">Monto recibido (S/)</label>
            <input
                type="number"
                wire:model="cobroMonto"
                step="0.01"
                min="{{ number_format($comanda->total, 2, '.', '') }}"
                class="ec-modal-input ec-modal-input--monto"
                placeholder="{{ number_format($comanda->total, 2) }}"
            />
            @error('cobroMonto') <p class="ec-modal-error">{{ $message }}</p> @enderror
            @if((float)$this->cobroMonto > (float)$comanda->total + 0.001)
                <p class="ec-modal-vuelto">
                    Vuelto: <strong>S/ {{ number_format((float)$this->cobroMonto - (float)$comanda->total, 2) }}</strong>
                </p>
            @endif
        </div>

        {{-- Acciones --}}
        <div class="ec-modal-footer">
            <button wire:click="cerrarModalCobro" class="ec-modal-btn ec-modal-btn--cancel">
                Cancelar
            </button>
            <button
                wire:click="procesarCobro"
                wire:loading.attr="disabled"
                wire:target="procesarCobro"
                class="ec-modal-btn ec-modal-btn--confirm"
            >
                <span wire:loading.remove wire:target="procesarCobro">
                    <x-heroicon-m-check class="ec-modal-btn-icon" /> Confirmar cobro
                </span>
                <span wire:loading wire:target="procesarCobro">Procesando…</span>
            </button>
        </div>

    </div>
</div>
@endif

<link rel="stylesheet" href="{{ asset('css/edit-comanda.css') }}?v={{ filemtime(public_path('css/edit-comanda.css')) }}">

</x-filament-panels::page>
