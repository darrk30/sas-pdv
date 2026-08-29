<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{{ ($venta->serie?->serie ?? '') }}-{{ str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT) }}</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    width: 80mm;
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    font-size: 10px;
    color: #000;
    background: #fff;
    line-height: 1.35;
}

.tk { width: 80mm; padding: 4mm 3mm 6mm; }

/* ── Empresa ──────────────────────────────────────── */
.tk-logo {
    display: block;
    max-width: 50mm;
    max-height: 20mm;
    object-fit: contain;
    margin: 0 auto 2mm;
}
.tk-empresa { text-align: center; margin-bottom: 2mm; }
.tk-empresa-nombre {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.tk-empresa-info { font-size: 9.5px; margin-top: 1mm; line-height: 1.5; }


/* ── Comprobante ──────────────────────────────────── */
.tk-comp { text-align: center; margin: 1.5mm 0; }
.tk-comp-tipo { font-size: 11.5px; font-weight: 700; text-transform: uppercase; }
.tk-comp-num  { font-size: 11px; font-weight: 700; }

/* ── Datos ────────────────────────────────────────── */
.tk-datos { font-size: 9.5px; margin: 1mm 0; }
.tk-datos-fila { display: flex; gap: 1mm; margin-bottom: .5mm; }
.tk-datos-label { font-weight: 700; white-space: nowrap; }

/* ── Separador ────────────────────────────────────── */
.sep { border: none; border-top: 0.5px solid #000; margin: 1mm 0; }

/* ── Tabla ítems ──────────────────────────────────── */
.tk-tabla { width: 100%; border-collapse: collapse; font-size: 9.5px; margin: 1mm 0; }
.tk-tabla thead tr { border-bottom: 1px solid #000; }
.tk-tabla th {
    font-weight: 700;
    font-size: 9px;
    text-transform: uppercase;
    padding: 0 1mm 1.5mm;
    text-align: left;
}
.tk-tabla th.r, .tk-tabla td.r { text-align: right; white-space: nowrap; }
.tk-tabla th.c, .tk-tabla td.c { text-align: center; }
.tk-tabla tbody tr:last-child td { border-bottom: none; padding-bottom: 1.5mm; }
.tk-tabla td { padding: 1mm 1mm; vertical-align: top; }
.td-desc { word-break: break-word; }
.td-und  { font-size: 8.5px; color: #444; white-space: nowrap; }

/* ── Totales ──────────────────────────────────────── */
.tk-tots { font-size: 9.5px; margin: 1mm 0; }
.tk-tot-fila { display: flex; justify-content: space-between; padding: .5mm 0; }
.tk-tot-fila--total {
    font-size: 13px;
    font-weight: 700;
    border-top: 1px solid #000;
    padding-top: 1.5mm;
    margin-top: .5mm;
}
.tk-tot-letras { font-size: 9px; font-style: italic; margin-top: 1mm; }

/* ── Pagos / QR ───────────────────────────────────── */
.tk-fe-wrap { display: flex; gap: 2mm; align-items: flex-start; margin: 1.5mm 0; }
.tk-qr { flex-shrink: 0; }
.tk-qr img { width: 30mm; height: 30mm; display: block; }
.tk-fe-data { font-size: 8.5px; line-height: 1.5; flex: 1; }
.tk-fe-data strong { font-size: 8px; }
.tk-hash { font-size: 7px; color: #444; word-break: break-all; margin-bottom: 1mm; }

.tk-pago-bloque { font-size: 9.5px; margin: 1mm 0; }
.tk-pago-fila { display: flex; justify-content: space-between; padding: .4mm 0; }
.tk-pago-label { font-weight: 700; }

/* ── Pie ──────────────────────────────────────────── */
.tk-footer { text-align: center; margin-top: 2mm; font-size: 9px; line-height: 1.6; }
.tk-footer-gracias { font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 1.5mm; }
.tk-no-comp { font-size: 8.5px; font-style: italic; margin-top: 2mm; text-align: center; }

/* ── Botón imprimir ───────────────────────────────── */
.btn-print {
    display: block; width: 100%; padding: 7px; margin-top: 5mm;
    background: #1e293b; color: #fff; border: none;
    border-radius: 4px; font-size: 12px; cursor: pointer;
    font-family: sans-serif;
}
@media print {
    @page { size: 80mm auto; margin: 3mm 0; }
    html, body { width: 80mm; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>
@php
    use App\Enums\TipoComprobante;
    $serie       = $venta->serie;
    $tipoEnum    = $serie?->tipo;
    $comprobante = ($serie?->serie ?? '---') . ' - ' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
    $esFactura   = $tipoEnum === TipoComprobante::Factura;
    $esBoleta    = $tipoEnum === TipoComprobante::Boleta;
    $esTicket    = $tipoEnum === TipoComprobante::Ticket;
    $esSin       = $tipoEnum === TipoComprobante::SinComprobante || $tipoEnum === null;
    $esFE        = $esFactura || $esBoleta;
    $tieneIgv    = $esFE && (float) $venta->igv > 0;

    $pagos       = $venta->pagos->filter(fn($p) => $p->monto > 0);
    $totalPagado = $pagos->sum('monto');
    $vuelto      = max(0, $totalPagado - (float) $venta->total);
    $condicion   = ((float)($venta->saldo_pendiente ?? 0) > 0) ? 'CRÉDITO' : 'CONTADO';

    $clienteNombre = $venta->cliente_nombre ?: ($venta->cliente?->razon_social ?? $venta->cliente?->nombre ?? 'PUBLICO EN GENERAL');
    $clienteDoc    = $venta->cliente_num_doc   ?: ($venta->cliente?->numero_documento ?? '00000000');
    $clienteTipoDoc = strtoupper($venta->cliente_tipo_doc ?? $venta->cliente?->tipo_documento ?? 'DNI');
    $clienteTel    = $venta->cliente?->telefono ?? '—';
    $clienteDir    = $venta->cliente?->direccion ?? '—';

    $fechaEmision  = $venta->fecha_emision?->format('d/m/Y H:i') ?? $venta->created_at->format('d/m/Y H:i');
    $cajero        = $venta->sesionCaja?->cajero?->name ?? null;

    $qrBase64 = $qrBase64 ?? null;
@endphp

<div class="tk">

    {{-- ══ CABECERA ══ --}}
    <div class="tk-empresa">
        @if($empresa->logo)
            <img src="{{ asset('storage/'.$empresa->logo) }}" class="tk-logo" alt="Logo">
        @endif
        <div class="tk-empresa-nombre">{{ $empresa->name }}</div>
        <div class="tk-empresa-info">
            @if($empresa->ruc) RUC: {{ $empresa->ruc }}<br>@endif
            @if($empresa->direccion) {{ $empresa->direccion }}<br>@endif
            @if($empresa->provincia || $empresa->departamento)
                {{ implode(' - ', array_filter([$empresa->provincia ?? null, $empresa->departamento ?? null])) }}<br>
            @endif
            @if($empresa->telefono) TELF: {{ $empresa->telefono }}@endif
        </div>
    </div>

    <div style="margin:2mm 0"></div>

    {{-- ══ TIPO COMPROBANTE ══ --}}
    <div class="tk-comp">
        <div class="tk-comp-tipo">
            @if($esFactura) FACTURA ELECTRÓNICA
            @elseif($esBoleta) BOL. ELECTRÓNICA
            @elseif($esTicket) TICKET DE VENTA
            @else COMPROBANTE DE VENTA
            @endif
        </div>
        <div class="tk-comp-num">{{ $comprobante }}</div>
    </div>

    <div class="sep"></div>

    {{-- ══ DATOS ══ --}}
    <div class="tk-datos">
        @if($cajero)
        <div class="tk-datos-fila"><span class="tk-datos-label">CAJERO:</span><span>{{ $cajero }}</span></div>
        @endif
        <div class="tk-datos-fila"><span class="tk-datos-label">FECHA DE EMISION:</span><span>{{ $fechaEmision }}</span></div>
        <div class="tk-datos-fila"><span class="tk-datos-label">CLIENTE:</span><span>{{ $clienteNombre }}</span></div>
        <div class="tk-datos-fila"><span class="tk-datos-label">{{ $clienteTipoDoc }}:</span><span>{{ $clienteDoc }}</span></div>
        @if($clienteTel && $clienteTel !== '—')
        <div class="tk-datos-fila"><span class="tk-datos-label">TELEFONO:</span><span>{{ $clienteTel }}</span></div>
        @endif
        @if($clienteDir && $clienteDir !== '—')
        <div class="tk-datos-fila"><span class="tk-datos-label">DIRECCION:</span><span>{{ $clienteDir }}</span></div>
        @endif
    </div>

    <div class="sep"></div>

    {{-- ══ PRODUCTOS ══ --}}
    <table class="tk-tabla">
        <thead>
            <tr>
                <th style="width:7mm">CANT.</th>
                <th>PRODUCTO</th>
                <th class="r" style="width:11mm">P.U.</th>
                <th class="r" style="width:11mm">IMP.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $d)
            @php
                $cant    = (float) $d->cantidad;
                $cantFmt = $cant == floor($cant) ? number_format($cant,0) : rtrim(rtrim(number_format($cant,3,'.',''),'0'),'.');
            @endphp
            <tr>
                <td class="c">{{ $cantFmt }}</td>
                <td class="td-desc">{{ $d->descripcion }}</td>
                <td class="r">S/ {{ number_format($d->precio_unitario,2) }}</td>
                <td class="r">S/ {{ number_format($d->total,2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ══ TOTALES ══ --}}
    <div class="tk-tots">
        @if((float)$venta->descuento_total > 0)
        <div class="tk-tot-fila"><span>DESCUENTO</span><span>- S/ {{ number_format($venta->descuento_total,2) }}</span></div>
        @endif
        <div class="sep"></div>
        @if($tieneIgv)
        <div class="tk-tot-fila"><span>OP. GRAVADA</span><span>S/ {{ number_format($venta->op_gravadas,2) }}</span></div>
        <div class="tk-tot-fila"><span>IGV (18%)</span><span>S/ {{ number_format($venta->igv,2) }}</span></div>
        @endif

        <div class="tk-tot-fila tk-tot-fila--total">
            <span>TOTAL</span>
            <span>S/ {{ number_format($venta->total,2) }}</span>
        </div>

        @if($venta->total_letras)
        <div class="tk-tot-letras">SON: {{ strtoupper($venta->total_letras) }}</div>
        @endif
    </div>

    <div class="sep"></div>

    {{-- ══ PAGOS + QR ══ --}}
    @if($esFE && $qrBase64)
    <div class="tk-fe-wrap">
        <div class="tk-qr">
            <img src="{{ $qrBase64 }}" alt="QR">
        </div>
        <div class="tk-fe-data">
            @if($venta->hash)
            <strong>CÓDIGO HASH:</strong><div class="tk-hash">{{ $venta->hash }}</div>
            @endif
            <strong style="font-size:8px">MÉTODOS DE PAGO</strong><br>
            @foreach($pagos as $pago)
            {{ strtoupper($pago->metodoPago?->nombre ?? 'EFECTIVO') }}: S/ {{ number_format($pago->monto,2) }}<br>
            @endforeach
            @if($vuelto > 0)
            VUELTO: S/ {{ number_format($vuelto,2) }}<br>
            @endif
        </div>
    </div>
    @else
    @if($pagos->isNotEmpty())
    <div class="tk-pago-bloque">
        <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#555;margin-bottom:1mm">Métodos de pago</div>
        @foreach($pagos as $pago)
        <div class="tk-pago-fila">
            <span>{{ strtoupper($pago->metodoPago?->nombre ?? 'EFECTIVO') }}</span>
            <span>S/ {{ number_format($pago->monto,2) }}</span>
        </div>
        @if($pago->referencia)<div style="font-size:8.5px;color:#555;padding-left:1mm">Ref: {{ $pago->referencia }}</div>@endif
        @endforeach
        @if($vuelto > 0)
        <div class="tk-pago-fila"><span>VUELTO</span><span>S/ {{ number_format($vuelto,2) }}</span></div>
        @endif
        @if((float)($venta->saldo_pendiente??0)>0)
        <div class="tk-pago-fila" style="font-style:italic"><span>Saldo pendiente</span><span>S/ {{ number_format($venta->saldo_pendiente,2) }}</span></div>
        @endif
    </div>
    @endif
    @endif

    <div class="sep"></div>

    {{-- ══ PIE ══ --}}
    <div class="tk-footer">
        @if($esFE)
        Representación impresa de la
        {{ $esFactura ? 'FACTURA ELECTRÓNICA' : 'BOL. ELECTRÓNICA' }}<br>
        consulte en: <strong>www.sunat.gob.pe</strong><br>
        @endif
        @if($esTicket || $esSin)
        <div class="tk-no-comp">Este documento NO constituye un comprobante de pago electrónico.</div>
        @endif
        <div class="tk-footer-gracias">GRACIAS POR SU PREFERENCIA</div>
    </div>

    <button class="btn-print no-print" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>

</div>
<script>
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
}
</script>
</body>
</html>
