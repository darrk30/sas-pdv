<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Arqueo de Caja #{{ $sesion->id }}</title>
    <style>

        /* Dejamos @page en 0 y controlamos el margen desde body.
           Esto evita problemas frecuentes de DomPDF con @page. */
        @page { margin: 0; }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }

        /* ── TICKET 80 MM ──────────────────────────────────── */
        @if(($formato ?? 'a4') === 'ticket')

        body {
            margin: 4mm;
            font-family: Courier, monospace;
            font-size: 7.5pt;
            color: #1a1a1a;
            background: #fff;
            line-height: 1.4;
        }
        .empresa      { text-align: center; font-size: 9pt; font-weight: bold; }
        .empresa-sub  { text-align: center; font-size: 6.5pt; color: #555; }
        .doc-title    { text-align: center; font-size: 8pt; font-weight: bold;
                        border-top: 1px solid #333; border-bottom: 1px solid #333;
                        padding: 2pt 0; margin: 4pt 0; letter-spacing: 0.5pt; }
        .sep-dash     { border: none; border-top: 1px dashed #888; margin: 4pt 0; }
        .sep-solid    { border: none; border-top: 1px solid #333; margin: 4pt 0; }
        .sec-label    { font-size: 7pt; font-weight: bold; text-transform: uppercase;
                        letter-spacing: 0.6pt; margin: 3pt 0 2pt; }
        .kv           { width: 100%; border-collapse: collapse; }
        .kv td        { font-size: 7pt; padding: 1pt 0; }
        .kv .k        { color: #555; }
        .kv .v        { text-align: right; font-weight: bold; }
        .tbl          { width: 100%; border-collapse: collapse; }
        .tbl td       { font-size: 6.5pt; padding: 1pt 1pt; vertical-align: top; }
        .tbl .hd td   { font-weight: bold; border-bottom: 1px solid #333; }
        .tbl .ft td   { font-weight: bold; border-top: 1px solid #333; }
        .tbl .n       { text-align: right; }
        .firma-line   { border-top: 1px solid #333; margin-top: 22pt;
                        padding-top: 2pt; text-align: center; font-size: 6.5pt; }
        .note         { font-size: 6.5pt; font-style: italic; color: #555; }
        .footer-t     { text-align: center; font-size: 6pt; color: #888; margin-top: 4pt; }

        /* ── A4 ────────────────────────────────────────────── */
        @else

        body {
            margin: 16mm 15mm 16mm 15mm;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #2c3e50;
            background: #fff;
            line-height: 1.45;
        }

        /* Utilidades */
        .tr   { text-align: right; }
        .tc   { text-align: center; }
        .bold { font-weight: bold; }
        .mut  { color: #7f8c8d; }
        .mono { font-family: Courier, monospace; }
        .grn  { color: #27ae60; }
        .red  { color: #c0392b; }
        .amb  { color: #d68910; }

        /* Encabezado */
        .hdr        { width: 100%; border-collapse: collapse; margin-bottom: 14pt;
                      border-bottom: 2pt solid #a8c4d8; }
        .hdr td     { padding-bottom: 6pt; }
        .h-empresa  { font-size: 14pt; font-weight: bold; color: #1a3a5c; }
        .h-sub      { font-size: 7.5pt; color: #7f8c8d; margin-top: 2pt; }
        .h-titulo   { font-size: 13pt; font-weight: bold; color: #1a3a5c; text-align: right; }
        .h-doc      { font-size: 7.5pt; color: #7f8c8d; text-align: right; margin-top: 2pt; }

        /* Banda sesión */
        .band       { width: 100%; border-collapse: collapse; margin-bottom: 12pt;
                      background: #4a7c9e; color: #fff; }
        .band td    { padding: 7pt 11pt; font-size: 8pt; }
        .band .lbl  { color: #b8d8ee; font-size: 6.5pt; display: block; margin-bottom: 2pt; }
        .band .val  { font-weight: bold; font-size: 9pt; }
        .band .div  { border-left: 1px solid #6a9ab8; width: 1pt; padding: 0; }

        /* KPI */
        .kpi-wrap      { width: 100%; border-collapse: collapse; margin-bottom: 11pt; }
        .kpi-wrap td   { border: 1pt solid #c0d8e8; background: #eef5fb;
                         padding: 7pt 6pt; text-align: center; }
        .kpi-lbl       { font-size: 6.5pt; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.4pt; }
        .kpi-val       { font-size: 14pt; font-weight: bold; color: #1a3a5c; margin: 2pt 0; }
        .kpi-sub       { font-size: 7pt; color: #a0b4c0; }

        /* Cuadre */
        .cuadre        { width: 100%; border-collapse: collapse; margin-bottom: 11pt; }
        .cuadre td     { border: 1pt solid #c0d8e8; padding: 7pt 8pt; text-align: center; }
        .cuadre-lbl    { font-size: 6.5pt; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.3pt; }
        .cuadre-val    { font-size: 11pt; font-weight: bold; margin-top: 3pt; }

        /* Secciones */
        .sec           { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        .sec .sh td    { background: #dce8f0; color: #1a3a5c; font-size: 7pt; font-weight: bold;
                         text-transform: uppercase; letter-spacing: 0.6pt;
                         padding: 4pt 8pt; border-left: 3pt solid #4a7c9e; }
        .sec .sh2 td   { background: #e4edf5; color: #1a3a5c; font-size: 7pt; font-weight: bold;
                         text-transform: uppercase; letter-spacing: 0.5pt;
                         padding: 3.5pt 8pt; border-left: 3pt solid #6b9ab8; }
        .sec td        { padding: 3.5pt 8pt; font-size: 8pt; border-bottom: 1px solid #ecf0f1; }
        .sec tr.alt td { background: #f5f9fc; }
        .sec tr.th  td { background: #eef4f8; font-size: 7.5pt; font-weight: bold; color: #4a6070; }
        .sec tr.tot td { background: #dce8f0; font-weight: bold; border-top: 1pt solid #a8c4d8; }
        .sec tr:last-child td { border-bottom: none; }

        /* Firmas */
        .firmas        { width: 100%; border-collapse: collapse; margin-top: 18pt; }
        .firmas td     { width: 50%; padding: 0 18pt; text-align: center; }
        .firma-line    { border-top: 1pt solid #4a7c9e; margin-top: 30pt;
                         padding-top: 4pt; font-size: 7.5pt; color: #4a6070; }

        /* Footer */
        .foot          { width: 100%; border-collapse: collapse; border-top: 1pt solid #c0d8e8; margin-top: 10pt; }
        .foot td       { padding: 4pt 0; font-size: 6.5pt; color: #a0b4c0; }

        @endif

    </style>
</head>
<body>

@if(($formato ?? 'a4') === 'ticket')

{{-- ══ TICKET 80 MM ══════════════════════════════════════════ --}}

<p class="empresa">{{ strtoupper($empresa->name) }}</p>
@if($empresa->ruc)      <p class="empresa-sub">RUC: {{ $empresa->ruc }}</p>@endif
@if($empresa->direccion)<p class="empresa-sub">{{ $empresa->direccion }}</p>@endif
@if($empresa->telefono) <p class="empresa-sub">Tel: {{ $empresa->telefono }}</p>@endif

<p class="doc-title">ARQUEO DE CAJA #{{ $sesion->id }}</p>

{{-- Datos de sesión --}}
<table class="kv">
    <tr><td class="k">Caja</td>    <td class="v">{{ $sesion->caja?->nombre ?? '—' }}</td></tr>
    <tr><td class="k">Cajero</td>  <td class="v">{{ $sesion->cajero?->name ?? '—' }}</td></tr>
    <tr><td class="k">Apertura</td><td class="v">{{ $sesion->fecha_apertura?->format('d/m/Y H:i') }}</td></tr>
    <tr><td class="k">Cierre</td>  <td class="v">{{ $sesion->fecha_cierre?->format('d/m/Y H:i') ?? 'En curso' }}</td></tr>
    @if($sesion->fecha_cierre)
        @php $diff = $sesion->fecha_apertura->diff($sesion->fecha_cierre); @endphp
        <tr><td class="k">Duración</td><td class="v">{{ sprintf('%dh %02dm', $diff->h + ($diff->days * 24), $diff->i) }}</td></tr>
    @endif
</table>

{{-- Ventas --}}
<hr class="sep-dash">
<p class="sec-label">Ventas</p>
<table class="kv">
    <tr><td class="k">Completadas</td>   <td class="v">{{ number_format($ventas->cnt_comp ?? 0) }} vta{{ ($ventas->cnt_comp ?? 0) != 1 ? 's' : '' }}</td></tr>
    <tr><td class="k">Total facturado</td><td class="v">S/ {{ number_format($ventas->tot_total ?? 0, 2) }}</td></tr>
    <tr><td class="k">Anuladas</td>      <td class="v">{{ number_format($ventas->cnt_anu ?? 0) }} (S/ {{ number_format($ventas->tot_anu ?? 0, 2) }})</td></tr>
    <tr><td class="k">Fondo apertura</td><td class="v">S/ {{ number_format($sesion->monto_apertura ?? 0, 2) }}</td></tr>
</table>

{{-- Cuadre --}}
@if(($sesion->total_sistema ?? 0) > 0 || ($sesion->total_cajero ?? 0) > 0)
    @php $dif = (float)($sesion->diferencia_total ?? 0); @endphp
    <hr class="sep-dash">
    <p class="sec-label">Cuadre</p>
    <table class="kv">
        <tr><td class="k">Sistema</td>      <td class="v">S/ {{ number_format($sesion->total_sistema ?? 0, 2) }}</td></tr>
        <tr><td class="k">Cajero</td>       <td class="v">S/ {{ number_format($sesion->total_cajero ?? 0, 2) }}</td></tr>
        @if(($sesion->total_creditos ?? 0) > 0)
            <tr><td class="k">Créditos</td><td class="v">S/ {{ number_format($sesion->total_creditos, 2) }}</td></tr>
        @endif
        <tr><td class="k bold">Diferencia</td><td class="v">{{ $dif >= 0 ? '+' : '' }}S/ {{ number_format($dif, 2) }}</td></tr>
    </table>
@endif

{{-- Métodos de pago --}}
@if($sesion->pagos->isNotEmpty())
    <hr class="sep-dash">
    <p class="sec-label">Métodos de pago</p>
    <table class="tbl">
        <tr class="hd"><td>Método</td><td class="n">Sistema</td><td class="n">Cajero</td></tr>
        @php $tS = 0; $tC = 0; @endphp
        @foreach($sesion->pagos as $p)
            @php $tS += $p->importe_sistema; $tC += ($p->importe_cajero ?? 0); @endphp
            <tr>
                <td>{{ $p->metodoPago?->nombre ?? '—' }}</td>
                <td class="n">{{ number_format($p->importe_sistema, 2) }}</td>
                <td class="n">{{ number_format($p->importe_cajero ?? 0, 2) }}</td>
            </tr>
        @endforeach
        <tr class="ft">
            <td>TOTAL</td>
            <td class="n">{{ number_format($tS, 2) }}</td>
            <td class="n">{{ number_format($tC, 2) }}</td>
        </tr>
    </table>
@endif

{{-- Comprobantes --}}
@if($comprobantes->isNotEmpty())
    <hr class="sep-dash">
    <p class="sec-label">Comprobantes</p>
    <table class="tbl">
        <tr class="hd"><td>Tipo</td><td class="n">Cant</td><td class="n">Total</td></tr>
        @foreach($comprobantes as $c)
            <tr>
                <td>{{ ucfirst($c->tipo) }}</td>
                <td class="n">{{ $c->cnt }}</td>
                <td class="n">S/{{ number_format($c->total, 2) }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Resumen financiero --}}
<hr class="sep-dash">
<p class="sec-label">Resumen financiero</p>
@php
    $neta = ($ventas->tot_total ?? 0) - ($ventas->igv ?? 0);
    $util = $neta - ($ventas->costo ?? 0);
    $mg   = $neta > 0 ? round(($util / $neta) * 100, 1) : 0;
@endphp
<table class="kv">
    <tr><td class="k">IGV 18%</td>      <td class="v">S/ {{ number_format($ventas->igv ?? 0, 2) }}</td></tr>
    <tr><td class="k">Base impon.</td>  <td class="v">S/ {{ number_format($neta, 2) }}</td></tr>
    <tr><td class="k">Costo</td>        <td class="v">S/ {{ number_format($ventas->costo ?? 0, 2) }}</td></tr>
    @if(($ventas->descuento ?? 0) > 0)
        <tr><td class="k">Descuentos</td><td class="v">S/ {{ number_format($ventas->descuento, 2) }}</td></tr>
    @endif
    <tr><td class="k bold">Utilidad</td><td class="v">S/ {{ number_format($util, 2) }} ({{ $mg }}%)</td></tr>
</table>

{{-- Cortesías --}}
@if($cortesias->isNotEmpty())
    <hr class="sep-dash">
    <p class="sec-label">Cortesías</p>
    <table class="tbl">
        <tr class="hd"><td>Producto</td><td class="n">Qty</td></tr>
        @foreach($cortesias as $c)
            <tr>
                <td>{{ $c->descripcion }}</td>
                <td class="n">{{ number_format($c->qty, 0) }}</td>
            </tr>
        @endforeach
        <tr class="ft">
            <td>Total unidades</td>
            <td class="n">{{ number_format($cortesias->sum('qty'), 0) }}</td>
        </tr>
    </table>
@endif

{{-- Movimientos --}}
@if($movResumen->isNotEmpty() || $movDetalle->isNotEmpty())
    <hr class="sep-dash">
    <p class="sec-label">Movimientos</p>
    @foreach([
        ['ingreso','aprobado','Ing. aprobados'],
        ['ingreso','anulado', 'Ing. anulados'],
        ['egreso', 'aprobado','Egr. aprobados'],
        ['egreso', 'anulado', 'Egr. anulados'],
    ] as [$t, $e, $l])
        @php $r = $movResumen->get("{$t}_{$e}"); @endphp
        @if($r)
            <table class="kv">
                <tr>
                    <td class="k">{{ $l }}</td>
                    <td class="v">{{ $r->cnt }} · S/ {{ number_format($r->tot, 2) }}</td>
                </tr>
            </table>
        @endif
    @endforeach
    @if($movDetalle->isNotEmpty())
        <table class="tbl" style="margin-top:2pt">
            <tr class="hd"><td>Concepto</td><td class="n">Monto</td></tr>
            @foreach($movDetalle as $m)
                <tr>
                    <td style="font-size:6pt">
                        {{ $m->concepto ?? ($m->tipo === 'ingreso' ? 'Ingreso' : 'Egreso') }}
                        {{ $m->estado === 'anulado' ? ' [A]' : '' }}
                    </td>
                    <td class="n">{{ $m->tipo === 'ingreso' ? '+' : '-' }}{{ number_format($m->monto, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endif

{{-- Notas --}}
@if($sesion->notas_cierre)
    <hr class="sep-dash">
    <p class="sec-label">Notas</p>
    <p class="note">{{ $sesion->notas_cierre }}</p>
@endif

{{-- Firmas --}}
<hr class="sep-solid">
<table style="width:100%;border-collapse:collapse">
    <tr>
        <td class="firma-line" style="width:48%">Cajero<br>{{ $sesion->cajero?->name ?? '' }}</td>
        <td style="width:4%"></td>
        <td class="firma-line" style="width:48%">Supervisor</td>
    </tr>
</table>
<hr class="sep-dash">
<p class="footer-t">{{ $generadoEn }} · SAS-PDV · Sesión #{{ $sesion->id }}</p>

@else

{{-- ══ A4 ════════════════════════════════════════════════════ --}}

{{-- Encabezado --}}
<table class="hdr" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:58%;vertical-align:top">
            <div class="h-empresa">{{ $empresa->name }}</div>
            @if($empresa->ruc)      <div class="h-sub">RUC: {{ $empresa->ruc }}</div>@endif
            @if($empresa->direccion)<div class="h-sub">{{ $empresa->direccion }}</div>@endif
            @if($empresa->telefono) <div class="h-sub">Tel: {{ $empresa->telefono }}</div>@endif
        </td>
        <td style="width:42%;vertical-align:top">
            <div class="h-titulo">ARQUEO DE CAJA</div>
            <div class="h-doc">Reporte de cierre de sesión</div>
            <div class="h-doc" style="margin-top:3pt;font-weight:bold;color:#1a3a5c">Sesión #{{ $sesion->id }}</div>
            <div class="h-doc">Generado: {{ $generadoEn }}</div>
        </td>
    </tr>
</table>

{{-- Banda sesión --}}
<table class="band" cellpadding="0" cellspacing="0">
    <tr>
        <td><span class="lbl">Caja</span><span class="val">{{ $sesion->caja?->nombre ?? '—' }}</span></td>
        <td class="div"></td>
        <td><span class="lbl">Cajero</span><span class="val">{{ $sesion->cajero?->name ?? '—' }}</span></td>
        <td class="div"></td>
        <td><span class="lbl">Apertura</span><span class="val">{{ $sesion->fecha_apertura?->format('d/m/Y H:i') ?? '—' }}</span></td>
        <td class="div"></td>
        <td><span class="lbl">Cierre</span><span class="val">{{ $sesion->fecha_cierre?->format('d/m/Y H:i') ?? 'En curso' }}</span></td>
        <td class="div"></td>
        <td>
            <span class="lbl">Duración</span>
            <span class="val">
                @if($sesion->fecha_cierre)
                    @php $d = $sesion->fecha_apertura->diff($sesion->fecha_cierre); @endphp
                    {{ sprintf('%dh %02dm', $d->h + ($d->days * 24), $d->i) }}
                @else—@endif
            </span>
        </td>
    </tr>
</table>

{{-- KPIs --}}
<table class="kpi-wrap" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:25%">
            <div class="kpi-lbl">Ventas completadas</div>
            <div class="kpi-val">{{ number_format($ventas->cnt_comp ?? 0) }}</div>
            <div class="kpi-sub">transacciones</div>
        </td>
        <td style="width:25%">
            <div class="kpi-lbl">Ventas anuladas</div>
            <div class="kpi-val red">{{ number_format($ventas->cnt_anu ?? 0) }}</div>
            <div class="kpi-sub">S/ {{ number_format($ventas->tot_anu ?? 0, 2) }}</div>
        </td>
        <td style="width:25%">
            <div class="kpi-lbl">Total facturado</div>
            <div class="kpi-val">S/ {{ number_format($ventas->tot_total ?? 0, 2) }}</div>
            <div class="kpi-sub">ventas completadas</div>
        </td>
        <td style="width:25%">
            <div class="kpi-lbl">Fondo apertura</div>
            <div class="kpi-val grn">S/ {{ number_format($sesion->monto_apertura ?? 0, 2) }}</div>
            <div class="kpi-sub">efectivo inicial</div>
        </td>
    </tr>
</table>

{{-- Cuadre --}}
@if(($sesion->total_sistema ?? 0) > 0 || ($sesion->total_cajero ?? 0) > 0)
    @php
        $dif = (float)($sesion->diferencia_total ?? 0);
        $dc  = $dif == 0 ? 'grn' : ($dif > 0 ? 'amb' : 'red');
    @endphp
    <table class="sec" cellpadding="0" cellspacing="0">
        <tr class="sh"><td colspan="5">Cuadre de Caja</td></tr>
    </table>
    <table class="cuadre" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:20%">
                <div class="cuadre-lbl">Fondo apertura</div>
                <div class="cuadre-val">S/ {{ number_format($sesion->monto_apertura ?? 0, 2) }}</div>
            </td>
            <td style="width:20%">
                <div class="cuadre-lbl">Total sistema</div>
                <div class="cuadre-val">S/ {{ number_format($sesion->total_sistema ?? 0, 2) }}</div>
            </td>
            <td style="width:20%">
                <div class="cuadre-lbl">Total cajero</div>
                <div class="cuadre-val">S/ {{ number_format($sesion->total_cajero ?? 0, 2) }}</div>
            </td>
            @if(($sesion->total_creditos ?? 0) > 0)
                <td style="width:20%">
                    <div class="cuadre-lbl">Créditos otorgados</div>
                    <div class="cuadre-val amb">S/ {{ number_format($sesion->total_creditos, 2) }}</div>
                </td>
            @endif
            <td>
                <div class="cuadre-lbl">Diferencia</div>
                <div class="cuadre-val {{ $dc }}">{{ $dif >= 0 ? '+' : '' }}S/ {{ number_format($dif, 2) }}</div>
            </td>
        </tr>
    </table>
@endif

{{-- Métodos de pago --}}
@if($sesion->pagos->isNotEmpty())
    <table class="sec" cellpadding="0" cellspacing="0">
        <tr class="sh"><td colspan="5">Ventas por Método de Pago</td></tr>
        <tr class="th">
            <td>Método</td>
            <td class="tr">Sistema</td>
            <td class="tr">Cajero</td>
            <td class="tr">Diferencia</td>
            <td class="tc" style="width:5%">OK</td>
        </tr>
        @php $tS = 0; $tC = 0; @endphp
        @foreach($sesion->pagos as $i => $pg)
            @php
                $s  = (float)$pg->importe_sistema;
                $c  = (float)($pg->importe_cajero ?? 0);
                $d  = $c - $s;
                $tS += $s; $tC += $c;
                $dc = $d == 0 ? 'grn' : ($d > 0 ? 'amb' : 'red');
            @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="bold">{{ $pg->metodoPago?->nombre ?? '—' }}</td>
                <td class="tr mono">S/ {{ number_format($s, 2) }}</td>
                <td class="tr mono">S/ {{ number_format($c, 2) }}</td>
                <td class="tr mono {{ $dc }}">{{ $d >= 0 ? '+' : '' }}S/ {{ number_format($d, 2) }}</td>
                <td class="tc mut">{{ $pg->importe_cajero !== null ? '✔' : '—' }}</td>
            </tr>
        @endforeach
        @php $dt = $tC - $tS; $dct = $dt == 0 ? 'grn' : ($dt > 0 ? 'amb' : 'red'); @endphp
        <tr class="tot">
            <td class="bold">TOTAL</td>
            <td class="tr bold mono">S/ {{ number_format($tS, 2) }}</td>
            <td class="tr bold mono">S/ {{ number_format($tC, 2) }}</td>
            <td class="tr bold mono {{ $dct }}">{{ $dt >= 0 ? '+' : '' }}S/ {{ number_format($dt, 2) }}</td>
            <td></td>
        </tr>
    </table>
@endif

{{-- Comprobantes + Resumen financiero --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10pt;border-collapse:separate;border-spacing:6pt 0">
    <tr>
        <td width="48%" valign="top">
            <table class="sec" cellpadding="0" cellspacing="0">
                <tr class="sh"><td colspan="3">Comprobantes Emitidos</td></tr>
                <tr class="th"><td>Tipo</td><td class="tr">Cantidad</td><td class="tr">Total</td></tr>
                @forelse($comprobantes as $i => $comp)
                    <tr class="{{ $i % 2 ? 'alt' : '' }}">
                        <td>{{ ucfirst($comp->tipo) }}</td>
                        <td class="tr">{{ number_format($comp->cnt) }}</td>
                        <td class="tr mono">S/ {{ number_format($comp->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="tc mut">Sin comprobantes</td></tr>
                @endforelse
            </table>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top">
            @php
                $neta = ($ventas->tot_total ?? 0) - ($ventas->igv ?? 0);
                $util = $neta - (float)($ventas->costo ?? 0);
                $mg   = $neta > 0 ? round(($util / $neta) * 100, 1) : 0;
            @endphp
            <table class="sec" cellpadding="0" cellspacing="0">
                <tr class="sh"><td colspan="2">Resumen Financiero</td></tr>
                <tr>         <td>Total facturado</td><td class="tr bold mono">S/ {{ number_format($ventas->tot_total ?? 0, 2) }}</td></tr>
                <tr class="alt"><td>IGV (18%)</td>   <td class="tr mono">S/ {{ number_format($ventas->igv ?? 0, 2) }}</td></tr>
                <tr>         <td>Base imponible</td>  <td class="tr mono">S/ {{ number_format($neta, 2) }}</td></tr>
                <tr class="alt"><td>Costo de ventas</td><td class="tr mono">S/ {{ number_format($ventas->costo ?? 0, 2) }}</td></tr>
                @if(($ventas->descuento ?? 0) > 0)
                    <tr><td>Descuentos</td><td class="tr mono amb">S/ {{ number_format($ventas->descuento, 2) }}</td></tr>
                @endif
                <tr class="tot">
                    <td>Utilidad bruta</td>
                    <td class="tr bold mono {{ $util >= 0 ? 'grn' : 'red' }}">S/ {{ number_format($util, 2) }}</td>
                </tr>
                <tr><td class="mut">Margen bruto</td><td class="tr bold">{{ number_format($mg, 1) }}%</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Cortesías --}}
@if($cortesias->isNotEmpty())
    <table class="sec" cellpadding="0" cellspacing="0">
        <tr class="sh"><td colspan="3">Cortesías Entregadas</td></tr>
        <tr class="th">
            <td>Producto / Descripción</td>
            <td class="tr">Unidades</td>
            <td class="tr">En ventas</td>
        </tr>
        @foreach($cortesias as $i => $ct)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td>{{ $ct->descripcion }}</td>
                <td class="tr mono bold">{{ number_format($ct->qty, $ct->qty == floor($ct->qty) ? 0 : 3) }}</td>
                <td class="tr mut">{{ $ct->en_ventas }}</td>
            </tr>
        @endforeach
        <tr class="tot">
            <td class="bold">Total unidades</td>
            <td class="tr bold mono">{{ number_format($cortesias->sum('qty'), 0) }}</td>
            <td></td>
        </tr>
    </table>
@endif

{{-- Movimientos resumen --}}
<table class="sec" cellpadding="0" cellspacing="0">
    <tr class="sh"><td colspan="4">Movimientos de Caja — Resumen</td></tr>
    <tr class="th"><td colspan="2">Tipo</td><td class="tr">Registros</td><td class="tr">Total</td></tr>
    @foreach([
        ['ingreso','aprobado','Ingresos aprobados'],
        ['ingreso','anulado', 'Ingresos anulados'],
        ['egreso', 'aprobado','Egresos aprobados'],
        ['egreso', 'anulado', 'Egresos anulados'],
    ] as [$t, $e, $l])
        @php $row = $movResumen->get("{$t}_{$e}"); @endphp
        @if($row)
            <tr>
                <td colspan="2" style="color:{{ $e==='aprobado'&&$t==='ingreso'?'#27ae60':($e==='anulado'?'#c0392b':($t==='egreso'&&$e==='aprobado'?'#d68910':'#7f8c8d')) }}">
                    {{ $l }}
                </td>
                <td class="tr mut">{{ $row->cnt }}</td>
                <td class="tr bold mono">S/ {{ number_format($row->tot, 2) }}</td>
            </tr>
        @endif
    @endforeach
</table>

{{-- Movimientos detalle --}}
@if($movDetalle->isNotEmpty())
    <table class="sec" cellpadding="0" cellspacing="0" style="margin-top:3pt">
        <tr class="sh2"><td colspan="4">Movimientos Manuales — Detalle</td></tr>
        <tr class="th">
            <td style="width:15%">Fecha/Hora</td>
            <td>Concepto</td>
            <td style="width:18%">Método</td>
            <td class="tr" style="width:16%">Monto</td>
        </tr>
        @foreach($movDetalle as $i => $mov)
            @php $ing = $mov->tipo === 'ingreso'; $ok = $mov->estado === 'aprobado'; @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td class="mut mono" style="font-size:7pt">
                    {{ $mov->fecha ? \Carbon\Carbon::parse($mov->fecha)->format('d/m H:i') : '—' }}
                </td>
                <td>
                    {{ $mov->concepto ?: '—' }}
                    @if(!$ok)<span style="color:#c0392b;font-size:6pt"> [anulado]</span>@endif
                </td>
                <td class="mut" style="font-size:7.5pt">{{ $mov->metodo ?? '—' }}</td>
                <td class="tr mono bold {{ $ing ? 'grn' : 'red' }}">{{ $ing ? '+' : '−' }}S/ {{ number_format($mov->monto, 2) }}</td>
            </tr>
        @endforeach
    </table>
@endif

{{-- Notas --}}
@if($sesion->notas_cierre)
    <table class="sec" cellpadding="0" cellspacing="0">
        <tr class="sh"><td>Notas de Cierre</td></tr>
        <tr><td style="padding:6pt 8pt;font-size:8pt;color:#4a6070;font-style:italic">{{ $sesion->notas_cierre }}</td></tr>
    </table>
@endif

{{-- Firmas --}}
<table class="firmas" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <div class="firma-line">
                Firma y sello del Cajero<br>
                <span class="mut">{{ $sesion->cajero?->name ?? '' }}</span>
            </div>
        </td>
        <td>
            <div class="firma-line">
                Firma y sello del Supervisor<br>
                <span class="mut">DNI: _________________</span>
            </div>
        </td>
    </tr>
</table>

{{-- Footer --}}
<table class="foot" cellpadding="0" cellspacing="0">
    <tr>
        <td>Generado: {{ $generadoEn }} &nbsp;·&nbsp; SAS-PDV &nbsp;·&nbsp; Sesión #{{ $sesion->id }}</td>
        <td class="tr">Documento de uso interno</td>
    </tr>
</table>

@endif
</body>
</html>
