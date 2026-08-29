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
thead th.right  { text-align: right; }
thead th.center { text-align: center; }
tbody td { padding: 3px 5px; font-size: 7.5px; border: 1px solid #d1d9e6; vertical-align: top; }
tbody td.right  { text-align: right; }
tbody td.center { text-align: center; }
tbody tr.anulada td { color: #9ca3af; }
.tr-tot td { background: #FFF3CD; border-top: 2px solid #e2a800; font-size: 7.5px; font-weight: bold; }
</style>
</head>
<body>
<div class="page">

{{-- Header --}}
<table style="margin-bottom:6px;">
    <tr>
        <td style="background:#1E3A5F;padding:8px 12px;vertical-align:middle;">
            <div style="font-size:11px;font-weight:bold;color:#fff;">{{ strtoupper($empresa->nombre) }}</div>
            <div style="font-size:8.5px;color:#c7d7ee;margin-top:2px;">RUC {{ $empresa->ruc ?? '' }}</div>
        </td>
        <td style="background:#1E3A5F;padding:8px 12px;text-align:right;vertical-align:middle;">
            <div style="font-size:13px;font-weight:bold;text-transform:uppercase;color:#fff;">Reporte de Ventas</div>
            <div style="font-size:8px;color:#c7d7ee;margin-top:2px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

{{-- Filtros --}}
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

{{-- Tabla --}}
<table>
    <thead>
        <tr>
            @foreach($activeColumns as $key => $label)
            <th class="{{ in_array($key, $moneyKeys) ? 'right' : '' }}">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        @php
            $bg        = $i % 2 === 0 ? '#EBF0F8' : '#ffffff';
            $esAnulada = str_contains(strtolower($row['estado'] ?? ''), 'anulada');
        @endphp
        <tr style="background:{{ $bg }}" class="{{ $esAnulada ? 'anulada' : '' }}">
            @foreach($activeColumns as $key => $label)
            @php
                $val     = $row[$key] ?? '';
                $isMoney = in_array($key, $moneyKeys) && is_float($val);
                $isRight  = $isMoney;
                $isCenter = in_array($key, ['detalles_count','cortesias_count','notas_count','estado','estado_sunat']);
                $display  = $isMoney ? 'S/ ' . number_format($val, 2) : $val;
            @endphp
            <td class="{{ $isRight ? 'right' : ($isCenter ? 'center' : '') }}">{{ $display }}</td>
            @endforeach
        </tr>
        @endforeach

        <tr class="tr-tot">
            @foreach($activeColumns as $key => $label)
            @php $isMoney = in_array($key, $moneyKeys); @endphp
            <td class="{{ $isMoney ? 'right' : '' }}">
                @if($key === 'comprobante')
                    TOTAL ({{ $ventas->count() }} registros)
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

{{-- Resumen --}}
<table style="margin-top:8px;">
    <tr>
        @if(! empty($resumen['porMetodo']))
        <td style="vertical-align:top;padding-right:6px;width:50%;">
            <div style="background:#EBF0F8;padding:5px 8px;">
                <div style="font-size:8px;font-weight:bold;color:#1E3A5F;text-transform:uppercase;margin-bottom:4px;">Por método de pago</div>
                @foreach($resumen['porMetodo'] as $m)
                <table style="margin:0;"><tr>
                    <td style="font-size:7.5px;color:#2C5282;font-weight:bold;padding:1px 0;">{{ $m['nombre'] }}</td>
                    <td style="font-size:7.5px;text-align:right;font-weight:bold;padding:1px 0;">S/ {{ number_format($m['total'], 2) }}</td>
                </tr></table>
                @endforeach
            </div>
        </td>
        @endif
        <td style="vertical-align:top;padding-left:{{ empty($resumen['porMetodo']) ? '0' : '6px' }};width:{{ empty($resumen['porMetodo']) ? '100%' : '50%' }};">
            <div style="background:#f0fdf4;padding:5px 8px;">
                <div style="font-size:8px;font-weight:bold;color:#166534;text-transform:uppercase;margin-bottom:4px;">Resumen</div>
                <table style="margin:0;">
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Completadas</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">{{ $resumen['count'] }}</td></tr>
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Total cobrado</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">S/ {{ number_format($resumen['total'], 2) }}</td></tr>
                    @if(($resumen['descuentoTotal'] ?? 0) > 0)
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Descuentos</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">- S/ {{ number_format($resumen['descuentoTotal'], 2) }}</td></tr>
                    @endif
                    @if(($resumen['creditoPendiente'] ?? 0) > 0)
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Crédito pendiente</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">S/ {{ number_format($resumen['creditoPendiente'], 2) }}</td></tr>
                    @endif
                    <tr><td style="font-size:7.5px;color:#166534;padding:1px 0;">Anuladas</td><td style="font-size:7.5px;text-align:right;padding:1px 0;">{{ $resumen['anuladas'] }}</td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- Footer --}}
<table style="margin-top:10px;border-top:1px solid #d1d9e6;">
    <tr>
        <td style="padding-top:4px;font-size:7px;color:#9ca3af;">{{ $empresa->nombre }} — RUC {{ $empresa->ruc ?? '' }}</td>
        <td style="padding-top:4px;font-size:7px;color:#9ca3af;text-align:right;">Reporte generado por SAS-PDV · {{ now()->format('d/m/Y H:i') }} · {{ $usuarioNombre }}</td>
    </tr>
</table>

</div>
</body>
</html>
