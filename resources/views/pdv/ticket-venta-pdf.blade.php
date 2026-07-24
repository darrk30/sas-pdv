<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 5mm 5mm; }

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 9pt;
    color: #000;
    width: 100%;
    line-height: 1.4;
}

/* ── Cabecera empresa ─────────────────────────────── */
.empresa {
    text-align: center;
    margin-bottom: 3mm;
}
.empresa-logo {
    max-width: 40mm;
    max-height: 15mm;
    display: block;
    margin: 0 auto 2mm;
}
.empresa-nombre {
    font-size: 11pt;
    font-weight: bold;
    text-transform: uppercase;
}
.empresa-info {
    font-size: 8pt;
    margin-top: 1mm;
    line-height: 1.5;
}

/* ── Separadores ──────────────────────────────────── */
.sep-dashed { border-top: 1px dashed #000; margin: 2mm 0; }
.sep-solid  { border-top: 1px solid #000;  margin: 2mm 0; }

/* ── Comprobante ──────────────────────────────────── */
.comprobante {
    text-align: center;
    margin-bottom: 1mm;
}
.comprobante-tipo { font-size: 10pt; font-weight: bold; text-transform: uppercase; }
.comprobante-num  { font-size: 9pt; font-weight: bold; }
.comprobante-fecha { font-size: 8pt; color: #333; }

/* ── Cliente ──────────────────────────────────────── */
.cliente { font-size: 8pt; margin-bottom: 1mm; }
.cliente-titulo { font-weight: bold; font-size: 8pt; text-transform: uppercase; margin-bottom: 1mm; }
.cliente table { width: 100%; border-collapse: collapse; }
.cliente td { padding: 0; vertical-align: top; }
.cliente-label { width: 14mm; color: #444; }

/* ── Tabla ítems ──────────────────────────────────── */
.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8pt;
    margin: 1mm 0;
}
.items-table th {
    text-align: left;
    font-weight: bold;
    font-size: 8pt;
    padding: 0 0.5mm 1mm;
    border-bottom: 1px solid #000;
}
.items-table th.r { text-align: right; }
.items-table td {
    padding: 0.8mm 0.5mm;
    vertical-align: top;
}
.items-table td.r { text-align: right; white-space: nowrap; }
.td-und { font-size: 7pt; color: #444; }
.td-desc { }

/* ── Totales ──────────────────────────────────────── */
.totales { font-size: 8pt; margin-top: 1mm; }
.totales table { width: 100%; border-collapse: collapse; }
.totales td { padding: 0.3mm 0; }
.totales td.val { text-align: right; white-space: nowrap; }
.total-grande {
    font-size: 11pt;
    font-weight: bold;
    border-top: 1px solid #000;
    padding-top: 1mm;
    margin-top: 1mm;
}
.total-grande table { width: 100%; }
.total-grande td.val { text-align: right; }

/* ── Pagos ────────────────────────────────────────── */
.pagos-titulo { font-weight: bold; font-size: 8pt; text-transform: uppercase; margin-bottom: 1mm; }
.pagos-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
.pagos-table td { padding: 0.3mm 0; vertical-align: top; }
.pagos-table td.val { text-align: right; white-space: nowrap; }
.pago-ref { font-size: 7pt; color: #555; }

/* ── Pie ──────────────────────────────────────────── */
.gracias {
    text-align: center;
    font-size: 10pt;
    font-weight: bold;
    margin: 2mm 0 1mm;
}
.subgracias {
    text-align: center;
    font-size: 8pt;
}
.no-comprobante {
    text-align: center;
    font-size: 7.5pt;
    font-style: italic;
    margin-top: 2mm;
    padding-top: 1mm;
    border-top: 1px dashed #000;
    line-height: 1.5;
}

/* ── QR y letras ──────────────────────────────────── */
.letras {
    font-size: 7.5pt;
    font-style: italic;
    margin: 2mm 0 1mm;
    line-height: 1.4;
}
.qr-wrap {
    text-align: center;
    margin: 2mm 0;
}
.qr-wrap img {
    width: 40mm;
    height: 40mm;
}
.qr-label {
    text-align: center;
    font-size: 7pt;
    color: #444;
    margin-top: 1mm;
}
.hash-fe {
    font-size: 6.5pt;
    color: #555;
    font-family: 'Courier New', monospace;
    word-break: break-all;
    margin-top: 1mm;
}
.nota-fe {
    text-align: center;
    font-size: 7pt;
    font-style: italic;
    margin-top: 1mm;
    line-height: 1.4;
    color: #333;
}
</style>
</head>
<body>
@php
    use App\Enums\TipoComprobante;
    $serie       = $venta->serie;
    $tipoEnum    = $serie?->tipo;
    $comprobante = ($serie?->serie ?? '---') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
    $esFactura   = $tipoEnum === TipoComprobante::Factura;
    $esBoleta    = $tipoEnum === TipoComprobante::Boleta;
    $esTicket    = $tipoEnum === TipoComprobante::Ticket;
    $esSin       = $tipoEnum === TipoComprobante::SinComprobante || $tipoEnum === null;
    $tieneIgv    = ($esFactura || $esBoleta) && (float) $venta->igv > 0;
    $esFE        = ($esFactura || $esBoleta) && ! empty($venta->qr_data);

    $clienteTel = $venta->cliente?->telefono;
    $clienteDir = $venta->cliente?->direccion ?? null;

    // DomPDF necesita rutas absolutas de disco para imágenes
    $logoPath = $empresa->logo ? public_path('storage/' . $empresa->logo) : null;
    // $qrBase64 viene del servicio cuando $esFE; sino null
    $qrBase64 = $qrBase64 ?? null;
@endphp

{{-- ══ CABECERA EMPRESA ══ --}}
<div class="empresa">
    @if ($logoPath && file_exists($logoPath))
        <img src="{{ $logoPath }}" class="empresa-logo" alt="Logo">
    @endif
    <div class="empresa-nombre">{{ $empresa->name }}</div>
    <div class="empresa-info">
        @if ($empresa->ruc)RUC: {{ $empresa->ruc }}<br>@endif
        @if ($empresa->direccion){{ $empresa->direccion }}<br>@endif
        @if ($empresa->provincia || $empresa->departamento)
            {{ implode(', ', array_filter([$empresa->provincia ?? null, $empresa->departamento ?? null])) }}<br>
        @endif
        @if ($empresa->telefono)Tel: {{ $empresa->telefono }}@endif
    </div>
</div>

<div class="sep-solid"></div>

{{-- ══ COMPROBANTE ══ --}}
<div class="comprobante">
    <div class="comprobante-tipo">
        @if ($esFactura) FACTURA ELECTRÓNICA
        @elseif ($esBoleta) BOLETA DE VENTA ELECTRÓNICA
        @elseif ($esTicket) TICKET DE VENTA
        @else COMPROBANTE DE VENTA
        @endif
    </div>
    <div class="comprobante-num">{{ $comprobante }}</div>
    <div class="comprobante-fecha">
        {{ $venta->fecha_emision?->format('d/m/Y H:i') ?? $venta->created_at->format('d/m/Y H:i') }}
    </div>
</div>

{{-- ══ CLIENTE ══ --}}
@if ($venta->cliente_nombre || $venta->cliente_num_doc)
<div class="sep-dashed"></div>
<div class="cliente">
    <div class="cliente-titulo">Cliente</div>
    <table>
        @if ($venta->cliente_nombre)
        <tr>
            <td class="cliente-label">Nombre:</td>
            <td>{{ $venta->cliente_nombre }}</td>
        </tr>
        @endif
        @if ($venta->cliente_num_doc)
        <tr>
            <td class="cliente-label">{{ strtoupper($venta->cliente_tipo_doc ?? 'Doc') }}:</td>
            <td>{{ $venta->cliente_num_doc }}</td>
        </tr>
        @endif
        @if ($clienteTel)
        <tr>
            <td class="cliente-label">Tel:</td>
            <td>{{ $clienteTel }}</td>
        </tr>
        @endif
        @if ($clienteDir)
        <tr>
            <td class="cliente-label">Dir:</td>
            <td>{{ $clienteDir }}</td>
        </tr>
        @endif
    </table>
</div>
@endif

<div class="sep-dashed"></div>

{{-- ══ ÍTEMS ══ --}}
<table class="items-table">
    <thead>
        <tr>
            <th>Cant</th>
            <th>Und</th>
            <th>Descripción</th>
            <th class="r">P.U.</th>
            <th class="r">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($venta->detalles as $d)
        @php
            $cant    = (float) $d->cantidad;
            $cantFmt = $cant == floor($cant)
                ? number_format($cant, 0)
                : rtrim(rtrim(number_format($cant, 3, '.', ''), '0'), '.');
            $simbolo = $d->producto?->unidadMedida?->simbolo
                ?? $d->variante?->producto?->unidadMedida?->simbolo
                ?? '';
        @endphp
        <tr>
            <td>{{ $cantFmt }}</td>
            <td class="td-und">{{ $simbolo }}</td>
            <td class="td-desc">{{ $d->descripcion }}</td>
            <td class="r">{{ number_format($d->precio_unitario, 2) }}</td>
            <td class="r">{{ number_format($d->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="sep-solid"></div>

{{-- ══ TOTALES ══ --}}
<div class="totales">
    @if ((float)$venta->descuento_total > 0)
    <table><tr>
        <td>Descuento</td>
        <td class="val">- S/ {{ number_format($venta->descuento_total, 2) }}</td>
    </tr></table>
    @endif

    @if ($tieneIgv)
    <table>
        <tr><td>Op. Gravada</td><td class="val">S/ {{ number_format($venta->op_gravadas, 2) }}</td></tr>
        <tr><td>IGV (18%)</td><td class="val">S/ {{ number_format($venta->igv, 2) }}</td></tr>
    </table>
    @endif

    <div class="total-grande">
        <table><tr>
            <td>TOTAL</td>
            <td class="val">S/ {{ number_format($venta->total, 2) }}</td>
        </tr></table>
    </div>
</div>

{{-- ══ MÉTODOS DE PAGO ══ --}}
@php $pagos = $venta->pagos->filter(fn($p) => $p->monto > 0); @endphp
@if ($pagos->isNotEmpty())
<div class="sep-dashed"></div>
<div class="pagos-titulo">Forma de pago</div>
<table class="pagos-table">
    @foreach ($pagos as $pago)
    <tr>
        <td>
            {{ $pago->metodoPago?->nombre ?? '—' }}
            @if ($pago->referencia)
                <br><span class="pago-ref">Ref: {{ $pago->referencia }}</span>
            @endif
        </td>
        <td class="val">S/ {{ number_format($pago->monto, 2) }}</td>
    </tr>
    @endforeach
    @if ((float)$venta->saldo_pendiente > 0)
    <tr>
        <td><em>Saldo pendiente</em></td>
        <td class="val">S/ {{ number_format($venta->saldo_pendiente, 2) }}</td>
    </tr>
    @endif
</table>
@endif

<div class="sep-dashed"></div>

{{-- ══ PIE ══ --}}
<div class="gracias">¡Gracias por su preferencia!</div>
<div class="subgracias">Vuelva pronto.</div>

@if ($esTicket || $esSin)
<div class="no-comprobante">
    Este documento NO constituye<br>
    un comprobante de pago electrónico.
</div>
@endif

{{-- ══ QR + LETRAS (solo boleta/factura electrónica) ══ --}}
@if ($esFE && $qrBase64)
<div class="sep-dashed"></div>

@if ($venta->total_letras)
<div class="letras">Son: {{ $venta->total_letras }}</div>
@endif

<div class="qr-wrap">
    <img src="{{ $qrBase64 }}" alt="QR SUNAT">
</div>
<div class="qr-label">Consulte en sunat.gob.pe</div>

@if ($venta->hash)
<div class="hash-fe">Hash: {{ $venta->hash }}</div>
@endif

<div class="nota-fe">
    Representación impresa de<br>
    {{ $esFactura ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA' }}
</div>
@endif

</body>
</html>
