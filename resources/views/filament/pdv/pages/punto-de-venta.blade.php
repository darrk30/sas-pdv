<x-filament-panels::page>

    <link rel="stylesheet" href="{{ asset('css/punto-de-venta.css') }}?v={{ filemtime(public_path('css/punto-de-venta.css')) }}">

    <div class="pdv-root">

    {{-- ══ HEADER ══ --}}
    <div class="pdv-header">
        <div class="pdv-header__left">
            <p class="pdv-header__titulo">Punto de Venta</p>
            <p class="pdv-header__sub">Cajero: {{ auth()->user()->name }}</p>
        </div>

        <div class="pdv-header__right">
            {{-- Botón reimprimir último ticket --}}
            @if($ultimaVentaId)
            <div class="pdv-reprint-wrap" x-data="{ hover: false }"
                 @pdv-reprint-browser.window="(function(){ var f = document.getElementById('pdv-reprint-frame'); if (f && f.contentWindow) f.contentWindow.print(); })()">
                <button
                    type="button"
                    class="pdv-reprint-btn"
                    @mouseenter="hover = true"
                    @mouseleave="hover = false"
                    wire:click="reimprimirDirecto"
                    wire:loading.attr="disabled"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.169a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                    </svg>
                </button>
                <div class="pdv-reprint-tooltip" x-show="hover" style="display:none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="11" height="11">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.169a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/>
                    </svg>
                    Reimprimir {{ $ultimaVentaNumero }}
                </div>
            </div>
            @endif
            <div class="pdv-header__right-text">
                <p class="pdv-header__venta">Venta #{{ $this->getNumeroPreview() }}</p>
                <p class="pdv-header__fecha">{{ now()->format('d/m/Y  H:i') }}</p>
            </div>
        </div>
    </div>

    {{-- Iframe oculto para reimpresión del último ticket --}}
    @if($ultimaVentaId)
    <iframe
        id="pdv-reprint-frame"
        src="{{ route('pdv.ticket.venta', $ultimaVentaId) }}"
        style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;border:0;"
    ></iframe>
    @endif

    <div class="pdv-wrap"
         x-data="{
             carrito: {},
             carritoOpen: false,
             _t: {},
             get carritoVacio() { return Object.keys(this.carrito).length === 0; },
             get itemCount()    { return Object.keys(this.carrito).length; },
             get carritoTotal() {
                 return Object.values(this.carrito).reduce(function(s,it){ return it.cortesia ? s : s + parseFloat(it.cantidad) * parseFloat(it.precio); }, 0);
             },
             init() {
                 this._t = {};
                 this.carrito = window.__pdvInitCarrito ?? {};
                 this.syncResumen();
             },
             _clone() {
                 const c = {};
                 for (const k in this.carrito) {
                     const e = this.carrito[k];
                     c[k] = { key: k, tipo: e.tipo, id: e.id, nombre: e.nombre, precio: parseFloat(e.precio)||0, precio_normal: parseFloat(e.precio_normal)||0, cortesia: !!e.cortesia, puede_cortesia: !!e.puede_cortesia, cantidad: parseFloat(e.cantidad)||0, decimal: !!e.decimal, stock_max: e.stock_max??null, venta_sin_stock: !!e.venta_sin_stock, detalles_resumen: e.detalles_resumen||[] };
                 }
                 return c;
             },
             _qtyForBase(tipo, id) {
                 let total = 0;
                 for (const it of Object.values(this.carrito)) {
                     if (it.tipo === tipo && it.id === id) total += parseFloat(it.cantidad)||0;
                 }
                 return total;
             },
             _resolveKey(c, baseKey, esCortesia, precio) {
                 const matches = (it) => !!it.cortesia === esCortesia && (esCortesia || Math.abs((parseFloat(it.precio)||0) - precio) < 0.005);
                 if (!c[baseKey]) return baseKey;
                 if (matches(c[baseKey])) return baseKey;
                 let i = 2;
                 while (true) {
                     const k = baseKey + '_' + i;
                     if (!c[k]) return k;
                     if (matches(c[k])) return k;
                     i++;
                 }
             },
             syncResumen() {
                 const r = {};
                 for (const it of Object.values(this.carrito)) {
                     if (it.tipo === 'promocion') {
                         const sk = 'promocion_' + it.id;
                         r[sk] = (r[sk]||0) + parseFloat(it.cantidad);
                         for (const d of (it.detalles_resumen||[])) {
                             const dk = d.variante_id ? 'variante_'+d.variante_id : 'producto_'+d.producto_id;
                             r[dk] = (r[dk]||0) + d.cantidad * parseFloat(it.cantidad);
                         }
                     } else {
                         const sk2 = it.tipo + '_' + it.id;
                         r[sk2] = (r[sk2]||0) + parseFloat(it.cantidad);
                     }
                 }
                 Alpine.store('carritoResumen', r);
             },
             addToCart(p) {
                 const esCortesia = !!p.es_cortesia;
                 const precio     = parseFloat(p.precio) || 0;
                 const addQty     = parseFloat(p.cantidad) || 1;
                 const baseKey    = p.tipo + '_' + p.id;
                 const stockMax   = p.stock_max ?? null;

                 if (stockMax !== null && !(p.venta_sin_stock)) {
                     const enCarrito = this._qtyForBase(p.tipo, p.id);
                     if (enCarrito + addQty > stockMax) { this.$wire.notificarStockInsuficiente(stockMax); return; }
                 }

                 const c   = this._clone();
                 const key = this._resolveKey(c, baseKey, esCortesia, precio);
                 if (c[key]) {
                     const it = c[key];
                     c[key].cantidad = it.decimal ? Math.round((it.cantidad + addQty) * 1000)/1000 : it.cantidad + addQty;
                 } else {
                     c[key] = { key: key, tipo: p.tipo, id: p.id, nombre: p.nombre, precio: precio, precio_normal: parseFloat(p.precio_normal||p.precio)||0, cortesia: esCortesia, puede_cortesia: !!p.puede_cortesia, cantidad: addQty, decimal: !!p.es_decimal, stock_max: stockMax, venta_sin_stock: !!p.venta_sin_stock, detalles_resumen: p.detalles_resumen||[] };
                 }
                 this.carrito = c;
                 this.syncResumen();
             },
             canInc(key) {
                 const it = this.carrito[key];
                 if (!it) return false;
                 if (it.stock_max === null || it.venta_sin_stock) return true;
                 return this._qtyForBase(it.tipo, it.id) < it.stock_max;
             },
             incItem(key) {
                 if (!this.canInc(key)) { const it0 = this.carrito[key]; if (it0) this.$wire.notificarStockInsuficiente(it0.stock_max); return; }
                 const c = this._clone(); const it = c[key]; if (!it) return;
                 c[key].cantidad = it.decimal ? Math.round((it.cantidad+1)*1000)/1000 : it.cantidad+1;
                 this.carrito = c; this.syncResumen(); this.scheduleSync(key);
             },
             decItem(key) {
                 const c = this._clone(); const it = c[key]; if (!it) return;
                 const next = it.decimal ? Math.round((it.cantidad-1)*1000)/1000 : it.cantidad-1;
                 if (next <= 0) { this.delItem(key); return; }
                 c[key].cantidad = next;
                 this.carrito = c; this.syncResumen(); this.scheduleSync(key);
             },
             scheduleSync(key) {
                 clearTimeout(this._t[key]);
                 this._t[key] = setTimeout(() => {
                     const it = this.carrito[key];
                     if (it) { this.$wire.carrito = this._clone(); this.$wire.actualizarCantidad(key, Math.max(0.001, parseFloat(it.cantidad))); }
                 }, 350);
             },
             delItem(key) {
                 clearTimeout(this._t[key]); delete this._t[key];
                 const c = this._clone(); delete c[key];
                 this.carrito = c; this.syncResumen();
             },
             vaciar() {
                 Object.values(this._t).forEach(clearTimeout); this._t = {};
                 this.carrito = {}; this.syncResumen();
             }
         }"
         @cerrar-carrito-mobile.window="carritoOpen = false"
         @pdv-carrito-sync.window="
            const _fr = $event.detail.carrito;
            const _me = {};
            for (const _k in _fr) {
                _me[_k] = _t[_k]
                    ? { ..._fr[_k], cantidad: carrito[_k]?.cantidad ?? _fr[_k].cantidad, precio: carrito[_k]?.precio ?? _fr[_k].precio }
                    : _fr[_k];
            }
            carrito = _me;
            Alpine.store('carritoResumen', $event.detail.resumen)"
         @product-selected.window="addToCart($event.detail[0] ?? $event.detail)"
         @pdv-abrir-variantes.window="Alpine.store('vm') && Alpine.store('vm').abrir($event.detail.data, $event.detail.precioLista)">

        {{-- ══ ÁREA DE PRODUCTOS (izquierda) ══ --}}
        <livewire:pdv.product-catalog
            :carritoResumen="$this->getCarritoResumen()"
            :pendienteResumen="$this->getPendienteResumen()"
            :showPromociones="true"
            wire:key="pdv-catalog"
        />


        {{-- ══ CARRITO (derecha) ══ --}}
        {{-- Backdrop mobile --}}
        <div
            class="pdv-cart-backdrop"
            x-show="carritoOpen"
            @click="carritoOpen = false"
            style="display:none"
        ></div>

        <div class="pdv-carrito" :class="{ 'pdv-carrito--open': carritoOpen }">

            {{-- Header carrito --}}
            <div class="pdv-carrito__header">
                <div class="pdv-carrito__titulo">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    Carrito
                    <span class="pdv-carrito__count" x-show="itemCount > 0" x-text="itemCount" style="display:none"></span>
                </div>
                <div class="pdv-carrito__header-actions">
                    <button class="pdv-carrito__vaciar" x-show="!carritoVacio" @click="vaciar()" style="display:none" type="button">Vaciar</button>
                    <button class="pdv-carrito__cerrar-mobile" @click="carritoOpen = false" title="Cerrar">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ── CLIENTE ── --}}
            <div class="pdv-cliente">
                <div class="pdv-cliente__row">
                    <div class="pdv-cliente__search-wrap">
                        <svg class="pdv-cliente__icono" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                        <input
                            type="text"
                            class="pdv-cliente__input"
                            wire:model.live.debounce.300ms="clienteBusqueda"
                            placeholder="Buscar cliente..."
                        />
                        @if($clienteId)
                            <button class="pdv-cliente__clear" wire:click="limpiarCliente">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                    <button class="pdv-cliente__nuevo-btn" wire:click="abrirModalNuevoCliente"
                            wire:loading.attr="disabled" wire:target="abrirModalNuevoCliente"
                            title="Nuevo cliente">
                        <svg wire:loading.remove wire:target="abrirModalNuevoCliente"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        <svg wire:loading wire:target="abrirModalNuevoCliente"
                             class="pdv-spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-dasharray="28 56" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                {{-- Dropdown sugerencias --}}
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

            </div>

            {{-- ── TIPO DE COMPROBANTE ── --}}
            @php
                $series  = $this->getSeries();
                $tieneFE = \Filament\Facades\Filament::getTenant()->tieneFacturacionElectronica();
            @endphp
            <div class="pdv-comprobante">
                @foreach([
                    ['tipo' => 'factura', 'label' => 'Factura',  'color' => 'info',    'soloFE' => true],
                    ['tipo' => 'boleta',  'label' => 'Boleta',   'color' => 'success', 'soloFE' => true],
                    ['tipo' => 'ticket',  'label' => 'Ticket',   'color' => 'warning', 'soloFE' => false],
                ] as $c)
                    @if($c['soloFE'] && !$tieneFE) @continue @endif
                    @php
                        $serie      = $series->first(fn($s) => $s->tipo->value === $c['tipo']);
                        $activo     = $tipoComprobante === $c['tipo'];
                        $disponible = $serie !== null;
                        $invalido   = $activo && $clienteId && $c['tipo'] === 'factura' && $clienteTipoDoc !== 'ruc';
                    @endphp
                    <button
                        class="pdv-comp-btn pdv-comp-btn--{{ $c['color'] }} {{ $activo ? 'pdv-comp-btn--activo' : '' }} {{ ! $disponible ? 'pdv-comp-btn--disabled' : '' }}"
                        wire:click="seleccionarComprobante('{{ $c['tipo'] }}')"
                        @if(! $disponible) disabled @endif
                        title="{{ ! $disponible ? 'Sin serie activa' : '' }}"
                    >
                        <span class="pdv-comp-btn__label">{{ $c['label'] }}</span>
                        <span class="pdv-comp-btn__serie">{{ $serie?->serie ?? 'Sin serie' }}</span>
                        @if($invalido)
                            <span class="pdv-comp-btn__alerta">Requiere RUC</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Items del carrito --}}
            <div class="pdv-carrito__empty" x-show="carritoVacio" style="display:none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                </svg>
                <p>El carrito está vacío</p>
                <span>Selecciona productos para comenzar</span>
            </div>

            <div class="pdv-carrito__lista" x-show="!carritoVacio" style="display:none" wire:ignore>
                <template x-for="[key, item] in Object.entries(carrito)" :key="key">
                    <div class="pdv-item" :class="{'pdv-item--cortesia': item.cortesia}">

                        {{-- Cabecera: badges + nombre + precio + botón eliminar --}}
                        <div class="pdv-item__top">
                            <div class="pdv-item__info">
                                <div class="pdv-item__badges">
                                    <span x-show="item.tipo === 'promocion'" class="pdv-item__badge-promo" style="display:none">PROMO</span>
                                    <button
                                        x-show="item.puede_cortesia"
                                        @click="$wire.carrito = _clone(); $wire.toggleCortesia(key)"
                                        :class="item.cortesia ? 'pdv-item__badge-cortesia pdv-item__badge-cortesia--on' : 'pdv-item__badge-cortesia pdv-item__badge-cortesia--off'"
                                        :title="item.cortesia ? 'Quitar cortesía' : 'Aplicar como cortesía (gratis)'"
                                        style="display:none"
                                        type="button"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="11" height="11">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                                        </svg>
                                        <span x-text="item.cortesia ? 'GRATIS' : 'Cortesía'"></span>
                                    </button>
                                </div>
                                <p class="pdv-item__nombre" x-text="item.nombre"></p>
                                <p class="pdv-item__precio-unit">
                                    <span x-show="item.cortesia" class="pdv-item__precio-gratis" style="display:none">Gratis</span>
                                    <span
                                        x-show="!item.cortesia"
                                        x-data="{ editing: false, val: parseFloat(item.precio).toFixed(2), saved: parseFloat(item.precio).toFixed(2) }"
                                        class="pdv-item__precio-editable"
                                        @pdv-carrito-sync.window="if (!editing) { const _ev = $event.detail.carrito[key]; if (_ev) { val = parseFloat(_ev.precio).toFixed(2); saved = val; } }"
                                        style="display:none"
                                    >
                                        <span x-show="item.precio_normal > item.precio" class="pdv-item__precio-tachado" style="display:none">
                                            S/ <span x-text="parseFloat(item.precio_normal).toFixed(2)"></span>
                                        </span>
                                        <span
                                            x-show="!editing"
                                            @click="editing = true; $nextTick(() => $refs.priceInp?.select())"
                                            :class="item.precio_normal > item.precio ? 'pdv-item__precio-valor pdv-item__precio-valor--oferta' : 'pdv-item__precio-valor'"
                                            title="Toca para editar el precio"
                                        >S/ <span x-text="parseFloat(val).toFixed(2)"></span> c/u</span>
                                        <input
                                            x-ref="priceInp"
                                            x-show="editing"
                                            x-model="val"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            class="pdv-item__precio-input"
                                            @blur="const _p = Math.max(0.01, parseFloat(val) || 0.01); val = _p.toFixed(2); editing = false; $wire.carrito = _clone(); $wire.actualizarPrecio(item.key, _p)"
                                            @keydown.enter="$el.blur()"
                                            @keydown.escape="editing = false; val = saved"
                                        />
                                    </span>
                                </p>
                            </div>
                            <button class="pdv-item__del" @click="delItem(key)" title="Eliminar" type="button">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                            </button>
                        </div>

                        {{-- Pie: controles de cantidad + subtotal --}}
                        <div class="pdv-item__controles">
                            <div class="pdv-item__foot">
                                <div class="pdv-qty pdv-qty--decimal" x-show="item.decimal" style="display:none">
                                    <button class="pdv-qty__btn pdv-qty__btn--menos" @click="decItem(key)" type="button">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                    </button>
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        :value="item.cantidad"
                                        class="pdv-qty__decimal-input"
                                        @keydown.enter="$el.blur()"
                                        @keydown="if($event.key==='-'||$event.key==='e'||$event.key==='E')$event.preventDefault()"
                                        @input="$event.target.value=$event.target.value.replace(/[^0-9.]/g,'').replace(/^(\d*\.?\d*).*$/,'$1')"
                                        @blur="carrito[key].cantidad=Math.max(0.001,parseFloat($event.target.value)||0.001); $event.target.value=carrito[key].cantidad; syncResumen(); scheduleSync(key)"
                                    />
                                    <button class="pdv-qty__btn pdv-qty__btn--mas" @click="incItem(key)" :disabled="!canInc(key)" :class="{'pdv-qty__btn--disabled': !canInc(key)}" type="button">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                    </button>
                                </div>
                                <div class="pdv-qty" x-show="!item.decimal" style="display:none">
                                    <button class="pdv-qty__btn pdv-qty__btn--menos" @click="decItem(key)" type="button">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                    </button>
                                    <input x-show="item.tipo === 'promocion'" type="number" min="1" step="1" :value="item.cantidad" class="pdv-qty__decimal-input"
                                        @blur="carrito[key].cantidad = Math.max(1, parseInt($event.target.value)||1); syncResumen(); scheduleSync(key)"
                                        @keydown.enter="$el.blur()"
                                        @keydown="if($event.key==='-'||$event.key==='e'||$event.key==='E')$event.preventDefault()"
                                        @input="if(parseInt($event.target.value)<1)$event.target.value=1"
                                        style="display:none">
                                    <span class="pdv-qty__num" x-show="item.tipo !== 'promocion'" x-text="Math.round(item.cantidad)" style="display:none"></span>
                                    <button class="pdv-qty__btn pdv-qty__btn--mas" @click="incItem(key)" :disabled="!canInc(key)" :class="{'pdv-qty__btn--disabled': !canInc(key)}" type="button">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                    </button>
                                </div>
                                <span class="pdv-item__subtotal" :class="{'pdv-item__subtotal--gratis': item.cortesia}"
                                      x-text="'S/ ' + (parseFloat(item.cantidad) * parseFloat(item.precio)).toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="pdv-carrito__footer" x-show="!carritoVacio" style="display:none">
                <div class="pdv-carrito__totales">
                    <div class="pdv-carrito__fila">
                        <span class="pdv-carrito__label" x-text="itemCount + ' ítems'"></span>
                        <span class="pdv-carrito__sublabel">Subtotal</span>
                    </div>
                    <div class="pdv-carrito__fila">
                        <span class="pdv-carrito__total-label">Total</span>
                        <span class="pdv-carrito__total-monto" x-text="'S/ ' + carritoTotal.toFixed(2)"></span>
                    </div>
                </div>
                <button class="pdv-btn-venta" @click="$wire.carrito = _clone(); $wire.abrirModalPago()" type="button"
                    wire:loading.attr="disabled" wire:target="abrirModalPago">
                    <span wire:loading.remove wire:target="abrirModalPago">
                        Procesar Venta
                        <kbd class="pdv-kbd">Ctrl+↵</kbd>
                    </span>
                    <span wire:loading wire:target="abrirModalPago" style="display:none;align-items:center;gap:6px;">
                        <svg class="pdv-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"/><path fill="currentColor" style="opacity:.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Cargando...
                    </span>
                </button>
            </div>

        </div>{{-- /pdv-carrito --}}

        {{-- ══ FAB carrito (solo mobile) ══ --}}
        <button class="pdv-fab" @click="carritoOpen = true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
            </svg>
            <span class="pdv-fab__badge" x-show="itemCount > 0" x-text="itemCount" style="display:none"></span>
        </button>

    </div>{{-- /pdv-wrap --}}

    </div>{{-- /pdv-root --}}


    {{-- ══ MODAL: sin sesión de caja ══ --}}
    @if($modalSinSesion)
        <div class="pdv-overlay" wire:key="modal-sin-sesion">
            <div class="pdv-overlay__backdrop" wire:click="cerrarModalSinSesion"></div>
            <div class="pdv-modal pdv-modal--sin-sesion">
                <div class="pdv-sin-sesion">
                    <div class="pdv-sin-sesion__icono">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                    </div>
                    <h3 class="pdv-sin-sesion__titulo">Sin sesión de caja activa</h3>
                    <p class="pdv-sin-sesion__desc">Debes aperturar una caja antes de poder procesar ventas.</p>
                    <a href="{{ $this->getUrlAperturaCaja() }}" class="pdv-sin-sesion__btn-aperturar">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                        </svg>
                        Aperturar Caja
                    </a>
                    <button class="pdv-sin-sesion__btn-cancelar" wire:click="cerrarModalSinSesion">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    @endif


    {{-- ══ MODAL: pago ══ --}}
    @if($modalPago)
        <div class="pdv-overlay" wire:key="modal-pago">
            <div class="pdv-overlay__backdrop" wire:click="cerrarModalPago"></div>
            <div class="pdv-modal pdv-modal--pago"
                 x-data="{
                     activeTab: 'pagos',
                     deliveryActivo: false,
                     despachoRequerido: false,
                     despachoDireccion: '',
                     deliveryNombre: '{{ addslashes($clienteNombre ?? '') }}',
                     deliveryTelefono: '{{ addslashes($clienteTelefono ?? '') }}',
                     deliveryRepartidor: '',

                     metodosPago: {{ json_encode($metodosPagoDisponibles) }},
                     metodoPagoId: {{ $metodoPagoId ?? 'null' }},
                     montoPagoInput: '{{ $montoPagoInput ?? '' }}',
                     pagoReferencia: '',
                     pagosAgregados: {{ json_encode($pagosAgregados ?? []) }},
                     descuentoInput: '{{ $descuentoInput ?? '0' }}',
                     totalBase: {{ $this->getTotal() }},
                     esTicket: {{ $tipoComprobante === 'ticket' ? 'true' : 'false' }},

                     get metodoActivo() { return this.metodosPago.find(m => m.id === this.metodoPagoId) || null; },
                     get descuento() {
                         const d = parseFloat((this.descuentoInput + '').replace(',', '.') || '0');
                         return Math.max(0, Math.min(d, this.totalBase));
                     },
                     get totalConDescuento() { return Math.round(Math.max(0, this.totalBase - this.descuento) * 100) / 100; },
                     get totalPagado()       { return Math.round(this.pagosAgregados.reduce((s, p) => s + parseFloat(p.monto), 0) * 100) / 100; },
                     get saldoRestante()     { return Math.round((this.totalConDescuento - this.totalPagado) * 100) / 100; },
                     get totalEsCero()       { return this.totalConDescuento <= 0.01; },
                     get listo()             { return (this.saldoRestante <= 0.01 && this.pagosAgregados.length > 0) || this.totalEsCero; },
                     get opGravadas()        { return this.esTicket ? 0 : Math.round(this.totalConDescuento / 1.18 * 100) / 100; },
                     get igv()               { return this.esTicket ? 0 : Math.round((this.totalConDescuento - this.opGravadas) * 100) / 100; },
                     fmt(n) { return parseFloat(n).toFixed(2); },

                     autoSeleccionarEfectivo() {
                         if (this.metodoPagoId) return;
                         const ef = this.metodosPago.find(m => m.nombre.toLowerCase() === 'efectivo');
                         if (ef) { this.metodoPagoId = ef.id; this.pagoReferencia = ''; }
                     },
                     seleccionarMetodoPago(id) {
                         this.metodoPagoId = id;
                         this.pagoReferencia = '';
                         const s = this.saldoRestante;
                         this.montoPagoInput = s > 0 ? this.fmt(s) : '0.00';
                     },
                     agregarPago() {
                         const monto = parseFloat((this.montoPagoInput + '').replace(',', '.') || '0');
                         if (!this.metodoPagoId || monto <= 0) return;
                         const metodo = this.metodosPago.find(m => m.id === this.metodoPagoId);
                         if (metodo && metodo.requiere_referencia && !(this.pagoReferencia || '').trim()) return;
                         const condicion = metodo ? metodo.condicion_pago : 'contado';
                         const idx = this.pagosAgregados.findIndex(p => p.metodo_pago_id === this.metodoPagoId && (p.condicion_pago || 'contado') === condicion);
                         if (idx !== -1) {
                             this.pagosAgregados[idx].monto += monto;
                             if (this.pagoReferencia) this.pagosAgregados[idx].referencia = this.pagoReferencia;
                         } else {
                             this.pagosAgregados.push({
                                 metodo_pago_id: this.metodoPagoId,
                                 nombre: metodo ? metodo.nombre : '',
                                 imagen_url: metodo ? metodo.imagen_url : null,
                                 monto,
                                 referencia: this.pagoReferencia,
                                 condicion_pago: condicion
                             });
                         }
                         const s = this.saldoRestante;
                         this.montoPagoInput = s > 0 ? this.fmt(s) : '0.00';
                         this.pagoReferencia = '';
                     },
                     eliminarPago(idx) {
                         this.pagosAgregados.splice(idx, 1);
                         const s = this.saldoRestante;
                         if (s > 0) this.montoPagoInput = this.fmt(s);
                     },
                     setMontoExacto() {
                         this.autoSeleccionarEfectivo();
                         this.montoPagoInput = this.fmt(Math.max(0, this.saldoRestante));
                     },
                     ajustarMonto(delta) {
                         this.autoSeleccionarEfectivo();
                         const actual = parseFloat((this.montoPagoInput + '').replace(',', '.') || '0');
                         this.montoPagoInput = this.fmt(Math.max(0, actual + delta));
                     },
                     confirmarVenta() {
                         if (!this.listo) return;
                         $wire.deliveryActivo       = this.deliveryActivo;
                         $wire.despachoRequerido    = this.despachoRequerido;
                         $wire.despachoDireccion    = this.despachoDireccion;
                         $wire.deliveryNombre       = this.deliveryActivo ? this.deliveryNombre    : '';
                         $wire.deliveryTelefono     = this.deliveryActivo ? this.deliveryTelefono  : '';
                         $wire.deliveryRepartidor   = this.deliveryActivo ? this.deliveryRepartidor : '';
                         $wire.pagosAgregados       = this.pagosAgregados;
                         $wire.descuentoInput       = this.descuentoInput;
                         $wire.procesarVenta();
                     }
                 }">

                {{-- Header --}}
                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo">Procesar Venta</h3>
                        <p class="pdv-modal__subtitulo">Selecciona método y completa el pago</p>
                    </div>
                    @if(\Filament\Facades\Filament::getTenant()->tieneModulo('despacho') && auth()->user()?->can('tienda.ver'))
                    <div class="pdv-pago-toggles">
                        <label class="pdv-pago-toggle">
                            <input type="checkbox" class="pdv-pago-toggle__check"
                                x-model="deliveryActivo"
                                @change="if (deliveryActivo) { activeTab = 'delivery' } else { if (activeTab === 'delivery') activeTab = 'pagos' }"
                            />
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="13" height="13">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                            </svg>
                            <span>Delivery</span>
                        </label>
                        <label class="pdv-pago-toggle">
                            <input type="checkbox" class="pdv-pago-toggle__check"
                                x-model="despachoRequerido"
                                @change="if (despachoRequerido) { activeTab = 'despacho' } else { if (activeTab === 'despacho') activeTab = 'pagos' }"
                            />
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="13" height="13">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                            </svg>
                            <span>Despacho</span>
                        </label>
                    </div>
                    @endif
                    <button class="pdv-modal__cerrar" wire:click="cerrarModalPago">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Tabs --}}
                @if(\Filament\Facades\Filament::getTenant()->tieneModulo('despacho') && auth()->user()?->can('tienda.ver'))
                <div class="pdv-pago-tabs">
                    <button class="pdv-pago-tab" :class="{ 'pdv-pago-tab--active': activeTab === 'pagos' }"
                        @click="activeTab = 'pagos'" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/>
                        </svg>
                        Pagos
                    </button>
                    <button class="pdv-pago-tab" :class="{ 'pdv-pago-tab--active': activeTab === 'delivery' }"
                        x-show="deliveryActivo" style="display:none"
                        @click="activeTab = 'delivery'" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                        </svg>
                        Delivery
                    </button>
                    <button class="pdv-pago-tab" :class="{ 'pdv-pago-tab--active': activeTab === 'despacho' }"
                        x-show="despachoRequerido" style="display:none"
                        @click="activeTab = 'despacho'" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                        Despacho
                    </button>
                </div>
                @endif

                {{-- Body: layout 2 columnas en PC --}}
                <div class="pdv-modal__body pdv-pago-body" x-show="activeTab === 'pagos'">
                    <div class="pdv-pago-layout">

                        {{-- ══ COLUMNA IZQUIERDA: métodos de pago ══ --}}
                        <div class="pdv-pago-col-izq">
                            <div class="pdv-pago-section">
                                <p class="pdv-pago-section__label">Método de pago</p>
                                <p class="pdv-pago-empty" x-show="metodosPago.length === 0">No hay métodos de pago configurados</p>
                                <div class="pdv-metodos-lista" x-show="metodosPago.length > 0">
                                    <template x-for="(metodo, idx) in metodosPago" :key="metodo.id">
                                        <button class="pdv-metodo-item" :class="{ 'pdv-metodo-item--activo': metodoPagoId === metodo.id }" @click="seleccionarMetodoPago(metodo.id)" type="button">
                                            <template x-if="metodo.imagen_url">
                                                <img class="pdv-metodo-item__img" :src="metodo.imagen_url" :alt="metodo.nombre"/>
                                            </template>
                                            <template x-if="!metodo.imagen_url">
                                                <div class="pdv-metodo-item__avatar" x-text="metodo.nombre.charAt(0).toUpperCase()"></div>
                                            </template>
                                            <span class="pdv-metodo-item__nombre" x-text="metodo.nombre"></span>
                                            <kbd class="pdv-kbd pdv-kbd--metodo" x-show="idx < 9" x-text="'Ctrl+' + (idx + 1)"></kbd>
                                            <svg class="pdv-metodo-item__check" x-show="metodoPagoId === metodo.id" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>

                                {{-- Referencia --}}
                                <div class="pdv-pago-referencia" x-show="metodoActivo && metodoActivo.requiere_referencia" style="display:none">
                                    <input type="text" class="pdv-field__input" x-model="pagoReferencia" placeholder="Referencia / N° operación"/>
                                </div>

                                {{-- Crédito: fecha vencimiento --}}
                                <div class="pdv-pago-referencia" x-show="metodoActivo && metodoActivo.condicion_pago === 'credito'" style="display:none">
                                    <x-filament::input.wrapper label="Fecha de vencimiento" :prefix-icon="'heroicon-o-calendar-days'" style="--prefix-icon-size: .9rem;">
                                        <x-filament::input type="date" wire:model.live="fechaVencimientoCredito" :min="now()->addDay()->toDateString()"/>
                                    </x-filament::input.wrapper>
                                    @if($fechaVencimientoCredito)
                                        <p class="fi-fo-field-wrp-helper-text text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Vence {{ \Carbon\Carbon::parse($fechaVencimientoCredito)->format('d/m/Y') }}
                                            ({{ \Carbon\Carbon::parse($fechaVencimientoCredito)->diffForHumans() }})
                                        </p>
                                    @endif
                                    <div x-show="!$wire.clienteId" style="display:flex;align-items:center;gap:.4rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:.4rem;padding:.4rem .6rem;margin-top:.4rem;font-size:.73rem;color:#92400e;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:.85rem;height:.85rem;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                        <span>Para crédito selecciona un cliente con DNI o RUC</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ══ COLUMNA DERECHA ══ --}}
                        <div class="pdv-pago-col-der">

                            {{-- ── Monto y botones rápidos ── --}}
                            <div class="pdv-pago-section">
                                <p class="pdv-pago-section__label">Monto a pagar</p>

                                <div class="pdv-pago-monto-row">
                                    <div class="pdv-pago-input-wrap" style="flex:1">
                                        <span class="pdv-pago-input-wrap__prefix">S/</span>
                                        <input
                                            type="number"
                                            class="pdv-pago-input"
                                            x-model="montoPagoInput"
                                            min="0"
                                            step="0.10"
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <button class="pdv-btn-agregar-pago" @click="agregarPago()" type="button">
                                        Agregar
                                        <kbd class="pdv-kbd">Ctrl+↵</kbd>
                                    </button>
                                </div>
                            </div>

                            {{-- ── Pagos registrados ── --}}
                            <div class="pdv-pago-section" x-show="pagosAgregados.length > 0" style="display:none">
                                <p class="pdv-pago-section__label">Pagos registrados</p>
                                <div class="pdv-pagos-lista">
                                    <template x-for="(pago, idx) in pagosAgregados" :key="idx">
                                        <div class="pdv-pago-item">
                                            <div class="pdv-pago-item__info">
                                                <span class="pdv-pago-item__nombre">
                                                    <span x-text="pago.nombre"></span>
                                                    <span class="pdv-credito-badge" x-show="pago.condicion_pago === 'credito'">Crédito</span>
                                                </span>
                                                <span class="pdv-pago-item__ref" x-show="pago.referencia" x-text="pago.referencia"></span>
                                            </div>
                                            <span class="pdv-pago-item__monto" x-text="'S/ ' + fmt(pago.monto)"></span>
                                            <button class="pdv-pago-item__del" @click="eliminarPago(idx)" type="button">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- ── Resumen + descuento + saldo ── --}}
                            <div class="pdv-pago-section pdv-pago-section--resumen">
                                <p class="pdv-pago-section__label">Resumen</p>

                                <div class="pdv-pago-resumen">
                                    @php $itemsCortesia = collect($carrito)->where('cortesia', true); @endphp
                                    @if($itemsCortesia->isNotEmpty())
                                        <div class="pdv-pago-resumen__fila pdv-pago-resumen__fila--cortesia">
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="12" height="12" style="display:inline;vertical-align:middle;margin-right:2px">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                                                </svg>
                                                Cortesía ({{ $itemsCortesia->count() }} ítem{{ $itemsCortesia->count() > 1 ? 's' : '' }})
                                            </span>
                                            <span>Gratis</span>
                                        </div>
                                    @endif
                                    <template x-if="!esTicket">
                                        <div>
                                            <div class="pdv-pago-resumen__fila">
                                                <span>Op. Gravada</span>
                                                <span x-text="'S/ ' + fmt(opGravadas)"></span>
                                            </div>
                                            <div class="pdv-pago-resumen__fila">
                                                <span>IGV (18%)</span>
                                                <span x-text="'S/ ' + fmt(igv)"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <div class="pdv-pago-resumen__fila pdv-pago-resumen__fila--total">
                                        <span>Total</span>
                                        <span x-text="'S/ ' + fmt(totalConDescuento)"></span>
                                    </div>
                                </div>

                                {{-- Descuento --}}
                                <div class="pdv-pago-descuento-wrap">
                                    <span class="pdv-pago-section__label">Descuento</span>
                                    <div class="pdv-pago-input-wrap pdv-pago-input-wrap--sm">
                                        <span class="pdv-pago-input-wrap__prefix">S/</span>
                                        <input
                                            type="number"
                                            class="pdv-pago-input"
                                            x-model="descuentoInput"
                                            min="0"
                                            step="0.10"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>

                                {{-- Saldo / cambio --}}
                                <div class="pdv-pago-saldo" x-show="pagosAgregados.length > 0" style="display:none">
                                    <div class="pdv-pago-saldo__fila">
                                        <span>Pagado</span>
                                        <span class="pdv-pago-saldo__ok" x-text="'S/ ' + fmt(totalPagado)"></span>
                                    </div>
                                    <div class="pdv-pago-saldo__fila" x-show="saldoRestante > 0.001">
                                        <span>Pendiente</span>
                                        <span class="pdv-pago-saldo__pend" x-text="'S/ ' + fmt(saldoRestante)"></span>
                                    </div>
                                    <div class="pdv-pago-saldo__fila" x-show="saldoRestante <= 0.001">
                                        <span>Cambio</span>
                                        <span class="pdv-pago-saldo__cambio" x-text="'S/ ' + fmt(Math.abs(saldoRestante))"></span>
                                    </div>
                                </div>
                            </div>

                        </div>{{-- /col-der --}}
                    </div>{{-- /layout --}}
                </div>{{-- /body pagos --}}

                {{-- Tab Delivery --}}
                @if(\Filament\Facades\Filament::getTenant()->tieneModulo('despacho') && auth()->user()?->can('tienda.ver'))
                <div class="pdv-pago-section" x-show="activeTab === 'delivery'" style="display:none">
                    <div class="pdv-delivery-form__fields">
                        <div class="pdv-delivery-field">
                            <label class="pdv-delivery-label">Nombre del cliente</label>
                            <input
                                type="text"
                                class="pdv-delivery-input"
                                x-model="deliveryNombre"
                                placeholder="Nombre del cliente"
                                maxlength="200"
                            />
                        </div>
                        <div class="pdv-delivery-field">
                            <label class="pdv-delivery-label">Teléfono</label>
                            <input
                                type="text"
                                class="pdv-delivery-input"
                                x-model="deliveryTelefono"
                                placeholder="Teléfono"
                                maxlength="20"
                            />
                        </div>
                        <div class="pdv-delivery-field pdv-delivery-field--full">
                            <label class="pdv-delivery-label">Dirección</label>
                            <textarea
                                class="pdv-delivery-input"
                                x-model="despachoDireccion"
                                placeholder="Dirección de entrega"
                                maxlength="500"
                                rows="3"
                            ></textarea>
                        </div>
                        <div class="pdv-delivery-field pdv-delivery-field--full">
                            <label class="pdv-delivery-label">Repartidor</label>
                            <input
                                type="text"
                                class="pdv-delivery-input"
                                x-model="deliveryRepartidor"
                                placeholder="Nombre del repartidor"
                                maxlength="200"
                            />
                        </div>
                    </div>
                </div>

                {{-- Tab Despacho --}}
                <div class="pdv-pago-section" x-show="activeTab === 'despacho'" style="display:none">
                    <div class="pdv-despacho-wrap-tab">
                        <p class="pdv-despacho-wrap-tab__hint">Indica la dirección o lugar de envío para esta orden de despacho.</p>
                        <textarea
                            class="pdv-despacho-direccion"
                            x-model="despachoDireccion"
                            placeholder="Dirección o lugar (ej: Agencia Olva, Jr. Lima 123)"
                            maxlength="500"
                            rows="4"
                        ></textarea>
                    </div>
                </div>
                @endif

                <div class="pdv-modal__footer">
                    <button
                        class="pdv-btn-confirmar"
                        :class="{ 'pdv-btn-confirmar--venta': listo }"
                        @click="confirmarVenta()"
                        :disabled="!listo"
                        wire:loading.attr="disabled"
                        wire:target="procesarVenta"
                    >
                        <span wire:loading.remove wire:target="procesarVenta" style="display:contents">
                            <span x-text="listo ? 'Confirmar Venta' : 'Completa el pago para continuar'"></span>
                            <kbd class="pdv-kbd" x-show="listo">Ctrl+↵</kbd>
                        </span>
                        <span wire:loading wire:target="procesarVenta" style="display:none;align-items:center;gap:6px;">
                            <svg class="pdv-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity:.25"/><path fill="currentColor" style="opacity:.75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Procesando...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    @endif


    {{-- ══ MODAL: nuevo cliente rápido (componente compartido) ══ --}}
    <livewire:pdv.nuevo-cliente-modal wire:key="nuevo-cliente-modal" />

    {{-- ══ MODAL: venta completada / impresión (componente compartido) ══ --}}
    <livewire:pdv.venta-completada-modal wire:key="venta-completada-modal" />

