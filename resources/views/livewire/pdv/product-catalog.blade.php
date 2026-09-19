<div class="pc-root">

    {{-- ── Buscador ─────────────────────────────────────────────────── --}}
    <div class="pdv-busqueda">
        <svg class="pdv-busqueda__icono" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
        </svg>
        <input
            type="text"
            class="pdv-busqueda__input pdv-busqueda__input--con-scan"
            wire:model.live.debounce.300ms="busqueda"
            placeholder="Buscar por nombre, código o barras..."
        />
        @if($busqueda)
            <button class="pdv-busqueda__clear pdv-busqueda__clear--con-scan" wire:click="limpiarBusqueda">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif
        <button type="button" class="pdv-busqueda__scan"
            onclick="window.dispatchEvent(new CustomEvent('open-barcode-scanner', { detail: { path: '__pdv__' } }))"
            title="Escanear código de barras">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
        </button>
    </div>

    {{-- ── Categorías ───────────────────────────────────────────────── --}}
    <div
        class="pdv-categorias-wrap"
        x-data="{
            canLeft:  false,
            canRight: false,
            dragging: false,
            startX:   0,
            startLeft: 0,
            update() {
                const el = this.$refs.scroll;
                this.canLeft  = el.scrollLeft > 2;
                this.canRight = el.scrollLeft < el.scrollWidth - el.clientWidth - 2;
            },
            slide(dir) {
                this.$refs.scroll.scrollBy({ left: dir * 140, behavior: 'smooth' });
            },
            dragStart(e) {
                if (e.button !== 0) return;
                this.dragging  = true;
                this.startX    = e.pageX;
                this.startLeft = this.$refs.scroll.scrollLeft;
                e.preventDefault();
            },
            dragMove(e) {
                if (!this.dragging) return;
                this.$refs.scroll.scrollLeft = this.startLeft - (e.pageX - this.startX);
            },
            dragEnd() { this.dragging = false; }
        }"
        x-init="update()"
        @mousemove.window="dragMove($event)"
        @mouseup.window="dragEnd()"
        @mouseleave.window="dragEnd()"
    >
        {{-- Flecha izquierda (solo escritorio) --}}
        <button
            type="button"
            class="pdv-cat-arrow pdv-cat-arrow--left"
            x-show="canLeft"
            @click="slide(-1)"
            tabindex="-1"
            aria-hidden="true"
            style="display:none"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
            </svg>
        </button>

        <div
            class="pdv-categorias"
            x-ref="scroll"
            @scroll="update()"
            @mousedown="dragStart($event)"
            :class="dragging ? 'pdv-categorias--drag' : ''"
        >
            <button class="pdv-cat-btn {{ $categoriaId === null ? 'pdv-cat-btn--activo' : '' }}" wire:click="seleccionarCategoria(null)">
                Todos
            </button>
            @if($hayPromociones)
                <button class="pdv-cat-btn pdv-cat-btn--promo {{ $categoriaId === -1 ? 'pdv-cat-btn--activo' : '' }}" wire:click="seleccionarCategoria(-1)">
                    Promos
                </button>
            @endif
            @foreach($categorias as $cat)
                <button class="pdv-cat-btn {{ $categoriaId === $cat->id ? 'pdv-cat-btn--activo' : '' }}" wire:click="seleccionarCategoria({{ $cat->id }})">
                    {{ $cat->nombre }}
                </button>
            @endforeach
        </div>

        {{-- Flecha derecha (solo escritorio) --}}
        <button
            type="button"
            class="pdv-cat-arrow pdv-cat-arrow--right"
            x-show="canRight"
            @click="slide(1)"
            tabindex="-1"
            aria-hidden="true"
            style="display:none"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </button>
    </div>

    {{-- ── Grid de productos / promociones ───────────────────────────── --}}
    <div class="pdv-grid">

        {{-- Promociones (solo cuando categoriaId === -1) --}}
        @if($promociones->isNotEmpty())
            <div class="pdv-items-grid">
                @foreach($promociones as $promo)
                    @php
                        $stockBase     = $promo->stockPredictivo();
                        $detallesVista = $promo->detalles->map(function ($d) {
                            $nombre = $d->variante?->producto?->nombre ?? $d->producto?->nombre ?? '—';
                            if ($d->variante_id && $d->variante) {
                                $vals = $d->variante->valores->map(fn($pav) => $pav->valor?->nombre)->filter()->join(' / ');
                                if ($vals) $nombre .= " ($vals)";
                            }
                            return ['nombre' => $nombre, 'cantidad' => $d->cantidad ?? 1];
                        })->values()->all();
                    @endphp
                    <button
                        class="pdv-card pdv-card--promo {{ $stockBase === 0 ? 'pdv-card--agotada' : '' }}"
                        wire:key="promo-card-{{ $promo->id }}-{{ (int)($stockBase ?? -1) }}"
                        x-data="{
                            base: {{ $stockBase !== null ? (int) $stockBase : 'null' }},
                            get ic() { return (Alpine.store('carritoResumen')||{})['promocion_{{ $promo->id }}']||0; },
                            get ds() { return this.base === null ? null : Math.max(0, this.base - this.ic); }
                        }"
                        :class="{ 'pdv-card--agotada': ds !== null && ds <= 0 }"
                        :disabled="ds !== null && ds <= 0"
                        wire:click="seleccionarPromocion({{ $promo->id }})"
                        {{ $stockBase === 0 ? 'disabled' : '' }}
                    >
                        <div class="pdv-card__img-wrap">
                            @if($promo->imagen)
                                <img class="pdv-card__img" src="{{ \Illuminate\Support\Facades\Storage::url($promo->imagen) }}" alt="{{ $promo->nombre }}"/>
                            @else
                                <div class="pdv-card__avatar-grande pdv-card__avatar-grande--promo">{{ strtoupper(mb_substr($promo->nombre, 0, 1)) }}</div>
                            @endif
                            <span class="pdv-card__badge pdv-card__badge--promo">PROMO</span>
                            @if($stockBase !== null)
                                <span
                                    class="pdv-card__stock-badge"
                                    :class="ds <= 0 ? 'pdv-card__badge-stock--agotada' : (ds <= 5 ? 'pdv-card__stock-badge--bajo' : 'pdv-card__stock-badge--ok')"
                                    x-text="ds <= 0 ? 'Agotada' : 'Stock ' + ds"
                                >{{ $stockBase === 0 ? 'Agotada' : 'Stock ' . $stockBase }}</span>
                            @endif
                        </div>
                        <div class="pdv-card__body">
                            <p class="pdv-card__nombre">{{ $promo->nombre }}</p>
                            <p class="pdv-card__meta">{{ $promo->detalles_count }} productos</p>
                            <div class="pdv-card__precio-fila">
                                <p class="pdv-card__precio">S/ {{ number_format($promo->precio, 2) }}</p>
                                <x-pdv.promo-vista :detalles="$detallesVista" clase="promo-vista--catalog" />
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif

        @if($productos->isNotEmpty())
            <div class="pdv-items-grid" id="pc-productos-grid">
                @foreach($productos as $producto)
                    @php
                        $tieneVariantes = $producto->variantesActivas->isNotEmpty();
                        $simbolo        = $producto->unidadMedida?->simbolo;

                        $stockSimple    = ! $tieneVariantes && $producto->control_de_stock
                            ? (float)($producto->inventario?->stock_reserva ?? 0) : null;
                        $stockVariantes = $tieneVariantes && $producto->control_de_stock
                            ? $producto->variantesActivas->sum(fn($v) => (float)($v->inventario?->stock_reserva ?? 0)) : null;
                        $stock          = $stockSimple ?? $stockVariantes;

                        // Claves del carrito para el badge Alpine
                        $cartKeys = $tieneVariantes
                            ? $producto->variantesActivas->map(fn($v) => "variante_{$v->id}")->values()->toArray()
                            : ["producto_{$producto->id}"];

                        // dbStock = raw stock_reserva; Alpine store handles the real-time delta
                        // (no PHP adjustment — that would double-subtract with the Alpine pending)
                        $stockVisible = $stock;

                        $agotado    = $producto->control_de_stock
                            && ! $producto->venta_sin_stock
                            && $stockVisible !== null
                            && $stockVisible <= 0;
                        $stockNivel = $stockVisible === null ? null : ($stockVisible <= 0 ? 'agotado' : ($stockVisible <= 5 ? 'bajo' : 'ok'));

                        // Para quick-add: precompute precio y es_decimal (sin variantes, sin cortesia)
                        $precioNorm  = (float) $producto->precio_venta;
                        $precioFinal = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
                            ? (float) $producto->precio_con_descuento
                            : $precioNorm;
                        $esDecimal   = $producto->unidadMedida?->esContinua() ?? false;
                        $quickAdd    = ! $tieneVariantes && ! $producto->es_cortesia;

                        // Listas de precios activas del producto (solo si el plan y el permiso lo permiten)
                        $listasPrecios = $this->featureListaPrecios
                            ? $producto->preciosLista
                                ->filter(fn($p) => $p->lista?->activa)
                                ->map(fn($p) => ['id' => $p->lista_precio_id, 'nombre' => $p->lista->nombre, 'precio' => (float) $p->precio])
                                ->values()
                                ->toArray()
                            : [];
                        $tieneListasPrecios = count($listasPrecios) > 0;
                    @endphp
                    <button
                        class="pdv-card {{ $agotado ? 'pdv-card--agotado' : '' }}"
                        @if($tieneListasPrecios)
                        x-data="{
                            modalLista: false,
                            listas: @js($listasPrecios),
                            precioBase: {{ $precioFinal }},
                            precioNorm: {{ $precioNorm }},
                            precioActual: {{ $precioFinal }},
                            listaActiva: null,
                            seleccionarLista(lista) {
                                this.precioActual = lista.precio;
                                this.listaActiva = lista.id;
                                this.modalLista = false;
                            },
                            resetearLista() {
                                this.precioActual = this.precioBase;
                                this.listaActiva = null;
                            }
                        }"
                        @if($tieneVariantes)
                            @click="$wire.abrirModalProducto({{ $producto->id }}, listaActiva ? precioActual : null)"
                        @else
                            @click="$dispatch('product-selected', [{ tipo: 'producto', id: {{ $producto->id }}, nombre: '{{ addslashes($producto->nombre) }}', precio: precioActual, precio_normal: precioNorm, es_cortesia: false, cantidad: 1, producto_id: {{ $producto->id }}, puede_cortesia: false, es_decimal: {{ $esDecimal ? 'true' : 'false' }}, stock_max: {{ $stockVisible !== null ? (float)$stockVisible : 'null' }}, venta_sin_stock: {{ $producto->venta_sin_stock ? 'true' : 'false' }} }])"
                        @endif
                        @elseif($quickAdd)
                            @click="$dispatch('product-selected', [{ tipo: 'producto', id: {{ $producto->id }}, nombre: '{{ addslashes($producto->nombre) }}', precio: {{ $precioFinal }}, precio_normal: {{ $precioNorm }}, es_cortesia: false, cantidad: 1, producto_id: {{ $producto->id }}, puede_cortesia: false, es_decimal: {{ $esDecimal ? 'true' : 'false' }}, stock_max: {{ $stockVisible !== null ? (float)$stockVisible : 'null' }}, venta_sin_stock: {{ $producto->venta_sin_stock ? 'true' : 'false' }} }])"
                        @else
                            wire:click="abrirModalProducto({{ $producto->id }})"
                        @endif
                        @if($agotado) disabled @endif
                    >
                        <div class="pdv-card__img-wrap">
                            @if($producto->logo)
                                <img class="pdv-card__img" src="{{ \Illuminate\Support\Facades\Storage::url($producto->logo) }}" alt="{{ $producto->nombre }}"/>
                            @else
                                <div class="pdv-card__avatar-grande">{{ strtoupper(mb_substr($producto->nombre, 0, 1)) }}</div>
                            @endif
                            @if($agotado)
                                <div class="pdv-card__agotado-overlay"><span>AGOTADO</span></div>
                            @endif
                            <span class="pdv-card__badge pdv-card__badge--carrito"
                                x-data="{
                                    keys: @js($cartKeys),
                                    get qty() {
                                        const r = Alpine.store('carritoResumen') || {};
                                        return this.keys.reduce((s, k) => s + (r[k] || 0), 0);
                                    }
                                }"
                                x-show="qty > 0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:.6rem;height:.6rem;display:inline;vertical-align:-.05em;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                                </svg>
                                <span x-text="qty % 1 === 0 ? Math.round(qty) : qty.toFixed(2)"></span>
                            </span>
                            @if($producto->es_cortesia)
                                <span class="pdv-card__badge pdv-card__badge--cortesia">CORTESÍA</span>
                            @endif
                            @if($stockNivel !== null && ! $agotado)
                                <span
                                    class="pdv-card__stock-badge"
                                    wire:key="sb-{{ $producto->id }}-{{ (int)floor($stockVisible) }}"
                                    x-data="{
                                        dbStock: {{ $stockVisible }},
                                        keys: @js($cartKeys),
                                        get pending() {
                                            const r = Alpine.store('carritoResumen') || {};
                                            return this.keys.reduce((s, k) => s + (r[k] || 0), 0);
                                        },
                                        get display() { return Math.max(0, this.dbStock - this.pending); },
                                        get nivel() { const s = this.display; return s <= 0 ? 'agotado' : s <= 5 ? 'bajo' : 'ok'; }
                                    }"
                                    :class="'pdv-card__stock-badge--' + nivel"
                                ><span x-text="display % 1 === 0 ? Math.round(display).toString() : display.toFixed(2)"></span>@if($simbolo)<small class="pdv-card__stock-unit"> {{ $simbolo }}</small>@endif</span>
                            @endif
                        </div>
                        <div class="pdv-card__body">
                            <p class="pdv-card__nombre">{{ $producto->nombre }}</p>
                            @if($tieneVariantes)
                                <p class="pdv-card__meta">{{ $producto->variantesActivas->count() }} variantes</p>
                            @endif
                            @if(!$tieneVariantes && $producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
                                <p class="pdv-card__precio-original">S/ {{ number_format($producto->precio_venta, 2) }}</p>
                                @if($tieneListasPrecios)
                                    <div class="pdv-card__precio-fila">
                                        <p class="pdv-card__precio pdv-card__precio--oferta" x-text="'S/ ' + precioActual.toFixed(2)" style="margin:0"></p>
                                        <div class="pdv-card__lista-controles" @click.stop>
                                            <div class="pdv-card__lista-reset" x-show="listaActiva !== null" @click="resetearLista()" title="Quitar lista"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg></div>
                                            <div class="pdv-card__lista-btn" :class="listaActiva !== null ? 'pdv-card__lista-btn--activa' : ''" @click="modalLista = true" title="Lista de precios"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 4.75A.75.75 0 0 1 6.75 4h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 4.75ZM6 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 10Zm0 5.25a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75ZM1.99 4.75a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1v-.01ZM1.99 15.25a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1v-.01ZM1.99 10a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1V10Z" clip-rule="evenodd"/></svg></div>
                                            <div class="pdv-lista-backdrop" x-show="modalLista" x-cloak @click="modalLista = false"></div>
                                            <div class="pdv-lista-popup" x-show="modalLista" x-cloak @click.stop>
                                                <div class="pdv-lista-popup__header"><span>Lista de precios</span><div class="pdv-lista-popup__cerrar" @click="modalLista = false"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg></div></div>
                                                <ul class="pdv-lista-popup__lista"><template x-for="lista in listas" :key="lista.id"><li><div class="pdv-lista-popup__opcion" :class="listaActiva === lista.id ? 'pdv-lista-popup__opcion--activa' : ''" @click="seleccionarLista(lista)"><span x-text="lista.nombre" class="pdv-lista-popup__nombre"></span><span x-text="'S/ ' + lista.precio.toFixed(2)" class="pdv-lista-popup__precio"></span></div></li></template></ul>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <p class="pdv-card__precio pdv-card__precio--oferta">S/ {{ number_format($producto->precio_con_descuento, 2) }}</p>
                                @endif
                            @else
                                @if($tieneVariantes && $tieneListasPrecios)
                                    <div class="pdv-card__precio-fila">
                                        <p class="pdv-card__precio" x-show="!listaActiva" style="margin:0">Desde S/ {{ number_format($producto->variantesActivas->min('precio_final'), 2) }}</p>
                                        <p class="pdv-card__precio" x-show="listaActiva" x-cloak x-text="'S/ ' + precioActual.toFixed(2)" style="margin:0"></p>
                                        <div class="pdv-card__lista-controles" @click.stop>
                                            <div class="pdv-card__lista-reset" x-show="listaActiva !== null" @click="resetearLista()" title="Quitar lista"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg></div>
                                            <div class="pdv-card__lista-btn" :class="listaActiva !== null ? 'pdv-card__lista-btn--activa' : ''" @click="modalLista = true" title="Lista de precios"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 4.75A.75.75 0 0 1 6.75 4h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 4.75ZM6 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 10Zm0 5.25a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75ZM1.99 4.75a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1v-.01ZM1.99 15.25a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1v-.01ZM1.99 10a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1V10Z" clip-rule="evenodd"/></svg></div>
                                            <div class="pdv-lista-backdrop" x-show="modalLista" x-cloak @click="modalLista = false"></div>
                                            <div class="pdv-lista-popup" x-show="modalLista" x-cloak @click.stop>
                                                <div class="pdv-lista-popup__header"><span>Lista de precios</span><div class="pdv-lista-popup__cerrar" @click="modalLista = false"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg></div></div>
                                                <ul class="pdv-lista-popup__lista"><template x-for="lista in listas" :key="lista.id"><li><div class="pdv-lista-popup__opcion" :class="listaActiva === lista.id ? 'pdv-lista-popup__opcion--activa' : ''" @click="seleccionarLista(lista)"><span x-text="lista.nombre" class="pdv-lista-popup__nombre"></span><span x-text="'S/ ' + lista.precio.toFixed(2)" class="pdv-lista-popup__precio"></span></div></li></template></ul>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($tieneVariantes)
                                    <p class="pdv-card__precio">Desde S/ {{ number_format($producto->variantesActivas->min('precio_final'), 2) }}</p>
                                @elseif($tieneListasPrecios)
                                    <div class="pdv-card__precio-fila">
                                        <p class="pdv-card__precio" x-text="'S/ ' + precioActual.toFixed(2)" style="margin:0"></p>
                                        <div class="pdv-card__lista-controles" @click.stop>
                                            <div class="pdv-card__lista-reset" x-show="listaActiva !== null" @click="resetearLista()" title="Quitar lista"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg></div>
                                            <div class="pdv-card__lista-btn" :class="listaActiva !== null ? 'pdv-card__lista-btn--activa' : ''" @click="modalLista = true" title="Lista de precios"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 4.75A.75.75 0 0 1 6.75 4h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 4.75ZM6 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75A.75.75 0 0 1 6 10Zm0 5.25a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75ZM1.99 4.75a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1v-.01ZM1.99 15.25a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1v-.01ZM1.99 10a1 1 0 0 1 1-1H3a1 1 0 0 1 1 1v.01a1 1 0 0 1-1 1h-.01a1 1 0 0 1-1-1V10Z" clip-rule="evenodd"/></svg></div>
                                            <div class="pdv-lista-backdrop" x-show="modalLista" x-cloak @click="modalLista = false"></div>
                                            <div class="pdv-lista-popup" x-show="modalLista" x-cloak @click.stop>
                                                <div class="pdv-lista-popup__header"><span>Lista de precios</span><div class="pdv-lista-popup__cerrar" @click="modalLista = false"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg></div></div>
                                                <ul class="pdv-lista-popup__lista"><template x-for="lista in listas" :key="lista.id"><li><div class="pdv-lista-popup__opcion" :class="listaActiva === lista.id ? 'pdv-lista-popup__opcion--activa' : ''" @click="seleccionarLista(lista)"><span x-text="lista.nombre" class="pdv-lista-popup__nombre"></span><span x-text="'S/ ' + lista.precio.toFixed(2)" class="pdv-lista-popup__precio"></span></div></li></template></ul>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <p class="pdv-card__precio">S/ {{ number_format($producto->precio_venta, 2) }}</p>
                                @endif
                            @endif
                        </div>
                    </button>

                @endforeach
            </div>

            @if($productos->count() >= $perPage)
                <div
                    wire:key="pc-sentinel-{{ $perPage }}"
                    x-intersect.margin.300px="$wire.cargarMas()"
                    class="pdv-sentinel"
                ></div>
            @endif
        @elseif($promociones->isEmpty())
            <div class="pdv-vacio">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <p>No se encontraron productos</p>
                @if($busqueda)
                    <button class="pdv-vacio__link" wire:click="limpiarBusqueda">Limpiar búsqueda</button>
                @endif
            </div>
        @endif
    </div>

    {{-- ── Modal variantes ─────────────────────────────────────────────── --}}
    @if($modalAbierto)
    <div class="pdv-overlay" x-data="{
            cantidad: {{ $modalCantidad }},
            esCortesia: {{ $modalCortesia ? 'true' : 'false' }},
            puedeCortesia: {{ $productoEsCortesia ? 'true' : 'false' }},
            esDecimal: {{ $productoEsDecimal ? 'true' : 'false' }},
        }">
        <div class="pdv-overlay__backdrop" wire:click="cerrarModal"></div>
        <div class="pdv-modal">

            {{-- Header --}}
            <div class="pdv-modal__header">
                <h3 class="pdv-modal__titulo">{{ $productoModalNombre }}</h3>
                <button class="pdv-modal__cerrar" wire:click="cerrarModal">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body scrollable --}}
            <div class="pdv-modal__body">

                {{-- Precio + Cortesía --}}
                @php
                    // Buscar stock de la variante según los atributos ya seleccionados
                    $stockVarianteModal = null;
                    if ($productoControlStock && count($atributosModal) > 0 && count($seleccionados) >= count($atributosModal)) {
                        $selectedPavIds = array_values($seleccionados);
                        foreach ($variantesInfo as $vInfo) {
                            if (count($vInfo['pav_ids']) === count($selectedPavIds)
                                && empty(array_diff($selectedPavIds, $vInfo['pav_ids']))) {
                                $stockVarianteModal = (float) $vInfo['stock'];
                                break;
                            }
                        }
                    }
                    $stockNivelModal = $stockVarianteModal === null ? null
                        : ($stockVarianteModal <= 0
                            ? ($productoVentaSinStock ? 'sin-stock' : 'agotado')
                            : ($stockVarianteModal <= 5 ? 'bajo' : 'ok'));
                @endphp
                <div class="pdv-modal__precio-row">
                    <div>
                        <p class="pdv-modal__precio-label">Precio</p>
                        <p class="pdv-modal__precio-total" x-text="'S/ ' + ({{ $precioBase }} + {{ $precioAdicionalTotal }}).toFixed(2)"
                            :class="esCortesia ? 'pdv-modal__precio-gratis' : ''"></p>
                        @if($precioBase < $precioBaseOriginal)
                            <p class="pdv-modal__precio-detalle" style="text-decoration:line-through;">S/ {{ number_format($precioBaseOriginal + $precioAdicionalTotal, 2) }}</p>
                        @endif
                    </div>
                    <div class="pdv-modal__precio-right">
                        @if($stockVarianteModal !== null)
                            <span class="pdv-modal__stock-badge pdv-modal__stock-badge--{{ $stockNivelModal }}">
                                @if($stockNivelModal === 'agotado')
                                    Agotado
                                @elseif($stockNivelModal === 'sin-stock')
                                    Sin stock
                                @else
                                    Stock {{ $productoEsDecimal ? number_format($stockVarianteModal, 2) : (int) $stockVarianteModal }} {{ $productoUnidadSimbolo ?: 'unid.' }}
                                @endif
                            </span>
                        @endif
                        <template x-if="puedeCortesia">
                            <button
                                type="button"
                                class="pdv-modal__cortesia-btn"
                                :class="esCortesia ? 'pdv-modal__cortesia-btn--on' : ''"
                                @click="esCortesia = !esCortesia"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:.8rem;height:.8rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                                </svg>
                                Cortesía
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Atributos --}}
                @foreach($atributosModal as $atributo)
                <div>
                    <p class="pdv-atributo__label">
                        {{ $atributo['nombre'] }}
                        <span class="pdv-atributo__requerido">requerido</span>
                    </p>
                    <div class="pdv-atributo__opciones">
                        @foreach($atributo['valores'] as $valor)
                            @php
                                $seleccionado  = isset($seleccionados[$atributo['id']]) && (int)$seleccionados[$atributo['id']] === (int)$valor['id'];
                                $deshabilitado = in_array((int)$valor['id'], $valoresDeshabilitados);
                            @endphp
                            <button
                                type="button"
                                class="pdv-valor-btn {{ $seleccionado ? 'pdv-valor-btn--activo' : '' }}"
                                wire:click="seleccionarValor({{ $atributo['id'] }}, {{ $valor['id'] }})"
                                @if($deshabilitado) disabled style="opacity:.35;cursor:not-allowed;" @endif
                            >
                                {{ $valor['nombre'] }}
                                @if($valor['precio_adicional'] > 0)
                                    <span class="pdv-valor-btn__extra">+{{ number_format($valor['precio_adicional'], 2) }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
                @endforeach

            </div>{{-- /body --}}

            {{-- Footer: cantidad + confirmar --}}
            <div class="pdv-modal__footer">
                <div class="pdv-modal__qty-row">
                    <span class="pdv-modal__qty-label">Cantidad</span>
                    <div class="pdv-modal__qty">
                        <button type="button" class="pdv-qty__btn pdv-qty__btn--menos"
                            @click="cantidad = esDecimal ? Math.max(0.1, +(cantidad - 0.1).toFixed(3)) : Math.max(1, cantidad - 1)">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                        </button>
                        <input
                            type="number"
                            class="pdv-modal__qty-input"
                            x-model.number="cantidad"
                            :min="esDecimal ? 0.001 : 1"
                            :step="esDecimal ? 0.1 : 1"
                        />
                        <button type="button" class="pdv-qty__btn pdv-qty__btn--mas"
                            @click="cantidad = esDecimal ? +(cantidad + 0.1).toFixed(3) : cantidad + 1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="pdv-btn-confirmar"
                    style="display:flex;align-items:center;justify-content:center;gap:.4rem;"
                    @click="$wire.confirmarModalConParams(cantidad, esCortesia)"
                    @if(count($seleccionados) < count($atributosModal)) disabled @endif
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    Agregar al pedido
                </button>
            </div>

        </div>
    </div>
    @endif

</div>
