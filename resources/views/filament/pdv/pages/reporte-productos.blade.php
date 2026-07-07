@php
    $productos = $this->getProductos();
@endphp

<x-filament-panels::page>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/reporte-productos.css') }}">
    @endpush

    {{-- ── Encabezado ──────────────────────────────────────────────── --}}
    <div class="rp-header">
        <h1 class="rp-titulo">Reporte de Productos más vendidos</h1>
        <p class="rp-subtitulo">Resumen de ventas agrupado por producto</p>
    </div>

    {{-- ── Filtros ──────────────────────────────────────────────────── --}}
    <div class="rp-filtros">
        {{ $this->form }}
        @if($this->hayFiltros())
            <div class="rp-filtros__limpiar">
                <button wire:click="limpiarFiltros" class="rp-btn-limpiar">
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    {{-- ── Tabla ────────────────────────────────────────────────────── --}}
    <div class="rp-tabla-wrap">
        @if($productos->isEmpty())
            <div class="rp-empty">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/>
                </svg>
                <p>No hay ventas registradas para los filtros seleccionados.</p>
            </div>
        @else
            <div class="rp-tabla-scroll">
            <table class="rp-tabla">
                <thead>
                    <tr>
                        <th class="rp-col-rank">#</th>
                        <th class="rp-col-cat">Categoría</th>
                        <th class="rp-col-prod">Producto</th>
                        <th class="rp-col-num">Unidades vendidas</th>
                        <th class="rp-col-num">Ventas (S/)</th>
                        <th class="rp-col-num">Costos (S/)</th>
                        <th class="rp-col-num">Utilidad (S/)</th>
                    </tr>
                </thead>
                <tbody>
                    @php $offset = ($productos->currentPage() - 1) * $productos->perPage(); @endphp
                    @foreach($productos as $i => $p)
                        @php $utilidad = (float) $p->utilidad; @endphp
                        <tr>
                            <td class="rp-col-rank">
                                <span class="rp-rank">{{ $offset + $i + 1 }}</span>
                            </td>
                            <td class="rp-col-cat">
                                <span class="rp-badge-cat">{{ $p->categoria }}</span>
                            </td>
                            <td class="rp-col-prod">{{ $p->descripcion }}</td>
                            <td class="rp-col-num rp-fw">
                                {{ number_format((float)$p->qty, 2) }}
                                <span class="rp-unidad">{{ $p->unidad }}</span>
                            </td>
                            <td class="rp-col-num rp-blue">S/ {{ number_format((float)$p->ingresos, 2) }}</td>
                            <td class="rp-col-num rp-muted">S/ {{ number_format((float)$p->costo, 2) }}</td>
                            <td class="rp-col-num {{ $utilidad >= 0 ? 'rp-green' : 'rp-red' }}">
                                S/ {{ number_format($utilidad, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>{{-- /rp-tabla-scroll --}}

            @if($productos->hasPages())
                <div class="rp-pagination">
                    {{ $productos->links('vendor.pagination.pdv') }}
                </div>
            @endif
        @endif
    </div>

</x-filament-panels::page>