<style>
html, body.fi-body { overflow: hidden; }

/* ── Badges de atajos de teclado ── */
.pdv-kbd {
    display: inline-flex;
    align-items: center;
    font-family: ui-monospace, 'Cascadia Code', monospace;
    font-size: 0.62rem;
    font-weight: 600;
    line-height: 1;
    padding: 0.15rem 0.35rem;
    border-radius: 0.25rem;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: inherit;
    opacity: 0.8;
    margin-left: 0.4rem;
    vertical-align: middle;
    pointer-events: none;
    letter-spacing: 0.02em;
    white-space: nowrap;
}
.pdv-kbd--metodo {
    background: rgba(0, 0, 0, 0.06);
    border-color: rgba(0, 0, 0, 0.14);
    color: #6b7280;
    margin-left: auto;
    flex-shrink: 0;
}
.dark .pdv-kbd--metodo {
    background: rgba(255, 255, 255, 0.07);
    border-color: rgba(255, 255, 255, 0.14);
    color: #9ca3af;
}
.pdv-spinner {
    width: 1em;
    height: 1em;
    animation: pdv-spin .7s linear infinite;
    flex-shrink: 0;
}
@keyframes pdv-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>

<script>
window.__pdvInitCarrito = @js($carrito);
document.addEventListener('alpine:init', () => {
    Alpine.store('carritoResumen', @js($this->getCarritoResumen()));
});

