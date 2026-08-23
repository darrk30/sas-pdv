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
thead th.right { text-align: right; }
tbody tr:nth-child(even) { background: #EBF0F8; }
tbody tr:nth-child(odd)  { background: #fff; }
tbody td { padding: 3px 5px; font-size: 7.5px; border: 1px solid #d1d9e6; vertical-align: top; }
tbody td.right { text-align: right; }

.totals-row { background: #FFF3CD !important; font-weight: bold; }
.totals-row td { border-top: 2px solid #e2a800; font-size: 7.5px; }

.resumen-wrap { margin-top: 8px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
.resumen-box  { background: #EBF0F8; border-radius: 3px; padding: 5px 8px; min-width: 180px; }
.resumen-titulo { font-size: 8px; font-weight: bold; color: #1E3A5F; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.4px; }
.resumen-row  { display: flex; justify-content: space-between; font-size: 7.5px; padding: 1px 0; gap: 16px; }
.resumen-label { color: #2C5282; font-weight: bold; }

.saldo-box  { background: #f0fdf4; border-radius: 3px; padding: 5px 8px; min-width: 180px; }
.saldo-titulo { font-size: 8px; font-weight: bold; color: #166534; margin-bottom: 4px; text-transform: uppercase; }
.saldo-row  { display: flex; justify-content: space-between; font-size: 7.5px; padding: 1px 0; gap: 16px; }
.saldo-label { color: #166534; }

.footer {
    margin-top: 10px; border-top: 1px solid #d1d9e6;
    padding-top: 4px; font-size: 7px; color: #9ca3af;
    display: flex; justify-content: space-between;
}
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
        <div class="header-reporte">Reporte de Compras</div>
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
            @foreach($activeColumns as $key => $label)
            <th class="{{ in_array($key, $moneyKeys) ? 'right' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            @foreach($activeColumns as $key => $label)
            @php
                $val     = $row[$key] ?? '';
                $isMoney = in_array($key, $moneyKeys) && is_numeric($val) && (float)$val > 0;
                $display = $isMoney ? 'S/ ' . number_format((float)$val, 2) : ($val ?: '—');
            @endphp
            <td class="{{ $isMoney ? 'right' : '' }}">{{ $display }}</td>
            @endforeach
        </tr>
        @endforeach

        <tr class="totals-row">
            @foreach($activeColumns as $key => $label)
            <td class="{{ in_array($key, $moneyKeys) ? 'right' : '' }}">
                @if($key === 'comprobante')
                    TOTAL ({{ $compras->count() }} registros)
                @elseif(in_array($key, $moneyKeys) && isset($totales[$key]))
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
        <div class="resumen-row"><span class="resumen-label">Total compras</span><span>{{ number_format($resumen['cantidad']) }}</span></div>
        <div class="resumen-row"><span class="resumen-label">Total comprado</span><span>S/ {{ number_format($resumen['total'], 2) }}</span></div>
        <div class="resumen-row"><span class="resumen-label">Por pagar</span><span>{{ $resumen['pendiente'] }} compras</span></div>
    </div>
    <div class="saldo-box">
        <div class="saldo-titulo">Pagos</div>
        <div class="saldo-row"><span class="saldo-label">Total pagado</span><span>S/ {{ number_format($resumen['pagado'], 2) }}</span></div>
        <div class="saldo-row"><span class="saldo-label">Saldo pendiente</span><span>S/ {{ number_format($resumen['saldo'], 2) }}</span></div>
    </div>
</div>

<div class="footer">
    <span>{{ $empresa->nombre }} — RUC {{ $empresa->ruc ?? '' }}</span>
    <span>Reporte generado por SAS-PDV · {{ now()->format('d/m/Y H:i') }} · {{ $usuarioNombre }}</span>
</div>

</div>
</body>
</html>
