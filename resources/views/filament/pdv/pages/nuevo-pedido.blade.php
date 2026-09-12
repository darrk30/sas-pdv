<x-filament-panels::page>

<link rel="stylesheet" href="{{ asset('css/punto-de-venta.css') }}?v={{ filemtime(public_path('css/punto-de-venta.css')) }}">

@php
    $mesa    = $mesaId ? \App\Models\Mesa::with('piso')->find($mesaId) : null;
    $total   = $this->getTotal();
    $count   = $this->getItemCount();
    $resumen   = $this->getCarritoResumen();
    $pendiente = $this->getPendienteResumen();
@endphp

{{-- ═══════════════════════════════════════════════════════
     MODAL COMANDA (Alpine.js puro — sin round-trip Livewire)
     Escucha el evento 'imprimir-comanda-browser' del browser.
     ═══════════════════════════════════════════════════════ --}}
<div
    x-data="{
        open: false,
        areas: [],
        activeTab: 0,
        ordenId: 0,
        mesa: '',
        cajero: '',
        rol: '',
        numero: '',
        parcial: false,
        redirectUrl: '',
        ticketBase: '{{ url('/ticket/comanda') }}'
    }"
    @imprimir-comanda-browser.window="
        const raw = $event.detail;
        const d   = (Array.isArray(raw) ? raw[0] : raw) || {};
        ordenId     = d.ordenId  || 0;
        mesa        = d.mesa     || '';
        cajero      = d.cajero   || '';
        rol         = d.rol      || '';
        numero      = d.numero   || '';
        parcial     = !!d.parcial;
        redirectUrl = $wire.comandaRedirectUrl || '';
        try { areas = JSON.parse(d.areasJson || '[]'); } catch(e) { areas = []; }
        if (areas.length > 0) {
            activeTab = 0;
            open      = true;
        }
    "
    style="display:contents"
>
    <template x-if="open">
        <div class="pdv-overlay" style="z-index:10000">
            <div class="pdv-overlay__backdrop" @click="open = false; redirectUrl ? Livewire.navigate(redirectUrl) : $wire.cerrarComanda()"></div>
            <div class="pdv-modal" style="max-width:300px;width:100%;max-height:90vh;">

                {{-- Header --}}
                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo" x-text="'Comanda — ' + mesa"></h3>
                        <p class="pdv-modal__subtitulo" x-text="areas.length + (areas.length === 1 ? ' área de producción' : ' áreas de producción')"></p>
                    </div>
                    <button type="button" class="pdv-modal__cerrar" @click="open = false; redirectUrl ? Livewire.navigate(redirectUrl) : $wire.cerrarComanda()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Pestañas (solo si hay más de 1 área) --}}
                <template x-if="areas.length > 1">
                    <div class="pdv-comanda-tabs">
                        <template x-for="(area, i) in areas" :key="'tab-'+i">
                            <button
                                type="button"
                                class="pdv-comanda-tab"
                                :class="activeTab === i ? 'pdv-comanda-tab--activo' : ''"
                                @click="activeTab = i"
                                x-text="area.nombre ? area.nombre.toUpperCase() : ''"
                            ></button>
                        </template>
                    </div>
                </template>

                {{-- Paneles por área --}}
                <div class="pdv-modal__body" style="padding:1rem;gap:.75rem;overflow-y:auto;flex:1;min-height:0;">
                    <template x-for="(area, i) in areas" :key="'panel-'+i">
                        <div x-show="activeTab === i">

                            {{-- Chips nuevo / quitar --}}
                            <div class="pdv-comanda-chips" x-show="(area.nuevos && area.nuevos.length) || (area.cancelados && area.cancelados.length)" style="display:flex">
                                <span class="pdv-comanda-chip pdv-comanda-chip--nuevo"
                                    x-show="area.nuevos && area.nuevos.length"
                                    x-text="'+' + (area.nuevos ? area.nuevos.reduce((s,i)=>s+i.cant,0) : 0) + ' agregar'"></span>
                                <span class="pdv-comanda-chip pdv-comanda-chip--quitar"
                                    x-show="area.cancelados && area.cancelados.length"
                                    x-text="'-' + (area.cancelados ? area.cancelados.reduce((s,i)=>s+i.cant,0) : 0) + ' quitar'"></span>
                            </div>

                            {{-- Iframe de la comanda --}}
                            <div class="pdv-comanda-iframe-wrap">
                                <iframe
                                    :id="'np-frame-' + i"
                                    :src="ticketBase + '/' + ordenId + '?' + new URLSearchParams({
                                        area_nombre: area.nombre || 'COCINA',
                                        nuevos:      JSON.stringify(area.nuevos     || []),
                                        cancelados:  JSON.stringify(area.cancelados || []),
                                        parcial:     parcial ? '1' : '0',
                                        mesa:        mesa,
                                        cajero:      cajero,
                                        rol:         rol,
                                        numero:      numero
                                    }).toString()"
                                    class="pdv-comanda-iframe"
                                    @load="
                                        try {
                                            const doc = $el.contentDocument || $el.contentWindow.document;
                                            const h = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                                            if (h > 10) $el.style.height = h + 'px';
                                        } catch(e) {}
                                    "
                                ></iframe>
                            </div>

                            {{-- Botón imprimir --}}
                            <button
                                type="button"
                                class="pdv-btn-confirmar pdv-comanda-print-btn"
                                @click="document.getElementById('np-frame-' + i).contentWindow.print()"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                                <span x-text="'Imprimir ' + (area.nombre ? area.nombre.toUpperCase() : '')"></span>
                            </button>

                        </div>
                    </template>
                </div>

            </div>
        </div>
    </template>