// Atajo Ctrl+Enter: abrir modal → agregar pago → confirmar venta
document.addEventListener('keydown', function(ev) {
    if (!ev.ctrlKey || ev.key !== 'Enter') return;

    // Ceder si el modal de venta completada está abierto (tiene sus propios atajos)
    if (document.querySelector('.pdv-modal--impresion')) return;

    var el = ev.target;
    var tag = el.tagName.toLowerCase();

    // No interceptar en textarea (delivery/despacho)
    if (tag === 'textarea') return;

    // No interceptar en controles de items del carrito (edición precio y cantidad)
    if (el.closest('.pdv-item__top') || el.closest('.pdv-item__controles')) return;

    // Prioridad 1: Confirmar venta — saldo cubierto, botón habilitado
    var confirmar = document.querySelector('.pdv-btn-confirmar--venta:not([disabled])');
    if (confirmar) {
        ev.preventDefault();
        confirmar.click();
        return;
    }

    // Prioridad 2: Agregar pago — modal abierto
    var agregar = document.querySelector('.pdv-btn-agregar-pago');
    if (agregar) {
        ev.preventDefault();
        agregar.click();
        return;
    }

    // Prioridad 3: Abrir modal de cobro — carrito con ítems
    var procesar = document.querySelector('.pdv-btn-venta');
    if (procesar) {
        ev.preventDefault();
        procesar.click();
    }
}, true);

