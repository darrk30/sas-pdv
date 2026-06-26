<x-filament-panels::page>
    @php
        $movimientos = $this->getMovimientos();
        $productos   = $this->getProductosParaFiltro();
        $resumen     = $this->getResumen();

        $origenLabels = [
            'App\\Models\\Ajuste' => ['label' => 'Ajuste',  'color' => 'text-amber-600  dark:text-amber-400',  'bg' => 'bg-amber-50  dark:bg-amber-900/30  border-amber-200  dark:border-amber-700'],
            'App\\Models\\Compra' => ['label' => 'Compra',  'color' => 'text-blue-600   dark:text-blue-400',   'bg' => 'bg-blue-50   dark:bg-blue-900/30   border-blue-200   dark:border-blue-700'],
            'App\\Models\\Venta'  => ['label' => 'Venta',   'color' => 'text-violet-600 dark:text-violet-400', 'bg' => 'bg-violet-50 dark:bg-violet-900/30 border-violet-200 dark:border-violet-700'],
        ];
    @endphp

    {{-- ── Título ─────────────────────────────────────────────────────────── --}}
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Kardex de Inventario</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Historial de movimientos de stock</p>
    </div>

    {{-- ── Filtros ─────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">

            {{-- Búsqueda --}}
            <div class="xl:col-span-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Buscar</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 dark:text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="busqueda"
                        placeholder="Producto, variante, concepto…"
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                               bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                               placeholder-gray-400 dark:placeholder-gray-500
                               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    />
                </div>
            </div>

            {{-- Producto --}}
            <div class="xl:col-span-1">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Producto</label>
                <select
                    wire:model.live="productoId"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                >
                    <option value="">Todos los productos</option>
                    @foreach ($productos as $prod)
                        <option value="{{ $prod->id }}">
                            {{ $prod->nombre }}
                            @if ($prod->estado !== 'activo') ({{ $prod->estado }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Fecha desde --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Fecha desde</label>
                <input
                    type="date"
                    wire:model.live="fechaDesde"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
            </div>

            {{-- Fecha hasta --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Fecha hasta</label>
                <input
                    type="date"
                    wire:model.live="fechaHasta"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
            </div>

            {{-- Tipo --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tipo</label>
                <select
                    wire:model.live="tipo"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                >
                    <option value="">Todos</option>
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                </select>
            </div>

            {{-- Origen --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Origen</label>
                <select
                    wire:model.live="origen"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                >
                    <option value="">Todos</option>
                    <option value="App\Models\Ajuste">Ajuste</option>
                    <option value="App\Models\Compra">Compra</option>
                    <option value="App\Models\Venta">Venta</option>
                </select>
            </div>

            {{-- Limpiar --}}
            <div class="flex items-end">
                <button
                    wire:click="limpiarFiltros"
                    class="w-full px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600
                           text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700
                           hover:bg-gray-50 dark:hover:bg-gray-600
                           focus:outline-none focus:ring-2 focus:ring-primary-500
                           transition-colors duration-150"
                >
                    Limpiar filtros
                </button>
            </div>
        </div>
    </div>

    {{-- ── Chips de resumen ────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-2 mb-4">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
                     bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Total: {{ number_format($resumen['total']) }}
        </span>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
                     bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-700">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
            </svg>
            Entradas: {{ number_format($resumen['entradas']) }}
        </span>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold
                     bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-700">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
            </svg>
            Salidas: {{ number_format($resumen['salidas']) }}
        </span>
    </div>

    {{-- ── Tabla ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                            Fecha
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Producto
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Concepto / Origen
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                            Tipo
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                            Cantidad
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                            Costo / Precio
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                            Stock
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                            Usuario
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse ($movimientos as $mov)
                        @php
                            $esEntrada  = $mov->tipo === 'entrada';
                            $origenInfo = $origenLabels[$mov->movible_type] ?? null;
                            $unitario   = $esEntrada ? $mov->costo_unitario : $mov->precio_unitario;
                            $total      = $esEntrada ? $mov->costo_total    : $mov->precio_total;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors duration-100">

                            {{-- Fecha --}}
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ \Carbon\Carbon::parse($mov->fecha)->format('H:i') }}
                                </div>
                            </td>

                            {{-- Producto --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-gray-100 max-w-[180px] truncate"
                                     title="{{ $mov->producto_nombre }}">
                                    {{ $mov->producto_nombre }}
                                </div>
                                @if ($mov->variante_nombre)
                                    <div class="text-xs text-gray-400 dark:text-gray-500 max-w-[180px] truncate"
                                         title="{{ $mov->variante_nombre }}">
                                        {{ $mov->variante_nombre }}
                                    </div>
                                @endif
                            </td>

                            {{-- Concepto / Origen --}}
                            <td class="px-4 py-3">
                                <div class="text-gray-800 dark:text-gray-200 max-w-[200px]">
                                    {{ $mov->concepto }}
                                </div>
                                @if ($mov->notas)
                                    <div class="text-xs text-gray-400 dark:text-gray-500 max-w-[200px] truncate"
                                         title="{{ $mov->notas }}">
                                        {{ $mov->notas }}
                                    </div>
                                @endif
                                @if ($origenInfo)
                                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border
                                                 {{ $origenInfo['bg'] }} {{ $origenInfo['color'] }}">
                                        {{ $origenInfo['label'] }}
                                    </span>
                                @endif
                            </td>

                            {{-- Tipo --}}
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($esEntrada)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                                                 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-400
                                                 border border-green-200 dark:border-green-700">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                        </svg>
                                        Entrada
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                                                 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400
                                                 border border-red-200 dark:border-red-700">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                        </svg>
                                        Salida
                                    </span>
                                @endif
                            </td>

                            {{-- Cantidad --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="font-semibold {{ $esEntrada ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                    {{ $esEntrada ? '+' : '-' }}{{ number_format($mov->cantidad, 2) }}
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $mov->unidad }}</span>
                                </div>
                                @if ($mov->factor_conversion && $mov->factor_conversion != 1)
                                    <div class="text-xs text-gray-400 dark:text-gray-500">
                                        = {{ number_format($mov->cantidad_base, 2) }} unidades
                                    </div>
                                @endif
                            </td>

                            {{-- Costo / Precio --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($unitario !== null)
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        S/ {{ number_format($unitario, 2) }}
                                    </div>
                                    @if ($total !== null)
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            Total: S/ {{ number_format($total, 2) }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>

                            {{-- Stock --}}
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1 text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">{{ number_format($mov->stock_antes, 2) }}</span>
                                    <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                    <span class="font-semibold {{ $mov->stock_despues > $mov->stock_antes ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($mov->stock_despues, 2) }}
                                    </span>
                                </div>
                            </td>

                            {{-- Usuario --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($mov->user)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center
                                                    text-xs font-bold text-primary-700 dark:text-primary-400 flex-shrink-0">
                                            {{ strtoupper(substr($mov->user->name ?? '?', 0, 1)) }}
                                        </div>
                                        <span class="text-xs text-gray-700 dark:text-gray-300 max-w-[100px] truncate"
                                              title="{{ $mov->user->name }}">
                                            {{ $mov->user->name }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600 text-xs">Sistema</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        No hay movimientos para los filtros seleccionados
                                    </p>
                                    <button wire:click="limpiarFiltros"
                                            class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                                        Limpiar filtros
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        @if ($movimientos->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                {{ $movimientos->links() }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
