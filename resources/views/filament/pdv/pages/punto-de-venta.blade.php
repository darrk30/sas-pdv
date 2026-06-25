<x-filament-panels::page>

    {{-- CSS cargado dentro del componente para no romper el root único de Livewire --}}
    <link rel="stylesheet" href="{{ asset('css/punto-de-venta.css') }}">

    <div class="pdv-wrap">

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
                <button
                    class="pdv-cat-btn {{ $categoriaId === null ? 'pdv-cat-btn--activo' : '' }}"
                    wire:click="seleccionarCategoria(null)"
                >
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

                {{-- Sección: Promociones --}}
                @php $promociones = $this->getPromociones(); @endphp
                @if($promociones->isNotEmpty())
                    <p class="pdv-seccion-titulo pdv-seccion-titulo--promo">Promociones</p>
                    <div class="pdv-items-grid">
                        @foreach($promociones as $promo)
                            <button class="pdv-card pdv-card--promo" wire:click="agregarPromocion({{ $promo->id }})">
                                <span class="pdv-badge-promo">PROMO</span>

                                @if($promo->imagen)
                                    <img
                                        class="pdv-card__imagen"
                                        src="{{ \Illuminate\Support\Facades\Storage::url($promo->imagen) }}"
                                        alt="{{ $promo->nombre }}"
                                    />
                                @else
                                    <div class="pdv-card__avatar">
                                        {{ strtoupper(mb_substr($promo->nombre, 0, 1)) }}
                                    </div>
                                @endif

                                <p class="pdv-card__nombre">{{ $promo->nombre }}</p>
                                <p class="pdv-card__meta">{{ $promo->detalles_count }} productos</p>
                                <p class="pdv-card__precio">S/ {{ number_format($promo->precio, 2) }}</p>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Sección: Productos --}}
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
                                    ? (float)($producto->inventario?->stock_real ?? 0)
                                    : null;
                                $stockVariantes = $tieneVariantes && $producto->control_de_stock
                                    ? $producto->variantes->sum(fn($v) => (float)($v->inventario?->stock_real ?? 0))
                                    : null;
                                $stock = $stockSimple ?? $stockVariantes;
                            @endphp
                            <button class="pdv-card" wire:click="abrirModalProducto({{ $producto->id }})">

                                {{-- Imagen o avatar con inicial --}}
                                @if($producto->logo)
                                    <img
                                        class="pdv-card__imagen"
                                        src="{{ \Illuminate\Support\Facades\Storage::url($producto->logo) }}"
                                        alt="{{ $producto->nombre }}"
                                    />
                                @else
                                    <div class="pdv-card__avatar">
                                        {{ strtoupper(mb_substr($producto->nombre, 0, 1)) }}
                                    </div>
                                @endif

                                <p class="pdv-card__nombre">{{ $producto->nombre }}</p>

                                {{-- Stock --}}
                                @if($stock !== null)
                                    <span class="pdv-card__stock {{ $stock <= 0 ? 'pdv-card__stock--agotado' : ($stock <= 5 ? 'pdv-card__stock--bajo' : 'pdv-card__stock--ok') }}">
                                        Stock: {{ number_format($stock, 0) }}
                                    </span>
                                @endif

                                {{-- Precio --}}
                                @if($tieneVariantes)
                                    <p class="pdv-card__meta">{{ $producto->variantes->count() }} variantes</p>
                                    <p class="pdv-card__precio">Desde S/ {{ number_format($producto->variantes->min('precio_final'), 2) }}</p>
                                @else
                                    <p class="pdv-card__precio">S/ {{ number_format($producto->precio_venta, 2) }}</p>
                                @endif
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

            </div>{{-- /pdv-grid --}}
        </div>{{-- /pdv-productos --}}


        {{-- ══ CARRITO (derecha) ══ --}}
        <div class="pdv-carrito">

            {{-- Header --}}
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
                @if(! empty($carrito))
                    <button class="pdv-carrito__vaciar" wire:click="vaciarCarrito">
                        Vaciar
                    </button>
                @endif
            </div>

            {{-- Items --}}
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
                                    <button
                                        class="pdv-qty__btn pdv-qty__btn--menos"
                                        wire:click="disminuirCantidad('{{ $item['key'] }}')"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                        </svg>
                                    </button>
                                    <span class="pdv-qty__num">{{ $item['cantidad'] }}</span>
                                    <button
                                        class="pdv-qty__btn pdv-qty__btn--mas"
                                        wire:click="aumentarCantidad('{{ $item['key'] }}')"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Totales + acción --}}
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
                    <button class="pdv-btn-venta">
                        Procesar Venta
                    </button>
                </div>
            @endif

        </div>{{-- /pdv-carrito --}}

    </div>{{-- /pdv-wrap --}}


    {{-- ══ MODAL: selección de variantes ══ --}}
    @if($modalAbierto)
        <div class="pdv-overlay" wire:key="modal-variantes">

            {{-- Backdrop --}}
            <div class="pdv-overlay__backdrop" wire:click="cerrarModal"></div>

            {{-- Panel --}}
            <div class="pdv-modal">

                {{-- Header --}}
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

                {{-- Body: atributos --}}
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
                                        $activo = isset($seleccionados[$atributo['id']])
                                            && (int)$seleccionados[$atributo['id']] === (int)$valor['id'];
                                    @endphp
                                    <button
                                        class="pdv-valor-btn {{ $activo ? 'pdv-valor-btn--activo' : '' }}"
                                        wire:click="seleccionarValor({{ $atributo['id'] }}, {{ $valor['id'] }})"
                                        wire:key="valor-{{ $atributo['id'] }}-{{ $valor['id'] }}"
                                    >
                                        {{ $valor['nombre'] }}
                                        @if($valor['precio_adicional'] > 0)
                                            <span class="pdv-valor-btn__extra">
                                                +S/ {{ number_format($valor['precio_adicional'], 2) }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Footer: precio + confirmar --}}
                <div class="pdv-modal__footer">
                    <div class="pdv-modal__precio-row">
                        <div>
                            <p class="pdv-modal__precio-label">Precio total</p>
                            @if($precioAdicionalTotal > 0)
                                <p class="pdv-modal__precio-detalle">
                                    S/ {{ number_format($precioBase, 2) }} + S/ {{ number_format($precioAdicionalTotal, 2) }}
                                </p>
                            @endif
                        </div>
                        <span class="pdv-modal__precio-total">
                            S/ {{ number_format($precioBase + $precioAdicionalTotal, 2) }}
                        </span>
                    </div>

                    @php $todosSeleccionados = count($seleccionados) >= count($atributosModal); @endphp
                    <button
                        class="pdv-btn-confirmar"
                        wire:click="confirmarModal"
                        @if(! $todosSeleccionados) disabled @endif
                    >
                        {{ $todosSeleccionados ? 'Agregar al carrito' : 'Selecciona todas las opciones' }}
                    </button>
                </div>

            </div>{{-- /pdv-modal --}}
        </div>{{-- /pdv-overlay --}}
    @endif

</x-filament-panels::page>
