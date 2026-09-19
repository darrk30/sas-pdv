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

    <div class="pdv-wrap" x-data="{ carritoOpen: false }" @cerrar-carrito-mobile.window="carritoOpen = false">

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
                    @if($this->getItemCount() > 0)
                        <span class="pdv-carrito__count">{{ $this->getItemCount() }}</span>
                    @endif
                </div>
                <div class="pdv-carrito__header-actions">
                    @if(! empty($carrito))
                        <button class="pdv-carrito__vaciar" wire:click="vaciarCarrito">Vaciar</button>
                    @endif
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
            @if(empty($carrito))
                <div class="pdv-carrito__empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    <p>El carrito está vacío</p>
                    <span>Selecciona productos para comenzar</span>
                </div>
            @else
                <div class="pdv-carrito__lista">
                    @foreach($carrito as $item)
                        @php
                            $esCortesia    = $item['cortesia'] ?? false;
                            $puedeCortesia = $item['puede_cortesia'] ?? false;
                        @endphp
                        <div class="pdv-item {{ $esCortesia ? 'pdv-item--cortesia' : '' }}" wire:key="item-{{ $item['key'] }}">

                            {{-- Cabecera: badges + nombre + precio + botón eliminar --}}
                            <div class="pdv-item__top">
                                <div class="pdv-item__info">
                                    <div class="pdv-item__badges">
                                        @if($item['tipo'] === 'promocion')
                                            <span class="pdv-item__badge-promo">PROMO</span>
                                        @endif
                                        @if($puedeCortesia)
                                            <button
                                                wire:click="toggleCortesia('{{ $item['key'] }}')"
                                                class="pdv-item__badge-cortesia {{ $esCortesia ? 'pdv-item__badge-cortesia--on' : 'pdv-item__badge-cortesia--off' }}"
                                                title="{{ $esCortesia ? 'Quitar cortesía' : 'Aplicar como cortesía (gratis)' }}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="11" height="11">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                                                </svg>
                                                {{ $esCortesia ? 'GRATIS' : 'Cortesía' }}
                                            </button>
                                        @endif
                                    </div>
                                    <p class="pdv-item__nombre">{{ $item['nombre'] }}</p>
                                    <p class="pdv-item__precio-unit">
                                        @if($esCortesia)
                                            <span class="pdv-item__precio-gratis">Gratis</span>
                                        @else
                                            @php $tieneDescuento = isset($item['precio_normal']) && $item['precio_normal'] > $item['precio']; @endphp
                                            @if($tieneDescuento)
                                                <span class="pdv-item__precio-tachado">S/ {{ number_format($item['precio_normal'], 2) }}</span>
                                            @endif
                                            <span
                                                x-data="{ editing: false, val: '{{ number_format($item['precio'], 2, '.', '') }}', saved: '{{ number_format($item['precio'], 2, '.', '') }}' }"
                                                class="pdv-item__precio-editable"
                                                wire:ignore
                                                @pdv-price-fix.window="if ($event.detail.key === '{{ $item['key'] }}') { let p = $event.detail.precio.toFixed(2); saved = p; if (!editing) val = p; }"
                                            >
                                                <span
                                                    x-show="!editing"
                                                    @click="editing = true; $nextTick(() => $refs.inp_{{ $item['key'] }}.select())"
                                                    class="pdv-item__precio-valor {{ $tieneDescuento ? 'pdv-item__precio-valor--oferta' : '' }}"
                                                    title="Toca para editar el precio"
                                                >S/ <span x-text="parseFloat(val).toFixed(2)"></span> c/u</span>
                                                <input
                                                    x-ref="inp_{{ $item['key'] }}"
                                                    x-show="editing"
                                                    x-model="val"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="pdv-item__precio-input"
                                                    @blur="editing = false; $wire.actualizarPrecio('{{ $item['key'] }}', parseFloat(val) || 0)"
                                                    @keydown.enter="$el.blur()"
                                                    @keydown.escape="editing = false; val = saved"
                                                />
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <button class="pdv-item__del" wire:click="eliminarItem('{{ $item['key'] }}')" title="Eliminar">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </div>

                            {{-- Pie: controles de cantidad + subtotal (Alpine, wire:ignore) --}}
                            <div class="pdv-item__controles"
                                 wire:key="ctrl-{{ $item['key'] }}"
                                 wire:ignore
                                 x-data="{
                                     qty: {{ ($item['decimal'] ?? false) ? (float)$item['cantidad'] : (int)$item['cantidad'] }},
                                     precio: {{ (float)$item['precio'] }},
                                     decimal: {{ ($item['decimal'] ?? false) ? 'true' : 'false' }},
                                     sk: '{{ $item['tipo'] }}_{{ $item['id'] }}',
                                     tk: '{{ $item['key'] }}',
                                     detalles: @js($item['detalles_resumen'] ?? []),
                                     stockMax: {{ isset($item['stock_max']) && $item['stock_max'] !== null ? (float)$item['stock_max'] : 'null' }},
                                     ventaSinStock: {{ ($item['venta_sin_stock'] ?? false) ? 'true' : 'false' }},
                                     t: null,
                                     init() {
                                         if (!Alpine.store('carritoTotal')) Alpine.store('carritoTotal', {});
                                         const upd = () => { const n = parseFloat(this.qty); Alpine.store('carritoTotal')[this.tk] = isNaN(n) ? 0 : n * this.precio; };
                                         upd();
                                         this.$watch('qty', upd);
                                         this.$watch('precio', upd);
                                     },
                                     destroy() { const s = Alpine.store('carritoTotal'); if (s) delete s[this.tk]; },
                                     get canInc() { return this.stockMax === null || this.ventaSinStock || this.qty < this.stockMax; },
                                     _updStore(delta) {
                                         let s = Alpine.store('carritoResumen');
                                         if (this.detalles.length) {
                                             if (this.sk in s) s[this.sk] = Math.max(0, s[this.sk] + delta);
                                             this.detalles.forEach(d => {
                                                 let k = d.variante_id ? 'variante_'+d.variante_id : 'producto_'+d.producto_id;
                                                 if (k in s) s[k] = Math.max(0, s[k] + d.cantidad * delta);
                                             });
                                         } else {
                                             if (delta > 0) { if (this.sk in s) s[this.sk]++; }
                                             else { if (this.sk in s && s[this.sk] > 0) s[this.sk]--; }
                                         }
                                     },
                                     inc() {
                                         if (!this.canInc) return;
                                         if (this.decimal) {
                                             this.qty = Math.round((this.qty + 1) * 1000) / 1000;
                                         } else {
                                             this.qty++;
                                         }
                                         this._updStore(1);
                                         this.sync();
                                     },
                                     dec() {
                                         if (this.decimal) {
                                             let next = Math.round((this.qty - 1) * 1000) / 1000;
                                             if (next <= 0) { clearTimeout(this.t); $wire.eliminarItem('{{ $item['key'] }}'); return; }
                                             this.qty = next;
                                         } else {
                                             if (this.qty <= 1) { clearTimeout(this.t); $wire.eliminarItem('{{ $item['key'] }}'); return; }
                                             this.qty--;
                                         }
                                         this._updStore(-1);
                                         this.sync();
                                     },
                                     sync() { clearTimeout(this.t); this.t = setTimeout(() => $wire.actualizarCantidad('{{ $item['key'] }}', Math.max(0.001, this.qty)), 350); }
                                 }"
                                 @pdv-qty-fix.window="if ($event.detail.key === '{{ $item['key'] }}') qty = decimal ? $event.detail.qty : Math.round($event.detail.qty)"
                                 @pdv-price-fix.window="if ($event.detail.key === '{{ $item['key'] }}') precio = $event.detail.precio"
                            >
                                <div class="pdv-item__foot">
                                    @if($item['decimal'] ?? false)
                                        <div class="pdv-qty pdv-qty--decimal">
                                            <button class="pdv-qty__btn pdv-qty__btn--menos" @click="dec()">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                            </button>
                                            <input
                                                type="text"
                                                inputmode="decimal"
                                                x-model="qty"
                                                class="pdv-qty__decimal-input"
                                                @keydown.enter="$el.blur()"
                                                @keydown="if($event.key==='-'||$event.key==='e'||$event.key==='E')$event.preventDefault()"
                                                @input="$event.target.value=$event.target.value.replace(/[^0-9.]/g,'').replace(/^(\d*\.?\d*).*$/,'$1')"
                                                @blur="qty=Math.max(0.001,parseFloat($event.target.value)||0.001); $event.target.value=qty; sync()"
                                            />
                                            <button class="pdv-qty__btn pdv-qty__btn--mas" @click="inc()" :disabled="!canInc" :class="{'pdv-qty__btn--disabled': !canInc}">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                            </button>
                                        </div>
                                    @else
                                        <div class="pdv-qty">
                                            <button class="pdv-qty__btn pdv-qty__btn--menos" @click="dec()">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                            </button>
                                            @if($item['tipo'] === 'promocion')
                                                <input type="number" min="1" step="1" x-model.number="qty" class="pdv-qty__decimal-input"
                                                    @blur="sync()"
                                                    @keydown.enter="$el.blur()"
                                                    @keydown="if($event.key==='-'||$event.key==='e'||$event.key==='E')$event.preventDefault()"
                                                    @input="if(parseInt($event.target.value)<1)$event.target.value=1">
                                            @else
                                                <span class="pdv-qty__num" x-text="qty"></span>
                                            @endif
                                            <button class="pdv-qty__btn pdv-qty__btn--mas" @click="inc()" :disabled="!canInc" :class="{'pdv-qty__btn--disabled': !canInc}">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                            </button>
                                        </div>
                                    @endif
                                    <span class="pdv-item__subtotal {{ $esCortesia ? 'pdv-item__subtotal--gratis' : '' }}"
                                          x-text="'S/ ' + (qty * precio).toFixed(2)"></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pdv-carrito__footer"
                     x-data="{ get total() { const s = Alpine.store('carritoTotal'); return s ? Object.values(s).reduce((a,v) => a+v, 0) : {{ (float)$this->getTotal() }}; } }"
                >
                    <div class="pdv-carrito__totales">
                        <div class="pdv-carrito__fila">
                            <span class="pdv-carrito__label">{{ $this->getItemCount() }} ítems</span>
                            <span class="pdv-carrito__sublabel">Subtotal</span>
                        </div>
                        <div class="pdv-carrito__fila">
                            <span class="pdv-carrito__total-label">Total</span>
                            <span class="pdv-carrito__total-monto" x-text="'S/ ' + total.toFixed(2)"></span>
                        </div>
                    </div>
                    <button class="pdv-btn-venta" wire:click="abrirModalPago">Procesar Venta</button>
                </div>
            @endif

        </div>{{-- /pdv-carrito --}}

        {{-- ══ FAB carrito (solo mobile) ══ --}}
        <button class="pdv-fab" @click="carritoOpen = true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
            </svg>
            @if($this->getItemCount() > 0)
                <span class="pdv-fab__badge">{{ $this->getItemCount() }}</span>
            @endif
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
        @php $metodoActivo = collect($metodosPagoDisponibles)->firstWhere('id', $metodoPagoId); @endphp
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
                     deliveryRepartidor: ''
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
                                @if(empty($metodosPagoDisponibles))
                                    <p class="pdv-pago-empty">No hay métodos de pago configurados</p>
                                @else
                                    <div class="pdv-metodos-lista">
                                        @foreach($metodosPagoDisponibles as $metodo)
                                            <button
                                                class="pdv-metodo-item {{ $metodoPagoId === $metodo['id'] ? 'pdv-metodo-item--activo' : '' }}"
                                                wire:click="seleccionarMetodoPago({{ $metodo['id'] }})"
                                            >
                                                @if($metodo['imagen'])
                                                    <img class="pdv-metodo-item__img" src="{{ \Illuminate\Support\Facades\Storage::url($metodo['imagen']) }}" alt="{{ $metodo['nombre'] }}"/>
                                                @else
                                                    <div class="pdv-metodo-item__avatar">{{ strtoupper(mb_substr($metodo['nombre'], 0, 1)) }}</div>
                                                @endif
                                                <span class="pdv-metodo-item__nombre">{{ $metodo['nombre'] }}</span>
                                                @if($metodoPagoId === $metodo['id'])
                                                    <svg class="pdv-metodo-item__check" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                                    </svg>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                    @if($metodoActivo && $metodoActivo['requiere_referencia'])
                                        <div class="pdv-pago-referencia">
                                            <input
                                                type="text"
                                                class="pdv-field__input"
                                                wire:model.live="pagoReferencia"
                                                placeholder="Referencia / N° operación"
                                            />
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- ══ COLUMNA DERECHA ══ --}}
                        <div class="pdv-pago-col-der">

                            {{-- ── Monto y botones rápidos ── --}}
                            <div class="pdv-pago-section">
                                <p class="pdv-pago-section__label">Monto a pagar</p>
                                <div class="pdv-quick-btns">
                                    <button class="pdv-quick-btn pdv-quick-btn--exacto" wire:click="setMontoExacto">Exacto</button>
                                    <button class="pdv-quick-btn" wire:click="ajustarMonto(200)">+200</button>
                                    <button class="pdv-quick-btn" wire:click="ajustarMonto(100)">+100</button>
                                    <button class="pdv-quick-btn" wire:click="ajustarMonto(50)">+50</button>
                                    <button class="pdv-quick-btn" wire:click="ajustarMonto(20)">+20</button>
                                    <button class="pdv-quick-btn" wire:click="ajustarMonto(10)">+10</button>
                                </div>
                                <div class="pdv-pago-monto-row">
                                    <div class="pdv-pago-input-wrap" style="flex:1">
                                        <span class="pdv-pago-input-wrap__prefix">S/</span>
                                        <input
                                            type="number"
                                            class="pdv-pago-input"
                                            wire:model.live="montoPagoInput"
                                            min="0"
                                            step="0.10"
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <button class="pdv-btn-agregar-pago" wire:click="agregarPago">
                                        Agregar
                                    </button>
                                </div>
                            </div>

                            {{-- ── Pagos registrados ── --}}
                            @if(! empty($pagosAgregados))
                                <div class="pdv-pago-section">
                                    <p class="pdv-pago-section__label">Pagos registrados</p>
                                    <div class="pdv-pagos-lista">
                                        @foreach($pagosAgregados as $idx => $pago)
                                            <div class="pdv-pago-item" wire:key="pago-{{ $idx }}">
                                                <div class="pdv-pago-item__info">
                                                    <span class="pdv-pago-item__nombre">
                                                        {{ $pago['nombre'] }}
                                                        @if(($pago['condicion_pago'] ?? 'contado') === 'credito')
                                                            <span class="pdv-credito-badge">Crédito</span>
                                                        @endif
                                                    </span>
                                                    @if($pago['referencia'])
                                                        <span class="pdv-pago-item__ref">{{ $pago['referencia'] }}</span>
                                                    @endif
                                                </div>
                                                <span class="pdv-pago-item__monto">S/ {{ number_format($pago['monto'], 2) }}</span>
                                                <button class="pdv-pago-item__del" wire:click="eliminarPago({{ $idx }})">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

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
                                    @if($tipoComprobante !== 'ticket')
                                        <div class="pdv-pago-resumen__fila">
                                            <span>Op. Gravada</span>
                                            <span>S/ {{ number_format($this->getOpGravadas(), 2) }}</span>
                                        </div>
                                        <div class="pdv-pago-resumen__fila">
                                            <span>IGV (18%)</span>
                                            <span>S/ {{ number_format($this->getIgv(), 2) }}</span>
                                        </div>
                                    @endif
                                    <div class="pdv-pago-resumen__fila pdv-pago-resumen__fila--total">
                                        <span>Total</span>
                                        <span>S/ {{ number_format($this->getTotalConDescuento(), 2) }}</span>
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
                                            wire:model.live="descuentoInput"
                                            min="0"
                                            step="0.10"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>

                                {{-- Saldo / cambio --}}
                                @if(! empty($pagosAgregados))
                                    <div class="pdv-pago-saldo">
                                        <div class="pdv-pago-saldo__fila">
                                            <span>Pagado</span>
                                            <span class="pdv-pago-saldo__ok">S/ {{ number_format($this->getTotalPagado(), 2) }}</span>
                                        </div>
                                        @if($this->getSaldoRestante() > 0)
                                            <div class="pdv-pago-saldo__fila">
                                                <span>Pendiente</span>
                                                <span class="pdv-pago-saldo__pend">S/ {{ number_format($this->getSaldoRestante(), 2) }}</span>
                                            </div>
                                        @else
                                            <div class="pdv-pago-saldo__fila">
                                                <span>Cambio</span>
                                                <span class="pdv-pago-saldo__cambio">S/ {{ number_format(abs($this->getSaldoRestante()), 2) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
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
                    @php $listo = ($this->getSaldoRestante() <= 0.01 && ! empty($pagosAgregados)) || $this->totalEsCero(); @endphp
                    <button
                        class="pdv-btn-confirmar {{ $listo ? 'pdv-btn-confirmar--venta' : '' }}"
                        @click="
                            $wire.deliveryActivo       = deliveryActivo;
                            $wire.despachoRequerido    = despachoRequerido;
                            $wire.despachoDireccion    = despachoDireccion;
                            $wire.deliveryNombre       = deliveryActivo ? deliveryNombre    : '';
                            $wire.deliveryTelefono     = deliveryActivo ? deliveryTelefono  : '';
                            $wire.deliveryRepartidor   = deliveryActivo ? deliveryRepartidor : '';
                            $wire.procesarVenta()
                        "
                        @if(! $listo) disabled @endif
                        wire:loading.attr="disabled"
                        wire:target="procesarVenta"
                    >
                        <span wire:loading.remove wire:target="procesarVenta">
                            {{ $listo ? 'Confirmar Venta' : 'Completa el pago para continuar' }}
                        </span>
                        <span wire:loading wire:target="procesarVenta">Procesando...</span>
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
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('carritoResumen', @js($this->getCarritoResumen()));
});
</script>

</x-filament-panels::page>
