<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">

@php
    $resumen  = $this->getResumen();
    $clientes = $this->getClientes();

    $urlDetalle = fn($c) =>
        \App\Filament\Pdv\Pages\ReporteClienteComprasPage::getUrl() . '?' .
        http_build_query(['clienteNombre' => $c->cliente, 'clienteNumDoc' => $c->num_doc]);
@endphp

<div class="vs-root">

    <div class="vs-title">
        <div>
            <h1>Reporte de Clientes</h1>
            <p>Clientes identificados en ventas completadas</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="rg-kpis">
        <div class="rg-kpi rg-kpi--gray">
            <span class="rg-kpi__label">Clientes</span>
            <span class="rg-kpi__value">{{ number_format($resumen['totalClientes']) }}</span>
            <span class="rg-kpi__sub">identificados</span>
        </div>
        <div class="rg-kpi rg-kpi--blue">
            <span class="rg-kpi__label">Compras</span>
            <span class="rg-kpi__value">{{ number_format($resumen['totalCompras']) }}</span>
            <span class="rg-kpi__sub">total de transacciones</span>
        </div>
        <div class="rg-kpi rg-kpi--green">
            <span class="rg-kpi__label">Total facturado</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['totalGastado'], 2) }}</span>
            <span class="rg-kpi__sub">total comprobantes</span>
        </div>
        @if(($resumen['creditoPendiente'] ?? 0) > 0)
        <div class="rg-kpi rg-kpi--amber">
            <span class="rg-kpi__label">Crédito pendiente</span>
            <span class="rg-kpi__value">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</span>
            <span class="rg-kpi__sub">por cobrar</span>
        </div>
        @endif
    </div>

    {{-- Filtros --}}
    <div class="rg-form-wrap">{{ $this->form }}</div>

    {{-- Tabla --}}
    <div class="vs-panel">
        @if($clientes->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                </svg>
                <p>No hay ventas con cliente identificado en el período seleccionado</p>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Documento</th>
                            <th class="rg-th-right">N° Compras</th>
                            <th class="rg-th-right">Total facturado</th>
                            <th class="rg-th-right">Crédito pend.</th>
                            <th class="rg-th-right">Última compra</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $offset = ($clientes->currentPage() - 1) * $clientes->perPage(); @endphp
                        @foreach($clientes as $i => $c)
                            <tr wire:key="cli-{{ $loop->index }}">
                                <td class="rg-td-center" style="width:2.5rem;color:var(--vs-text-muted);font-size:.75rem;font-weight:700">
                                    {{ $offset + $i + 1 }}
                                </td>
                                <td>
                                    <span style="font-size:.8125rem;font-weight:700;color:var(--vs-text)">{{ $c->cliente }}</span>
                                </td>
                                <td>
                                    @if($c->num_doc)
                                        <span style="font-size:.75rem;font-family:ui-monospace,monospace;color:var(--vs-text-muted)">
                                            {{ strtoupper($c->tipo_doc) }} {{ $c->num_doc }}
                                        </span>
                                    @else
                                        <span style="color:var(--vs-text-faint)">—</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">{{ number_format($c->compras) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--pos">S/ {{ number_format((float)$c->total_gastado, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    @php $credCli = (float)($c->credito_pendiente ?? 0); @endphp
                                    @if($credCli > 0)
                                        <span class="vs-badge vs-badge--credito">S/ {{ number_format($credCli, 2) }}</span>
                                    @else
                                        <span style="color:var(--vs-text-faint)">—</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span style="font-size:.75rem;color:var(--vs-text-muted)">
                                        {{ $c->ultima_compra ? \Carbon\Carbon::parse($c->ultima_compra)->format('d/m/Y') : '—' }}
                                    </span>
                                </td>
                                <td class="rg-td-right">
                                    <a href="{{ $urlDetalle($c) }}" class="rg-btn-link">
                                        Ver compras →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($clientes->hasPages())
                <div class="rg-pagination">{{ $clientes->links() }}</div>
            @endif
        @endif
    </div>

</div>
</x-filament-panels::page>
