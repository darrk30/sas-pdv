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

.stats-wrap {
    width: 100%; border-collapse: separate; border-spacing: 6px 0;
    margin-bottom: 10px;
}
.stat-box {
    width: 25%; border-radius: 3px; padding: 6px 8px;
    text-align: center; vertical-align: top;
}
.stat-box--gray  { background: #EBF0F8; border-top: 3px solid #4A5568; }
.stat-box--green { background: #F0FDF4; border-top: 3px solid #22C55E; }
.stat-box--amber { background: #FFFBEB; border-top: 3px solid #F59E0B; }
.stat-box--red   { background: #FEF2F2; border-top: 3px solid #EF4444; }
.stat-label { font-size: 7px; text-transform: uppercase; letter-spacing: 0.4px; color: #6b7280; font-weight: bold; }
.stat-value { font-size: 18px; font-weight: bold; color: #1a202c; }
.stat-sub   { font-size: 6.5px; color: #9ca3af; }

table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
thead tr { background: #1E3A5F; color: #fff; }
thead th {
    padding: 4px 5px; text-align: left; font-size: 7.5px;
    font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px;
    border: 1px solid #164172; white-space: nowrap;
}
thead th.right  { text-align: right; }
tbody tr:nth-child(even) { background: #EBF0F8; }
tbody tr:nth-child(odd)  { background: #fff; }
tbody td { padding: 3px 5px; font-size: 7.5px; border: 1px solid #d1d9e6; vertical-align: middle; }
tbody td.right  { text-align: right; }
tbody td.mono   { font-family: DejaVu Sans Mono, monospace; }

.badge-disponible   { background: #dcfce7; color: #166534; padding: 1px 5px; border-radius: 99px; font-size: 7px; font-weight: 700; }
.badge-por-agotarse { background: #fef3c7; color: #92400e; padding: 1px 5px; border-radius: 99px; font-size: 7px; font-weight: 700; }
.badge-agotado      { background: #fee2e2; color: #991b1b; padding: 1px 5px; border-radius: 99px; font-size: 7px; font-weight: 700; }

.totals-row { background: #FFF3CD !important; font-weight: bold; }
.totals-row td { border-top: 2px solid #e2a800; font-size: 7.5px; }

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
    <div>
        <div class="header-empresa">{{ strtoupper($empresa->nombre) }}</div>
        <div class="header-ruc">RUC {{ $empresa->ruc ?? '' }}</div>
    </div>
    <div class="header-right">
        <div class="header-reporte">Inventario Activo</div>
        <div class="header-meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

{{-- Stats (tabla HTML para DomPDF — no soporta flex) --}}
<table class="stats-wrap">
    <tr>
        <td class="stat-box stat-box--gray">
            <div class="stat-label">Total</div>
            <div class="stat-value">{{ number_format($stats['total']) }}</div>
            <div class="stat-sub">productos activos</div>
        </td>
        <td class="stat-box stat-box--green">
            <div class="stat-label">Disponible</div>
            <div class="stat-value">{{ number_format($stats['disponible']) }}</div>
            <div class="stat-sub">con stock</div>
        </td>
        <td class="stat-box stat-box--amber">
            <div class="stat-label">Por agotarse</div>
            <div class="stat-value">{{ number_format($stats['por_agotarse']) }}</div>
            <div class="stat-sub">cerca del mínimo</div>
        </td>
        <td class="stat-box stat-box--red">
            <div class="stat-label">Agotado</div>
            <div class="stat-value">{{ number_format($stats['agotado']) }}</div>
            <div class="stat-sub">sin stock</div>
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th class="center" style="width:22px">#</th>
            <th>Producto</th>
            <th>Código</th>
            <th class="right">Stock Actual</th>
            <th class="right">Stock Mín.</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($registros as $i => $inv)
        @php
            $estadoVal = $inv->estado_inventario?->value ?? '';
            $badgeClass = match($estadoVal) {
                'disponible'   => 'badge-disponible',
                'por_agotarse' => 'badge-por-agotarse',
                'agotado'      => 'badge-agotado',
                default        => '',
            };
            $estadoLabel = match($estadoVal) {
                'disponible'   => 'Disponible',
                'por_agotarse' => 'Por agotarse',
                'agotado'      => 'Agotado',
                default        => '—',
            };
            if ($inv->variante_id && $inv->variante) {
                $nombre = \App\Models\AjusteDetalle::generarNombre(null, $inv->variante);
                $codigo = $inv->variante->codigo ?? '—';
            } else {
                $nombre = $inv->producto?->nombre ?? '—';
                $codigo = $inv->producto?->codigo_interno ?? '—';
            }
        @endphp
        <tr>
            <td class="right" style="color:#6b7280;font-weight:700">{{ $i + 1 }}</td>
            <td style="font-weight:600">{{ $nombre }}</td>
            <td class="mono">{{ $codigo }}</td>
            <td class="right num">{{ number_format((float)$inv->stock_real, 2) }}</td>
            <td class="right num" style="color:#6b7280">{{ number_format((float)($inv->stock_minimo ?? 0), 2) }}</td>
            <td><span class="{{ $badgeClass }}">{{ $estadoLabel }}</span></td>
        </tr>
        @endforeach

        <tr class="totals-row">
            <td class="right">—</td>
            <td>TOTAL ({{ $registros->count() }} registros)</td>
            <td></td>
            <td class="right num">{{ number_format($registros->sum(fn($r) => (float)$r->stock_real), 2) }}</td>
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
