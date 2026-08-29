<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 14mm 16mm 14mm 16mm; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #1a202c; background: #fff; }
.page { padding: 12mm 10mm; }

table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
thead tr { background: #1E3A5F; color: #fff; }
thead th { padding: 4px 5px; text-align: left; font-size: 7.5px; font-weight: bold; text-transform: uppercase; border: 1px solid #164172; white-space: nowrap; }
thead th.right { text-align: right; }
thead th.center { text-align: center; }
tbody td { padding: 3px 5px; font-size: 7.5px; border: 1px solid #d1d9e6; vertical-align: middle; }
tbody td.right { text-align: right; }
tbody td.center { text-align: center; }
.totals-row td { background: #FFF3CD; border-top: 2px solid #e2a800; font-weight: bold; font-size: 7.5px; }
</style>
</head>
<body>
<div class="page">

<table style="margin-bottom:6px;">
    <tr>
        <td style="background:#1E3A5F;padding:8px 12px;vertical-align:middle;">
            <div style="font-size:11px;font-weight:bold;color:#fff;">{{ strtoupper($empresa->nombre) }}</div>
            <div style="font-size:8.5px;color:#c7d7ee;margin-top:2px;">RUC {{ $empresa->ruc ?? '' }}</div>
        </td>
        <td style="background:#1E3A5F;padding:8px 12px;text-align:right;vertical-align:middle;">
            <div style="font-size:13px;font-weight:bold;text-transform:uppercase;color:#fff;">Reporte de Vendedores</div>
            <div style="font-size:8px;color:#c7d7ee;margin-top:2px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

@if(! empty($filtrosInfo))
<table style="margin-bottom:8px;background:#EBF0F8;border-left:3px solid #1E3A5F;">
    <tr>
        <td style="padding:5px 8px;font-size:8px;font-weight:bold;color:#1E3A5F;text-transform:uppercase;white-space:nowrap;vertical-align:top;">Filtros:</td>
        @foreach($filtrosInfo as $label => $valor)
        <td style="padding:5px 8px 5px 0;font-size:7.5px;vertical-align:top;"><span style="font-weight:bold;color:#2C5282;">{{ $label }}:</span> {{ $valor }}</td>
        @endforeach
    </tr>
</table>
@endif

<table>
    <thead>
        <tr>
            <th class="center" style="width:24px">#</th>
            @foreach($cols as $key => $label)
            <th class="{{ in_array($key, $moneyKeys) || in_array($key, ['cantidad','margen']) ? 'right' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        @php $bg = $i % 2 === 0 ? '#EBF0F8' : '#ffffff'; @endphp
        <tr style="background:{{ $bg }}">
            <td class="center" style="color:#6b7280;font-weight:700">{{ $i + 1 }}</td>
            @foreach($cols as $key => $label)
            @php
                $val      = $row[$key] ?? '';
                $isMoney  = in_array($key, $moneyKeys) && is_numeric($val) && (float)$val >= 0;
                $isNum    = $key === 'cantidad' && is_numeric($val);
                $isMargen = $key === 'margen';
                if ($isMoney)      { $display = 'S/ ' . number_format((float)$val, 2); }
                elseif ($isMargen) { $display = number_format((float)$val, 1) . '%'; }
                elseif ($isNum)    { $display = number_format((int)$val); }
                else               { $display = $val ?: '—'; }
            @endphp
            <td class="{{ ($isMoney || $isNum || $isMargen) ? 'right' : '' }}">{{ $display }}</td>
            @endforeach
        </tr>
        @endforeach

        <tr class="totals-row">
            <td class="center">—</td>
            @foreach($cols as $key => $label)
            <td class="{{ in_array($key, $moneyKeys) || in_array($key, ['cantidad','margen']) ? 'right' : '' }}">
                @if($key === 'vendedor')
                    TOTAL ({{ $rows->count() }} vendedores)
                @elseif(in_array($key, $moneyKeys) && isset($totales[$key]))
                    S/ {{ number_format($totales[$key], 2) }}
                @elseif($key === 'cantidad' && isset($totales['cantidad']))
                    {{ number_format($totales['cantidad']) }}
                @else
                    &nbsp;
                @endif
            </td>
            @endforeach
        </tr>
    </tbody>
</table>

<table style="margin-top:8px;">
    <tr>
        <td style="vertical-align:top;padding-right:6px;width:50%;">
            <div style="background:#EBF0F8;padding:5px 8px;">
                <div style="font-size:8px;font-weight:bold;color:#1E3A5F;text-transform:uppercase;margin-bottom:4px;">Resumen general</div>
                <table style="margin:0;">
                    <tr><td style="font-size:7.5px;color:#2C5282;font-weight:bold;padding:1px 0;">Vendedores activos</td><td style="font-size:7.5px;text-align:right;font-weight:bold;padding:1px 0;">{{ number_format($resumen['totalVendedores']) }}</td></tr>
                    <tr><td style="font-size:7.5px;color:#2C5282;font-weight:bold;padding:1px 0;">Total ventas</td><td style="font-size:7.5px;text-align:right;font-weight:bold;padding:1px 0;">{{ number_format($resumen['cantidad']) }}</td></tr>
                    <tr><td style="font-size:7.5px;color:#2C5282;font-weight:bold;padding:1px 0;">Total facturado</td><td style="font-size:7.5px;text-align:right;font-weight:bold;padding:1px 0;">S/ {{ number_format($resumen['ingresosBrutos'], 2) }}</td></tr>
                    <tr><td style="font-size:7.5px;color:#2C5282;font-weight:bold;padding:1px 0;">Total cobrado</td><td style="font-size:7.5px;text-align:right;font-weight:bold;padding:1px 0;">S/ {{ number_format($resumen['cobrado'], 2) }}</td></tr>
                    @if(($resumen['creditoPendiente'] ?? 0) > 0)
                    <tr><td style="font-size:7.5px;color:#2C5282;font-weight:bold;padding:1px 0;">Crédito pendiente</td><td style="font-size:7.5px;text-align:right;font-weight:bold;padding:1px 0;">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
        <td style="vertical-align:top;padding-left:6px;width:50%;">
            <div style="background:#f0fdf4;padding:5px 8px;">
                <div style="font-size:8px;font-weight:bold;color:#166534;text-transform:uppercase;margin-bottom:4px;">Utilidad</div>
                <table style="margin:0;">
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Costo de ventas</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">S/ {{ number_format($resumen['costoTotal'], 2) }}</td></tr>
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Utilidad bruta</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">S/ {{ number_format($resumen['utilidadBruta'], 2) }}</td></tr>
                    @php $margenGlobal = $resumen['ingresosBrutos'] > 0 ? round($resumen['utilidadBruta'] / $resumen['ingresosBrutos'] * 100, 1) : 0; @endphp
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Margen promedio</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">{{ number_format($margenGlobal, 1) }}%</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table style="margin-top:10px;border-top:1px solid #d1d9e6;">
    <tr>
        <td style="padding-top:4px;font-size:7px;color:#9ca3af;">{{ $empresa->nombre }} — RUC {{ $empresa->ruc ?? '' }}</td>
        <td style="padding-top:4px;font-size:7px;color:#9ca3af;text-align:right;">Reporte generado por SAS-PDV · {{ now()->format('d/m/Y H:i') }} · {{ $usuarioNombre }}</td>
    </tr>
</table>

</div>
</body>
</html>