// Ctrl+1…9: seleccionar método de pago (solo cuando el modal está abierto)
document.addEventListener('keydown', function(ev) {
    if (!ev.ctrlKey) return;
    var num = parseInt(ev.key);
    if (isNaN(num) || num < 1 || num > 9) return;

    var modal = document.querySelector('.pdv-modal--pago');
    if (!modal) return;

    var btns = modal.querySelectorAll('.pdv-metodo-item');
    var btn = btns[num - 1];
    if (btn) {
        ev.preventDefault();
        btn.click();
    }
}, true);

// Lector de barras físico (USB/Bluetooth) — emula teclado rápido + Enter
(function () {
    var _buf = '';
    var _last = 0;
    // Umbral: si la pausa entre teclas es < 60ms se considera scanner, no usuario
    var THRESHOLD = 60;

    document.addEventListener('keydown', function (ev) {
        // Ignorar si el foco está en un input de texto (evita interferir con búsquedas)
        var tag = (ev.target || document.activeElement).tagName.toLowerCase();
        var tipo = (ev.target || document.activeElement).type || '';
        var isText = tag === 'textarea' || (tag === 'input' && tipo !== 'checkbox' && tipo !== 'radio');

        var now = Date.now();
        var gap = now - _last;
        _last = now;

        if (ev.key === 'Enter') {
            var code = _buf.trim();
            _buf = '';
            // Solo procesar si acumulamos caracteres rápidos (scanner) y no estamos en input
            if (code.length >= 3 && gap < THRESHOLD && !isText) {
                ev.preventDefault();
                ev.stopPropagation();
                window.Livewire.dispatch('pdv-barcode', { code: code });
            }
            return;
        }

        // Acumular solo si el último golpe fue rápido (scanner) o es el primer carácter
        if (gap < THRESHOLD || _buf.length === 0) {
            if (ev.key && ev.key.length === 1) {
                _buf += ev.key;
            }
        } else {
            // Pausa larga → resetear y tratar como escritura manual
            _buf = (ev.key && ev.key.length === 1) ? ev.key : '';
        }
    }, true);
}());
</script>

</x-filament-panels::page>