</div>

{{-- Modal confirmación (sin impresión directa / fallback sin áreas) --}}
<div
    x-data="{ show: false, mesaNombre: '', ordenId: null }"
    @pedido-creado.window="show = true; mesaNombre = $event.detail[0].mesaNombre; ordenId = $event.detail[0].ordenId"
    style="display:contents"
>
    <div x-show="show" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:center;justify-content:center;" x-cloak>
        <div style="background:var(--body-bg,#fff);border-radius:.75rem;padding:2rem;max-width:380px;width:100%;text-align:center;">
            <div style="font-size:2.5rem;margin-bottom:.5rem;">🍽️</div>
            <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:.5rem;">Pedido registrado</h3>
            <p style="color:#666;margin-bottom:1.5rem;" x-text="'Mesa ' + mesaNombre + ' — pedido en cocina'"></p>
            <button
                @click="show = false; $wire.confirmarEnvio(ordenId)"
                style="background:#16a34a;color:#fff;border:none;padding:.6rem 1.5rem;border-radius:.5rem;font-weight:600;cursor:pointer;"
            >
                Entendido
            </button>
        </div>
    </div>
</div>

<div class="pdv-root">

    {{-- ══ HEADER ══ --}}
    <div class="pdv-header">
        <div class="pdv-header__left">
            <p class="pdv-header__titulo">Nuevo Pedido</p>
            <p class="pdv-header__sub">
                @if($mesa)
                    Mesa: <strong>{{ $mesa->nombre }}</strong>
                    @if($mesa->piso) · {{ $mesa->piso->nombre }} @endif
                @endif
            </p>
        </div>
        <div class="pdv-header__right">
            <div class="pdv-header__right-text">
                <p class="pdv-header__fecha">{{ now()->format('d/m/Y  H:i') }}</p>
                <p class="pdv-header__sub">Mozo: {{ auth()->user()->name }}</p>
            </div>
            @if(! empty($lastComandaData))
                <button
                    class="ep-btn ep-btn--icon"
                    wire:click="reenviarComanda"
                    title="Reenviar comanda"
                    style="margin-left:.5rem;"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.875 1.5C6.839 1.5 6 2.34 6 3.375v2.99c-.426.053-.851.11-1.274.174-1.454.218-2.476 1.483-2.476 2.917v6.294a3 3 0 0 0 3 3h.27l-.155 1.705A1.875 1.875 0 0 0 7.232 22.5h9.536a1.875 1.875 0 0 0 1.867-2.045l-.155-1.705h.27a3 3 0 0 0 3-3V9.456c0-1.434-1.022-2.7-2.476-2.917A48.716 48.716 0 0 0 18 6.366V3.375c0-1.036-.84-1.875-1.875-1.875h-8.25ZM16.5 6.205v-2.83A.375.375 0 0 0 16.125 3h-8.25a.375.375 0 0 0-.375.375v2.83a49.353 49.353 0 0 1 9 0Zm-.217 8.265c.178.018.317.16.333.337l.526 5.784a.375.375 0 0 1-.374.409H7.232a.375.375 0 0 1-.374-.409l.526-5.784a.337.337 0 0 1 .333-.337 41.741 41.741 0 0 1 8.566 0Zm.967-3.97a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 .75.75v.008a.75.75 0 0 1-.75.75H18a.75.75 0 0 1-.75-.75V10.5ZM15 9.75a.75.75 0 0 0-.75.75v.008c0 .414.336.75.75.75h.008a.75.75 0 0 0 .75-.75V10.5a.75.75 0 0 0-.75-.75H15Z" clip-rule="evenodd"/></svg>
                </button>
            @endif
        </div>
    </div>

    <div class="pdv-wrap" x-data="{ carritoOpen: false }" @cerrar-carrito.window="carritoOpen = false">

        {{-- ── Catálogo ── --}}
        <div class="pdv-productos"
             wire:loading.class="pdv-productos--sending"
             wire:target="enviarPedido">
            @if($mesaId)
                <livewire:pdv.product-catalog :carritoResumen="$resumen" :pendienteResumen="$pendiente" :showPromociones="true" wire:key="catalog-{{ $mesaId }}" />
            @endif
        </div>

        {{-- ── Panel del pedido ── --}}
        <div class="pdv-cart-backdrop" x-show="carritoOpen" @click="carritoOpen = false" style="display:none"></div>
        <div class="pdv-carrito" :class="{ 'pdv-carrito--open': carritoOpen }">

            <div class="pdv-carrito__header">
                <div class="pdv-carrito__titulo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0 1 18 9.375v9.375a3 3 0 0 0 3-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 0 0-.673-.05A3 3 0 0 0 15 1.5h-1.5a3 3 0 0 0-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6ZM13.5 3A1.5 1.5 0 0 0 12 4.5h4.5A1.5 1.5 0 0 0 15 3h-1.5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 0 1 3 20.625V9.375Z" clip-rule="evenodd"/></svg>
                    Pedido
                    @if($count > 0)
                        <span class="pdv-carrito__count">{{ $count }}</span>
                    @endif
                </div>
                <div class="pdv-carrito__header-actions">
                    @if(! empty($carrito))
                        <button class="pdv-carrito__vaciar" wire:click="vaciarCarrito">Vaciar</button>
                    @endif
                    <button class="pdv-carrito__cerrar-mobile" @click="carritoOpen = false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Lista de ítems --}}
            <div class="pdv-carrito__lista">
                @forelse($carrito as $key => $item)
                    <div class="pdv-item pdv-item--card" wire:key="item-{{ $key }}">
                        <div class="pdv-item__top">
                            <p class="pdv-item__nombre">
                                {{ $item['nombre'] }}
                                @if($item['tipo'] === 'promocion' && ! empty($item['detalles_resumen']))
                                    <x-pdv.promo-vista :detalles="$item['detalles_resumen']" clase="promo-vista--cart" />
                                @endif
                            </p>
                            <button class="pdv-item__del" wire:click="eliminarItem('{{ $key }}')">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="pdv-item__subtotal pdv-item__subtotal--big">
                            S/ {{ number_format($item['precio'], 2) }} c/u
                        </div>
                        <div class="pdv-item__bottom">
                            <div class="pdv-qty">
                                <button class="pdv-qty__btn pdv-qty__btn--menos" wire:click="decrementar('{{ $key }}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                </button>
                                <input class="pdv-qty__input" type="number" min="0.01" step="1"
                                    value="{{ $item['cantidad'] == floor($item['cantidad']) ? (int)$item['cantidad'] : number_format($item['cantidad'], 2) }}"
                                    @change="$wire.setCantidad('{{ $key }}', parseFloat($event.target.value) || 1)">
                                <button class="pdv-qty__btn pdv-qty__btn--mas" wire:click="incrementar('{{ $key }}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                            </div>
                            <span class="pdv-item__precio-unit pdv-item__precio-unit--total">S/ {{ number_format($item['precio'] * $item['cantidad'], 2) }}</span>
                        </div>

                        {{-- Nota para cocina --}}
                        <div class="pdv-item__nota-wrap"
                            x-data="{ open: {{ !empty($item['nota']) ? 'true' : 'false' }}, nota: @js($item['nota'] ?? '') }">
                            <button type="button"
                                class="pdv-nota-toggle"
                                :class="{ 'pdv-nota-toggle--active': open || nota }"
                                @click="open = !open">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="pdv-nota-icon"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.885L17.5 5.5a2.121 2.121 0 0 0-3-3L3.58 13.42a4 4 0 0 0-.885 1.343Z"/></svg>
                                <span x-text="nota ? nota : 'Añadir nota para cocina'"></span>
                            </button>
                            <div x-show="open" x-transition.duration.150ms class="pdv-nota-input-wrap">
                                <input
                                    type="text"
                                    class="pdv-nota-input"
                                    placeholder="Ej: sin cebolla, término medio, extra salsa…"
                                    x-model="nota"
                                    @blur="$wire.setNota('{{ $key }}', nota)"
                                    maxlength="120">
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="pdv-carrito__empty">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg>
                        <p>Sin productos aún</p>
                        <span>Selecciona productos del catálogo</span>
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="pdv-carrito__footer">
                <div class="pdv-carrito__totales">
                    <div class="pdv-carrito__fila">
                        <span class="pdv-carrito__total-label">Total estimado</span>
                        <span class="pdv-carrito__total-monto">S/ {{ number_format($total, 2) }}</span>
                    </div>
                </div>

                @can('restaurante.pedido.crear')
                <button
                    class="pdv-btn-venta"
                    wire:click="enviarPedido"
                    wire:loading.attr="disabled"
                    @if(empty($carrito)) disabled style="opacity:.5;cursor:not-allowed;" @endif
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;" aria-hidden="true"><path d="M3.478 2.405a.75.75 0 0 0-.926.94l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.405Z"/></svg>
                    <span wire:loading.remove wire:target="enviarPedido">Enviar pedido</span>
                    <span wire:loading wire:target="enviarPedido">Enviando…</span>
                </button>
                @endcan
            </div>

        </div>{{-- /pdv-carrito --}}

        {{-- FAB móvil --}}
        <button class="pdv-fab" @click="carritoOpen = true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg>
            @if($count > 0)
                <span class="pdv-fab__badge">{{ $count }}</span>
            @endif
        </button>

    </div>{{-- /pdv-wrap --}}

</div>

<link rel="stylesheet" href="{{ asset('css/nuevo-pedido.css') }}?v={{ filemtime(public_path('css/nuevo-pedido.css')) }}">

</x-filament-panels::page>
