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

<style>
/* ── Reset / Root ────────────────────────────────────────────────────────── */
.ec-root { display: flex; flex-direction: column; gap: .75rem; height: calc(100vh - 7rem); overflow: hidden; }

/* ── Header ─────────────────────────────────────────────────────────────── */
.ec-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #46449e;
    border-radius: .875rem;
    padding: .75rem 1rem;
    color: #fff;
    flex-shrink: 0;
}
.ec-header-left  { display: flex; align-items: center; gap: .75rem; }
.ec-header-right { display: flex; align-items: center; gap: .5rem; }
.ec-back-btn { background: rgba(255,255,255,.15); border: none; border-radius: .5rem; padding: .35rem; cursor: pointer; display: flex; color: #fff; }
.ec-back-icon { width: 1.1rem; height: 1.1rem; }
.ec-header-mesa { font-size: .95rem; font-weight: 700; display: flex; align-items: center; gap: .35rem; }
.ec-header-icon { width: .95rem; height: .95rem; opacity: .8; }
.ec-header-piso { font-weight: 400; opacity: .75; }
.ec-header-meta { display: flex; align-items: center; gap: .4rem; font-size: .75rem; opacity: .8; margin-top: .1rem; }
.ec-meta-icon { width: .75rem; height: .75rem; }
.ec-header-sep { opacity: .5; }
.ec-total-badge { background: rgba(255,255,255,.2); border-radius: .5rem; padding: .25rem .65rem; font-size: .9rem; font-weight: 700; }
.ec-toggle-btn { position: relative; background: rgba(255,255,255,.15); border: none; border-radius: .5rem; padding: .35rem; cursor: pointer; display: flex; color: #fff; }
.ec-toggle-icon { width: 1.1rem; height: 1.1rem; }
.ec-toggle-count { position: absolute; top: -.3rem; right: -.3rem; background: #ef4444; color: #fff; font-size: .6rem; font-weight: 700; border-radius: 999px; width: 1rem; height: 1rem; display: flex; align-items: center; justify-content: center; }

/* ── Layout ─────────────────────────────────────────────────────────────── */
.ec-layout { display: flex; gap: 1rem; flex: 1; min-height: 0; }

/* ══ Panel productos ════════════════════════════════════════════════════════ */
.ec-productos { flex: 1; display: flex; flex-direction: column; gap: .65rem; min-width: 0; overflow: hidden; }

/* Buscador */
.ec-search-wrap { position: relative; }
.ec-search-icon { position: absolute; left: .65rem; top: 50%; transform: translateY(-50%); width: .9rem; height: .9rem; color: #9ca3af; pointer-events: none; }
.ec-search-input { width: 100%; padding: .5rem .5rem .5rem 2rem; border: 1.5px solid var(--ec-border, #d1d5db); border-radius: .6rem; font-size: .85rem; background: var(--ec-surface, #fff); color: var(--ec-text, #111827); outline: none; transition: border-color .15s; }
.ec-search-input:focus { border-color: #46449e; }
.ec-search-clear { position: absolute; right: .5rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: .15rem; color: #9ca3af; display: flex; }
.ec-search-clear-icon { width: .85rem; height: .85rem; }

/* Categorías */
.ec-cats { display: flex; gap: .35rem; flex-wrap: nowrap; overflow-x: auto; padding-bottom: .1rem; scrollbar-width: none; }
.ec-cats::-webkit-scrollbar { display: none; }
.ec-cat { flex-shrink: 0; padding: .3rem .75rem; border-radius: 999px; border: 1.5px solid var(--ec-border, #d1d5db); background: transparent; font-size: .78rem; font-weight: 500; cursor: pointer; color: var(--ec-muted, #6b7280); transition: all .12s; white-space: nowrap; }
.ec-cat:hover { border-color: #46449e; color: #46449e; }
.ec-cat--active { background: #46449e; border-color: #46449e; color: #fff; }

/* Grid productos */
.ec-prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .6rem; overflow-y: auto; flex: 1; padding-right: .15rem; }
.ec-prod-card { display: flex; flex-direction: column; border: 1.5px solid var(--ec-border, #e5e7eb); border-radius: .75rem; background: var(--ec-surface, #fff); cursor: pointer; overflow: hidden; transition: box-shadow .15s, border-color .15s; text-align: left; padding: 0; }
.ec-prod-card:hover { border-color: #46449e; box-shadow: 0 2px 10px rgba(70,68,158,.15); }
.ec-prod-card:active { transform: scale(.97); }
.ec-prod-img { width: 100%; height: 80px; object-fit: cover; display: block; }
.ec-prod-placeholder { display: flex; align-items: center; justify-content: center; background: #f3f4f6; }
.ec-prod-ph-icon { width: 1.75rem; height: 1.75rem; color: #9ca3af; }
.ec-prod-info { padding: .45rem .6rem .3rem; flex: 1; }
.ec-prod-nombre { display: block; font-size: .78rem; font-weight: 600; color: var(--ec-text, #111827); line-height: 1.3; }
.ec-prod-precio { display: block; font-size: .8rem; font-weight: 700; color: #46449e; margin-top: .15rem; }
.ec-prod-add { display: flex; align-items: center; justify-content: center; padding: .35rem; background: #46449e; }
.ec-prod-add-icon { width: .9rem; height: .9rem; color: #fff; }
.ec-prod-add--loading { opacity: .5; }

/* Sin resultados */
.ec-no-products { display: flex; flex-direction: column; align-items: center; gap: .4rem; padding: 2.5rem 1rem; color: #9ca3af; font-size: .85rem; }
.ec-no-icon { width: 2rem; height: 2rem; }

/* ══ Panel orden ════════════════════════════════════════════════════════════ */
.ec-orden { width: 280px; flex-shrink: 0; display: flex; flex-direction: column; gap: 0; border: 1.5px solid var(--ec-border, #e5e7eb); border-radius: .875rem; background: var(--ec-surface, #fff); overflow: hidden; }
.ec-orden-header { display: flex; align-items: center; justify-content: space-between; padding: .65rem .9rem; border-bottom: 1px solid var(--ec-border, #e5e7eb); flex-shrink: 0; }
.ec-orden-title { font-size: .85rem; font-weight: 700; color: var(--ec-text, #111827); }
.ec-orden-count { font-size: .75rem; color: #6b7280; }

/* Orden vacía */
.ec-orden-empty { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 2rem 1rem; color: #9ca3af; font-size: .8rem; text-align: center; flex: 1; }
.ec-orden-empty-icon { width: 2.25rem; height: 2.25rem; }

/* Ítems */
.ec-items { flex: 1; overflow-y: auto; padding: .4rem .5rem; display: flex; flex-direction: column; gap: .35rem; }
.ec-item { padding: .55rem .6rem; border-radius: .6rem; background: var(--ec-item-bg, #f9fafb); border: 1px solid var(--ec-border, #e5e7eb); display: flex; flex-direction: column; gap: .35rem; transition: opacity .15s; }
.ec-item--enviado { opacity: .65; }
.ec-item-top { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
.ec-item-nombre { font-size: .8rem; font-weight: 600; color: var(--ec-text, #111827); line-height: 1.3; flex: 1; }
.ec-enviado-badge { font-size: .62rem; font-weight: 700; color: #15803d; background: #dcfce7; border-radius: 999px; padding: .1rem .4rem; flex-shrink: 0; }

/* Controles cantidad */
.ec-item-controls { display: flex; align-items: center; gap: .4rem; }
.ec-qty { display: flex; align-items: center; gap: .25rem; }
.ec-qty-btn { width: 1.4rem; height: 1.4rem; border-radius: .35rem; border: 1.5px solid var(--ec-border, #d1d5db); background: var(--ec-surface, #fff); cursor: pointer; display: flex; align-items: center; justify-content: center; color: #374151; transition: all .1s; }
.ec-qty-btn:hover { border-color: #46449e; color: #46449e; }
.ec-qty-icon { width: .7rem; height: .7rem; }
.ec-qty-val { font-size: .82rem; font-weight: 700; min-width: 1.2rem; text-align: center; }
.ec-item-precio { margin-left: auto; font-size: .82rem; font-weight: 700; color: var(--ec-text, #111827); }
.ec-item-del { background: none; border: none; cursor: pointer; color: #ef4444; display: flex; padding: .2rem; border-radius: .35rem; }
.ec-item-del:hover { background: #fee2e2; }
.ec-del-icon { width: .85rem; height: .85rem; }

/* Nota */
.ec-nota-toggle { display: flex; align-items: center; gap: .3rem; background: none; border: none; cursor: pointer; font-size: .72rem; color: #9ca3af; padding: 0; text-align: left; }
.ec-nota-toggle:hover { color: #46449e; }
.ec-nota-toggle--open { color: #46449e; }
.ec-nota-toggle-icon { width: .7rem; height: .7rem; flex-shrink: 0; }
.ec-nota-input-wrap { margin-top: .25rem; }
.ec-nota-input { width: 100%; padding: .3rem .45rem; border: 1px solid var(--ec-border, #d1d5db); border-radius: .4rem; font-size: .75rem; background: var(--ec-surface, #fff); color: var(--ec-text, #111827); outline: none; }
.ec-nota-input:focus { border-color: #46449e; }

/* Totales */
.ec-totales { padding: .5rem .9rem; border-top: 1px solid var(--ec-border, #e5e7eb); flex-shrink: 0; display: flex; flex-direction: column; gap: .2rem; }
.ec-total-row { display: flex; justify-content: space-between; font-size: .78rem; color: #6b7280; }
.ec-total-final { font-size: .9rem; font-weight: 700; color: var(--ec-text, #111827); margin-top: .2rem; }

/* Acciones */
.ec-acciones { padding: .65rem .75rem; border-top: 1px solid var(--ec-border, #e5e7eb); display: flex; flex-direction: column; gap: .4rem; flex-shrink: 0; }
.ec-btn { display: flex; align-items: center; justify-content: center; gap: .4rem; padding: .55rem; border-radius: .6rem; font-size: .82rem; font-weight: 700; text-decoration: none; transition: filter .15s; cursor: pointer; border: none; }
.ec-btn:hover { filter: brightness(.92); }
.ec-btn-icon { width: .85rem; height: .85rem; flex-shrink: 0; }
.ec-btn--cocina   { background: #f59e0b; color: #fff; }
.ec-btn--precuenta{ background: #6b7280; color: #fff; }
.ec-btn--cobrar   { background: #22c55e; color: #fff; }

/* ── Modal cobro ─────────────────────────────────────────────────────────── */
.ec-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9000; display:flex; align-items:center; justify-content:center; padding:1rem; }
.ec-modal { background:var(--ec-surface,#fff); border-radius:1rem; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.25); display:flex; flex-direction:column; gap:0; overflow:hidden; }
.ec-modal-header { display:flex; align-items:center; justify-content:space-between; padding:.9rem 1.1rem; border-bottom:1px solid var(--ec-border,#e5e7eb); }
.ec-modal-title { display:flex; align-items:center; gap:.4rem; font-size:.95rem; font-weight:700; color:var(--ec-text,#111827); }
.ec-modal-title-icon { width:1.1rem; height:1.1rem; color:#22c55e; }
.ec-modal-subtitle { font-weight:400; color:#6b7280; }
.ec-modal-close { background:none; border:none; cursor:pointer; color:#9ca3af; display:flex; padding:.25rem; border-radius:.35rem; }
.ec-modal-close:hover { background:#f3f4f6; color:#374151; }
.ec-modal-close-icon { width:1.1rem; height:1.1rem; }
.ec-modal-total-banner { background:#f0fdf4; border-bottom:1px solid #bbf7d0; padding:.65rem 1.1rem; display:flex; justify-content:space-between; align-items:center; font-size:.85rem; color:#166534; }
.ec-modal-total-amount { font-size:1.25rem; font-weight:800; }
.ec-modal-field { padding:.75rem 1.1rem 0; display:flex; flex-direction:column; gap:.3rem; }
.ec-modal-label { font-size:.78rem; font-weight:600; color:var(--ec-text,#374151); }
.ec-modal-radio-group { display:flex; flex-wrap:wrap; gap:.4rem; }
.ec-modal-radio-opt { display:flex; align-items:center; gap:.35rem; padding:.3rem .65rem; border:1.5px solid var(--ec-border,#d1d5db); border-radius:999px; font-size:.78rem; font-weight:500; cursor:pointer; color:var(--ec-text,#374151); transition:all .12s; }
.ec-modal-radio-opt--active { background:#46449e; border-color:#46449e; color:#fff; }
.ec-modal-radio-input { position:absolute; opacity:0; pointer-events:none; }
.ec-modal-select { width:100%; padding:.45rem .6rem; border:1.5px solid var(--ec-border,#d1d5db); border-radius:.55rem; font-size:.85rem; background:var(--ec-surface,#fff); color:var(--ec-text,#111827); outline:none; }
.ec-modal-select:focus { border-color:#46449e; }
.ec-modal-input { width:100%; padding:.45rem .6rem; border:1.5px solid var(--ec-border,#d1d5db); border-radius:.55rem; font-size:.85rem; background:var(--ec-surface,#fff); color:var(--ec-text,#111827); outline:none; }
.ec-modal-input:focus { border-color:#46449e; }
.ec-modal-input--monto { font-size:1.15rem; font-weight:700; text-align:right; }
.ec-modal-error { font-size:.72rem; color:#ef4444; margin-top:.1rem; }
.ec-modal-warn  { font-size:.78rem; color:#d97706; }
.ec-modal-vuelto { font-size:.82rem; color:#166534; margin-top:.2rem; }
.ec-modal-footer { display:flex; gap:.6rem; padding:.9rem 1.1rem; border-top:1px solid var(--ec-border,#e5e7eb); margin-top:.75rem; }
.ec-modal-btn { flex:1; padding:.6rem; border-radius:.6rem; font-size:.85rem; font-weight:700; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.35rem; transition:filter .15s; }
.ec-modal-btn--cancel  { background:var(--ec-item-bg,#f3f4f6); color:#374151; }
.ec-modal-btn--cancel:hover  { filter:brightness(.94); }
.ec-modal-btn--confirm { background:#22c55e; color:#fff; }
.ec-modal-btn--confirm:hover { filter:brightness(.92); }
.ec-modal-btn--confirm:disabled { opacity:.6; cursor:not-allowed; filter:none; }
.ec-modal-btn-icon { width:.85rem; height:.85rem; }

/* Mobile responsive */
@media (max-width: 768px) {
    .ec-root { height: auto; overflow: visible; }
    .ec-layout { flex-direction: column; }
    .ec-orden { width: 100%; }
    .ec-orden--hidden { display: none; }
    .ec-prod-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); max-height: 50vh; }
}

/* Dark mode */
@media (prefers-color-scheme: dark) {
    .ec-search-input, .ec-nota-input { --ec-surface: #1f2937; --ec-border: #374151; --ec-text: #f9fafb; }
    .ec-prod-card, .ec-orden, .ec-item { --ec-surface: #1f2937; --ec-border: #374151; --ec-text: #f9fafb; --ec-item-bg: #111827; }
    .ec-cat { --ec-border: #374151; --ec-muted: #9ca3af; }
    .ec-modal { --ec-surface: #1f2937; --ec-border: #374151; --ec-text: #f9fafb; --ec-item-bg: #111827; }
    .ec-modal-total-banner { background:#14532d; border-color:#166534; color:#bbf7d0; }
    .ec-modal-btn--cancel { background:#374151; color:#e5e7eb; }
}
:root[data-theme="dark"] .ec-search-input,
:root[data-theme="dark"] .ec-nota-input { --ec-surface: #1f2937; --ec-border: #374151; --ec-text: #f9fafb; }
:root[data-theme="dark"] .ec-prod-card,
:root[data-theme="dark"] .ec-orden,
:root[data-theme="dark"] .ec-item { --ec-surface: #1f2937; --ec-border: #374151; --ec-text: #f9fafb; --ec-item-bg: #111827; }
:root[data-theme="dark"] .ec-cat { --ec-border: #374151; --ec-muted: #9ca3af; }
:root[data-theme="dark"] .ec-modal { --ec-surface: #1f2937; --ec-border: #374151; --ec-text: #f9fafb; --ec-item-bg: #111827; }
:root[data-theme="dark"] .ec-modal-total-banner { background:#14532d; border-color:#166534; color:#bbf7d0; }
:root[data-theme="dark"] .ec-modal-btn--cancel { background:#374151; color:#e5e7eb; }
</style>

</x-filament-panels::page>
