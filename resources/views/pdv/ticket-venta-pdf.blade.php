<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { size: 80mm auto; margin: 0; }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 9pt;
    color: #000;
    line-height: 1.35;
    width: 100%;
}

/* ── Empresa ──────────────────────────────────── */
.empresa { text-align: center; margin-bottom: 2mm; }
.empresa-logo { display: block; max-width: 48mm; max-height: 18mm; margin: 0 auto 2mm; }
.empresa-nombre { font-size: 11pt; font-weight: bold; text-transform: uppercase; letter-spacing: .3pt; }
.empresa-info { font-size: 8pt; margin-top: 1mm; line-height: 1.5; }


/* ── Comprobante ──────────────────────────────── */
.comp { text-align: center; margin: 1.5mm 0; }
.comp-tipo { font-size: 11pt; font-weight: bold; text-transform: uppercase; }
.comp-num  { font-size: 10pt; font-weight: bold; }

/* ── Separador ────────────────────────────────── */
.sep { border-top: 0.4pt dashed #888; margin: 2mm 0; }

/* ── Datos ────────────────────────────────────── */
.datos { font-size: 8.5pt; margin: 1mm 0; }
.datos table { width: 100%; border-collapse: collapse; }
.datos td { padding: 0.3mm 0; vertical-align: top; }
.datos td.lbl { font-weight: bold; white-space: nowrap; padding-right: 1mm; }

/* ── Tabla ítems ──────────────────────────────── */
.items { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin: 1mm 0; }
.items thead tr { border-bottom: 0.4pt dashed #888; }
.items th {
    font-weight: bold; font-size: 8pt; text-transform: uppercase;
    text-align: left; padding: 0 0.5mm 1.5mm;
}
.items th.r { text-align: right; }
.items tbody tr:last-child td { border-bottom: none; padding-bottom: 1.5mm; }
.items td { padding: 0.8mm 0.5mm; vertical-align: top; }
.items td.r { text-align: right; white-space: nowrap; }
.items td.c { text-align: center; }

/* ── Totales ──────────────────────────────────── */
.tots { font-size: 8.5pt; margin: 1mm 0; }
.tots table { width: 100%; border-collapse: collapse; }
.tots td { padding: 0.4mm 0; }
.tots td.val { text-align: right; white-space: nowrap; }
.tot-total { font-size: 11.5pt; font-weight: bold; padding-top: 1mm; }
.tot-total table { width: 100%; }
.tot-total td.val { text-align: right; }
.letras { font-size: 8pt; font-style: italic; margin-top: 1mm; }

/* ── Pagos ────────────────────────────────────── */
.pagos { font-size: 8.5pt; margin: 1mm 0; }
.pagos table { width: 100%; border-collapse: collapse; }
.pagos td { padding: 0.4mm 0; vertical-align: top; }
.pagos td.val { text-align: right; white-space: nowrap; }
.pagos td.lbl { font-weight: bold; }

/* ── QR + FE data ─────────────────────────────── */
.fe-wrap { width: 100%; border-collapse: collapse; margin: 1.5mm 0; }
.fe-wrap td { vertical-align: top; padding: 0; }
.fe-qr { width: 22mm; }
.fe-qr img { width: 20mm; height: 20mm; display: block; }
.fe-data { font-size: 7.5pt; line-height: 1.5; padding-left: 2mm; }
.fe-hash { font-size: 6.5pt; color: #444; word-break: break-all; margin-bottom: 1mm; }

/* ── Pie ──────────────────────────────────────── */
.footer { text-align: center; margin-top: 2mm; font-size: 8pt; line-height: 1.7; }
.footer-gracias { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; margin-top: 1.5mm; }
.no-comp { font-size: 7.5pt; font-style: italic; margin-top: 1.5mm; }
</style>
</head>
<body>
<div style="padding: 3mm 5mm 4mm;">
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

    $clienteNombre  = $venta->cliente_nombre ?: ($venta->cliente?->razon_social ?? $venta->cliente?->nombre ?? 'PUBLICO EN GENERAL');
    $clienteDoc     = $venta->cliente_num_doc ?: ($venta->cliente?->numero_documento ?? '-');
    $clienteTipoDocRaw = strtoupper($venta->cliente_tipo_doc ?? $venta->cliente?->tipo_documento?->value ?? '-');
    $clienteTipoDocLabel = match($clienteTipoDocRaw) {
        'RUC'  => 'RUC',
        'DNI'  => 'DNI',
        default => 'DNI',
    };
    $fechaEmision   = $venta->fecha_emision?->format('d/m/Y H:i') ?? $venta->created_at->format('d/m/Y H:i');
    $cajero         = $venta->sesionCaja?->cajero?->name ?? null;

    $orden       = $venta->relationLoaded('orden') ? $venta->orden : null;
    $ordenNumero = $orden?->numero ? 'ORD-' . (int) $orden->numero : null;
    $tipoAtencionLabel = match($venta->tipo ?? '') {
        'llevar'      => 'LLEVAR',
        'delivery'    => 'DELIVERY',
        'restaurante' => 'MESA',
        default       => null,
    };
    $vendedorOrden     = $orden?->vendedor;
    $responsableNombre = $vendedorOrden?->name ?? $cajero;
    $responsableRol    = $vendedorOrden?->roles?->first()?->name ?? null;
    $ordenDir = $orden?->cliente_direccion ?: ($venta->cliente?->direccion ?? null);
    $ordenTel = $orden?->cliente_telefono  ?: ($venta->cliente?->telefono  ?? null);

    $logoPath = $empresa->logo ? public_path('storage/'.$empresa->logo) : null;
    $logoTukipu = public_path('img/logotukipu.webp');
    $qrBase64 = $qrBase64 ?? null;
@endphp

{{-- ══ EMPRESA ══ --}}
<div class="empresa">
    @if($logoPath && file_exists($logoPath))
        <img src="{{ $logoPath }}" class="empresa-logo" alt="Logo">
    @endif
    <div class="empresa-nombre">{{ $empresa->name }}</div>
    <div class="empresa-info">
        @if($empresa->ruc)RUC: {{ $empresa->ruc }}<br>@endif
        @if($empresa->direccion){{ $empresa->direccion }}<br>@endif
        @if($empresa->provincia || $empresa->departamento)
            {{ implode(' - ', array_filter([$empresa->provincia ?? null, $empresa->departamento ?? null])) }}<br>
        @endif
        @if($empresa->telefono)TELF: {{ $empresa->telefono }}@endif
    </div>
</div>

<div style="margin:2mm 0"></div>

{{-- ══ COMPROBANTE ══ --}}
<div class="comp">
    <div class="comp-tipo">
        @if($esFactura)FACTURA ELECTRÓNICA
        @elseif($esBoleta)BOL. ELECTRÓNICA
        @elseif($esTicket)TICKET DE VENTA
        @else COMPROBANTE DE VENTA
        @endif
    </div>
    <div class="comp-num">{{ $comprobante }}</div>
</div>

<div class="sep"></div>

{{-- ══ DATOS ══ --}}
<div class="datos">
<table>
    <tr><td class="lbl">FECHA DE EMISION:</td><td>{{ $fechaEmision }}</td></tr>
    <tr><td class="lbl">CLIENTE:</td><td>{{ $clienteNombre }}</td></tr>
    <tr><td class="lbl">{{ $clienteTipoDocLabel }}:</td><td>{{ $clienteDoc }}</td></tr>
    @if($ordenDir)
    <tr><td class="lbl">Dir.:</td><td>{{ $ordenDir }}</td></tr>
    @endif
    @if($ordenTel)
    <tr><td class="lbl">Telf.:</td><td>{{ $ordenTel }}</td></tr>
    @endif
</table>
</div>

<div class="sep"></div>

{{-- ══ PRODUCTOS ══ --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:8mm">CANT.</th>
            <th>PRODUCTO</th>
            <th class="r" style="width:12mm">P.U.</th>
            <th class="r" style="width:12mm">IMP.</th>
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
            <td>{{ $d->descripcion }}</td>
            <td class="r">{{ number_format($d->precio_unitario,2) }}</td>
            <td class="r">{{ number_format($d->total,2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ══ TOTALES ══ --}}
@php $hayFilasTotales = $tieneIgv || (float)$venta->descuento_total > 0; @endphp
<div class="tots">
    @if($hayFilasTotales)
    <table>
        @if((float)$venta->descuento_total > 0)
        <tr><td>DESCUENTO</td><td class="val">- S/ {{ number_format($venta->descuento_total,2) }}</td></tr>
        @endif
        @if($tieneIgv)
        <tr><td>SUB TOTAL</td><td class="val">S/ {{ number_format((float)$venta->op_gravadas + (float)$venta->op_exoneradas,2) }}</td></tr>
        <tr><td>OP. GRAVADA</td><td class="val">S/ {{ number_format($venta->op_gravadas,2) }}</td></tr>
        <tr><td>IGV</td><td class="val">S/ {{ number_format($venta->igv,2) }}</td></tr>
        @endif
    </table>
    <div class="sep" style="margin:1mm 0"></div>
    @endif
    <div class="tot-total">
        <table><tr><td>TOTAL</td><td class="val">S/ {{ number_format($venta->total,2) }}</td></tr></table>
    </div>
    @if($venta->total_letras)
    <div class="letras">SON: {{ strtoupper($venta->total_letras) }}</div>
    @endif
</div>

<div class="sep"></div>

{{-- ══ QR + CONDICION / PAGOS ══ --}}
@if($esFE && $qrBase64)
<table class="fe-wrap">
    <tr>
        <td class="fe-qr"><img src="{{ $qrBase64 }}" alt="QR"></td>
        <td class="fe-data">
            @if($venta->hash)
            <strong>CÓDIGO HASH:</strong><br>
            <div class="fe-hash">{{ $venta->hash }}</div>
            @endif
            <strong style="font-size:7pt">FORMAS DE PAGO</strong><br>
            @foreach($pagos as $pago)
            {{ strtoupper($pago->metodoPago?->nombre ?? 'EFECTIVO') }}: S/ {{ number_format($pago->monto,2) }}<br>
            @endforeach
            @if($vuelto > 0)VUELTO: S/ {{ number_format($vuelto,2) }}<br>@endif
        </td>
    </tr>
</table>
@else
@if($pagos->isNotEmpty())
<div class="pagos">
<div style="font-size:7pt;font-weight:bold;text-transform:uppercase;letter-spacing:.04em;color:#555;margin-bottom:1mm">Formas de pago</div>
<table>
    @foreach($pagos as $pago)
    <tr>
        <td>{{ strtoupper($pago->metodoPago?->nombre ?? 'EFECTIVO') }}</td>
        <td class="val">S/ {{ number_format($pago->monto,2) }}</td>
    </tr>
    @endforeach
    @if($vuelto > 0)
    <tr><td>VUELTO</td><td class="val">S/ {{ number_format($vuelto,2) }}</td></tr>
    @endif
    @if((float)($venta->saldo_pendiente??0)>0)
    <tr><td><em>Saldo pendiente</em></td><td class="val">S/ {{ number_format($venta->saldo_pendiente,2) }}</td></tr>
    @endif
</table>
</div>
@endif
@endif

{{-- ══ PEDIDO / ATENCIÓN / CAJERO / RESPONSABLE ══ --}}
@if($ordenNumero || $tipoAtencionLabel || $responsableNombre)
<div class="datos" style="margin-top:1mm;">
<table>
    @if($ordenNumero)
    <tr><td class="lbl">PEDIDO:</td><td>{{ $ordenNumero }}</td></tr>
    @endif
    @if($tipoAtencionLabel)
    <tr><td class="lbl">ATENCIÓN:</td><td>{{ $tipoAtencionLabel }} - CAJA</td></tr>
    @endif
    @if($responsableNombre)
    <tr>
        <td class="lbl">{{ $responsableRol ? strtoupper($responsableRol) : 'CAJERO' }}:</td>
        <td>{{ $responsableNombre }}</td>
    </tr>
    @endif
</table>
</div>
@endif

<div class="sep"></div>

{{-- ══ PIE ══ --}}
<div class="footer">
    @if($esFE)
    Representación impresa de la {{ $esFactura ? 'FACTURA ELECTRÓNICA' : 'BOL. ELECTRÓNICA' }}<br>
    consulte en: <strong>www.sunat.gob.pe</strong><br>
    @endif
    @if($esTicket || $esSin)
    <div class="no-comp">Este documento NO constituye un comprobante de pago electrónico.</div>
    @endif
    <div class="footer-gracias">GRACIAS POR SU PREFERENCIA</div>

    {{-- Branding Tukipu --}}
    <div style="margin-top:3mm;padding-top:1.5mm;border-top:0.4pt dashed #ccc;text-align:center;">
        @if(file_exists($logoTukipu))
        <img src="{{ $logoTukipu }}" alt="Tukipu" style="height:4mm;display:block;margin:0 auto .8mm;opacity:.65;">
        @endif
        <span style="font-size:6.5pt;color:#888;">© {{ date('Y') }}. Tukipu — Sistema de Gestión</span>
    </div>
</div>

</div>{{-- /wrapper padding --}}
</body>
</html>
