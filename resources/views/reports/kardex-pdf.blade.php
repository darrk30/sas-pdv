<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 12mm 14mm 12mm 14mm; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.5px; color: #1a202c; background: #fff; }
.page { padding: 10mm 8mm; }

.header { background: #1E3A5F; color: #fff; padding: 7px 10px; margin-bottom: 6px; border-radius: 3px; }
.header table { width: 100%; border-collapse: collapse; }
.header-empresa { font-size: 11px; font-weight: bold; }
.header-ruc     { font-size: 8px; opacity: 0.85; margin-top: 2px; }
.header-right   { text-align: right; }
.header-reporte { font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
.header-meta    { font-size: 7.5px; opacity: 0.85; margin-top: 2px; }

/* Stats — tabla horizontal para DomPDF */
.stats-wrap { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 8px; }
.stat-box { width: 33.3%; border-radius: 3px; padding: 5px 8px; text-align: center; vertical-align: top; }
.stat-box--gray  { background: #EBF0F8; border-top: 3px solid #4A5568; }
.stat-box--green { background: #F0FDF4; border-top: 3px solid #22C55E; }
.stat-box--red   { background: #FEF2F2; border-top: 3px solid #EF4444; }
.stat-label { font-size: 7px; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; font-weight: bold; }
.stat-value { font-size: 16px; font-weight: bold; color: #1a202c; }
.stat-sub   { font-size: 6.5px; color: #9ca3af; }

.filtros-wrap { background: #EBF0F8; border-left: 3px solid #1E3A5F; padding: 4px 7px; margin-bottom: 7px; border-radius: 0 2px 2px 0; }
.filtros-titulo { font-size: 7.5px; font-weight: bold; color: #1E3A5F; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.4px; }
.filtros-grid   { display: flex; flex-wrap: wrap; gap: 2px 14px; }
.filtro-item    { font-size: 7px; }
.filtro-label   { font-weight: bold; color: #2C5282; }

table.main { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
thead tr { background: #1E3A5F; color: #fff; }
thead th {
    padding: 3px 4px; text-align: left; font-size: 7px;
    font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;
    border: 1px solid #164172; white-space: nowrap;
}
thead th.right  { text-align: right; }
thead th.center { text-align: center; }
tbody tr:nth-child(even) { background: #EBF0F8; }
tbody tr:nth-child(odd)  { background: #fff; }
tbody td { padding: 2.5px 4px; font-size: 7px; border: 1px solid #d1d9e6; vertical-align: middle; }
tbody td.right  { text-align: right; }
tbody td.center { text-align: center; }

.badge-entrada { background: #dcfce7; color: #166534; padding: 1px 4px; border-radius: 99px; font-size: 6.5px; font-weight: 700; }
.badge-salida  { background: #fee2e2; color: #991b1b; padding: 1px 4px; border-radius: 99px; font-size: 6.5px; font-weight: 700; }
.badge-ajuste  { background: #fef3c7; color: #92400e; padding: 1px 4px; border-radius: 99px; font-size: 6.5px; font-weight: 700; }
.badge-compra  { background: #dbeafe; color: #1e40af; padding: 1px 4px; border-radius: 99px; font-size: 6.5px; font-weight: 700; }
.badge-venta   { background: #ede9fe; color: #6d28d9; padding: 1px 4px; border-radius: 99px; font-size: 6.5px; font-weight: 700; }

.qty-entrada { color: #166534; font-weight: 700; }
.qty-salida  { color: #991b1b; font-weight: 700; }
.stock-up    { color: #166534; font-weight: 700; }
.stock-down  { color: #991b1b; font-weight: 700; }

.totals-row { background: #FFF3CD !important; font-weight: bold; }
.totals-row td { border-top: 2px solid #e2a800; }

.footer {
    margin-top: 8px; border-top: 1px solid #d1d9e6;
    padding-top: 3px; font-size: 6.5px; color: #9ca3af;
    display: flex; justify-content: space-between;
}
.num { font-variant-numeric: tabular-nums; }
</style>
</head>
<body>
<div class="page">

<div class="header">
    <table>
        <tr>
            <td>
                <div class="header-empresa">{{ strtoupper($empresa->nombre) }}</div>
                <div class="header-ruc">RUC {{ $empresa->ruc ?? '' }}</div>
            </td>
            <td class="header-right">
                <div class="header-reporte">Kardex de Inventario</div>
                <div class="header-meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- Stats horizontales --}}
<table class="stats-wrap">
    <tr>
        <td class="stat-box stat-box--gray">
            <div class="stat-label">Total</div>
            <div class="stat-value">{{ number_format($resumen['total']) }}</div>
            <div class="stat-sub">movimientos</div>
        </td>
        <td class="stat-box stat-box--green">
            <div class="stat-label">Entradas</div>
            <div class="stat-value">{{ number_format($resumen['entradas']) }}</div>
            <div class="stat-sub">ingresos de stock</div>
        </td>
        <td class="stat-box stat-box--red">
            <div class="stat-label">Salidas</div>
            <div class="stat-value">{{ number_format($resumen['salidas']) }}</div>
            <div class="stat-sub">egresos de stock</div>
        </td>
    </tr>
</table>

@php
    $filtrosInfo = [];
    if (!empty($filtros['desde'])) $filtrosInfo['Desde'] = \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y');
    if (!empty($filtros['hasta'])) $filtrosInfo['Hasta'] = \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y');
    if (!empty($filtros['tipo']))  $filtrosInfo['Tipo']  = ucfirst($filtros['tipo']);
    if (!empty($filtros['origen'])) {
        $ol = ['App\\Models\\Ajuste'=>'Ajuste','App\\Models\\Compra'=>'Compra','App\\Models\\Venta'=>'Venta'];
        $filtrosInfo['Origen'] = $ol[$filtros['origen']] ?? $filtros['origen'];
    }
@endphp

@if(!empty($filtrosInfo))
<div class="filtros-wrap">
    <div class="filtros-titulo">Filtros aplicados</div>
    <div class="filtros-grid">
        @foreach($filtrosInfo as $label => $valor)
        <div class="filtro-item"><span class="filtro-label">{{ $label }}:</span> {{ $valor }}</div>
        @endforeach
    </div>
</div>
@endif

<table class="main">
    <thead>
        <tr>
            <th class="center" style="width:18px">#</th>
            <th>Fecha</th>
            <th>Producto</th>
            <th>Concepto</th>
            <th class="center">Origen</th>
            <th class="center">Tipo</th>
            <th class="right">Cantidad</th>
            <th class="right">Stock Antes</th>
            <th class="right">Stock Después</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @foreach($movimientos as $i => $mov)
        @php
            $esEntrada  = $mov->tipo === 'entrada';
            $stockSube  = (float)$mov->stock_despues > (float)$mov->stock_antes;
            $origenLabel = $origenMeta[$mov->movible_type] ?? null;
            $origenKey   = strtolower($origenLabel ?? '');
        @endphp
        <tr>
            <td class="center" style="color:#6b7280;font-weight:700">{{ $i + 1 }}</td>
            <td class="num" style="white-space:nowrap">{{ \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}<br><span style="color:#9ca3af;font-size:6.5px">{{ \Carbon\Carbon::parse($mov->fecha)->format('H:i') }}</span></td>
            <td>
                <span style="font-weight:600">{{ $mov->producto_nombre }}</span>
                @if($mov->variante_nombre)<br><span style="color:#9ca3af">{{ $mov->variante_nombre }}</span>@endif
            </td>
            <td>{{ $mov->concepto }}</td>
            <td class="center">@if($origenLabel)<span class="badge-{{ $origenKey }}">{{ $origenLabel }}</span>@else —@endif</td>
            <td class="center"><span class="{{ $esEntrada ? 'badge-entrada' : 'badge-salida' }}">{{ $esEntrada ? 'Entrada' : 'Salida' }}</span></td>
            <td class="right num"><span class="{{ $esEntrada ? 'qty-entrada' : 'qty-salida' }}">{{ $esEntrada ? '+' : '-' }}{{ number_format((float)$mov->cantidad, 2) }}</span></td>
            <td class="right num" style="color:#6b7280">{{ number_format((float)$mov->stock_antes, 2) }}</td>
            <td class="right num"><span class="{{ $stockSube ? 'stock-up' : 'stock-down' }}">{{ number_format((float)$mov->stock_despues, 2) }}</span></td>
            <td>{{ $mov->user?->name ?? 'Sistema' }}</td>
        </tr>
        @endforeach

        <tr class="totals-row">
            <td class="center">—</td>
            <td colspan="5">TOTAL ({{ $movimientos->count() }} movimientos)</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <span>{{ $empresa->nombre }} — RUC {{ $empresa->ruc ?? '' }}</span>
    <span>Reporte generado por SAS-PDV · {{ now()->format('d/m/Y H:i') }} · {{ $usuarioNombre }}</span>
</div>

</div>
</body>
</html>
