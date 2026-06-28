<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/ventas-sesion.css') }}?v={{ filemtime(public_path('css/ventas-sesion.css')) }}">
<link rel="stylesheet" href="{{ asset('css/reporte-ganancias.css') }}?v={{ filemtime(public_path('css/reporte-ganancias.css')) }}">
<link rel="stylesheet" href="{{ asset('css/cierres-caja.css') }}?v={{ filemtime(public_path('css/cierres-caja.css')) }}">

@php $sesiones = $this->getSesiones(); @endphp

<div class="vs-root">

    {{-- ══ TÍTULO ══ --}}
    <div class="vs-title">
        <div>
            <h1>Cierres de Caja</h1>
            <p>Historial de sesiones y reportes por turno</p>
        </div>
    </div>

    {{-- ══ FILTROS ══ --}}
    <div class="rg-form-wrap">
        {{ $this->form }}
        @if($this->hayFiltros())
            <div class="rg-form-limpiar">
                <button wire:click="limpiarFiltros" class="vs-filter-reset">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                    Limpiar filtros
                </button>
            </div>
        @endif
    </div>

    {{-- ══ TABLA DE SESIONES ══ --}}
    <div class="vs-panel">
        @if($sesiones->isEmpty())
            <div class="rg-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                </svg>
                <p>No hay sesiones registradas</p>
            </div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>Caja / Cajero</th>
                            <th>Apertura</th>
                            <th>Cierre</th>
                            <th>Duración</th>
                            <th>Estado</th>
                            <th class="rg-th-right">Total sistema</th>
                            <th style="width:7rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sesiones as $ses)
                            @php
                                $dur = $ses->fecha_cierre
                                    ? $ses->fecha_apertura->diffForHumans($ses->fecha_cierre, true, false, 2)
                                    : '—';
                                $esAbierta = $ses->estado?->value === 'abierta';
                            @endphp
                            <tr wire:key="ses-{{ $ses->id }}">
                                <td>
                                    <div style="display:flex;flex-direction:column;gap:.1rem">
                                        <span style="font-weight:700;font-size:.8rem">{{ $ses->caja?->nombre ?? 'Caja' }}</span>
                                        <span style="font-size:.72rem;color:var(--vs-text-muted)">{{ $ses->cajero?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ $ses->fecha_apertura->format('d/m/Y') }}</span>
                                        <span class="rg-fecha__hora">{{ $ses->fecha_apertura->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if($ses->fecha_cierre)
                                        <div class="rg-fecha">
                                            <span class="rg-fecha__dia">{{ $ses->fecha_cierre->format('d/m/Y') }}</span>
                                            <span class="rg-fecha__hora">{{ $ses->fecha_cierre->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span style="color:var(--vs-text-faint);font-size:.75rem">En curso</span>
                                    @endif
                                </td>
                                <td style="font-size:.78rem;color:var(--vs-text-muted)">{{ $dur }}</td>
                                <td>
                                    <span class="cc-badge {{ $esAbierta ? 'cc-badge--open' : 'cc-badge--closed' }}">
                                        {{ $esAbierta ? 'Abierta' : 'Cerrada' }}
                                    </span>
                                    @if($ses->tiene_cuadre)
                                        <span class="cc-badge cc-badge--cuadre" style="margin-left:.25rem">Cuadre</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span style="font-weight:700;font-size:.8rem">S/ {{ number_format($ses->total_sistema ?? 0, 2) }}</span>
                                </td>
                                <td style="text-align:right">
                                    <button wire:click="abrirReporte({{ $ses->id }})" class="cc-btn-ver" wire:loading.attr="disabled">
                                        Ver reporte
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($sesiones->hasPages())
                <div class="rg-pagination">{{ $sesiones->links() }}</div>
            @endif
        @endif
    </div>

</div>{{-- /vs-root --}}


{{-- ══════════════════════════════════════════════════════════════════════
     MODAL DE REPORTE CON TABS
══════════════════════════════════════════════════════════════════════ --}}
@if($sesionId)

@php $sesInfo = $this->getSesionInfo(); @endphp

<div class="cc-overlay" wire:click.self="cerrarReporte">
<div class="cc-modal">

    {{-- ── CABECERA DEL MODAL ── --}}
    <div class="cc-modal__head">
        <div class="cc-modal__title">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
            </svg>
            <div>
                <span class="cc-modal__label">Reporte de Sesión</span>
                <span class="cc-modal__sub">
                    {{ $sesInfo?->caja?->nombre ?? 'Caja' }} ·
                    {{ $sesInfo?->cajero?->name ?? '—' }} ·
                    {{ $sesInfo?->fecha_apertura?->format('d/m/Y') ?? '' }}
                </span>
            </div>
        </div>
        <button wire:click="cerrarReporte" class="cc-modal__close" title="Cerrar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="18" height="18">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ── BARRA DE TABS ── --}}
    <div class="cc-tabs">
        @foreach([
            ['resumen',     'Resumen'],
            ['ventas',      'Ventas'],
            ['productos',   'Productos'],
            ['metodos',     'Métodos de pago'],
            ['cortesias',   'Cortesías'],
            ['movimientos', 'Movimientos'],
            ['info',        'Información'],
        ] as [$key, $label])
            <button wire:click="setTab('{{ $key }}')"
                    class="cc-tab {{ $tabReporte === $key ? 'cc-tab--activo' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Barra de carga visible durante navegación de tabs --}}
    <div class="cc-loading-bar" wire:loading wire:target="setTab,setSubTabVentas,setSubTabMov"></div>


    {{-- ════════════════════════════════════════
         TAB: RESUMEN
    ════════════════════════════════════════ --}}
    @if($tabReporte === 'resumen')
    @php $r = $this->getResumen(); @endphp
    <div class="cc-tab-body">

        <div class="cc-kpi-grid">

            <div class="cc-kpi cc-kpi--blue">
                <span class="cc-kpi__label">Ventas completadas</span>
                <span class="cc-kpi__value">{{ number_format($r['cnt_comp'] ?? 0) }}</span>
                <span class="cc-kpi__sub">aprobadas en esta sesión</span>
            </div>

            <div class="cc-kpi cc-kpi--red">
                <span class="cc-kpi__label">Ventas anuladas</span>
                <span class="cc-kpi__value">{{ number_format($r['cnt_anu'] ?? 0) }}</span>
                <span class="cc-kpi__sub">S/ {{ number_format($r['tot_anu'] ?? 0, 2) }} anulados</span>
            </div>

            <div class="cc-kpi cc-kpi--indigo">
                <span class="cc-kpi__label">Total facturado</span>
                <span class="cc-kpi__value">S/ {{ number_format($r['tot_total'] ?? 0, 2) }}</span>
                <span class="cc-kpi__sub">suma de todas las ventas</span>
            </div>

            <div class="cc-kpi cc-kpi--purple">
                <span class="cc-kpi__label">IGV facturado</span>
                <span class="cc-kpi__value">S/ {{ number_format($r['igv'] ?? 0, 2) }}</span>
                <span class="cc-kpi__sub">solo facturas y boletas (18%)</span>
            </div>

            <div class="cc-kpi cc-kpi--sky">
                <span class="cc-kpi__label">Total sin IGV</span>
                <span class="cc-kpi__value">S/ {{ number_format($r['neta'] ?? 0, 2) }}</span>
                <span class="cc-kpi__sub">total − IGV (tickets incluidos)</span>
            </div>

            <div class="cc-kpi cc-kpi--orange">
                <span class="cc-kpi__label">Costo de ventas</span>
                <span class="cc-kpi__value">S/ {{ number_format($r['costo'] ?? 0, 2) }}</span>
                <span class="cc-kpi__sub">costo de los productos vendidos</span>
            </div>

            <div class="cc-kpi cc-kpi--green">
                <span class="cc-kpi__label">Utilidad bruta</span>
                <span class="cc-kpi__value">S/ {{ number_format($r['utilidad'] ?? 0, 2) }}</span>
                <span class="cc-kpi__sub">total sin IGV − costo</span>
            </div>

            <div class="cc-kpi cc-kpi--teal">
                <span class="cc-kpi__label">Margen bruto</span>
                <span class="cc-kpi__value">{{ number_format($r['margen'] ?? 0, 1) }}%</span>
                <span class="cc-kpi__sub">utilidad / ventas netas</span>
            </div>

            @if(($r['descuento'] ?? 0) > 0)
            <div class="cc-kpi cc-kpi--amber">
                <span class="cc-kpi__label">Descuentos</span>
                <span class="cc-kpi__value">S/ {{ number_format($r['descuento'], 2) }}</span>
                <span class="cc-kpi__sub">total descontado en ventas</span>
            </div>
            @endif

        </div>

        {{-- Cuadre de caja --}}
        @if(($r['total_sistema'] ?? 0) > 0 || ($r['total_cajero'] ?? 0) > 0)
        <div class="cc-cuadre-strip">
            <div class="cc-cuadre-item">
                <span class="cc-cuadre-item__label">Fondo de apertura</span>
                <span class="cc-cuadre-item__val">S/ {{ number_format($r['monto_apertura'] ?? 0, 2) }}</span>
            </div>
            <div class="cc-cuadre-item">
                <span class="cc-cuadre-item__label">Total sistema</span>
                <span class="cc-cuadre-item__val">S/ {{ number_format($r['total_sistema'] ?? 0, 2) }}</span>
                <span class="cc-cuadre-item__sub">ventas + fondo</span>
            </div>
            <div class="cc-cuadre-item">
                <span class="cc-cuadre-item__label">Total cajero</span>
                <span class="cc-cuadre-item__val">S/ {{ number_format($r['total_cajero'] ?? 0, 2) }}</span>
            </div>
            @if(($r['total_creditos'] ?? 0) > 0)
            <div class="cc-cuadre-item cc-cuadre-item--credit">
                <span class="cc-cuadre-item__label">Créditos otorgados</span>
                <span class="cc-cuadre-item__val">S/ {{ number_format($r['total_creditos'], 2) }}</span>
                <span class="cc-cuadre-item__sub">no se cuentan físicamente</span>
            </div>
            @endif
            @php $dif = (float)($r['diferencia'] ?? 0); @endphp
            <div class="cc-cuadre-item {{ $dif == 0 ? 'cc-cuadre-item--ok' : ($dif > 0 ? 'cc-cuadre-item--pos' : 'cc-cuadre-item--neg') }}">
                <span class="cc-cuadre-item__label">Diferencia</span>
                <span class="cc-cuadre-item__val">{{ $dif >= 0 ? '+' : '' }}S/ {{ number_format($dif, 2) }}</span>
                <span class="cc-cuadre-item__sub">cajero − sistema</span>
            </div>
        </div>
        @endif

    </div>
    @endif


    {{-- ════════════════════════════════════════
         TAB: VENTAS
    ════════════════════════════════════════ --}}
    @if($tabReporte === 'ventas')
    @php $ventasTab = $this->getVentasTab(); @endphp
    <div class="cc-tab-body">

        <div class="cc-subtabs">
            <button wire:click="setSubTabVentas('aprobadas')"
                    class="cc-subtab {{ $subTabVentas === 'aprobadas' ? 'cc-subtab--activo cc-subtab--green' : '' }}">
                Aprobadas
            </button>
            <button wire:click="setSubTabVentas('anuladas')"
                    class="cc-subtab {{ $subTabVentas === 'anuladas' ? 'cc-subtab--activo cc-subtab--red' : '' }}">
                Anuladas
            </button>
        </div>

        @if($ventasTab->isEmpty())
            <div class="cc-empty">No hay ventas {{ $subTabVentas }} en esta sesión.</div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha / Hora</th>
                            <th>Cliente</th>
                            <th>Pago</th>
                            <th class="rg-th-right">Ítems</th>
                            <th class="rg-th-right">Total</th>
                            <th class="rg-th-right">Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ventasTab as $v)
                            @php $esCredito = ($v->estado_pago ?? 'pagado') === 'pendiente'; @endphp
                            <tr wire:key="vtab-{{ $v->id }}">
                                <td>
                                    <span class="rg-comprobante">
                                        {{ ($v->serie?->serie ?? '—') . '-' . $v->correlativo }}
                                    </span>
                                </td>
                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ $v->created_at->format('d/m/Y') }}</span>
                                        <span class="rg-fecha__hora">{{ $v->created_at->format('H:i:s') }}</span>
                                    </div>
                                </td>
                                <td style="font-size:.78rem">{{ $v->cliente_nombre ?: '—' }}</td>
                                <td>
                                    @if($esCredito)
                                        <span class="cc-badge" style="background:#fef3c7;color:#d97706">Crédito</span>
                                        @if(($v->saldo_pendiente ?? 0) > 0)
                                            <span style="display:block;font-size:.68rem;color:#d97706;margin-top:.15rem">
                                                Saldo: S/ {{ number_format($v->saldo_pendiente, 2) }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="cc-badge cc-badge--green">Pagado</span>
                                    @endif
                                </td>
                                <td class="rg-td-right" style="font-size:.78rem;color:var(--vs-text-muted)">
                                    {{ $v->detalles_count }}
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format($v->total, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto rg-monto--costo">S/ {{ number_format($v->costo_total, 2) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($ventasTab->hasPages())
                <div class="rg-pagination">{{ $ventasTab->links() }}</div>
            @endif
        @endif

    </div>
    @endif


    {{-- ════════════════════════════════════════
         TAB: PRODUCTOS VENDIDOS
    ════════════════════════════════════════ --}}
    @if($tabReporte === 'productos')
    @php $prodTab = $this->getProductosTab(); @endphp
    <div class="cc-tab-body">

        <p class="cc-tab-hint">Productos de ventas aprobadas, ordenados por cantidad vendida (de mayor a menor).</p>

        @if($prodTab->isEmpty())
            <div class="cc-empty">No hay productos vendidos en esta sesión.</div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th>Producto</th>
                            <th class="rg-th-right">Cantidad</th>
                            <th class="rg-th-right">En ventas</th>
                            <th class="rg-th-right">Total recaudado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $offset = ($prodTab->currentPage() - 1) * $prodTab->perPage(); @endphp
                        @foreach($prodTab as $i => $prod)
                            <tr wire:key="prod-{{ $loop->index }}">
                                <td style="color:var(--vs-text-faint);font-size:.75rem">{{ $offset + $i + 1 }}</td>
                                <td style="font-weight:600;font-size:.82rem">{{ $prod->descripcion }}</td>
                                <td class="rg-td-right">
                                    <span class="cc-qty-badge">
                                        {{ $prod->qty == floor($prod->qty)
                                            ? number_format($prod->qty, 0)
                                            : number_format($prod->qty, 3) }}
                                    </span>
                                </td>
                                <td class="rg-td-right" style="color:var(--vs-text-muted);font-size:.78rem">
                                    {{ $prod->en_ventas }} venta{{ $prod->en_ventas != 1 ? 's' : '' }}
                                </td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format($prod->tot, 2) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($prodTab->hasPages())
                <div class="rg-pagination">{{ $prodTab->links() }}</div>
            @endif
        @endif

    </div>
    @endif


    {{-- ════════════════════════════════════════
         TAB: MÉTODOS DE PAGO
    ════════════════════════════════════════ --}}
    @if($tabReporte === 'metodos')
    @php $metTab = $this->getMetodosYComprobantesTab(); @endphp
    <div class="cc-tab-body">

        <div class="cc-two-col">

            {{-- Por método de pago --}}
            <div class="cc-section" style="overflow-x:auto;">
                <div class="cc-section__head">Por método de pago</div>

                @if($metTab['metodos']->isEmpty())
                    <div class="cc-empty">Sin pagos registrados.</div>
                @else
                    @php $cuadreMap = $metTab['cuadre']->keyBy('metodo_pago_id'); @endphp
                    <table class="rg-table">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th class="rg-th-right">Sistema</th>
                                <th class="rg-th-right">Cajero</th>
                                <th class="rg-th-right">Diferencia</th>
                                <th class="rg-th-right">Trans.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metTab['metodos'] as $met)
                                @php
                                    $cup    = isset($met['metodo_pago_id']) ? ($cuadreMap[$met['metodo_pago_id']] ?? null) : null;
                                    $cajero = $cup ? (float)$cup->importe_cajero : null;
                                    $diff   = $cajero !== null ? ($cajero - (float)$met['sistema']) : null;
                                @endphp
                                <tr wire:key="met-{{ $loop->index }}">
                                    <td style="font-weight:600;font-size:.82rem">{{ $met['nombre'] }}</td>
                                    <td class="rg-td-right">
                                        <span class="rg-monto">S/ {{ number_format($met['sistema'], 2) }}</span>
                                    </td>
                                    <td class="rg-td-right">
                                        @if($cajero !== null)
                                            <span class="rg-monto">S/ {{ number_format($cajero, 2) }}</span>
                                        @else
                                            <span style="color:var(--vs-text-faint)">—</span>
                                        @endif
                                    </td>
                                    <td class="rg-td-right">
                                        @if($diff !== null)
                                            <span class="cc-diff {{ $diff == 0 ? 'cc-diff--ok' : ($diff > 0 ? 'cc-diff--pos' : 'cc-diff--neg') }}">
                                                {{ $diff >= 0 ? '+' : '' }}S/ {{ number_format($diff, 2) }}
                                            </span>
                                        @else
                                            <span style="color:var(--vs-text-faint)">—</span>
                                        @endif
                                    </td>
                                    <td class="rg-td-right" style="color:var(--vs-text-muted);font-size:.78rem">
                                        {{ $met['count'] }}
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Por tipo de comprobante --}}
            <div class="cc-section" style="overflow-x:auto;">
                <div class="cc-section__head">Por tipo de comprobante</div>
                @if($metTab['comprobantes']->isEmpty())
                    <div class="cc-empty">Sin ventas.</div>
                @else
                    <table class="rg-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th class="rg-th-right">Cantidad</th>
                                <th class="rg-th-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metTab['comprobantes'] as $comp)
                                @php
                                    $tipoLabel = match(strtolower($comp->tipo)) {
                                        'factura' => 'Factura',
                                        'boleta'  => 'Boleta',
                                        'ticket'  => 'Ticket / Nota',
                                        default   => ucfirst($comp->tipo),
                                    };
                                    $tipoClass = match(strtolower($comp->tipo)) {
                                        'factura' => 'cc-badge--blue',
                                        'boleta'  => 'cc-badge--green',
                                        default   => 'cc-badge--gray',
                                    };
                                @endphp
                                <tr wire:key="comp-{{ $loop->index }}">
                                    <td><span class="cc-badge {{ $tipoClass }}">{{ $tipoLabel }}</span></td>
                                    <td class="rg-td-right" style="font-weight:600">{{ $comp->count }}</td>
                                    <td class="rg-td-right">
                                        <span class="rg-monto">S/ {{ number_format($comp->total, 2) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>{{-- /cc-two-col --}}
    </div>
    @endif


    {{-- ════════════════════════════════════════
         TAB: CORTESÍAS
    ════════════════════════════════════════ --}}
    @if($tabReporte === 'cortesias')
    @php
        $cortProds  = $this->getCortesiasProductos();
        $cortVentas = $this->getCortesiasVentas();
    @endphp
    <div class="cc-tab-body">

        {{-- Productos en cortesía --}}
        <div class="cc-section" style="margin-bottom:1.25rem">
            <div class="cc-section__head">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
                </svg>
                Productos entregados en cortesía
            </div>

            @if($cortProds->isEmpty())
                <div class="cc-empty">No se dieron cortesías en esta sesión.</div>
            @else
                <table class="rg-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="rg-th-right">Cantidad dada</th>
                            <th class="rg-th-right">En ventas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cortProds as $cp)
                            <tr wire:key="cp-{{ $loop->index }}">
                                <td style="font-weight:600;font-size:.82rem">{{ $cp->descripcion }}</td>
                                <td class="rg-td-right">
                                    <span class="cc-qty-badge cc-qty-badge--gift">
                                        {{ $cp->qty == floor($cp->qty)
                                            ? number_format($cp->qty, 0)
                                            : number_format($cp->qty, 3) }}
                                    </span>
                                </td>
                                <td class="rg-td-right" style="color:var(--vs-text-muted);font-size:.78rem">
                                    {{ $cp->en_ventas }} venta{{ $cp->en_ventas != 1 ? 's' : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Ventas con cortesías --}}
        <div class="cc-section">
            <div class="cc-section__head">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
                Ventas que incluyeron cortesías
                <span style="margin-left:auto;font-size:.72rem;font-weight:400;color:var(--vs-text-faint)">ordenadas por fecha y hora</span>
            </div>

            @if($cortVentas->isEmpty())
                <div class="cc-empty">No hay ventas con cortesías.</div>
            @else
                <table class="rg-table">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Fecha / Hora</th>
                            <th>Cliente</th>
                            <th class="rg-th-right">Total venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cortVentas as $cv)
                            <tr wire:key="cv-{{ $cv->id }}">
                                <td><span class="rg-comprobante">{{ ($cv->serie?->serie ?? '—') . '-' . $cv->correlativo }}</span></td>
                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ $cv->created_at->format('d/m/Y') }}</span>
                                        <span class="rg-fecha__hora">{{ $cv->created_at->format('H:i:s') }}</span>
                                    </div>
                                </td>
                                <td style="font-size:.78rem">{{ $cv->cliente_nombre ?: '—' }}</td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format($cv->total, 2) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($cortVentas->hasPages())
                    <div class="rg-pagination">{{ $cortVentas->links() }}</div>
                @endif
            @endif
        </div>

    </div>
    @endif


    {{-- ════════════════════════════════════════
         TAB: MOVIMIENTOS
    ════════════════════════════════════════ --}}
    @if($tabReporte === 'movimientos')
    @php
        $movTab    = $this->getMovimientosTab();
        $movTotals = $this->getMovimientosTotales();
    @endphp
    <div class="cc-tab-body">

        {{-- Mini KPIs de movimientos --}}
        <div class="cc-mov-resumen">
            <div class="cc-mov-kpi cc-mov-kpi--ing">
                <span>Ingresos aprobados</span>
                <strong>S/ {{ number_format($movTotals['ing_apr_tot'], 2) }}</strong>
                <em>{{ $movTotals['ing_apr_cnt'] }} registro{{ $movTotals['ing_apr_cnt'] != 1 ? 's' : '' }}</em>
            </div>
            <div class="cc-mov-kpi cc-mov-kpi--ing-anu">
                <span>Ingresos anulados</span>
                <strong>S/ {{ number_format($movTotals['ing_anu_tot'], 2) }}</strong>
                <em>{{ $movTotals['ing_anu_cnt'] }} registro{{ $movTotals['ing_anu_cnt'] != 1 ? 's' : '' }}</em>
            </div>
            <div class="cc-mov-kpi cc-mov-kpi--egr">
                <span>Egresos aprobados</span>
                <strong>S/ {{ number_format($movTotals['egr_apr_tot'], 2) }}</strong>
                <em>{{ $movTotals['egr_apr_cnt'] }} registro{{ $movTotals['egr_apr_cnt'] != 1 ? 's' : '' }}</em>
            </div>
            <div class="cc-mov-kpi cc-mov-kpi--egr-anu">
                <span>Egresos anulados</span>
                <strong>S/ {{ number_format($movTotals['egr_anu_tot'], 2) }}</strong>
                <em>{{ $movTotals['egr_anu_cnt'] }} registro{{ $movTotals['egr_anu_cnt'] != 1 ? 's' : '' }}</em>
            </div>
        </div>

        {{-- Sub-tabs --}}
        <div class="cc-subtabs">
            @foreach([
                ['ing_apr', 'Ingresos aprobados',  'cc-subtab--green'],
                ['ing_anu', 'Ingresos anulados',   'cc-subtab--red'],
                ['egr_apr', 'Egresos aprobados',   'cc-subtab--orange'],
                ['egr_anu', 'Egresos anulados',    'cc-subtab--gray'],
            ] as [$key, $label, $activeClass])
                <button wire:click="setSubTabMov('{{ $key }}')"
                        class="cc-subtab {{ $subTabMov === $key ? 'cc-subtab--activo ' . $activeClass : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @php
            $movLabel = match($subTabMov) {
                'ing_apr' => 'ingresos aprobados',
                'ing_anu' => 'ingresos anulados',
                'egr_apr' => 'egresos aprobados',
                'egr_anu' => 'egresos anulados',
                default   => 'movimientos',
            };
        @endphp

        @if($movTab->isEmpty())
            <div class="cc-empty">No hay {{ $movLabel }} en esta sesión.</div>
        @else
            <div class="rg-table-scroll">
                <table class="rg-table rg-table--wide">
                    <thead>
                        <tr>
                            <th>Fecha / Hora</th>
                            <th>Concepto</th>
                            <th>Método</th>
                            <th class="rg-th-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movTab as $mov)
                            <tr wire:key="mov-{{ $mov->id }}">
                                <td>
                                    <div class="rg-fecha">
                                        <span class="rg-fecha__dia">{{ $mov->fecha?->format('d/m/Y') ?? '—' }}</span>
                                        <span class="rg-fecha__hora">{{ $mov->fecha?->format('H:i:s') ?? '' }}</span>
                                    </div>
                                </td>
                                <td style="font-size:.82rem">{{ $mov->concepto ?: '—' }}</td>
                                <td style="font-size:.78rem;color:var(--vs-text-muted)">{{ $mov->metodoPago?->nombre ?? '—' }}</td>
                                <td class="rg-td-right">
                                    @php $esIngreso = str_starts_with($subTabMov, 'ing_'); @endphp
                                    <span class="rg-monto {{ $esIngreso ? 'rg-monto--pos' : 'rg-monto--neg' }}">
                                        {{ $esIngreso ? '+' : '−' }}S/ {{ number_format($mov->monto, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($movTab->hasPages())
                <div class="rg-pagination">{{ $movTab->links() }}</div>
            @endif
        @endif

    </div>
    @endif


    {{-- ════════════════════════════════════════
         TAB: INFORMACIÓN DE SESIÓN
    ════════════════════════════════════════ --}}
    @if($tabReporte === 'info')
    <div class="cc-tab-body">
        @if($sesInfo)
        @php
            $dur = $sesInfo->fecha_cierre
                ? $sesInfo->fecha_apertura->diff($sesInfo->fecha_cierre)
                : null;
            $durStr = $dur
                ? sprintf('%dh %02dm', $dur->h + ($dur->days * 24), $dur->i)
                : 'En curso';
        @endphp

        <div class="cc-info-grid">

            <div class="cc-info-section">
                <div class="cc-info-section__head">Datos de la sesión</div>
                <dl class="cc-info-dl">
                    <dt>Caja</dt>
                    <dd>{{ $sesInfo->caja?->nombre ?? '—' }}</dd>

                    <dt>Cajero</dt>
                    <dd>{{ $sesInfo->cajero?->name ?? '—' }}</dd>

                    <dt>Apertura</dt>
                    <dd>{{ $sesInfo->fecha_apertura?->format('d/m/Y H:i:s') ?? '—' }}</dd>

                    <dt>Cierre</dt>
                    <dd>{{ $sesInfo->fecha_cierre?->format('d/m/Y H:i:s') ?? '—' }}</dd>

                    <dt>Duración</dt>
                    <dd>{{ $durStr }}</dd>

                    <dt>Estado</dt>
                    <dd>
                        @php $esAb = $sesInfo->estado?->value === 'abierta'; @endphp
                        <span class="cc-badge {{ $esAb ? 'cc-badge--open' : 'cc-badge--closed' }}">
                            {{ $esAb ? 'Abierta' : 'Cerrada' }}
                        </span>
                    </dd>

                    <dt>Monto de apertura</dt>
                    <dd>S/ {{ number_format($sesInfo->monto_apertura ?? 0, 2) }}</dd>
                </dl>
            </div>

            <div class="cc-info-section">
                <div class="cc-info-section__head">Cuadre de caja</div>
                <dl class="cc-info-dl">
                    <dt>Total sistema</dt>
                    <dd><strong>S/ {{ number_format($sesInfo->total_sistema ?? 0, 2) }}</strong></dd>

                    <dt>Total cajero</dt>
                    <dd>
                        @if($sesInfo->total_cajero !== null)
                            <strong>S/ {{ number_format($sesInfo->total_cajero, 2) }}</strong>
                        @else
                            <span style="color:var(--vs-text-faint)">Sin declarar</span>
                        @endif
                    </dd>

                    <dt>Diferencia</dt>
                    <dd>
                        @if($sesInfo->diferencia_total !== null)
                            @php $dif = (float)$sesInfo->diferencia_total; @endphp
                            <span class="cc-diff {{ $dif == 0 ? 'cc-diff--ok' : ($dif > 0 ? 'cc-diff--pos' : 'cc-diff--neg') }}">
                                {{ $dif >= 0 ? '+' : '' }}S/ {{ number_format($dif, 2) }}
                            </span>
                        @else
                            <span style="color:var(--vs-text-faint)">—</span>
                        @endif
                    </dd>

                    @if(($sesInfo->total_creditos ?? 0) > 0)
                    <dt style="color:#d97706">Créditos otorgados</dt>
                    <dd>
                        <strong style="color:#d97706">S/ {{ number_format($sesInfo->total_creditos, 2) }}</strong>
                        <span style="font-size:.72rem;color:var(--vs-text-faint);margin-left:.5rem">no se cuentan físicamente</span>
                    </dd>
                    @endif
                </dl>
            </div>

            @if($sesInfo->notas_cierre)
            <div class="cc-info-section" style="grid-column:1/-1">
                <div class="cc-info-section__head">Notas de cierre</div>
                <p class="cc-info-notas">{{ $sesInfo->notas_cierre }}</p>
            </div>
            @endif

            @if($sesInfo->pagos->isNotEmpty())
            <div class="cc-info-section" style="grid-column:1/-1; overflow-x:auto;">
                <div class="cc-info-section__head">Cuadre detallado por método de pago</div>
                <table class="rg-table">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th class="rg-th-right">Sistema</th>
                            <th class="rg-th-right">Cajero</th>
                            <th class="rg-th-right">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sesInfo->pagos as $pag)
                            @php $dPag = (float)($pag->importe_cajero ?? 0) - (float)$pag->importe_sistema; @endphp
                            <tr wire:key="pag-{{ $pag->id }}">
                                <td style="font-weight:600;font-size:.82rem">{{ $pag->metodoPago?->nombre ?? '—' }}</td>
                                <td class="rg-td-right">
                                    <span class="rg-monto">S/ {{ number_format($pag->importe_sistema, 2) }}</span>
                                </td>
                                <td class="rg-td-right">
                                    @if($pag->importe_cajero !== null)
                                        <span class="rg-monto">S/ {{ number_format($pag->importe_cajero, 2) }}</span>
                                    @else
                                        <span style="color:var(--vs-text-faint)">—</span>
                                    @endif
                                </td>
                                <td class="rg-td-right">
                                    <span class="cc-diff {{ $dPag == 0 ? 'cc-diff--ok' : ($dPag > 0 ? 'cc-diff--pos' : 'cc-diff--neg') }}">
                                        {{ $dPag >= 0 ? '+' : '' }}S/ {{ number_format($dPag, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        </div>
        @else
            <div class="cc-empty">No se pudo cargar la información de la sesión.</div>
        @endif
    </div>
    @endif


</div>{{-- /cc-modal --}}
</div>{{-- /cc-overlay --}}
@endif

</x-filament-panels::page>
