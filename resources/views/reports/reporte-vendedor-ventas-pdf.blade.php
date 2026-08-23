<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 14mm 16mm 14mm 16mm; }
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 8px;
    color: #1a202c;
    background: #fff;
}
.page { padding: 12mm 10mm; }

.header {
    background: #1E3A5F; color: #fff;
    padding: 8px 12px; margin-bottom: 6px;
    border-radius: 3px;
    display: flex; justify-content: space-between; align-items: center;
}
.header-empresa  { font-size: 11px; font-weight: bold; letter-spacing: 0.3px; }
.header-ruc      { font-size: 8.5px; margin-top: 2px; opacity: 0.85; }
.header-right    { text-align: right; }
.header-reporte  { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
.header-vendedor { font-size: 9px; margin-top: 2px; opacity: 0.9; }
.header-meta     { font-size: 8px; margin-top: 2px; opacity: 0.85; }

.filtros-wrap {
    background: #EBF0F8; border-left: 3px solid #1E3A5F;
    padding: 5px 8px; margin-bottom: 8px; border-radius: 0 2px 2px 0;
}
.filtros-titulo { font-size: 8px; font-weight: bold; color: #1E3A5F; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.5px; }
.filtros-grid   { display: flex; flex-wrap: wrap; gap: 3px 16px; }
.filtro-item    { font-size: 7.5px; }
.filtro-label   { font-weight: bold; color: #2C5282; }

table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
thead tr { background: #1E3A5F; color: #fff; }
thead th {
    padding: 4px 5px; text-align: left; font-size: 7.5px;
    font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;
    border: 1px solid #164172; white-space: nowrap;
}
thead th.right  { text-align: right; }
thead th.center { text-align: center; }
tbody tr:nth-child(even) { background: #EBF0F8; }
tbody tr:nth-child(odd)  { background: #fff; }
tbody td { padding: 3px 5px; font-size: 7.5px; border: 1px solid #d1d9e6; vertical-align: middle; }
tbody td.right  { text-align: right; }
tbody td.center { text-align: center; }

.badge-credito  { background: #fef3c7; color: #92400e; padding: 1px 5px; border-radius: 99px; font-size: 7px; font-weight: 700; }
.badge-contado  { background: #dcfce7; color: #166534; padding: 1px 5px; border-radius: 99px; font-size: 7px; font-weight: 700; }

.totals-row { background: #FFF3CD !important; font-weight: bold; }
.totals-row td { border-top: 2px solid #e2a800; font-size: 7.5px; }

.resumen-wrap { margin-top: 8px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
.resumen-box  { background: #EBF0F8; border-radius: 3px; padding: 5px 8px; min-width: 180px; }
.resumen-titulo { font-size: 8px; font-weight: bold; color: #1E3A5F; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.4px; }
.resumen-row  { display: flex; justify-content: space-between; font-size: 7.5px; padding: 1px 0; gap: 16px; }
.resumen-label { color: #2C5282; font-weight: bold; }

.util-box   { background: #f0fdf4; border-radius: 3px; padding: 5px 8px; min-width: 160px; }
.util-titulo{ font-size: 8px; font-weight: bold; color: #166534; margin-bottom: 4px; text-transform: uppercase; }
.util-row   { display: flex; justify-content: space-between; font-size: 7.5px; padding: 1px 0; gap: 16px; }
.util-label { color: #166534; }

.footer {
    margin-top: 10px; border-top: 1px solid #d1d9e6;
    padding-top: 4px; font-size: 7px; color: #9ca3af;
    display: flex; justify-content: space-between;
}
.num { font-variant-numeric: tabular-nums; }
</style>
</head>
<body>
<div class="page">

<div class="header">
    <div class="header-left">
        <div class="header-empresa">{{ strtoupper($empresa->nombre) }}</div>
        <div class="header-ruc">RUC {{ $empresa->ruc ?? '' }}</div>
    </div>
    <div class="header-right">
        <div class="header-reporte">Ventas del vendedor</div>
        <div class="header-vendedor">{{ $vendedorNombre }}</div>
        <div class="header-meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

@if(! empty($filtrosInfo))
<div class="filtros-wrap">
    <div class="filtros-titulo">Filtros aplicados</div>
    <div class="filtros-grid">
        @foreach($filtrosInfo as $label => $valor)
        <div class="filtro-item"><span class="filtro-label">{{ $label }}:</span> {{ $valor }}</div>
        @endforeach
    </div>
</div>
@endif

<table>
    <thead>
        <tr>
            <th class="center" style="width:22px">#</th>
            @foreach($cols as $key => $label)
            <th class="{{ in_array($key, $moneyKeys) ? 'right' : ($key === 'estado_pago' ? 'center' : '') }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        <tr>
            <td class="center" style="color:#6b7280;font-weight:700">{{ $i + 1 }}</td>
            @foreach($cols as $key => $label)
            @php
                $val     = $row[$key] ?? '';
                $isMoney = in_array($key, $moneyKeys) && is_numeric($val);
                if ($isMoney) {
                    $display = 'S/ ' . number_format((float)$val, 2);
                } else {
                    $display = $val ?: '—';
                }
            @endphp
            @if($key === 'estado_pago')
                <td class="center">
                    <span class="{{ $val === 'Crédito' ? 'badge-credito' : 'badge-contado' }}">{{ $val }}</span>
                </td>
            @else
                <td class="{{ $isMoney ? 'right num' : '' }}">{{ $display }}</td>
            @endif
            @endforeach
        </tr>
        @endforeach

        <tr class="totals-row">
            <td class="center">—</td>
            @foreach($cols as $key => $label)
            @php $isMoney = in_array($key, $moneyKeys); @endphp
            <td class="{{ $isMoney ? 'right' : ($key === 'estado_pago' ? 'center' : '') }}">
                @if($key === 'comprobante')
                    TOTAL ({{ $rows->count() }} ventas)
                @elseif($isMoney && isset($totales[$key]))
                    S/ {{ number_format($totales[$key], 2) }}
                @else
                    &nbsp;
                @endif
            </td>
            @endforeach
        </tr>
    </tbody>
</table>

<div class="resumen-wrap">
    <div class="resumen-box">
        <div class="resumen-titulo">Resumen</div>
        <div class="resumen-row"><span class="resumen-label">Total ventas</span><span>{{ number_format($resumen['cantidad']) }}</span></div>
        <div class="resumen-row"><span class="resumen-label">Total cobrado</span><span>S/ {{ number_format($resumen['cobrado'], 2) }}</span></div>
        @if(($resumen['creditoPendiente'] ?? 0) > 0)
        <div class="resumen-row"><span class="resumen-label">Crédito pendiente</span><span>S/ {{ number_format($resumen['creditoPendiente'], 2) }}</span></div>
        @endif
    </div>
    <div class="util-box">
        <div class="util-titulo">Utilidad</div>
        <div class="util-row"><span class="util-label">Utilidad neta</span><span>S/ {{ number_format($resumen['utilidad'], 2) }}</span></div>
        @php
            $totalVentas = $rows->sum(fn($r) => (float)($r['total'] ?? 0));
            $margen = $totalVentas > 0 ? round($resumen['utilidad'] / $totalVentas * 100, 1) : 0;
        @endphp
        <div class="util-row"><span class="util-label">Margen</span><span>{{ number_format($margen, 1) }}%</span></div>
    </div>
</div>

<div class="footer">
    <span>{{ $empresa->nombre }} — RUC {{ $empresa->ruc ?? '' }}</span>
    <span>Reporte generado por SAS-PDV · {{ now()->format('d/m/Y H:i') }} · {{ $usuarioNombre }}</span>
</div>

</div>
</body>
</html>
