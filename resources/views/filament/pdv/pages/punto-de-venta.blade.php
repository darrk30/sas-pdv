<x-filament-panels::page>

    <div class="flex gap-4" style="height: calc(100vh - 10rem);">

        {{-- ══ ÁREA DE PRODUCTOS (izquierda) ══ --}}
        <div class="flex flex-col flex-1 gap-3 min-w-0 overflow-hidden">

            {{-- Buscador --}}
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                    <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar producto o promoción..."
                    class="w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-9 pr-4 text-sm shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500"
                />
                @if($busqueda)
                    <button
                        wire:click="$set('busqueda', '')"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                @endif
            </div>

            {{-- Filtros de categoría --}}
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="$set('categoriaId', null)"
                    @class([
                        'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                        'bg-primary-500 text-white shadow-sm'             => $categoriaId === null,
                        'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' => $categoriaId !== null,
                    ])
                >
                    Todos
                </button>
                @foreach($this->getCategorias() as $cat)
                    <button
                        wire:click="$set('categoriaId', {{ $cat->id }})"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            'bg-primary-500 text-white shadow-sm'             => $categoriaId === $cat->id,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600' => $categoriaId !== $cat->id,
                        ])
                    >
                        {{ $cat->nombre }}
                    </button>
                @endforeach
            </div>

            {{-- Grid de productos y promociones --}}
            <div class="flex-1 overflow-y-auto pr-1">

                {{-- Sección: Promociones --}}
                @php $promociones = $this->getPromociones(); @endphp
                @if($promociones->isNotEmpty())
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">
                        Promociones
                    </p>
                    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach($promociones as $promo)
                            <button
                                wire:click="agregarPromocion({{ $promo->id }})"
                                class="group relative flex flex-col rounded-xl border border-amber-200 bg-amber-50 p-3 text-left transition hover:shadow-md dark:border-amber-700/50 dark:bg-amber-900/20"
                            >
                                <span class="absolute right-2 top-2 inline-flex items-center rounded-full bg-amber-400 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                    PROMO
                                </span>

                                @if($promo->imagen)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::url($promo->imagen) }}"
                                        class="mb-2 h-10 w-10 rounded-lg object-cover"
                                        alt="{{ $promo->nombre }}"
                                    />
                                @else
                                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-200 dark:bg-amber-800">
                                        <x-heroicon-o-tag class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                    </div>
                                @endif

                                <p class="line-clamp-2 text-sm font-medium leading-snug text-gray-800 dark:text-gray-200">
                                    {{ $promo->nombre }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-400">
                                    {{ $promo->detalles_count }} {{ Str::plural('producto', $promo->detalles_count) }}
                                </p>
                                <p class="mt-1 text-sm font-bold text-amber-600 dark:text-amber-400">
                                    S/ {{ number_format($promo->precio, 2) }}
                                </p>
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Sección: Productos --}}
                @php $productos = $this->getProductos(); @endphp
                @if($promociones->isNotEmpty() && $productos->isNotEmpty())
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Productos
                    </p>
                @endif

                @if($productos->isNotEmpty())
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach($productos as $producto)
                            <button
                                wire:click="abrirModalProducto({{ $producto->id }})"
                                class="group flex flex-col rounded-xl border border-gray-200 bg-white p-3 text-left transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-600"
                            >
                                @if($producto->logo)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::url($producto->logo) }}"
                                        class="mb-2 h-10 w-10 rounded-lg object-cover"
                                        alt="{{ $producto->nombre }}"
                                    />
                                @else
                                    <div class="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                                        <x-heroicon-o-cube class="h-5 w-5 text-gray-400" />
                                    </div>
                                @endif

                                <p class="line-clamp-2 text-sm font-medium leading-snug text-gray-800 dark:text-gray-200">
                                    {{ $producto->nombre }}
                                </p>

                                @if($producto->variantes->isNotEmpty())
                                    <p class="mt-0.5 text-xs text-gray-400">
                                        {{ $producto->variantes->count() }} variantes
                                    </p>
                                    <p class="mt-1 text-sm font-bold text-primary-600 dark:text-primary-400">
                                        Desde S/ {{ number_format($producto->variantes->min('precio_final'), 2) }}
                                    </p>
                                @else
                                    <p class="mt-auto pt-1 text-sm font-bold text-primary-600 dark:text-primary-400">
                                        S/ {{ number_format($producto->precio_venta, 2) }}
                                    </p>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                        <x-heroicon-o-magnifying-glass class="mb-3 h-10 w-10" />
                        <p class="text-sm">No se encontraron productos</p>
                        @if($busqueda)
                            <button
                                wire:click="$set('busqueda', '')"
                                class="mt-2 text-xs text-primary-500 hover:underline"
                            >
                                Limpiar búsqueda
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ══ CARRITO (derecha) ══ --}}
        <div class="flex w-72 shrink-0 flex-col rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:w-80">

            {{-- Header carrito --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shopping-cart class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Carrito</h2>
                    @if($this->getItemCount() > 0)
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary-500 text-[10px] font-bold text-white">
                            {{ $this->getItemCount() }}
                        </span>
                    @endif
                </div>
                @if(! empty($carrito))
                    <button
                        wire:click="vaciarCarrito"
                        wire:confirm="¿Vaciar todo el carrito?"
                        class="text-xs text-red-400 hover:text-red-600 dark:hover:text-red-400"
                    >
                        Vaciar
                    </button>
                @endif
            </div>

            {{-- Items del carrito --}}
            <div class="flex-1 overflow-y-auto p-3 space-y-2">
                @forelse($carrito as $item)
                    <div class="flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-gray-700/50">
                        <div class="flex-1 min-w-0">
                            @if($item['tipo'] === 'promocion')
                                <span class="mb-0.5 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">
                                    PROMO
                                </span>
                            @endif
                            <p class="text-xs font-medium leading-snug text-gray-800 dark:text-gray-200 line-clamp-2">
                                {{ $item['nombre'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                S/ {{ number_format($item['precio'], 2) }} c/u
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                S/ {{ number_format($item['precio'] * $item['cantidad'], 2) }}
                            </p>
                            <div class="flex items-center gap-1">
                                <button
                                    wire:click="disminuirCantidad('{{ $item['key'] }}')"
                                    class="flex h-5 w-5 items-center justify-center rounded bg-gray-200 text-gray-600 hover:bg-red-100 hover:text-red-600 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-red-900/40 dark:hover:text-red-400"
                                >
                                    <x-heroicon-o-minus class="h-3 w-3" />
                                </button>
                                <span class="w-6 text-center text-xs font-bold text-gray-700 dark:text-gray-300">
                                    {{ $item['cantidad'] }}
                                </span>
                                <button
                                    wire:click="aumentarCantidad('{{ $item['key'] }}')"
                                    class="flex h-5 w-5 items-center justify-center rounded bg-gray-200 text-gray-600 hover:bg-primary-100 hover:text-primary-600 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-primary-900/40 dark:hover:text-primary-400"
                                >
                                    <x-heroicon-o-plus class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                        <x-heroicon-o-shopping-cart class="mb-3 h-10 w-10 opacity-40" />
                        <p class="text-sm">El carrito está vacío</p>
                        <p class="mt-1 text-xs opacity-60">Selecciona productos para comenzar</p>
                    </div>
                @endforelse
            </div>

            {{-- Totales y acción --}}
            @if(! empty($carrito))
                <div class="border-t border-gray-200 p-4 dark:border-gray-700 space-y-3">
                    <div class="space-y-1">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $this->getItemCount() }} {{ Str::plural('producto', $this->getItemCount()) }}</span>
                            <span>Subtotal</span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total</span>
                            <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                S/ {{ number_format($this->getTotal(), 2) }}
                            </span>
                        </div>
                    </div>

                    <button class="w-full rounded-xl bg-primary-600 py-3 text-sm font-semibold text-white transition hover:bg-primary-700 active:scale-[.98]">
                        Procesar Venta
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- ══ MODAL: selección de variantes ══ --}}
    @if($modalAbierto)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            wire:key="modal-variantes"
        >
            {{-- Backdrop --}}
            <div
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                wire:click="cerrarModal"
            ></div>

            {{-- Panel del modal --}}
            <div class="relative flex max-h-[85vh] w-full max-w-md flex-col rounded-2xl bg-white shadow-2xl dark:bg-gray-800">

                {{-- Header --}}
                <div class="flex items-start justify-between border-b border-gray-200 p-5 dark:border-gray-700">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            {{ $productoModalNombre }}
                        </h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            Selecciona las opciones del producto
                        </p>
                    </div>
                    <button
                        wire:click="cerrarModal"
                        class="ml-4 shrink-0 rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                {{-- Opciones de atributos --}}
                <div class="flex-1 overflow-y-auto p-5 space-y-5">
                    @foreach($atributosModal as $atributo)
                        <div>
                            <p class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ $atributo['nombre'] }}
                                @if(! isset($seleccionados[$atributo['id']]))
                                    <span class="ml-1 text-xs font-normal text-gray-400">(requerido)</span>
                                @endif
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($atributo['valores'] as $valor)
                                    @php
                                        $estaSeleccionado = isset($seleccionados[$atributo['id']])
                                            && $seleccionados[$atributo['id']] === $valor['id'];
                                    @endphp
                                    <button
                                        wire:click="seleccionarValor({{ $atributo['id'] }}, {{ $valor['id'] }})"
                                        @class([
                                            'flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm font-medium transition',
                                            'border-primary-500 bg-primary-50 text-primary-700 ring-1 ring-primary-500 dark:bg-primary-900/30 dark:text-primary-300' => $estaSeleccionado,
                                            'border-gray-300 text-gray-700 hover:border-gray-400 dark:border-gray-600 dark:text-gray-300 dark:hover:border-gray-500' => ! $estaSeleccionado,
                                        ])
                                    >
                                        {{ $valor['nombre'] }}
                                        @if($valor['precio_adicional'] > 0)
                                            <span @class([
                                                'text-xs',
                                                'text-primary-500 dark:text-primary-400' => $estaSeleccionado,
                                                'text-gray-400' => ! $estaSeleccionado,
                                            ])>
                                                +S/ {{ number_format($valor['precio_adicional'], 2) }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Footer con precio y confirmar --}}
                <div class="border-t border-gray-200 p-5 dark:border-gray-700">
                    <div class="mb-4 flex items-end justify-between">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Precio total</p>
                            @if($precioAdicionalTotal > 0)
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    S/ {{ number_format($precioBase, 2) }}
                                    + S/ {{ number_format($precioAdicionalTotal, 2) }}
                                </p>
                            @endif
                        </div>
                        <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                            S/ {{ number_format($precioBase + $precioAdicionalTotal, 2) }}
                        </span>
                    </div>

                    <button
                        wire:click="confirmarModal"
                        @if(count($seleccionados) < count($atributosModal)) disabled @endif
                        class="w-full rounded-xl bg-primary-600 py-3 text-sm font-semibold text-white transition hover:bg-primary-700 active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        @if(count($seleccionados) < count($atributosModal))
                            Selecciona todas las opciones
                        @else
                            Agregar al carrito
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
