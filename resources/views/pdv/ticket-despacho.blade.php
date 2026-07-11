<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 10mm 8mm; }

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 9pt;
    color: #000;
    line-height: 1.5;
}

.wrap {
    margin: 0 6mm;
}

/* ── Separadores ──────────────────────────────────── */
.sep-dashed { border-top: 1px dashed #000; margin: 3mm 0; }
.sep-solid  { border-top: 2px solid #000;  margin: 3mm 0; }

/* ── Título despacho ──────────────────────────────── */
.dsp-titulo {
    text-align: center;
    font-size: 11pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: 2mm;
}
.dsp-subtitulo {
    text-align: center;
    font-size: 8pt;
    color: #333;
    line-height: 1.7;
}

/* ── Sección genérica ─────────────────────────────── */
.seccion-titulo {
    font-size: 7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #555;
    border-bottom: 1px solid #ccc;
    padding-bottom: 1mm;
    margin-bottom: 2mm;
}
.info-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
.info-table td { padding: .5mm 0; vertical-align: top; }
.info-label { width: 20mm; color: #444; font-size: 8pt; }

/* ── Tabla ítems ──────────────────────────────────── */
.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.5pt;
    margin-top: 1mm;
}
.items-table th {
    text-align: left;
    font-size: 7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #555;
    padding: 0 1mm 1.5mm;
    border-bottom: 1px solid #000;
}
.items-table th.r { text-align: right; }
.items-table td {
    padding: 1.2mm 1mm;
    vertical-align: top;
    border-bottom: 1px dashed #ccc;
}
.items-table tbody tr:last-child td { border-bottom: none; }
.items-table td.r { text-align: right; white-space: nowrap; }
.items-table td.cant { width: 7mm; font-weight: bold; }

/* ── Campos de envío (apilados) ───────────────────── */
.envio-campo {
    margin-bottom: 2.5mm;
}
.envio-label {
    font-size: 7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #555;
}
.envio-valor {
    font-size: 8.5pt;
    line-height: 1.5;
    word-break: break-word;
}

/* ── Pie ──────────────────────────────────────────── */
.pie {
    text-align: center;
    font-size: 7.5pt;
    color: #666;
    margin-top: 4mm;
    padding-top: 2mm;
    border-top: 1px dashed #aaa;
    line-height: 1.7;
}
</style>
</head>
<body>
@php
    $serie       = $venta->serie;
    $comprobante = ($serie?->serie ?? '---') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
    $orden       = $venta->orden;

    $clienteNombre  = $orden?->cliente_nombre ?: $venta->cliente_nombre ?: 'Cliente general';
    $clienteDoc     = $orden?->cliente_num_doc ?: $venta->cliente_num_doc;
    $clienteTipoDoc = strtoupper($orden?->cliente_tipo_doc ?: $venta->cliente_tipo_doc ?: 'Doc');
    $clienteTel     = $orden?->cliente_telefono ?: $venta->cliente?->telefono;

    $depPrvDst  = $orden?->notas_internas;
    $dirAgencia = $orden?->direccion_agencia;
@endphp
<div class="wrap">
{{-- ══ TÍTULO ══ --}}
<div class="dsp-titulo">Ticket de Despacho</div>
<div class="dsp-subtitulo">
    Venta: {{ $comprobante }}&nbsp;&nbsp;·&nbsp;&nbsp;{{ $venta->fecha_emision?->format('d/m/Y H:i') ?? $venta->created_at->format('d/m/Y H:i') }}
    @if ($orden?->numero)
        <br>Orden: #{{ $orden->numero }}
    @endif
</div>

<div class="sep-solid"></div>

{{-- ══ DATOS DEL CLIENTE ══ --}}
<div class="seccion-titulo">Datos del cliente</div>
<table class="info-table">
    <tr>
        <td class="info-label">Nombre:</td>
        <td>{{ $clienteNombre }}</td>
    </tr>
    @if ($clienteDoc)
    <tr>
        <td class="info-label">{{ $clienteTipoDoc }}:</td>
        <td>{{ $clienteDoc }}</td>
    </tr>
    @endif
    @if ($clienteTel)
    <tr>
        <td class="info-label">Teléfono:</td>
        <td>{{ $clienteTel }}</td>
    </tr>
    @endif
</table>

@if ($depPrvDst || $dirAgencia)
<div class="sep-dashed"></div>

{{-- ══ DATOS DE ENVÍO ══ --}}
<div class="seccion-titulo">Datos de envío</div>
@if ($depPrvDst)
<div class="envio-campo">
    <div class="envio-label">Dep / Prov / Dist:</div>
    <div class="envio-valor">{{ $depPrvDst }}</div>
</div>
@endif
@if ($dirAgencia)
<div class="envio-campo">
    <div class="envio-label">Agencia:</div>
    <div class="envio-valor">{{ $dirAgencia }}</div>
</div>
@endif
@endif

<div class="sep-dashed"></div>

{{-- ══ ÍTEMS ══ --}}
<div class="seccion-titulo">Productos</div>
<table class="items-table">
    <thead>
        <tr>
            <th>Cant</th>
            <th>Descripción</th>
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
        @endphp
        <tr>
            <td class="cant">{{ $cantFmt }}</td>
            <td>{{ $d->descripcion }}</td>
            <td class="r">S/ {{ number_format($d->total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- ══ PIE ══ --}}
<div class="pie">
    Documento de despacho interno · No constituye comprobante de pago
</div>

</div>{{-- /wrap --}}
</body>
</html>
