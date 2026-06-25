<x-filament-panels::page>

    <link rel="stylesheet" href="{{ asset('css/punto-de-venta.css') }}">

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

    <div class="pdv-wrap" x-data="{ carritoOpen: false }">

        {{-- ══ ÁREA DE PRODUCTOS (izquierda) ══ --}}
        <div class="pdv-productos">

            {{-- Buscador --}}
            <div class="pdv-busqueda">
                <svg class="pdv-busqueda__icono" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input
                    type="text"
                    class="pdv-busqueda__input"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar producto o promoción..."
                />
                @if($busqueda)
                    <button class="pdv-busqueda__clear" wire:click="limpiarBusqueda">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Filtros de categoría --}}
            <div class="pdv-categorias">
                <button class="pdv-cat-btn {{ $categoriaId === null ? 'pdv-cat-btn--activo' : '' }}" wire:click="seleccionarCategoria(null)">
                    Todos
                </button>
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
                    <p class="pdv-seccion-titulo pdv-seccion-titulo--promo">Promociones</p>
                    <div class="pdv-items-grid">
                        @foreach($promociones as $promo)
                            <button class="pdv-card pdv-card--promo" wire:click="agregarPromocion({{ $promo->id }})">
                                <div class="pdv-card__img-wrap">
                                    @if($promo->imagen)
                                        <img class="pdv-card__img" src="{{ \Illuminate\Support\Facades\Storage::url($promo->imagen) }}" alt="{{ $promo->nombre }}"/>
                                    @else
                                        <div class="pdv-card__avatar-grande pdv-card__avatar-grande--promo">{{ strtoupper(mb_substr($promo->nombre, 0, 1)) }}</div>
                                    @endif
                                    <span class="pdv-card__badge pdv-card__badge--promo">PROMO</span>
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
                @if($promociones->isNotEmpty() && $productos->isNotEmpty())
                    <p class="pdv-seccion-titulo pdv-seccion-titulo--productos">Productos</p>
                @endif

                @if($productos->isNotEmpty())
                    <div class="pdv-items-grid">
                        @foreach($productos as $producto)
                            @php
                                $tieneVariantes = $producto->variantes->isNotEmpty();
                                $stockSimple    = ! $tieneVariantes && $producto->control_de_stock
                                    ? (float)($producto->inventario?->stock_real ?? 0) : null;
                                $stockVariantes = $tieneVariantes && $producto->control_de_stock
                                    ? $producto->variantes->sum(fn($v) => (float)($v->inventario?->stock_real ?? 0)) : null;
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
                                            {{ number_format($stock, 0) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="pdv-card__body">
                                    <p class="pdv-card__nombre">{{ $producto->nombre }}</p>
                                    @if($tieneVariantes)
                                        <p class="pdv-card__meta">{{ $producto->variantes->count() }} variantes</p>
                                    @endif
                                    <p class="pdv-card__precio">
                                        @if($producto->es_cortesia)
                                            GRATIS
                                        @elseif($tieneVariantes)
                                            Desde S/ {{ number_format($producto->variantes->min('precio_final'), 2) }}
                                        @else
                                            S/ {{ number_format($producto->precio_venta, 2) }}
                                        @endif
                                    </p>
                                </div>
                            </button>
                        @endforeach
                    </div>
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
                        <div class="pdv-item" wire:key="item-{{ $item['key'] }}">
                            <div class="pdv-item__info">
                                @if($item['tipo'] === 'promocion')
                                    <span class="pdv-item__badge-promo">PROMO</span>
                                @endif
                                <p class="pdv-item__nombre">{{ $item['nombre'] }}</p>
                                <p class="pdv-item__precio-unit">S/ {{ number_format($item['precio'], 2) }} c/u</p>
                            </div>
                            <div class="pdv-item__controles">
                                <span class="pdv-item__subtotal">S/ {{ number_format($item['precio'] * $item['cantidad'], 2) }}</span>
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


    {{-- ══ MODAL: pago ══ --}}
    @if($modalPago)
        <div class="pdv-overlay" wire:key="modal-pago">
            <div class="pdv-overlay__backdrop" wire:click="cerrarModalPago"></div>
            <div class="pdv-modal pdv-modal--pago">

                <div class="pdv-modal__header">
                    <div>
                        <h3 class="pdv-modal__titulo">Procesar Venta</h3>
                        <p class="pdv-modal__subtitulo">Completa el pago para confirmar</p>
                    </div>
                    <button class="pdv-modal__cerrar" wire:click="cerrarModalPago">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="pdv-modal__body pdv-pago-body">

                    {{-- ── RESUMEN ── --}}
                    <div class="pdv-pago-resumen">
                        <div class="pdv-pago-resumen__fila">
                            <span>Op. Gravada</span>
                            <span>S/ {{ number_format($this->getOpGravadas(), 2) }}</span>
                        </div>
                        <div class="pdv-pago-resumen__fila">
                            <span>IGV (18%)</span>
                            <span>S/ {{ number_format($this->getIgv(), 2) }}</span>
                        </div>
                        @if($this->getDescuento() > 0)
                            <div class="pdv-pago-resumen__fila pdv-pago-resumen__fila--descuento">
                                <span>Descuento</span>
                                <span>-S/ {{ number_format($this->getDescuento(), 2) }}</span>
                            </div>
                        @endif
                        <div class="pdv-pago-resumen__fila pdv-pago-resumen__fila--total">
                            <span>Total</span>
                            <span>S/ {{ number_format($this->getTotalConDescuento(), 2) }}</span>
                        </div>
                    </div>

                    {{-- ── DESCUENTO ── --}}
                    <div class="pdv-pago-section">
                        <p class="pdv-pago-section__label">Descuento (opcional)</p>
                        <div class="pdv-pago-input-wrap">
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

                    {{-- ── MÉTODOS DE PAGO ── --}}
                    <div class="pdv-pago-section">
                        <p class="pdv-pago-section__label">Método de pago</p>
                        @if(empty($metodosPagoDisponibles))
                            <p class="pdv-pago-empty">No hay métodos de pago configurados</p>
                        @else
                            <div class="pdv-metodos-grid">
                                @foreach($metodosPagoDisponibles as $metodo)
                                    <button
                                        class="pdv-metodo-btn {{ $metodoPagoId === $metodo['id'] ? 'pdv-metodo-btn--activo' : '' }}"
                                        wire:click="seleccionarMetodoPago({{ $metodo['id'] }})"
                                    >
                                        @if($metodo['imagen'])
                                            <img class="pdv-metodo-btn__img" src="{{ \Illuminate\Support\Facades\Storage::url($metodo['imagen']) }}" alt="{{ $metodo['nombre'] }}"/>
                                        @else
                                            <div class="pdv-metodo-btn__avatar">{{ strtoupper(mb_substr($metodo['nombre'], 0, 1)) }}</div>
                                        @endif
                                        <span class="pdv-metodo-btn__nombre">{{ $metodo['nombre'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                            @php $metodoActivo = collect($metodosPagoDisponibles)->firstWhere('id', $metodoPagoId); @endphp
                            @if($metodoActivo && $metodoActivo['requiere_referencia'])
                                <div class="pdv-pago-referencia">
                                    <input
                                        type="text"
                                        class="pdv-field__input"
                                        wire:model.live="pagoReferencia"
                                        placeholder="Número de referencia / operación"
                                    />
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- ── MONTO Y BOTONES RÁPIDOS ── --}}
                    <div class="pdv-pago-section">
                        <p class="pdv-pago-section__label">Monto</p>
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
                                Agregar pago
                            </button>
                        </div>
                    </div>

                    {{-- ── PAGOS AGREGADOS ── --}}
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
                        </div>
                    @endif

                </div>

                <div class="pdv-modal__footer">
                    @php $listo = $this->getSaldoRestante() <= 0.01 && ! empty($pagosAgregados); @endphp
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
                            class="pdv-field__input"
                            wire:model.live="ncNumeroDoc"
                            placeholder="{{ $ncTipoDoc === 'ruc' ? '11 dígitos' : '8 dígitos' }}"
                            maxlength="{{ $ncTipoDoc === 'ruc' ? 11 : 8 }}"
                        />
                    </div>

                    <div class="pdv-field">
                        <label class="pdv-field__label">Nombre / Razón Social <span class="pdv-field__req">*</span></label>
                        <input type="text" class="pdv-field__input" wire:model.live="ncNombre" placeholder="Nombre"/>
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
