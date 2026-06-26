<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/kardex.css') }}?v={{ filemtime(public_path('css/kardex.css')) }}">

    @php
        $movimientos = $this->getMovimientos();
        $productos   = $this->getProductosParaFiltro();
        $resumen     = $this->getResumen();

        $origenMeta = [
            'App\\Models\\Ajuste' => ['label' => 'Ajuste',  'mod' => 'ajuste'],
            'App\\Models\\Compra' => ['label' => 'Compra',  'mod' => 'compra'],
            'App\\Models\\Venta'  => ['label' => 'Venta',   'mod' => 'venta'],
        ];
    @endphp

    {{-- ── Título ─────────────────────────────────────────────────────────── --}}
    <div class="kdx-title">
        <h1>Kardex de Inventario</h1>
        <p>Historial de movimientos de stock</p>
    </div>

    {{-- ── Panel filtros + tabla ──────────────────────────────────────────── --}}
    <div class="kdx-panel">

        {{-- Filtros --}}
        <div class="kdx-filters">
            <div class="kdx-filters-grid">

                {{-- Búsqueda --}}
                <div class="kdx-field">
                    <label>Buscar</label>
                    <div class="kdx-input-wrap">
                        <span class="kdx-input-icon">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                            </svg>
                        </span>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="busqueda"
                            placeholder="Producto, variante, concepto…"
                            class="kdx-input kdx-input--icon"
                        />
                    </div>
                </div>

                {{-- Producto --}}
                <div class="kdx-field">
                    <label>Producto</label>
                    <select wire:model.live="productoId" class="kdx-select">
                        <option value="">Todos los productos</option>
                        @foreach ($productos as $prod)
                            <option value="{{ $prod->id }}">
                                {{ $prod->nombre }}{{ $prod->estado !== 'activo' ? ' ('.$prod->estado.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha desde --}}
                <div class="kdx-field">
                    <label>Fecha desde</label>
                    <input type="date" wire:model.live="fechaDesde" class="kdx-input" />
                </div>

                {{-- Fecha hasta --}}
                <div class="kdx-field">
                    <label>Fecha hasta</label>
                    <input type="date" wire:model.live="fechaHasta" class="kdx-input" />
                </div>

                {{-- Tipo --}}
                <div class="kdx-field">
                    <label>Tipo</label>
                    <select wire:model.live="tipo" class="kdx-select">
                        <option value="">Todos</option>
                        <option value="entrada">Entrada</option>
                        <option value="salida">Salida</option>
                    </select>
                </div>

                {{-- Origen --}}
                <div class="kdx-field">
                    <label>Origen</label>
                    <select wire:model.live="origen" class="kdx-select">
                        <option value="">Todos</option>
                        <option value="App\Models\Ajuste">Ajuste</option>
                        <option value="App\Models\Compra">Compra</option>
                        <option value="App\Models\Venta">Venta</option>
                    </select>
                </div>

                {{-- Limpiar --}}
                <div class="kdx-field">
                    <label>&nbsp;</label>
                    <button wire:click="limpiarFiltros" class="kdx-btn-clear">
                        Limpiar filtros
                    </button>
                </div>

            </div>
        </div>

        {{-- Chips de resumen --}}
        <div style="padding: .75rem 1rem; border-bottom: 1px solid var(--kdx-border);">
            <div class="kdx-chips">
                <span class="kdx-chip kdx-chip--gray">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Total: {{ number_format($resumen['total']) }}
                </span>
                <span class="kdx-chip kdx-chip--green">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                    </svg>
                    Entradas: {{ number_format($resumen['entradas']) }}
                </span>
                <span class="kdx-chip kdx-chip--red">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                    Salidas: {{ number_format($resumen['salidas']) }}
                </span>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="kdx-table-wrap">
            <table class="kdx-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Concepto / Origen</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Costo / Precio</th>
                        <th class="text-center">Stock</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movimientos as $mov)
                        @php
                            $esEntrada  = $mov->tipo === 'entrada';
                            $meta       = $origenMeta[$mov->movible_type] ?? null;
                            $unitario   = $esEntrada ? $mov->costo_unitario  : $mov->precio_unitario;
                            $total      = $esEntrada ? $mov->costo_total     : $mov->precio_total;
                            $stockSube  = (float) $mov->stock_despues > (float) $mov->stock_antes;
                        @endphp
                        <tr>

                            {{-- Fecha --}}
                            <td class="nowrap">
                                <div class="kdx-fecha-dia">{{ \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}</div>
                                <div class="kdx-fecha-hora">{{ \Carbon\Carbon::parse($mov->fecha)->format('H:i') }}</div>
                            </td>

                            {{-- Producto --}}
                            <td>
                                <div class="kdx-prod-nombre" title="{{ $mov->producto_nombre }}">
                                    {{ $mov->producto_nombre }}
                                </div>
                                @if ($mov->variante_nombre)
                                    <div class="kdx-prod-variante" title="{{ $mov->variante_nombre }}">
                                        {{ $mov->variante_nombre }}
                                    </div>
                                @endif
                            </td>

                            {{-- Concepto / Origen --}}
                            <td>
                                <div class="kdx-concepto-texto">{{ $mov->concepto }}</div>
                                @if ($mov->notas)
                                    <div class="kdx-concepto-notas" title="{{ $mov->notas }}">
                                        {{ $mov->notas }}
                                    </div>
                                @endif
                                @if ($meta)
                                    <span class="kdx-badge kdx-badge--{{ $meta['mod'] }}">
                                        {{ $meta['label'] }}
                                    </span>
                                @endif
                            </td>

                            {{-- Tipo --}}
                            <td class="text-center nowrap">
                                @if ($esEntrada)
                                    <span class="kdx-tipo kdx-tipo--entrada">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                        </svg>
                                        Entrada
                                    </span>
                                @else
                                    <span class="kdx-tipo kdx-tipo--salida">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                        </svg>
                                        Salida
                                    </span>
                                @endif
                            </td>

                            {{-- Cantidad --}}
                            <td class="text-right nowrap">
                                <div class="kdx-qty {{ $esEntrada ? 'kdx-qty--entrada' : 'kdx-qty--salida' }}">
                                    {{ $esEntrada ? '+' : '-' }}{{ number_format((float)$mov->cantidad, 2) }}
                                    <span class="kdx-qty-unit">{{ $mov->unidad }}</span>
                                </div>
                                @if ((float)$mov->factor_conversion && (float)$mov->factor_conversion != 1)
                                    <div class="kdx-qty-base">
                                        = {{ number_format((float)$mov->cantidad_base, 2) }} uds.
                                    </div>
                                @endif
                            </td>

                            {{-- Costo / Precio --}}
                            <td class="text-right nowrap">
                                @if ($unitario !== null)
                                    <div class="kdx-monto-unit">S/ {{ number_format((float)$unitario, 2) }}</div>
                                    @if ($total !== null)
                                        <div class="kdx-monto-total">Total: S/ {{ number_format((float)$total, 2) }}</div>
                                    @endif
                                @else
                                    <span class="kdx-monto-dash">—</span>
                                @endif
                            </td>

                            {{-- Stock --}}
                            <td class="text-center nowrap">
                                <div class="kdx-stock">
                                    <span class="kdx-stock-antes">{{ number_format((float)$mov->stock_antes, 2) }}</span>
                                    <span class="kdx-stock-arrow">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                        </svg>
                                    </span>
                                    <span class="{{ $stockSube ? 'kdx-stock-up' : 'kdx-stock-down' }}">
                                        {{ number_format((float)$mov->stock_despues, 2) }}
                                    </span>
                                </div>
                            </td>

                            {{-- Usuario --}}
                            <td class="nowrap">
                                @if ($mov->user)
                                    <div class="kdx-user">
                                        <div class="kdx-avatar">
                                            {{ strtoupper(substr($mov->user->name ?? '?', 0, 1)) }}
                                        </div>
                                        <span class="kdx-user-name" title="{{ $mov->user->name }}">
                                            {{ $mov->user->name }}
                                        </span>
                                    </div>
                                @else
                                    <span class="kdx-user-sistema">Sistema</span>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="kdx-empty">
                                    <div class="kdx-empty-icon">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    <p>No hay movimientos para los filtros seleccionados</p>
                                    <button wire:click="limpiarFiltros" class="kdx-empty-link">
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
            <div class="kdx-pagination">
                {{ $movimientos->links() }}
            </div>
        @endif

    </div>
</x-filament-panels::page>
