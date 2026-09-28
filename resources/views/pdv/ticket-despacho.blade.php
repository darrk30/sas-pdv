<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 10mm 8mm; }

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 9pt;
    color: #000;
    line-height: 1.35;
}

.wrap {
    margin: 0 6mm;
}


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
}
.items-table th.r { text-align: right; }
.items-table td {
    padding: 1.2mm 1mm;
    vertical-align: top;
    border-bottom: 1px solid #ddd;
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

    // Geo: nuevas columnas primero, fallback a notas_internas para órdenes antiguas
    $dept = $orden?->orden_departamento;
    $prov = $orden?->orden_provincia;
    $dist = $orden?->orden_distrito;
    if ($dept || $prov || $dist) {
        $geoTexto = implode(' / ', array_filter([$dept, $prov, $dist]));
    } elseif ($orden?->notas_internas) {
        $geoTexto = $orden->notas_internas;
    } else {
        $geoTexto = null;
    }

    $dirAgencia        = $orden?->direccion_agencia;
    $trackingCode      = $orden?->tracking_code;
    $codigoRetiro      = $orden?->codigo_retiro;
    $despachoDireccion = $venta->despacho_direccion;

    $metodoTipo = $orden?->metodoEnvio?->tipo; // 'delivery' | 'provincial' | 'retiro' | null

    $seccionEnvio = match($metodoTipo) {
        'delivery'   => 'Envío · Delivery',
        'provincial' => 'Envío Provincial',
        'retiro'     => 'Retiro en tienda',
        default      => 'Datos de envío',
    };
    $dirLabel = match($metodoTipo) {
        'delivery'   => 'Dirección de entrega',
        'provincial' => 'Agencia',
        'retiro'     => 'Punto de retiro',
        default      => 'Dirección',
    };
    $dirRetiro = ($metodoTipo === 'retiro') ? ($orden?->metodoEnvio?->direccion_retiro) : null;

    $hayEnvio = $geoTexto || $dirAgencia || $dirRetiro || $trackingCode || $codigoRetiro || $despachoDireccion;
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

<div style="margin:2mm 0"></div>

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

@if ($hayEnvio)
<div style="margin:2mm 0"></div>

{{-- ══ DATOS DE ENVÍO ══ --}}
<div class="seccion-titulo">{{ $seccionEnvio }}</div>
@if ($geoTexto)
<div class="envio-campo">
    <div class="envio-label">Dep / Prov / Dist:</div>
    <div class="envio-valor">{{ $geoTexto }}</div>
</div>
@endif
@if ($dirAgencia)
<div class="envio-campo">
    <div class="envio-label">{{ $dirLabel }}:</div>
    <div class="envio-valor">{{ $dirAgencia }}</div>
</div>
@endif
@if ($dirRetiro && !$dirAgencia)
<div class="envio-campo">
    <div class="envio-label">Dirección de retiro:</div>
    <div class="envio-valor">{{ $dirRetiro }}</div>
</div>
@endif
@if ($trackingCode)
<div class="envio-campo">
    <div class="envio-label">Código de rastreo:</div>
    <div class="envio-valor">{{ $trackingCode }}</div>
</div>
@endif
@if ($codigoRetiro)
<div class="envio-campo">
    <div class="envio-label">Clave de recojo:</div>
    <div class="envio-valor">{{ $codigoRetiro }}</div>
</div>
@endif
@if ($despachoDireccion && !$dirAgencia && !$dirRetiro)
<div class="envio-campo">
    <div class="envio-label">Dirección / Lugar:</div>
    <div class="envio-valor">{{ $despachoDireccion }}</div>
</div>
@endif
@endif

<div style="margin:2mm 0"></div>

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
