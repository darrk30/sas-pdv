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
            <p class="pdv-header__venta">Venta #{{ $this->getNumeroPreview() }}</p>
            <p class="pdv-header__fecha">{{ now()->format('d/m/Y  H:i') }}</p>
        </div>
    </div>

    <div class="pdv-wrap" x-data="{ carritoOpen: false }" @cerrar-carrito-mobile.window="carritoOpen = false">

        {{-- ══ ÁREA DE PRODUCTOS (izquierda) ══ --}}
        <div class="pdv-productos">

            {{-- Buscador --}}
            <div class="pdv-busqueda">
                <svg class="pdv-busqueda__icono" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input
                    type="text"
                    class="pdv-busqueda__input pdv-busqueda__input--con-scan"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar por nombre, código interno o código de barras..."
                />
                @if($busqueda)
                    <button class="pdv-busqueda__clear pdv-busqueda__clear--con-scan" wire:click="limpiarBusqueda" title="Limpiar">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
                <button
                    type="button"
                    class="pdv-busqueda__scan"
                    onclick="window.dispatchEvent(new CustomEvent('open-barcode-scanner', { detail: { path: '__pdv__' } }))"
                    title="Escanear código de barras"
                >
                    <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                </button>
            </div>

            {{-- Filtros de categoría --}}
            <div class="pdv-categorias">
                <button class="pdv-cat-btn {{ $categoriaId === null ? 'pdv-cat-btn--activo' : '' }}" wire:click="seleccionarCategoria(null)">
                    Todos
                </button>
                @if($this->getHayPromociones())
                    <button class="pdv-cat-btn pdv-cat-btn--promo {{ $categoriaId === -1 ? 'pdv-cat-btn--activo' : '' }}" wire:click="seleccionarCategoria(-1)">
                        Promos
                    </button>
                @endif
                @foreach($this->getCategorias() as $cat)
                    <button
                        class="pdv-cat-btn {{ $categoriaId === $cat->id ? 'pdv-cat-btn--activo' : '' }}"
                        wire:click="seleccionarCategoria({{ $cat->id }})"
                    >
                        {{ $cat->nombre }}
                    </button>
                @endforeach
            </div>

            {{-- Grid scrollable --}}
            <div class="pdv-grid">

                {{-- Promociones --}}
                @php $promociones = $this->getPromociones(); @endphp
                @if($promociones->isNotEmpty())
                    <div class="pdv-items-grid">
                        @foreach($promociones as $promo)
                            @php $stockPromo = $promo->stockPredictivo(); @endphp
                            <button
                                class="pdv-card pdv-card--promo {{ $stockPromo === 0 ? 'pdv-card--agotada' : '' }}"
                                wire:click="agregarPromocion({{ $promo->id }})"
                                {{ $stockPromo === 0 ? 'disabled' : '' }}
                            >
                                <div class="pdv-card__img-wrap">
                                    @if($promo->imagen)
                                        <img class="pdv-card__img" src="{{ \Illuminate\Support\Facades\Storage::url($promo->imagen) }}" alt="{{ $promo->nombre }}"/>
                                    @else
                                        <div class="pdv-card__avatar-grande pdv-card__avatar-grande--promo">{{ strtoupper(mb_substr($promo->nombre, 0, 1)) }}</div>
                                    @endif
                                    <span class="pdv-card__badge pdv-card__badge--promo">PROMO</span>
                                    @if($stockPromo === 0)
                                        <span class="pdv-card__badge-stock pdv-card__badge-stock--agotada">Agotada</span>
                                    @elseif($stockPromo !== null)
                                        <span class="pdv-card__stock-badge pdv-card__stock-badge--{{ $stockPromo <= 5 ? 'bajo' : 'ok' }}">
                                            Stock {{ $stockPromo }}
                                        </span>
                                    @endif
                                </div>
                                <div class="pdv-card__body">
                                    <p class="pdv-card__nombre">{{ $promo->nombre }}</p>
                                    <p class="pdv-card__meta">{{ $promo->detalles_count }} productos</p>
                                    <p class="pdv-card__precio">S/ {{ number_format($promo->precio, 2) }}</p>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Productos --}}
                @php $productos = $this->getProductos(); @endphp

                @if($productos->isNotEmpty())
                    <div class="pdv-items-grid" id="pdv-productos-grid">
                        @foreach($productos as $producto)
                            @php
                                $tieneVariantes = $producto->variantesActivas->isNotEmpty();
                                $stockSimple    = ! $tieneVariantes && $producto->control_de_stock
                                    ? (float)($producto->inventario?->stock_real ?? 0) : null;
                                $stockVariantes = $tieneVariantes && $producto->control_de_stock
                                    ? $producto->variantesActivas->sum(fn($v) => (float)($v->inventario?->stock_real ?? 0)) : null;
                                $stock      = $stockSimple ?? $stockVariantes;
                                $agotado    = $producto->control_de_stock
                                    && ! $producto->venta_sin_stock
                                    && $stock !== null
                                    && $stock <= 0;
                                $stockNivel = $stock === null ? null : ($stock <= 0 ? 'agotado' : ($stock <= 5 ? 'bajo' : 'ok'));
                            @endphp
                            <button
                                class="pdv-card {{ $agotado ? 'pdv-card--agotado' : '' }}"
                                wire:click="abrirModalProducto({{ $producto->id }})"
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
                                    @if($producto->es_cortesia)
                                        <span class="pdv-card__badge pdv-card__badge--cortesia">GRATIS</span>
                                    @endif
                                    @if($stockNivel !== null && ! $agotado)
                                        <span class="pdv-card__stock-badge pdv-card__stock-badge--{{ $stockNivel }}">
                                            Stock {{ number_format($stock, 0) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="pdv-card__body">
                                    <p class="pdv-card__nombre">{{ $producto->nombre }}</p>
                                    @if($tieneVariantes)
                                        <p class="pdv-card__meta">{{ $producto->variantesActivas->count() }} variantes</p>
                                    @endif
                                    <p class="pdv-card__precio">
                                        @if($producto->es_cortesia)
                                            GRATIS
                                        @elseif($tieneVariantes)
                                            Desde S/ {{ number_format($producto->variantesActivas->min('precio_final'), 2) }}
                                        @else
                                            S/ {{ number_format($producto->precio_venta, 2) }}
                                        @endif
                                    </p>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    {{-- Sentinel: dispara cargarMas() cuando entra al viewport --}}
                    @if($productos->count() >= $this->perPage)
                        <div
                            wire:key="sentinel-{{ $this->perPage }}"
                            x-intersect.margin.300px="$wire.cargarMas()"
                            class="pdv-sentinel"
                        ></div>
                        <div wire:loading.delay wire:target="cargarMas" class="pdv-sentinel__loading">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="pdv-sentinel__spinner">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                            </svg>
                            Cargando más productos...
                        </div>
                    @endif
                @else
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
        </div>{{-- /pdv-productos --}}


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
                    <button class="pdv-cliente__nuevo-btn" wire:click="abrirModalNuevoCliente" title="Nuevo cliente">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
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
            @php $series = $this->getSeries(); @endphp
            <div class="pdv-comprobante">
                @foreach([
                    ['tipo' => 'factura', 'label' => 'Factura',  'color' => 'info'],
                    ['tipo' => 'boleta',  'label' => 'Boleta',   'color' => 'success'],
                    ['tipo' => 'ticket',  'label' => 'Ticket',   'color' => 'warning'],
                ] as $c)
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
                        @php $esCortesia = $item['cortesia'] ?? false; @endphp
                        <div class="pdv-item {{ $esCortesia ? 'pdv-item--cortesia' : '' }}" wire:key="item-{{ $item['key'] }}">
                            <div class="pdv-item__info">
                                <div class="pdv-item__badges">
                                    @if($item['tipo'] === 'promocion')
                                        <span class="pdv-item__badge-promo">PROMO</span>
                                    @endif
                                    @if($esCortesia)
                                        <span class="pdv-item__badge-cortesia">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="10" height="10">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                                            </svg>
                                            Cortesía
                                        </span>
                                    @endif
                                </div>
                                <p class="pdv-item__nombre">{{ $item['nombre'] }}</p>
                                <p class="pdv-item__precio-unit">
                                    @if($esCortesia)
                                        <span class="pdv-item__precio-gratis">Gratis</span>
                                    @else
                                        <span
                                            x-data="{ editing: false, val: '{{ number_format($item['precio'], 2, '.', '') }}' }"
                                            class="pdv-item__precio-editable"
                                        >
                                            <span
                                                x-show="!editing"
                                                @click="editing = true; $nextTick(() => $refs.inp_{{ $item['key'] }}.select())"
                                                class="pdv-item__precio-valor"
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
                                                @keydown.escape="editing = false; val = '{{ number_format($item['precio'], 2, '.', '') }}'"
                                            />
                                        </span>
                                    @endif
                                </p>
                            </div>
                            <div class="pdv-item__controles">
                                <span class="pdv-item__subtotal {{ $esCortesia ? 'pdv-item__subtotal--gratis' : '' }}">
                                    S/ {{ number_format($item['precio'] * $item['cantidad'], 2) }}
                                </span>
                                <div class="pdv-qty">
                                    <button class="pdv-qty__btn pdv-qty__btn--menos" wire:click="disminuirCantidad('{{ $item['key'] }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                        </svg>
                                    </button>
                                    <span class="pdv-qty__num">{{ $item['cantidad'] }}</span>
                                    <button class="pdv-qty__btn pdv-qty__btn--mas" wire:click="aumentarCantidad('{{ $item['key'] }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pdv-carrito__footer">
                    <div class="pdv-carrito__totales">
                        <div class="pdv-carrito__fila">
                            <span class="pdv-carrito__label">{{ $this->getItemCount() }} ítems</span>
                            <span class="pdv-carrito__sublabel">Subtotal</span>
                        </div>
                        <div class="pdv-carrito__fila">
                            <span class="pdv-carrito__total-label">Total</span>
                            <span class="pdv-carrito__total-monto">S/ {{ number_format($this->getTotal(), 2) }}</span>
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


    {{-- ══ MODAL: variantes ══ --}}
    @if($modalAbierto)
        <div class="pdv-overlay" wire:key="modal-variantes">
            <div class="pdv-overlay__backdrop" wire:click="cerrarModal"></div>
            <div class="pdv-modal">
                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo">{{ $productoModalNombre }}</h3>
                        <p class="pdv-modal__subtitulo">Selecciona las opciones del producto</p>
                    </div>
                    <button class="pdv-modal__cerrar" wire:click="cerrarModal">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="pdv-modal__body">
                    @foreach($atributosModal as $atributo)
                        <div wire:key="atributo-{{ $atributo['id'] }}">
                            <p class="pdv-atributo__label">
                                {{ $atributo['nombre'] }}
                                @if(! isset($seleccionados[$atributo['id']]))
                                    <span class="pdv-atributo__requerido">(requerido)</span>
                                @endif
                            </p>
                            <div class="pdv-atributo__opciones">
                                @foreach($atributo['valores'] as $valor)
                                    @php
                                        $activo        = isset($seleccionados[$atributo['id']]) && (int)$seleccionados[$atributo['id']] === (int)$valor['id'];
                                        $deshabilitado = in_array((int)$valor['id'], $valoresDeshabilitados);
                                    @endphp
                                    <button
                                        class="pdv-valor-btn {{ $activo ? 'pdv-valor-btn--activo' : '' }} {{ $deshabilitado ? 'pdv-valor-btn--deshabilitado' : '' }}"
                                        wire:click="seleccionarValor({{ $atributo['id'] }}, {{ $valor['id'] }})"
                                        wire:key="valor-{{ $atributo['id'] }}-{{ $valor['id'] }}"
                                        @if($deshabilitado) disabled @endif
                                    >
                                        {{ $valor['nombre'] }}
                                        @if($valor['precio_adicional'] > 0)
                                            <span class="pdv-valor-btn__extra">+S/ {{ number_format($valor['precio_adicional'], 2) }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="pdv-modal__footer">
                    <div class="pdv-modal__precio-row">
                        <div>
                            <p class="pdv-modal__precio-label">Precio total</p>
                            @if($precioAdicionalTotal > 0)
                                <p class="pdv-modal__precio-detalle">S/ {{ number_format($precioBase, 2) }} + S/ {{ number_format($precioAdicionalTotal, 2) }}</p>
                            @endif
                        </div>
                        <span class="pdv-modal__precio-total">S/ {{ number_format($precioBase + $precioAdicionalTotal, 2) }}</span>
                    </div>
                    @php $todosSeleccionados = count($seleccionados) >= count($atributosModal); @endphp
                    <button class="pdv-btn-confirmar" wire:click="confirmarModal" @if(! $todosSeleccionados) disabled @endif>
                        {{ $todosSeleccionados ? 'Agregar al carrito' : 'Selecciona todas las opciones' }}
                    </button>
                </div>
            </div>
        </div>
    @endif


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
            <div class="pdv-modal pdv-modal--pago">

                {{-- Header --}}
                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo">Procesar Venta</h3>
                        <p class="pdv-modal__subtitulo">Selecciona método y completa el pago</p>
                    </div>
                    <div class="pdv-despacho-wrap">
                        <label class="pdv-despacho-label">
                            <input
                                type="checkbox"
                                class="pdv-despacho-check"
                                wire:model.live="despachoRequerido"
                            />
                            <span class="pdv-despacho-text">¿Despacho pendiente?</span>
                        </label>
                        @if($despachoRequerido)
                            <span class="pdv-despacho-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="12" height="12">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                </svg>
                                Pendiente de envío
                            </span>
                        @endif
                    </div>
                    <button class="pdv-modal__cerrar" wire:click="cerrarModalPago">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Body: layout 2 columnas en PC --}}
                <div class="pdv-modal__body pdv-pago-body">
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
                                                    <span class="pdv-pago-item__nombre">{{ $pago['nombre'] }}</span>
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
                                    <div class="pdv-pago-resumen__fila">
                                        <span>Op. Gravada</span>
                                        <span>S/ {{ number_format($this->getOpGravadas(), 2) }}</span>
                                    </div>
                                    <div class="pdv-pago-resumen__fila">
                                        <span>IGV (18%)</span>
                                        <span>S/ {{ number_format($this->getIgv(), 2) }}</span>
                                    </div>
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
                </div>{{-- /body --}}

                <div class="pdv-modal__footer">
                    @php $listo = ($this->getSaldoRestante() <= 0.01 && ! empty($pagosAgregados)) || $this->totalEsCero(); @endphp
                    <button
                        class="pdv-btn-confirmar {{ $listo ? 'pdv-btn-confirmar--venta' : '' }}"
                        wire:click="procesarVenta"
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


    {{-- ══ MODAL: nuevo cliente rápido ══ --}}
    @if($modalNuevoCliente)
        <div class="pdv-overlay" wire:key="modal-nuevo-cliente">
            <div class="pdv-overlay__backdrop" wire:click="cerrarModalNuevoCliente"></div>
            <div class="pdv-modal" style="max-width:26rem;">
                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo">Nuevo Cliente</h3>
                        <p class="pdv-modal__subtitulo">Registro rápido</p>
                    </div>
                    <button class="pdv-modal__cerrar" wire:click="cerrarModalNuevoCliente">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="pdv-modal__body">

                    <div class="pdv-field">
                        <label class="pdv-field__label">Tipo de documento</label>
                        <div class="pdv-doc-tipo">
                            <button
                                class="pdv-doc-tipo__btn {{ $ncTipoDoc === 'dni' ? 'pdv-doc-tipo__btn--activo' : '' }}"
                                wire:click="$set('ncTipoDoc', 'dni')"
                            >DNI</button>
                            <button
                                class="pdv-doc-tipo__btn {{ $ncTipoDoc === 'ruc' ? 'pdv-doc-tipo__btn--activo' : '' }}"
                                wire:click="$set('ncTipoDoc', 'ruc')"
                            >RUC</button>
                        </div>
                    </div>

                    <div class="pdv-field">
                        <label class="pdv-field__label">Número de documento <span class="pdv-field__req">*</span></label>
                        <input
                            type="text"
                            class="pdv-field__input {{ $errors->has('ncNumeroDoc') ? 'pdv-field__input--error' : '' }}"
                            wire:model.live="ncNumeroDoc"
                            placeholder="{{ $ncTipoDoc === 'ruc' ? '11 dígitos' : '8 dígitos' }}"
                            maxlength="{{ $ncTipoDoc === 'ruc' ? 11 : 8 }}"
                        />
                        @error('ncNumeroDoc')
                            <p class="pdv-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pdv-field">
                        <label class="pdv-field__label">Nombre / Razón Social <span class="pdv-field__req">*</span></label>
                        <input type="text" class="pdv-field__input {{ $errors->has('ncNombre') ? 'pdv-field__input--error' : '' }}" wire:model.live="ncNombre" placeholder="Nombre"/>
                        @error('ncNombre')
                            <p class="pdv-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($ncTipoDoc === 'dni')
                        <div class="pdv-field">
                            <label class="pdv-field__label">Apellidos</label>
                            <input type="text" class="pdv-field__input" wire:model.live="ncApellidos" placeholder="Apellidos"/>
                        </div>
                    @endif

                </div>
                <div class="pdv-modal__footer">
                    <button class="pdv-btn-confirmar" wire:click="crearCliente">
                        Crear y seleccionar cliente
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
