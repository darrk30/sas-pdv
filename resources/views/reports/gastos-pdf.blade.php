<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 12mm 14mm 12mm 14mm; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.5px; color: #1a202c; background: #fff; }
.page { padding: 8mm 6mm; }

.stats-wrap { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.stat-box { width: 25%; padding: 5px 8px; text-align: center; vertical-align: top; }
.stat-box--gray   { background: #EBF0F8; border-top: 3px solid #4A5568; }
.stat-box--green  { background: #F0FDF4; border-top: 3px solid #22C55E; }
.stat-box--amber  { background: #FFFBEB; border-top: 3px solid #F59E0B; }
.stat-box--red    { background: #FEF2F2; border-top: 3px solid #EF4444; }
.stat-label { font-size: 7px; text-transform: uppercase; color: #6b7280; font-weight: bold; }
.stat-value { font-size: 14px; font-weight: bold; color: #1a202c; }
.stat-sub   { font-size: 6.5px; color: #9ca3af; }

table.main { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
thead tr { background: #1E3A5F; color: #fff; }
thead th { padding: 3px 4px; text-align: left; font-size: 7px; font-weight: bold; text-transform: uppercase; border: 1px solid #164172; white-space: nowrap; }
thead th.right  { text-align: right; }
thead th.center { text-align: center; }
tbody td { padding: 2.5px 4px; font-size: 7px; border: 1px solid #d1d9e6; vertical-align: middle; }
tbody td.right  { text-align: right; }
tbody td.center { text-align: center; }
tbody td.mono   { font-family: DejaVu Sans Mono, monospace; }

.badge { padding: 1px 5px; font-size: 6.5px; font-weight: 700; }
.badge-pendiente  { background: #fef3c7; color: #92400e; }
.badge-aprobado   { background: #dcfce7; color: #166534; }
.badge-anulado    { background: #fee2e2; color: #991b1b; }
.badge-cat        { background: #dbeafe; color: #1e40af; }

.totals-row td { background: #FFF3CD; border-top: 2px solid #e2a800; font-weight: bold; }
</style>
</head>
<body>
<div class="page">

<table style="width:100%;border-collapse:collapse;margin-bottom:6px;">
    <tr>
        <td style="background:#1E3A5F;padding:8px 12px;vertical-align:middle;">
            <div style="font-size:11px;font-weight:bold;color:#fff;">{{ strtoupper($empresa->nombre) }}</div>
            <div style="font-size:8px;color:#c7d7ee;margin-top:2px;">RUC {{ $empresa->ruc ?? '' }}</div>
        </td>
        <td style="background:#1E3A5F;padding:8px 12px;text-align:right;vertical-align:middle;">
            <div style="font-size:12px;font-weight:bold;text-transform:uppercase;color:#fff;">Reporte de Gastos</div>
            <div style="font-size:7.5px;color:#c7d7ee;margin-top:2px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="stats-wrap">
    <tr>
        <td class="stat-box stat-box--gray">
            <div class="stat-label">Total registros</div>
            <div class="stat-value">{{ $resumen['total'] }}</div>
            <div class="stat-sub">incluyendo anulados</div>
        </td>
        <td class="stat-box stat-box--green">
            <div class="stat-label">Suma aprobada</div>
            <div class="stat-value">S/ {{ number_format($resumen['aprobados'], 2) }}</div>
            <div class="stat-sub">gastos confirmados</div>
        </td>
        <td class="stat-box stat-box--amber">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value">S/ {{ number_format($resumen['pendientes'], 2) }}</div>
            <div class="stat-sub">por aprobar</div>
        </td>
        <td class="stat-box stat-box--red">
            <div class="stat-label">Anulados</div>
            <div class="stat-value">{{ $resumen['anulados'] }}</div>
            <div class="stat-sub">registros</div>
        </td>
    </tr>
</table>

<table class="main">
    <thead>
        <tr>
            <th class="center" style="width:18px">#</th>
            <th style="width:52px">Código</th>
            <th style="width:42px">Fecha</th>
            <th style="width:55px">Categoría</th>
            <th>Descripción</th>
            <th style="width:55px">Empleado</th>
            <th class="right" style="width:48px">Monto</th>
            <th class="center" style="width:44px">Estado</th>
            <th style="width:55px">Registrado por</th>
        </tr>
    </thead>
    <tbody>
        @foreach($gastos as $i => $g)
        @php
            $bg        = $i % 2 === 0 ? '#EBF0F8' : '#ffffff';
            $codigo    = $g->serie . '-' . str_pad($g->correlativo, 6, '0', STR_PAD_LEFT);
            $estadoKey = $g->estado?->value ?? '';
            $badgeCls  = 'badge badge-' . $estadoKey;
            $catLabel  = $g->categoria?->getLabel() ?? '—';
        @endphp
        <tr style="background:{{ $bg }}">
            <td class="center" style="color:#6b7280;font-weight:700">{{ $i + 1 }}</td>
            <td class="mono" style="font-weight:600;color:#1E3A5F">{{ $codigo }}</td>
            <td>{{ $g->fecha?->format('d/m/Y') ?? '—' }}</td>
            <td><span class="badge badge-cat">{{ $catLabel }}</span></td>
            <td>{{ Str::limit($g->descripcion, 55) }}</td>
            <td>{{ $g->empleado?->name ?? '—' }}</td>
            <td class="right" style="font-weight:700">S/ {{ number_format((float)$g->monto, 2) }}</td>
            <td class="center"><span class="{{ $badgeCls }}">{{ $g->estado?->getLabel() ?? '—' }}</span></td>
            <td>{{ $g->registradoPor?->name ?? 'Sistema' }}</td>
        </tr>
        @endforeach

        <tr class="totals-row">
            <td class="center">—</td>
            <td colspan="5">TOTAL ACTIVOS ({{ $resumen['activos'] }} gastos)</td>
            <td class="right">S/ {{ number_format($resumen['suma'], 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

<table style="width:100%;border-collapse:collapse;margin-top:8px;border-top:1px solid #d1d9e6;">
    <tr>
        <td style="padding-top:3px;font-size:6.5px;color:#9ca3af;">{{ $empresa->nombre }} — RUC {{ $empresa->ruc ?? '' }} &nbsp;|&nbsp; Reporte generado por SAS-PDV · {{ now()->format('d/m/Y H:i') }} · {{ $usuarioNombre }}</td>
    </tr>
</table>

</div>
</body>
</html>
